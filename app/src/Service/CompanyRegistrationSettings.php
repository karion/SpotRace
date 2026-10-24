<?php

namespace App\Service;

use App\Entity\Company;
use App\Entity\CompanySetting;
use App\Repository\CompanySettingRepository;
use Doctrine\ORM\EntityManagerInterface;

class CompanyRegistrationSettings
{
    public const FIELDS = [
        'allowed_email_domains' => ['label' => 'Dozwolone domeny e-mail', 'type' => 'domains'],
        'password_min_length' => ['label' => 'Minimalna długość hasła', 'type' => 'integer'],
        'password_require_lowercase' => ['label' => 'Wymagaj małej litery', 'type' => 'bool'],
        'password_require_uppercase' => ['label' => 'Wymagaj wielkiej litery', 'type' => 'bool'],
        'password_require_digit' => ['label' => 'Wymagaj cyfry', 'type' => 'bool'],
        'password_require_special' => ['label' => 'Wymagaj znaku specjalnego', 'type' => 'bool'],
        'token_ttl_hours' => ['label' => 'Ważność nowych linków (godziny)', 'type' => 'integer'],
    ];

    public function __construct(private readonly SettingsResolver $settings, private readonly CompanySettingRepository $overrides, private readonly EntityManagerInterface $em)
    {
    }

    /** @return array<string, array{mode: string, value: string}> */
    public function data(Company $company): array
    {
        $overrides = $this->overrides->findByCompanyIndexed($company);
        $data = [];
        foreach (self::FIELDS as $field => $definition) {
            $key = 'registration.'.$field;
            $data[$field] = ['mode' => isset($overrides[$key]) ? 'own' : 'inherit', 'value' => $this->format($this->settings->get($key, $company))];
        }

        return $data;
    }

    /** @return array<string, array{global: string, effective: string}> */
    public function values(Company $company): array
    {
        $values = [];
        foreach (self::FIELDS as $field => $definition) {
            $key = 'registration.'.$field;
            $values[$field] = ['global' => $this->display($this->settings->get($key)), 'effective' => $this->display($this->settings->get($key, $company))];
        }

        return $values;
    }

    /** @param array<string, array{mode: string, value: mixed}> $data */
    public function save(Company $company, array $data): void
    {
        $overrides = $this->overrides->findByCompanyIndexed($company);
        foreach (self::FIELDS as $field => $definition) {
            $key = 'registration.'.$field;
            $override = $overrides[$key] ?? null;
            if ('inherit' === $data[$field]['mode']) {
                if ($override) {
                    $this->em->remove($override);
                }
                continue;
            }
            $value = $data[$field]['value'];
            $value = match ($definition['type']) {
                'domains' => self::domains((string) $value),
                'bool' => '1' === $value,
                default => (int) $value,
            };
            if (!$override) {
                $override = (new CompanySetting())->setCompany($company)->setKey($key);
                $this->em->persist($override);
            }
            $override->setValue($value);
        }
        $this->em->flush();
        $this->settings->reset();
    }

    /** @return list<string> */
    public static function domains(string $value): array
    {
        return array_values(array_unique(array_filter(array_map(static fn (string $domain): string => mb_strtolower(trim($domain)), preg_split('/[,\r\n]+/', $value) ?: []), static fn (string $domain): bool => '' !== $domain)));
    }

    private function format(mixed $value): string
    {
        return is_array($value) ? implode(', ', $value) : (is_bool($value) ? ($value ? '1' : '0') : (string) $value);
    }

    private function display(mixed $value): string
    {
        return is_bool($value) ? ($value ? 'Tak' : 'Nie') : ($this->format($value) ?: 'Wszystkie domeny');
    }
}
