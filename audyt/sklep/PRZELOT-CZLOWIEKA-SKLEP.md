# PRZELOT CZŁOWIEKA — SKLEP (K7, trzeci przebieg INNĄ METODĄ: klikanie, nie czytanie)

Kto: właściciel (Dzidek) — nie autor. Kiedy: po rundach A/B i naprawach, po moim „bezpiecznie". Gdzie: tunel (nowy)
do stosu warsztatowego. Czas: ~40 min. Zasada: odhaczasz TYLKO to, co zobaczyłeś na ekranie. Coś dziwnego = 😖 + jedno
zdanie, co się stało (nie diagnozuj). LICZNIK na dole przelicza WYŁĄCZNIE `bash narzedzia-qa/mapa-licznik.sh audyt/sklep/PRZELOT-CZLOWIEKA-SKLEP.md --przelot` (po każdej sesji odhaczania); `k7-koniec.sh` tylko CZYTA (≥ 27 punktów, 0 ⬜, 0 😖, LICZNIK zgodny = komplet). Statusy: ⬜ / ✅ / 😖 / 🐞.

## A. KUPUJĄCY (bez logowania)
- ⬜ A1 · wejście na `/sklep/` — lista ma 2 produkty, ceny, przycisk do produktu; baner „tryb testowy" widoczny
- ⬜ A2 · strona ChatBota: wiesz po 20 s, co robi, jak działa, ile czasu oszczędza, ile kosztuje
- ⬜ A3 · strona Segregatora: jw.
- ⬜ A4 · galeria „Co jest w środku": kafle otwierają pełny ekran, Esc zamyka, strona się nie przewija pod spodem
- ⬜ A5 · nagranie w galerii gra (albo pokazuje plakat i przycisk) — bez czarnego kwadratu
- ⬜ A6 · „Kup teraz" bez zaznaczonej zgody = nie przechodzi, z komunikatem po ludzku
- ⬜ A7 · zgoda + „Kup teraz" → strona Stripe; karta 4242… → powrót na „Dziękujemy"
- ⬜ A8 · „Dziękujemy" sama zmienia status aż do „Mail wysłany" (bez odświeżania ręką)
- ⬜ A9 · mail w łapaczu (tunel do :8025 albo zrzut ode mnie): kwota, link, potwierdzenie zgody z datą, instrukcja, gwarancja, link do opinii, „odpisz na tego maila"
- ⬜ A10 · link pobrania → pobiera się ZIP o sensownej nazwie; drugi klik też działa
- ⬜ A11 · link do opinii → formularz; bez zgody na publikację imienia nie przechodzi; z — dziękuje
- ⬜ A12 · telefon (albo wąskie okno): lista i strona produktu czytelne, pigułka zakupu nie zasłania treści

## B. WŁAŚCICIEL (zalogowany jako administrator)
- ⬜ B1 · menu „Sklep Agentów": Pulpit / Produkty / Opinie / Dziennik / Ustawienia — wszystko otwiera się bez błędu
- ⬜ B2 · Pulpit: Twój zakup z A7 jest na liście (czas, kwota, produkt), wskaźniki mają sens, CSV się pobiera i otwiera
- ⬜ B3 · Zamówienie (z listy): szczegóły, treść wysłanego maila, „Wyślij ponownie" → nowy mail w łapaczu; STARY link z A10 już nie działa
- ⬜ B4 · Zamówienie: „Anonimizuj" → e-mail znika z widoku i z CSV; zamówienie zostaje w liczbach
- ⬜ B5 · Opinie: Twoja opinia z A11 czeka; „Przyjmij" → widać ją na stronie produktu; „Odrzuć" → znika; da się cofnąć
- ⬜ B6 · Produkty: edycja nazwy/ceny; wyłączenie sprzedaży; próba włączenia produktu BEZ pliku = odmowa po ludzku
- ⬜ B7 · Produkty: wgranie pliku, który nie jest ZIP-em = odmowa; ZIP = potwierdzenie
- ⬜ B8 · Ustawienia: zapis bez zmian nic nie psuje; pusty klucz Stripe → panel MÓWI, że sklep nie sprzeda (nie milczy)
- ⬜ B9 · Dziennik: Twoje logowanie jest; wyloguj się i wpisz ZŁE hasło 3× — wpisy są, a czwarta próba wyraźnie zwleka; potem dobre hasło wchodzi
- ⬜ B10 · Pulpit → „dociągnij zaległe": kończy się komunikatem, nie białym ekranem
- ⬜ B11 · Wtyczki → Sklep Agentów → Wyłącz → Włącz: sklep dalej działa, dane są
- ⬜ B12 · instrukcja `INSTRUKCJA-INSTALACJI.md` Z ZEGAREM: krok po kroku jak kupujący/właściciel — coś niejasne = 😖 z numerem kroku

## C. KLAWIATURA (bez myszy)
- ⬜ C1 · Tab po liście sklepu: widać, gdzie jesteś (obrys), Enter otwiera produkt
- ⬜ C2 · na stronie produktu Tab dochodzi do „Kup teraz" i do galerii; w otwartym pełnym ekranie Tab NIE ucieka pod spód
- ⬜ C3 · formularz opinii: Tab po polach, Enter wysyła, błąd czytelny bez koloru

## LICZNIK
CELE: 27 · ✅ 0 · 😖 0 · 🐞 0 · ⬜ 27
