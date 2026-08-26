#!/bin/bash
# Próba kontrolna bramki poswiadczenia: każde podłożone poświadczenie MUSI paść, czysty stan MUSI przejść.   WERSJA 1.1.2
# Przypadki: cookie-jar WP · sekret webhooka · nonce w URL (`&_wpnonce=`) · nonce w JSON · cookie-jar W HISTORII
# (zacommitowany i usunięty następnym commitem w klonie tymczasowym — drzewo czyste, historia brudna).
set -u
cd "$(git rev-parse --show-toplevel)" || exit 2
BLEDY=0
PLIK=audyt/.proba-kontrolna-cookie.txt
KLON=""
sprzatnij() { rm -f "$PLIK"; [ -n "$KLON" ] && [ -d "$KLON" ] && rm -rf "$KLON"; }
trap sprzatnij EXIT

przypadek() { # przypadek <opis> <treść pliku>
	printf '%b' "$2" > "$PLIK"
	if bash narzedzia-qa/bramka-poswiadczenia.sh > /dev/null; then echo "  BLAD $1 PRZESZEDL"; BLEDY=$((BLEDY+1)); else echo "  OK   $1 złapany (EXIT 1)"; fi
	rm -f "$PLIK"
}

if bash narzedzia-qa/bramka-poswiadczenia.sh > /dev/null; then echo "  OK   czysty stan przechodzi (drzewo + historia)"; else echo "  BLAD czysty stan pada (repo ma poświadczenia?)"; BLEDY=$((BLEDY+1)); fi
ZERA=$(printf '0%.0s' $(seq 32)); A10=$(printf 'a%.0s' $(seq 10)); B10=$(printf 'b%.0s' $(seq 10))
przypadek "podłożony cookie-jar" "# Netscape HTTP Cookie File\n#HttpOnly_localhost\tFALSE\t/\tFALSE\t0\twordpress_logged_in_${ZERA}\tuser%7C1%7Cx\n"
przypadek "podłożony sekret webhooka" "whsec_PROBA_KONTROLNA_1234567890\n"
przypadek "podłożony nonce w URL" "href=\"http://localhost/wp-admin/admin-post.php?action=x&_wpnonce=${A10}\"\n"
przypadek "podłożony nonce w zrzucie HTML z &amp; (fixture recenzji #190 W1)" "href=\"/wp-admin/admin-post.php?action=sklepag_resend&amp;zamowienie=5&amp;_wpnonce=1a2b3c4d5e\"\n"
przypadek "podłożony nonce w JSON" "{\"nonce\":\"${B10}\"}\n"

# HISTORIA: klon repo do katalogu tymczasowego (w /tmp), cookie-jar zacommitowany, potem `git rm` w kolejnym commicie.
KLON=$(mktemp -d "${TMPDIR:-/tmp}/poswiadczenia-proba.XXXXXX")
if git clone -q . "$KLON" && cp narzedzia-qa/bramka-poswiadczenia.sh "$KLON/narzedzia-qa/" && (
	cd "$KLON" && git config user.email a@b.c && git config user.name a \
	&& printf '# Netscape HTTP Cookie File\n#HttpOnly_localhost\tFALSE\t/\tFALSE\t0\twordpress_logged_in_%s\tx\n' "$ZERA" > ck.txt \
	&& git add ck.txt && git commit -qm "brud" && git rm -q ck.txt && git commit -qm "sprzatanie w nowym commicie" \
	&& bash narzedzia-qa/bramka-poswiadczenia.sh > /dev/null
); then echo "  BLAD cookie-jar w HISTORII (usunięty nowym commitem) PRZESZEDL"; BLEDY=$((BLEDY+1)); else echo "  OK   cookie-jar w HISTORII złapany mimo git rm (EXIT 1)"; fi

if [ "$BLEDY" -eq 0 ]; then echo "BRAMKA POSWIADCZENIA ROZPOZNAJE PODLOZONE POSWIADCZENIA (drzewo + historia) — i przepuszcza czysty stan"; exit 0; fi
echo "BLEDOW: $BLEDY"; exit 1
