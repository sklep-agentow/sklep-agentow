# BRIEFING NAPRAW — stan wiedzy, który ma przeżyć wyczyszczenie kontekstu

Plik pisany przez buildera b1 (1.09). Trafia tu **tylko to, czego nie da się odtworzyć
z kodu ani z historii gita**: ustalenia, powody decyzji i pozycje jeszcze nierozstrzygnięte.

---

## 1. ⛔ ZNALEZISKO BEZ WPISU W REJESTRZE — produkt skasowany między zapłatą a webhookiem

**Stan sprzed `#258`** (`wtyczki/sklep-agentow/includes/zakup.php`, `sklepag_domknij_zakup()`,
linie **827–831** w commicie-rodzicu `80aac64`):

```php
$produkt = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$pp} WHERE id = %d", (int) $nasza->produkt_id ) );
if ( ! $produkt ) {
    sklepag_zdarzenie( null, 'domkniecie_brak_produktu', $sid );
    return true;
}
```

**Co się działo z pieniędzmi kupującego.** Webhook przychodzi **po** obciążeniu karty, więc
pieniądze są już u sprzedawcy. `return true` oddaje Stripe’owi **HTTP 200 = „przyjęte"**, więc
Stripe **nie ponawia**. Wiersz zamówienia **nie powstaje w ogóle** — nie ma maila, nie ma linku
do pobrania, nie ma pozycji na liście zamówień i nie ma tego w przychodzie na Pulpicie.
Jedynym śladem całej transakcji jest wpis `domkniecie_brak_produktu` w dzienniku zdarzeń.
Sesja zostaje w stanie `utworzona` (flaga stanu jest ustawiana dopiero za tym returnem), więc
`sklepag_dociagnij_zalegle()` bierze ją co 10 minut i **za każdym razem uderza w tę samą
ścianę** — bez końca i bez sygnału dla właściciela.

**Ważny niuans do wpisu w rejestrze:** panel **nie ma kasowania produktów** (w
`includes/admin-produkty.php` nie ma ani jednego `$wpdb->delete` na tabeli produktów — jest
tylko dezaktywacja `aktywny = 0`). Ten stan wymaga więc usunięcia wiersza **spoza panelu**:
ręczny SQL, migracja hostingu, nieudany import. Rzadkie — ale skutkiem jest zabrane pieniądze
i zero doręczenia, bez żadnego ostrzeżenia.

✅ **Zamknięte w `#258`** przy okazji migawki produktu: domknięcie nie czyta już tabeli
produktów, więc brak wiersza nie ma jak zatrzymać wydania zakupu. Pozycja nie ma jednak wpisu
w żadnym rejestrze — koordynatorka chciała ją dopisać.

---

## 2. Rzeczy oddane właścicielowi — ⛔ NIE ruszać bez jego decyzji

- **Podwójne kliknięcie „Kup teraz" tworzy dwie płatności.**
  `testy/idem-nie-oddaje-oplaconej-sesji-test.php`, przypadek **B**, pada **także na czystym
  `main`** (sprawdzone wgraniem kodu z `main` na ten sam stos). Okno idempotencji stoi na
  transiencie `md5(IP|produkt_id|tryb)` i nie dotyka tabeli sesji.
- **`aktywny = 0` a zakup już opłacony.** `#258` świadomie **nie** blokuje doręczenia zakupu
  opłaconego. Gdyby właściciel chciał inaczej, trzeba najpierw rozstrzygnąć, co się dzieje
  z pieniędzmi (zwrot automatyczny? kolejka dla właściciela?) — to zmiana reguły handlowej.

---

## 3. Ustalenia procesowe (zmierzone, nie zasłyszane)

- **„`push --force` jest na tej maszynie zablokowany" to NIEPRAWDA dla repo klienta** —
  pozycja `W-2` rejestru infra. Zasada „osobny commit zamiast poprawiania wypchniętego"
  obowiązuje niezależnie od blokady.
- **PR w konflikcie nie dostaje od GitHuba ŻADNYCH przebiegów CI** i nie mówi o tym słowem —
  wygląda jak PR czekający na kontrolę. Po każdym scaleniu do `main` sprawdzać
  `gh pr view <nr> --json mergeable`.
- **Wersja sklepu żyje w OŚMIU miejscach**, ChatBota w sześciu. Dwa ostatnie w sklepie
  (odznaka `README.md`, proza `SECURITY.md`) **nie są w `BRAMKI=`** — łapią je dopiero
  *próby kontrolne* `paczka-zgodnosc-proba-kontrolna.sh` i `security-wersje-proba-kontrolna.sh`,
  uruchamiane w CI krokiem „Proby kontrolne bramek". Lokalne 12/12 świeci wtedy na zielono.
- **Zestaw prób kontrolnych CI odtwarza się na koncie z Dockerem tak:**
  `DOCKER_HOST=unix:///nonexistent.sock bash narzedzia-qa/proby-kontrolne-przelot.sh <log> narzedzia-qa`
  (bez tego uruchamiasz 4 próby więcej niż wykonawca i stawiasz stos próbny).
  Przelot **odmawia pracy na brudnym drzewie**.
- **`audyt/REJESTR-ZNALEZISK-INFRA.md` NIE MA strażnika** — `bramka-znaleziska.sh:17` czyta
  wyłącznie `audyt/REJESTR-ZNALEZISK.md`. Zielone bramki nie dowodzą, że ten rejestr mówi prawdę.
  Liczniki („OTWARTYCH", „BEZ strażnika") trzeba obniżać **ręcznie, jednym ruchem z naprawą**.

---

## 4. Pułapki przyrządów, na które nadziałem się osobiście

1. **`wp eval-file` wciąga plik W FUNKCJI.** Zmienne z góry pliku **nie są globalne** —
   `global $ts` w funkcji pomocniczej daje pustą nazwę tabeli i `insert()` idzie w nicość.
   Nadziałem się dwa razy; łapie to warunek wstępny („zasiew NAPRAWDĘ jest w bazie").
2. **Poza AJAX-em `wp_send_json()` kończy się GOŁYM `die`**, nie `wp_die` — żaden z trzech
   uchwytów (`wp_die_handler`, `wp_die_json_handler`, `wp_die_ajax_handler`) nie zadziała.
   Test kończy się `EXIT=0` **bez ani jednej asercji** i wygląda jak przebieg, który przeszedł.
   Obejście: `add_filter( 'wp_doing_ajax', '__return_true' )` na czas wywołania handlera.
3. **Zasiew testu musi powstawać TĄ SAMĄ funkcją co dane w produkcji.** Ręcznie sklejony napis
   wyglądający na JSON nie jest wynikiem `wp_json_encode()` — przez to strażnik RODO przez całą
   swoją historię nie widział różnicy między postacią surową a zakodowaną.
4. **Formularz zakupu ma `required` na zgodzie** — e2e klikające „Kup teraz" bez zaznaczenia
   zgody nie robi NIC, a test świeci na zielono. Każdy test klikający musi mieć osobny warunek
   „przebieg NAPRAWDĘ ruszył" (stan pośredni, który MUSI wystąpić).
5. **Kod wyjścia mierzyć BEZ potoku** — `| tail` podmienia go na kod `tail`.

---

## 5. Klasy ze `SCIAGA-EGZAMINATORA.md`, na których łapano mnie w recenzjach

- **Klasa 8 — bliźniak naprawy.** Recenzja `#259`: zamknąłem awarię kasowania `chatl_sesje`,
  a dwa pozostałe `DELETE` w **tej samej funkcji** dalej połykały `false`. Domknięte jednym
  kształtem (`chatl_rodo_awaria_kasowania()`), nie trzema kopiami.
- **Klasa 11 — bramka o zakresie węższym niż nazwa.** Ta sama recenzja: strażnik nazwany
  „obietnica zgodna z kodem" mierzył obecność słowa `napisz`. Poprawione na pomiar OBU stanów
  opcji `sklepag_email_kontaktowy`, z kontrolą dodatnią na stopce.
- **Klasa 94 (etap budowy)** — otwierać `wp-plugin-development` **z nazwy**, nie z pamięci.

---

## 6. ⚠️ Obserwacja z boku — SZABLON polityki prywatności nie aktualizuje się po wgraniu nowej wersji

Zmierzone 1.09 na stosie warsztatowym przy pisaniu strażnika Q-1. Strona „Polityka prywatności
sklepu (SZABLON)" (opcja `sklepag_strona_polityka`, id 121) niesie **352 znaki** i kończy się na
„dziennik zdarzeń (90 dni)", podczas gdy szablon w `includes/aktywacja.php` ma dziś także akapit
o **danych do faktury (cel + podstawa z ustawy o VAT), adresie IP przy pobraniu i opiniach**.

**Powód:** strony sklepu powstają **raz, przy aktywacji** i nic ich potem nie odświeża. Klient,
który zainstalował starszą wersję, ma opublikowaną politykę **milczącą o kategoriach danych,
które sklep dziś przetwarza** — a to jest dokument RODO, nie tekst sprzedażowy.

⛔ **To NIE jest skutek żadnego z moich PR-ów** i nie ruszałem tego. Nie ma tej pozycji w żadnym
rejestrze. Do rozstrzygnięcia przez koordynatorkę: czy szablon ma być odświeżany przy migracji
schematu (ryzyko: nadpisanie tekstu, który właściciel już wypełnił), czy wystarczy ostrzeżenie
na Pulpicie „polityka pochodzi z wersji X, sprawdź ją".

⭐ Dlatego strażnik `faktura-obietnica-zgodna-z-kodem-test.php` mierzy **źródło szablonu**
(`includes/aktywacja.php`), a nie stronę w bazie: pomiar instancji mówiłby o wieku instalacji,
a nie o tym, co sklep daje kupującemu dziś.
