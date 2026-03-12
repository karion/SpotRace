<?php

namespace App\Repository;

use App\Entity\ParkingReservation;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ParkingReservation>
 */
class ParkingReservationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ParkingReservation::class);
    }

    /** @return array<int, ParkingReservation> */
    public function findByDate(\DateTimeImmutable $date): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.reservationDate = :date')
            ->setParameter('date', $date)
            ->getQuery()
            ->getResult();
    }

    public function findUserReservationForDate(User $user, \DateTimeImmutable $date): ?ParkingReservation
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.reservedForUser = :user')
            ->andWhere('r.reservationDate = :date')
            ->setParameter('user', $user)
            ->setParameter('date', $date)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findSpotReservationForDate(string $spotId, \DateTimeImmutable $date): ?ParkingReservation
    {
        return $this->createQueryBuilder('r')
            ->join('r.parkingSpot', 's')
            ->andWhere('s.id = :spotId')
            ->andWhere('r.reservationDate = :date')
            ->setParameter('spotId', $spotId)
            ->setParameter('date', $date)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
