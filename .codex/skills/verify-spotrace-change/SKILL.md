---
name: verify-spotrace-change
description: Weryfikowanie zmian w SpotRace za pomocą celów Make dla PHPUnit, PHPStan i PHP-CS-Fixer oraz kontrola diffu i stanu Git. Używać po implementacji lub przed commitem i pull requestem, a także przy diagnozowaniu błędów testów, analizy statycznej albo formatowania.
---

# Weryfikacja zmian SpotRace

## Procedura

1. Upewnić się, że kontener `frankenphp` działa. W razie potrzeby uruchomić `make up`.
2. Uruchomić `scripts/verify.sh` z głównego katalogu repozytorium.
3. Jeśli kontrola nie przejdzie, naprawić przyczynę i uruchomić cały zestaw ponownie. Nie pomijać kolejnych etapów po naprawie.
4. Sprawdzić `git diff` i `git status --short`, aby wykryć przypadkowe pliki i zmiany spoza zadania. Skrypt wykonuje już `git diff --check`.
5. Zaraportować osobno wynik PHPUnit, PHPStan i PHP-CS-Fixer. Wyraźnie wskazać każdą kontrolę, której nie dało się uruchomić.

## Zakres kontroli

Skrypt wykonuje wyłącznie cele projektu:

```bash
make phpunit
make phpstan
make php-cs-fixer-check
```

`make php-cs-fixer-check` tylko sprawdza styl. Do automatycznej naprawy użyć świadomie `make php-cs-fixer`, a następnie ponownie uruchomić cały skrypt.
