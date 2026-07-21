<?php

namespace App\Service;

use App\Entity\Company;

class CompanyRegistrationPolicy
{
    public function __construct(private readonly SettingsResolver $settings)
    {
    }

    /** @return array<int, string> */
    public function validate(string $name, string $email, string $password, ?Company $company): array
    {
        $errors = [];

        if ('' === trim($name)) {
            $errors[] = 'Imię użytkownika jest wymagane.';
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Podaj poprawny adres email.';
        } elseif (!$this->isDomainAllowed($email, $company)) {
            $errors[] = 'Domena email nie jest dozwolona dla tej firmy.';
        }

        return [...$errors, ...$this->validatePassword($password, $company)];
    }

    /** @return array<int, string> */
    public function validatePassword(string $password, ?Company $company): array
    {
        $errors = [];
        $passwordMinLength = max(1, $this->settings->int(SettingKeys::REGISTRATION_PASSWORD_MIN_LENGTH, $company));
        if (mb_strlen($password) < $passwordMinLength) {
            $errors[] = sprintf('Hasło musi mieć minimum %d znaków.', $passwordMinLength);
        }
        if ($this->settings->bool(SettingKeys::REGISTRATION_PASSWORD_REQUIRE_LOWERCASE, $company) && !preg_match('/\p{Ll}/u', $password)) {
            $errors[] = 'Hasło musi zawierać co najmniej 1 małą literę.';
        }
        if ($this->settings->bool(SettingKeys::REGISTRATION_PASSWORD_REQUIRE_UPPERCASE, $company) && !preg_match('/\p{Lu}/u', $password)) {
            $errors[] = 'Hasło musi zawierać co najmniej 1 wielką literę.';
        }
        if ($this->settings->bool(SettingKeys::REGISTRATION_PASSWORD_REQUIRE_DIGIT, $company) && !preg_match('/\d/', $password)) {
            $errors[] = 'Hasło musi zawierać co najmniej 1 cyfrę.';
        }
        if ($this->settings->bool(SettingKeys::REGISTRATION_PASSWORD_REQUIRE_SPECIAL, $company) && !preg_match('/[^\p{L}\d]/u', $password)) {
            $errors[] = 'Hasło musi zawierać co najmniej 1 znak specjalny.';
        }

        return $errors;
    }

    private function isDomainAllowed(string $email, ?Company $company): bool
    {
        $domains = $this->settings->stringList(SettingKeys::REGISTRATION_ALLOWED_EMAIL_DOMAINS, $company);
        if ([] === $domains) {
            return true;
        }

        $parts = explode('@', $email);
        $domain = mb_strtolower((string) end($parts));

        return in_array($domain, $domains, true);
    }
}
