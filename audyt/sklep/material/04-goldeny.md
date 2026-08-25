# 04 — GOLDENY SKLEPU (złote zasady z naszych blizn — audytor traktuje je jak wymagania kontraktowe)

Źródła: `CLAUDE.md` repo (sekcje „Rzeczy, które wywalały nas wcześniej" i „Bezpieczeństwo — minimum podręcznikowe"),
`PLAN-PROJEKTU.md` §7, `DECYZJE.md` D22/D24/D26, `SCIAGA-EGZAMINATORA.md`. Naruszenie goldena = znalezisko z wagą wg §5 `jak-audytowac`.

1. **Nonce I sprawdzenie uprawnień PARĄ na każdej mutacji panelu** (nonce = CSRF, uprawnienia = autoryzacja). Jedno bez
   drugiego = DUŻA (cicha), nawet gdy dziś nieeksploatowalne — kwalifikację napisz wprost.
2. **Funkcja autoryzująca nie ma domyślnego otwarcia:** brak danych = odmowa, nie zgoda (D-kontrakt INNER JOIN opinii).
3. **`$wpdb->prepare()` do każdego SQL z wejściem; `LIKE` wyłącznie z `esc_like()`** (klasa 2 ściągi).
4. **Sanitize wcześnie (konkretne klucze + `wp_unslash`), escape późno** (`esc_html/attr/url`) — opisy produktów renderowane jako TEKST.
5. **Jedno źródło prawdy o stanie zakupu = `zamowienia.status`; każde przejście stanu = atomowy UPDATE z warunkiem na stan
   poprzedni; przegrywający silnik wycofuje się BEZ maila** (D24). Dwa maile = KRYTYCZNA.
6. **Cena i produkt wyłącznie z bazy** — nigdy z żądania.
7. **Liczniki limitów zużywane PO sukcesie, nie przed; limit mierzy wymiar, który robi szkodę** (klasa „zły wymiar licznika");
   trasa webhooka JAWNIE poza limiterem (autoryzacją jest podpis).
8. **Podpis webhooka porównywany stałoczasowo na SUROWYM body.**
9. **Wywołania zewnętrzne: suma prób w jednym żądaniu PHP wyraźnie < 30 s** (sklep: 15 s × 1; ponowienia robią Stripe/cron).
10. **Sekrety w opcjach `autoload=no`, nigdy w repo/logach/CSV/mailu; uninstall kasuje sekrety i cron ZAWSZE** (D22; klasa 1 ściągi).
11. **Nowa tabela/opcja = trzy miejsca naraz: `dbDelta` + `uninstall.php` + ścieżka podniesienia schematu** (symetria zapis↔sprzątanie).
12. **Licznik/stan NIE w `wp_options`** — własna tabela (klasa 7 ściągi; boty: licznik gubił 13/20 równoległych zapisów).
13. **Wynik KAŻDEGO zapisu do bazy sprawdzany; kod ZMIERZYŁ stan świata PO operacji** — komunikat sukcesu bez dowodu = cicha awaria.
14. **Każda liczba w treściach dla klienta (mail, FAQ, strona produktu, readme) generowana ze stałej, nie wpisana ręcznie**
    (`SKLEPAG_LIMIT_POBRAN`, `SKLEPAG_LINK_GODZIN`, `SKLEPAG_OPINIA_DNI`) — klasa 3 ściągi.
15. **Wersja mówi to samo w sześciu miejscach** (nagłówek, stała, Stable tag, CHANGELOG, changelog readme, DOK-TECH) — klasa 4.
16. **Paczka bez śladów wewnętrznych** (TODO, notatki QA, adresy dev, numery zgłoszeń, imiona) — klasa 5.
17. **Kod nie robi więcej niż spec bez wpisu w PONAD-SPECYFIKACJE** — klasa 6.
18. **Opóźnienie logowań to OPÓŹNIENIE, nie twarda blokada** (D26): wspólne konta, egzaminator nie może zostać zablokowany;
    limit 6/min per IP odbija natychmiast (429) PRZED sleep.
19. **Assety tylko tam, gdzie działają** (front: strony sklepu; panel: ekrany `sklepag`).
20. **Naprawa = KLASA, nie egzemplarz** (grep po całym repo); każdy naprawiony błąd ma strażnika z `exit 1`, który PADA na starym kodzie.
