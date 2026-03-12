# SpotRace (Symfony 6.4 LTS)

Aplikacja Symfony 6.4 LTS z frontem w Twig i bazą MySQL.
Kod aplikacji znajduje się w katalogu `app/`.

## Wymagania lokalne

- Docker + Docker Compose
- Make

## Uruchomienie środowiska developerskiego

```bash
make up
```

## Instalacja zależności (Composer w kontenerze)

```bash
make composer-install
```

## Start aplikacji

Po uruchomieniu odwiedź:

- http://localhost:8080

## Najczęściej używane komendy

```bash
make help
make build
make logs
make sf CMD='about'
make migrate
```
