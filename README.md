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
- Sesja trwa 1 tydzień (`604800` sekund)
- Wszystkie strony poza `/login`, `/register`, `/verify-email/*`, `/forgot-password`, `/reset-password/*` wymagają zalogowania
- Dozwolone domeny email konfigurujesz przez `ALLOWED_EMAIL_DOMAINS` (CSV, np. `firma.pl,gmail.com`)

### Nadanie roli admin po emailu

```bash
php app/bin/console app:user:promote-admin user@example.com
```
