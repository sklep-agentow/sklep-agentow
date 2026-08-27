/* Blokada zakupu bez zgody mowi do kupujacego PO POLSKU.
 *
 * Sklep jest w calosci polski, ale pole zgody stalo na samym `required`, wiec
 * komunikat rysowala przegladarka — po angielsku („Please check this box if you
 * want to proceed."), zaleznie od jezyka przegladarki klienta. Jedyny obcojezyczny
 * tekst, jaki widzial kupujacy, i to dokladnie w chwili platnosci.
 *
 * Test pilnuje DWOCH rzeczy naraz, bo jedna bez drugiej jest bezwartosciowa:
 *   1) zakup bez zgody nadal NIE przechodzi (obietnica prawna, nie kosmetyka),
 *   2) komunikat, ktory widzi czlowiek, jest po polsku i NIE jest natywnym
 *      dymkiem przegladarki.
 *
 * Serwer odrzuca zakup bez zgody niezaleznie od JS (`includes/zakup.php`), wiec
 * ten test dotyczy warstwy jezyka — ale wprost sprawdza, ze naprawa jezyka nie
 * rozszczelnila blokady.
 *
 * Wymaga playwright-core i dzialajacego stacku. Bez logowania (strona publiczna).
 * Uzycie: node testy/zgoda-komunikat-po-polsku-test.js [adres]
 */
const { chromium } = require('playwright-core');
const { wymagajChromium } = require('./lib/chromium');
const BAZA = process.argv[2] || 'http://localhost:8890';
const PRODUKT = BAZA + '/sklep/produkt/chatbot-live/';

let bledy = 0;
function sprawdz(opis, warunek, szczegol) {
  console.log((warunek ? '  OK   ' : '  BLAD ') + opis);
  if (szczegol) { console.log('       ' + szczegol); }
  if (!warunek) { bledy++; }
}

(async () => {
  const b = await chromium.launch({ executablePath: wymagajChromium() });
  const p = await b.newPage({ viewport: { width: 1280, height: 900 } });
  p.setDefaultTimeout(60000); // 2 vCPU: domyslne 30 s bywa za malo pod pelna linia bazowa
  /* Zaden test nie ma prawa wyjsc na prawdziwego Stripe'a. */
  await p.route('**://*.stripe.com/**', (r) => r.abort());

  console.log('[1] pole zgody istnieje, jest puste i wymagane');
  await p.goto(PRODUKT, { waitUntil: 'load' });
  const pole = await p.$('input[name="zgoda"]');
  sprawdz('pole zgody jest na stronie produktu', pole !== null);
  if (!pole) { await b.close(); process.exit(1); }
  const stan = await pole.evaluate((e) => ({ checked: e.checked, required: e.required }));
  sprawdz('zgoda nie jest zaznaczona z gory', stan.checked === false);
  sprawdz('zgoda jest polem wymaganym', stan.required === true);

  console.log('[2] proba zakupu BEZ zgody');
  const przed = p.url();
  await p.click('button[type="submit"], input[type="submit"]');
  await p.waitForTimeout(1500);

  console.log('[3] blokada trzyma — to jest obietnica wazniejsza niz jezyk');
  sprawdz('nie wyszlismy ze strony produktu', p.url() === przed, 'url: ' + p.url());

  console.log('[4] komunikat dla czlowieka jest po polsku');
  const kom = await pole.evaluate((e) => e.validationMessage || '');
  sprawdz('komunikat nie jest pusty', kom.trim().length > 0, 'komunikat: "' + kom + '"');
  sprawdz('komunikat NIE jest natywnym dymkiem po angielsku',
    !/please|check this box|proceed|fill out this field/i.test(kom),
    'komunikat: "' + kom + '"');
  sprawdz('komunikat jest po polsku (ma polskie znaki albo polskie slowo kluczowe)',
    /[ąćęłńóśźż]/i.test(kom) || /\b(zaznacz|zgod|musisz|potwierdz)/i.test(kom),
    'komunikat: "' + kom + '"');

  console.log('[5] po zaznaczeniu zgody komunikat znika (nie zostaje przyklejony)');
  await pole.check();
  const poZaznaczeniu = await pole.evaluate((e) => { e.checkValidity(); return e.validationMessage || ''; });
  sprawdz('zaznaczona zgoda nie zostawia komunikatu bledu', poZaznaczeniu.trim() === '',
    'komunikat: "' + poZaznaczeniu + '"');

  await b.close();
  console.log('\n' + (bledy ? 'NIEPOWODZENIE: ' + bledy + ' krokow nie przeszlo' : 'WSZYSTKIE KROKI PRZESZLY'));
  process.exit(bledy ? 1 : 0);
})().catch((e) => { console.error(e); process.exit(1); });
