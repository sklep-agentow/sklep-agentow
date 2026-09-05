#!/bin/bash
# Proba kontrolna bramki zgodnosci dokumentacji (zasada D14: kontrola, ktora nie
# znajdzie PODLOZONEGO bledu, jest slepa).
#
# Po co osobno: kontrola testy/dokumentacja-zgodnosc.sh (nie bramka CI — biegnie u recenzenta i w przelocie recznym) przez dlugi czas
# porownywala KOD z liczba wpisana w SAMEJ SOBIE, a nie z dokumentacja. Wiersz
# "numer schematu, obecnie 3" przy schemacie 4 przeszedl przez nia na zielono
# i zlapal go dopiero recenzent (PR #73) — a rozjazd dokumentacji z kodem to
# klasa #3 sciagi egzaminatora, najczestsza uwaga w poprzednim projekcie.
#
# Ta proba podklada nieaktualna liczbe w KAZDYM miejscu dokumentacji, ktore
# cytuje kod, i sprawdza, ze bramka za kazdym razem PADA.
#
# Uzycie: bash testy/zgodnosc-proba-kontrolna.sh    (z katalogu repo)
set -u
DOK=wtyczki/chatbot-live/DOKUMENTACJA-TECHNICZNA.md
KOPIA=$(mktemp)
cp "$DOK" "$KOPIA"
trap 'cp "$KOPIA" "$DOK"; rm -f "$KOPIA"' EXIT
BLEDY=0

# probuj "opis" "sed-podkladajacy-nieaktualna-liczbe" "fragment-kroku-ktory-ma-paść"
probuj() {
  local opis="$1" mutacja="$2" krok="$3"
  cp "$KOPIA" "$DOK"
  eval "$mutacja"
  if ! diff -q "$KOPIA" "$DOK" >/dev/null; then
    local wyjscie kod
    wyjscie=$(bash testy/dokumentacja-zgodnosc.sh 2>&1); kod=$?
    if [ "$kod" -ne 0 ] && echo "$wyjscie" | grep -q "BLAD.*$krok"; then
      printf '  OK   bramka PADLA na: %-44s (EXIT=%d)\n' "$opis" "$kod"
      echo "$wyjscie" | grep "BLAD.*$krok" | head -1 | sed 's/^/         /'
    else
      printf '  BLAD bramka NIE zlapala: %-41s (EXIT=%d)\n' "$opis" "$kod"
      BLEDY=$((BLEDY+1))
    fi
  else
    printf '  BLAD mutacja nie weszla do pliku: %-32s\n' "$opis"
    BLEDY=$((BLEDY+1))
  fi
}

echo "=== PROBA KONTROLNA: czy bramka widzi nieaktualna dokumentacje ==="

# 1. Dokladnie ten blad, ktory zglosil recenzent PR #73.
probuj "sekcja 4: numer schematu podmieniony na nieaktualny" \
  "sed -i 's/numer schematu, obecnie \`[0-9]*\`/numer schematu, obecnie \`0\`/' $DOK" \
  "sekcja 4 (wp_options): schemat = kod"

# 2. Naglowek dokumentu z nieaktualna wersja wtyczki.
probuj "naglowek: wersja wtyczki podmieniona na nieaktualna" \
  "sed -i 's/^Wersja wtyczki: \*\*[0-9.]*\*\*/Wersja wtyczki: **0.0.1**/' $DOK" \
  "naglowek dokumentacji: wersja = kod"

# 3. Naglowek dokumentu z nieaktualnym numerem schematu.
probuj "naglowek: schemat podmieniony na nieaktualny" \
  "sed -i 's/schemat bazy: \*\*[0-9]*\*\*/schemat bazy: **0**/' $DOK" \
  "naglowek dokumentacji: schemat = kod"

# 4. Nieaktualna wartosc stalej w tabeli sekcji 5 (tu: prog scalania luk).
probuj "tabela stalych: CHATL_LUKI_PROG z nieaktualna wartoscia" \
  "sed -i 's/| \`CHATL_LUKI_PROG\` | \`[0-9]*\`/| \`CHATL_LUKI_PROG\` | \`99\`/' $DOK" \
  "DOK=KOD: CHATL_LUKI_PROG"

# 5. Uklad tabeli rozjechany tak, ze petla nie sprawdzilaby NICZEGO —
#    kontrola z pusta petla jest grozniejsza niz brak kontroli.
probuj "tabela stalych rozbita (petla bez ani jednego sprawdzenia)" \
  "sed -i 's/^| \`CHATL_/| CHATL_/' $DOK" \
  "stalych sprawdzonych z tabeli dokumentacji"

# Mutant na kontrole "kazdy plik includes/ jest wymagany". Mutuje INNY plik niz
# reszta prob (chatbot-live.php, nie dokumentacje), wiec ma wlasna kopie i wlasne
# przywrocenie. Blad, ktory to odtwarza, zdarzyl sie naprawde: konflikt scalania
# 1.4.10 z 1.5.0 zjadl require dla includes/przejecie.php, plik zostal w paczce,
# allowlista swiecila na zielono, a polowa wtyczki po prostu sie nie ladowala.
BOOT=wtyczki/chatbot-live/chatbot-live.php
KOPIA_BOOT=$(mktemp)
cp "$BOOT" "$KOPIA_BOOT"
sed -i "/require_once CHATL_KATALOG . 'includes\/przejecie.php';/d" "$BOOT"
if ! diff -q "$KOPIA_BOOT" "$BOOT" >/dev/null; then
  wyjscie=$(bash testy/dokumentacja-zgodnosc.sh 2>&1); kod=$?
  if [ "$kod" -ne 0 ] && echo "$wyjscie" | grep -q "BLAD.*jest wymagany przez require_once"; then
    printf '  OK   bramka PADLA na: %-44s (EXIT=%d)\n' "zgubiony require dla pliku z includes/" "$kod"
  else
    printf '  BLAD bramka NIE zlapala: %-41s (EXIT=%d)\n' "zgubiony require dla pliku z includes/" "$kod"
    BLEDY=$((BLEDY+1))
  fi
else
  printf '  BLAD mutacja nie weszla do pliku: %-32s\n' "zgubiony require"
  BLEDY=$((BLEDY+1))
fi
cp "$KOPIA_BOOT" "$BOOT"
rm -f "$KOPIA_BOOT"

echo
echo "=== KONTROLA ODWROTNA: czysta dokumentacja ma przechodzic ==="
cp "$KOPIA" "$DOK"
if bash testy/dokumentacja-zgodnosc.sh > /dev/null 2>&1; then
  echo "  OK   czysta dokumentacja przechodzi (EXIT=0)"
else
  echo "  BLAD czysta dokumentacja NIE przechodzi — bramka pada zawsze, wiec nic nie znaczy"
  BLEDY=$((BLEDY+1))
fi

echo
if [ "$BLEDY" -eq 0 ]; then
  echo "BRAMKA WIDZI KAZDA NIEAKTUALNA LICZBE W DOKUMENTACJI"
else
  echo "BRAMKA SLEPA NA $BLEDY MIEJSC"
fi
exit $((BLEDY > 0))
