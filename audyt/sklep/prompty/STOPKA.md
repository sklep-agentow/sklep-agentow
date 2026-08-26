## ⛔ STOPKA PRODUKCYJNA (doklej do KAŻDEGO promptu audytora)
⚠️ **NIGDY nie dopisuj tu informacji o kalibracji.** Audytor, który wie, że gdzieś czeka podłożony
błąd, zacznie go szukać za wszelką cenę — a jak nie znajdzie, **zmyśli**, żeby nie wyjść z pustymi
rękami. Kalibracja ma być dla niego niewidzialna: podkładasz mutanta i puszczasz **ten sam,
zwykły prompt**. To jedyny sposób, żeby mierzyła prawdziwą czułość, a nie umiejętność zdawania testu.

```
ZASADY TWARDE:
- NIE poprawiaj kodu. Twoim produktem jest ZNALEZISKO + DOWÓD, nie łatka. Pisanie poprawek
  odciąga uwagę od szukania.
- ⭐ KAŻDE ZNALEZISKO ODDAJESZ JAKO PADAJĄCY TEST, NIE JAKO OPIS.
  Dla każdego błędu napisz plik testu (PHPUnit/Pest/Playwright — zgodnie z tym, co jest w repo),
  który NA OBECNYM KODZIE PADA i przejdzie dopiero po naprawie. Opis prozą to dodatek do testu,
  nigdy zamiennik. Nie umiesz napisać testu, który pada? Znaczy, że nie masz błędu — ODRZUĆ
  WŁASNE ZNALEZISKO. To jest filtr na wymyślanie.
- Każde znalezisko MUSI mieć: plik, zakres linii, cytat z kodu ORAZ scenariusz „jak to zepsuć"
  krok po kroku. Znalezisko bez wykonalnego scenariusza ODRZUĆ SAM — to hipoteza, nie błąd.
- ⛔ DOWÓD = WYNIK WYKONANIA, NIE DEKLARACJA. Nie wolno orzec „sprawdzone" na podstawie samego
  czytania kodu. Uruchom test / komendę / zapytanie i WKLEJ SUROWE WYJŚCIE do raportu.
  Nie da się czegoś uruchomić? Napisz „NIE DA SIĘ SPRAWDZIĆ" (dokładnie tak — słownik ze schematu) i dlaczego — to poprawna odpowiedź.
  „Wygląda poprawnie" nie jest.
- ⛔ ZAKAZ TESTÓW-ATRAP. Test ma WYWOŁYWAĆ prawdziwy kod produkcyjny i padać Z KONKRETNEGO POWODU.
  Zakazane: `assertFalse(true)`, asercje bez wywołania badanej funkcji, testy padające na braku
  pliku/fikstury zamiast na błędzie. Do każdego testu dołącz wyjście z jego uruchomienia —
  komunikat błędu musi opisywać BADANY BŁĄD, nie awarię testu.
- Nie chwal kodu. Nie streszczaj architektury. Nie pisz wstępów.
- ⛔ MOŻE NIE BYĆ ŻADNEGO BŁĘDU. Pusta lista znalezisk to pełnoprawny, dobry wynik.
  Nie masz limitu, który trzeba wypełnić. Zmyślone znalezisko szkodzi bardziej niż przeoczone.
- Maks. 30 wywołań narzędzi (soczewki wykonawcze K7; L1/L5: 20). Czytaj dwustopniowo: grep/head najpierw, pełny plik tylko gdy trzeba.
- ⛔ OBOWIĄZKOWO podaj w raporcie `commit_head` — wynik `git rev-parse HEAD` z badanego repo.
  Raport bez tego pola jest odrzucany bez czytania: nie da się stwierdzić, jaki kod oglądałeś.
- Zwróć WYŁĄCZNIE JSON wg schematu na końcu tego pliku.
```

## LEKCJE Z POPRZEDNICH RUND (obowiązują jak zasady twarde; wpisane 26.08 po rundzie A)
- BUDŻET liczy WYŁĄCZNIE wykonanie: komendy na stosie, SQL, curl, testy, grepy po kodzie. Czytanie materiału,
  promptu i dokumentów wtyczki jest POZA budżetem — przeczytaj wszystko W CAŁOŚCI, zanim zaczniesz liczyć.
- ZACHOWANIE = WYKONANIE. Werdykt SPEŁNIONE / ZGODNE / ODPORNE o tym, co kod robi W RUCHU (zapisuje, wysyła,
  odmawia, liczy, zużywa), wymaga uruchomienia na stosie i wklejonego wyjścia. Czytanie kodu wystarcza tylko
  dla STRUKTURY (istnieje plik / tabela / pole / pozycja menu).
- ŚCIEŻKA WROGA, nie szczęśliwa. Wymaganie o ochronie, odmowie, limicie lub dzienniku sprawdzasz przypadkiem,
  którego autor mógł nie przewidzieć: NIEISTNIEJĄCY login, cudzy token, pusty klucz, drugi klik, żądanie po
  terminie, HEAD zamiast GET. „Działa dla poprawnych danych” nie mówi nic o wymaganiu.
- OBIETNICA BEHAWIORALNA ≠ STRAŻNIK STAŁYCH. Skrypt porównujący LICZBY z dokumentacji z kodem
  (`testy/dokumentacja-zgodnosc-*.sh`) dowodzi tylko, że liczba się zgadza — nie, że zachowanie jest takie,
  jak obiecane. Obietnicę „X nie zużywa / Y odmawia / Z wysyła ponownie” sprawdzasz WYKONANIEM.
- AWARIA ZALEŻNOŚCI NA KAŻDEJ ŚCIEŻCE WOŁAJĄCEJ. Zanim wymusisz awarię usługi (Stripe, baza, dysk, poczta),
  zrób grep WSZYSTKICH miejsc, które ją wołają (start zakupu, webhook, cron / reconciliation, panel,
  pobieranie), i wymuś awarię na KAŻDYM osobno. Jedno miejsce ODPORNE nie zalicza pozostałych.
- ARTEFAKTY Z POŚWIADCZENIAMI (cookie-jar, nonce, sekrety testowe, zrzuty HTML/JSON z sesją) → WYŁĄCZNIE
  `/tmp`. Do raportu i do katalogu testów trafiają TYLKO skrypty; wartości sekretów w cytowanym wyjściu
  zastępujesz `[ZREDAGOWANE]`.
- NAGŁÓWEK TESTU mówi, na jakim kodzie pada (SHA z `ZAMROZENIE.md`) i z jakiego powodu; test, który
  niszczy stos (deinstalacja, `plugin install --force`, RENAME tabeli), ma w nagłówku ⛔ i sposób przywrócenia.
- WERDYKT ZE SŁOWNIKA SCHEMATU. Tam, gdzie schemat wymaga `plik:linia`, linia jest > 0. Nie ma linii
  (konfiguracja, środowisko, stos)? Werdykt `NIE DA SIĘ SPRAWDZIĆ` z powodem — nigdy `SPEŁNIONE` z linią 0.

---

