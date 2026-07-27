#!/usr/bin/env bash

set -euo pipefail

DIR="${1:-.}"
QUALITY="${2:-80}"

if [[ ! -d "$DIR" ]]; then
    echo "Błąd: katalog '$DIR' nie istnieje." >&2
    exit 1
fi

if ! command -v ffmpeg &>/dev/null; then
    echo "Błąd: ffmpeg nie jest zainstalowany lub nie znajduje się w PATH." >&2
    exit 1
fi

shopt -s nullglob nocaseglob

files=("$DIR"/*.jpg "$DIR"/*.jpeg)

if [[ ${#files[@]} -eq 0 ]]; then
    echo "Brak plików .jpg/.jpeg w katalogu '$DIR'."
    exit 0
fi

for jpg in "${files[@]}"; do
    webp="${jpg%.*}.webp"

    if [[ -f "$webp" ]]; then
        echo "Pomijam konwersję (plik webp już istnieje): $webp"
        rm -- "$jpg"
        echo "Usunięto oryginał: $jpg"
        continue
    fi

    echo "Konwertuję: $jpg -> $webp"

    if ! ffmpeg -n -i "$jpg" \
        -c:v libwebp -lossless 0 -q:v "$QUALITY" -compression_level 6 \
        "$webp"; then
        echo "Błąd podczas konwersji pliku: $jpg" >&2
        exit 1
    fi

    rm -- "$jpg"
    echo "Usunięto oryginał: $jpg"
done

echo "Zakończono. Wszystkie pliki przetworzone."

