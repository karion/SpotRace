<?php

namespace App\Controller;

use App\Entity\Company;
use App\Entity\ParkingSpot;
use App\Entity\ParkingSpotAssignment;
use App\Entity\User;
use App\Form\ParkingSpotAssignmentType;
use App\Repository\CompanyParkingSpotRepository;
use App\Service\ParkingSpotAssignmentManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/company/parking-spots')]
class CompanyParkingSpotController extends AbstractController
{
    public function __construct(
        private readonly CompanyParkingSpotRepository $companySpots,
        private readonly ParkingSpotAssignmentManager $assignmentManager,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('', name: 'app_company_parking_spot_index', methods: ['GET'])]
    public function index(): Response
    {
        $today = (new \DateTimeImmutable())->setTime(0, 0);
        $company = $this->currentCompany();

        return $this->render('company/parking_spot/index.html.twig', [
            'company' => $company,
            'companySpots' => $this->companySpots->findActiveForCompanyInRange($company, $today, $today),
        ]);
    }

    #[Route('/{id}/assign', name: 'app_company_parking_spot_assign', methods: ['GET', 'POST'])]
    public function assign(Request $request, ParkingSpot $parkingSpot): Response
    {
        $company = $this->currentCompany();
        if (!$this->companySpots->findActiveForSpot($parkingSpot, (new \DateTimeImmutable())->setTime(0, 0))) {
            throw $this->createNotFoundException('Miejsce nie jest aktywnie przypisane do firmy.');
        }

        $assignment = $this->assignmentManager->createDraft($parkingSpot);
        $form = $this->createForm(ParkingSpotAssignmentType::class, $assignment, $this->assignmentManager->formOptions('Zapisz przypisanie', $company));
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($this->assignmentManager->hasValidationErrors($form, $assignment, null, $company)) {
                $this->addFlash('error', 'Popraw błędy w formularzu przypisania.');
            } else {
                $this->assignmentManager->create($assignment, $this->currentUser());
                $this->addFlash('success', 'Przypisanie miejsca zostało zapisane.');

                return $this->redirectToRoute('app_company_parking_spot_assign', ['id' => $parkingSpot->getId()]);
            }
        }

        return $this->renderAssignmentPage($parkingSpot, $form->createView());
    }

    #[Route('/assignment/{assignment}/delete', name: 'app_company_parking_spot_assignment_delete', methods: ['POST'])]
    public function deleteAssignment(Request $request, ParkingSpotAssignment $assignment): Response
    {
        $this->denyWhenSpotOutsideCompany($assignment->getParkingSpot());
        if (!$this->isCsrfTokenValid('delete-assignment-'.$assignment->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Nieprawidłowy token CSRF.');
        }

        $parkingSpot = $assignment->getParkingSpot();
        $this->entityManager->remove($assignment);
        $this->entityManager->flush();
        $this->addFlash('success', 'Przypisanie miejsca zostało usunięte.');

        return $this->redirectToRoute('app_company_parking_spot_assign', ['id' => $parkingSpot->getId()]);
    }

    private function renderAssignmentPage(ParkingSpot $parkingSpot, FormView $assignmentForm): Response
    {
        $this->denyWhenSpotOutsideCompany($parkingSpot);

        return $this->render('company/parking_spot/assign.html.twig', [
            'spot' => $parkingSpot,
            'assignmentForm' => $assignmentForm,
            'assignments' => $this->assignmentManager->historyForSpotAndCompany($parkingSpot, $this->currentCompany()),
        ]);
    }

    private function currentUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User || !$user->isCompanyAdmin()) {
            throw $this->createAccessDeniedException('Wymagana rola company admin.');
        }

        return $user;
    }

    private function currentCompany(): Company
    {
        $company = $this->currentUser()->getCompany();
        if (!$company instanceof Company) {
            throw $this->createAccessDeniedException('Konto company admin nie jest przypisane do firmy.');
        }

        return $company;
    }

    private function denyWhenSpotOutsideCompany(ParkingSpot $parkingSpot): void
    {
        $companySpot = $this->companySpots->findActiveForSpot($parkingSpot, (new \DateTimeImmutable())->setTime(0, 0));
        if (!$companySpot || $companySpot->getCompany()->getId() !== $this->currentCompany()->getId()) {
            throw $this->createAccessDeniedException('Nie możesz zarządzać miejscem innej firmy.');
        }
    }
}
