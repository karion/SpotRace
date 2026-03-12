# SpotRace (Symfony 6.4 LTS)

Aplikacja Symfony 6.4 LTS z frontem w Twig i bazą MySQL.
Kod aplikacji znajduje się w katalogu `app/`.

## Wymagania lokalne

- Docker + Docker Compose
- Make

## Uruchomienie środowiska developerskiego

```bash
make up
```

## Instalacja zależności (Composer w kontenerze)

```bash
make composer-install
```

## Start aplikacji

Po uruchomieniu odwiedź:

- http://localhost:8080
- http://localhost:8025 (MailHog UI)

## Najczęściej używane komendy

```bash
make help
make build
make logs
make sf CMD='about'
make migrate
```

## System logowania i rejestracji

- Role: `ROLE_USER` (domyślnie), `ROLE_ADMIN`
- Rejestracja wymaga: imię, email, hasło
- Potwierdzenie email i reset hasła działają przez link z tokenem
- Reset hasła wysyła email przez Mailer (w dev do MailHog)
- Sesja trwa 1 tydzień (`604800` sekund)
- Wszystkie strony poza `/login`, `/register`, `/verify-email/*`, `/forgot-password`, `/reset-password/*` wymagają zalogowania
- Dozwolone domeny email konfigurujesz przez `ALLOWED_EMAIL_DOMAINS` (CSV, np. `firma.pl,gmail.com`)

### Nadanie roli admin po emailu

```bash
php app/bin/console app:user:promote-admin user@example.com
```

## Mailer (dev)

- Transport SMTP: `MAILER_DSN=smtp://mailhog:1025`
- Nadawca: `MAILER_FROM=no-reply@spotrace.local`
- Skrzynka developerska: `http://localhost:8025`

## System rezerwacji miejsc

- Szczegółowe przypadki użycia: `docs/rezerwacje-miejsc.md`.
- Rezerwacja wolnych miejsc: tylko dziś + jutro.
- Potwierdzenie/przekazanie przypisanego miejsca: dziś + do 7 dni wprzód.
- Dla dnia bieżącego przypisane miejsce wraca do puli po godzinie granicznej (domyślnie 07:00), jeśli nie zostało potwierdzone.

### Zmienne konfiguracyjne

- `APP_TIMEZONE` (domyślnie `Europe/Warsaw`)
- `RESERVATION_CONFIRMATION_DEADLINE_HOUR` (domyślnie `7`)
- `RESERVATION_ASSIGNED_WINDOW_DAYS` (domyślnie `7`)
- `RESERVATION_FREE_WINDOW_DAYS` (domyślnie `1`)
