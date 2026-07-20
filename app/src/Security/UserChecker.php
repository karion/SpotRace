<?php

namespace App\Security;

use App\Entity\Company;
use App\Entity\User;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class UserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof User) {
            return;
        }

        if (!$user->isEmailVerified()) {
            throw new CustomUserMessageAccountStatusException('Potwierdź email zanim się zalogujesz.');
        }

        if (User::STATUS_BLOCKED === $user->getStatus()) {
            throw new CustomUserMessageAccountStatusException('Konto jest zablokowane. Skontaktuj się z administratorem.');
        }

        if (User::STATUS_PASSWORD_RESET_REQUIRED === $user->getStatus()) {
            throw new CustomUserMessageAccountStatusException('Musisz zresetować hasło przed zalogowaniem.');
        }

        $company = $user->getCompany();
        if (!$user->isAdmin() && !$company instanceof Company) {
            throw new CustomUserMessageAccountStatusException('Konto nie jest przypisane do firmy.');
        }

        if ($company instanceof Company && !$company->isActive()) {
            throw new CustomUserMessageAccountStatusException('Firma jest zablokowana. Skontaktuj się z administratorem.');
        }
    }

    public function checkPostAuth(UserInterface $user): void
    {
    }
}
