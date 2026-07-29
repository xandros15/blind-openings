# Nowy świat wiedzówek - ślepe openingi #

Projekt do prowadzenia ślepych openingów bazując na listach uczestników.

## Reqs ##

1. docker + docker compose
2. nvm/npm 24 (dev only)
3. php85 (dev only)
4. ffmpeg (do przekonwertowania jpg -> webp)
5. paczke z pobranymi openingami z anime themes
6. paczke z pobranymi obrazkami (można użyć `make download-posters` - konfiguracja API IMAGES)

## Instalacja ##

Ustawienie odpowiednich zmiennych srodowiskowych w pliku `{projekt}/.env` (patrz `.env.example`)
Następnie:

```
make build
```

Aplikacja jest podzielona na 2 części.

1. Formularz zgłoszeniowy uczestników. Tę aplikację musimy upublicznić przez serwer http tak, by uczestnik mógł się do
   niej odwołać. My również w celu pobrania list
2. Panel sterowania. To jest aplikacja do wybierania drużyny i losowania openingów. Wystarczy, że będzie uruchomiona na
   komputerze prowadzącego.

Do projektu dołączony jest przykładowy plik compose.yml, by móc uruchomić obie części na jednym środowisku.

Docelowo zaleca się rozdzielenie części panelu (prywatny) z formularzem (publiczny), gdyż panel ma otwarte endpointy
zmieniające stan bez autoryzacji.

przy ustawieniu zmiennych środowiskowych należy pamiętać o wskazaniu pełnych ścieżek do plików plakatów i wideo.

| ZMIENNA               | ŚRODOWISKO | PRZYKŁAD                         | OPIS                                                                                                                                   |
|:----------------------|------------|----------------------------------|----------------------------------------------------------------------------------------------------------------------------------------|
| PORT_FORM_WEB         | wszystkie  | 2137                             | wystawiony port pod aplikacje formularza (http://localhost:{port})                                                                     |
| PORT_PANEL_WEB        | wszystkie  | 2138                             | wystawiony port pod aplikacje panelu (http://localhost:{port})                                                                         |
| VITE_FORM_API_URL     | form(dev)  | http://localhost:2137/api        | adres api formularza                                                                                                                   |
| VITE_PANEL_API_URL    | panel(dev) | http://localhost:2138/api        | adres api panelu                                                                                                                       |
| VITE_PANEL_IMAGES_URL | panel(dev) | http://localhost:2138/images     | adres z plakatami (ktore są zamontowane przez IMAGE_DIR)                                                                               |
| VITE_PANEL_VIDEOS_URL | panel(dev) | http://localhost:2138/videos     | adres z openingami (ktore są zamontowane przez VIDEO_DIR)                                                                              |
| X_MAL_CLIENT_ID       | form       | c986cf85aa0c7a2be997817af7ef249f | klucz api potrzebny do pobierania list z mala (https://myanimelist.net/apiconfig)                                                      |
| DB_FORM_USER          | form       | blindopenings                    | cred pod baze danych                                                                                                                   |
| DB_FORM_PASS          | form       | blindopenings                    | cred pod baze danych                                                                                                                   |
| DB_FORM_NAME          | form       | blindopenings                    | cred pod baze danych                                                                                                                   |
| DB_PANEL_USER         | panel      | blindopenings-panel              | cred pod baze danych                                                                                                                   |
| DB_PANEL_PASS         | panel      | blindopenings-panel              | cred pod baze danych                                                                                                                   |
| DB_PANEL_NAME         | panel      | blindopenings-panel              | cred pod baze danych                                                                                                                   |
| FORM_API              | panel      | http://form-web                  | adres api do formularza. Potrzebne by z poziomu panelu postawionego na komputerze, móc pobrac listy z formularzy z zewnętrznego hosta. |
| VIDEO_DIR             | panel      | D:\video                         | lokalizacje do folderu z openingami                                                                                                    |
| IMAGE_DIR             | panel      | D:\image                         | lokalizacje do folderu z plakatami                                                                                                     |

## Wystartowanie z docker compose + migracja ##

```
docker compose up -d
make migrate-panel && make migrate-form
```

## DEV

### frontend ###

```
cd form-ui
npm run dev
```

```
cd panel-ui
npm run dev
```

Wymagana ustawienia zmiennych środowiskowych z prefiksem `VITE_`.

### backend ###

Tworzymy compose.override.yml na wzór compose.override.yml.example.

Następnie `docker compose up -d`.

Od tego momendu możemy edytować pliki backendowe, w trakcie dzialania dockera.

### Konfiguracja API IMAGES ###

(sekcja w budowie)

Jest to serwis mający w swoich endpointach plakaty z nazwami `ANIDB_ID.jpg` gdzie ANIDB_ID jest to identyfikatora anime
z anidb. Należy dodać do zmiennych środowiskowych `DB_PANEL_HOST`, by uzyskać dostęp do bazy.
