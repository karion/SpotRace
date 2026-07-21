# SpotRace (Symfony 7.4)

Aplikacja Symfony 7.4 z frontem w Twig i bazą MySQL.
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

## Dokumentacja

- Dokumentacja systemowa: `docs/dokumentacja-systemu.md`.
- Przypadki użycia rezerwacji: `docs/rezerwacje-miejsc.md`.
- Firmy i pule miejsc postojowych: `docs/firmy.md`.

## System logowania i rejestracji

- Role: `ROLE_USER` (domyślnie), `ROLE_COMPANY_ADMIN`, `ROLE_ADMIN`
- Użytkownik i company admin muszą należeć do jednej firmy
- Rejestracja wymaga dedykowanego linku firmy, imienia, emaila i hasła
- Potwierdzenie email i reset hasła działają przez link z tokenem
- Reset hasła wysyła email przez Mailer (w dev do MailHog)
- Sesja trwa 1 tydzień (`604800` sekund)
- Wszystkie strony poza `/login`, `/register`, `/verify-email/*`, `/forgot-password`, `/reset-password/*` wymagają zalogowania
- Dozwolone domeny email, wymagania hasła i czas ważności linku wynikają z ustawień globalnych lub nadpisań firmy

### Nadanie roli admin po emailu

```bash
php app/bin/console app:user:promote-admin user@example.com
```

### Odebranie roli admin po emailu

```bash
php app/bin/console app:user:demote-admin user@example.com
```

## Mailer (dev)

- Transport SMTP: `MAILER_DSN=smtp://mailhog:1025`
- Nadawca: `MAILER_FROM=no-reply@spotrace.local`
- Skrzynka developerska: `http://localhost:8025`

## System rezerwacji miejsc

- Szczegółowe przypadki użycia: `docs/rezerwacje-miejsc.md`.
- Użytkownik widzi i rezerwuje wyłącznie miejsca swojej firmy.
- Rezerwacja wolnych miejsc, potwierdzenie/przekazanie przypisanego miejsca i godzina graniczna korzystają z ustawień globalnych albo nadpisań firmy.
- Domyślnie wolne miejsca można rezerwować dziś + jutro, a przypisane potwierdzać/przekazywać dziś + do 7 dni wprzód.
- Dla dnia bieżącego przypisane miejsce wraca do puli po godzinie granicznej (domyślnie 07:00), jeśli nie zostało potwierdzone.

### Konfiguracja

- `APP_TIMEZONE` pozostaje zmienną środowiskową (domyślnie `Europe/Warsaw`).
- Ustawienia rejestracji i rezerwacji są zapisywane w bazie w `app_setting`.
- Firma może nadpisać wybrane wartości w `company_setting`; bez nadpisania używana jest wartość globalna.
