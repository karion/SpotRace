<?php

namespace App\Repository;

use App\Entity\Company;
use App\Entity\User;
use App\Entity\Vehicle;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Vehicle> */
class VehicleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Vehicle::class);
    }

    /** @return array<int, Vehicle> */
    public function findByOwner(User $owner): array
    {
        return $this->findBy(['owner' => $owner], ['licensePlate' => 'ASC']);
    }

    public function findOneByOwnerAndPlate(User $owner, string $licensePlate): ?Vehicle
    {
        return $this->findOneBy(['owner' => $owner, 'licensePlate' => $licensePlate]);
    }

    public function findOneForOwner(string $id, User $owner): ?Vehicle
    {
        return $this->createQueryBuilder('vehicle')
            ->andWhere('vehicle.id = :id')
            ->andWhere('vehicle.owner = :owner')
            ->setParameter('id', $id)
            ->setParameter('owner', $owner)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @return array<int, Vehicle> */
    public function findByCompany(Company $company): array
    {
        return $this->createQueryBuilder('vehicle')
            ->addSelect('owner')
            ->join('vehicle.owner', 'owner')
            ->andWhere('owner.company = :company')
            ->setParameter('company', $company)
            ->orderBy('owner.name', 'ASC')
            ->addOrderBy('vehicle.licensePlate', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
