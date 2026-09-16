<?php

namespace App\Entity;

use App\Repository\ParkingLocationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ParkingLocationRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_parking_location_name', columns: ['name'])]
#[UniqueEntity(fields: ['name'], message: 'Lokalizacja o tej nazwie już istnieje.')]
class ParkingLocation
{
    #[ORM\Id]
    #[ORM\Column(type: Types::GUID)]
    private string $id;

    #[ORM\Column(length: 120)]
    #[Assert\NotBlank(message: 'Nazwa lokalizacji jest wymagana.')]
    #[Assert\Length(max: 120, maxMessage: 'Nazwa może mieć maksymalnie {{ limit }} znaków.')]
    private string $name = '';

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

    private static function generateUuidV4(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0F) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3F) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
