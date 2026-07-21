<?php

namespace App\Service;

use App\Entity\Company;
use App\Entity\ParkingSpot;
use App\Entity\ParkingSpotAssignment;
use App\Entity\User;
use App\Repository\CompanyParkingSpotRepository;
use App\Repository\ParkingSpotAssignmentRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;

class ParkingSpotAssignmentManager
{
    public function __construct(
        private readonly ParkingSpotAssignmentRepository $assignments,
        private readonly UserRepository $users,
        private readonly CompanyParkingSpotRepository $companySpots,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function createDraft(ParkingSpot $parkingSpot): ParkingSpotAssignment
    {
        return (new ParkingSpotAssignment())
            ->setParkingSpot($parkingSpot)
            ->setStartsAt(new \DateTimeImmutable('today'));
    }

    /** @return array<int, User> */
    public function usersForAssignment(?Company $company = null): array
    {
        if ($company instanceof Company) {
            return $this->users->findByCompany($company);
        }

        return $this->users->findBy([], ['name' => 'ASC']);
    }

    /** @return array<string, mixed> */
    public function formOptions(string $submitLabel, ?Company $company = null): array
    {
        return [
            'users' => $this->usersForAssignment($company),
            'submit_label' => $submitLabel,
        ];
    }

    /** @return array<int, ParkingSpotAssignment> */
    public function historyForSpot(ParkingSpot $parkingSpot): array
    {
        return $this->assignments->findByParkingSpot($parkingSpot->getId());
    }

    /** @return array<int, ParkingSpotAssignment> */
    public function historyForSpotAndCompany(ParkingSpot $parkingSpot, Company $company): array
    {
        return $this->assignments->findByParkingSpotAndCompany($parkingSpot->getId(), $company);
    }

    public function hasValidationErrors(FormInterface $form, ParkingSpotAssignment $assignment, ?string $excludedAssignmentId = null, ?Company $requiredCompany = null): bool
    {
        $hasErrors = false;

        if (null !== $assignment->getEndsAt() && $assignment->getEndsAt() < $assignment->getStartsAt()) {
            $form->get('endsAt')->addError(new FormError('Data zakończenia nie może być wcześniejsza niż data początku.'));
            $hasErrors = true;
        }

        if ($this->assignments->hasOverlappingAssignmentForSpot(
            $assignment->getParkingSpot()->getId(),
            $assignment->getStartsAt(),
            $assignment->getEndsAt(),
            $excludedAssignmentId,
        )) {
            $form->addError(new FormError('To miejsce ma już przypisanie w podanym zakresie dat.'));
            $hasErrors = true;
        }

        $activeCompanySpot = $this->companySpots->findActiveForSpot($assignment->getParkingSpot(), $assignment->getStartsAt());
        if (!$activeCompanySpot) {
            $form->addError(new FormError('Miejsce nie jest przypisane do firmy w dacie początku.'));
            $hasErrors = true;
        } else {
            $spotCompany = $activeCompanySpot->getCompany();
            if ($requiredCompany instanceof Company && $spotCompany->getId() !== $requiredCompany->getId()) {
                $form->addError(new FormError('Miejsce nie należy do Twojej firmy.'));
                $hasErrors = true;
            }
            if ($assignment->getAssignedUser()->getCompany()?->getId() !== $spotCompany->getId()) {
                $form->addError(new FormError('Użytkownik musi należeć do firmy miejsca.'));
                $hasErrors = true;
            }
            if ($activeCompanySpot->getEndsAt() && (null === $assignment->getEndsAt() || $assignment->getEndsAt() > $activeCompanySpot->getEndsAt())) {
                $form->get('endsAt')->addError(new FormError('Przypisanie nie może wykraczać poza datę transferu miejsca.'));
                $hasErrors = true;
            }
        }

        return $hasErrors;
    }

    public function create(ParkingSpotAssignment $assignment, User $assignedBy): void
    {
        $assignment->setAssignedByUser($assignedBy);
        $this->entityManager->persist($assignment);
        $this->entityManager->flush();
    }

    public function update(): void
    {
        $this->entityManager->flush();
    }
}
