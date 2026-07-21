<?php

namespace App\Tests\Service;

use App\Entity\AppSetting;
use App\Entity\Company;
use App\Entity\CompanySetting;
use App\Repository\AppSettingRepository;
use App\Repository\CompanySettingRepository;
use App\Service\SettingsResolver;
use App\Service\SettingValueNormalizer;
use PHPUnit\Framework\TestCase;

class SettingsResolverTest extends TestCase
{
    public function testReturnsGlobalValueWhenCompanyOverrideDoesNotExist(): void
    {
        $company = (new Company())->setName('Acme')->setSlug('acme');
        $resolver = $this->resolver([$this->setting('reservation.free_window_days', AppSetting::TYPE_INT, 1)], []);

        self::assertSame(1, $resolver->int('reservation.free_window_days', $company));
    }

    public function testReturnsCompanyOverrideWhenItExists(): void
    {
        $company = (new Company())->setName('Acme')->setSlug('acme');
        $override = (new CompanySetting())
            ->setCompany($company)
            ->setKey('reservation.free_window_days')
            ->setValue(3);
        $resolver = $this->resolver([$this->setting('reservation.free_window_days', AppSetting::TYPE_INT, 1)], [
            'reservation.free_window_days' => $override,
        ]);

        self::assertSame(3, $resolver->int('reservation.free_window_days', $company));
    }

    public function testNormalizesStringListValues(): void
    {
        $resolver = $this->resolver([$this->setting('registration.allowed_email_domains', AppSetting::TYPE_STRING_LIST, 'ACME.test, example.com')], []);

        self::assertSame(['acme.test', 'example.com'], $resolver->stringList('registration.allowed_email_domains'));
    }

    public function testCachesGlobalSettingsForRequest(): void
    {
        $settings = [
            $this->setting('reservation.free_window_days', AppSetting::TYPE_INT, 1),
            $this->setting('reservation.assigned_window_days', AppSetting::TYPE_INT, 7),
        ];
        $appSettings = $this->createMock(AppSettingRepository::class);
        $appSettings->expects(self::once())->method('findForForm')->willReturn($settings);
        $companySettings = $this->createMock(CompanySettingRepository::class);
        $companySettings->expects(self::never())->method('findByCompanyIndexed');
        $resolver = new SettingsResolver($appSettings, $companySettings, new SettingValueNormalizer());

        self::assertSame(1, $resolver->int('reservation.free_window_days'));
        self::assertSame(7, $resolver->int('reservation.assigned_window_days'));
        self::assertSame(1, $resolver->int('reservation.free_window_days'));
    }

    public function testCachesCompanySettingsPerCompanyForRequest(): void
    {
        $company = (new Company())->setName('Acme')->setSlug('acme');
        $override = (new CompanySetting())
            ->setCompany($company)
            ->setKey('reservation.free_window_days')
            ->setValue(3);
        $appSettings = $this->createMock(AppSettingRepository::class);
        $appSettings->expects(self::once())->method('findForForm')->willReturn([
            $this->setting('reservation.free_window_days', AppSetting::TYPE_INT, 1),
        ]);
        $companySettings = $this->createMock(CompanySettingRepository::class);
        $companySettings->expects(self::once())->method('findByCompanyIndexed')->with($company)->willReturn([
            'reservation.free_window_days' => $override,
        ]);
        $resolver = new SettingsResolver($appSettings, $companySettings, new SettingValueNormalizer());

        self::assertSame(3, $resolver->int('reservation.free_window_days', $company));
        self::assertSame(3, $resolver->int('reservation.free_window_days', $company));
    }

    public function testThrowsWhenGlobalSettingIsMissing(): void
    {
        $appSettings = $this->createMock(AppSettingRepository::class);
        $appSettings->method('findForForm')->willReturn([]);
        $companySettings = $this->createMock(CompanySettingRepository::class);
        $resolver = new SettingsResolver($appSettings, $companySettings, new SettingValueNormalizer());

        $this->expectException(\RuntimeException::class);
        $resolver->int('missing.key');
    }

    private function setting(string $key, string $type, mixed $value): AppSetting
    {
        return (new AppSetting())
            ->setKey($key)
            ->setType($type)
            ->setValue($value)
            ->setLabel($key)
            ->setGroup('Test');
    }

    /**
     * @param array<int, AppSetting>        $settings
     * @param array<string, CompanySetting> $overrides
     */
    private function resolver(array $settings, array $overrides): SettingsResolver
    {
        $appSettings = $this->createMock(AppSettingRepository::class);
        $appSettings->method('findForForm')->willReturn($settings);
        $companySettings = $this->createMock(CompanySettingRepository::class);
        $companySettings->method('findByCompanyIndexed')->willReturn($overrides);

        return new SettingsResolver($appSettings, $companySettings, new SettingValueNormalizer());
    }
}
