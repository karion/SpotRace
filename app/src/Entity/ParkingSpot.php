<?php

namespace App\Entity;

use App\Repository\ParkingSpotRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: ParkingSpotRepository::class)]
class ParkingSpot
{
    #[ORM\Id]
    #[ORM\Column(type: Types::GUID)]
    private string $id;

    #[ORM\Column(length: 120)]
    private string $name;

    #[ORM\Column(type: Types::TEXT)]
    private string $description;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'RESTRICT')]
    private ?ParkingLocation $location = null;

    public function getLocation(): ?ParkingLocation
    {
        return $this->location;
    }

    public function setLocation(?ParkingLocation $location): self
    {
        $this->location = $location;

        return $this;
    }

    public function __construct()
    {
        $this->id = Uuid::v4()->toRfc4122();
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

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): self
    {
        $this->description = trim($description);

        return $this;
    }
}
