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
}
