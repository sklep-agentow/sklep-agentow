#!/bin/bash
# Uruchomienie strażnika A3-3 tak, żeby ŹRÓDŁEM PRAWDY BYŁO REPOZYTORIUM.
#
# PO CO: `wp eval-file` biegnie w kontenerze i widzi wyłącznie to, co ktoś tam
# wcześniej wgrał. Strażnik pilnujący rozjazdu „dokument ↔ kod" nie może czytać
# dokumentu z kopii wtyczki na stosie: ta bywa dowolnie stara, więc test świeciłby
# zielono przy złym pliku w repo i czerwono przy dobrym (dokładnie to zdarzyło się
# recenzentowi PR #165). Ten skrypt wgrywa dokumenty i kod Z TEGO DRZEWA — raz,
# jawnie — i dopiero potem uruchamia test.
#
# UŻYCIE:
#   bash testy/segregator-deklaracja-vs-ladunek-ai.sh               # oczekiwane EXIT=0
#   bash testy/segregator-deklaracja-vs-ladunek-ai.sh --kalibracja  # dokument z origin/main → oczekiwane EXIT=1
#
# ⚠️ Wgrywa wtyczkę segregator-maila na wspólny stos (jak każdy test tej wtyczki).
# Kalibracja podmienia WYŁĄCZNIE dokument (INSTRUKCJA.md), nie kod — mierzy to,
# co strażnik ma pilnować: czy dokument nadąża za kodem.
#
# KODY: 0 = tryb zwykły przeszedł albo kalibracja padła zgodnie z oczekiwaniem,
#       1 = odwrotnie (test kłamie), 2 = brak stosu / dockera / gałęzi porównania.
set -u
KONTENER=strona_wp_wordpress
WTYCZKA=wtyczki/segregator-maila
KORZEN=$(git rev-parse --show-toplevel 2>/dev/null) || { echo "nie jestem w repozytorium git" >&2; exit 2; }
cd "$KORZEN" || exit 2
# ⛔ „NIE WIEM" TO NIE TO SAMO CO „NIE STOI" — patrz `narzedzia-qa/test-php.sh`. Samo
# `docker inspect` konczy sie niezerowo zarowno gdy kontenera NIE MA, jak i gdy to konto
# nie widzi demona Dockera. Rozstrzyga osobne pytanie, czy Docker w ogole odpowiada.
if ! docker ps > /dev/null 2>&1; then
	echo "NIE WIEM, czy stos $KONTENER stoi — to konto nie widzi Dockera (brak dostepu do demona, nie brak kontenera)" >&2
	exit 2
fi
docker inspect "$KONTENER" > /dev/null 2>&1 || { echo "Docker odpowiedzial: stosu $KONTENER NIE MA" >&2; exit 2; }

KALIBRACJA=0
[ "${1:-}" = "--kalibracja" ] && KALIBRACJA=1

# 1. Kod wtyczki na stos — pomiar ładunku ma iść po kodzie z TEJ gałęzi.
docker cp "$WTYCZKA/." "$KONTENER:/var/www/html/wp-content/plugins/segregator-maila/" >/dev/null
docker exec "$KONTENER" chown -R www-data:www-data /var/www/html/wp-content/plugins/segregator-maila

# 2. Dokumenty i pliki źródłowe do /tmp/segm-repo — to je czyta strażnik.
docker exec "$KONTENER" rm -rf /tmp/segm-repo
docker exec "$KONTENER" mkdir -p /tmp/segm-repo
docker cp "$WTYCZKA" "$KONTENER:/tmp/segm-repo/segregator-maila" >/dev/null

if [ "$KALIBRACJA" = "1" ]; then
	BAZA="${SEGM_BAZA:-origin/main}"
	git rev-parse --verify "$BAZA" >/dev/null 2>&1 || { echo "brak gałęzi porównania: $BAZA" >&2; exit 2; }
	TMP=$(mktemp)
	git show "$BAZA:$WTYCZKA/INSTRUKCJA.md" > "$TMP" || { rm -f "$TMP"; exit 2; }
	docker cp "$TMP" "$KONTENER:/tmp/segm-repo/segregator-maila/INSTRUKCJA.md" >/dev/null
	rm -f "$TMP"
	echo "=== KALIBRACJA: dokument z $BAZA, kod z tej gałęzi — oczekuję EXIT=1 ==="
fi

# Test biegnie jako www-data, a `docker cp` zachowuje prawa źródła (mktemp daje
# 600 dla roota) — bez tego strażnik meldowałby „brak dokumentu" zamiast wyniku.
docker exec "$KONTENER" chmod -R a+rX /tmp/segm-repo

docker cp testy/segregator-deklaracja-vs-ladunek-ai.php "$KONTENER:/tmp/segm-dekl.php" >/dev/null
docker exec -u www-data "$KONTENER" wp --path=/var/www/html eval-file /tmp/segm-dekl.php
KOD=$?
echo "EXIT=$KOD"

# Sprzątanie: kopia repo w kontenerze nie ma prawa zostać i udawać źródła prawdy.
docker exec "$KONTENER" rm -rf /tmp/segm-repo

if [ "$KALIBRACJA" = "1" ]; then
	[ "$KOD" = "1" ] && { echo "KALIBRACJA OK — na starym dokumencie strażnik PADA."; exit 0; }
	echo "KALIBRACJA PADŁA — na starym dokumencie strażnik PRZESZEDŁ, czyli niczego nie pilnuje." >&2
	exit 1
fi
exit "$KOD"
