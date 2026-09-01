# F5 — Regulamin sklepu (osobny dokument)

**Audytor:** sesja audytu białych plam, 2026-09-01 · **Gałąź:** `audyt/biale-plamy-01-09`
**`commit_head`:** `7a8fb5b` (po wciągnięciu `origin/main` = `a1e1a68`, PR #243)
**Produkt:** `sklep-agentow` 0.9.28 · **Stanowisko:** `strona_wp_wordpress` → `localhost:8890`
**Zakres z mapy terenu:** `F5 | Regulamin sklepu (osobny dokument) | ⬜ NIETKNIĘTE — sklep sprzedaje konsumentom`

---

## Wobec czego oceniam (kryteria + skąd) — ZAPISANE PRZED OGLĄDANIEM

⛔ Nie oceniam z pamięci. Wszystkie przepisy wypisane **słowo w słowo** z oficjalnych źródeł
w `audyt/plamy/F5-teksty/zrodla-prawne.md`, razem z adresem i sposobem odczytu.
Ustawa: **tekst jednolity Dz.U. 2024 poz. 1796 ze zm. Dz.U. 2025 poz. 1172**, stan na 2026-03-10,
pobrany z ISAP i wyciągnięty `pdftotext -enc UTF-8 -layout`. Dyrektywa: EUR-Lex CELEX `32011L0083`,
polska wersja językowa.

| # | Kryterium | Źródło |
|---|---|---|
| **P1** | Potwierdzenie zawarcia umowy na trwałym nośniku musi zawierać **„informację o udzielonej przez konsumenta zgodzie na dostarczenie treści cyfrowych w okolicznościach powodujących utratę prawa odstąpienia od umowy"** | ustawa, **art. 21 ust. 1 pkt 2** (= dyrektywa **art. 8 ust. 7 lit. b**) |
| **P2** | To samo potwierdzenie musi zawierać **„informacje, o których mowa w art. 12 ust. 1, chyba że przedsiębiorca dostarczył konsumentowi te informacje na trwałym nośniku przed zawarciem umowy"** | ustawa, **art. 21 ust. 1 pkt 1** |
| **P3** | Utrata prawa odstąpienia przy treściach cyfrowych ma **TRZY** warunki, trzeci to: **„a przedsiębiorca przekazał konsumentowi potwierdzenie, o którym mowa w art. 15 ust. 1 i 2 albo art. 21 ust. 1"** | ustawa, **art. 38 ust. 1 pkt 13** (= dyrektywa art. 16 lit. m + art. 14 ust. 4 lit. b ppkt iii) |
| **P4** | Informacje z art. 12 ust. 1 (21 punktów) mają dotrzeć **„najpóźniej w chwili wyrażenia przez konsumenta woli związania się umową"** — czyli PRZED kliknięciem „Kup teraz", nie w mailu po zakupie | ustawa, **art. 12 ust. 1**, zdanie wstępne |
| **P5** | W szczególności: dane identyfikujące sprzedawcy (pkt 2), adres/e-mail/telefon (pkt 3), adres reklamacji (pkt 4), cena **wraz z podatkami** (pkt 5), **procedura rozpatrywania reklamacji** (pkt 8), brak prawa odstąpienia (pkt 12), **odpowiedzialność za zgodność świadczenia z umową** (pkt 13), **treść gwarancji i sposób jej realizacji** (pkt 14), **pozasądowe sposoby rozpatrywania reklamacji** (pkt 21) | ustawa, art. 12 ust. 1 |
| **P6** | Ochrona konsumencka dla osoby fizycznej kupującej na firmę poza swoją specjalnością stoi na **art. 7aa**; **art. 38a jest UCHYLONY** | ustawa, art. 7aa oraz wiersz „Art. 38a. (uchylony)" |
| **W1** | Kryterium projektu: „Regulamin i Polityka jako **szablony bez cudzych danych**" — spełnione wg `rubryka-GOTOWE.md` #11 (✅) | `rubryka-GOTOWE.md`, kryterium 11 |
| **W2** | Klasa 3 ściągi: **dokumentacja/komentarz KŁAMIE względem kodu** (wracała ×4) | `audyt/SCIAGA-EGZAMINATORA.md` |
| **W3** | Klasa 54: **treść tworzona przy aktywacji, naprawiona w kodzie, NIE DOCIERA do działających instalacji**; klasa 65: **kompensacja-bliźniak przywraca PODZBIÓR** | `audyt/SCIAGA-EGZAMINATORA.md` |
| **W4** | Zasada audytu: **nie wolno orzekać `BRAK` o rzeczach żyjących w PROCEDURZE**, a nie w kodzie (instrukcja wdrożeniowa) | skill `jak-audytowac`, §📎 wpadka 3 |

⛔ **Granica mojej roli:** jestem audytorem oprogramowania, nie prawnikiem. Orzekam wyłącznie
**obecność albo nieobecność** treści wymaganej wprost przez zacytowany przepis — nie oceniam,
czy dane sformułowanie jest prawnie wystarczające. Tam, gdzie potrzebna jest kwalifikacja prawna,
piszę to wprost.

---

## Co i czym zmierzyłem

Wszystko pod `flock /root/praca/zlecenia/wymiana/stos.lock`. Odczyty ze stosu — **zero zapisów**.

| # | Pomiar | Komenda / plik | Wynik |
|---|---|---|---|
| M1 | status obu stron prawnych | `wp post list --post_status=any --include=118,119,120,121` | 118 `sklep` **publish** · 119 `dziekujemy-za-zakup` **publish** · 120 „Regulamin sklepu (SZABLON)" **draft, slug pusty** · 121 „Polityka prywatności sklepu (SZABLON)" **draft, slug pusty** |
| M2 | treść obu szkiców | `wp post get 120/121 --field=post_content` | `F5-teksty/regulamin-szkic-id120.txt` (365 B), `…/polityka-szkic-id121.txt` (353 B) |
| M3 | kto w ogóle używa tych stron | `grep -rn sklepag_strona_* wtyczki/sklep-agentow/` | **regulamin 2 · polityka 2** (tworzenie + kasowanie) — przy **sklep 18 · dziekujemy 6** w 7 plikach (próba kontrolna) |
| M4 | linki prawne na ścieżce kupującego | `curl` → `F5-teksty/{sklep,produkt-chatbot,dziekujemy}.html` + `grep` po `<a…>` | jedyne linki: `/polityka-prywatnosci` i `/polityka-cookies` — **strony MOTYWU gospodarza, nie sklepu**. Link do regulaminu: **0** |
| M5 | rdzeń „regulamin" w całym HTML (nie tylko w linkach) | `grep -oic` | sklep **0** · produkt **0** · dziękujemy **0**. Próba kontrolna: `sklepag` = 155/307/17, `testow` = 7/9/4 |
| M6 | czy panel przypomina właścicielowi | `grep -i "regulamin\|polityk"` w `admin-dashboard.php`, `admin-ustawienia.php`, `admin.php` | **0 wzmianek**. Próba kontrolna: `grep -c "notice notice-"` w dashboardzie = **6** (mechanizm istnieje i grep go widzi) |
| M7 | czy to żyje w PROCEDURZE (instrukcja) | `grep -n -i` w `INSTRUKCJA.md` | **TAK** — `:210-213`, jedno zdanie |
| M8 | mail po zakupie — prawdziwa treść z bazy | `wp eval` po `mail_tresc` zam. `9901622` | `F5-teksty/mail-po-zakupie-9901622.txt` (tokeny wycięte przeze mnie) |
| M9 | terminy z art. 12 na stronie przed zakupem | `grep -oic` po tekście strony | `brutto`/`VAT`/`podatek` = **0/0/0** · `ODR`/`pozasąd`/`polubown` = **0/0/0** · `rękojm` = **0** · `zgodnoś` = **0** · `regulamin` = **0** |
| M10 | co robi kod przy ZWROCIE | `grep -c status wtyczki/sklep-agentow/includes/pobieranie.php` | **0** — pobieranie nie patrzy na status. Próba kontrolna: `token_pobrania`=2, `pobrania_uzyte`=1, `link_wygasa`=1 |
| M11 | historia szablonu polityki | `git log -S` po `aktywacja.php` | opinie → **0.9.8** (`65a88a5`) · IP przy pobraniu → **0.9.23** (`9049704`) · dane do faktury → **0.9.25** (`2992247`) |
| M13 | czy szkic jest naprawdę niedostępny dla gościa | `curl -o /dev/null -w "%{http_code}" "…/?page_id=N"` | 118 (Sklep, opublikowany) → **301** · **120 → 404** · **121 → 404**. Próba kontrolna w tej samej komendzie: strona opublikowana odpowiada przekierowaniem, szkice nie istnieją dla niezalogowanego |
| M12 | jedno źródło treści zgody | `grep -rn SKLEPAG_ZGODA_TRESC` | `zakup.php:11` (definicja) → `front-produkt.php:610` (render) → `zakup.php:859` (zapis do bazy) → `zakup.php:1195` (mail). **Jedna definicja, cztery użycia** |

---

## Kontrola dodatnia

1. **Czy w ogóle znajduję linki w tych plikach** — ten sam wzorzec `<a…>` po `regulamin|polityk`
   **znalazł** `/polityka-prywatnosci` i `/polityka-cookies` na wszystkich trzech stronach.
   Przyrząd widzi linki; zero przy „regulamin" jest pomiarem, nie ślepotą.
2. **Czy `grep` po rdzeniach polskich działa** — przy zerze dla `regulamin`/`VAT`/`ODR` ten sam
   przebieg dał `sklepag` = 155/307/17 i `gwaranc` = 7 na stronie produktu. Szukałem **rdzenia**,
   nie całego słowa (`odst(ą|a)p`, `pozasąd`/`pozasad`, `rękojm`/`rekojm` — obie pisownie).
3. **Czy „nikt tego nie woła" to pomiar** — opcje `sklepag_strona_regulamin`/`_polityka` mają
   **2 trafienia**, a używane `sklepag_strona_sklep`/`_dziekujemy` **18 i 6**, w siedmiu plikach.
   Wzorzec ten sam, wynik różny → to nieużywanie, nie zła komenda.
4. **Czy panel w ogóle umie ostrzegać** — `grep -c "notice notice-"` w dashboardzie = **6**
   (m.in. ostrzeżenie o pustych danych sprzedawcy). Mechanizm jest, regulaminu w nim nie ma.
5. **Czy szkic regulaminu daje się przeszukać** — `gwaranc|zwrot|14 dni` = **0**, przy
   `sprzedawca|reklamac` = **2** w tym samym pliku.
6. ⛔ **Sprawdziłem PROCEDURĘ, zanim orzekłem BRAK** (zasada W4) — i **znalazłem ją**:
   `INSTRUKCJA.md:211` mówi „Uzupełnij je swoimi danymi sprzedawcy i opublikuj, zanim zaczniesz
   sprzedawać naprawdę". Dlatego **nie zgłaszam** „brak procedury publikacji" — zgłaszam co innego,
   niżej.

---

## WYNIK: **ZNALEZIONE** — 5 znalezisk (2 duże, 2 średnie, 1 drobne) + 3 wyniki CZYSTE

---

## ① ODPOWIEDŹ NA PYTANIE PIERWSZE — status stron i podlinkowanie

| Strona | ID | Status | Slug | Link w stopce | Link w checkoucie / na stronie produktu | Link w mailu |
|---|---|---|---|---|---|---|
| Sklep | 118 | **publish** | `sklep` | — | — | — |
| Dziękujemy za zakup | 119 | **publish** | `dziekujemy-za-zakup` | — | — | — |
| **Regulamin sklepu (SZABLON)** | **120** | **⛔ draft** | **pusty** | **NIE** | **NIE** | **NIE** |
| **Polityka prywatności sklepu (SZABLON)** | **121** | **⛔ draft** | **pusty** | **NIE** | **NIE** | **NIE** |

Stopka, którą kupujący widzi na wszystkich trzech stronach sklepu, niesie **wyłącznie** dwa linki
motywu gospodarza — `/polityka-prywatnosci` (id 11) i `/polityka-cookies` (id 12). To **strony
witryny**, nie strony sklepu; nasza wtyczka ich nie tworzy i nie ma nad nimi kontroli.
Słowo „regulamin" nie pada na ścieżce kupującego **ani razu** (M5).

---

## ② ODPOWIEDŹ NA PYTANIE DRUGIE — pary cytatów, słowo w słowo

⛔ Poniżej **cytaty**, nie wnioski. Rozstrzygnięcie zostawiam czytelnikowi.

### Para A — gwarancja

| Gdzie | Cytat |
|---|---|
| Strona produktu, pasek pod ceną (`front-produkt.php:818`) | „**✔ 14 dni na zwrot — bez pytań**" |
| Strona produktu, przy formularzu zakupu (`front-produkt.php:967`) | „**✔ 14 dni gwarancji satysfakcji — zwrot bez pytań**" |
| Strona produktu, sekcja GWARANCJA (`front-produkt.php:345`, render `:989`) | „**14 dni gwarancji satysfakcji: jeśli bot nie spełni Twoich oczekiwań, napisz do nas — zwracamy pieniądze bez pytań. Zwrot oznacza wygaśnięcie prawa do aktualizacji i wsparcia dla kupionej kopii.**" |
| Strona produktu, podpis końcowy (`front-produkt.php:1007`) | „**[ ChatBot LIVE · 14 dni na zwrot ]**" |
| **Regulamin (szkic id 120), cały tekst** | *(brak — rdzeń `gwaranc`, `zwrot`, `14 dni` = **0 trafień**; próba kontrolna `sprzedawca\|reklamac` = 2)* |
| Mail po zakupie (`mail-po-zakupie-9901622.txt`) | „**GWARANCJA:** 14 dni gwarancji satysfakcji […] **Aby z niej skorzystać, odpisz na tego maila.**" |

### Para B — prawo odstąpienia, ten sam ekran

| Gdzie | Cytat |
|---|---|
| Strona produktu, ~20 linii nad przyciskiem (`:818`) | „✔ **14 dni na zwrot — bez pytań**" |
| Strona produktu, zgoda przy przycisku (`zakup.php:11` → `front-produkt.php:610`) | „**Wyrażam zgodę na natychmiastowe dostarczenie treści cyfrowej i przyjmuję do wiadomości utratę prawa odstąpienia od umowy (art. 38 ustawy o prawach konsumenta).**" |
| Regulamin (szkic id 120) | „Zgoda na natychmiastowe dostarczenie: kupujący wyraża ją przy zakupie i przyjmuje utratę prawa odstąpienia (**art. 38 ust. o prawach konsumenta**)." |

⚠️ Prawnie **te dwa zdania mogą współistnieć** — gwarancja satysfakcji jest zobowiązaniem
umownym sprzedawcy, ustawowe prawo odstąpienia to co innego. **Nie zgłaszam sprzeczności prawnej.**
Zgłaszam to, co da się zmierzyć: **żaden dokument tego nie godzi**, bo dokument, który miałby
to zrobić, jest szkicem bez ani jednego słowa o gwarancji. Kupujący czyta „zwrot bez pytań"
i „tracisz prawo odstąpienia" na jednym ekranie i nie ma gdzie sprawdzić, które zdanie go dotyczy.

### Para C — co dokładnie kupujący dostaje

| Gdzie | Cytat |
|---|---|
| Strona produktu, ramka „PO ZAKUPIE" (`front-produkt.php:979`) | „mail z linkiem pobrania zaraz po płatności — **link ważny 72 h, do 10 pobrań**" |
| Regulamin (szkic id 120) | „Produkty: treści cyfrowe (wtyczki WordPressa) **dostarczane natychmiast po opłaceniu**." *(o terminie ważności linku i limicie pobrań — ani słowa)* |

### Para D — ta sama podstawa prawna, dwa zapisy

| Gdzie | Cytat |
|---|---|
| Zgoda przy zakupie (`zakup.php:11`) | „(**art. 38 ustawy o prawach konsumenta**)" |
| Regulamin (szkic id 120, `aktywacja.php:191`) | „(**art. 38 ust. o prawach konsumenta**)" ← urwane „ustawy" |

### Para E — gdzie składać reklamację

| Gdzie | Cytat |
|---|---|
| Strona produktu, przed zakupem | *(brak adresu sprzedawcy — 11 trafień na „kontakt" to **wyłącznie** tekst marketingowy o bocie zbierającym kontakty, np. „Zbieranie kontaktów", „Bot prosi o maila")* |
| Regulamin (szkic id 120) | „Reklamacje: **[MAIL WŁAŚCICIELA]**." ← wypełniacz |
| Mail po zakupie | „Masz pytanie? **Odpisz na tego maila.**" |

---

## ③ ODPOWIEDŹ NA PYTANIE TRZECIE — czy mail niesie potwierdzenie zgody

### ✅ **NIESIE. Kryterium P1 SPEŁNIONE.**

Cytat z prawdziwego maila zapisanego w bazie przy zamówieniu `9901622`
(`F5-teksty/mail-po-zakupie-9901622.txt`, kolumna `zamowienia.mail_tresc`):

```
POTWIERDZENIE TWOJEJ ZGODY:
Wyrażam zgodę na natychmiastowe dostarczenie treści cyfrowej i przyjmuję do wiadomości
utratę prawa odstąpienia od umowy (art. 38 ustawy o prawach konsumenta).
Zgoda zaznaczona przy zakupie 01.09.2026 02:28.
```

Wobec **art. 21 ust. 1 pkt 2** („informację o udzielonej przez konsumenta zgodzie na dostarczenie
treści cyfrowych w okolicznościach powodujących utratę prawa odstąpienia") i **art. 8 ust. 7 lit. b**
dyrektywy: treść zgody **i** znacznik czasu są w potwierdzeniu. Mechanizm jest zbudowany porządnie —
zgoda ma **jedno źródło** (`zakup.php:11`), jest zapisywana do bazy w chwili zakupu (`:859`,
kolumna `zgoda_tresc` + `zgoda_czas`) i przepisywana do maila **z bazy**, nie z bieżącej stałej
(`:1193-1195`). Zmiana napisu w kodzie nie sfałszuje starych potwierdzeń. **To jest zrobione dobrze
i zapisuję to jako wynik, nie jako brak zastrzeżeń.**

### ⛔ Ale to jest **drugi z trzech** warunków art. 38 ust. 1 pkt 13

Przepis wymaga łącznie: (a) wyraźnej i uprzedniej zgody, (b) poinformowania i przyjęcia
do wiadomości, **(c) przekazania potwierdzenia „o którym mowa w art. 15 ust. 1 i 2 albo art. 21 ust. 1"**.
A art. 21 ust. 1 ma **dwa** punkty — pkt 2 (zgoda, ✅) **oraz pkt 1** (informacje z art. 12 ust. 1,
o ile nie przekazano ich wcześniej na trwałym nośniku). Strona internetowa trwałym nośnikiem
nie jest, więc ciężar pkt 1 spada na maila. Zmierzone braki w mailu i na stronie — patrz `F5-F3`.

---

## Znaleziska

### `F5-F1` — **DUZE** · Regulamin sklepu dla kupującego NIE ISTNIEJE — i nic nie prowadzi do zmiany tego stanu poza jednym zdaniem w instrukcji

**Objaw:** sklep sprzedaje konsumentom, a na całej ścieżce zakupu słowo „regulamin" nie pada
ani razu (M5: 0/0/0 przy próbie kontrolnej 155/307/17). Dokument istnieje jako **szkic bez sluga**
(id 120, `post_status=draft`). Zmierzone, nie założone: `?page_id=120` i `?page_id=121` oddają
gościowi **HTTP 404**, przy `?page_id=118` (opublikowany Sklep) → **301** w tej samej komendzie (M13).

**Plik i linia:** `wtyczki/sklep-agentow/includes/aktywacja.php:191` — strona zakładana ze statusem
`'draft'`; `uninstall.php:36` — kasowana. **To jedyne dwa miejsca w całej wtyczce, które o niej wiedzą.**

**Dowód (M3), z próbą kontrolną w tej samej komendzie:**
```
sklepag_strona_sklep           18
sklepag_strona_dziekujemy       6
sklepag_strona_regulamin        2      ← tworzenie + kasowanie
sklepag_strona_polityka         2      ← tworzenie + kasowanie
```
**Dowód (M6):** dashboard ma **6** bloków `notice notice-` (w tym „Mail po zakupie idzie bez danych
sprzedawcy", `admin-dashboard.php:387`) — i **ani jednego** o regulaminie.

⛔ **Czego to znalezisko NIE mówi:** że nie ma procedury. Sprawdziłem i **jest**
(`INSTRUKCJA.md:211`: „Uzupełnij je swoimi danymi sprzedawcy i opublikuj, zanim zaczniesz sprzedawać
naprawdę"). Rzecz w tym, **gdzie** to zdanie stoi i **co po nim zostaje**:
1. leży w **rozdziale 6 o RODO i anonimizacji**, między akapitem o nieudanym usuwaniu danych
   a rozdziałem 7 o dzienniku — nie w liście kroków instalacji;
2. **nawet wykonane w całości nie daje efektu**: właściciel może wypełnić i opublikować obie strony,
   a na stronie produktu i w checkoucie **dalej nie będzie do nich linku**, bo nic ich nie renderuje
   (M3). Opublikowany regulamin, do którego nie prowadzi żaden odsyłacz, jest dla kupującego
   tym samym co szkic.

**Skutek DLA KLIENTA:** właściciel sklepu sprzedaje treści cyfrowe konsumentom bez regulaminu
dostępnego dla kupującego. Kupujący nie ma gdzie sprawdzić warunków gwarancji, trybu reklamacji
ani tego, kto jest sprzedawcą. Dla właściciela to ryzyko wobec UOKiK i wobec kupującego;
dla nas — produkt „gotowy do sprzedaży", który do sprzedaży konsumenckiej nie jest przygotowany.
**Awaria cicha:** nic nie mruga, sklep działa.

**RODZAJ SKUTKU:** `zaobserwowany` (statusy z bazy + zerowe trafienia z próbą kontrolną).
**Waga: DUŻA.**

**Propozycja naprawy:** (a) renderować w stopce sekcji zakupu linki do stron z opcji
`sklepag_strona_regulamin`/`_polityka` **tylko gdy są opublikowane**, a gdy są szkicem —
pokazać właścicielowi (nie kupującemu) ostrzeżenie w dashboardzie, tym samym mechanizmem
`notice`, którym już ostrzega o pustych danych sprzedawcy; (b) przenieść zdanie z `INSTRUKCJA.md:211`
do listy kroków instalacji. ⭐ Strażnik: test, który po opublikowaniu obu stron wymaga linku
do nich w HTML strony produktu — i **pada**, gdy linku nie ma.

---

### `F5-F2` — **DUZE** · Szablon regulaminu nie zawiera treści, których wymaga art. 12 ust. 1 — po wypełnieniu wypełniaczy nadal nie jest regulaminem

**Objaw:** dokument nazwany „Regulamin sklepu" ma **pięć linii i trzy wypełniacze**. Cały tekst
(`F5-teksty/regulamin-szkic-id120.txt`, 365 bajtów):

```
SZABLON — wypełnij danymi sprzedawcy przed publikacją.

Sprzedawca: [NAZWA, ADRES, KONTAKT]
Produkty: treści cyfrowe (wtyczki WordPressa) dostarczane natychmiast po opłaceniu.
Zgoda na natychmiastowe dostarczenie: kupujący wyraża ją przy zakupie i przyjmuje utratę
prawa odstąpienia (art. 38 ust. o prawach konsumenta).
Reklamacje: [MAIL WŁAŚCICIELA].
```

**Plik i linia:** `wtyczki/sklep-agentow/includes/aktywacja.php:191`.

**Zestawienie z kryterium P5** (ustawa, art. 12 ust. 1 — pełne brzmienie w `zrodla-prawne.md`):

| pkt art. 12 ust. 1 | jest w szablonie? |
|---|---|
| 2 — dane identyfikujące, firma, organ rejestrowy, numer rejestrowy | wypełniacz `[NAZWA, ADRES, KONTAKT]` — bez organu i numeru |
| 4 — adres do reklamacji | wypełniacz `[MAIL WŁAŚCICIELA]` |
| 5 — łączna cena **wraz z podatkami** | **NIE** |
| 7 — sposób i termin zapłaty | **NIE** |
| 8 — sposób i termin spełnienia świadczenia oraz **procedura rozpatrywania reklamacji** | częściowo („natychmiast po opłaceniu"); procedury **NIE** |
| 12 — brak prawa odstąpienia | **TAK** (jedyny punkt opisany w pełni) |
| 13 — **odpowiedzialność za zgodność świadczenia z umową** | **NIE** |
| 14 — **treść gwarancji i sposób jej realizacji** | **NIE** — rdzeń `gwaranc`/`zwrot`/`14 dni` = **0 trafień**, przy próbie kontrolnej `sprzedawca\|reklamac` = 2 |
| 19/20 — funkcjonalność, kompatybilność, interoperacyjność treści cyfrowej | **NIE** |
| 21 — **pozasądowe sposoby rozpatrywania reklamacji** | **NIE** |

⛔ **Najostrzejszy punkt to 14.** Sklep obiecuje gwarancję **cztery razy na jednej stronie,
w trzech różnych brzmieniach** (Para A wyżej) i piąty raz w mailu — a dokument, który miałby
określić jej treść i sposób realizacji, **nie wspomina o niej ani słowem**. Jedyna instrukcja,
jak z niej skorzystać („odpisz na tego maila"), dociera **po** zakupie.

**Skutek DLA KLIENTA:** właściciel, który zrobi dokładnie to, co mówi instrukcja — wypełni trzy
wypełniacze i opublikuje — będzie przekonany, że ma regulamin. Nie będzie go miał. To gorsze
niż brak szablonu, bo **szablon daje fałszywe poczucie załatwionej sprawy** (nasza własna klasa:
„sierota z twardą deklaracją w nagłówku jest gorsza niż brak narzędzia", ściąga 75).

**RODZAJ SKUTKU:** `wyliczony` (konkretna lista punktów ustawy zestawiona z konkretnym tekstem
o długości 365 bajtów). Kwalifikacja prawna — do potwierdzenia przez prawnika; **obecność
i nieobecność treści zmierzyłem, wystarczalności sformułowań nie oceniam.**
**Waga: DUŻA.**

**Propozycja naprawy:** rozszerzyć szablon o brakujące punkty art. 12 ust. 1 (przede wszystkim
gwarancja z terminem i trybem, procedura reklamacyjna z terminem odpowiedzi, odpowiedzialność
za zgodność świadczenia, pozasądowe sposoby + adres platformy ODR, cena z podatkami) albo —
uczciwiej — **przestać nazywać ten plik „Regulaminem"** i nazwać go „Rusztowanie regulaminu —
uzupełnij u prawnika", z jawną listą tego, czego w nim nie ma. ⛔ Zdanie „Regulamin i Polityka
jako szablony bez cudzych danych" z `rubryka-GOTOWE.md` #11 (✅ SPEŁNIONE) jest prawdziwe co do
litery i mylące co do wagi: kryterium pytało, czy nie ma tam **cudzych danych** — nie pytało,
czy to jest regulamin.

---

### `F5-F3` — **SRED** · Obowiązek informacyjny sprzed zakupu nie ma gdzie zostać spełniony: dane sprzedawcy istnieją WYŁĄCZNIE jako stopka maila PO zakupie

**Objaw:** art. 12 ust. 1 każe podać informacje „najpóźniej w chwili wyrażenia przez konsumenta
woli związania się umową" (P4). Na stronie produktu — tej, na której stoi przycisk „Kup teraz" —
nie ma nazwy sprzedawcy, adresu, NIP-u, telefonu, adresu reklamacyjnego, informacji
o odpowiedzialności za zgodność świadczenia ani o pozasądowych sposobach dochodzenia roszczeń.

**Plik i linia:** `wtyczki/sklep-agentow/includes/admin-ustawienia.php:162` — sekcja ustawień
nazywa się wprost **„Dane sprzedawcy (stopka maila po zakupie)"**; renderowane są tylko przez
`zakup.php:1168` (`sklepag_stopka_sprzedawcy_tekst`) do maila. **Żaden plik frontu ich nie drukuje.**

**Dowód (M9), tekst strony produktu, `grep -oic` po rdzeniach:**
```
brutto=0  VAT=0  podatek=0        ← art. 12 ust. 1 pkt 5 („cena wraz z podatkami")
ODR=0  pozasąd=0  polubown=0      ← pkt 21
rękojm=0  zgodnoś=0               ← pkt 13
regulamin=0                        ← gdzie miałaby stać reszta
```
Próba kontrolna na tym samym pliku: `sklepag`=307, `gwaranc`=7, `kontakt`=11 — przy czym
**wszystkie 11 trafień „kontakt" to tekst marketingowy o bocie** („Zbieranie kontaktów",
„Bot prosi o maila i zapisuje zgłoszenie"), ani jedno nie jest kontaktem do sprzedawcy.

**Co jest spełnione i nie zgłaszam:** pkt 1 (główne cechy — opisane obszernie), pkt 12
(brak prawa odstąpienia — zgoda przy przycisku), pkt 19/20 częściowo („WordPress 6.0+",
„instalacja jak każdej wtyczki"), pkt 8 w części o terminie („link ważny 72 h, do 10 pobrań").

**Skutek DLA KLIENTA:** kupujący zawiera umowę, nie wiedząc, z kim. Dla właściciela: informacje,
które ustawa każe podać **przed** umową, jego sklep podaje **po** — i tylko część z nich.
Dodatkowo, wobec art. 38 ust. 1 pkt 13 (P3), niepełne potwierdzenie z art. 21 ust. 1 pkt 1
osłabia trzeci warunek wyłączenia prawa odstąpienia — czyli tę samą rzecz, którą checkbox
przy zakupie ma zabezpieczyć.

**RODZAJ SKUTKU:** `wyliczony`. **Waga: ŚREDNIA** (realna dziura, ale częściowo do zamknięcia
treścią, którą właściciel i tak musi wpisać).

**Propozycja naprawy:** wydrukować dane sprzedawcy z Ustawień w stopce sekcji zakupu na stronie
produktu (te same dane, ten sam `sklepag_sprzedawca()`, inne miejsce), a brakujące punkty
art. 12 dopiąć przez link do regulaminu z `F5-F1`.

---

### `F5-F4` — **SRED** · Kompensacja dla starych instalacji przywraca PODZBIÓR: polityka prywatności na działającej instalacji nie wie o danych do faktury ani o opiniach

**Objaw:** szablon Polityki rósł trzy razy, a instrukcja każe dopisać ręcznie **tylko jedną**
z trzech rzeczy. Na tym stanowisku strona 121 zawiera wersję sprzed wszystkich trzech zmian.

**Dowód — tekst w bazie (`wp post get 121`) kontra tekst w kodzie (`aktywacja.php:192`):**

| Fragment w kodzie dziś | W bazie na stanowisku | Dodany w |
|---|---|---|
| „dziennik zdarzeń (90 dni, **w tym adres IP przy każdym pobraniu pliku**)" | „dziennik zdarzeń (90 dni)" | **0.9.23**, `9049704` |
| „**dobrowolnie podane dane do faktury** (nazwa firmy, NIP, adres — cel: wystawienie faktury, podstawa: obowiązek prawny z ustawy o VAT)" | **brak** | **0.9.25**, `2992247` |
| „**przy dobrowolnej opinii — imię i treść opinii**, publikowane na stronie produktu wyłącznie za wyrażoną w formularzu zgodą" | **brak** | **0.9.8**, `65a88a5` |

**A instrukcja kompensuje tylko pierwszy wiersz** (`INSTRUKCJA.md:212-214`):
> „Szkice powstają tylko raz, przy pierwszej instalacji — jeśli opublikowałeś Politykę z wersji
> wcześniejszej niż 0.9.23, dopisz do niej sam: „dziennik zdarzeń (90 dni, w tym adres IP przy
> każdym pobraniu pliku)"."

Zdania o danych do faktury (0.9.25 — **późniejszej** niż próg 0.9.23 podany w instrukcji) ani
o opiniach nie ma w żadnej instrukcji dopisania. Próba kontrolna: rdzeń `faktur` występuje
w `INSTRUKCJA.md` **11 razy** — czyli temat jest opisany, tylko nie w tym miejscu.

**To jest nasza klasa 65 ściągi** („kompensacja-bliźniak przywraca PODZBIÓR") nałożona na klasę 54
(„treść tworzona przy aktywacji, naprawiona w kodzie, nie dociera do działających instalacji").

**Skutek DLA KLIENTA:** właściciel, który zainstalował sklep w 0.9.23 lub 0.9.24 i zaktualizował
do 0.9.28, ma opublikowaną politykę prywatności, która **nie wymienia** nazwy firmy, NIP-u i adresu
kupującego — danych osobowych, które jego sklep od 0.9.25 zbiera i trzyma. Osoba, której dane
dotyczą, czyta dokument nieprawdziwy.

**RODZAJ SKUTKU:** `zaobserwowany` (różnica tekstu w bazie i w kodzie, na tej instalacji).
**Waga: ŚREDNIA.**

**Propozycja naprawy:** kompensacja ma wymieniać **każdą** zmianę szablonu z jej wersją (albo
lepiej: pokazać właścicielowi w panelu różnicę między jego opublikowaną stroną a bieżącym
szablonem). ⭐ Strażnik: test porównujący listę fragmentów szablonu z listą fragmentów wymienionych
w instrukcji — **pada**, gdy szablon urośnie, a instrukcja nie.

---

### `F5-F5` — **MALE** · Instrukcja dla klienta powołuje się na przepis UCHYLONY

**Objaw:** `wtyczki/sklep-agentow/INSTRUKCJA.md:148`:
> „osoba prowadząca jednoosobową działalność, która kupuje poza swoją specjalnością, ma ochronę
> konsumenta (**art. 38a ustawy o prawach konsumenta**)."

**Dowód ze źródła** (tekst jednolity Dz.U. 2024 poz. 1796, stan 2026-03-10):
```
wiersz 876:   Art. 38a. (uchylony)
wiersz 300:   Art. 7aa. 1. Przepisy dotyczące konsumenta zawarte w rozdziałach 4, 5a i 5b
              stosuje się do osoby fizycznej zawierającej umowę bezpośrednio związaną
              z jej działalnością gospodarczą, gdy z treści tej umowy wynika, że nie ma ona
              dla tej osoby charakteru zawodowego […]
```

**Merytorycznie zdanie instrukcji jest PRAWDZIWE** — taka ochrona istnieje. Fałszywa jest
wyłącznie podstawa: przepis został przeniesiony do art. 7aa, a art. 38a uchylony.
Kto sprawdzi cytat w ustawie, znajdzie „(uchylony)" i straci zaufanie do reszty dokumentu.

**Skutek DLA KLIENTA:** właściciel sklepu cytujący ten przepis kupującemu albo księgowej powołuje
się na nieistniejącą podstawę. **Waga: DROBNA.** **RODZAJ SKUTKU:** `zaobserwowany`.

**Propozycja naprawy:** `art. 38a` → `art. 7aa`. Przy okazji `aktywacja.php:191`:
„art. 38 **ust.** o prawach konsumenta" → „art. 38 **ustawy** o prawach konsumenta".

---

## Wyniki CZYSTE (też są wynikiem)

1. ✅ **Mail po zakupie niesie potwierdzenie zgody** — art. 21 ust. 1 pkt 2 / art. 8 ust. 7 lit. b
   dyrektywy. Z treścią zgody **i** znacznikiem czasu, przepisane **z bazy**, nie z bieżącej stałej,
   więc zmiana napisu w kodzie nie sfałszuje starych potwierdzeń (`zakup.php:1193-1195`).
2. ✅ **Zgoda ma jedno źródło prawdy** — `zakup.php:11` → render `:610` → zapis `:859` → mail `:1195`.
   Nie ma drugiej kopii napisu, która mogłaby się rozjechać. Dokładnie to, czego uczy klasa 27 ściągi.
3. ✅ **Zwrot nie odcina wcześniej wysłanego linku — i instrukcja mówi to WPROST.**
   Kod: `pobieranie.php` nie sprawdza statusu (`grep -c status` = **0**, przy `token_pobrania`=2,
   `pobrania_uzyte`=1, `link_wygasa`=1 jako próbie kontrolnej). Instrukcja `:90-91`: „⚠️ Zwrot ani
   spór **nie odcinają** wcześniej wysłanego linku do pobrania — działa do końca swojej ważności
   (72 godziny, 10 pobrań)". Kod i tekst dla klienta zgodne. **Szukałem tu rozjazdu i go nie ma.**

---

## Czego NIE sprawdziłem i dlaczego

| Co | Dlaczego | Do zrobienia |
|---|---|---|
| **Czy strony motywu gospodarza** (`/polityka-prywatnosci`, id 11) **pokrywają przetwarzanie danych przez sklep** | to strony witryny, nie wtyczki; ich treść należy do terenu `F4`/RODO, nie do F5 | wziąć przy `F4` |
| **Kwalifikacja prawna sformułowań** (czy dane zdanie „wystarcza" wobec art. 12) | nie jestem prawnikiem; mierzyłem **obecność treści**, nie jej wystarczalność | opinia prawnika przed sprzedażą na żywo |
| **Pozostałe punkty art. 12 ust. 1** (3a, 5a, 6, 9, 10, 11, 15, 16, 17, 18) | część nie dotyczy sprzedaży jednorazowej treści cyfrowej, część wymaga oceny prawnej | przy przeglądzie prawnym |
| **Strona produktu Segregatora** pod kątem tych samych par cytatów | mierzyłem ChatBota; teksty gwarancji są **identyczne** (`front-produkt.php:345` i `:505` — ten sam napis), ale całości drugiej strony nie przeszedłem | powtórzyć M9 dla `/sklep/produkt/segregator-maila/` |
| **Wygląd regulaminu po opublikowaniu** | strona jest szkicem; nie publikuję jej, bo to zapis na wspólnym stosie | właściciel na swoim stanowisku |
| **Wersja HTML maila** | czytałem `mail_tresc` (wersja tekstowa zapisana w bazie); wersji graficznej nie oglądałem w łapaczu | otworzyć w mailpicie |
| **Czy inne wtyczki/motyw dokładają regulamin** | poza zakresem naszego produktu | — |

---

## ⭐ Czego NIE było na mojej liście (przeciwwaga)

1. **Kto jest administratorem danych przy 4 wdrożeniach.** `ZROZUMIENIE.md` R2 mówi, że każdy
   z czterech właścicieli stron jest **osobnym administratorem danych** i że instrukcja musi to
   mówić. Moje pytanie tego nie obejmowało — sprawdziłem szablon, nie ten wymóg.
2. **Czy sklep w ogóle wie, że sprzedaje w Polsce.** Nie ma nigdzie wyboru jurysdykcji ani waluty
   innej niż konfigurowalna — a szablon regulaminu cytuje polską ustawę na sztywno. Przy sprzedaży
   z innego kraju ten tekst byłby po prostu błędny. Nikt tego nie pytał.
3. **Trwały nośnik.** Przyjąłem, że mail spełnia wymóg trwałego nośnika — to założenie
   podręcznikowe, ale **go nie zweryfikowałem** (mail z linkiem, który wygasa po 72 h, bywa
   przedmiotem sporu w tej kwestii).
4. **Teksty w panelu właściciela** jako źródło obietnic prawnych — sprawdzałem front i mail;
   panel opisuje np. anonimizację i zwroty własnymi słowami i nikt ich nie zestawił z ustawą.
5. **Ścieżka „kupujący chce odstąpić"** — nie ma jej w produkcie w ogóle (żadnego formularza,
   żadnego wzoru z załącznika nr 2 do ustawy). Nie pytałem o nią, bo pytanie brzmiało „czy jest
   regulamin", a nie „co się dzieje, gdy ktoś jednak chce odstąpić".

---

## Propozycja wiersza do mapy terenu i do rejestru (gotowe do przeklejenia)

⛔ Nie edytuję `audyt/MAPA-TERENU-INFRA.md` ani rejestrów — pliki wspólne.

**Do `audyt/MAPA-TERENU-INFRA.md`, sekcja F — podmiana wiersza `F5`:**

```
| F5 | Regulamin sklepu (osobny dokument) | 🟡 | **1.09**, `audyt/plamy/F5-RAPORT.md` (sha `7a8fb5b`), przepisy cytowane ze źródła w `audyt/plamy/F5-teksty/zrodla-prawne.md` | ⛔ **5 znalezisk.** Regulamin i Polityka to SZKICE (`draft`, bez sluga), a opcje `sklepag_strona_regulamin/_polityka` mają **2 trafienia w całej wtyczce** (tworzenie + kasowanie) przy 18/6 dla stron używanych — **nic ich nie linkuje, panel o nich nie przypomina** (6 notice, 0 wzmianek). Szablon „Regulaminu" to 5 linii i 3 wypełniacze: brak procedury reklamacyjnej, odpowiedzialności za zgodność świadczenia, pozasądowych sposobów i **treści gwarancji**, którą strona produktu obiecuje 4× w 3 brzmieniach. ✅ CZYSTO: mail niesie potwierdzenie zgody (art. 21 ust. 1 pkt 2 / dyr. art. 8 ust. 7 lit. b) z datą, przepisane z bazy. 🟡 nie sprawdzone: strony motywu gospodarza (→ F4), kwalifikacja prawna sformułowań, strona Segregatora |
```

**Do rejestru znalezisk (produkt + dokument):**

```
| `F5-F1` | **DUZE** · `aktywacja.php:191` zakłada „Regulamin sklepu" jako `draft`; opcje `sklepag_strona_regulamin/_polityka` używane WYŁĄCZNIE tam i w `uninstall.php:36` (2 trafienia vs 18/6 dla stron używanych) — kupujący nie ma linku do regulaminu na żadnej stronie sklepu (rdzeń „regulamin" = 0/0/0, próba kontrolna 155/307/17), panel nie przypomina (6 notice, 0 wzmianek), instrukcja mówi o tym jednym zdaniem w rozdziale o RODO (`INSTRUKCJA.md:211`) — a nawet wykonanie tego zdania nie tworzy linku | `audyt/plamy/F5-RAPORT.md` §① i M3/M5/M6 — BRAK strażnika | OTWARTE |
| `F5-F2` | **DUZE** · `aktywacja.php:191` — szablon regulaminu (365 B, 5 linii, 3 wypełniacze) nie zawiera art. 12 ust. 1 pkt 5, 7, 8 (procedura reklamacji), 13, 14 (treść gwarancji i sposób realizacji), 19/20, 21; gwarancja obiecana 4× na stronie produktu w 3 brzmieniach ma w regulaminie **0 wystąpień** (próba kontrolna `sprzedawca\|reklamac` = 2) | `audyt/plamy/F5-RAPORT.md` §② Para A + tabela pkt art. 12; przepisy w `F5-teksty/zrodla-prawne.md` — BRAK strażnika | OTWARTE |
| `F5-F3` | **SRED** · dane sprzedawcy istnieją tylko jako stopka maila PO zakupie (`admin-ustawienia.php:162` — sekcja nazwana wprost „Dane sprzedawcy (stopka maila po zakupie)”, render `zakup.php:1168`); na stronie z przyciskiem „Kup teraz” brak nazwy, adresu, NIP-u, adresu reklamacji, informacji o zgodności świadczenia i o ODR — a art. 12 ust. 1 wymaga ich PRZED zawarciem umowy | `grep -oic` po rdzeniach: `brutto/VAT/podatek`=0, `ODR/pozasąd/polubown`=0, `rękojm`=0, `zgodnoś`=0; próba kontrolna `sklepag`=307, `gwaranc`=7 — BRAK strażnika | OTWARTE |
| `F5-F4` | **SRED** · klasa 65+54: szablon Polityki rósł w 0.9.8 (opinie), 0.9.23 (IP przy pobraniu), 0.9.25 (dane do faktury), a `INSTRUKCJA.md:212-214` każe dopisać ręcznie TYLKO fragment o IP i tylko instalacjom sprzed 0.9.23; strona 121 na stanowisku nie ma żadnego z trzech | porównanie `wp post get 121` z `aktywacja.php:192` + `git log -S` (`65a88a5`, `9049704`, `2992247`) — BRAK strażnika | OTWARTE |
| `F5-F5` | **MALE** · `INSTRUKCJA.md:148` powołuje się na `art. 38a ustawy o prawach konsumenta`, a w tekście jednolitym Dz.U. 2024 poz. 1796 stoi „Art. 38a. (uchylony)” — obowiązuje `art. 7aa`. Dodatkowo `aktywacja.php:191`: „art. 38 **ust.** o prawach konsumenta” zamiast „ustawy” | `audyt/plamy/F5-teksty/zrodla-prawne.md` §7 (cytat z ISAP, wiersze 300 i 876) — BRAK strażnika | OTWARTE |
```

**Do `rubryka-GOTOWE.md` — propozycja przypisu przy kryterium #11 (nie zmieniam sam):**
werdykt ✅ dla członu „Regulamin i Polityka jako szablony bez cudzych danych" jest **prawdziwy
co do litery** (cudzych danych tam nie ma) i **mylący co do wagi**: kryterium pytało o brak cudzych
danych, nie o to, czy dokument jest regulaminem. Proponuję dopisać przy #11 odsyłacz do `F5-F1`/`F5-F2`.

---
⭐ **Lista zadań dla następnej sesji** — to, czego przy tej plamie NIE zdążyłem sprawdzić, jest zebrane razem z pozostałymi w `audyt/plamy/PRZEKAZANIE-NASTEPNEJ-SESJI.md`. Sekcja „Czego NIE sprawdziłem" wyżej jest źródłem tamtej listy, nie jej zamiennikiem.
