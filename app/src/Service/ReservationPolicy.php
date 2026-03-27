<?php

namespace App\Service;

class ReservationPolicy
{
    public function __construct(
        private readonly int $confirmationDeadlineHour,
        private readonly int $assignedWindowDays,
        private readonly int $freeReservationWindowDays,
        private readonly string $timezone,
    ) {
    }

    public function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now', new \DateTimeZone($this->timezone));
    }

    public function today(): \DateTimeImmutable
    {
        return $this->now()->setTime(0, 0);
    }

    public function confirmationCutoff(\DateTimeImmutable $date): \DateTimeImmutable
    {
        return $date->setTime($this->confirmationDeadlineHour, 0);
    }

    public function formattedConfirmationDeadline(): string
    {
        return sprintf('%02d:00', $this->confirmationDeadlineHour);
    }

    public function assignedWindowDays(): int
    {
        return $this->assignedWindowDays;
    }

    public function freeReservationWindowDays(): int
    {
        return $this->freeReservationWindowDays;
    }

    public function isWithinAssignedWindow(\DateTimeImmutable $date): bool
    {
        $today = $this->today();
        $max = $today->modify(sprintf('+%d days', $this->assignedWindowDays));

        return $date >= $today && $date <= $max;
    }

    public function isWithinFreeWindow(\DateTimeImmutable $date): bool
    {
        $today = $this->today();
        $max = $today->modify(sprintf('+%d days', $this->freeReservationWindowDays));

        return $date >= $today && $date <= $max;
    }

    public function canManageAssignedSpot(\DateTimeImmutable $date): bool
    {
        if (!$this->isWithinAssignedWindow($date)) {
            return false;
        }

        $today = $this->today();
        if ($date > $today) {
            return true;
        }

        return $this->now() < $this->confirmationCutoff($today);
    }

    public function canReleaseReservation(\DateTimeImmutable $date): bool
    {
        $day = $date->setTime(0, 0);
        $today = $this->today();
        if ($day < $today) {
            return false;
        }

        if ($day > $today) {
            return true;
        }

        return $this->now() < $this->confirmationCutoff($today);
    }

    public function isAssignmentLockedForOthers(\DateTimeImmutable $date): bool
    {
        $today = $this->today();
        if ($date > $today) {
            return true;
        }

        return $this->now() < $this->confirmationCutoff($today);
    }
}
