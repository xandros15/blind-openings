# Nowy świat wiedzówek - ślepe openingi #

Projekt do prowadzenia ślepych openingów bazując na listach uczestników.

## Reqs ##

1. docker + docker compose
2. nvm/npm 24 (dev only)
3. php85 (dev only)

## Instalacja ##

Ustawienie odpowiednich zmiennych srodowiskowych w pliku `{projekt}/.env` (patrz `.env.example`)
Następnie:

```
make build
```

## Wystartowanie z docker compose + migracja ##

```
docker compose up -d
docker compose exec php php vendor/bin/doctrine-migrations migrations:migrate
```

## dev frontend ##

```
cd list-request/ui
npm run dev
```

Wymagana zmienna środowiskowa `VITE_API_URL` do prawidłowego działania


## dev backend ##

Tworzymy compose.override.yml na wzór compose.override.yml.example.

Następnie `docker compose up -d`.

Od tego momendu możemy edytować pliki backendowe, w trakcie dzialania dockera.
