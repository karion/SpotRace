<?php

namespace App\Entity;

use App\Repository\ParkingReservationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ParkingReservationRepository::class)]
#[ORM\Table(name: 'parking_reservation', uniqueConstraints: [
    new ORM\UniqueConstraint(name: 'uniq_spot_per_day', columns: ['parking_spot_id', 'reservation_date']),
    new ORM\UniqueConstraint(name: 'uniq_user_per_day', columns: ['reserved_for_user_id', 'reservation_date']),
])]
class ParkingReservation
{
    #[ORM\Id]
    #[ORM\Column(type: Types::GUID)]
    private string $id;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ParkingSpot $parkingSpot;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $reservedForUser;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $createdByUser;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $reservationDate;

    #[ORM\Column(length: 30)]
    private string $type;

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

    public function getParkingSpot(): ParkingSpot
    {
        return $this->parkingSpot;
    }

    public function setParkingSpot(ParkingSpot $parkingSpot): self
    {
        $this->parkingSpot = $parkingSpot;

        return $this;
    }

    public function getReservedForUser(): User
    {
        return $this->reservedForUser;
    }

    public function setReservedForUser(User $reservedForUser): self
    {
        $this->reservedForUser = $reservedForUser;

        return $this;
    }

    public function getCreatedByUser(): User
    {
        return $this->createdByUser;
    }

    public function setCreatedByUser(User $createdByUser): self
    {
        $this->createdByUser = $createdByUser;

        return $this;
    }

    public function getReservationDate(): \DateTimeImmutable
    {
        return $this->reservationDate;
    }

    public function setReservationDate(\DateTimeImmutable $reservationDate): self
    {
        $this->reservationDate = $reservationDate;

        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    private static function generateUuidV4(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0F) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3F) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
