#!/usr/bin/env python3
"""Werdykt Lighthouse dla JEDNEJ komorki (strona x tryb), z MEDIANY podanych przebiegow.

Uzycie: lh-werdykt.py <prog> <etykieta|cicho> <plik.json> [plik2.json ...]
  etykieta `cicho` = nie drukuj wiersza (sonda uzywana wewnatrz skryptu do decyzji o powtorkach).

EXIT 0 = kazda z 4 kategorii ma MEDIANE >= prog · 1 = ktoras ponizej · 2 = brak/zly plik.

PO CO MEDIANA: wydajnosc mierzona na maszynie z 2 vCPU jest szumna (tryb telefonu spowalnia
procesor 4x), wiec pojedynczy przebieg nie jest werdyktem. Zmierzone 27.08.2026: dwa pelne
przebiegi daly ponizej progu DWIE ROZNE komorki (segregator/mobile 93, potem sklep/mobile 87),
a powtorki tych samych komorek: 97/99/99 oraz 99/100/100.
Przy JEDNYM pliku mediana rowna sie wartosci — zachowanie jest wtedy identyczne jak przed
dodaniem powtorek (to jest warunek proby kontrolnej).
"""
import json
import sys

KATEGORIE = (
    ('perf', 'performance'),
    ('a11y', 'accessibility'),
    ('bp', 'best-practices'),
    ('seo', 'seo'),
)


def mediana(liczby):
    u = sorted(liczby)
    n = len(u)
    if n % 2:
        return u[n // 2]
    return round((u[n // 2 - 1] + u[n // 2]) / 2)


def wyniki(sciezka):
    with open(sciezka, encoding='utf-8') as f:
        kat = json.load(f)['categories']
    return [round((kat[klucz]['score'] or 0) * 100) for _, klucz in KATEGORIE]


def main():
    if len(sys.argv) < 4:
        print('uzycie: lh-werdykt.py <prog> <etykieta|cicho> <plik.json> [...]', file=sys.stderr)
        return 2
    prog = int(sys.argv[1])
    etykieta = sys.argv[2]
    pliki = sys.argv[3:]

    try:
        przebiegi = [wyniki(p) for p in pliki]
    except (OSError, ValueError, KeyError) as blad:
        print('lh-werdykt: nie umiem odczytac wyniku: %s' % blad, file=sys.stderr)
        return 2

    med = [mediana([p[i] for p in przebiegi]) for i in range(len(KATEGORIE))]
    ponizej = [nazwa for (nazwa, _), wartosc in zip(KATEGORIE, med) if wartosc < prog]

    if etykieta != 'cicho':
        czesci = etykieta.split()
        strona = czesci[0]
        tryb = czesci[1] if len(czesci) > 1 else ''
        ogon = ''
        if len(pliki) > 1:
            surowe = ' / '.join(str(p[0]) for p in przebiegi)
            ogon += '   perf z %d przebiegow: %s -> mediana %d' % (len(pliki), surowe, med[0])
        if ponizej:
            ogon += '   PONIZEJ %d: %s' % (prog, ','.join(ponizej))
        print('%-11s %-8s %5d %5d %5d %5d%s' % (strona, tryb, med[0], med[1], med[2], med[3], ogon))

    return 1 if ponizej else 0


if __name__ == '__main__':
    sys.exit(main())
