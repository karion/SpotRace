# Firmy i pule miejsc postojowych

## Cel funkcji

Model firm pozwala jednemu zarządcy obsługiwać wiele organizacji korzystających z tej samej aplikacji i tej samej puli fizycznych miejsc postojowych. Firma wyznacza zakres widoczności użytkowników, company adminów oraz miejsc dostępnych do rezerwacji.

## Role i zakres odpowiedzialności

- `ROLE_ADMIN` jest rolą globalną zarządcy. Nie musi należeć do firmy.
- `ROLE_USER` i `ROLE_COMPANY_ADMIN` muszą należeć dokładnie do jednej firmy.
- Admin zarządza firmami, miejscami, transferami miejsc oraz rolami.
- Company admin zarządza kontami użytkowników swojej firmy i przypisaniami jej miejsc do jej użytkowników.
- Company admin nie nadaje ani nie odbiera ról, nie tworzy miejsc, nie edytuje miejsc, nie usuwa miejsc i nie transferuje ich między firmami.

## Zarządzanie firmą

Admin może dodać, edytować, zablokować i usunąć firmę. Firma ma nazwę, unikalny slug, status `active` albo `blocked` oraz politykę rejestracji.

Zablokowanie firmy blokuje logowanie wszystkich jej użytkowników oraz rejestrację nowych kont w tej firmie. Firmę można usunąć tylko wtedy, gdy nie ma przypisanych użytkowników oraz nie ma bieżących ani przyszłych miejsc postojowych. Reset aplikacji po wdrożeniu tej funkcji oznacza, że nie jest wymagana migracja istniejących użytkowników i miejsc do firm.

## Rejestracja użytkowników firmy

Firma ma dedykowany adres rejestracji:

```text
/register/{companySlug}?token=...
```

Token rejestracyjny jest wielorazowy, ważny 48 godzin i może zostać wcześniej unieważniony. Firma może mieć wiele aktywnych tokenów jednocześnie. Rejestracja przypisuje konto do firmy wynikającej ze sluga i tokenu.

Polityka rejestracji firmy obejmuje dozwolone domeny e-mail oraz wymagania hasła. Domyślnie hasło ma minimum 12 znaków bez wymagań złożoności. Firma może dodatkowo wymagać małej litery, wielkiej litery, cyfry i znaku specjalnego.

## Przypisanie miejsc do firm

Miejsca postojowe są przypisywane do firm przez tabelę łączącą, bez dodawania `company_id` do tabeli miejsca. Relacja jest czasowa i zawiera co najmniej:

- `company_id`,
- `parking_spot_id`,
- `starts_at`,
- `ends_at`.

Dla jednego miejsca okresy firm nie mogą się nakładać. W danym dniu miejsce należy do najwyżej jednej firmy. Użytkownik widzi i rezerwuje wyłącznie miejsca swojej firmy.

## Transfer miejsca między firmami

Admin może zaplanować transfer miejsca przez wskazanie firmy docelowej i daty obowiązywania. Nowa firma korzysta z miejsca od tej daty. Stara firma korzysta z miejsca do dnia poprzedzającego transfer.

Podczas okresu przejściowego stara firma nadal widzi i wykorzystuje miejsce, ale nie może tworzyć przypisań ani rezerwacji wykraczających poza datę transferu. Istniejące przypisania starej firmy są skracane do dnia poprzedzającego transfer, a rezerwacje od daty transferu są usuwane. Dane historyczne pozostają bez zmian.

## Scenariusze akceptacyjne

- Admin wykonuje pełny cykl zarządzania firmą: dodanie, edycja, blokada i usunięcie.
- Admin nadaje i odbiera `ROLE_COMPANY_ADMIN`.
- Nie można usunąć firmy posiadającej użytkowników albo bieżące lub przyszłe miejsca.
- Użytkownik zablokowanej firmy nie może się zalogować.
- Company admin nie widzi użytkowników ani miejsc innej firmy i nie może zmieniać ról.
- Token nieprawidłowy, wygasły albo unieważniony blokuje rejestrację.
- Rejestracja odrzuca niedozwoloną domenę i hasło niezgodne z polityką firmy.
- Transfer udostępnia miejsce nowej firmie od wskazanej daty i nie pozwala starej firmie tworzyć danych po tej dacie.
