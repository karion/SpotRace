<?php

namespace App\Service;

use App\Entity\Company;
use App\Entity\CompanyParkingSpot;
use App\Entity\ParkingSpot;
use App\Repository\CompanyParkingSpotRepository;
use App\Repository\ParkingReservationRepository;
use App\Repository\ParkingSpotAssignmentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;

class CompanyParkingSpotManager
{
    public function __construct(
        private readonly CompanyParkingSpotRepository $companySpots,
        private readonly ParkingSpotAssignmentRepository $assignments,
        private readonly ParkingReservationRepository $reservations,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function createDraft(ParkingSpot $parkingSpot): CompanyParkingSpot
    {
        return (new CompanyParkingSpot())
            ->setParkingSpot($parkingSpot)
            ->setStartsAt((new \DateTimeImmutable())->setTime(0, 0));
    }

    public function hasValidationErrors(FormInterface $form, CompanyParkingSpot $companySpot, ?string $excludedId = null): bool
    {
        $hasErrors = false;
        if (null !== $companySpot->getEndsAt() && $companySpot->getEndsAt() < $companySpot->getStartsAt()) {
            $form->get('endsAt')->addError(new FormError('Data zakończenia nie może być wcześniejsza niż data początku.'));
            $hasErrors = true;
        }

        if ($this->companySpots->hasOverlappingAssignmentForSpot($companySpot->getParkingSpot(), $companySpot->getStartsAt(), $companySpot->getEndsAt(), $excludedId)) {
            $form->addError(new FormError('To miejsce jest już przypisane do firmy w podanym zakresie dat.'));
            $hasErrors = true;
        }

        return $hasErrors;
    }

    /** @return array<int, CompanyParkingSpot> */
    public function historyForSpot(ParkingSpot $parkingSpot): array
    {
        return $this->companySpots->findByParkingSpot($parkingSpot);
    }

    public function create(CompanyParkingSpot $companySpot): void
    {
        $this->entityManager->persist($companySpot);
        $this->entityManager->flush();
    }

    public function transfer(ParkingSpot $parkingSpot, Company $targetCompany, \DateTimeImmutable $transferDate): void
    {
        $previousDay = $transferDate->modify('-1 day');
        $current = $this->companySpots->findActiveForSpot($parkingSpot, $previousDay);
        if ($current) {
            $current->setEndsAt($previousDay);
        }

        $target = (new CompanyParkingSpot())
            ->setParkingSpot($parkingSpot)
            ->setCompany($targetCompany)
            ->setStartsAt($transferDate);
        $this->entityManager->persist($target);

        foreach ($this->assignments->findByParkingSpotFromDate($parkingSpot->getId(), $transferDate) as $assignment) {
            if ($assignment->getStartsAt() >= $transferDate) {
                $this->entityManager->remove($assignment);
                continue;
            }
            $assignment->setEndsAt($previousDay);
        }

        foreach ($this->reservations->findByParkingSpotFromDate($parkingSpot->getId(), $transferDate) as $reservation) {
            $this->entityManager->remove($reservation);
        }

        $this->entityManager->flush();
    }
}
