<?php

namespace App\Service;

final class SettingKeys
{
    public const REGISTRATION_ALLOWED_EMAIL_DOMAINS = 'registration.allowed_email_domains';
    public const REGISTRATION_PASSWORD_MIN_LENGTH = 'registration.password_min_length';
    public const REGISTRATION_PASSWORD_REQUIRE_LOWERCASE = 'registration.password_require_lowercase';
    public const REGISTRATION_PASSWORD_REQUIRE_UPPERCASE = 'registration.password_require_uppercase';
    public const REGISTRATION_PASSWORD_REQUIRE_DIGIT = 'registration.password_require_digit';
    public const REGISTRATION_PASSWORD_REQUIRE_SPECIAL = 'registration.password_require_special';
    public const REGISTRATION_TOKEN_TTL_HOURS = 'registration.token_ttl_hours';
    public const RESERVATION_CONFIRMATION_DEADLINE_HOUR = 'reservation.confirmation_deadline_hour';
    public const RESERVATION_ASSIGNED_WINDOW_DAYS = 'reservation.assigned_window_days';
    public const RESERVATION_FREE_WINDOW_DAYS = 'reservation.free_window_days';

    private function __construct()
    {
    }
}
