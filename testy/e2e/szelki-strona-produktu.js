/* Szelki strony produktu (ChatBot LIVE i Segregator maila) — kontrole przeglądarkowe tego, czego
 * zrzut nie pokaże: kolejność ładowania, bezpieczniki, pigułka, kotwica, lightbox, nagranie, tryby.
 *
 *   1. front-ruch.js ZABLOKOWANY → po 3,2 s bezpiecznik `sklepag-odslon` odsłania wszystko,
 *   2. front-ruch.js OPÓŹNIONY 0,9 s → hero ukryte w pierwszej klatce, potem wjeżdża; ostatnia faza
 *      poza ekranem zostaje ukryta (kaskada NIE zgaszona), po doscrollowaniu wjeżdża,
 *   3. pigułka sticky po minięciu hero: fixed, 14 px od dołu, wyśrodkowana, widoczna,
 *   4. kotwica „Kup teraz" w hero → sekcja ceny pod nagłówkiem gospodarza, cena w kadrze, ramka błyska,
 *   5. lightbox: dialog z podpisem, scroll zablokowany, fokus na „Zamknij", Tab i Shift+Tab NIE
 *      wychodzą poza dialog (pułapka fokusu), Esc zamyka i oddaje fokus kaflowi,
 *   6. nagranie w kadrze gra, plakat zgaszony,
 *   7. hero produktu = panel na żywo z wierszem chipów (sygnatura produktu),
 *   8. „ogranicz ruch" → nic nie ukryte, nagranie stoi na plakacie,
 *   9. bez JavaScriptu → wszystko widoczne,
 *  10. lista /sklep/ bez wiersza chipów (panel listy nie dostaje elementów strony produktu).
 *
 * Kod wyjścia 0 = wszystko OK, 1 = którakolwiek kontrola pada, 2 = błąd przyrządu.
 * Użycie: node testy/e2e/szelki-strona-produktu.js [adres] [slug] [silnik]
 *   silnik: chromium (domyślnie), firefox albo msedge — cel L6-03 mapy wymaga wszystkich
 *   trzech, bo kryterium GOTOWE #11 wymienia Chrome, Edge i Firefox z nazwy.
 *   slug: chatbot-live (domyślnie) albo segregator-maila. SKLEPAG_PRZEKIERUJ_HOST=host — gdy
 *   siteurl stacku wskazuje inny host (np. tunel), żądania do niego idą na adres bazy.
 */
const { chromium, firefox } = require('playwright-core');
const { wymagajChromium } = require('../lib/chromium');

const BAZA = process.argv[2] || 'http://localhost:8890';
const SLUG = process.argv[3] || 'chatbot-live';
const SILNIK = (process.argv[4] || 'chromium').toLowerCase();
/* ⛔ NAZWA SILNIKA MUSI BYC ZNANA. Bez tego kazda inna wartosc — literowka `edge`, `webkit`,
   cokolwiek — wpadala w ostatnia galaz wyboru nizej, uruchamiala chromium, wypisywala
   `WSZYSTKO OK` i konczyla EXIT=0. Czlowiek zapisywal wtedy w tabeli „Edge ✅”, majac pomiar
   z chromium. To ta sama klasa klamiacego straznika, ktora ten przyrzad mial zamknac dla
   `msedge` — zamknieta wtedy dla JEDNEGO literalu, nie dla calej klasy (recenzja WAZNA 4). */
const SILNIKI_ZNANE = [ 'chromium', 'firefox', 'msedge' ];
if ( ! SILNIKI_ZNANE.includes( SILNIK ) ) {
  console.error( 'BLAD PRZYRZADU: nieznany silnik „' + SILNIK + '”. Znane: ' + SILNIKI_ZNANE.join( ', ' ) + '.' );
  process.exit( 2 );
}
const URL = BAZA + '/sklep/produkt/' + SLUG + '/';
const PRZEKIERUJ = process.env.SKLEPAG_PRZEKIERUJ_HOST || '';
const OSTATNIA_FAZA = '.sklepag-fazy > .sklepag-faza:last-child';

let bledy = 0;
function sprawdz(opis, warunek, szczegol) {
  console.log((warunek ? '  OK   ' : '  BLAD ') + opis);
  if (szczegol) { console.log('       ' + szczegol); }
  if (!warunek) { bledy++; }
}

async function kontekst(browser, opcje) {
  const ctx = await browser.newContext(Object.assign({ viewport: { width: 1440, height: 900 } }, opcje || {}));
  const page = await ctx.newPage();
  page.setDefaultTimeout(60000);
  await page.route('**/*', (r) => {
    const u = r.request().url();
    if (PRZEKIERUJ && u.includes(PRZEKIERUJ)) {
      return r.continue({ url: u.replace(/https?:\/\/[^/]+/, BAZA) });
    }
    return r.continue();
  });
  return { ctx, page };
}

const op = (page, sel) => page.$eval(sel, (e) => getComputedStyle(e).opacity);

(async () => {
  /* Edge idzie kanalem `msedge` (prawdziwa przegladarka Microsoftu z systemu), a nie
     chromium z Playwrighta — kryterium GOTOWE #11 wymienia Edge z nazwy, wiec proxy przez
     wspolny silnik Blink byloby argumentem, a nie pomiarem. Brak przegladarki w systemie
     konczy sie kodem 2 (blad przyrzadu), nie cichym zjazdem na chromium — inaczej zielone
     znaczyloby cos innego, niz mowi nazwa silnika. */
  const browser = 'firefox' === SILNIK
    ? await firefox.launch()
    : 'msedge' === SILNIK
      ? await chromium.launch({ channel: 'msedge' })
      : await chromium.launch({ executablePath: wymagajChromium() });
  try {
    // 1. skrypt zablokowany
    let { ctx, page } = await kontekst(browser);
    await page.route('**/front-ruch.js*', (r) => r.abort());
    await page.goto(URL, { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(3200);
    let odslon = await page.evaluate(() => document.documentElement.classList.contains('sklepag-odslon'));
    let a = [await op(page, '.sklepag-hero-tytul'), await op(page, '.sklepag-hero-wizual'), await op(page, OSTATNIA_FAZA + ' .sklepag-kafel')];
    sprawdz('1. skrypt zablokowany: bezpiecznik odsłania wszystko', odslon && a.every((o) => o === '1'), `odslon=${odslon} opacity=${a.join(',')}`);
    await ctx.close();

    // 2. skrypt opóźniony 0,9 s
    ({ ctx, page } = await kontekst(browser));
    await page.route('**/front-ruch.js*', async (r) => { await new Promise((res) => setTimeout(res, 900)); return r.continue(); });
    await page.goto(URL, { waitUntil: 'domcontentloaded' });
    const heroStart = await op(page, '.sklepag-hero-tytul');
    await page.waitForTimeout(3200);
    odslon = await page.evaluate(() => document.documentElement.classList.contains('sklepag-odslon'));
    const heroPo = await op(page, '.sklepag-hero-tytul');
    const fazaPrzed = await op(page, OSTATNIA_FAZA + ' .sklepag-kafel');
    sprawdz('2a. skrypt opóźniony: hero ukryte w 1. klatce, potem wjeżdża', heroStart === '0' && heroPo === '1', `start=${heroStart} po=${heroPo}`);
    sprawdz('2b. skrypt opóźniony: ostatnia faza poza ekranem NIE odsłonięta po 3,2 s', !odslon && fazaPrzed === '0', `odslon=${odslon} faza=${fazaPrzed}`);
    await page.$eval(OSTATNIA_FAZA, (e) => e.scrollIntoView({ block: 'center' }));
    await page.waitForTimeout(1500);
    const fazaPo = await op(page, OSTATNIA_FAZA + ' .sklepag-kafel');
    sprawdz('2c. ostatnia faza wjeżdża po doscrollowaniu', fazaPo === '1', `faza=${fazaPo}`);

    // 3. pigułka sticky
    const sticky = await page.evaluate(() => {
      const s = document.querySelector('.sklepag-sticky');
      const r = s.getBoundingClientRect();
      const c = getComputedStyle(s);
      return { hidden: s.hasAttribute('hidden'), position: c.position, opacity: c.opacity, dol: Math.round(innerHeight - r.bottom), srodek: Math.round(r.left + r.width / 2 - innerWidth / 2) };
    });
    sprawdz('3. sticky: fixed przy dole ekranu, wycentrowana, widoczna', !sticky.hidden && sticky.position === 'fixed' && sticky.opacity === '1' && sticky.dol === 14 && Math.abs(sticky.srodek) < 3, JSON.stringify(sticky));

    // 4. kotwica „Kup teraz"
    await page.evaluate(() => window.scrollTo(0, 0));
    await page.waitForTimeout(300);
    await page.click('.sklepag-hero .sklepag-kup-kotwica');
    await page.waitForTimeout(2200); // gospodarz ma scroll-behavior: smooth
    const kotwica = await page.evaluate(() => {
      const s = document.querySelector('.sklepag-sekcja-zakup');
      const f = document.querySelector('.sklepag-zakup');
      return { hash: location.hash, gora: Math.round(s.getBoundingClientRect().top), cenaWidoczna: document.querySelector('.sklepag-sekcja-zakup .sklepag-cena').getBoundingClientRect().top > 60, animacja: getComputedStyle(f).animationName };
    });
    sprawdz('4. kotwica Kup teraz: sekcja ceny pod nagłówkiem, cena w kadrze, ramka błyska', kotwica.hash === '#sklepag-zakup' && kotwica.gora >= 60 && kotwica.gora <= 140 && kotwica.cenaWidoczna && kotwica.animacja === 'sklepag-zakup-blysk', JSON.stringify(kotwica));

    // 5. lightbox + pułapka fokusu
    const link = await page.$(OSTATNIA_FAZA + ' .sklepag-lightbox');
    await link.click();
    await page.waitForTimeout(400);
    const dialog = await page.$('.sklepag-lightbox-tlo[role="dialog"]');
    const podpis = dialog ? await dialog.$eval('figcaption', (e) => e.textContent) : '';
    const zablokowany = await page.evaluate(() => document.documentElement.classList.contains('sklepag-lightbox-otwarty'));
    const fokusNaZamknij = await page.evaluate(() => document.activeElement && document.activeElement.classList.contains('sklepag-lightbox-zamknij'));
    sprawdz('5a. lightbox: dialog z podpisem, scroll zablokowany, fokus na zamknięciu', !!dialog && podpis.length > 5 && zablokowany && fokusNaZamknij, `podpis="${podpis}" lock=${zablokowany} fokus=${fokusNaZamknij}`);
    await page.keyboard.press('Tab');
    const poTab = await page.evaluate(() => !!document.activeElement.closest('.sklepag-lightbox-tlo'));
    await page.keyboard.press('Shift+Tab');
    const poShiftTab = await page.evaluate(() => !!document.activeElement.closest('.sklepag-lightbox-tlo'));
    sprawdz('5b. lightbox: Tab i Shift+Tab nie wychodzą poza dialog', poTab && poShiftTab, `tab=${poTab} shiftTab=${poShiftTab}`);
    await page.keyboard.press('Escape');
    await page.waitForTimeout(300);
    const dialogPo = await page.$('.sklepag-lightbox-tlo');
    const fokusWrocil = await page.evaluate(() => document.activeElement && document.activeElement.classList.contains('sklepag-lightbox'));
    const odblokowany = await page.evaluate(() => !document.documentElement.classList.contains('sklepag-lightbox-otwarty'));
    sprawdz('5c. lightbox: Esc zamyka, fokus wraca do kafla, scroll odblokowany', !dialogPo && fokusWrocil && odblokowany, `dialog=${!!dialogPo} fokus=${fokusWrocil} odblok=${odblokowany}`);

    // 6. nagranie (Chromium pauzuje autoplay poza kadrem — mierzymy W kadrze, jak widzi to człowiek)
    await page.$eval('.sklepag-kafel--wideo', (e) => e.scrollIntoView({ block: 'center' }));
    await page.waitForTimeout(1500);
    const gra = await page.evaluate(() => {
      const v = document.querySelector('.sklepag-kafel--wideo video');
      return { klasa: !!v.closest('.sklepag-kafel--gra'), paused: v.paused, plakat: !!v.getAttribute('poster') };
    });
    sprawdz('6. nagranie w kadrze gra, plakat zgaszony', gra.klasa && !gra.paused && gra.plakat, JSON.stringify(gra));

    // 7. hero = panel na żywo z chipami
    const chipy = await page.$$eval('.sklepag-hero-wizual--panel .sklepag-panel-baza .sklepag-panel-chip', (e) => e.length);
    sprawdz('7. hero: panel na żywo z wierszem chipów (sygnatura produktu)', chipy >= 3, `chipy=${chipy}`);
    await ctx.close();

    // 8. reduced-motion
    ({ ctx, page } = await kontekst(browser, { reducedMotion: 'reduce' }));
    await page.goto(URL, { waitUntil: 'networkidle' });
    await page.waitForTimeout(800);
    const js = await page.evaluate(() => document.documentElement.classList.contains('sklepag-js'));
    const rm = await page.evaluate((sel) => sel.map((s) => getComputedStyle(document.querySelector(s)).opacity), ['.sklepag-hero-tytul', '.sklepag-hero-wizual', OSTATNIA_FAZA + ' .sklepag-kafel', '.sklepag-funkcja', '.sklepag-faq-blok']);
    const wideoStoi = await page.evaluate(() => document.querySelector('.sklepag-kafel--wideo video').paused);
    sprawdz('8. ogranicz ruch: nic nie ukryte, nagranie stoi na plakacie', !js && rm.every((o) => o === '1') && wideoStoi, `js=${js} opacity=${rm.join(',')} paused=${wideoStoi}`);
    await ctx.close();

    // 9. bez JS
    ({ ctx, page } = await kontekst(browser, { javaScriptEnabled: false }));
    await page.goto(URL, { waitUntil: 'load' });
    const bezJs = await page.evaluate((sel) => sel.map((s) => { const e = document.querySelector(s); return e ? getComputedStyle(e).opacity : 'brak'; }), ['.sklepag-hero-tytul', '.sklepag-hero-wizual', OSTATNIA_FAZA + ' .sklepag-kafel', '.sklepag-faza-kafle', '.sklepag-final-duet']);
    sprawdz('9. bez JavaScriptu: wszystko widoczne', bezJs.every((o) => o === '1'), bezJs.join(','));
    await ctx.close();

    // 10. lista bez chipów (panel listy = bez elementów strony produktu)
    ({ ctx, page } = await kontekst(browser));
    await page.goto(BAZA + '/sklep/', { waitUntil: 'domcontentloaded' });
    const lista = await page.evaluate(() => ({ panele: document.querySelectorAll('.sklepag-panel').length, baza: document.querySelectorAll('.sklepag-panel-baza').length }));
    sprawdz('10. lista /sklep/: jeden panel, zero wierszy chipów', lista.panele === 1 && lista.baza === 0, JSON.stringify(lista));
    await ctx.close();
  } finally {
    await browser.close();
  }
  console.log(bledy ? 'BLEDY: ' + bledy : 'WSZYSTKO OK');
  process.exit(bledy ? 1 : 0);
})().catch((e) => { console.error(e); process.exit(2); });
