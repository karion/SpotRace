<?php

namespace App\Tests\Service;

use App\Entity\Company;
use App\Service\CompanyRegistrationPolicy;
use PHPUnit\Framework\TestCase;

class CompanyRegistrationPolicyTest extends TestCase
{
    private CompanyRegistrationPolicy $policy;

    protected function setUp(): void
    {
        $this->policy = new CompanyRegistrationPolicy();
    }

    public function testValidateAcceptsDefaultTwelveCharacterPasswordWithoutComplexity(): void
    {
        $company = (new Company())->setName('Acme')->setSlug('acme')->setPasswordMinLength(12);

        self::assertSame([], $this->policy->validate('User', 'user@example.com', 'abcdefghijkl', $company));
    }

    public function testValidateRejectsEmailOutsideCompanyDomains(): void
    {
        $company = (new Company())
            ->setName('Acme')
            ->setSlug('acme')
            ->setAllowedEmailDomains('acme.test');

        self::assertContains('Domena email nie jest dozwolona dla tej firmy.', $this->policy->validate('User', 'user@example.com', 'abcdefghijkl', $company));
    }

    public function testValidatePasswordAppliesCompanyComplexityRequirements(): void
    {
        $company = (new Company())
            ->setName('Acme')
            ->setSlug('acme')
            ->setPasswordMinLength(8)
            ->setPasswordRequireLowercase(true)
            ->setPasswordRequireUppercase(true)
            ->setPasswordRequireDigit(true)
            ->setPasswordRequireSpecial(true);

        $errors = $this->policy->validatePassword('password', $company);

        self::assertContains('Hasło musi zawierać co najmniej 1 wielką literę.', $errors);
        self::assertContains('Hasło musi zawierać co najmniej 1 cyfrę.', $errors);
        self::assertContains('Hasło musi zawierać co najmniej 1 znak specjalny.', $errors);
    }
}
