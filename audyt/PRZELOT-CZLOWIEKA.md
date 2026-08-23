# 🖱️ RĘCZNY PRZELOT CZŁOWIEKA — lista do odhaczenia (~40 min)

> ⛔ **To jest TWARDY WARUNEK KOŃCA audytu, nie opcja.** Uzasadnienie jest najmocniejszym
> dowodem, jaki mamy o własnych ślepych plamach: **17 znalezisk złapał wyłącznie człowiek
> klikający, automaty zero.** Zrzut ekranu nie klika. Agent nie poczuje, że coś jest upierdliwe.
>
> Kto: Dzidek albo koordynatorka. **Nie autor wtyczki** — on wie, gdzie kliknąć, żeby zadziałało.
> Gdzie: `http://localhost:8890`, poczta na `:8025`.

## Jak wypełniać
Przy każdym punkcie postaw jeden znak i **dopisz, co zobaczyłeś** — jedno zdanie wystarczy.
`✅` zadziałało tak, jak obiecuje produkt · `🐞` nie zadziałało · `😖` zadziałało, ale jest
upierdliwe albo mylące (**to też znalezisko** — kupujący nie odróżnia „działa źle" od „działa,
ale mnie wkurza") · `⬜` nie sprawdzone.

⛔ **Nie czytaj kodu.** Jesteś tu jako człowiek, który pierwszy raz widzi ten produkt.
Jeśli musisz zajrzeć do kodu, żeby zrozumieć, co się stało — **to jest znalezisko**,
bo kupujący do kodu nie zajrzy.

---

## A. ChatBot LIVE — oczami GOŚCIA strony (15 min)

- [ ] Wejdź na stronę z czatem **jako niezalogowany gość**, w oknie prywatnym. Otwórz okno czatu.
      Czy widać, jak zacząć, bez czytania instrukcji?
- [ ] **Napisz pierwszą wiadomość i wyślij.** Czy przyszła odpowiedź?
      ⭐ *Ten punkt jest najważniejszy z całej listy.*
- [ ] Zadaj pytanie, na które bot **zna** odpowiedź (z bazy par). Trafna?
- [ ] Zadaj pytanie, na które bot **nie zna** odpowiedzi. Czy mówi to uczciwie, czy zmyśla?
- [ ] Wpisz **polskie znaki, emoji, cudzysłowy i znacznik HTML** w jednym pytaniu. Co się stało?
- [ ] Wyślij **bardzo długie** pytanie (przekrocz limit). Czy dostajesz zrozumiały komunikat,
      czy cisza?
- [ ] **Kliknij wyślij dwa razy szybko.** Dublują się wiadomości?
- [ ] Otwórz czat **w dwóch kartach naraz** i pisz w obu. Rozmowy się mieszają?
- [ ] Zamknij okno czatu i otwórz ponownie. Rozmowa jest, czy zniknęła?
- [ ] **Odetnij internet w połowie oczekiwania na odpowiedź** (tryb offline w narzędziach
      przeglądarki). Co widzi gość — prawdę czy wieczne kółko?
- [ ] Przejdź **cały czat samą klawiaturą**, bez myszy: Tab, Enter, Escape. Da się?
- [ ] Otwórz **konsolę przeglądarki** (F12) i przeklikaj czat jeszcze raz.
      Czy w konsoli są czerwone błędy? ⭐ Wpisz ich treść.

## B. ChatBot — przejęcie rozmowy przez człowieka (5 min)
- [ ] Jako gość napisz wiadomość. W drugiej przeglądarce, jako **administrator**, przejmij rozmowę.
- [ ] **Czy gość widzi, że dołączył konsultant?** Bez odświeżania strony.
- [ ] Napisz jako konsultant. Czy gość dostaje wiadomość i wie, że to człowiek?
- [ ] **Zamknij kartę konsultanta** i nic nie rób. Czy rozmowa wraca do bota, i czy gość o tym wie?

## C. Segregator maila — oczami WŁAŚCICIELA firmy (15 min)
- [ ] Wejdź do panelu Segregatora **pierwszy raz**. Wiadomo, od czego zacząć?
- [ ] Podłącz skrzynkę (mailpit `:8025`). Czy komunikaty prowadzą Cię za rękę, gdy coś nie gra?
- [ ] Wpisz **zły port** albo **złe hasło**. Czy komunikat mówi, co konkretnie poprawić?
- [ ] Zostaw pole **portu puste** i zapisz. Co się stało z wartością?
- [ ] Puść przebieg **na sucho**. Czy widać, co BY zrobił, i czy na pewno nic nie ruszył?
- [ ] Puść przebieg **na żywo**. Sprawdź w mailpicie, czy maile faktycznie się przeniosły.
- [ ] **Cofnij przebieg.** Czy maile wróciły tam, skąd przyszły?
      ⭐ *Sprawdź w mailpicie, nie ufaj komunikatowi na ekranie.*
- [ ] Puść **dwa przebiegi naraz** (kliknij, a w drugiej karcie kliknij ponownie). Co się stało?
- [ ] Zmień regułę tak, żeby pasowała do wszystkiego. Przebieg. Czy da się to odkręcić?

## D. Wspólne — to, co widzi każdy administrator (5 min)
- [ ] Wejdź na **pulpit WordPressa** i na ustawienia **innej** wtyczki. Czy któryś z botów
      wyświetla tam swoje komunikaty? Da się je zamknąć **na stałe**?
- [ ] Zaloguj się jako **redaktor** (nie administrator). Widzisz panele botów? Da się coś kliknąć?
- [ ] Przejdź instrukcję **dosłownie, krok po kroku**, jak kupujący. Każdy krok da się wykonać?
      ⭐ Zapisz **czas** — instrukcja obiecuje konkretną liczbę minut i ta liczba ma mieć pokrycie.
- [ ] Kliknij **każdy odnośnik** w panelach obu wtyczek. Któryś prowadzi donikąd?

---

## Wynik
Policz na dole: ile `✅`, ile `🐞`, ile `😖`, ile `⬜`.
⛔ **Każde `🐞` i `😖` idzie do `audyt/REJESTR-ZNALEZISK.md`** z kodem `CZ-1`, `CZ-2`… —
tak samo jak znaleziska agentów, bez taryfy ulgowej i bez pomijania „bo to drobiazg".
Znalezisko od człowieka klikającego jest **statystycznie cenniejsze** niż od automatu:
mamy pomiar, że siedemnastu z nich automaty nie zobaczyły w ogóle.
