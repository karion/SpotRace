---
name: manage-parking-reservations
description: Implementowanie, poprawianie i przeglądanie logiki rezerwacji oraz przypisań miejsc parkingowych w SpotRace. Używać przy zmianach potwierdzania, przekazywania i zwalniania miejsc, okien czasowych, dostępności, nakładających się przypisań, typów rezerwacji oraz zabezpieczeń przed podwójną rezerwacją.
---

# Zarządzanie rezerwacjami SpotRace

## Procedura

1. Przeczytać `references/domain-rules.md`.
2. Przeczytać aktualne `docs/rezerwacje-miejsc.md` oraz klasy wskazane w mapie kodu. Traktować kod i konfigurację jako stan wykonawczy; zgłaszać rozbieżności z dokumentacją.
3. Zidentyfikować dotknięte niezmienniki przed edycją. Uwzględnić użytkownika rezerwującego, użytkownika docelowego, miejsce, datę, przypisanie i godzinę graniczną.
4. Umieścić obliczenia czasu w `ReservationPolicy`, zapytania w repozytoriach, a reguły wieloetapowe w usłudze domenowej. Nie powielać logiki pomiędzy Twig i kontrolerem.
5. Zachować ochronę dwuwarstwową: kontrola aplikacyjna przed zapisem oraz obsługa `UniqueConstraintViolationException` po `flush()`.
6. Dla operacji mutujących wymagać `POST`, poprawnego CSRF i kontroli właściciela lub roli.
7. Dodać testy graniczne: dziś przed i po godzinie granicznej, pierwszy i ostatni dzień okna, data poza oknem, zajęte miejsce oraz użytkownik z istniejącą rezerwacją.
8. Zaktualizować `docs/rezerwacje-miejsc.md`, gdy zmienia się zachowanie biznesowe lub konfiguracja.
9. Uruchomić skill `$verify-spotrace-change`.

## Zasady implementacyjne

- Normalizować daty biznesowe do północy w strefie `APP_TIMEZONE`.
- Nie kodować wartości `7` ani `1` poza konfiguracją i `ReservationPolicy`.
- Zachować znaczenie pól `reservedForUser` i `createdByUser` przy delegowaniu.
- Nie usuwać istniejących rezerwacji podczas dodawania lub zmiany przypisania.
- Nie polegać wyłącznie na stanie wyświetlonym w UI; ponownie sprawdzić warunki przy zapisie.
- Przy zmianie dostępności sprawdzić zarówno zapytania listujące, jak i endpoint wykonujący operację.
