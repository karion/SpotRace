<?php

namespace App\Tests\Service;

use App\Entity\User;
use App\Service\AdminUserService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class AdminUserServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;

    private AdminUserService $service;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->service = new AdminUserService($this->entityManager);
    }

    public function testBlockBlocksUserClearsPasswordResetAndFlushes(): void
    {
        $managedUser = $this->createUser();
        $managedUser
            ->setPasswordResetToken('token')
            ->setPasswordResetExpiresAt(new \DateTimeImmutable('+1 day'));

        $this->entityManager->expects(self::once())->method('flush');

        $this->service->block($managedUser, null);

        self::assertSame(User::STATUS_BLOCKED, $managedUser->getStatus());
        self::assertNull($managedUser->getPasswordResetToken());
        self::assertNull($managedUser->getPasswordResetExpiresAt());
    }

    public function testForcePasswordResetRequiresResetClearsTokenAndFlushes(): void
    {
        $managedUser = $this->createUser();
        $managedUser
            ->setPasswordResetToken('token')
            ->setPasswordResetExpiresAt(new \DateTimeImmutable('+1 day'));

        $this->entityManager->expects(self::once())->method('flush');

        $this->service->forcePasswordReset($managedUser, null);

        self::assertSame(User::STATUS_PASSWORD_RESET_REQUIRED, $managedUser->getStatus());
        self::assertNull($managedUser->getPasswordResetToken());
        self::assertNull($managedUser->getPasswordResetExpiresAt());
    }

    public function testUnlockRequiresResetClearsTokenAndFlushes(): void
    {
        $managedUser = $this->createUser()->block();
        $managedUser
            ->setPasswordResetToken('token')
            ->setPasswordResetExpiresAt(new \DateTimeImmutable('+1 day'));

        $this->entityManager->expects(self::once())->method('flush');

        $this->service->unlock($managedUser, null);

        self::assertSame(User::STATUS_PASSWORD_RESET_REQUIRED, $managedUser->getStatus());
        self::assertNull($managedUser->getPasswordResetToken());
        self::assertNull($managedUser->getPasswordResetExpiresAt());
    }

    public function testBlockThrowsWhenManagingOwnAccount(): void
    {
        $managedUser = $this->createUser();
        $currentUser = $this->createUser();
        $this->setUserId($currentUser, $managedUser->getId());

        $this->entityManager->expects(self::never())->method('flush');

        $this->expectException(AccessDeniedHttpException::class);
        $this->expectExceptionMessage('Nie możesz zmieniać statusu własnego konta.');

        $this->service->block($managedUser, $currentUser);
    }

    private function createUser(): User
    {
        return (new User())
            ->setEmail('user@example.com')
            ->setName('User')
            ->setPasswordHash('hash');
    }

    private function setUserId(User $user, string $id): void
    {
        $reflection = new \ReflectionProperty(User::class, 'id');
        $reflection->setAccessible(true);
        $reflection->setValue($user, $id);
    }
}
