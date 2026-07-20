<?php

namespace App\Service;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Security\Core\User\UserInterface;

class AdminUserService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function block(User $managedUser, ?UserInterface $currentUser): void
    {
        $this->denySelfAction($managedUser, $currentUser);

        $managedUser
            ->block()
            ->setPasswordResetToken(null)
            ->setPasswordResetExpiresAt(null);

        $this->entityManager->flush();
    }

    public function forcePasswordReset(User $managedUser, ?UserInterface $currentUser): void
    {
        $this->denySelfAction($managedUser, $currentUser);

        $managedUser
            ->requirePasswordReset()
            ->setPasswordResetToken(null)
            ->setPasswordResetExpiresAt(null);

        $this->entityManager->flush();
    }

    public function unlock(User $managedUser, ?UserInterface $currentUser): void
    {
        $this->denySelfAction($managedUser, $currentUser);

        $managedUser
            ->requirePasswordReset()
            ->setPasswordResetToken(null)
            ->setPasswordResetExpiresAt(null);

        $this->entityManager->flush();
    }

    public function promoteToCompanyAdmin(User $managedUser, ?UserInterface $currentUser): void
    {
        $this->denySelfAction($managedUser, $currentUser);
        if (null === $managedUser->getCompany()) {
            throw new AccessDeniedHttpException('Company admin musi być przypisany do firmy.');
        }

        $managedUser->promoteToCompanyAdmin();
        $this->entityManager->flush();
    }

    public function demoteFromCompanyAdmin(User $managedUser, ?UserInterface $currentUser): void
    {
        $this->denySelfAction($managedUser, $currentUser);

        $managedUser->demoteFromCompanyAdmin();
        $this->entityManager->flush();
    }

    private function denySelfAction(User $managedUser, ?UserInterface $currentUser): void
    {
        if ($currentUser instanceof User && $currentUser->getId() === $managedUser->getId()) {
            throw new AccessDeniedHttpException('Nie możesz zmieniać statusu własnego konta.');
        }
    }
}
