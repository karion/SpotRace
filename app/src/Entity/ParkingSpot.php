<?php

namespace App\Entity;

use App\Repository\ParkingSpotRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

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

    public function __construct()
    {
        $this->id = self::generateUuidV4();
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

    private static function generateUuidV4(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
