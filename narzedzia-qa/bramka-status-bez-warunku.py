#!/usr/bin/env python3
"""bramka-status-bez-warunku.py — zapis statusu zamowienia MUSI chronic stan poprzedni.  2.0.0

PO CO (recenzje PR #198, rundy 2, 4, 5): TRZY razy pod rzad ten sam wzorzec — kod wpisywal
status zamowienia jako stala, bez pytania, w jakim stanie zamowienie bylo. Wygrany spor
meldowal „mail wyslany" zamowieniu, do ktorego mail nie doszedl; cofniety zwrot to samo;
„Wyslij ponownie" kasowalo znacznik zwrotu. Za kazdym razem znajdowal to recenzent, po jednym
egzemplarzu na runde. Ta bramka pyta o CALA KLASE naraz.

WERSJA 2.0.0 (runda 7) — poprzednia, grepowa, miala TRZY slepe plamy, wszystkie zmierzone
przez recenzenta:
  * widziala tylko zapisy przez nazwe `$pz` (alias tej samej tabeli przechodzil),
  * pomijala CALY plik, w ktorym nie bylo przypisania `$x = $wpdb->prefix . '...'` —
    a nazwe tabeli wolno wpisac wprost: `UPDATE {$wpdb->prefix}sklepag_zamowienia`,
  * szukala LINIOWO, wiec zapytanie rozbite na dwie linie nie trafialo na liste.
Dlatego bramka czyta teraz CALE stringi SQL, nie linie, i rozpoznaje tabele na dwa sposoby:
przez zmienna przypisana do nazwy tabeli ORAZ przez nazwe wpisana wprost.

ZASADA: kazdy `UPDATE <tabela zamowien> ... SET ... status=...` musi albo miec w tym samym
zapytaniu warunek na `status` w `WHERE`, albo niesc w promieniu trzech linii nad poczatkiem
zapytania jawny znacznik:   // status-bez-warunku: <dlaczego to bezpieczne>

Wywolanie: python3 narzedzia-qa/bramka-status-bez-warunku.py [sciezka-repo]
Kody wyjscia: 0 = kazdy zapis chroniony albo jawnie zwolniony · 1 = zapis bez ochrony
              ALBO zero znalezionych zapisow (zero to slepota, nie czystosc)
              2 = nie ma czego sprawdzac (brak katalogu wtyczki)
"""
import os
import re
import sys

TABELA = "sklepag_zamowienia"
ZNACZNIK = "status-bez-warunku:"

repo = sys.argv[1] if len(sys.argv) > 1 else "."
wtyczka = os.path.join(repo, "wtyczki", "sklep-agentow")
if not os.path.isdir(wtyczka):
    sys.stderr.write("STATUS-GUARD: brak %s\n" % wtyczka)
    sys.exit(2)

# String SQL w podwojnych cudzyslowach, moze byc WIELOLINIOWY.
STRING_SQL = re.compile(r'"((?:[^"\\]|\\.)*)"', re.S)
# Przypisanie zmiennej do nazwy tabeli zamowien.
PRZYPISANIE = re.compile(
    r"\$([A-Za-z_][A-Za-z0-9_]*)\s*=\s*\$wpdb->prefix\s*\.\s*['\"]" + TABELA + r"['\"]"
)
# Zapis statusu: `status=` nie poprzedzone znakiem slowa (zeby `status_przed_sporem=` odpadlo).
ZAPIS_STATUSU = re.compile(r"(?<![A-Za-z0-9_])status\s*=")
# Warunek ochronny: `status` gdziekolwiek po WHERE.
WHERE_ZE_STATUSEM = re.compile(r"\bWHERE\b.*?(?<![A-Za-z0-9_])status\b", re.S | re.I)

bledy = []
zbadane = 0

for korzen, _, pliki in os.walk(wtyczka):
    for nazwa in pliki:
        if not nazwa.endswith(".php"):
            continue
        sciezka = os.path.join(korzen, nazwa)
        try:
            with open(sciezka, encoding="utf-8") as f:
                tresc = f.read()
        except OSError:
            continue

        # ⛔ Komentarze WYCINAMY przed parsowaniem stringow. Komentarz z cudzyslowem
        # (a takich jest u nas duzo, bo piszemy po polsku i cytujemy statusy) rozjezdza
        # parowanie `"` i cale fragmenty pliku wypadaja poza analize — zmierzone: bramka
        # widziala 5 zapisow zamiast 9. Numeracja linii MUSI zostac, wiec komentarz zastepuje
        # pusta linia o tej samej dlugosci w liniach, a nie znika.
        linie = tresc.split("\n")
        for i, linia in enumerate(linie):
            bez_bialych = linia.lstrip()
            if bez_bialych.startswith("//") or bez_bialych.startswith("*") or bez_bialych.startswith("/*"):
                linie[i] = ""
        analiza = "\n".join(linie)

        zmienne = set(PRZYPISANIE.findall(analiza))
        # Tabela rozpoznawana na DWA sposoby: przez zmienna i przez nazwe wpisana wprost.
        wzorce_tabeli = [r"\{\$wpdb->prefix\}" + TABELA]
        wzorce_tabeli += [r"\{\$" + re.escape(z) + r"\}" for z in sorted(zmienne)]
        TABELA_W_SQL = re.compile("|".join(wzorce_tabeli))

        for dopasowanie in STRING_SQL.finditer(analiza):
            sql = dopasowanie.group(1)
            if "UPDATE" not in sql.upper() or "SET" not in sql.upper():
                continue
            if not TABELA_W_SQL.search(sql):
                continue
            czesc_set = sql.upper().split("SET", 1)[1]
            czesc_set = sql[len(sql) - len(czesc_set):]
            if not ZAPIS_STATUSU.search(czesc_set):
                continue

            zbadane += 1
            if WHERE_ZE_STATUSEM.search(sql):
                continue

            nr = analiza.count("\n", 0, dopasowanie.start()) + 1
            poczatek = max(0, nr - 4)
            kontekst = tresc.split("\n")[poczatek:nr]
            if any(ZNACZNIK in linia for linia in kontekst):
                continue
            wzgledna = os.path.relpath(sciezka, repo)
            bledy.append((wzgledna, nr, " ".join(sql.split())[:140]))

if zbadane == 0:
    sys.stderr.write(
        "STATUS-GUARD: nie znalazlam ANI JEDNEGO zapisu statusu w %s — wzorzec przestal pasowac\n"
        "  albo zmienil sie ksztalt kodu. To NIE jest 'czysto'.\n" % wtyczka
    )
    sys.exit(1)

if bledy:
    for plik, nr, sql in bledy:
        sys.stderr.write("STATUS-GUARD: %s:%s — zapis statusu bez warunku na stan poprzedni.\n" % (plik, nr))
        sys.stderr.write("  %s\n" % sql)
    sys.stderr.write(
        "  Dodaj warunek WHERE ... AND status NOT IN (...) albo znacznik: // %s <powod>\n" % ZNACZNIK
    )
    sys.exit(1)

print("STATUS-GUARD: %d zapisow statusu zamowienia — kazdy chroniony albo jawnie zwolniony." % zbadane)
sys.exit(0)
