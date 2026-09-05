#!/bin/bash
# gitleaks-proba-kontrolna.sh — dowód, że bramka gitleaks w bramki.sh skanuje HISTORIĘ HEAD-a,        WERSJA 1.1.1
# a nie wszystkie referencje (recenzja #195 W1: refs/pull/194/merge ze skasowanej gałęzi paliło bramkę).
# Komendę gitleaks bierze Z bramki.sh (grep), więc kalibruje BRAMKĘ, nie własną kopię. Buduje w /tmp repo: czysty HEAD + OSOBNA gałąź z podłożonym kluczem Stripe → bramka MUSI przejść (0);
# potem ten sam klucz w commicie NA HEAD → bramka MUSI paść (1). Kontrola odwrotna jest pierwsza.
# Kody: 0 = oba przypadki zgodne · 1 = bramka pomyliła się · 2 = gitleaks nie do zapewnienia (sieć/suma) albo brak komendy w bramki.sh
set -u
KORZEN="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BIN="${GITLEAKS_BIN:-${TMPDIR:-/tmp}/bramki-narzedzia/gitleaks}"   # ta sama ścieżka co CACHE w bramki.sh (#196 D2)
# KOMENDA BRAMKI czytana Z bramki.sh (nie przepisana): cofnięcie `--log-opts=HEAD` na `--all` w bramce MUSI zepsuć tę próbę (#196 W1)
KOMENDA=$(grep -E '^[[:space:]]*"\$bin" git ' "$KORZEN/narzedzia-qa/bramki.sh" | head -1 | sed 's/^[[:space:]]*//')
[ -n "$KOMENDA" ] || { echo "gitleaks-proba: nie znalazłam komendy gitleaks w bramki.sh (wzorzec \"\$bin\" git …)" >&2; exit 2; }
echo "  komenda bramki: $KOMENDA"
# ⛔ SAMOWYSTARCZALNOŚĆ (5.09, #287): do dziś próba żądała, żeby KTOŚ WCZEŚNIEJ uruchomił bramki.sh i pobrał binarkę —
# w pełnym przelocie robiła to przypadkiem wcześniejsza próba, a w osobnym odłamku CI nikt, więc próba oddawała kod 2
# i przelot padał na suficie niezmierzonych. Ukryta zależność od KOLEJNOŚCI, którą podział na odłamki ujawnił.
# Bierzemy PRAWDZIWĄ `zapewnij_narzedzie` z bramki.sh (tryb samych definicji), nie kopię — ta sama wersja, ta sama suma.
if [ ! -x "$BIN" ]; then
	# shellcheck source=narzedzia-qa/bramki.sh
	BRAMKI_TYLKO_FUNKCJE=1 . "$KORZEN/narzedzia-qa/bramki.sh"
	BIN=$(zapewnij_narzedzie gitleaks \
		"https://github.com/gitleaks/gitleaks/releases/download/v${WERSJA_GITLEAKS}/gitleaks_${WERSJA_GITLEAKS}_linux_x64.tar.gz" \
		gitleaks "$SUMA_GITLEAKS") || { echo "gitleaks-proba: nie udało się zapewnić gitleaks ${WERSJA_GITLEAKS} (sieć? suma?)" >&2; exit 2; }
	echo "  gitleaks pobrany/zweryfikowany przez zapewnij_narzedzie z bramki.sh: $BIN"
fi
R="$(mktemp -d)"; trap 'rm -rf "$R"' EXIT; BLEDY=0
# Klucz-atrapa składany W LOCIE z dwóch części: dosłowny `sk_test_…` w tym pliku paliłby gitleaks i bramkę poświadczeń
# w historii HEAD (zmierzone 26.08). 28 mieszanych znaków = wzorzec stripe-access-token + entropia (same litery gitleaks IGNORUJE).
KLUCZ="sk_$(printf 'test_%s' 'Zx9Qa7Lm3Kp2Vt8Rn5Wc1Yb4Hd6J')"
( cd "$R" && git init -q && git config user.email a@b.c && git config user.name a \
  && printf 'czysto\n' > a.txt && git add -A && git commit -qm baza \
  && git checkout -qb zatruta && printf 'klucz=%s\n' "$KLUCZ" > sekret.txt && git add -A && git commit -qm zatruty \
  && git checkout -q - )
skan() { ( cd "$R" && bin="$BIN" && eval "$KOMENDA" >/dev/null 2>&1 ); echo $?; }
k=$(skan); if [ "$k" -eq 0 ]; then echo "  OK   klucz TYLKO na innej gałęzi → bramka przechodzi (EXIT=0)"; else echo "  BLAD klucz na innej gałęzi pali bramkę (EXIT=$k) — skan po --all?"; BLEDY=$((BLEDY+1)); fi
( cd "$R" && printf 'klucz=%s\n' "$KLUCZ" > sekret.txt && git add -A && git commit -qm "zatruty na HEAD" )
k=$(skan); if [ "$k" -ne 0 ]; then echo "  OK   klucz w historii HEAD → bramka pada (EXIT=$k)"; else echo "  BLAD klucz w historii HEAD NIE pali bramki (EXIT=0)"; BLEDY=$((BLEDY+1)); fi
[ "$BLEDY" -eq 0 ] && { echo "GITLEAKS-PROBA: bramka odróżnia historię HEAD od cudzych referencji"; exit 0; }
echo "GITLEAKS-PROBA: $BLEDY błąd(y)"; exit 1
