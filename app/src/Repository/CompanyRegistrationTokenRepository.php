<?php

namespace App\Repository;

use App\Entity\Company;
use App\Entity\CompanyRegistrationToken;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CompanyRegistrationToken>
 */
class CompanyRegistrationTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CompanyRegistrationToken::class);
    }

    public function findUsableToken(Company $company, string $token, ?\DateTimeImmutable $now = null): ?CompanyRegistrationToken
    {
        $now ??= new \DateTimeImmutable();

        return $this->createQueryBuilder('t')
            ->andWhere('t.company = :company')
            ->andWhere('t.token = :token')
            ->andWhere('t.revokedAt IS NULL')
            ->andWhere('t.expiresAt >= :now')
            ->setParameter('company', $company)
            ->setParameter('token', $token)
            ->setParameter('now', $now)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @return array<int, CompanyRegistrationToken> */
    public function findByCompany(Company $company): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.company = :company')
            ->setParameter('company', $company)
            ->orderBy('t.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
