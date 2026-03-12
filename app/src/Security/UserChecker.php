<?php

namespace App\Security;

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
    }

    public function checkPostAuth(UserInterface $user): void
    {
    }
}
