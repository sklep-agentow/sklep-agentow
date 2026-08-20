/* C — brak sygnalu, ze bot pisze. Straznik, kod wyjscia 1 przy porazce.
 *
 * Tryb AI ma timeout 9 sekund plus jedno ponowienie, czyli do 18 sekund ciszy.
 * Pole i przycisk nie byly blokowane i nie bylo zadnego wskaznika, wiec gosc
 * uznawal, ze sie zawiesilo, i klikal drugi raz — a kazde kliniecie to kolejne
 * wywolanie modelu na rachunku wlasciciela.
 *
 * Odpowiedz serwera OPOZNIAMY przez page.route, zeby stan "bot pisze" dalo sie
 * w ogole zobaczyc; potem przepuszczamy zadanie do prawdziwego serwera.
 *
 * Uzycie: node testy/wskaznik-pisania-test.js [adres]
 */
const { chromium } = require('playwright-core');
const { wymagajChromium } = require('./lib/chromium');
const BAZA = process.argv[2] || 'http://localhost:8890';

let bledy = 0;
function sprawdz(opis, warunek, szczegol) {
  console.log((warunek ? '  OK   ' : '  BLAD ') + opis);
  if (szczegol) { console.log('       ' + szczegol); }
  if (!warunek) { bledy++; }
}

(async () => {
  const b = await chromium.launch({ executablePath: wymagajChromium() });
  const p = await b.newPage({ viewport: { width: 1280, height: 900 } });

  /* Opoznienie 1,5 s tylko dla pytania — dosc, zeby zmierzyc stan posredni. */
  let wyslanych = 0;
  await p.route('**/wp-admin/admin-ajax.php', async (route) => {
    const dane = route.request().postData() || '';
    if (dane.indexOf('chatl_pytanie') !== -1) {
      wyslanych++;
      await new Promise((r) => setTimeout(r, 1500));
    }
    return route.continue();
  });

  await p.goto(BAZA + '/', { waitUntil: 'load' });
  await p.click('.chatl-babelek');

  console.log('[1] stan spoczynku: pole i przycisk dzialaja, nie ma dymka pisania');
  sprawdz('pole pytania aktywne', false === (await p.$eval('.chatl-pole-pytania', (e) => e.disabled)));
  sprawdz('przycisk aktywny', false === (await p.$eval('.chatl-wyslij-pytanie', (e) => e.disabled)));
  sprawdz('brak dymka pisania', (await p.$('.chatl-pisze')) === null);

  console.log('\n[2] w trakcie oczekiwania: blokada + dymek z kropkami');
  await p.fill('.chatl-pole-pytania', 'ile kosztuje wasza usluga');
  await p.click('.chatl-wyslij-pytanie');
  /* Czekamy OGRANICZONY czas i raportujemy wynik, zamiast pozwolic
     waitForSelector rzucic wyjatkiem: brak dymka ma byc opisanym krokiem
     "BLAD ...", a nie wywroceniem calego testu — inaczej proba kontrolna nie
     widzi, KTORA obietnica padla. Zmierzone na mutancie "brak dymka". */
  const jestDymek = await p.waitForSelector('.chatl-pisze', { timeout: 5000 })
    .then(() => true).catch(() => false);
  sprawdz('pojawil sie dymek "bot pisze"', jestDymek);
  if (!jestDymek) {
    console.log('       (dalsze kroki tej sekcji nie maja czego sprawdzac)');
  }
  sprawdz('dymek ma trzy kropki', 3 === (await p.$$eval('.chatl-pisze .chatl-kropka', (e) => e.length).catch(() => 0)));
  sprawdz('pole pytania ZABLOKOWANE', true === (await p.$eval('.chatl-pole-pytania', (e) => e.disabled)));
  sprawdz('przycisk ZABLOKOWANY', true === (await p.$eval('.chatl-wyslij-pytanie', (e) => e.disabled)));
  sprawdz('dymek ma etykiete dla czytnika ekranu',
    'Bot pisze odpowiedź' === (await p.$eval('.chatl-pisze .chatl-dymek', (e) => e.getAttribute('aria-label')).catch(() => null)));
  sprawdz('dymek jest ogloszany (aria-live)',
    'polite' === (await p.$eval('.chatl-pisze .chatl-dymek', (e) => e.getAttribute('aria-live')).catch(() => null)));

  console.log('\n[3] ponowne wyslanie w trakcie oczekiwania NIE leci do serwera');
  /* NIE uzywamy fill/click: Playwright CZEKA, az pole sie odblokuje, wiec
     zamiast podwojnego wyslania badalby drugie pytanie po kolei (zmierzone —
     licznik rosl 1 -> 2 i test oskarzal produkt o swoj wlasny blad).
     Wymuszamy zdarzenie submit wprost, omijajac blokade w przegladarce —
     to jest dokladnie ta sciezka, ktorej ma bronic straz w kodzie widgetu. */
  const przed = wyslanych;
  await p.evaluate(() => {
    document.querySelector('.chatl-pole-pytania').value = 'drugie pytanie';
    document.querySelector('.chatl-form-pytania').dispatchEvent(
      new Event('submit', { cancelable: true, bubbles: true })
    );
  });
  await p.waitForTimeout(400);
  sprawdz('licznik zadan nie wzrosl w trakcie oczekiwania', wyslanych === przed,
    'zadan: ' + przed + ' -> ' + wyslanych);

  console.log('\n[4] po odpowiedzi: dymek znika, pole wraca');
  await p.waitForFunction(
    () => document.querySelectorAll('.chatl-wiadomosci .chatl-bot:not(.chatl-pisze)').length >= 2,
    null, { timeout: 20000 }
  );
  await p.waitForTimeout(200);
  sprawdz('dymek pisania zniknal', (await p.$('.chatl-pisze')) === null);
  sprawdz('pole pytania znow aktywne', false === (await p.$eval('.chatl-pole-pytania', (e) => e.disabled)));
  sprawdz('przycisk znow aktywny', false === (await p.$eval('.chatl-wyslij-pytanie', (e) => e.disabled)));
  const rozmowa = await p.$eval('.chatl-wiadomosci', (e) => e.textContent.replace(/\s+/g, ' ').trim());
  sprawdz('odpowiedz bota jest w rozmowie', rozmowa.length > 0);
  sprawdz('kropki nie zostaly w tresci rozmowy', (await p.$$eval('.chatl-kropka', (e) => e.length)) === 0);

  console.log('\n[5] po BLEDZIE pole tez wraca (jedna wpadka nie zamyka czatu)');
  /* Zdejmujemy trase dopiero, gdy nic nie jest w locie — inaczej podmiana
     obslugi trafia w zadanie juz obsluzone i Playwright rzuca wyjatkiem. */
  await p.waitForTimeout(300);
  await p.unroute('**/wp-admin/admin-ajax.php');
  await p.route('**/wp-admin/admin-ajax.php', (route) => route.abort());
  await p.fill('.chatl-pole-pytania', 'pytanie ktore padnie');
  await p.click('.chatl-wyslij-pytanie');
  await p.waitForTimeout(1200);
  sprawdz('po nieudanym zadaniu pole jest odblokowane', false === (await p.$eval('.chatl-pole-pytania', (e) => e.disabled)));
  sprawdz('po nieudanym zadaniu przycisk jest odblokowany', false === (await p.$eval('.chatl-wyslij-pytanie', (e) => e.disabled)));
  sprawdz('dymek pisania sprzatniety takze po bledzie', (await p.$('.chatl-pisze')) === null);

  await b.close();
  console.log('\n' + (bledy ? 'NIEPOWODZENIE: ' + bledy + ' krokow nie przeszlo' : 'WSZYSTKIE KROKI PRZESZLY'));
  process.exit(bledy ? 1 : 0);
})().catch((e) => { console.error(e); process.exit(1); });
