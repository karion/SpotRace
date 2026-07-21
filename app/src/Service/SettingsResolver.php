<?php

namespace App\Service;

use App\Entity\AppSetting;
use App\Entity\Company;
use App\Repository\AppSettingRepository;
use App\Repository\CompanySettingRepository;

class SettingsResolver
{
    public function __construct(
        private readonly AppSettingRepository $appSettings,
        private readonly CompanySettingRepository $companySettings,
        private readonly SettingValueNormalizer $normalizer,
    ) {
    }

    public function get(string $key, ?Company $company = null): mixed
    {
        $global = $this->appSettings->findOneByKey($key);
        if (!$global instanceof AppSetting) {
            throw new \RuntimeException(sprintf('Brak globalnego ustawienia "%s".', $key));
        }

        $value = $global->getValue();
        if ($company instanceof Company) {
            $override = $this->companySettings->findOneByCompanyAndKey($company, $key);
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
}
