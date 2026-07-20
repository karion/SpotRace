<?php

namespace App\Service;

use App\Entity\Company;

class CompanyRegistrationPolicy
{
    /** @return array<int, string> */
    public function validate(string $name, string $email, string $password, Company $company): array
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
    public function validatePassword(string $password, Company $company): array
    {
        $errors = [];
        if (mb_strlen($password) < $company->getPasswordMinLength()) {
            $errors[] = sprintf('Hasło musi mieć minimum %d znaków.', $company->getPasswordMinLength());
        }
        if ($company->isPasswordRequireLowercase() && !preg_match('/\p{Ll}/u', $password)) {
            $errors[] = 'Hasło musi zawierać co najmniej 1 małą literę.';
        }
        if ($company->isPasswordRequireUppercase() && !preg_match('/\p{Lu}/u', $password)) {
            $errors[] = 'Hasło musi zawierać co najmniej 1 wielką literę.';
        }
        if ($company->isPasswordRequireDigit() && !preg_match('/\d/', $password)) {
            $errors[] = 'Hasło musi zawierać co najmniej 1 cyfrę.';
        }
        if ($company->isPasswordRequireSpecial() && !preg_match('/[^\p{L}\d]/u', $password)) {
            $errors[] = 'Hasło musi zawierać co najmniej 1 znak specjalny.';
        }

        return $errors;
    }

    private function isDomainAllowed(string $email, Company $company): bool
    {
        $domains = $company->allowedEmailDomainList();
        if ([] === $domains) {
            return true;
        }

        $parts = explode('@', $email);
        $domain = mb_strtolower((string) end($parts));

        return in_array($domain, $domains, true);
    }
}
