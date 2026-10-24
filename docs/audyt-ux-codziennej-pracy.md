# Audyt UI/UX codziennej pracy w SpotRace

Data analizy: 24 października 2026 r.

## Zakres i metoda

Ocena obejmuje codzienną ścieżkę użytkownika: sprawdzanie rezerwacji, wybór dnia i miejsca, obsługę pojazdu, potwierdzanie i przekazywanie przypisania oraz zwalnianie miejsca.

Raport opiera się na kodzie widoków, formularzy i reguł dostępności oraz zapisanych zrzutach ekranu. Jest to audyt ekspercki, bez badań z użytkownikami i pomiarów czasu w przeglądarce. Wnioski o szybkości dotyczą struktury interakcji, nie zmierzonej wydajności aplikacji. Rekomendacje opisują proponowane zmiany, a nie funkcje już wdrożone.

## Ocena ogólna

Codzienna rezerwacja ma sensowną strukturę, ale aplikacja wymaga od użytkownika zbyt dużo interpretowania statusów i ponownego odnajdywania kontekstu. Największy potencjał poprawy to pewność: „co właśnie rezerwuję?”, „czy mam już miejsce?” i „dlaczego nie mogę wykonać tej akcji?”.

Warto zachować grupowanie **dzień → lokalizacja → miejsce**, wspólną listę miejsc przypisanych i wolnych oraz wybór pojazdu dopiero po wskazaniu miejsca.

## Codzienne zadania

| Zadanie | Obecna ścieżka | Ocena |
|---|---|---|
| Sprawdzenie dzisiejszej rezerwacji | Wejście na stronę → odszukanie dnia i informacji o rezerwacji | Brakuje wyraźnego podsumowania „Twoje miejsce na dziś” i lokalizacji. |
| Rezerwacja wolnego miejsca | Dzień → lokalizacja → miejsce → modal → pojazd → zapis | Dobry podział na dwa etapy, ale modal nie pokazuje wybranego miejsca i dnia. |
| Potwierdzenie przypisanego miejsca | Odszukanie wyróżnionego miejsca → „Potwierdź” → pojazd → zapis | Akcja czytelna, lecz termin pierwszeństwa nie jest widoczny. |
| Przekazanie miejsca | Osobna strona → odbiorca → pojazd → zapis | Najbardziej podatna na pomyłki ścieżka: pojazdy nie są ograniczone do wybranego odbiorcy. |
| Zwolnienie rezerwacji | Przycisk przy rezerwacji → natychmiastowy zapis | Szybkie, ale łatwe do wykonania przypadkiem. Po terminie przycisk znika bez wyjaśnienia. |
| Poprawienie błędu | Przejście z modala na osobną stronę formularza | Utrata kontekstu i inny sposób obsługi pojazdu niż podczas rozpoczynania rezerwacji. |

## Priorytet 1: wiarygodność informacji i zapobieganie błędom

### Rozdzielić przypisanie i zajętość

W `ReservationCalendar` własne przypisanie jest sprawdzane przed istniejącą rezerwacją. Po godzinie granicznej takie miejsce otrzymuje `assigned_available`, nawet jeśli ktoś już je zarezerwował. Jeśli dostępny jest formularz rezerwacji innego miejsca, widok może pokazać również to zajęte miejsce jako klikalne. Serwer odrzuci zapis, ale użytkownik dowie się o problemie za późno.

Zajętość powinna określać dostępność akcji, a przypisanie być dodatkowym oznaczeniem. Potrzebne są jednoznaczne etykiety:

- „Twoja rezerwacja”.
- „Zarezerwowane”.
- „Twoje przypisanie — potwierdź do 07:00”.
- „Pierwszeństwo innej osoby do 07:00”.
- „Dostępne”.

Godziny muszą wynikać z konfiguracji firmy; 07:00 jest jedynie przykładem. Samo „Potwierdzone” nie powinno oznaczać dowolnej rezerwacji własnego przypisanego miejsca.

### Pokazywać pełny kontekst przed zapisem

W modalu nad wyborem pojazdu umieścić np. **„Jutro, 25 października · Parking B · A-04”**. Przycisk powinien nazywać konkretną akcję: **„Zarezerwuj A-04”**, a przy potwierdzaniu przypisania: **„Potwierdź A-02”**.

### Nie proponować wyboru, który zakończy się błędem

Lista pojazdów zawsze zawiera „Bez rejestracji”, także gdy firma wymaga tablicy. W takim przypadku zastąpić tę opcję „Wybierz pojazd” i od razu wyjaśnić wymaganie. Przy braku zapisanych pojazdów pokazać od razu pole numeru.

### Filtrować pojazdy podczas przekazywania miejsca

Najpierw wybór osoby, potem wyłącznie jej pojazdy i „Inny pojazd”. Obecna lista pojazdów całej firmy wydłuża szukanie i pozwala wybrać zestaw, który serwer następnie odrzuca. Walidacja właściciela pojazdu po stronie serwera musi pozostać.

## Priorytet 2: szybkość i zachowanie kontekstu

- Na początku strony pokazywać „Dzisiaj” i „Jutro” z osobistym statusem: „Masz miejsce”, „Czeka na potwierdzenie” albo „Nie masz rezerwacji”.
- Ograniczyć przewijanie: zachować hierarchię, ale dalsze dni zwijać lub przełączać paskiem dat. Przy lokalizacji pokazywać liczbę dostępnych miejsc. Wybór wariantu nawigacji warto sprawdzić w prototypie mobilnym.
- Dodać opcjonalny filtr „Tylko dostępne”. Własna rezerwacja i własne przypisanie powinny pozostać widoczne również po jego włączeniu.
- Zapamiętywać preferowany pojazd i lokalizację. Wybrany pojazd nadal wyraźnie pokazywać przed zapisem; przy kilku samochodach nie zakładać bezwarunkowo ostatnio użytego.
- Wracać do wybranego dnia po operacji. Obecnie przekierowanie zawiera `?date=…`, ale `HomeController` go nie wykorzystuje. Przy rezerwacji dalszego dnia użytkownik ponownie musi odnaleźć właściwą sekcję.
- Przenieść linki administracyjne do osobnej nawigacji. Administrator firmy też codziennie parkuje; zarządzanie użytkownikami nie powinno odsuwać jego rezerwacji w dół strony.

## Komunikaty i odzyskiwanie po błędach

| Sytuacja | Rekomendowany komunikat i działanie |
|---|---|
| Udany zapis | „Zarezerwowano A-04 w Parking B na jutro, 25 października. Pojazd: WA123.” |
| Miejsce zajęte podczas wyboru | „A-04 zostało właśnie zarezerwowane. Wybierz inne miejsce.” Przycisk „Pokaż dostępne”; zachowany dzień i pojazd. |
| Istniejąca rezerwacja | „Masz już rezerwację A-02 na ten dzień.” Odnośnik do niej. |
| Koniec pierwszeństwa | „Termin potwierdzenia minął. Możesz zarezerwować miejsce, jeśli nadal jest wolne.” |
| Brak możliwości zwolnienia | „Termin zwolnienia minął o 07:00.” Informacja obok rezerwacji zamiast samego zniknięcia przycisku. |
| Błędna tablica | Błąd bezpośrednio pod polem, z zachowaniem wpisanej wartości i kontekstu rezerwacji. |

Daty, godziny, miejsca i tablice w przykładach należy zastępować danymi konkretnej operacji.

Błędy poprawialne powinny pozostać w tym samym modalu. Konflikty dostępności powinny prowadzić z powrotem do wyboru miejsca. Podczas zapisu potrzebny jest stan „Zapisywanie…” i ochrona przed ponownym kliknięciem.

Przed zwolnieniem wystarczy krótkie potwierdzenie z dniem i miejscem. Nie proponuje się prostego „Cofnij”, ponieważ zwolnione miejsce może już zająć ktoś inny.

Takie zmiany realizują zasady widoczności stanu systemu, zapobiegania błędom i pomagania w ich naprawie opisane przez [Nielsen Norman Group](https://www.nngroup.com/articles/ten-usability-heuristics/).

## Pozostałe usprawnienia i dostępność

- W „Moich pojazdach” pokazać najpierw zapisane pojazdy, a dodawanie jako osobną akcję.
- Przy edycji tablicy wyjaśnić, że istniejące rezerwacje zachowają dotychczasowy numer.
- Zastąpić prowadzące z powrotem do logowania „Załóż konto” informacją: „Do rejestracji potrzebujesz linku od administratora firmy”.
- Ujednolicić daty: „Dzisiaj, sob. 24.10”, a pełną datę pokazywać w podsumowaniu.
- Sprawdzić obsługę klawiaturą, fokus modala, powrót fokusu po zamknięciu i czytelność błędów dla czytników ekranu.
- Dla głównych akcji mobilnych przyjąć jako cel obszary dotykowe około 44 × 44 px. Minimum WCAG 2.2 AA wynosi 24 × 24 CSS px z określonymi wyjątkami. Nie jest to stwierdzenie naruszenia normy w obecnym interfejsie — wielkości wymagają pomiaru. Źródło: [W3C — Target Size (Minimum)](https://www.w3.org/WAI/WCAG22/Understanding/target-size-minimum).

## Kolejność wdrażania i ocena efektów

1. Prawidłowe statusy, kontekst modala i eliminacja niedozwolonych wyborów.
2. Zachowanie kontekstu po zapisie i błędach oraz czytelne potwierdzenia operacji.
3. Szybsza nawigacja po dniach, preferencje użytkownika i uporządkowanie nawigacji.

Efekt sprawdzić na telefonie i komputerze. Przed zmianami zebrać punkt odniesienia, a następnie porównać:

- czas wykonania rezerwacji i potwierdzenia przypisanego miejsca;
- liczbę błędnych prób i powrotów do listy;
- skuteczność przekazania miejsca właściwej osobie z właściwym pojazdem;
- zdolność wskazania dnia, lokalizacji, miejsca i pojazdu swojej rezerwacji bez dodatkowego szukania;
- zrozumienie przyczyny niedostępności akcji przed i po godzinie granicznej.

Scenariusze powinny objąć zapisany i nowy pojazd, wymagane tablice, wiele lokalizacji, zajęcie miejsca podczas wyboru oraz rezerwację dalszego dnia.

## Materiały projektu wykorzystane w analizie

- [Widok kalendarza](../app/templates/home/index.html.twig) i [przygotowanie statusów](../app/src/Service/ReservationCalendar.php).
- [Formularze i wybory pojazdów](../app/src/Form/ReservationFormFactory.php).
- [Obsługa operacji i błędów](../app/src/Controller/ReservationController.php).
- [Widok przekazywania miejsca](../app/templates/home/delegate_assigned.html.twig) oraz [formularz poprawiania rezerwacji](../app/templates/home/reservation_form.html.twig).
- [Moje pojazdy](../app/templates/vehicle/index.html.twig) i [logowanie](../app/templates/auth/login.html.twig).
- [Zasady rezerwacji](rezerwacje-miejsc.md).
- Zapisane zrzuty: [kalendarz](screenshots/reservations/calendar.png), [przekazanie](screenshots/reservations/delegate.png). Zrzuty są materiałem pomocniczym i mogą przedstawiać wcześniejsze warianty formularzy; źródłem zachowania jest aktualny kod.
