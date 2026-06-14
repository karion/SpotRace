# Repository Guidelines

## Struktura projektu i organizacja modułów

SpotRace jest aplikacją Symfony 7.4 uruchamianą w Dockerze. Kod aplikacji znajduje się w katalogu `app/`:

- `app/src/` zawiera kontrolery, encje, formularze, repozytoria, usługi, zabezpieczenia i komendy konsolowe.
- `app/templates/` przechowuje szablony Twig pogrupowane według funkcji: `admin/`, `auth/` i `home/`.
- `app/config/` zawiera konfigurację usług, tras i pakietów Symfony.
- `app/migrations/` przechowuje migracje Doctrine. Nie modyfikuj migracji, która została już zastosowana; utwórz nową.
- `app/tests/` zawiera testy PHPUnit, obecnie głównie dla warstwy usług.
- `docs/` zawiera dokumentację systemu i zasad rezerwacji.

Konfiguracja kontenerów znajduje się w `docker/` oraz `docker-compose.yml`.

## Budowanie, testowanie i praca lokalna

Polecenia uruchamiaj z głównego katalogu repozytorium:

```bash
make build              # Buduje obrazy i uruchamia usługi
make up                 # Uruchamia FrankenPHP, MySQL i MailHog
make composer-install   # Instaluje zależności PHP
make migrate            # Wykonuje migracje Doctrine
make sf CMD='about'     # Uruchamia komendę Symfony
make phpstan            # Uruchamia PHPStan na poziomie 6
docker compose exec frankenphp php bin/phpunit
make php-cs-fixer-check
```

Aplikacja działa pod `http://localhost:8080`, a MailHog pod `http://localhost:8025`.

## Styl kodu i nazewnictwo

Stosuj składnię PHP 8.2+, wcięcia czterema spacjami i konwencje Symfony. PHP-CS-Fixer używa zestawu reguł `@Symfony`. Klasy nazywaj w `PascalCase`, a metody i właściwości w `camelCase`. Nazwa klasy powinna wskazywać odpowiedzialność, np. `HomeController`, `ReservationPolicy` lub `ParkingSpotRepository`.

Kontrolery powinny pozostać cienkie. Reguły biznesowe umieszczaj w usługach lub obiektach domenowych. Szablony Twig zapisuj w katalogu właściwej funkcji, używając opisowych nazw `snake_case`.

## Zasady testowania

Projekt używa PHPUnit 13. Testy umieszczaj w `app/tests/`, w przestrzeni nazw `App\Tests`, a pliki nazywaj `*Test.php`. Nazwy metod powinny opisywać zachowanie, np. `testBlockThrowsWhenManagingOwnAccount`. Dodawaj testy dla reguł biznesowych, autoryzacji i naprawianych regresji.

Przed wysłaniem zmian uruchom PHPUnit, PHPStan i PHP-CS-Fixer w trybie kontrolnym.

## Commity i pull requesty

Historia używa krótkich, rozkazujących opisów po polsku lub angielsku, często z numerem zadania, np. `Dodaj edycję przypisania miejsca (#16)`. Jeden commit powinien obejmować jedną logiczną zmianę.

Pull request powinien opisywać zmianę zachowania, wskazywać powiązane zadanie i podawać wykonane polecenia weryfikacyjne. Wymień migracje oraz zmiany konfiguracji. Dla zmian w Twig lub interfejsie dodaj zrzuty ekranu. Aktualizuj `docs/`, gdy zmieniają się procesy lub zasady rezerwacji.

## Bezpieczeństwo i konfiguracja

Nie commituj `.env.local`, danych logowania, tokenów ani plików generowanych w `app/var/`. Nowe zmienne konfiguracyjne dodawaj z bezpiecznymi wartościami domyślnymi i dokumentuj je w `README.md`.
