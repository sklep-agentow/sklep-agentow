#!/bin/bash
# Próba kontrolna bramki poswiadczenia: podłożony cookie-jar WP MUSI paść, czysty stan MUSI przejść.   WERSJA 1.0.0
set -u
cd "$(git rev-parse --show-toplevel)" || exit 2
BLEDY=0
PLIK=audyt/.proba-kontrolna-cookie.txt
trap 'rm -f "$PLIK"' EXIT
if bash narzedzia-qa/bramka-poswiadczenia.sh > /dev/null; then echo "  OK   czysty stan przechodzi"; else echo "  BLAD czysty stan pada (repo ma poświadczenia?)"; BLEDY=$((BLEDY+1)); fi
printf '# Netscape HTTP Cookie File\n#HttpOnly_localhost\tFALSE\t/\tFALSE\t0\twordpress_logged_in_%s\tuser%%7C1%%7Cx\n' "$(printf '0%.0s' $(seq 32))" > "$PLIK"
if bash narzedzia-qa/bramka-poswiadczenia.sh > /dev/null; then echo "  BLAD podłożony cookie-jar PRZESZEDL"; BLEDY=$((BLEDY+1)); else echo "  OK   podłożony cookie-jar złapany (EXIT 1)"; fi
rm -f "$PLIK"
printf 'whsec_PROBA_KONTROLNA_1234567890\n' > "$PLIK"
if bash narzedzia-qa/bramka-poswiadczenia.sh > /dev/null; then echo "  BLAD podłożony sekret webhooka PRZESZEDL"; BLEDY=$((BLEDY+1)); else echo "  OK   podłożony sekret webhooka złapany (EXIT 1)"; fi
rm -f "$PLIK"
[ "$BLEDY" -eq 0 ] && { echo "BRAMKA POSWIADCZENIA ROZPOZNAJE PODLOZONE POSWIADCZENIA — i przepuszcza czysty stan"; exit 0; } || { echo "BLEDOW: $BLEDY"; exit 1; }
