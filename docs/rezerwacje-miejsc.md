# System rezerwacji miejsc — przypadki użycia

## Założenia biznesowe

1. Administrator może przypisać miejsce do użytkownika:
   - bezterminowo,
   - do konkretnej daty.
2. Miejsce postojowe musi w danym dniu należeć do dokładnie jednej firmy albo być niedostępne dla użytkowników.
3. Użytkownik i company admin należą dokładnie do jednej firmy.
4. Użytkownik widzi, rezerwuje, potwierdza i przekazuje wyłącznie miejsca swojej firmy.
5. Przypisana osoba może:
   - potwierdzić swoje miejsce (dla dziś i do 7 dni w przód),
   - przekazać swoje miejsce innej osobie (dla dziś i do 7 dni w przód).
6. Dla dnia bieżącego przypisane miejsce jest zablokowane dla innych użytkowników tej samej firmy do skonfigurowanej godziny granicznej, domyślnie 07:00.
7. Zwykły użytkownik może rezerwować wolne miejsca swojej firmy na dowolny dzień w oknie skonfigurowanym globalnie albo nadpisanym dla firmy.
8. Jedna osoba może mieć maksymalnie jedną rezerwację dziennie.
9. Nie można zarezerwować miejsca już zarezerwowanego.
10. Admin rezerwuje miejsca tak samo jak użytkownik.
11. Dodanie nowego przypisania miejsca nie usuwa istniejących rezerwacji.
12. Transfer miejsca do innej firmy ogranicza przypisania i rezerwacje starej firmy do dnia poprzedzającego transfer.

## Przypadki użycia

### UC-01: Admin lub company admin przypisuje miejsce użytkownikowi
- **Aktor:** Admin lub company admin.
- **Warunek wstępny:** Istnieje miejsce należące do firmy użytkownika.
- **Kroki:**
  1. Aktor otwiera panel miejsca.
  2. Wybiera użytkownika z firmy miejsca, datę początku oraz opcjonalnie datę końca.
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
  2. Wybiera użytkownika docelowego z tej samej firmy.
  3. Klika „Przekaż miejsce”.
- **Rezultat:** Powstaje rezerwacja na miejsce przypisane, ale dla innego użytkownika tej samej firmy.

### UC-04: Użytkownik rezerwuje wolne miejsce
- **Aktor:** Użytkownik
- **Warunek wstępny:** Wybrana data mieści się w oknie rezerwacji wolnych miejsc firmy.
- **Kroki:**
  1. Użytkownik wybiera dzień.
  2. Widzi listę wolnych miejsc swojej firmy.
  3. Rezerwuje jedno z nich.
- **Rezultat:** Powstaje rezerwacja wolnego miejsca.

### UC-05: Admin transferuje miejsce do innej firmy
- **Aktor:** Admin.
- **Warunek wstępny:** Istnieje miejsce, firma źródłowa i firma docelowa.
- **Kroki:**
  1. Admin wybiera miejsce, firmę docelową i datę transferu.
  2. System ogranicza przypisania starej firmy do dnia poprzedzającego transfer.
  3. System usuwa przyszłe rezerwacje starej firmy od daty transferu.
  4. System tworzy czasowe przypisanie miejsca do nowej firmy od daty transferu.
- **Rezultat:** Od daty transferu miejsce jest widoczne i dostępne tylko dla nowej firmy.

### UC-06: Rezerwacja jest ograniczona datą transferu
- **Aktor:** Użytkownik lub company admin starej firmy.
- **Warunek wstępny:** Miejsce ma zaplanowany transfer do innej firmy.
- **Kroki:**
  1. Aktor próbuje utworzyć rezerwację albo przypisanie wykraczające poza datę transferu.
  2. System odrzuca operację.
- **Rezultat:** Dane starej firmy nie przekraczają dnia poprzedzającego transfer.

## Konfiguracja

Limity rezerwacji są ustawieniami globalnymi z możliwością nadpisania dla firmy:

- `reservation.confirmation_deadline_hour` — godzina graniczna (domyślnie 7).
- `reservation.assigned_window_days` — ile dni wprzód można potwierdzać/przekazywać przypisane miejsce.
- `reservation.free_window_days` — ile dni wprzód można rezerwować wolne miejsca.

Strefa czasowa pozostaje zmienną środowiskową `APP_TIMEZONE`.

Szczegółowy model firm i transferu miejsc opisuje `docs/firmy.md`.
