<?php

namespace App\Service;

use App\Entity\Company;

class ReservationPolicy
{
    public function __construct(
        private readonly SettingsResolver $settings,
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

    public function confirmationCutoff(\DateTimeImmutable $date, ?Company $company = null): \DateTimeImmutable
    {
        return $date->setTime($this->confirmationDeadlineHour($company), 0);
    }

    public function formattedConfirmationDeadline(?Company $company = null): string
    {
        return sprintf('%02d:00', $this->confirmationDeadlineHour($company));
    }

    public function assignedWindowDays(?Company $company = null): int
    {
        return max(0, $this->settings->int(SettingKeys::RESERVATION_ASSIGNED_WINDOW_DAYS, $company));
    }

    public function freeReservationWindowDays(?Company $company = null): int
    {
        return max(0, $this->settings->int(SettingKeys::RESERVATION_FREE_WINDOW_DAYS, $company));
    }

    public function isWithinAssignedWindow(\DateTimeImmutable $date, ?Company $company = null): bool
    {
        $today = $this->today();
        $max = $today->modify(sprintf('+%d days', $this->assignedWindowDays($company)));

        return $date >= $today && $date <= $max;
    }

    public function isWithinFreeWindow(\DateTimeImmutable $date, ?Company $company = null): bool
    {
        $today = $this->today();
        $max = $today->modify(sprintf('+%d days', $this->freeReservationWindowDays($company)));

        return $date >= $today && $date <= $max;
    }

    public function canManageAssignedSpot(\DateTimeImmutable $date, ?Company $company = null): bool
    {
        if (!$this->isWithinAssignedWindow($date, $company)) {
            return false;
        }

        $today = $this->today();
        if ($date > $today) {
            return true;
        }

        return $this->now() < $this->confirmationCutoff($today, $company);
    }

    public function canReleaseReservation(\DateTimeImmutable $date, ?Company $company = null): bool
    {
        $day = $date->setTime(0, 0);
        $today = $this->today();
        if ($day < $today) {
            return false;
        }

        if ($day > $today) {
            return true;
        }

        return $this->now() < $this->confirmationCutoff($today, $company);
    }

    public function isAssignmentLockedForOthers(\DateTimeImmutable $date, ?Company $company = null): bool
    {
        $today = $this->today();
        if ($date > $today) {
            return true;
        }

        return $this->now() < $this->confirmationCutoff($today, $company);
    }

    private function confirmationDeadlineHour(?Company $company = null): int
    {
        return min(23, max(0, $this->settings->int(SettingKeys::RESERVATION_CONFIRMATION_DEADLINE_HOUR, $company)));
    }
}
