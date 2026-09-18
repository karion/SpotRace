<?php

namespace App\Tests\Service;

use App\Service\ReservationPolicy;
use App\Service\SettingKeys;
use App\Service\SettingsResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ReservationPolicyTest extends TestCase
{
    /** @return iterable<string, array{string}> */
    public static function invalidDates(): iterable
    {
        foreach (['2026-02-30', '2026-13-01', '2026-1-01', '', "2026-01-01\0", '2026-09-18 00:00:00'] as $date) {
            yield $date => [$date];
        }
    }

    #[DataProvider('invalidDates')]
    public function testRejectsInvalidBusinessDates(string $value): void
    {
        $this->expectException(\DomainException::class);
        $this->policy('2026-09-18 06:59:59')->parseDate($value);
    }

    public function testParsesAtMidnightInConfiguredTimezone(): void
    {
        $date = $this->policy('2026-09-18 06:59:59')->parseDate('2026-09-18');
        self::assertSame('2026-09-18 00:00:00 Europe/Warsaw', $date->format('Y-m-d H:i:s e'));
    }

    public function testCutoffAndWindowBoundaries(): void
    {
        foreach (['06:59:59' => true, '07:00:00' => false, '07:00:01' => false] as $time => $before) {
            $policy = $this->policy('2026-09-18 '.$time);
            $today = $policy->today();
            self::assertSame($before, $policy->canManageAssignedSpot($today));
            self::assertSame($before, $policy->canReleaseReservation($today));
            self::assertSame($before, $policy->isAssignmentLockedForOthers($today));
            self::assertTrue($policy->isWithinFreeWindow($today));
            self::assertTrue($policy->isWithinFreeWindow($today->modify('+1 day')));
            self::assertFalse($policy->isWithinFreeWindow($today->modify('+2 days')));
            self::assertFalse($policy->isWithinFreeWindow($today->modify('-1 day')));
            self::assertTrue($policy->canManageAssignedSpot($today->modify('+7 days')));
            self::assertFalse($policy->canManageAssignedSpot($today->modify('+8 days')));
            self::assertFalse($policy->canManageAssignedSpot($today->modify('-1 day')));
            self::assertTrue($policy->canReleaseReservation($today->modify('+1 day')));
            self::assertFalse($policy->canReleaseReservation($today->modify('-1 day')));
        }
    }

    private function policy(string $time): ReservationPolicy
    {
        $settings = $this->createStub(SettingsResolver::class);
        $settings->method('int')->willReturnCallback(fn (string $key) => match ($key) {
            SettingKeys::RESERVATION_ASSIGNED_WINDOW_DAYS, SettingKeys::RESERVATION_CONFIRMATION_DEADLINE_HOUR => 7,
            SettingKeys::RESERVATION_FREE_WINDOW_DAYS => 1,
            default => throw new \LogicException('Unexpected setting: '.$key),
        });

        return new class($settings, $time) extends ReservationPolicy {
            public function __construct(SettingsResolver $settings, private readonly string $time)
            {
                parent::__construct($settings, 'Europe/Warsaw');
            }

            public function now(): \DateTimeImmutable
            {
                return new \DateTimeImmutable($this->time, new \DateTimeZone('Europe/Warsaw'));
            }
        };
    }
}
