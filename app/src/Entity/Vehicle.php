<?php

namespace App\Entity;

use App\Repository\VehicleRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: VehicleRepository::class)]
#[ORM\Table(name: 'vehicle')]
#[ORM\UniqueConstraint(name: 'uniq_vehicle_owner_plate', columns: ['owner_id', 'license_plate'])]
class Vehicle
{
    #[ORM\Id]
    #[ORM\Column(type: Types::GUID)]
    private string $id;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'owner_id', nullable: false, onDelete: 'CASCADE')]
    private User $owner;

    #[ORM\Column(length: 20)]
    #[Assert\NotBlank(message: 'Numer rejestracyjny jest wymagany.')]
    #[Assert\Length(max: 20, maxMessage: 'Numer rejestracyjny może mieć maksymalnie {{ limit }} znaków.')]
    #[Assert\Regex(pattern: '/^[A-Z0-9-]+$/', message: 'Numer rejestracyjny może zawierać tylko litery, cyfry i myślnik.')]
    private string $licensePlate = '';

    public function __construct()
    {
        $this->id = Uuid::v4()->toRfc4122();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getOwner(): User
    {
        return $this->owner;
    }

    public function setOwner(User $owner): self
    {
        $this->owner = $owner;

        return $this;
    }

    public function getLicensePlate(): string
    {
        return $this->licensePlate;
    }

    public function setLicensePlate(string $licensePlate): self
    {
        $this->licensePlate = strtoupper((string) preg_replace('/\s+/u', '', trim($licensePlate)));

        return $this;
    }
}
