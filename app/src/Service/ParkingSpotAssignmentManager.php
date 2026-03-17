<?php

namespace App\Service;

use App\Entity\ParkingSpot;
use App\Entity\ParkingSpotAssignment;
use App\Entity\User;
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
    public function usersForAssignment(): array
    {
        return $this->users->findBy([], ['name' => 'ASC']);
    }

    /** @return array<string, mixed> */
    public function formOptions(string $submitLabel): array
    {
        return [
            'users' => $this->usersForAssignment(),
            'submit_label' => $submitLabel,
        ];
    }

    /** @return array<int, ParkingSpotAssignment> */
    public function historyForSpot(ParkingSpot $parkingSpot): array
    {
        return $this->assignments->findByParkingSpot($parkingSpot->getId());
    }

    public function hasValidationErrors(FormInterface $form, ParkingSpotAssignment $assignment, ?string $excludedAssignmentId = null): bool
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
