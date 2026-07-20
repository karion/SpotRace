<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[ORM\UniqueConstraint(name: 'uniq_user_email', columns: ['email'])]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    public const ROLE_ADMIN = 'ROLE_ADMIN';
    public const ROLE_COMPANY_ADMIN = 'ROLE_COMPANY_ADMIN';
    public const ROLE_USER = 'ROLE_USER';

    public const STATUS_ACTIVE = 'active';
    public const STATUS_BLOCKED = 'blocked';
    public const STATUS_PASSWORD_RESET_REQUIRED = 'password_reset_required';

    #[ORM\Id]
    #[ORM\Column(type: Types::GUID)]
    private string $id;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'RESTRICT')]
    private ?Company $company = null;

    #[ORM\Column(length: 180)]
    private string $email;

    #[ORM\Column(length: 120)]
    private string $name;

    #[ORM\Column(length: 255)]
    private string $passwordHash;

    /** @var array<int, string> */
    #[ORM\Column(type: Types::JSON)]
    private array $roles = [self::ROLE_USER];

    #[ORM\Column(length: 40)]
    private string $status = self::STATUS_ACTIVE;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $emailVerifiedAt = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $emailVerificationToken = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $passwordResetToken = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $passwordResetExpiresAt = null;

    public function __construct()
    {
        $this->id = self::generateUuidV4();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getCompany(): ?Company
    {
        return $this->company;
    }

    public function setCompany(?Company $company): self
    {
        $this->company = $company;

        return $this;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = mb_strtolower(trim($email));

        return $this;
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

    public function getPasswordHash(): string
    {
        return $this->passwordHash;
    }

    public function setPasswordHash(string $passwordHash): self
    {
        $this->passwordHash = $passwordHash;

        return $this;
    }

    public function getPassword(): string
    {
        return $this->passwordHash;
    }

    /** @return array<int, string> */
    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = self::ROLE_USER;

        return array_values(array_unique($roles));
    }

    /** @param array<int, string> $roles */
    public function setRoles(array $roles): self
    {
        $this->roles = array_values(array_unique($roles));

        return $this;
    }

    public function eraseCredentials(): void
    {
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    public function isAdmin(): bool
    {
        return in_array(self::ROLE_ADMIN, $this->getRoles(), true);
    }

    public function isCompanyAdmin(): bool
    {
        return in_array(self::ROLE_COMPANY_ADMIN, $this->getRoles(), true);
    }

    public function promoteToAdmin(): self
    {
        $roles = $this->getRoles();
        $roles[] = self::ROLE_ADMIN;
        $this->setRoles($roles);

        return $this;
    }

    public function demoteFromAdmin(): self
    {
        $this->setRoles(array_values(array_filter(
            $this->getRoles(),
            static fn (string $role): bool => self::ROLE_ADMIN !== $role,
        )));

        return $this;
    }

    public function promoteToCompanyAdmin(): self
    {
        $roles = $this->getRoles();
        $roles[] = self::ROLE_COMPANY_ADMIN;
        $this->setRoles($roles);

        return $this;
    }

    public function demoteFromCompanyAdmin(): self
    {
        $this->setRoles(array_values(array_filter(
            $this->getRoles(),
            static fn (string $role): bool => self::ROLE_COMPANY_ADMIN !== $role,
        )));

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

    public function requirePasswordReset(): self
    {
        $this->status = self::STATUS_PASSWORD_RESET_REQUIRED;

        return $this;
    }

    public function activate(): self
    {
        $this->status = self::STATUS_ACTIVE;

        return $this;
    }

    public function canLogin(): bool
    {
        if (self::STATUS_ACTIVE !== $this->status) {
            return false;
        }

        if (!$this->isAdmin() && null === $this->company) {
            return false;
        }

        return $this->isAdmin() || $this->company?->isActive();
    }

    public function canRequestPasswordReset(): bool
    {
        return self::STATUS_ACTIVE === $this->status || self::STATUS_PASSWORD_RESET_REQUIRED === $this->status;
    }

    public function getEmailVerifiedAt(): ?\DateTimeImmutable
    {
        return $this->emailVerifiedAt;
    }

    public function markEmailVerified(): self
    {
        $this->emailVerifiedAt = new \DateTimeImmutable();
        $this->emailVerificationToken = null;

        return $this;
    }

    public function isEmailVerified(): bool
    {
        return null !== $this->emailVerifiedAt;
    }

    public function getEmailVerificationToken(): ?string
    {
        return $this->emailVerificationToken;
    }

    public function setEmailVerificationToken(?string $emailVerificationToken): self
    {
        $this->emailVerificationToken = $emailVerificationToken;

        return $this;
    }

    public function getPasswordResetToken(): ?string
    {
        return $this->passwordResetToken;
    }

    public function setPasswordResetToken(?string $passwordResetToken): self
    {
        $this->passwordResetToken = $passwordResetToken;

        return $this;
    }

    public function getPasswordResetExpiresAt(): ?\DateTimeImmutable
    {
        return $this->passwordResetExpiresAt;
    }

    public function setPasswordResetExpiresAt(?\DateTimeImmutable $passwordResetExpiresAt): self
    {
        $this->passwordResetExpiresAt = $passwordResetExpiresAt;

        return $this;
    }

    private static function generateUuidV4(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0F) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3F) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
