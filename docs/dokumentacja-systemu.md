# Dokumentacja systemu SpotRace

## 1. Cel systemu

SpotRace to aplikacja webowa wspierająca zarządzanie miejscami parkingowymi w organizacji.
System pozwala na:

- rejestrację i logowanie użytkowników,
- administracyjne zarządzanie użytkownikami,
- zarządzanie miejscami postojowymi,
- przypisywanie miejsc konkretnym osobom,
- rezerwowanie miejsc wolnych oraz obsługę miejsc przypisanych (potwierdzenie/przekazanie).

## 2. Zakres funkcjonalny

### 2.1. Uwierzytelnianie i konta

- Rejestracja użytkownika z walidacją domeny e-mail (`ALLOWED_EMAIL_DOMAINS`).
- Potwierdzenie adresu e-mail przez token.
- Logowanie formularzem (`email` + `password`) i sesja użytkownika.
- Reset hasła z linkiem ważnym 1 godzinę.
- Kontrola statusu użytkownika przy logowaniu:
  - brak potwierdzenia e-mail,
  - konto zablokowane,
  - wymuszony reset hasła.

### 2.2. Zarządzanie użytkownikami (admin)

- Lista użytkowników.
- Blokada konta.
- Wymuszenie resetu hasła.
- Odblokowanie konta (z jednoczesnym wymuszeniem resetu hasła).
- Zabezpieczenie przed samodzielną zmianą statusu własnego konta admina.
- Nadanie roli admin przez komendę CLI.
- Odebranie roli admin przez komendę CLI.
- Ostrzeżenie w CLI, jeśli po odebraniu uprawnień nie ma aktywnego administratora.

### 2.3. Zarządzanie miejscami postojowymi (admin)

- Dodawanie, edycja i usuwanie miejsc.
- Definiowanie przypisań miejsca do użytkownika:
  - od konkretnej daty,
  - opcjonalnie do daty końcowej,
  - walidacja nakładania się zakresów przypisań dla tego samego miejsca.

### 2.4. Rezerwacje i reguły biznesowe

- Rezerwacja wolnego miejsca przez użytkownika w oknie czasowym (domyślnie dziś + jutro).
- Potwierdzenie przypisanego miejsca w szerszym oknie (domyślnie dziś + 7 dni).
- Przekazanie przypisanego miejsca innej osobie (to samo okno co potwierdzenie).
- Zwolnienie własnej rezerwacji:
  - dla dni przyszłych zawsze,
  - dla dnia bieżącego do godziny granicznej (`RESERVATION_CONFIRMATION_DEADLINE_HOUR`).
- Ograniczenia spójności:
  - jeden użytkownik może mieć tylko jedną rezerwację na dzień,
  - jedno miejsce może być zarezerwowane tylko raz na dzień,
  - przypisane miejsce może być czasowo zablokowane dla innych do godziny granicznej.

## 3. Architektura techniczna

### 3.1. Stos technologiczny

- **Backend:** PHP 8.3 + Symfony 6.4 LTS.
- **ORM i migracje:** Doctrine ORM / Doctrine Migrations.
- **Frontend:** Twig (renderowanie po stronie serwera).
- **Baza danych:** MySQL 8.0.
- **Serwer aplikacyjny:** FrankenPHP.
- **Obsługa maili (dev):** MailHog.
- **Uruchomienie lokalne:** Docker Compose + Makefile.

### 3.2. Struktura wysokopoziomowa

- `app/src/Controller` – warstwa HTTP (routing, formularze, odpowiedzi).
- `app/src/Service` – logika domenowa i reguły biznesowe.
- `app/src/Repository` – dostęp do danych i zapytania domenowe.
- `app/src/Entity` – model danych mapowany przez Doctrine.
- `app/templates` – widoki Twig.
- `app/config` – konfiguracja frameworka, DI i security.
- `app/migrations` – wersjonowanie schematu bazy.

### 3.3. Komponenty runtime

- `frankenphp` – aplikacja Symfony wystawiona na porcie `8080`.
- `mysql` – baza danych na porcie `3306`.
- `mailhog` – SMTP (`1025`) + UI (`8025`).

## 4. Kluczowe przepływy

### 4.1. Rejestracja i aktywacja konta

1. Użytkownik zakłada konto przez `/register`.
2. System tworzy token weryfikacyjny i wysyła wiadomość e-mail.
3. Po wejściu na `/verify-email/{token}` konto zostaje aktywowane.
4. Logowanie jest możliwe dopiero po potwierdzeniu e-mail.

### 4.2. Rezerwacja wolnego miejsca

1. Użytkownik wybiera dzień i wolne miejsce.
2. System sprawdza ograniczenia (okno czasowe, dostępność, brak innej rezerwacji użytkownika).
3. Tworzona jest rezerwacja typu `free`.
4. Dodatkowo chroni przed wyścigami przez obsługę błędu naruszenia unikalności.

### 4.3. Potwierdzenie/przekazanie przypisanego miejsca

1. System pobiera aktywne przypisanie użytkownika dla wybranego dnia.
2. Weryfikuje okno czasowe i dostępność miejsca.
3. Dla potwierdzenia tworzy rezerwację `assigned_confirmed`.
4. Dla przekazania tworzy rezerwację `assigned_delegated` dla użytkownika docelowego.

### 4.4. Zwalnianie rezerwacji

1. Użytkownik żąda zwolnienia własnej rezerwacji.
2. Reguły polityki sprawdzają, czy zwolnienie w danej dacie jest dozwolone.
3. Rezerwacja jest usuwana.

## 5. Bezpieczeństwo

- Autoryzacja oparta o role i reguły `access_control`:
  - trasy publiczne (`/login`, `/register`, `/verify-email`, `/forgot-password`, `/reset-password`),
  - `/admin/**` tylko dla `ROLE_ADMIN`,
  - pozostałe dla `ROLE_USER`.
- CSRF dla formularzy logowania/wylogowania i operacji mutujących.
- Walidacja statusu konta w `UserChecker` na etapie logowania.
- Hasła przechowywane jako hash (konfiguracja Symfony `password_hashers`).

## 6. Konfiguracja środowiska

Parametry przez zmienne środowiskowe:

- `APP_TIMEZONE`,
- `RESERVATION_CONFIRMATION_DEADLINE_HOUR`,
- `RESERVATION_ASSIGNED_WINDOW_DAYS`,
- `RESERVATION_FREE_WINDOW_DAYS`,
- `ALLOWED_EMAIL_DOMAINS`,
- `MAILER_FROM`.

W środowisku developerskim uruchomionym przez Docker Compose:

- aplikacja: `http://localhost:8080`,
- MailHog UI: `http://localhost:8025`.

## 7. Operacje i utrzymanie

Najważniejsze komendy (przez `make`):

- `make up` / `make down` / `make build`,
- `make composer-install`,
- `make sf CMD='about'`,
- `make migrate`.

Dodatkowo:

- nadanie roli administratora:
  - `php app/bin/console app:user:promote-admin user@example.com`.
- odebranie roli administratora:
  - `php app/bin/console app:user:demote-admin user@example.com`.

## 8. Jakość i testy

W repozytorium są testy jednostkowe warstwy serwisów (`app/tests/Service`).
Zakres testów obejmuje m.in.:

- zarządzanie przypisaniami miejsc,
- operacje administracyjne na użytkownikach.
