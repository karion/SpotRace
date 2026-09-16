<?php

namespace App\Controller;

use App\Entity\ParkingLocation;
use App\Form\ParkingLocationType;
use App\Repository\ParkingLocationRepository;
use App\Service\ParkingLocationManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/locations')]
class AdminParkingLocationController extends AbstractController
{
    public function __construct(
        private readonly ParkingLocationRepository $locations,
        private readonly ParkingLocationManager $manager,
    ) {
    }

    #[Route('', name: 'app_admin_location_index', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        $location = new ParkingLocation();
        $form = $this->createForm(ParkingLocationType::class, $location, ['submit_label' => 'Dodaj lokalizację']);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->manager->save($location);
                $this->addFlash('success', 'Lokalizacja została dodana.');
            } catch (\DomainException $exception) {
                $this->addFlash('error', $exception->getMessage());
            }

            return $this->redirectToRoute('app_admin_location_index');
        }

        return $this->render('admin/location/index.html.twig', [
            'locations' => $this->locations->findBy([], ['name' => 'ASC']),
            'createForm' => $form->createView(),
        ]);
    }

    #[Route('/{id}/edit', name: 'app_admin_location_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, ParkingLocation $location): Response
    {
        $form = $this->createForm(ParkingLocationType::class, $location);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->manager->save($location);
                $this->addFlash('success', 'Lokalizacja została zaktualizowana.');
            } catch (\DomainException $exception) {
                $this->addFlash('error', $exception->getMessage());
            }

            return $this->redirectToRoute('app_admin_location_index');
        }

        return $this->render('admin/location/edit.html.twig', ['editForm' => $form->createView()]);
    }

    #[Route('/{id}/delete', name: 'app_admin_location_delete', methods: ['POST'])]
    public function delete(Request $request, ParkingLocation $location): Response
    {
        if (!$this->isCsrfTokenValid('delete-location-'.$location->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Nieprawidłowy token CSRF.');
        }

        try {
            $this->manager->delete($location);
            $this->addFlash('success', 'Lokalizacja została usunięta.');
        } catch (\DomainException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('app_admin_location_index');
    }
}
