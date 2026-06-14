# Reguły domenowe rezerwacji

## Niezmienniki

- Użytkownik może mieć najwyżej jedną rezerwację danego dnia.
- Miejsce może mieć najwyżej jedną rezerwację danego dnia.
- Ograniczenia są wymuszane przez `uniq_user_per_day` i `uniq_spot_per_day`.
- Dodanie przypisania nie usuwa istniejących rezerwacji.
- Zakres przypisania jest domknięty; brak `endsAt` oznacza przypisanie bezterminowe.
- Zakresy przypisań tego samego miejsca nie mogą się nakładać.
- Administrator rezerwuje miejsce według tych samych reguł co użytkownik.

## Okna czasowe

- `RESERVATION_ASSIGNED_WINDOW_DAYS`: zakres potwierdzania i przekazywania przypisanego miejsca.
- `RESERVATION_FREE_WINDOW_DAYS`: zakres rezerwowania wolnego miejsca.
- `RESERVATION_CONFIRMATION_DEADLINE_HOUR`: godzina graniczna zarządzania dzisiejszym przypisaniem i zwalniania dzisiejszej rezerwacji.
- `APP_TIMEZONE`: strefa wszystkich porównań biznesowych.

Nie zakładać wartości domyślnych w implementacji. Pobierać je przez `ReservationPolicy`.

## Typy rezerwacji

- `free`: użytkownik zarezerwował wolne miejsce.
- `assigned_confirmed`: właściciel potwierdził przypisane miejsce.
- `assigned_delegated`: właściciel przypisania przekazał miejsce; `reservedForUser` wskazuje odbiorcę, a `createdByUser` właściciela.

## Mapa kodu

- `app/src/Service/ReservationPolicy.php`: czas, okna i godzina graniczna.
- `app/src/Controller/HomeController.php`: przepływy HTTP rezerwacji.
- `app/src/Entity/ParkingReservation.php`: model i ograniczenia unikalności.
- `app/src/Entity/ParkingSpotAssignment.php`: okres przypisania.
- `app/src/Repository/ParkingReservationRepository.php`: wyszukiwanie rezerwacji.
- `app/src/Repository/ParkingSpotAssignmentRepository.php`: aktywne przypisania i nakładanie zakresów.
- `app/src/Service/ParkingSpotAssignmentManager.php`: walidacja i zapis przypisań.
- `app/templates/home/`: widok dni, akcji i delegowania.
- `docs/rezerwacje-miejsc.md`: przypadki użycia.

## Lista przypadków granicznych

- dokładnie godzina graniczna;
- data dzisiejsza, przeszła i maksymalna dozwolona;
- właściciel z istniejącą rezerwacją;
- odbiorca delegowania z istniejącą rezerwacją;
- miejsce zajęte po wyświetleniu formularza;
- przypisane miejsce wybierane jako wolne;
- aktywne przypisanie innego użytkownika;
- zakres bezterminowy oraz stykające się zakresy przypisań.
