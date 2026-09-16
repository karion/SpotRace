<?php

namespace App\Tests\Service;

use App\Entity\ParkingLocation;
use App\Repository\ParkingSpotRepository;
use App\Service\ParkingLocationManager;
use Doctrine\DBAL\Driver\Exception;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class ParkingLocationManagerTest extends TestCase
{
    public function testRejectsDeletingUsedLocationBeforeRemovingIt(): void
    {
        $location = (new ParkingLocation())->setName('Parking A');
        $spots = $this->createMock(ParkingSpotRepository::class);
        $spots->expects(self::once())->method('hasLocation')->with($location)->willReturn(true);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('remove');
        $em->expects(self::never())->method('flush');

        $this->expectException(\DomainException::class);
        (new ParkingLocationManager($spots, $em))->delete($location);
    }

    public function testHandlesSpotAssignedConcurrentlyDuringDeletion(): void
    {
        $location = (new ParkingLocation())->setName('Parking A');
        $spots = $this->createMock(ParkingSpotRepository::class);
        $spots->expects(self::once())->method('hasLocation')->with($location)->willReturn(false);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('remove')->with($location);
        $em->expects(self::once())->method('flush')->willThrowException(new ForeignKeyConstraintViolationException($this->createStub(Exception::class), null));

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Nie można usunąć lokalizacji');
        (new ParkingLocationManager($spots, $em))->delete($location);
    }

    public function testHandlesConcurrentDuplicateName(): void
    {
        $location = (new ParkingLocation())->setName('Parking A');
        $spots = $this->createStub(ParkingSpotRepository::class);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('persist')->with($location);
        $em->expects(self::once())->method('flush')->willThrowException(new UniqueConstraintViolationException($this->createStub(Exception::class), null));

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Lokalizacja o tej nazwie już istnieje.');
        (new ParkingLocationManager($spots, $em))->save($location);
    }
}
