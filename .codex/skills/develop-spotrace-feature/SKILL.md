---
name: develop-spotrace-feature
description: Kompleksowe implementowanie funkcji i poprawek w aplikacji SpotRace opartej na Symfony, Doctrine i Twig. Używać przy zadaniach obejmujących kontrolery, usługi, formularze, encje, repozytoria, migracje, szablony, konfigurację, autoryzację, testy lub dokumentację projektu.
---

# Rozwój funkcji SpotRace

## Rozpoznanie

1. Przeczytać `AGENTS.md`, powiązaną dokumentację w `docs/` oraz istniejący przepływ od trasy do widoku i zapisu.
2. Sprawdzić stan Git i zachować niezwiązane zmiany użytkownika.
3. Określić kryteria akceptacji, role, przypadki błędne, wpływ na dane i wymagane komunikaty.

## Implementacja

1. Dostosować rozwiązanie do istniejących wzorców:
   - kontrolery obsługują HTTP, formularze, przekierowania i komunikaty flash;
   - usługi zawierają reguły biznesowe;
   - repozytoria zawierają zapytania Doctrine;
   - encje chronią spójny model danych;
   - Twig renderuje stan przekazany przez backend.
2. Używać wstrzykiwania zależności i typów PHP. Nie tworzyć globalnych helperów ani nowej abstrakcji bez rzeczywistej potrzeby.
3. Dla zmian danych zaktualizować encję i utworzyć nową migrację Doctrine. Nie edytować zastosowanych migracji.
4. Dla operacji mutujących stosować odpowiednią metodę HTTP, CSRF, autoryzację i walidację po stronie serwera.
5. Utrzymywać tekst interfejsu po polsku i spójnie używać Bootstrap 5 w istniejących szablonach.
6. Ograniczać liczbę zapytań: preferować zapytania zakresowe i eager loading zamiast zapytań w pętli.

## Testy i zakończenie

1. Dodać test `*Test.php` w odpowiadającym obszarze `app/tests/`. Nazwać metody według zachowania, np. `testRejectsOverlappingAssignment`.
2. Testować przypadek poprawny, autoryzację, walidację oraz regresję, którą zmiana naprawia.
3. Zaktualizować `README.md` lub `docs/`, jeśli zmienia się konfiguracja, proces użytkownika albo reguła biznesowa.
4. Uruchomić skill `$verify-spotrace-change`.
5. Podsumować zmienione zachowanie, migracje i wynik kontroli. Nie deklarować sukcesu dla kontroli, których nie uruchomiono.
