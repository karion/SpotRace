<?php

namespace App\Service;

use App\Entity\Company;
use App\Entity\ParkingReservation;
use App\Entity\ParkingSpot;
use App\Entity\ParkingSpotAssignment;
use App\Entity\User;
use App\Model\ReservationData;
use App\Repository\CompanyParkingSpotRepository;
use App\Repository\ParkingReservationRepository;
use App\Repository\ParkingSpotAssignmentRepository;
use App\Repository\ParkingSpotRepository;
use App\Repository\UserRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;

class ReservationManager
{
    public function __construct(
        private readonly ParkingSpotRepository $spots,
        private readonly CompanyParkingSpotRepository $companySpots,
        private readonly ParkingSpotAssignmentRepository $assignments,
        private readonly ParkingReservationRepository $reservations,
        private readonly UserRepository $users,
        private readonly ReservationPolicy $policy,
        private readonly SettingsResolver $settings,
        private readonly VehicleManager $vehicles,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function reserveFree(User $user, ReservationData $data): void
    {
        $company = $this->company($user);
        $date = $this->policy->parseDate($data->date);
        if (!$this->policy->isWithinFreeWindow($date, $company)) {
            throw new \DomainException('Wybrany dzień jest poza oknem rezerwowania wolnych miejsc.');
        }

        $spot = $data->spotId ? $this->spots->find($data->spotId) : null;
        if (!$spot instanceof ParkingSpot) {
            throw new \DomainException('Nie znaleziono wskazanego miejsca.');
        }
        $this->assertSpotInCompany($spot, $company, $date);
        $this->assertAvailable($spot, $user, $date);
        $assignment = $this->assignments->findUserAssignmentForDate($user, $date);
        if ($assignment && $assignment->getParkingSpot()->getId() === $spot->getId() && $this->policy->isAssignmentLockedForOthers($date, $company)) {
            throw new \DomainException('Dla przypisanego miejsca użyj potwierdzenia lub przekazania miejsca.');
        }
        foreach ($this->assignments->findActiveForDate($date) as $active) {
            if ($active->getParkingSpot()->getId() === $spot->getId() && $this->policy->isAssignmentLockedForOthers($date, $company)) {
                throw new \DomainException('To miejsce jest czasowo zarezerwowane dla osoby przypisanej.');
            }
        }

        $this->save($spot, $user, $user, $date, 'free', $data);
    }

    public function confirmAssigned(User $user, ReservationData $data): void
    {
        $date = $this->policy->parseDate($data->date);
        $assignment = $this->assignedSpot($user, $date);
        $this->assertAvailable($assignment->getParkingSpot(), $user, $date);
        $this->save($assignment->getParkingSpot(), $user, $user, $date, 'assigned_confirmed', $data);
    }

    public function delegateAssigned(User $user, ReservationData $data): void
    {
        $date = $this->policy->parseDate($data->date);
        $assignment = $this->assignedSpot($user, $date);
        $target = $data->targetUserId ? $this->users->find($data->targetUserId) : null;
        if (!$target instanceof User || $target->getCompany()?->getId() !== $this->company($user)->getId() || $target->getId() === $user->getId()) {
            throw new \DomainException('Wybierz innego użytkownika z Twojej firmy.');
        }
        $this->assertAvailable($assignment->getParkingSpot(), $target, $date);
        $this->save($assignment->getParkingSpot(), $target, $user, $date, 'assigned_delegated', $data);
    }

    public function release(User $user, ReservationData $data): void
    {
        $company = $this->company($user);
        $date = $this->policy->parseDate($data->date);
        if (!$this->policy->canReleaseReservation($date, $company)) {
            throw new \DomainException(sprintf('Zwolnienie miejsca jest możliwe do %s wybranego dnia.', $this->policy->formattedConfirmationDeadline($company)));
        }
        $reservation = $this->reservations->findUserReservationForDate($user, $date);
        if (!$reservation) {
            throw new \DomainException('Nie masz rezerwacji do zwolnienia w tym dniu.');
        }
        $this->entityManager->remove($reservation);
        $this->entityManager->flush();
    }

    public function assignedSpot(User $user, \DateTimeImmutable $date): ParkingSpotAssignment
    {
        $company = $this->company($user);
        if (!$this->policy->canManageAssignedSpot($date, $company)) {
            throw new \DomainException(sprintf('Potwierdzenie lub przekazanie miejsca jest możliwe do %d dni wprzód, a dzisiaj przed %s.', $this->policy->assignedWindowDays($company), $this->policy->formattedConfirmationDeadline($company)));
        }
        $assignment = $this->assignments->findUserAssignmentForDate($user, $date);
        if (!$assignment) {
            throw new \DomainException('Nie masz przypisanego miejsca dla wybranego dnia.');
        }
        $this->assertSpotInCompany($assignment->getParkingSpot(), $company, $date);

        return $assignment;
    }

    private function company(User $user): Company
    {
        return $user->getCompany() ?? throw new \DomainException('Konto nie jest przypisane do firmy.');
    }

    private function assertSpotInCompany(ParkingSpot $spot, Company $company, \DateTimeImmutable $date): void
    {
        if ($this->companySpots->findActiveForSpot($spot, $date)?->getCompany()->getId() !== $company->getId()) {
            throw new \DomainException('Miejsce nie należy do Twojej firmy w wybranym dniu.');
        }
    }

    private function assertAvailable(ParkingSpot $spot, User $target, \DateTimeImmutable $date): void
    {
        if ($this->reservations->findUserReservationForDate($target, $date)) {
            throw new \DomainException('Osoba korzystająca z miejsca ma już rezerwację w tym dniu.');
        }
        if ($this->reservations->findSpotReservationForDate($spot->getId(), $date)) {
            throw new \DomainException('To miejsce jest już zarezerwowane.');
        }
    }

    private function save(ParkingSpot $spot, User $target, User $actor, \DateTimeImmutable $date, string $type, ReservationData $data): void
    {
        $plate = $this->vehicles->resolveLicensePlate($target, $data->vehicleId, $data->licensePlate);
        if (null === $plate && $this->settings->bool(SettingKeys::RESERVATION_REQUIRE_LICENSE_PLATE, $this->company($target))) {
            throw new \DomainException('Podaj numer rejestracyjny przed zapisaniem rezerwacji.');
        }
        $reservation = (new ParkingReservation())
            ->setParkingSpot($spot)
            ->setReservedForUser($target)
            ->setCreatedByUser($actor)
            ->setReservationDate($date)
            ->setType($type)
            ->setLicensePlate($plate);
        $this->entityManager->persist($reservation);
        try {
            // Doctrine saves both the new vehicle and reservation in one flush transaction.
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException $exception) {
            throw new \DomainException('Nie udało się zapisać rezerwacji: miejsce, rezerwacja użytkownika lub pojazd zostały zapisane równocześnie. Odśwież stronę i spróbuj ponownie.', previous: $exception);
        }
    }
}
