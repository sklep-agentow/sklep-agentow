# PLAN ETAPU 1 (PLANOWANIE) — sklep-agentow wg PROCES-PROJEKTU.md

Stan na 17.08 ~00:15. Legenda: ✅ zrobione · ⏳ teraz · 🔜 czeka.

## KROK 0 — ROZRUCH WARSZTATU W REPO ⏳
`rozruch-projektu.sh` → nowe repo na koncie klienckim (klon strony kolegi TYLKO-ODCZYT → nasza kopia robocza), CI, manifest, maszyny QA, DZIEN-1. Ich repo i żywej strony NIE dotykamy.

## GODZINA 0 — SKREŚLONA (decyzja właściciela 17.08 ~00:20)
**ZERO pytań do kolegi — to część egzaminu.** Wszystkie otwarte sprawy rozstrzygamy SAMI.
Zamiennik: **REJESTR DECYZJI WŁASNYCH** — każda niejasność dostaje: naszą decyzję + uzasadnienie (źródło: notatka / wzorce rynku / zdrowy rozsądek nazwany po imieniu) + koszt zmiany, gdyby kolega chciał inaczej. Decyzje „drogo cofnąć" klepie Dzidek.
Rozstrzygnięcia od ręki:
- mail po zakupie = potwierdzenie + instrukcje + wygasający link do plików (wzorzec zipsell + notatka „potwierdzenie, instrukcje"),
- opisy i ceny 2 agentów = MY piszemy (Dzidek: „coś wymyślimy"; wzorce opisów z godofprompt/agenciai),
- panel admina = podgląd sprzedaży + PEŁNA edycja produktów (notatka wprost: „dodawanie, usuwanie, modyfikowanie"),
- rubryka GOTOWE = ze scenariuszy KUPUJĄCEGO i notatki kolegi (nie z architektury).

## BRAMKA A — „WIEMY, CO KLIENT CHCE?" (praca własna, równolegle z czekaniem)
- ✅ surowe zdania klienta zapisane (ZROZUMIENIE.md), każda reguła ma źródło (notatka kolegi / odpowiedzi właściciela),
- ✅ MUST NOT spisane (bez kont kupujących, bez kursów, pliki nie w repo, nie dotykamy reszty strony),
- ✅ wzorce dziedziny zebrane (ZWIAD-WZORCE.md: zipsell, oficjalny przykład Stripe, godofprompt, agenciai),
- ⏳ mapa AS-IS→TO-BE przepływu zakupu (kto → co widzi → co dostaje; z odnogami: mail nie doszedł, płatność urwana),
- ⏳ rejestr założeń niepotwierdzonych (już mamy kandydatów: BLIK w trybie testowym, komercja na darmowym Cloudflare, hydracja menu),
- ZAMIANA: inwentaryzacja SQL istniejącej bazy ODPADA (baza nie istnieje — sklep od zera); zamiast tego: dane startowe produktów od kolegi/właściciela.

## BRAMKA B — „KLIENT POTWIERDZIŁ SEMANTYKĘ?" 🔜
Arkusz wrócił z zaznaczonymi przynętami · pytania o wartości na KONKRETACH (np. „zakup przeszedł, mail nie wyszedł — co widzi kupujący i co widzi admin?") · lista funkcji z etykietą rdzeń/potrzebne/miłe uzgodniona.

## BRAMKA C — „ŚRODOWISKO STOI I KOŚĆ CHODZI?" ⏳ (zaraz po rozruchu)
- środowisko-kopia: nasz klon strony + zaplecze TESTOWE (tryb testowy Stripe),
- ⭐ CHODZĄCA KOŚĆ: JEDEN zakup od kliknięcia „kup" po maila z linkiem do pliku — najcieńszy przekrój całości; na nim rośnie reszta,
- wroga sesja (nasze „cmentarzysko", wersja na statyczną stronę): adblock blokuje skrypt płatności · kupujący płaci i zamyka kartę przed powrotem na stronę · mail ląduje w spamie · podwójny klik „kup" · webhook przychodzi dwa razy.

## BRAMKA D — „PROJEKT PO KOŚCI + KRYTYKA DWUWARSTWOWA" 🔜
Projekt techniczny pisany PO kości (opisuje, co REALNIE działa) + kontrakty (witryna↔zaplecze↔Stripe↔mail) + wagi per odpowiedzialność („cokolwiek dotyka pieniędzy/wydaje plik/wysyła mail" = najwyższa) + **rubryka GOTOWE ze scenariuszy EGZAMINATORA, nie z architektury** + krytyka A (reguły bez źródła w materiałach, sprzeczności, co zostaje przy padnięciu w połowie) i B (gdzie kod może spełnić dokument, a złamać skutek).

## BRAMKA E — „WOLNO BUDOWAĆ?" (skrypt, binarnie) 🔜
Zero konfliktów źródeł · założenia wysokiego ryzyka domknięte albo jawnie przyjęte z właścicielem · każde wymaganie ma źródło i scenariusz · przynęty zaznaczone · kość przeszła od końca do końca. Dopiero wtedy budowa właściwa (Etap 2).

## DECYZJE JUŻ PODJĘTE (kontekst dla bramek)
Cel nr 1 = ZDAĆ EGZAMIN · wszystko w trybie testowym płatności · architektura: statyczna witryna + darmowe zaplecze (Cloudflare) + Stripe Checkout + mail z wygasającym linkiem · własnego kodu minimum, wzory z oficjalnych przykładów Stripe · paczka konfigurowalna pod 4 strony.
