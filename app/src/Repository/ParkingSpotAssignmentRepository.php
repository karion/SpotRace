<?php

namespace App\Repository;

use App\Entity\ParkingSpotAssignment;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ParkingSpotAssignment>
 */
class ParkingSpotAssignmentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ParkingSpotAssignment::class);
    }

    /** @return array<int, ParkingSpotAssignment> */
    public function findActiveForDate(\DateTimeImmutable $date): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.startsAt <= :date')
            ->andWhere('a.endsAt IS NULL OR a.endsAt >= :date')
            ->setParameter('date', $date)
            ->getQuery()
            ->getResult();
    }

    public function findUserAssignmentForDate(User $user, \DateTimeImmutable $date): ?ParkingSpotAssignment
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.assignedUser = :user')
            ->andWhere('a.startsAt <= :date')
            ->andWhere('a.endsAt IS NULL OR a.endsAt >= :date')
            ->setParameter('user', $user)
            ->setParameter('date', $date)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @return array<int, ParkingSpotAssignment> */
    public function findByParkingSpot(string $spotId): array
    {
        return $this->createQueryBuilder('a')
            ->join('a.parkingSpot', 's')
            ->andWhere('s.id = :spotId')
            ->setParameter('spotId', $spotId)
            ->orderBy('a.startsAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function hasOverlappingAssignmentForSpot(
        string $spotId,
        \DateTimeImmutable $startsAt,
        ?\DateTimeImmutable $endsAt,
        ?string $excludedAssignmentId = null,
    ): bool {
        $newStart = $startsAt->setTime(0, 0, 0);
        $newEnd = (null !== $endsAt)
            ? $endsAt->setTime(23, 59, 59)
            : new \DateTimeImmutable('9999-12-31 23:59:59');

        $qb = $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->join('a.parkingSpot', 's')
            ->andWhere('s.id = :spotId')
            ->andWhere('a.startsAt <= :newEnd')
            ->andWhere('a.endsAt IS NULL OR a.endsAt >= :newStart')
            ->setParameter('spotId', $spotId)
            ->setParameter('newStart', $newStart)
            ->setParameter('newEnd', $newEnd);

        if (null !== $excludedAssignmentId) {
            $qb
                ->andWhere('a.id != :excludedAssignmentId')
                ->setParameter('excludedAssignmentId', $excludedAssignmentId);
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }
}
