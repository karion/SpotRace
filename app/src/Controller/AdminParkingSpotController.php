<?php

namespace App\Controller;

use App\Entity\ParkingSpot;
use App\Entity\ParkingSpotAssignment;
use App\Entity\User;
use App\Form\ParkingSpotType;
use App\Repository\ParkingSpotAssignmentRepository;
use App\Repository\ParkingSpotRepository;
use App\Repository\UserRepository;
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
        private readonly ParkingSpotAssignmentRepository $assignments,
        private readonly UserRepository $users,
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
        if ('POST' === $request->getMethod()) {
            if (!$this->isCsrfTokenValid('assign-spot-'.$parkingSpot->getId(), (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException('Nieprawidłowy token CSRF.');
            }

            $assignedUser = $this->users->find((string) $request->request->get('assigned_user_id'));
            if (!$assignedUser instanceof User) {
                $this->addFlash('error', 'Nie znaleziono użytkownika do przypisania.');

                return $this->redirectToRoute('app_admin_parking_spot_assign', ['id' => $parkingSpot->getId()]);
            }

            $startsAt = \DateTimeImmutable::createFromFormat('Y-m-d', (string) $request->request->get('starts_at'));
            if (!$startsAt) {
                $this->addFlash('error', 'Nieprawidłowa data początku przypisania.');

                return $this->redirectToRoute('app_admin_parking_spot_assign', ['id' => $parkingSpot->getId()]);
            }

            $endsAtRaw = trim((string) $request->request->get('ends_at', ''));
            $endsAt = null;
            if ('' !== $endsAtRaw) {
                $endsAt = \DateTimeImmutable::createFromFormat('Y-m-d', $endsAtRaw);
                if (!$endsAt) {
                    $this->addFlash('error', 'Nieprawidłowa data zakończenia przypisania.');

                    return $this->redirectToRoute('app_admin_parking_spot_assign', ['id' => $parkingSpot->getId()]);
                }

                if ($endsAt < $startsAt) {
                    $this->addFlash('error', 'Data zakończenia nie może być wcześniejsza niż data początku.');

                    return $this->redirectToRoute('app_admin_parking_spot_assign', ['id' => $parkingSpot->getId()]);
                }
            }

            if ($this->assignments->hasOverlappingAssignmentForSpot($parkingSpot->getId(), $startsAt, $endsAt)) {
                $this->addFlash('error', 'To miejsce ma już przypisanie w podanym zakresie dat.');

                return $this->redirectToRoute('app_admin_parking_spot_assign', ['id' => $parkingSpot->getId()]);
            }

            /** @var User $currentUser */
            $currentUser = $this->getUser();
            $assignment = (new ParkingSpotAssignment())
                ->setParkingSpot($parkingSpot)
                ->setAssignedUser($assignedUser)
                ->setAssignedByUser($currentUser)
                ->setStartsAt($startsAt)
                ->setEndsAt($endsAt);

            $this->entityManager->persist($assignment);
            $this->entityManager->flush();

            $this->addFlash('success', 'Przypisanie miejsca zostało zapisane.');

            return $this->redirectToRoute('app_admin_parking_spot_assign', ['id' => $parkingSpot->getId()]);
        }

        return $this->render('admin/parking_spot/assign.html.twig', [
            'spot' => $parkingSpot,
            'users' => $this->users->findBy([], ['name' => 'ASC']),
            'assignments' => $this->assignments->findByParkingSpot($parkingSpot->getId()),
        ]);
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

        if ('POST' === $request->getMethod()) {
            if (!$this->isCsrfTokenValid('edit-assignment-'.$assignment->getId(), (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException('Nieprawidłowy token CSRF.');
            }

            $assignedUser = $this->users->find((string) $request->request->get('assigned_user_id'));
            if (!$assignedUser instanceof User) {
                $this->addFlash('error', 'Nie znaleziono użytkownika do przypisania.');

                return $this->redirectToRoute('app_admin_parking_spot_assignment_edit', ['assignment' => $assignment->getId()]);
            }

            $startsAt = \DateTimeImmutable::createFromFormat('Y-m-d', (string) $request->request->get('starts_at'));
            if (!$startsAt) {
                $this->addFlash('error', 'Nieprawidłowa data początku przypisania.');

                return $this->redirectToRoute('app_admin_parking_spot_assignment_edit', ['assignment' => $assignment->getId()]);
            }

            $endsAtRaw = trim((string) $request->request->get('ends_at', ''));
            $endsAt = null;
            if ('' !== $endsAtRaw) {
                $endsAt = \DateTimeImmutable::createFromFormat('Y-m-d', $endsAtRaw);
                if (!$endsAt) {
                    $this->addFlash('error', 'Nieprawidłowa data zakończenia przypisania.');

                    return $this->redirectToRoute('app_admin_parking_spot_assignment_edit', ['assignment' => $assignment->getId()]);
                }

                if ($endsAt < $startsAt) {
                    $this->addFlash('error', 'Data zakończenia nie może być wcześniejsza niż data początku.');

                    return $this->redirectToRoute('app_admin_parking_spot_assignment_edit', ['assignment' => $assignment->getId()]);
                }
            }

            if ($this->assignments->hasOverlappingAssignmentForSpot($parkingSpot->getId(), $startsAt, $endsAt, $assignment->getId())) {
                $this->addFlash('error', 'To miejsce ma już przypisanie w podanym zakresie dat.');

                return $this->redirectToRoute('app_admin_parking_spot_assignment_edit', ['assignment' => $assignment->getId()]);
            }

            $assignment
                ->setAssignedUser($assignedUser)
                ->setStartsAt($startsAt)
                ->setEndsAt($endsAt);

            $this->entityManager->flush();

            $this->addFlash('success', 'Przypisanie miejsca zostało zaktualizowane.');

            return $this->redirectToRoute('app_admin_parking_spot_assign', ['id' => $parkingSpot->getId()]);
        }

        return $this->render('admin/parking_spot/assign.html.twig', [
            'spot' => $parkingSpot,
            'users' => $this->users->findBy([], ['name' => 'ASC']),
            'assignments' => $this->assignments->findByParkingSpot($parkingSpot->getId()),
            'editingAssignment' => $assignment,
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
