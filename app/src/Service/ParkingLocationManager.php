<?php

namespace App\Service;

use App\Entity\ParkingLocation;
use App\Repository\ParkingSpotRepository;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;

class ParkingLocationManager
{
    public function __construct(
        private readonly ParkingSpotRepository $spots,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function save(ParkingLocation $location): void
    {
        $this->entityManager->persist($location);
        try {
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException $exception) {
            throw new \DomainException('Lokalizacja o tej nazwie już istnieje.', previous: $exception);
        }
    }

    public function delete(ParkingLocation $location): void
    {
        if ($this->spots->hasLocation($location)) {
            throw new \DomainException('Nie można usunąć lokalizacji, do której należą miejsca postojowe.');
        }

        $this->entityManager->remove($location);
        try {
            $this->entityManager->flush();
        } catch (ForeignKeyConstraintViolationException $exception) {
            throw new \DomainException('Nie można usunąć lokalizacji, do której należą miejsca postojowe.', previous: $exception);
        }
    }
}
