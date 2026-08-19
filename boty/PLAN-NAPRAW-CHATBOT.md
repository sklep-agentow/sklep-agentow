# PLAN NAPRAW — ChatBot LIVE (przed audytem, wersja docelowa 1.2.2)

**Data: 2026-08-20.** Podstawa: 4 niezależne krytyki modeli (runda 1) zweryfikowane w kodzie
+ znaleziska Dzidka z klikania na żywo. ⛔ To jest osobny produkt — Segregator ma własny plan.

## KONTEKST (czego krytycy nie wiedzieli)
- To **testowy bot na egzamin**, sprzedawany jako produkt w naszym sklepie. Nazwę i zakres
  podał ZAMAWIAJĄCY w notatce zlecenia — nazwy nie zmieniamy.
- Świadome granice (decyzja zamawiającego, zapisana w rejestrze D19): bez czatu z żywym
  operatorem, bez pamięci między wizytami, bez czytania treści strony.
- Termin: paczka i pierwsza próba oddania **27.08**.
- Bot działa w 100% BEZ klucza AI (silnik regułowy). AI to opcja na kluczu właściciela.

## STAN OBECNY (zweryfikowany w kodzie, nie z pamięci)
✅ Baza pytań z wariantami · zgłoszenia + powiadomienie mailem właściciela · historia rozmów
z retencją 90 dni (cron) · seed 10 par PL · tryb AI ze stop-lossem i zjazdem do reguł ·
klucz API bez autoload, nigdy nie wraca do formularza · nonce + capability na mutacjach ·
czysty uninstall (kasuje klucz ZAWSZE) · phpcs/phpstan czyste.
❌ Braki potwierdzone niżej.

## NAPRAWY — kolejność wg ryzyka

### A. PRAWNE (blokujące oddanie)
| # | Co | Dlaczego | Skąd |
|---|---|---|---|
| A1 | **Zgoda + informacja o prywatności w formularzu kontaktu**: checkbox „zapoznałem się", link do strony polityki (adres z ustawień), zdanie co zbieramy i na jak długo | gość zostawia e-mail i pytanie — dziś nie widzi ŻADNEJ informacji; to wymóg RODO | 3/4 krytyków, potwierdzone w kodzie (zero trafień) |
| A2 | **Rozdzielenie komunikatu o prywatności**: tryb regułowy = treść nie opuszcza serwera; tryb AI = pytanie idzie do dostawcy. W widgecie, instrukcji i na stronie produktu | dziś mówimy „dane zostają u Ciebie" — w trybie AI to nieprawda | 2/4 krytyków |
| A3 | **Eksport rozmów i zgłoszeń do CSV** przed automatycznym kasowaniem po 90 dniach | właściciel traci leady; RODO wymaga też możliwości wydania danych | 2/4 krytyków |

### B. UŻYWALNOŚĆ (znaleziska z klikania)
| # | Co | Dlaczego |
|---|---|---|
| B1 | **Zamykanie formularza kontaktu** (przycisk × + Escape + reset przy ponownym otwarciu czatu) | dziś formularz da się TYLKO wysłać — gość, który zmienił zdanie, ma go na stałe. Znalezisko Dzidka na żywo |
| B2 | **Test bota w panelu**: pole „wpisz pytanie" → pokazuje odpowiedź i która para zadziałała | właściciel musi dziś wychodzić na stronę, żeby sprawdzić własną bazę. 2/4 krytyków |
| B3 | **Warianty odporne na literówki i brak ogonków** w seedzie demo (cenik/cennik, godziny/godzin, otwarci/otwarte) + zdanie w instrukcji „dopisz warianty, jak klienci piszą" | silnik dopasowuje podłańcuchy — „cenik" dziś nie łapie „cennik". 3/4 krytyków wskazało sztywność |
| B4 | **Domyślny próg `$prog=10`** w limicie zapytań | obietnica z recenzji #40; kosmetyka, ale zamyka listę „nieruszonych" |

### C. PRODUKT (przewaga, tanie)
| # | Co | Dlaczego |
|---|---|---|
| C1 | **Drugi dostawca AI: Gemini** (wybór w ustawieniach) + **instrukcja zdobycia DARMOWEGO klucza w panelu** | 4/4 krytyków: „własny klucz to bariera dla nietechnicznego". Gemini daje klucz bez karty → bariera znika. Dodatkowo pozwala PRZETESTOWAĆ tryb AI na żywo, czego nigdy nie zrobiliśmy |
| C2 | **Uczciwy podtytuł na stronie produktu**: co bot robi i czego NIE robi (brak czatu z człowiekiem) — mocniej niż dziś | 3/4 krytyków: nazwa „LIVE" myli. Nazwy nie zmieniamy (litera zamówienia), ale obietnicę prostujemy |

### D. DOKUMENTACJA (po zamrożeniu kodu)
| # | Co |
|---|---|
| D1 | Dokumentacja **techniczna**: schemat 5 tabel z opisem kolumn, zaczepy i filtry do rozbudowy, wymagania, co dzieje się przy instalacji i deinstalacji |
| D2 | **Diagram przepływu**: gość pyta → silnik szuka → trafienie / brak → formularz → zgłoszenie + mail |

## ⛔ ŚWIADOMIE NIE ROBIMY (idzie do „droga rozbudowy" w instrukcji)
Czat z żywym operatorem · Messenger / WhatsApp / Instagram · pamięć rozmów między wizytami ·
czytanie treści strony · aplikacja mobilna · integracja z kalendarzem rezerwacji ·
analityka konwersji. **Powód:** zakres klepnięty przez zamawiającego + termin 27.08.

## SZACUNEK CZASU
A: ~1,5 h · B: ~2 h · C: ~2,5 h · D: ~1 h → **razem ~7 h**, potem audyt.
