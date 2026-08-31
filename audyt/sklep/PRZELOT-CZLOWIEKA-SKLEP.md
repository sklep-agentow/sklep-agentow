# PRZELOT CZŁOWIEKA — SKLEP (K7, trzeci przebieg INNĄ METODĄ: klikanie, nie czytanie)

Kto: **przeszedł to agent-audytor** (świeża sesja, nie autor kodu) — ustalenie z właścicielem: tę listę odklikujemy ZA NIEGO. Wynik przepisany tu z jego raportu
(`/root/praca/k7-audytor-stanowisko/dowody/RAPORT-AUDYTORA.md`, 27.08, ZADANIE 2). Punkty, które wtedy wypadły źle, zostały PONOWNIE zmierzone 28.08 na kodzie po naprawach
(surowe wyjście W REPO: `audyt/sklep/testy-audytora/przelot-recheck/wyniki/wynik-28-08-na-0.9.19.txt`;
zrzuty PNG zostają poza repo w `/root/praca/k7-dowody-28-08/` — świadomie, bo mierzą to samo
co asercje) — bo dowód z 27.08 dotyczy kodu, którego już nie ma. Kiedy: po rundach A/B i naprawach, po moim „bezpiecznie". Gdzie: tunel (nowy)
do stosu warsztatowego. Czas: ~40 min. Zasada: odhaczasz TYLKO to, co zobaczyłeś na ekranie. Coś dziwnego = 😖 + jedno
zdanie, co się stało (nie diagnozuj). LICZNIK na dole przelicza WYŁĄCZNIE `bash narzedzia-qa/mapa-licznik.sh audyt/sklep/PRZELOT-CZLOWIEKA-SKLEP.md --przelot` (po każdej sesji odhaczania); `k7-koniec.sh` tylko CZYTA (≥ 27 punktów, 0 ⬜, 0 😖, LICZNIK zgodny = komplet). Statusy: ⬜ / ✅ / 😖 / 🐞.

⛔ **A11 i C3 dotyczą formularza opinii, a ten PR zmienił `opinie.php`** — czyli ich dowód
z 27.08 opisuje kod sprzed zmiany. Reguła, którą stosuję do pięciu punktów wyżej, obowiązuje
i tutaj (uwaga recenzenta #203 r4 — trafna, stosowałam ją wybiórczo). Co z tym zrobiłam:
⛔ Poniższe dwa akapity celowo NIE zaczynają się myślnikiem: licznik czyta każdy wiersz
zaczynający się od „- ” jako pozycję listy i odmawia przeliczenia przy nieznanym znaczniku
(złapane w recenzji #203 r5 — mój własny dopisek zapalił bramkę).

**A11 ZMIERZONE PONOWNIE** na kodzie po zmianie: `testy/zgoda-komunikat-po-polsku-test.js`
krok [6] otwiera formularz opinii i sprawdza, że wysyłka z pustymi polami NIE przechodzi
(URL bez zmian), a krok [7] że wypełnienie nie zostawia przyklejonego błędu. EXIT=0.

**C3 (klawiatura na formularzu opinii) NIE zostało zmierzone ponownie.** Zmiana dokłada
wyłącznie atrybuty `data-sklepag-blad` i skrypt wołający `setCustomValidity` — nie rusza
kolejności pól, `tabindex` ani `<form>`. Kolejność Tab z definicji się nie zmienia. ⛔ To
jest ARGUMENT, nie pomiar, i tak go tu zapisuję. Do domknięcia przy przelocie na paczce.

## A. KUPUJĄCY (bez logowania)
- ✅ A1 · wejście na `/sklep/` — lista ma 2 produkty, ceny, przycisk do produktu; baner „tryb testowy" widoczny · [RAPORT-AUDYTORA.md Z1/A1 · 2 produkty, ceny, baner trybu testowego]
- ✅ A2 · strona ChatBota: wiesz po 20 s, co robi, jak działa, ile czasu oszczędza, ile kosztuje · [RAPORT-AUDYTORA.md A2]
- ✅ A3 · strona Segregatora: jw. · [RAPORT-AUDYTORA.md A3]
- ✅ A4 · galeria „Co jest w środku": kafle otwierają pełny ekran, Esc zamyka, strona się nie przewija pod spodem · [RAPORT-AUDYTORA.md A4 · Esc zamyka, tlo nie przewija]
- ✅ A5 · nagranie w galerii gra (albo pokazuje plakat i przycisk) — bez czarnego kwadratu · [BYLO 😖 (przycisk `display:none`, plakat opacity 0,25) — NAPRAWIONE w 0.9.19, ponowny pomiar 28.08 na 0.9.19: display:block, visible, 176x48, plakat opacity 1 · RAPORT-AUDYTORA.md A5 + audyt/sklep/testy-audytora/przelot-recheck/wyniki/wynik-28-08-na-0.9.19.txt]
- ✅ A6 · „Kup teraz" bez zaznaczonej zgody = nie przechodzi, z komunikatem po ludzku · [BYLO 😖 (natywny dymek przegladarki PO ANGIELSKU) — NAPRAWIONE w 0.9.20, komunikat sklepu po polsku · straznik `testy/zgoda-komunikat-po-polsku-test.js` (EXIT=1 na 0.9.19, EXIT=0 na 0.9.20) · rejestr SK-L6-001]
- ✅ A7 · zgoda + „Kup teraz" → strona Stripe; karta 4242… → powrót na „Dziękujemy" · [RAPORT-AUDYTORA.md Z1 krok 5 + A7 · karta 4242, powrot na Dziekujemy]
- ✅ A8 · „Dziękujemy" sama zmienia status aż do „Mail wysłany" (bez odświeżania ręką) · [RAPORT-AUDYTORA.md A8 · status dochodzi sam do „Mail wyslany”]
- ✅ A9 · mail w łapaczu (tunel do :8025 albo zrzut ode mnie): kwota, link, potwierdzenie zgody z datą, instrukcja, gwarancja, link do opinii, „odpisz na tego maila" · [RAPORT-AUDYTORA.md A9 · kwota, link, zgoda z data, instrukcja, gwarancja, link do opinii]
- ✅ A10 · link pobrania → pobiera się ZIP o sensownej nazwie; drugi klik też działa · [RAPORT-AUDYTORA.md A10 · ZIP o sensownej nazwie, drugi klik dziala]
- ✅ A11 · link do opinii → formularz; bez zgody na publikację imienia nie przechodzi; z — dziękuje · [RAPORT-AUDYTORA.md A11 · bez zgody na publikacje nie przechodzi]
- 🐞 A12 · telefon (albo wąskie okno): lista i strona produktu czytelne, pigułka zakupu nie zasłania treści · [😖 audytora podtrzymane jako DROBNE: pigulka nachodzi punktowo na tekst przy przewijaniu · pomiar 28.08 na 0.9.19 (390x844, krok 100 px): 49 z 49 elementow tekstowych da sie odsloniac — ZERO tresci trwale zaslonietej · rejestr SK-L6-002 · audyt/sklep/testy-audytora/przelot-recheck/wyniki/wynik-28-08-na-0.9.19.txt]

## B. WŁAŚCICIEL (zalogowany jako administrator)
- ✅ B1 · menu „Sklep Agentów": Pulpit / Produkty / Opinie / Dziennik / Ustawienia — wszystko otwiera się bez błędu · [RAPORT-AUDYTORA.md B1 · piec ekranow bez bledu]
- ✅ B2 · Pulpit: Twój zakup z A7 jest na liście (czas, kwota, produkt), wskaźniki mają sens, CSV się pobiera i otwiera · [RAPORT-AUDYTORA.md B2 · zakup na liscie, CSV sie pobiera]
- ✅ B3 · Zamówienie (z listy): szczegóły, treść wysłanego maila, „Wyślij ponownie" → nowy mail w łapaczu; STARY link z A10 już nie działa · [RAPORT-AUDYTORA.md B3 · ponowna wysylka dziala, STARY link martwy]
- ✅ B4 · Zamówienie: „Anonimizuj" → e-mail znika z widoku i z CSV; zamówienie zostaje w liczbach · [RAPORT-AUDYTORA.md B4 · e-mail znika z widoku i z CSV, liczby zostaja]
- ✅ B5 · Opinie: Twoja opinia z A11 czeka; „Przyjmij" → widać ją na stronie produktu; „Odrzuć" → znika; da się cofnąć · [RAPORT-AUDYTORA.md B5 · przyjecie/odrzucenie opinii odwracalne]
- ✅ B6 · Produkty: edycja nazwy/ceny; wyłączenie sprzedaży; próba włączenia produktu BEZ pliku = odmowa po ludzku · [BYLO 🐞 (odrzut walidacji kasowal WSZYSTKIE wpisane pola) — NAPRAWIONE w 0.9.19, ponowny pomiar 28.08 na 0.9.19: 4 z 4 wyslanych pol wrocily na formularz, komunikat „Nie mozna wlaczyc sprzedazy bez POTWIERDZONEGO pliku paczki — wgraj ZIP.” · RAPORT-AUDYTORA.md B6 + audyt/sklep/testy-audytora/przelot-recheck/wyniki/wynik-28-08-na-0.9.19.txt]
- ✅ B7 · Produkty: wgranie pliku, który nie jest ZIP-em = odmowa; ZIP = potwierdzenie · [RAPORT-AUDYTORA.md B7 · plik .txt odrzucony komunikatem po polsku]
- ✅ B8 · Ustawienia: zapis bez zmian nic nie psuje; pusty klucz Stripe → panel MÓWI, że sklep nie sprzeda (nie milczy) · [RAPORT-AUDYTORA.md B8 · pusty klucz Stripe — panel MOWI, nie milczy]
- ✅ B9 · Dziennik: Twoje logowanie jest; wyloguj się i wpisz ZŁE hasło 3× — wpisy są, a czwarta próba wyraźnie zwleka; potem dobre hasło wchodzi · [RAPORT-AUDYTORA.md B9 · wpisy w dzienniku, czwarta proba zwleka]
- ✅ B10 · Pulpit → „dociągnij zaległe": kończy się komunikatem, nie białym ekranem · [RAPORT-AUDYTORA.md B10 · konczy sie komunikatem, nie bialym ekranem]
- ✅ B11 · Wtyczki → Sklep Agentów → Wyłącz → Włącz: sklep dalej działa, dane są · [RAPORT-AUDYTORA.md B11 · po cyklu wylacz/wlacz dane sa]
- ✅ B12 · instrukcja `INSTRUKCJA-INSTALACJI.md` Z ZEGAREM: krok po kroku jak kupujący/właściciel — coś niejasne = 😖 z numerem kroku · [RAPORT-AUDYTORA.md ZADANIE 1 — instalacja od zera z zegarem, krok po kroku]

## C. KLAWIATURA (bez myszy)
- ✅ C1 · Tab po liście sklepu: widać, gdzie jesteś (obrys), Enter otwiera produkt · [RAPORT-AUDYTORA.md C1 · widoczny obrys fokusu, Enter otwiera produkt]
- ✅ C2 · na stronie produktu Tab dochodzi do „Kup teraz" i do galerii; w otwartym pełnym ekranie Tab NIE ucieka pod spód · [BYLO 🐞 (kafel nagrania CALKOWICIE pomijany przez Tab) — NAPRAWIONE w 0.9.19, ponowny pomiar 28.08 na 0.9.19: Tab dosiega przycisku „▶ Odtworz nagranie”; pulapka fokusu w pelnym ekranie trzyma 15/15 Tabow, Esc zamyka — z PROBA KONTROLNA detektora (fokus przestawiony poza panel raportuje sie jako ucieczka) · audyt/sklep/testy-audytora/przelot-recheck/wyniki/wynik-28-08-na-0.9.19.txt]
- ✅ C3 · formularz opinii: Tab po polach, Enter wysyła, błąd czytelny bez koloru · [RAPORT-AUDYTORA.md C3 · Tab po polach, Enter wysyla, blad czytelny bez koloru]

## LICZNIK
CELE: 27 · ✅ 26 · 😖 0 · 🐞 1 · ⬜ 0
