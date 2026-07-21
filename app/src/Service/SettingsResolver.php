<?php

namespace App\Service;

use App\Entity\AppSetting;
use App\Entity\Company;
use App\Entity\CompanySetting;
use App\Repository\AppSettingRepository;
use App\Repository\CompanySettingRepository;

class SettingsResolver
{
    /** @var array<string, AppSetting>|null */
    private ?array $globalSettings = null;

    /** @var array<string, array<string, CompanySetting>> */
    private array $companySettingsByCompanyId = [];

    public function __construct(
        private readonly AppSettingRepository $appSettings,
        private readonly CompanySettingRepository $companySettings,
        private readonly SettingValueNormalizer $normalizer,
    ) {
    }

    public function get(string $key, ?Company $company = null): mixed
    {
        $global = $this->globalSetting($key);
        if (!$global instanceof AppSetting) {
            throw new \RuntimeException(sprintf('Brak globalnego ustawienia "%s".', $key));
        }

        $value = $global->getValue();
        if ($company instanceof Company) {
            $override = $this->companySetting($company, $key);
            if (null !== $override) {
                $value = $override->getValue();
            }
        }

        return $this->normalizer->normalize($value, $global->getType());
    }

    /** @return array<int, string> */
    public function stringList(string $key, ?Company $company = null): array
    {
        $value = $this->get($key, $company);
        if (!is_array($value)) {
            throw new \RuntimeException(sprintf('Ustawienie "%s" nie jest listą.', $key));
        }

        return array_values(array_map('strval', $value));
    }

    public function int(string $key, ?Company $company = null): int
    {
        return (int) $this->get($key, $company);
    }

    public function bool(string $key, ?Company $company = null): bool
    {
        return (bool) $this->get($key, $company);
    }

    private function globalSetting(string $key): ?AppSetting
    {
        if (null === $this->globalSettings) {
            $this->globalSettings = [];
            foreach ($this->appSettings->findForForm() as $setting) {
                $this->globalSettings[$setting->getKey()] = $setting;
            }
        }

        return $this->globalSettings[$key] ?? null;
    }

    private function companySetting(Company $company, string $key): ?CompanySetting
    {
        if (!array_key_exists($company->getId(), $this->companySettingsByCompanyId)) {
            $this->companySettingsByCompanyId[$company->getId()] = $this->companySettings->findByCompanyIndexed($company);
        }

        return $this->companySettingsByCompanyId[$company->getId()][$key] ?? null;
    }
}
