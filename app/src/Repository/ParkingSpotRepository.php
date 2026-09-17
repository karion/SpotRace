<?php

namespace App\Repository;

use App\Entity\ParkingLocation;
use App\Entity\ParkingSpot;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ParkingSpot>
 */
class ParkingSpotRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ParkingSpot::class);
    }

    public function hasLocation(ParkingLocation $location): bool
    {
        return $this->count(['location' => $location]) > 0;
    }

    /** @return array<int, ParkingSpot> */
    public function findAllWithLocations(): array
    {
        return $this->createQueryBuilder('spot')
            ->addSelect('location')
            ->leftJoin('spot.location', 'location')
            ->orderBy('spot.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
