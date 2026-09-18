<?php

namespace App\Model;

class ReservationData
{
    public string $date = '';
    public ?string $spotId = null;
    public ?string $targetUserId = null;
    public ?string $vehicleId = null;
    public ?string $licensePlate = null;
}
