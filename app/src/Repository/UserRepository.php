<?php

namespace App\Repository;

use App\Entity\Company;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function findOneByEmail(string $email): ?User
    {
        return $this->findOneBy(['email' => mb_strtolower(trim($email))]);
    }

    public function findOneByVerificationToken(string $token): ?User
    {
        return $this->findOneBy(['emailVerificationToken' => $token]);
    }

    public function findOneByPasswordResetToken(string $token): ?User
    {
        return $this->findOneBy(['passwordResetToken' => $token]);
    }

    /** @return array<int, User> */
    public function findByCompany(Company $company): array
    {
        return $this->findBy(['company' => $company], ['email' => 'ASC']);
    }

    public function hasUsersForCompany(Company $company): bool
    {
        return (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->andWhere('u.company = :company')
            ->setParameter('company', $company)
            ->getQuery()
            ->getSingleScalarResult() > 0;
    }

    public function hasActiveAdmin(): bool
    {
        $sql = <<<'SQL'
            SELECT EXISTS(
                SELECT 1
                FROM `user`
                WHERE status = :status
                  AND JSON_CONTAINS(roles, :adminRole) = 1
            )
        SQL;

        return (bool) $this->getEntityManager()
            ->getConnection()
            ->fetchOne($sql, [
                'status' => User::STATUS_ACTIVE,
                'adminRole' => '"ROLE_ADMIN"',
            ]);
    }
}
