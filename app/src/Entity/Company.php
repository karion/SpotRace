<?php

namespace App\Entity;

use App\Repository\CompanyRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: CompanyRepository::class)]
#[ORM\Table(name: 'company')]
#[ORM\UniqueConstraint(name: 'uniq_company_slug', columns: ['slug'])]
class Company
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_BLOCKED = 'blocked';

    #[ORM\Id]
    #[ORM\Column(type: Types::GUID)]
    private string $id;

    #[ORM\Column(length: 160)]
    private string $name;

    #[ORM\Column(length: 160)]
    private string $slug;

    #[ORM\Column(length: 40)]
    private string $status = self::STATUS_ACTIVE;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->id = Uuid::v4()->toRfc4122();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = trim($name);

        return $this;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): self
    {
        $slug = mb_strtolower(trim($slug));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? $slug;
        $this->slug = trim($slug, '-');

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function block(): self
    {
        $this->status = self::STATUS_BLOCKED;

        return $this;
    }

    public function activate(): self
    {
        $this->status = self::STATUS_ACTIVE;

        return $this;
    }

    public function isActive(): bool
    {
        return self::STATUS_ACTIVE === $this->status;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
