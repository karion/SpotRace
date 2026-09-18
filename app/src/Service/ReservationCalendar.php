<?php

namespace App\Service;

use App\Entity\ParkingSpotAssignment;
use App\Entity\User;
use App\Repository\CompanyParkingSpotRepository;
use App\Repository\ParkingReservationRepository;
use App\Repository\ParkingSpotAssignmentRepository;

class ReservationCalendar
{
    public function __construct(
        private readonly CompanyParkingSpotRepository $companySpots,
        private readonly ParkingSpotAssignmentRepository $assignments,
        private readonly ParkingReservationRepository $reservations,
        private readonly ReservationPolicy $policy,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function days(User $user): array
    {
        if (!$user->getCompany()) {
            return [];
        }

        $days = [];
        $company = $user->getCompany();
        $today = $this->policy->today();
        $windowDays = max($this->policy->assignedWindowDays($company), $this->policy->freeReservationWindowDays($company));
        $rangeEnd = $today->modify(sprintf('+%d days', $windowDays));

        $companySpotsByDate = [];
        foreach ($this->companySpots->findActiveForCompanyInRange($user->getCompany(), $today, $rangeEnd) as $companySpot) {
            foreach ($this->expandCompanySpotDates($companySpot, $today, $rangeEnd) as $dateKey) {
                $companySpotsByDate[$dateKey][$companySpot->getParkingSpot()->getId()] = $companySpot->getParkingSpot();
            }
        }

        $userAssignmentsByDate = [];
        foreach ($this->assignments->findUserAssignmentsInRange($user, $today, $rangeEnd) as $assignment) {
            foreach ($this->expandAssignmentDates($assignment, $today, $rangeEnd) as $dateKey) {
                $userAssignmentsByDate[$dateKey] = $assignment;
            }
        }

        $reservationsByDate = [];
        $userReservationsByDate = [];
        foreach ($this->reservations->findByDateRange($today, $rangeEnd) as $reservation) {
            $dateKey = $reservation->getReservationDate()->format('Y-m-d');
            $reservationsByDate[$dateKey][] = $reservation;

            if ($reservation->getReservedForUser()->getId() === $user->getId()) {
                $userReservationsByDate[$dateKey] = $reservation;
            }
        }

        $activeAssignmentsByDate = [];
        foreach ($this->assignments->findActiveInRange($today, $rangeEnd) as $activeAssignment) {
            foreach ($this->expandAssignmentDates($activeAssignment, $today, $rangeEnd) as $dateKey) {
                $activeAssignmentsByDate[$dateKey][] = $activeAssignment;
            }
        }

        for ($offset = 0; $offset <= $windowDays; ++$offset) {
            $date = $today->modify(sprintf('+%d days', $offset));
            $isWithinFreeWindow = $offset <= $this->policy->freeReservationWindowDays($company);
            $dateKey = $date->format('Y-m-d');

            $assignment = $userAssignmentsByDate[$dateKey] ?? null;
            if ($assignment && !isset($companySpotsByDate[$dateKey][$assignment->getParkingSpot()->getId()])) {
                $assignment = null;
            }
            $userReservation = $userReservationsByDate[$dateKey] ?? null;

            $assignedSpotReserved = false;
            if ($assignment) {
                foreach ($reservationsByDate[$dateKey] ?? [] as $reservation) {
                    if ($reservation->getParkingSpot()->getId() === $assignment->getParkingSpot()->getId()) {
                        $assignedSpotReserved = true;
                        break;
                    }
                }
            }

            $allSpots = array_values($companySpotsByDate[$dateKey] ?? []);
            if ($assignment && !array_key_exists($assignment->getParkingSpot()->getId(), $companySpotsByDate[$dateKey] ?? [])) {
                $allSpots[] = $assignment->getParkingSpot();
            }

            if (!$isWithinFreeWindow && !$assignment && !$userReservation) {
                continue;
            }

            $availableSpots = [];
            $takenSpotIds = [];
            foreach ($reservationsByDate[$dateKey] ?? [] as $reservation) {
                $takenSpotIds[$reservation->getParkingSpot()->getId()] = true;
            }

            $assignedSpotIds = [];
            foreach ($activeAssignmentsByDate[$dateKey] ?? [] as $activeAssignment) {
                $assignedSpotIds[$activeAssignment->getParkingSpot()->getId()] = true;
            }

            $spotStatuses = [];
            foreach ($allSpots as $spot) {
                $spotId = $spot->getId();
                if ($assignment?->getParkingSpot()->getId() === $spotId) {
                    $spotStatuses[$spotId] = $this->policy->isAssignmentLockedForOthers($date, $company) ? 'assigned_own' : 'assigned_available';
                } elseif (isset($takenSpotIds[$spotId])) {
                    $spotStatuses[$spotId] = 'reserved';
                } elseif (isset($assignedSpotIds[$spotId])) {
                    $spotStatuses[$spotId] = $this->policy->isAssignmentLockedForOthers($date, $company) ? 'assigned' : 'assigned_available';
                } else {
                    $spotStatuses[$spotId] = 'available';
                }
            }

            $locationsByKey = [];
            foreach ($allSpots as $spot) {
                $location = $spot->getLocation();
                $locationKey = $location?->getId() ?? 'without-location';
                $locationsByKey[$locationKey] ??= [
                    'name' => $location?->getName() ?? 'Bez lokalizacji',
                    'spots' => [],
                ];
                $locationsByKey[$locationKey]['spots'][] = $spot;
            }
            uasort($locationsByKey, static fn (array $left, array $right): int => strnatcasecmp($left['name'], $right['name']));

            if ($isWithinFreeWindow) {
                $lockedSpotIds = [];
                foreach ($activeAssignmentsByDate[$dateKey] ?? [] as $activeAssignment) {
                    $spotId = $activeAssignment->getParkingSpot()->getId();
                    if ($this->policy->isAssignmentLockedForOthers($date, $company) && (!$assignment || $assignment->getParkingSpot()->getId() !== $spotId)) {
                        $lockedSpotIds[$spotId] = true;
                    }
                }

                foreach ($allSpots as $spot) {
                    $isOwnAssignedSpot = $assignment?->getParkingSpot()->getId() === $spot->getId();
                    if (isset($takenSpotIds[$spot->getId()]) || isset($lockedSpotIds[$spot->getId()]) || ($isOwnAssignedSpot && $this->policy->isAssignmentLockedForOthers($date, $company))) {
                        continue;
                    }
                    $availableSpots[] = $spot;
                }
            }

            $days[] = [
                'date' => $date,
                'displayDate' => $this->formatPolishShortDate($date),
                'isWithinFreeWindow' => $isWithinFreeWindow,
                'assignment' => $assignment,
                'userReservation' => $userReservation,
                'canManageAssigned' => $assignment && $this->policy->canManageAssignedSpot($date, $company) && !$assignedSpotReserved && !$userReservation,
                'assignedSpotReserved' => $assignedSpotReserved,
                'canReserveFree' => $isWithinFreeWindow && !$userReservation,
                'canReleaseReservation' => $userReservation && $this->policy->canReleaseReservation($date, $company),
                'availableSpots' => $availableSpots,
                'spots' => $allSpots,
                'locations' => array_values($locationsByKey),
                'spotStatuses' => $spotStatuses,
            ];
        }

        return $days;
    }

    /** @return array<int, string> */
    private function expandCompanySpotDates(\App\Entity\CompanyParkingSpot $companySpot, \DateTimeImmutable $rangeStart, \DateTimeImmutable $rangeEnd): array
    {
        $start = $this->policy->parseDate($companySpot->getStartsAt()->format('Y-m-d'));
        $end = $companySpot->getEndsAt() ? $this->policy->parseDate($companySpot->getEndsAt()->format('Y-m-d')) : $rangeEnd;
        $date = max($start, $rangeStart);
        $endDate = min($end, $rangeEnd);

        $dates = [];
        while ($date <= $endDate) {
            $dates[] = $date->format('Y-m-d');
            $date = $date->modify('+1 day');
        }

        return $dates;
    }

    /** @return array<int, string> */
    private function expandAssignmentDates(ParkingSpotAssignment $assignment, \DateTimeImmutable $rangeStart, \DateTimeImmutable $rangeEnd): array
    {
        $start = $this->policy->parseDate($assignment->getStartsAt()->format('Y-m-d'));
        $end = $assignment->getEndsAt() ? $this->policy->parseDate($assignment->getEndsAt()->format('Y-m-d')) : $rangeEnd;
        $date = max($start, $rangeStart);
        $endDate = min($end, $rangeEnd);

        $dates = [];
        while ($date <= $endDate) {
            $dates[] = $date->format('Y-m-d');
            $date = $date->modify('+1 day');
        }

        return $dates;
    }

    private function formatPolishShortDate(\DateTimeImmutable $date): string
    {
        $days = [
            'pon.',
            'wt.',
            'śr.',
            'czw.',
            'pt.',
            'sob.',
            'niedz.',
        ];

        $dayName = $days[(int) $date->format('N') - 1];

        return sprintf('%s %s', $dayName, $date->format('d/m'));
    }
}
