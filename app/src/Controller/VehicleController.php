<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\Vehicle;
use App\Form\VehicleType;
use App\Repository\VehicleRepository;
use App\Service\VehicleManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/vehicles')]
class VehicleController extends AbstractController
{
    public function __construct(
        private readonly VehicleRepository $vehicles,
        private readonly VehicleManager $manager,
    ) {
    }

    #[Route('', name: 'app_vehicle_index', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        $user = $this->currentUser();
        $vehicle = new Vehicle();
        $form = $this->createForm(VehicleType::class, $vehicle);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->manager->save($vehicle, $user);
                $this->addFlash('success', 'Pojazd został dodany.');

                return $this->redirectToRoute('app_vehicle_index');
            } catch (\DomainException $exception) {
                $this->addFlash('error', $exception->getMessage());
            }
        }

        return $this->render('vehicle/index.html.twig', [
            'vehicles' => $this->vehicles->findByOwner($user),
            'createForm' => $form->createView(),
        ]);
    }

    #[Route('/{id}/edit', name: 'app_vehicle_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, string $id): Response
    {
        $user = $this->currentUser();
        $vehicle = $this->vehicles->findOneForOwner($id, $user);
        if (!$vehicle instanceof Vehicle) {
            throw $this->createNotFoundException('Nie znaleziono pojazdu.');
        }

        $form = $this->createForm(VehicleType::class, $vehicle, ['submit_label' => 'Zapisz zmiany']);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->manager->save($vehicle, $user);
                $this->addFlash('success', 'Pojazd został zaktualizowany.');

                return $this->redirectToRoute('app_vehicle_index');
            } catch (\DomainException $exception) {
                $this->addFlash('error', $exception->getMessage());
            }
        }

        return $this->render('vehicle/edit.html.twig', ['editForm' => $form->createView(), 'vehicle' => $vehicle]);
    }

    #[Route('/{id}/delete', name: 'app_vehicle_delete', methods: ['POST'])]
    public function delete(Request $request, string $id): Response
    {
        $user = $this->currentUser();
        $vehicle = $this->vehicles->findOneForOwner($id, $user);
        if (!$vehicle instanceof Vehicle) {
            throw $this->createNotFoundException('Nie znaleziono pojazdu.');
        }
        if (!$this->isCsrfTokenValid('delete-vehicle-'.$vehicle->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Nieprawidłowy token CSRF.');
        }

        $this->manager->delete($vehicle, $user);
        $this->addFlash('success', 'Pojazd został usunięty.');

        return $this->redirectToRoute('app_vehicle_index');
    }

    private function currentUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('Użytkownik musi być zalogowany.');
        }

        return $user;
    }
}
