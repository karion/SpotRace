<?php

namespace App\Entity;

use App\Repository\CompanyRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

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

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $allowedEmailDomains = null;

    #[ORM\Column]
    private int $passwordMinLength = 12;

    #[ORM\Column]
    private bool $passwordRequireLowercase = false;

    #[ORM\Column]
    private bool $passwordRequireUppercase = false;

    #[ORM\Column]
    private bool $passwordRequireDigit = false;

    #[ORM\Column]
    private bool $passwordRequireSpecial = false;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->id = self::generateUuidV4();
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

    public function getAllowedEmailDomains(): ?string
    {
        return $this->allowedEmailDomains;
    }

    public function setAllowedEmailDomains(?string $allowedEmailDomains): self
    {
        $domains = array_values(array_filter(array_map('trim', explode(',', (string) $allowedEmailDomains))));
        $this->allowedEmailDomains = [] === $domains ? null : implode(',', $domains);

        return $this;
    }

    public function getPasswordMinLength(): int
    {
        return $this->passwordMinLength;
    }

    public function setPasswordMinLength(int $passwordMinLength): self
    {
        $this->passwordMinLength = max(1, $passwordMinLength);

        return $this;
    }

    public function isPasswordRequireLowercase(): bool
    {
        return $this->passwordRequireLowercase;
    }

    public function setPasswordRequireLowercase(bool $passwordRequireLowercase): self
    {
        $this->passwordRequireLowercase = $passwordRequireLowercase;

        return $this;
    }

    public function isPasswordRequireUppercase(): bool
    {
        return $this->passwordRequireUppercase;
    }

    public function setPasswordRequireUppercase(bool $passwordRequireUppercase): self
    {
        $this->passwordRequireUppercase = $passwordRequireUppercase;

        return $this;
    }

    public function isPasswordRequireDigit(): bool
    {
        return $this->passwordRequireDigit;
    }

    public function setPasswordRequireDigit(bool $passwordRequireDigit): self
    {
        $this->passwordRequireDigit = $passwordRequireDigit;

        return $this;
    }

    public function isPasswordRequireSpecial(): bool
    {
        return $this->passwordRequireSpecial;
    }

    public function setPasswordRequireSpecial(bool $passwordRequireSpecial): self
    {
        $this->passwordRequireSpecial = $passwordRequireSpecial;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** @return array<int, string> */
    public function allowedEmailDomainList(): array
    {
        return array_values(array_filter(array_map(
            static fn (string $domain): string => mb_strtolower(trim($domain)),
            explode(',', (string) $this->allowedEmailDomains),
        )));
    }

    private static function generateUuidV4(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0F) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3F) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
