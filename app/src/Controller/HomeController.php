<?php

namespace App\Controller;

use App\Entity\ParkingReservation;
use App\Entity\User;
use App\Repository\ParkingReservationRepository;
use App\Repository\ParkingSpotAssignmentRepository;
use App\Repository\ParkingSpotRepository;
use App\Repository\UserRepository;
use App\Service\ReservationPolicy;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class HomeController extends AbstractController
{
    public function __construct(
        private readonly ParkingSpotRepository $parkingSpots,
        private readonly ParkingSpotAssignmentRepository $assignments,
        private readonly ParkingReservationRepository $reservations,
        private readonly UserRepository $users,
        private readonly EntityManagerInterface $entityManager,
        private readonly ReservationPolicy $policy,
    ) {
    }

    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function index(Request $request): Response
    {
        /** @var User|null $user */
        $user = $this->getUser();

        $selectedDate = \DateTimeImmutable::createFromFormat('Y-m-d', (string) $request->query->get('date', ''));
        if (!$selectedDate) {
            $selectedDate = $this->policy->today();
        }

        $assignment = null;
        if ($user instanceof User) {
            $assignment = $this->assignments->findUserAssignmentForDate($user, $selectedDate);
        }

        $allSpots = $this->parkingSpots->findBy([], ['name' => 'ASC']);
        $reserved = $this->reservations->findByDate($selectedDate);
        $takenSpotIds = [];
        foreach ($reserved as $reservation) {
            $takenSpotIds[$reservation->getParkingSpot()->getId()] = true;
        }

        $activeAssignments = $this->assignments->findActiveForDate($selectedDate);
        $lockedSpotIds = [];
        foreach ($activeAssignments as $activeAssignment) {
            $spotId = $activeAssignment->getParkingSpot()->getId();
            if ($this->policy->isAssignmentLockedForOthers($selectedDate) && (!$assignment || $assignment->getParkingSpot()->getId() !== $spotId)) {
                $lockedSpotIds[$spotId] = true;
            }
        }

        $availableSpots = [];
        foreach ($allSpots as $spot) {
            if (isset($takenSpotIds[$spot->getId()]) || isset($lockedSpotIds[$spot->getId()])) {
                continue;
            }
            $availableSpots[] = $spot;
        }

        $canReserveFree = $this->policy->isWithinFreeWindow($selectedDate);
        $canConfirmAssigned = $assignment && $this->policy->canManageAssignedSpot($selectedDate);

        return $this->render('home/index.html.twig', [
            'user' => $user,
            'selectedDate' => $selectedDate,
            'availableSpots' => $availableSpots,
            'canReserveFree' => $canReserveFree,
            'assignment' => $assignment,
            'canConfirmAssigned' => $canConfirmAssigned,
            'users' => $this->users->findBy([], ['name' => 'ASC']),
        ]);
    }

    #[Route('/reservations/free', name: 'app_reservation_free', methods: ['POST'])]
    public function reserveFree(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $date = $this->mustParseDate((string) $request->request->get('date'));
        $this->validateCsrf($request, 'reserve-free-'.$date->format('Y-m-d'));

        if (!$this->policy->isWithinFreeWindow($date)) {
            $this->addFlash('error', 'Możesz rezerwować wolne miejsca tylko na dzisiaj i jutro.');

            return $this->redirectToRoute('app_home', ['date' => $date->format('Y-m-d')]);
        }

        if (!$this->ensureUserHasNoReservation($user, $date)) {
            $this->addFlash('error', 'Masz już rezerwację w tym dniu.');

            return $this->redirectToRoute('app_home', ['date' => $date->format('Y-m-d')]);
        }

        $spotId = (string) $request->request->get('spot_id');
        $spot = $this->parkingSpots->find($spotId);
        if (!$spot) {
            $this->addFlash('error', 'Nie znaleziono wskazanego miejsca.');

            return $this->redirectToRoute('app_home', ['date' => $date->format('Y-m-d')]);
        }

        if ($this->reservations->findSpotReservationForDate($spotId, $date)) {
            $this->addFlash('error', 'To miejsce jest już zarezerwowane.');

            return $this->redirectToRoute('app_home', ['date' => $date->format('Y-m-d')]);
        }

        $assigned = $this->assignments->findUserAssignmentForDate($user, $date);
        if ($assigned && $assigned->getParkingSpot()->getId() === $spotId) {
            $this->addFlash('error', 'Dla przypisanego miejsca użyj potwierdzenia lub przekazania miejsca.');

            return $this->redirectToRoute('app_home', ['date' => $date->format('Y-m-d')]);
        }

        foreach ($this->assignments->findActiveForDate($date) as $a) {
            if ($a->getParkingSpot()->getId() === $spotId && $this->policy->isAssignmentLockedForOthers($date)) {
                $this->addFlash('error', 'To miejsce jest czasowo zarezerwowane dla osoby przypisanej.');

                return $this->redirectToRoute('app_home', ['date' => $date->format('Y-m-d')]);
            }
        }

        $reservation = (new ParkingReservation())
            ->setParkingSpot($spot)
            ->setReservedForUser($user)
            ->setCreatedByUser($user)
            ->setReservationDate($date)
            ->setType('free');

        $this->entityManager->persist($reservation);
        $this->entityManager->flush();

        $this->addFlash('success', 'Wolne miejsce zostało zarezerwowane.');

        return $this->redirectToRoute('app_home', ['date' => $date->format('Y-m-d')]);
    }

    #[Route('/reservations/confirm-assigned', name: 'app_reservation_confirm_assigned', methods: ['POST'])]
    public function confirmAssigned(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $date = $this->mustParseDate((string) $request->request->get('date'));
        $this->validateCsrf($request, 'confirm-assigned-'.$date->format('Y-m-d'));

        if (!$this->policy->canManageAssignedSpot($date)) {
            $this->addFlash('error', 'Potwierdzenie przypisanego miejsca jest możliwe do 7 dni wprzód.');

            return $this->redirectToRoute('app_home', ['date' => $date->format('Y-m-d')]);
        }

        $assignment = $this->assignments->findUserAssignmentForDate($user, $date);
        if (!$assignment) {
            $this->addFlash('error', 'Nie masz przypisanego miejsca dla wybranego dnia.');

            return $this->redirectToRoute('app_home', ['date' => $date->format('Y-m-d')]);
        }

        if (!$this->ensureUserHasNoReservation($user, $date)) {
            $this->addFlash('error', 'Masz już rezerwację w tym dniu.');

            return $this->redirectToRoute('app_home', ['date' => $date->format('Y-m-d')]);
        }

        if ($this->reservations->findSpotReservationForDate($assignment->getParkingSpot()->getId(), $date)) {
            $this->addFlash('error', 'To miejsce jest już zarezerwowane.');

            return $this->redirectToRoute('app_home', ['date' => $date->format('Y-m-d')]);
        }

        $reservation = (new ParkingReservation())
            ->setParkingSpot($assignment->getParkingSpot())
            ->setReservedForUser($user)
            ->setCreatedByUser($user)
            ->setReservationDate($date)
            ->setType('assigned_confirmed');

        $this->entityManager->persist($reservation);
        $this->entityManager->flush();

        $this->addFlash('success', 'Przypisane miejsce zostało potwierdzone.');

        return $this->redirectToRoute('app_home', ['date' => $date->format('Y-m-d')]);
    }

    #[Route('/reservations/delegate-assigned', name: 'app_reservation_delegate_assigned', methods: ['POST'])]
    public function delegateAssigned(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $date = $this->mustParseDate((string) $request->request->get('date'));
        $this->validateCsrf($request, 'delegate-assigned-'.$date->format('Y-m-d'));

        if (!$this->policy->canManageAssignedSpot($date)) {
            $this->addFlash('error', 'Przekazanie miejsca jest możliwe do 7 dni wprzód.');

            return $this->redirectToRoute('app_home', ['date' => $date->format('Y-m-d')]);
        }

        $assignment = $this->assignments->findUserAssignmentForDate($user, $date);
        if (!$assignment) {
            $this->addFlash('error', 'Nie masz przypisanego miejsca dla wybranego dnia.');

            return $this->redirectToRoute('app_home', ['date' => $date->format('Y-m-d')]);
        }

        $targetUser = $this->users->find((string) $request->request->get('target_user_id'));
        if (!$targetUser instanceof User) {
            $this->addFlash('error', 'Nie znaleziono użytkownika docelowego.');

            return $this->redirectToRoute('app_home', ['date' => $date->format('Y-m-d')]);
        }

        if ($this->reservations->findSpotReservationForDate($assignment->getParkingSpot()->getId(), $date)) {
            $this->addFlash('error', 'To miejsce jest już zarezerwowane.');

            return $this->redirectToRoute('app_home', ['date' => $date->format('Y-m-d')]);
        }

        if (!$this->ensureUserHasNoReservation($targetUser, $date)) {
            $this->addFlash('error', 'Użytkownik docelowy ma już rezerwację w tym dniu.');

            return $this->redirectToRoute('app_home', ['date' => $date->format('Y-m-d')]);
        }

        $reservation = (new ParkingReservation())
            ->setParkingSpot($assignment->getParkingSpot())
            ->setReservedForUser($targetUser)
            ->setCreatedByUser($user)
            ->setReservationDate($date)
            ->setType('assigned_delegated');

        $this->entityManager->persist($reservation);
        $this->entityManager->flush();

        $this->addFlash('success', 'Przypisane miejsce zostało przekazane innej osobie.');

        return $this->redirectToRoute('app_home', ['date' => $date->format('Y-m-d')]);
    }

    private function ensureUserHasNoReservation(User $user, \DateTimeImmutable $date): bool
    {
        return null === $this->reservations->findUserReservationForDate($user, $date);
    }

    private function mustParseDate(string $date): \DateTimeImmutable
    {
        $parsed = \DateTimeImmutable::createFromFormat('Y-m-d', $date);
        if (!$parsed) {
            throw $this->createNotFoundException('Nieprawidłowa data.');
        }

        return $parsed;
    }

    private function validateCsrf(Request $request, string $tokenId): void
    {
        if (!$this->isCsrfTokenValid($tokenId, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Nieprawidłowy token CSRF.');
        }
    }
}
