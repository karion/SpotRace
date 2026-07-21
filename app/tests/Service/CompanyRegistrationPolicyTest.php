<?php

namespace App\Tests\Service;

use App\Entity\Company;
use App\Service\CompanyRegistrationPolicy;
use App\Service\SettingKeys;
use App\Service\SettingsResolver;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class CompanyRegistrationPolicyTest extends TestCase
{
    public function testValidateAcceptsDefaultTwelveCharacterPasswordWithoutComplexity(): void
    {
        $company = (new Company())->setName('Acme')->setSlug('acme');
        $policy = new CompanyRegistrationPolicy($this->settingsResolver());

        self::assertSame([], $policy->validate('User', 'user@example.com', 'abcdefghijkl', $company));
    }

    public function testValidateRejectsEmailOutsideCompanyDomains(): void
    {
        $company = (new Company())->setName('Acme')->setSlug('acme');
        $policy = new CompanyRegistrationPolicy($this->settingsResolver([
            SettingKeys::REGISTRATION_ALLOWED_EMAIL_DOMAINS => ['acme.test'],
        ]));

        self::assertContains('Domena email nie jest dozwolona dla tej firmy.', $policy->validate('User', 'user@example.com', 'abcdefghijkl', $company));
    }

    public function testValidatePasswordAppliesCompanyComplexityRequirements(): void
    {
        $company = (new Company())->setName('Acme')->setSlug('acme');
        $policy = new CompanyRegistrationPolicy($this->settingsResolver([
            SettingKeys::REGISTRATION_PASSWORD_MIN_LENGTH => 8,
            SettingKeys::REGISTRATION_PASSWORD_REQUIRE_LOWERCASE => true,
            SettingKeys::REGISTRATION_PASSWORD_REQUIRE_UPPERCASE => true,
            SettingKeys::REGISTRATION_PASSWORD_REQUIRE_DIGIT => true,
            SettingKeys::REGISTRATION_PASSWORD_REQUIRE_SPECIAL => true,
        ]));

        $errors = $policy->validatePassword('password', $company);

        self::assertContains('Hasło musi zawierać co najmniej 1 wielką literę.', $errors);
        self::assertContains('Hasło musi zawierać co najmniej 1 cyfrę.', $errors);
        self::assertContains('Hasło musi zawierać co najmniej 1 znak specjalny.', $errors);
    }

    /** @param array<string, mixed> $values */
    private function settingsResolver(array $values = []): SettingsResolver&MockObject
    {
        $resolver = $this->createMock(SettingsResolver::class);
        $resolver->method('int')->willReturnCallback(static fn (string $key): int => (int) ($values[$key] ?? match ($key) {
            SettingKeys::REGISTRATION_PASSWORD_MIN_LENGTH => 12,
            SettingKeys::REGISTRATION_TOKEN_TTL_HOURS => 48,
            default => 0,
        }));
        $resolver->method('bool')->willReturnCallback(static fn (string $key): bool => (bool) ($values[$key] ?? false));
        $resolver->method('stringList')->willReturnCallback(static fn (string $key): array => $values[$key] ?? []);

        return $resolver;
    }
}
