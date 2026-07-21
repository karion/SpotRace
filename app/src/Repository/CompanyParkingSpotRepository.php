<?php

namespace App\Repository;

use App\Entity\Company;
use App\Entity\CompanyParkingSpot;
use App\Entity\ParkingSpot;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CompanyParkingSpot>
 */
class CompanyParkingSpotRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CompanyParkingSpot::class);
    }

    /** @return array<int, CompanyParkingSpot> */
    public function findByParkingSpot(ParkingSpot $parkingSpot): array
    {
        return $this->createQueryBuilder('cps')
            ->addSelect('c')
            ->join('cps.company', 'c')
            ->andWhere('cps.parkingSpot = :parkingSpot')
            ->setParameter('parkingSpot', $parkingSpot)
            ->orderBy('cps.startsAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findActiveForSpot(ParkingSpot $parkingSpot, \DateTimeImmutable $date): ?CompanyParkingSpot
    {
        return $this->createQueryBuilder('cps')
            ->addSelect('c')
            ->join('cps.company', 'c')
            ->andWhere('cps.parkingSpot = :parkingSpot')
            ->andWhere('cps.startsAt <= :date')
            ->andWhere('cps.endsAt IS NULL OR cps.endsAt >= :date')
            ->setParameter('parkingSpot', $parkingSpot)
            ->setParameter('date', $date)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @return array<int, CompanyParkingSpot> */
    public function findActiveForCompanyInRange(Company $company, \DateTimeImmutable $startsAt, \DateTimeImmutable $endsAt): array
    {
        return $this->createQueryBuilder('cps')
            ->addSelect('s')
            ->join('cps.parkingSpot', 's')
            ->andWhere('cps.company = :company')
            ->andWhere('cps.startsAt <= :endsAt')
            ->andWhere('cps.endsAt IS NULL OR cps.endsAt >= :startsAt')
            ->setParameter('company', $company)
            ->setParameter('startsAt', $startsAt)
            ->setParameter('endsAt', $endsAt)
            ->orderBy('s.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function hasCurrentOrFutureForCompany(Company $company, \DateTimeImmutable $today): bool
    {
        return (int) $this->createQueryBuilder('cps')
            ->select('COUNT(cps.id)')
            ->andWhere('cps.company = :company')
            ->andWhere('cps.endsAt IS NULL OR cps.endsAt >= :today')
            ->setParameter('company', $company)
            ->setParameter('today', $today)
            ->getQuery()
            ->getSingleScalarResult() > 0;
    }

    public function hasOverlappingAssignmentForSpot(ParkingSpot $parkingSpot, \DateTimeImmutable $startsAt, ?\DateTimeImmutable $endsAt, ?string $excludedId = null): bool
    {
        $newEnd = $endsAt ?? new \DateTimeImmutable('9999-12-31');
        $qb = $this->createQueryBuilder('cps')
            ->select('COUNT(cps.id)')
            ->andWhere('cps.parkingSpot = :parkingSpot')
            ->andWhere('cps.startsAt <= :newEnd')
            ->andWhere('cps.endsAt IS NULL OR cps.endsAt >= :startsAt')
            ->setParameter('parkingSpot', $parkingSpot)
            ->setParameter('startsAt', $startsAt)
            ->setParameter('newEnd', $newEnd);

        if (null !== $excludedId) {
            $qb->andWhere('cps.id != :excludedId')->setParameter('excludedId', $excludedId);
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }
}
