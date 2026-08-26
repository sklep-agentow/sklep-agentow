#!/bin/bash
# gitleaks-proba-kontrolna.sh — dowód, że bramka gitleaks w bramki.sh skanuje HISTORIĘ HEAD-a,        WERSJA 1.0.0
# a nie wszystkie referencje (recenzja #195 W1: refs/pull/194/merge ze skasowanej gałęzi paliło bramkę).
# Buduje w /tmp repo: czysty HEAD + OSOBNA gałąź z podłożonym kluczem Stripe → bramka MUSI przejść (0);
# potem ten sam klucz w commicie NA HEAD → bramka MUSI paść (1). Kontrola odwrotna jest pierwsza.
# Kody: 0 = oba przypadki zgodne · 1 = bramka pomyliła się · 2 = brak binarki gitleaks
set -u
BIN="${GITLEAKS_BIN:-/tmp/bramki-narzedzia/gitleaks}"
[ -x "$BIN" ] || { echo "gitleaks-proba: brak $BIN (uruchom najpierw bramki.sh, żeby pobrał binarkę)" >&2; exit 2; }
R="$(mktemp -d)"; trap 'rm -rf "$R"' EXIT; BLEDY=0
# Klucz-atrapa składany W LOCIE z dwóch części: dosłowny `sk_test_…` w tym pliku paliłby gitleaks i bramkę poświadczeń
# w historii HEAD (zmierzone 26.08). 24 mieszane znaki = wzorzec stripe-access-token + entropia (same litery gitleaks IGNORUJE).
KLUCZ="sk_$(printf 'test_%s' 'Zx9Qa7Lm3Kp2Vt8Rn5Wc1Yb4Hd6J')"
( cd "$R" && git init -q && git config user.email a@b.c && git config user.name a \
  && printf 'czysto\n' > a.txt && git add -A && git commit -qm baza \
  && git checkout -qb zatruta && printf 'klucz=%s\n' "$KLUCZ" > sekret.txt && git add -A && git commit -qm zatruty \
  && git checkout -q - )
skan() { ( cd "$R" && "$BIN" git --no-banner --redact --log-opts=HEAD . >/dev/null 2>&1 ); echo $?; }
k=$(skan); if [ "$k" -eq 0 ]; then echo "  OK   klucz TYLKO na innej gałęzi → bramka przechodzi (EXIT=0)"; else echo "  BLAD klucz na innej gałęzi pali bramkę (EXIT=$k) — skan po --all?"; BLEDY=$((BLEDY+1)); fi
( cd "$R" && printf 'klucz=%s\n' "$KLUCZ" > sekret.txt && git add -A && git commit -qm "zatruty na HEAD" )
k=$(skan); if [ "$k" -ne 0 ]; then echo "  OK   klucz w historii HEAD → bramka pada (EXIT=$k)"; else echo "  BLAD klucz w historii HEAD NIE pali bramki (EXIT=0)"; BLEDY=$((BLEDY+1)); fi
[ "$BLEDY" -eq 0 ] && { echo "GITLEAKS-PROBA: bramka odróżnia historię HEAD od cudzych referencji"; exit 0; }
echo "GITLEAKS-PROBA: $BLEDY błąd(y)"; exit 1
