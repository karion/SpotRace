<?php

namespace App\Tests\Service;

use App\Entity\Company;
use App\Entity\CompanyParkingSpot;
use App\Entity\ParkingSpot;
use App\Entity\ParkingSpotAssignment;
use App\Entity\User;
use App\Repository\CompanyParkingSpotRepository;
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
    private CompanyParkingSpotRepository&MockObject $companySpots;
    private EntityManagerInterface&MockObject $entityManager;
    private ParkingSpotAssignmentManager $manager;
    private Company $company;

    protected function setUp(): void
    {
        $this->assignments = $this->createMock(ParkingSpotAssignmentRepository::class);
        $this->users = $this->createMock(UserRepository::class);
        $this->companySpots = $this->createMock(CompanyParkingSpotRepository::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->company = (new Company())->setName('Acme')->setSlug('acme');

        $this->manager = new ParkingSpotAssignmentManager(
            $this->assignments,
            $this->users,
            $this->companySpots,
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
        $form->expects(self::once())->method('get')->with('endsAt')->willReturn($endsAtField);
        $endsAtField->expects(self::once())->method('addError')->with(self::isInstanceOf(FormError::class));
        $form->expects(self::never())->method('addError');

        $this->assignments->expects(self::once())->method('hasOverlappingAssignmentForSpot')->willReturn(false);
        $this->companySpots->expects(self::once())->method('findActiveForSpot')->willReturn($this->companySpotFor($assignment->getParkingSpot()));

        self::assertTrue($this->manager->hasValidationErrors($form, $assignment));
    }

    public function testHasValidationErrorsAddsFormErrorForOverlap(): void
    {
        $assignment = $this->buildAssignment(
            startsAt: new \DateTimeImmutable('2026-03-20'),
            endsAt: new \DateTimeImmutable('2026-03-21'),
        );

        $form = $this->createMock(FormInterface::class);
        $form->expects(self::never())->method('get');
        $form->expects(self::once())->method('addError')->with(self::isInstanceOf(FormError::class));

        $this->assignments->expects(self::once())->method('hasOverlappingAssignmentForSpot')->willReturn(true);
        $this->companySpots->expects(self::once())->method('findActiveForSpot')->willReturn($this->companySpotFor($assignment->getParkingSpot()));

        self::assertTrue($this->manager->hasValidationErrors($form, $assignment, 'excluded-id'));
    }

    public function testHasValidationErrorsAddsFormErrorWhenUserIsFromDifferentCompany(): void
    {
        $otherCompany = (new Company())->setName('Other')->setSlug('other');
        $assignment = $this->buildAssignment(
            startsAt: new \DateTimeImmutable('2026-03-20'),
            endsAt: new \DateTimeImmutable('2026-03-21'),
            company: $otherCompany,
        );

        $form = $this->createMock(FormInterface::class);
        $form->expects(self::once())->method('addError')->with(self::isInstanceOf(FormError::class));
        $form->expects(self::never())->method('get');

        $this->assignments->expects(self::once())->method('hasOverlappingAssignmentForSpot')->willReturn(false);
        $this->companySpots->expects(self::once())->method('findActiveForSpot')->willReturn($this->companySpotFor($assignment->getParkingSpot()));

        self::assertTrue($this->manager->hasValidationErrors($form, $assignment));
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

        $this->assignments->expects(self::once())->method('hasOverlappingAssignmentForSpot')->willReturn(false);
        $this->companySpots->expects(self::once())->method('findActiveForSpot')->willReturn($this->companySpotFor($assignment->getParkingSpot()));

        self::assertFalse($this->manager->hasValidationErrors($form, $assignment));
    }

    public function testCreateSetsAssignedByAndPersistsAssignment(): void
    {
        $assignment = $this->buildAssignment(
            startsAt: new \DateTimeImmutable('2026-03-20'),
            endsAt: null,
        );
        $assignedBy = $this->createUser('admin@example.com', 'Admin', $this->company);

        $this->entityManager->expects(self::once())->method('persist')->with($assignment);
        $this->entityManager->expects(self::once())->method('flush');

        $this->manager->create($assignment, $assignedBy);

        self::assertSame($assignedBy, $assignment->getAssignedByUser());
    }

    public function testUpdateFlushesEntityManager(): void
    {
        $this->entityManager->expects(self::once())->method('flush');

        $this->manager->update();
    }

    private function buildAssignment(\DateTimeImmutable $startsAt, ?\DateTimeImmutable $endsAt, ?Company $company = null): ParkingSpotAssignment
    {
        $company ??= $this->company;

        return (new ParkingSpotAssignment())
            ->setParkingSpot((new ParkingSpot())->setName('A-01')->setDescription('Miejsce testowe'))
            ->setAssignedUser($this->createUser('user@example.com', 'Użytkownik Testowy', $company))
            ->setStartsAt($startsAt)
            ->setEndsAt($endsAt);
    }

    private function companySpotFor(ParkingSpot $parkingSpot): CompanyParkingSpot
    {
        return (new CompanyParkingSpot())
            ->setCompany($this->company)
            ->setParkingSpot($parkingSpot)
            ->setStartsAt(new \DateTimeImmutable('2026-03-01'));
    }

    private function createUser(string $email, string $name, Company $company): User
    {
        return (new User())
            ->setCompany($company)
            ->setEmail($email)
            ->setName($name)
            ->setPasswordHash('secret');
    }
}
