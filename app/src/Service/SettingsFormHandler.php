<?php

namespace App\Service;

use App\Entity\AppSetting;
use App\Entity\Company;
use App\Entity\CompanySetting;
use App\Repository\AppSettingRepository;
use App\Repository\CompanySettingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

class SettingsFormHandler
{
    public function __construct(
        private readonly AppSettingRepository $appSettings,
        private readonly CompanySettingRepository $companySettings,
        private readonly SettingValueNormalizer $normalizer,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /** @return array<int, array{setting: AppSetting, value: string}> */
    public function globalRows(): array
    {
        return array_map(
            fn (AppSetting $setting): array => [
                'setting' => $setting,
                'value' => $this->normalizer->formatForForm($setting->getValue(), $setting->getType()),
            ],
            $this->appSettings->findForForm(),
        );
    }

    /** @return array<int, array{setting: AppSetting, globalValue: string, effectiveValue: string, overrideValue: string, hasOverride: bool}> */
    public function companyRows(Company $company): array
    {
        $overrides = $this->companySettings->findByCompanyIndexed($company);

        return array_map(
            function (AppSetting $setting) use ($overrides): array {
                $override = $overrides[$setting->getKey()] ?? null;
                $effectiveValue = $override instanceof CompanySetting ? $override->getValue() : $setting->getValue();

                return [
                    'setting' => $setting,
                    'globalValue' => $this->normalizer->formatForForm($setting->getValue(), $setting->getType()),
                    'effectiveValue' => $this->normalizer->formatForForm($effectiveValue, $setting->getType()),
                    'overrideValue' => $this->normalizer->formatForForm($effectiveValue, $setting->getType()),
                    'hasOverride' => $override instanceof CompanySetting,
                ];
            },
            $this->appSettings->findForForm(),
        );
    }

    public function updateGlobal(Request $request): void
    {
        $values = $this->requestArray($request, 'value');
        foreach ($this->appSettings->findForForm() as $setting) {
            $setting->setValue($this->normalizer->normalize(
                $this->postedValue($values, $setting),
                $setting->getType(),
            ));
        }

        $this->entityManager->flush();
    }

    public function updateCompany(Request $request, Company $company): void
    {
        $values = $this->requestArray($request, 'value');
        $overridesEnabled = $this->requestArray($request, 'override');
        $overrides = $this->companySettings->findByCompanyIndexed($company);

        foreach ($this->appSettings->findForForm() as $setting) {
            $override = $overrides[$setting->getKey()] ?? null;
            if (!array_key_exists($setting->getKey(), $overridesEnabled)) {
                if ($override instanceof CompanySetting) {
                    $this->entityManager->remove($override);
                }

                continue;
            }

            if (!$override instanceof CompanySetting) {
                $override = (new CompanySetting())
                    ->setCompany($company)
                    ->setKey($setting->getKey());
                $this->entityManager->persist($override);
            }

            $override->setValue($this->normalizer->normalize(
                $this->postedValue($values, $setting),
                $setting->getType(),
            ));
        }

        $this->entityManager->flush();
    }

    /** @return array<string, mixed> */
    private function requestArray(Request $request, string $key): array
    {
        return $request->request->all($key);
    }

    /** @param array<string, mixed> $values */
    private function postedValue(array $values, AppSetting $setting): mixed
    {
        if (AppSetting::TYPE_BOOL === $setting->getType()) {
            return array_key_exists($setting->getKey(), $values);
        }

        return $values[$setting->getKey()] ?? '';
    }
}
