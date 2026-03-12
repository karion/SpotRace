<?php

namespace App\Controller;

use App\Entity\ParkingSpot;
use App\Form\ParkingSpotType;
use App\Repository\ParkingSpotRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/parking-spots')]
class AdminParkingSpotController extends AbstractController
{
    public function __construct(
        private readonly ParkingSpotRepository $parkingSpots,
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

    #[Route('/{id}/delete', name: 'app_admin_parking_spot_delete', methods: ['POST'])]
    public function delete(ParkingSpot $parkingSpot): Response
    {
        $this->entityManager->remove($parkingSpot);
        $this->entityManager->flush();

        $this->addFlash('success', 'Miejsce postojowe zostało usunięte.');

        return $this->redirectToRoute('app_admin_parking_spot_index');
    }
}
