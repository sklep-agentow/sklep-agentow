#!/bin/bash
# bramka-poswiadczenia.sh — ŻYWE POŚWIADCZENIA W REPO (ciasteczka sesji WP, nonce ze zrzutów, sekrety Stripe).   WERSJA 1.0.0
#
# Po co (26.08, recenzja #188 r2 KRYTYCZNA): commit „dowody do repo” wniósł plik cookie-jar z ważną 46 h sesją
# `audytor-admin`, zrzuty HTML panelu z aktualnymi nonce'ami i sekret webhooka — gitleaks (reguły ogólne) i `slady`
# (skanuje tylko wtyczki/) puściły to na zielono. Ta bramka skanuje CAŁE repo (śledzone + nieśledzone poza .gitignore).
# EXIT 0 = zero trafień · 1 = trafienia (wypisane plik:linia) · 2 = poza repozytorium git.
set -u
cd "$(git rev-parse --show-toplevel 2>/dev/null)" || { echo "bramka-poswiadczenia: nie w repo git" >&2; exit 2; }
WZORCE='wordpress_logged_in_[0-9a-f]{32}|wordpress_sec_[0-9a-f]{32}|^#HttpOnly_|^# Netscape HTTP Cookie File|whsec_[A-Za-z0-9_]{10,}|(sk|rk)_(test|live)_[A-Za-z0-9]{16,}|name="_wpnonce" value="[0-9a-f]{10}"'
TRAFIENIA=$(git ls-files -z --cached --others --exclude-standard \
	| grep -zvE '^narzedzia-qa/bramka-poswiadczenia(-proba-kontrolna)?\.sh$' \
	| xargs -0 grep -nHE -- "$WZORCE" 2>/dev/null | cut -c1-200 || true)
if [ -n "$TRAFIENIA" ]; then
	echo "POSWIADCZENIA: żywe poświadczenia w repo — usuń plik/wartość, unieważnij sesję (wp user session destroy <login> --all), obróć sekret:"
	printf '%s\n' "$TRAFIENIA" | head -40
	echo "POSWIADCZENIA: $(printf '%s\n' "$TRAFIENIA" | wc -l) trafień → EXIT 1"
	exit 1
fi
echo "POSWIADCZENIA: 0 trafień (skan: $(git ls-files --cached --others --exclude-standard | wc -l) plików)"
