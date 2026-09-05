#!/bin/bash
# segregator-mime-ci-proba-kontrolna.sh — WPIĘCIE strażnika A7c-F1 w przelot prób W ZAKRESIE `narzedzia-qa` (tak woła CI).
# Strażnik (testy/segregator-mime-test.php) i jego mutanty (testy/segregator-mime-proba-kontrolna.sh) to czysty PHP CLI
# bez stosu, ale CI woła przelot z zakresem `narzedzia-qa`, więc znalezisko DUŻE nie miało żadnej automatycznej bramy
# poza ręcznym przelotem pełnym (#297 r1 WAŻNA 2). Kod wyjścia = kod próby w testy/: 0 zielono · 1 strażnik ślepy · 2 nie zmierzono.
# Inna nazwa niż w testy/ celowo — przelot pełny (bez zakresu) uruchamia oba pliki i liczy je osobno.
set -u
cd "$(dirname "$0")/.." || exit 2
[ -f testy/segregator-mime-proba-kontrolna.sh ] || { echo "brak testy/segregator-mime-proba-kontrolna.sh — nie zmierzono" >&2; exit 2; }
command -v php > /dev/null 2>&1 || { echo "brak php w PATH — nie zmierzono" >&2; exit 2; }
exec bash testy/segregator-mime-proba-kontrolna.sh
