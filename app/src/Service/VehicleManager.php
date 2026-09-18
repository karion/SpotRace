<?php

namespace App\Service;

use App\Entity\User;
use App\Entity\Vehicle;
use App\Repository\VehicleRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;

class VehicleManager
{
    public function __construct(
        private readonly VehicleRepository $vehicles,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function save(Vehicle $vehicle, User $owner): void
    {
        $vehicle->setOwner($owner);
        try {
            $this->entityManager->persist($vehicle);
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException $exception) {
            throw new \DomainException('Ten numer rejestracyjny jest już zapisany na Twoim koncie.', previous: $exception);
        }
    }

    public function delete(Vehicle $vehicle, User $owner): void
    {
        if ($vehicle->getOwner()->getId() !== $owner->getId()) {
            throw new \DomainException('Nie możesz usunąć pojazdu innego użytkownika.');
        }

        $this->entityManager->remove($vehicle);
        $this->entityManager->flush();
    }

    public function resolveLicensePlate(User $owner, ?string $vehicleId, ?string $newLicensePlate): ?string
    {
        $vehicleId = trim((string) $vehicleId);
        $newLicensePlate = $this->normalize($newLicensePlate);
        if ('' !== $vehicleId && null !== $newLicensePlate) {
            throw new \DomainException('Wybierz zapisany pojazd albo wpisz nowy numer rejestracyjny.');
        }

        if ('' !== $vehicleId) {
            $vehicle = $this->vehicles->findOneForOwner($vehicleId, $owner);
            if (!$vehicle instanceof Vehicle) {
                throw new \DomainException('Wybrany pojazd nie należy do Twojego konta.');
            }

            return $vehicle->getLicensePlate();
        }

        if (null === $newLicensePlate) {
            return null;
        }

        $existing = $this->vehicles->findOneByOwnerAndPlate($owner, $newLicensePlate);
        if (!$existing instanceof Vehicle) {
            $this->entityManager->persist((new Vehicle())->setOwner($owner)->setLicensePlate($newLicensePlate));
        }

        return $newLicensePlate;
    }

    public function normalize(?string $licensePlate): ?string
    {
        if (null === $licensePlate) {
            return null;
        }

        $normalized = strtoupper((string) preg_replace('/\s+/u', '', trim($licensePlate)));

        if ('' === $normalized) {
            return null;
        }

        if (mb_strlen($normalized) > 20 || !preg_match('/^[A-Z0-9-]+$/', $normalized)) {
            throw new \DomainException('Numer rejestracyjny może zawierać tylko litery, cyfry i myślnik oraz mieć maksymalnie 20 znaków.');
        }

        return $normalized;
    }
}
