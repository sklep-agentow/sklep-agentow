# 🔧 PLAN NAPRAWY — wpięcie sklepu w mechanizm prywatności WordPressa (`I-16`)

> **Zgoda właściciela 1.09:** *naprawiamy wszystko, co jest błędem.*
> Ten plik istnieje, żeby ta naprawa NIE powtórzyła losu długu, który opisuje: był zapisany
> w `audyt/CO-WISI.md:304`, nikt go nie przeniósł i produkt pojechał do klienta.

## Co jest źle (zmierzone 1.09)

Sklep przechowuje dane osobowe kupujących i **nie rejestruje ani jednego** z mechanizmów
prywatności WordPressa:

```
wp_privacy_personal_data_exporters   → 0 wystąpień
wp_privacy_personal_data_erasers     → 0 wystąpień
wp_add_privacy_policy_content        → 0 wystąpień
kontrola dodatnia: add_action        → 33 wystąpienia
```

**Skutek dla klienta:** obsługa sklepu dostaje żądanie RODO, używa wbudowanego narzędzia
WordPressa *Narzędzia → Eksportuj / Usuń dane osobowe*, dostaje raport **bez danych ze sklepu**
i uznaje obowiązek za wypełniony. Dane kupującego zostają.

⛔ **Sklep NIE ignoruje RODO** — ma własną anonimizację (`sklepag_obsluz_anonimizacje`,
`admin_post_sklepag_anonimizuj`), przemyślaną i naprawianą. Brakuje MOSTU do WordPressa.

## Gdzie leżą dane osobowe (7 tabel, prefiks `sklepag_`)

| tabela | dane osobowe |
|---|---|
| `zamowienia` | `email_kupujacego`, `dane_faktury` (nazwa, NIP, adres) |
| `sesje_checkout` | adres e-mail w metadanych sesji |
| `opinie` | imię autora, powiązanie z zamówieniem |
| `zdarzenia` | adres e-mail w treści `opis`, adres IP |
| `logowania` | adres IP |
| `rate_limit` | adres IP |
| `produkty` | brak danych osobowych |

## Kroki — w tej kolejności

**1. Wydzielić czystą funkcję anonimizacji.** Dziś logika jest wpleciona w obsługę żądania
HTTP: `current_user_can`, `check_admin_referer`, `$_GET`, `wp_die`. Nie da się jej wywołać
z erasera. Wydzielić `sklepag_anonimizuj_zamowienie( int $id ): array` zwracającą wynik
zamiast kończyć proces; obsługa panelu zostaje cienką skorupą, która ją woła.
⛔ **Zachować kolejność operacji** — jest częścią naprawy, nie stylem: najpierw ślady wtórne
(dziennik, opinia), na końcu źródło (adres w zamówieniu). Odwrotna kolejność uniemożliwiała
powtórzenie po awarii i zostawiała adres w dzienniku NA STAŁE, z potwierdzeniem usunięcia.

**2. Eksporter** (`wp_privacy_personal_data_exporters`). Po adresie e-mail zwraca: zamówienia
(numer, data, kwota, pozycje, dane do faktury), opinie, sesje checkout. Stronicowanie
obowiązkowe — WordPress woła eksporter partiami i przekazuje numer strony.

**3. Kasownik** (`wp_privacy_personal_data_erasers`). Most do funkcji z kroku 1. Zwraca
`items_removed` / `items_retained` zgodnie z kontraktem WordPressa. ⛔ Dane wymagane prawem
podatkowym (faktura: nazwa, NIP, adres) **zostają** — z jawnym `items_retained` i powodem;
podstawa jest już opisana w `DECYZJE.md` D36 (art. 6 ust. 1 lit. c RODO, obowiązek z ustawy
o VAT, art. 106e ust. 1 pkt 3).

**4. Podpowiedź do polityki prywatności** (`wp_add_privacy_policy_content`). Co sklep zbiera,
po co, na jakiej podstawie i jak długo trzyma.

**5. Próba kontrolna.** Każdy przypadek psuje DOKŁADNIE JEDEN warunek:
eksporter zwraca dane istniejącego kupującego · zwraca pustkę dla nieznanego adresu ·
stronicowanie oddaje komplet w dwóch partiach · kasownik czyści ślady wtórne i źródło ·
kasownik ZOSTAWIA dane wymagane prawem i mówi o tym w `items_retained` ·
kontrola dodatnia: przed naprawą WordPress nie widzi tych danych wcale.

## Czego ta naprawa NIE obejmuje

Retencji czasowej (automatyczne kasowanie po N miesiącach) — to `F4` w mapie terenu, nadal
NIETKNIĘTE, i osobna decyzja właściciela o polityce. Tu chodzi wyłącznie o żądanie kupującego.

## Kolejność wobec innych prac

⛔ Osobny pull request, PO domknięciu #243. Powód: #243 rusza infrastrukturę i bramki, ten
rusza KOD PRODUKTU dla klienta — mieszanie ich w jednym zgłoszeniu utrudnia recenzję i cofnięcie.
