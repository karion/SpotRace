<?php

namespace App\Tests\Service;

use App\Entity\User;
use App\Entity\Vehicle;
use App\Repository\VehicleRepository;
use App\Service\VehicleManager;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class VehicleManagerTest extends TestCase
{
    public function testNormalizesNewPlateAndPersistsVehicle(): void
    {
        $owner = $this->user('owner@example.test');
        $repository = $this->createMock(VehicleRepository::class);
        $repository->expects(self::once())->method('findOneByOwnerAndPlate')->with($owner, 'EL12345')->willReturn(null);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('persist')->with(self::callback(
            static fn (Vehicle $vehicle): bool => $vehicle->getOwner() === $owner && 'EL12345' === $vehicle->getLicensePlate(),
        ));

        $plate = (new VehicleManager($repository, $entityManager))->resolveLicensePlate($owner, null, ' el 12345 ');

        self::assertSame('EL12345', $plate);
    }

    public function testReusesExistingPlateWithoutPersistingDuplicateVehicle(): void
    {
        $owner = $this->user('owner@example.test');
        $vehicle = (new Vehicle())->setOwner($owner)->setLicensePlate('EL12345');
        $repository = $this->createMock(VehicleRepository::class);
        $repository->expects(self::once())->method('findOneByOwnerAndPlate')->with($owner, 'EL12345')->willReturn($vehicle);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('persist');

        self::assertSame('EL12345', (new VehicleManager($repository, $entityManager))->resolveLicensePlate($owner, null, 'EL 12345'));
    }

    public function testRejectsVehicleBelongingToAnotherUser(): void
    {
        $owner = $this->user('owner@example.test');
        $repository = $this->createMock(VehicleRepository::class);
        $repository->expects(self::once())->method('findOneForOwner')->with('vehicle-id', $owner)->willReturn(null);
        $entityManager = $this->createStub(EntityManagerInterface::class);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('nie należy do Twojego konta');
        (new VehicleManager($repository, $entityManager))->resolveLicensePlate($owner, 'vehicle-id', null);
    }

    public function testRejectsBothSavedAndNewPlate(): void
    {
        $owner = $this->user('owner@example.test');
        $manager = new VehicleManager($this->createStub(VehicleRepository::class), $this->createStub(EntityManagerInterface::class));

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('zapisany pojazd albo wpisz nowy');
        $manager->resolveLicensePlate($owner, 'vehicle-id', 'EL12345');
    }

    public function testRejectsInvalidNewPlate(): void
    {
        $owner = $this->user('owner@example.test');
        $manager = new VehicleManager($this->createStub(VehicleRepository::class), $this->createStub(EntityManagerInterface::class));

        $this->expectException(\DomainException::class);
        $manager->resolveLicensePlate($owner, null, 'EL/12345');
    }

    private function user(string $email): User
    {
        return (new User())
            ->setEmail($email)
            ->setName('Test')
            ->setPasswordHash('unused');
    }
}
