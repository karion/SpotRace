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
        $resolver = $this->resolver($this->setting('reservation.free_window_days', AppSetting::TYPE_INT, 1), null);

        self::assertSame(1, $resolver->int('reservation.free_window_days', $company));
    }

    public function testReturnsCompanyOverrideWhenItExists(): void
    {
        $company = (new Company())->setName('Acme')->setSlug('acme');
        $override = (new CompanySetting())
            ->setCompany($company)
            ->setKey('reservation.free_window_days')
            ->setValue(3);
        $resolver = $this->resolver($this->setting('reservation.free_window_days', AppSetting::TYPE_INT, 1), $override);

        self::assertSame(3, $resolver->int('reservation.free_window_days', $company));
    }

    public function testNormalizesStringListValues(): void
    {
        $resolver = $this->resolver($this->setting('registration.allowed_email_domains', AppSetting::TYPE_STRING_LIST, 'ACME.test, example.com'), null);

        self::assertSame(['acme.test', 'example.com'], $resolver->stringList('registration.allowed_email_domains'));
    }

    public function testThrowsWhenGlobalSettingIsMissing(): void
    {
        $appSettings = $this->createMock(AppSettingRepository::class);
        $appSettings->method('findOneByKey')->willReturn(null);
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

    private function resolver(AppSetting $setting, ?CompanySetting $override): SettingsResolver
    {
        $appSettings = $this->createMock(AppSettingRepository::class);
        $appSettings->method('findOneByKey')->willReturn($setting);
        $companySettings = $this->createMock(CompanySettingRepository::class);
        $companySettings->method('findOneByCompanyAndKey')->willReturn($override);

        return new SettingsResolver($appSettings, $companySettings, new SettingValueNormalizer());
    }
}
