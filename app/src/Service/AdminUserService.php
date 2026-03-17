<?php

namespace App\Service;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class AdminUserService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function block(User $managedUser, ?User $currentUser): void
    {
        $this->denySelfAction($managedUser, $currentUser);

        $managedUser
            ->block()
            ->setPasswordResetToken(null)
            ->setPasswordResetExpiresAt(null);

        $this->entityManager->flush();
    }

    public function forcePasswordReset(User $managedUser, ?User $currentUser): void
    {
        $this->denySelfAction($managedUser, $currentUser);

        $managedUser
            ->requirePasswordReset()
            ->setPasswordResetToken(null)
            ->setPasswordResetExpiresAt(null);

        $this->entityManager->flush();
    }

    public function unlock(User $managedUser, ?User $currentUser): void
    {
        $this->denySelfAction($managedUser, $currentUser);

        $managedUser
            ->requirePasswordReset()
            ->setPasswordResetToken(null)
            ->setPasswordResetExpiresAt(null);

        $this->entityManager->flush();
    }

    private function denySelfAction(User $managedUser, ?User $currentUser): void
    {
        if ($currentUser instanceof User && $currentUser->getId() === $managedUser->getId()) {
            throw new AccessDeniedHttpException('Nie możesz zmieniać statusu własnego konta.');
        }
    }
}
