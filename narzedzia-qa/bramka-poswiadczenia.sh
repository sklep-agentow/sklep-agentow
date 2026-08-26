#!/bin/bash
# bramka-poswiadczenia.sh — ŻYWE POŚWIADCZENIA W REPO (ciasteczka sesji WP, nonce ze zrzutów/URL/JSON, sekrety Stripe).   WERSJA 1.1.1
#
# Po co (26.08, recenzja #188 r2 KRYTYCZNA): commit „dowody do repo” wniósł plik cookie-jar z ważną 46 h sesją
# `audytor-admin`, zrzuty HTML panelu z aktualnymi nonce'ami i sekret webhooka — gitleaks (reguły ogólne) i `slady`
# (skanuje tylko wtyczki/) puściły to na zielono. Ta bramka skanuje CAŁE repo: drzewo (śledzone + nieśledzone poza
# .gitignore) ORAZ historię wszystkich osiągalnych commitów (1.1.0, recenzja #189 W2: „git rm w nowym commicie”
# nie czyści historii — gałąź trzeba przepisać albo zastąpić czystą).
# Wzorce 1.1.0 (recenzja #189 W1): nonce także w URL (`wp_nonce_url` → `&_wpnonce=…`, tak robi panel sklepu) i w JSON;
# `whsec_` od 6 znaków (prawdziwe sekrety Stripe mają 32+; prefiks generatora `whsec_TEST_` w testach ma 5 → nie łapie).
# EXIT 0 = zero trafień w drzewie I w historii · 1 = trafienia (plik:linia albo commit:plik:linia) · 2 = poza repozytorium git.
set -u
cd "$(git rev-parse --show-toplevel 2>/dev/null)" || { echo "bramka-poswiadczenia: nie w repo git" >&2; exit 2; }
WZORCE='wordpress_logged_in_[0-9a-f]{32}|wordpress_sec_[0-9a-f]{32}|^#HttpOnly_|^# Netscape HTTP Cookie File|whsec_[A-Za-z0-9_]{6,}|(sk|rk)_(test|live)_[A-Za-z0-9]{16,}|name="_wpnonce" value="[0-9a-f]{10}"|[?&]_wpnonce=[0-9a-f]{10}|"nonce":"[0-9a-f]{10}"'
WYJATKI=':!narzedzia-qa/bramka-poswiadczenia.sh :!narzedzia-qa/bramka-poswiadczenia-proba-kontrolna.sh'

# Faza 1: DRZEWO ROBOCZE (śledzone + nieśledzone poza .gitignore).
TRAFIENIA=$(git ls-files -z --cached --others --exclude-standard \
	| grep -zvE '^narzedzia-qa/bramka-poswiadczenia(-proba-kontrolna)?\.sh$' \
	| xargs -0 grep -nHE -- "$WZORCE" 2>/dev/null | cut -c1-200 || true)
if [ -n "$TRAFIENIA" ]; then
	echo "POSWIADCZENIA: żywe poświadczenia w DRZEWIE — usuń plik/wartość, unieważnij sesję (wp user session destroy <login> --all), obróć sekret:"
	printf '%s\n' "$TRAFIENIA" | head -40
	echo "POSWIADCZENIA: $(printf '%s\n' "$TRAFIENIA" | wc -l) trafień w drzewie → EXIT 1"
	exit 1
fi

# Faza 2: HISTORIA — każdy commit osiągalny z HEAD (na runnerze HEAD = merge PR-a z main, więc obejmuje całą gałąź
# i cały main). NIE `--all` (1.1.1): GitHub trzyma `refs/pull/N/head` zamkniętych PR-ów na zawsze i runner je widzi —
# #188/#189 (martwy sekret testowy, AN-012) świeciłyby czerwono do końca życia repo, choć nic z nich nie jest scalane.
# Pytanie bramki: „czy TO, co scalamy, niesie sekret w historii”.
# shellcheck disable=SC2086
HIST=$(git grep -nIE -- "$WZORCE" $(git rev-list HEAD) -- . $WYJATKI 2>/dev/null | cut -c1-200 || true)
if [ -n "$HIST" ]; then
	echo "POSWIADCZENIA: żywe poświadczenia w HISTORII (commit:plik:linia) — gałąź przepisać albo zastąpić czystą od main, sesje unieważnić, sekret obrócić:"
	printf '%s\n' "$HIST" | head -20
	echo "POSWIADCZENIA: $(printf '%s\n' "$HIST" | wc -l) trafień w historii → EXIT 1"
	exit 1
fi
echo "POSWIADCZENIA: 0 trafień (drzewo: $(git ls-files --cached --others --exclude-standard | wc -l) plików; historia od HEAD: $(git rev-list HEAD | wc -l) commitów)"
