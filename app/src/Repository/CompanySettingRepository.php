<?php

namespace App\Repository;

use App\Entity\Company;
use App\Entity\CompanySetting;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CompanySetting>
 */
class CompanySettingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CompanySetting::class);
    }

    public function findOneByCompanyAndKey(Company $company, string $key): ?CompanySetting
    {
        return $this->findOneBy(['company' => $company, 'key' => $key]);
    }

    /** @return array<string, CompanySetting> */
    public function findByCompanyIndexed(Company $company): array
    {
        $settings = [];
        foreach ($this->findBy(['company' => $company]) as $setting) {
            $settings[$setting->getKey()] = $setting;
        }

        return $settings;
    }
}
