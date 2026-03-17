<?php

namespace App\Controller;

use App\Entity\ParkingSpot;
use App\Entity\ParkingSpotAssignment;
use App\Entity\User;
use App\Form\ParkingSpotAssignmentType;
use App\Form\ParkingSpotType;
use App\Repository\ParkingSpotRepository;
use App\Service\ParkingSpotAssignmentManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/parking-spots')]
class AdminParkingSpotController extends AbstractController
{
    public function __construct(
        private readonly ParkingSpotRepository $parkingSpots,
        private readonly ParkingSpotAssignmentManager $assignmentManager,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('', name: 'app_admin_parking_spot_index', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        $parkingSpot = new ParkingSpot();
        $form = $this->createForm(ParkingSpotType::class, $parkingSpot, [
            'submit_label' => 'Dodaj miejsce',
            'description_rows' => 4,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($parkingSpot);
            $this->entityManager->flush();

            $this->addFlash('success', 'Miejsce postojowe zostało dodane.');

            return $this->redirectToRoute('app_admin_parking_spot_index');
        }

        return $this->render('admin/parking_spot/index.html.twig', [
            'spots' => $this->parkingSpots->findBy([], ['name' => 'ASC']),
            'createForm' => $form->createView(),
        ]);
    }

    #[Route('/{id}/edit', name: 'app_admin_parking_spot_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, ParkingSpot $parkingSpot): Response
    {
        $form = $this->createForm(ParkingSpotType::class, $parkingSpot, [
            'submit_label' => 'Zapisz zmiany',
            'description_rows' => 6,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();

            $this->addFlash('success', 'Miejsce postojowe zostało zaktualizowane.');

            return $this->redirectToRoute('app_admin_parking_spot_index');
        }

        return $this->render('admin/parking_spot/edit.html.twig', [
            'spot' => $parkingSpot,
            'editForm' => $form->createView(),
        ]);
    }

    #[Route('/{id}/assign', name: 'app_admin_parking_spot_assign', methods: ['GET', 'POST'])]
    public function assign(Request $request, ParkingSpot $parkingSpot): Response
    {
        $assignment = $this->assignmentManager->createDraft($parkingSpot);
        $form = $this->createForm(ParkingSpotAssignmentType::class, $assignment, $this->assignmentManager->formOptions('Zapisz przypisanie'));
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($this->assignmentManager->hasValidationErrors($form, $assignment)) {
                $this->addFlash('error', 'Popraw błędy w formularzu przypisania.');
            } else {
                $currentUser = $this->getUser();
                if (!$currentUser instanceof User) {
                    throw $this->createAccessDeniedException('Nie udało się ustalić zalogowanego użytkownika.');
                }

                $this->assignmentManager->create($assignment, $currentUser);
                $this->addFlash('success', 'Przypisanie miejsca zostało zapisane.');

                return $this->redirectToRoute('app_admin_parking_spot_assign', ['id' => $parkingSpot->getId()]);
            }
        }

        return $this->renderAssignmentPage($parkingSpot, $form->createView());
    }

    #[Route('/assignment/{assignment}/delete', name: 'app_admin_parking_spot_assignment_delete', methods: ['POST'])]
    public function deleteAssignment(Request $request, ParkingSpotAssignment $assignment): Response
    {
        $parkingSpot = $assignment->getParkingSpot();

        if (!$this->isCsrfTokenValid('delete-assignment-'.$assignment->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Nieprawidłowy token CSRF.');
        }

        $this->entityManager->remove($assignment);
        $this->entityManager->flush();

        $this->addFlash('success', 'Przypisanie miejsca zostało usunięte.');

        return $this->redirectToRoute('app_admin_parking_spot_assign', ['id' => $parkingSpot->getId()]);
    }

    #[Route('/assignment/{assignment}/edit', name: 'app_admin_parking_spot_assignment_edit', methods: ['GET', 'POST'])]
    public function editAssignment(Request $request, ParkingSpotAssignment $assignment): Response
    {
        $parkingSpot = $assignment->getParkingSpot();
        $form = $this->createForm(ParkingSpotAssignmentType::class, $assignment, $this->assignmentManager->formOptions('Zapisz zmiany'));
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($this->assignmentManager->hasValidationErrors($form, $assignment, $assignment->getId())) {
                $this->addFlash('error', 'Popraw błędy w formularzu przypisania.');
            } else {
                $this->assignmentManager->update();
                $this->addFlash('success', 'Przypisanie miejsca zostało zaktualizowane.');

                return $this->redirectToRoute('app_admin_parking_spot_assign', ['id' => $parkingSpot->getId()]);
            }
        }

        return $this->renderAssignmentPage($parkingSpot, $form->createView(), $assignment);
    }

    #[Route('/{id}/delete', name: 'app_admin_parking_spot_delete', methods: ['POST'])]
    public function delete(ParkingSpot $parkingSpot): Response
    {
        $this->entityManager->remove($parkingSpot);
        $this->entityManager->flush();

        $this->addFlash('success', 'Miejsce postojowe zostało usunięte.');

        return $this->redirectToRoute('app_admin_parking_spot_index');
    }

    private function renderAssignmentPage(ParkingSpot $parkingSpot, FormView $assignmentForm, ?ParkingSpotAssignment $editingAssignment = null): Response
    {
        return $this->render('admin/parking_spot/assign.html.twig', [
            'spot' => $parkingSpot,
            'assignmentForm' => $assignmentForm,
            'assignments' => $this->assignmentManager->historyForSpot($parkingSpot),
            'editingAssignment' => $editingAssignment,
        ]);
    }
}
