# System rezerwacji miejsc — przypadki użycia

## Założenia biznesowe

1. Administrator może przypisać miejsce do użytkownika:
   - bezterminowo,
   - do konkretnej daty.
2. Przypisana osoba może:
   - potwierdzić swoje miejsce (dla dziś i do 7 dni w przód),
   - przekazać swoje miejsce innej osobie (dla dziś i do 7 dni w przód).
3. Dla dnia bieżącego przypisane miejsce jest zablokowane dla innych użytkowników do godziny 07:00.
4. Zwykły użytkownik może rezerwować wolne miejsca na dowolny dzień (od dnia bieżącego).
5. Jedna osoba może mieć maksymalnie jedną rezerwację dziennie.
6. Nie można zarezerwować miejsca już zarezerwowanego.
7. Admin rezerwuje miejsca tak samo jak użytkownik.
8. Dodanie nowego przypisania miejsca nie usuwa istniejących rezerwacji.

## Przypadki użycia

### UC-01: Admin przypisuje miejsce użytkownikowi
- **Aktor:** Admin
- **Warunek wstępny:** Istnieje miejsce i użytkownik.
- **Kroki:**
  1. Admin otwiera panel miejsca.
  2. Wybiera użytkownika, datę początku oraz opcjonalnie datę końca.
  3. Zapisuje przypisanie.
- **Rezultat:** Powstaje wpis przypisania miejsca.

### UC-02: Użytkownik potwierdza przypisane miejsce
- **Aktor:** Użytkownik z przypisaniem
- **Warunek wstępny:** Dla wybranego dnia istnieje aktywne przypisanie.
- **Kroki:**
  1. Użytkownik wybiera dzień.
  2. Klika „Potwierdź przypisane miejsce”.
- **Rezultat:** Powstaje rezerwacja przypisanego miejsca dla tego użytkownika.

### UC-03: Użytkownik przekazuje przypisane miejsce
- **Aktor:** Użytkownik z przypisaniem
- **Warunek wstępny:** Dla wybranego dnia istnieje aktywne przypisanie, a miejsce nie jest jeszcze zarezerwowane.
- **Kroki:**
  1. Użytkownik wybiera dzień.
  2. Wybiera użytkownika docelowego.
  3. Klika „Przekaż miejsce”.
- **Rezultat:** Powstaje rezerwacja na miejsce przypisane, ale dla innego użytkownika.

### UC-04: Użytkownik rezerwuje wolne miejsce
- **Aktor:** Użytkownik
- **Warunek wstępny:** Wybrana data to dziś lub jutro.
- **Kroki:**
  1. Użytkownik wybiera dzień.
  2. Widzi listę wolnych miejsc.
  3. Rezerwuje jedno z nich.
- **Rezultat:** Powstaje rezerwacja wolnego miejsca.

## Konfiguracja

Wartości można zmieniać przez zmienne środowiskowe:

- `APP_TIMEZONE` — strefa czasowa (np. `Europe/Warsaw`).
- `RESERVATION_CONFIRMATION_DEADLINE_HOUR` — godzina graniczna (domyślnie 7).
- `RESERVATION_ASSIGNED_WINDOW_DAYS` — ile dni wprzód można potwierdzać/przekazywać przypisane miejsce.
- `RESERVATION_FREE_WINDOW_DAYS` — ile dni wprzód można rezerwować wolne miejsca.
