# PROPOZYCJA ARCHITEKTURY — sklep-agentow (do klepnięcia przez właściciela)

> ⚠️ **HISTORIA — UNIEWAŻNIONE PIWOTEM 17.08 (D16):** cały ten dokument opisuje wariant
> „statyczny HTML + Cloudflare", który odpadł (sklep = wtyczka WP, zaplecze w WP).
> Repo strony przemianowane: `matthewplugins.github.io` → dziś `MatthewPlugins/automatic-ai`
> (prywatne, mamy ZAPIS — zasady w ZROZUMIENIE §PIWOT PW-6). Zostaje jako zapis zwiadu.

Status: PROPOZYCJA 16.08.2026 ~23:15. Architektura = „drogo cofnąć" → decyzja właściciela.

## CO POKAZAŁ ZWIAD (fakty, sama sprawdziłam 16.08)

- Strona docelowa: `matthewplugins.github.io` = **Next.js static export na GitHub Pages** (darmowy hosting z repo).
- Repo ma **TYLKO branch `gh-pages` z gotowym buildem — ŹRÓDŁA Next.js tam NIE MA** (commity „deploy: <hash>" — źródło żyje gdzie indziej, pewnie prywatne).
- Strona ŻYWA (ostatni deploy: 16.08.2026). Rozmiar 32 MB (16 MB blog). **Bez własnej domeny** (brak CNAME → adres *.github.io).
- Sklepu/płatności na stronie NIE MA (menu: usługi, realizacje, portfolio, proces, blog, o-nas, faq, kontakt).
- Agencja podaje stack: Next.js/React, Supabase, Vercel, n8n, Claude/OpenAI.

## KONSEKWENCJE

1. **Nie mamy źródła Next.js** → nasz sklep NIE może być „dopisany do ich aplikacji". Musi być **samodzielnym statycznym katalogiem** `sklep/` dorzucanym do repo Pages. To zresztą i tak wymusza wymóg „4 różne strony + wpinane/odpinane".
2. **GitHub Pages nie wykona żadnego kodu serwerowego** → baza, panel admina, webhook płatności, maile, wydawanie plików = OSOBNA usługa zaplecza.

## PROPOZYCJA (jedna rekomendacja)

**Frontend:** katalog `sklep/` (czysty HTML/CSS/JS, zero frameworka) + `config.js` per strona (nazwa, waluta, adres zaplecza, klucz publiczny Stripe). Punkt zaczepienia w menu — 2 warianty do przetestowania na kości: (a) jedna linijka `<script>` dokładająca pozycję menu po załadowaniu strony, (b) ręczny link dodany przez właściciela w JEGO źródle. ⚠️ ryzyko do zbadania: hydracja React może nadpisać ręczne zmiany w zbudowanym HTML.

**Zaplecze: Cloudflare Workers + D1 + R2 — 0 zł/mies przy naszej skali.**
- Worker (mini-serwer): webhook Stripe, wydawanie plików podpisanymi linkami, wysyłka maila, API panelu admina, log logowań.
- D1 (baza SQL): produkty (CRUD, typ: agent/kurs), zakupy, dziennik logowań admina.
- R2 (magazyn): pliki produktów — NIE w publicznym repo (ryzyko R1 zamknięte).
- Limity darmowe (potwierdzone 16.08, developers.cloudflare.com): 100 000 requestów/DZIEŃ, D1: 5 GB + 5 mln odczytów/dzień, R2: 10 GB + darmowy transfer. Nasza skala: pojedyncze zakupy dziennie — zapas tysiąckrotny.
- Zakazu użycia komercyjnego na darmowym planie NIE znalazłam w cenniku; doprecyzuję w warunkach przy zakładaniu konta (wpis do rejestru NIEZWERYFIKOWANE).

**Płatności: Stripe Checkout** (strona płatności hostowana przez Stripe; karty nie dotykają naszej strony). Budowa i egzamin = tryb testowy. Produkcja = właściciel aktywuje konto i podmienia klucze w configu.

**Maile (potwierdzenie + instrukcje): Resend, darmowe 100/dzień.** ⚠️ SZCZERA DZIURA: wysyłka z WŁASNEJ domeny wymaga posiadania domeny — strony *.github.io jej nie mają. Na egzamin wystarczy tryb testowy/nadawca sandboksowy; na produkcję właściciel podpina swoją domenę (albo zmienimy dostawcę maila). Otwarte, nie blokuje budowy.

**Wdrożenie per właściciel (4 strony):** własne darmowe konto Cloudflare + własne konto Stripe + wypełniony config + skrypt wdrożeniowy z instrukcją (jak w MP: paczka + instrukcja prostym językiem).

**Odpięcie:** kasujesz katalog `sklep/` + linijkę menu; zaplecze wyłączasz osobno (usunięcie Workera). Strona wraca do stanu sprzed wpięcia.

## CO ODPADA I CZEMU (z dowodem)

- **Vercel darmowy (Hobby): ODPADA** — oficjalne wytyczne fair-use: „Hobby teams are restricted to non-commercial personal use only… Any method of requesting or processing payment from visitors of the site" (vercel.com/docs/limits/fair-use-guidelines, odczyt 16.08). Sklep = komercja wprost.
- **WooCommerce/WordPress** (wzorzec z agenciai.pl): odpada — nie wpina się w statyczny HTML.
- **Supabase**: niepotrzebny — D1 pokrywa bazę, mniej kont do zakładania per właściciel. (Stack kolegi go zna, ale kolega NIE utrzymuje naszego zaplecza.)
- **VPS**: odpada — koszt i utrzymanie ×4 właścicieli, przeczy „0 zł na start".

## OTWARTE (do kości / bramek, nie blokują klepnięcia)

1. Hydracja vs ręczny link w menu (test na kości, wariant a/b).
2. Symulacja BLIK w trybie testowym Stripe.
3. Nadawca maili na produkcji bez domeny.
4. Czy darmowy plan Cloudflare formalnie dopuszcza komercję (warunki, nie cennik).
