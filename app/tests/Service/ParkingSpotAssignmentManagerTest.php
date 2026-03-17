<?php

namespace App\Tests\Service;

use App\Entity\ParkingSpot;
use App\Entity\ParkingSpotAssignment;
use App\Entity\User;
use App\Repository\ParkingSpotAssignmentRepository;
use App\Repository\UserRepository;
use App\Service\ParkingSpotAssignmentManager;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;

class ParkingSpotAssignmentManagerTest extends TestCase
{
    private ParkingSpotAssignmentRepository&MockObject $assignments;
    private UserRepository&MockObject $users;
    private EntityManagerInterface&MockObject $entityManager;
    private ParkingSpotAssignmentManager $manager;

    protected function setUp(): void
    {
        $this->assignments = $this->createMock(ParkingSpotAssignmentRepository::class);
        $this->users = $this->createMock(UserRepository::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);

        $this->manager = new ParkingSpotAssignmentManager(
            $this->assignments,
            $this->users,
            $this->entityManager,
        );
    }

    public function testHasValidationErrorsAddsEndsAtErrorForInvalidDateRange(): void
    {
        $assignment = $this->buildAssignment(
            startsAt: new \DateTimeImmutable('2026-03-20'),
            endsAt: new \DateTimeImmutable('2026-03-19'),
        );

        $form = $this->createMock(FormInterface::class);
        $endsAtField = $this->createMock(FormInterface::class);

        $form->expects(self::once())
            ->method('get')
            ->with('endsAt')
            ->willReturn($endsAtField);

        $endsAtField->expects(self::once())
            ->method('addError')
            ->with(self::callback(function (FormError $error): bool {
                return 'Data zakończenia nie może być wcześniejsza niż data początku.' === $error->getMessage();
            }));

        $form->expects(self::never())->method('addError');

        $this->assignments->expects(self::once())
            ->method('hasOverlappingAssignmentForSpot')
            ->with(
                $assignment->getParkingSpot()->getId(),
                $assignment->getStartsAt(),
                $assignment->getEndsAt(),
                null,
            )
            ->willReturn(false);

        self::assertTrue($this->manager->hasValidationErrors($form, $assignment));
    }

    public function testHasValidationErrorsAddsFormErrorForOverlap(): void
    {
        $assignment = $this->buildAssignment(
            startsAt: new \DateTimeImmutable('2026-03-20'),
            endsAt: new \DateTimeImmutable('2026-03-21'),
        );

        $form = $this->createMock(FormInterface::class);
        $endsAtField = $this->createMock(FormInterface::class);

        $form->expects(self::never())->method('get');

        $form->expects(self::once())
            ->method('addError')
            ->with(self::callback(function (FormError $error): bool {
                return 'To miejsce ma już przypisanie w podanym zakresie dat.' === $error->getMessage();
            }));

        $this->assignments->expects(self::once())
            ->method('hasOverlappingAssignmentForSpot')
            ->with(
                $assignment->getParkingSpot()->getId(),
                $assignment->getStartsAt(),
                $assignment->getEndsAt(),
                'excluded-id',
            )
            ->willReturn(true);

        $endsAtField->expects(self::never())->method('addError');

        self::assertTrue($this->manager->hasValidationErrors($form, $assignment, 'excluded-id'));
    }

    public function testHasValidationErrorsReturnsFalseForValidData(): void
    {
        $assignment = $this->buildAssignment(
            startsAt: new \DateTimeImmutable('2026-03-20'),
            endsAt: new \DateTimeImmutable('2026-03-21'),
        );

        $form = $this->createMock(FormInterface::class);

        $form->expects(self::never())->method('get');
        $form->expects(self::never())->method('addError');

        $this->assignments->expects(self::once())
            ->method('hasOverlappingAssignmentForSpot')
            ->willReturn(false);

        self::assertFalse($this->manager->hasValidationErrors($form, $assignment));
    }

    public function testCreateSetsAssignedByAndPersistsAssignment(): void
    {
        $assignment = $this->buildAssignment(
            startsAt: new \DateTimeImmutable('2026-03-20'),
            endsAt: null,
        );
        $assignedBy = $this->createUser('admin@example.com', 'Admin');

        $this->entityManager->expects(self::once())
            ->method('persist')
            ->with($assignment);

        $this->entityManager->expects(self::once())
            ->method('flush');

        $this->manager->create($assignment, $assignedBy);

        self::assertSame($assignedBy, $assignment->getAssignedByUser());
    }

    public function testUpdateFlushesEntityManager(): void
    {
        $this->entityManager->expects(self::once())
            ->method('flush');

        $this->manager->update();
    }

    private function buildAssignment(\DateTimeImmutable $startsAt, ?\DateTimeImmutable $endsAt): ParkingSpotAssignment
    {
        return (new ParkingSpotAssignment())
            ->setParkingSpot((new ParkingSpot())->setName('A-01')->setDescription('Miejsce testowe'))
            ->setAssignedUser($this->createUser('user@example.com', 'Użytkownik Testowy'))
            ->setStartsAt($startsAt)
            ->setEndsAt($endsAt);
    }

    private function createUser(string $email, string $name): User
    {
        return (new User())
            ->setEmail($email)
            ->setName($name)
            ->setPasswordHash('secret');
    }
}
