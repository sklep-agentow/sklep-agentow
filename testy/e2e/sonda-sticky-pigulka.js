/* Sonda pigułki sticky na stronie produktu — SKALIBROWANA (pada na kodzie sprzed naprawy).
 *
 * Co psuło się w kodzie: pigułkę centruje `transform: translateX(-50%)` bez !important,
 * a dwie reguły hurtowe `.sklepag-wyspa > * { transform: none !important }` (bezpiecznik
 * `sklepag-odslon` po 2,5 s bez skryptu; „ogranicz ruch" włączone przy otwartej stronie)
 * zdejmowały je — pigułka stawała lewą krawędzią na środku ekranu (środek +120 px).
 *
 * Pięć przebiegów (przewinięte poza hero):
 *   A. desktop 1440 px bez opóźnienia — kontrola (ma przechodzić przed i po naprawie),
 *   B. desktop, front-ruch.js opóźniony 3,5 s → inline w <head> dokłada `sklepag-odslon`,
 *   C. desktop, `prefers-reduced-motion: reduce` włączone PO załadowaniu strony,
 *   D. telefon 390 px bez opóźnienia — pigułka 12 px od LEWEJ (nie wyśrodkowana) i w całości w oknie,
 *   E. telefon 390 px + skrypt opóźniony 3,5 s — jw.
 * Warunek wspólny: pigułka fixed, widoczna (klasa stanu), opacity 1; desktop: środek w osi okna
 * (±2 px); telefon: lewa krawędź 12 px, prawa nie poza oknem.
 * Kod wyjścia 0 = wszystkie OK, 1 = którykolwiek pada, 2 = błąd przyrządu.
 *
 * Użycie: node testy/e2e/sonda-sticky-pigulka.js [adres] [slug]
 *   adres — baza stacku (domyślnie http://localhost:8890), slug — produkt (domyślnie chatbot-live).
 *   SKLEPAG_PRZEKIERUJ_HOST=host — gdy siteurl stacku wskazuje inny host (np. tunel),
 *   żądania do niego idą na adres bazy (pomiar bez pośrednika).
 */
const { chromium } = require('playwright-core');
const { wymagajChromium } = require('../lib/chromium');

const BAZA = process.argv[2] || 'http://localhost:8890';
const SLUG = process.argv[3] || 'chatbot-live';
const URL = BAZA + '/sklep/produkt/' + SLUG + '/';
const PRZEKIERUJ = process.env.SKLEPAG_PRZEKIERUJ_HOST || '';

let bledy = 0;
function sprawdz(opis, warunek, szczegol) {
  console.log((warunek ? '  OK   ' : '  BLAD ') + opis);
  if (szczegol) { console.log('       ' + szczegol); }
  if (!warunek) { bledy++; }
}

/* Telefon (< 40rem): pigułka z założenia NIE jest wyśrodkowana — siedzi 12 px od lewej (prawy róg
   należy do bąbelka czatu) i w całości w oknie. Warunek jest inny niż na desktopie, dlatego osobny. */
const TELEFON = { width: 390, height: 844 };

async function proba(browser, nazwa, opoznienieMs, reducedPo, viewport) {
  const telefon = !!viewport;
  const ctx = await browser.newContext({ viewport: viewport || { width: 1440, height: 900 }, deviceScaleFactor: telefon ? 2 : 1, isMobile: telefon, hasTouch: telefon });
  const page = await ctx.newPage();
  page.setDefaultTimeout(60000);
  await page.route('**/*', async (r) => {
    const u = r.request().url();
    const url = PRZEKIERUJ && u.includes(PRZEKIERUJ) ? u.replace(/https?:\/\/[^/]+/, BAZA) : undefined;
    if (opoznienieMs && u.includes('front-ruch.js')) {
      await new Promise((res) => setTimeout(res, opoznienieMs));
    }
    return r.continue(url ? { url } : {});
  });
  await page.goto(URL, { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(opoznienieMs + 1500);
  await page.evaluate(() => window.scrollTo(0, 1800));
  await page.waitForTimeout(1200);
  if (reducedPo) {
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await page.waitForTimeout(600);
  }
  const w = await page.evaluate(() => {
    const s = document.querySelector('.sklepag-sticky');
    if (!s) { return null; }
    const r = s.getBoundingClientRect();
    const c = getComputedStyle(s);
    return {
      odslon: document.documentElement.classList.contains('sklepag-odslon'),
      js: document.documentElement.classList.contains('sklepag-js'),
      widoczna: s.classList.contains('sklepag-sticky--widoczna'),
      hidden: s.hasAttribute('hidden'),
      position: c.position,
      opacity: c.opacity,
      transform: c.transform,
      srodek_px: Math.round(r.left + r.width / 2 - innerWidth / 2),
      lewa_px: Math.round(r.left),
      prawa_px: Math.round(innerWidth - r.right),
    };
  });
  await ctx.close();
  const baza = !!w && !w.hidden && w.widoczna && w.position === 'fixed' && w.opacity === '1';
  const ok = baza && (telefon ? (w.lewa_px === 12 && w.prawa_px >= 0) : Math.abs(w.srodek_px) <= 2);
  sprawdz(nazwa, ok, JSON.stringify(w));
}

(async () => {
  const browser = await chromium.launch({ executablePath: wymagajChromium() });
  try {
    await proba(browser, 'A. bez opóźnienia (kontrola): pigułka fixed i wyśrodkowana', 0, false);
    await proba(browser, 'B. front-ruch.js opóźniony 3,5 s (bezpiecznik sklepag-odslon): pigułka nadal wyśrodkowana', 3500, false);
    await proba(browser, 'C. „ogranicz ruch" włączone PO załadowaniu: pigułka nadal wyśrodkowana', 0, true);
    await proba(browser, 'D. telefon 390 px: pigułka 12 px od lewej, w całości w oknie', 0, false, TELEFON);
    await proba(browser, 'E. telefon 390 px + front-ruch.js opóźniony 3,5 s: pigułka nadal 12 px od lewej', 3500, false, TELEFON);
  } finally {
    await browser.close();
  }
  console.log(bledy ? 'BLEDY: ' + bledy : 'WSZYSTKO OK');
  process.exit(bledy ? 1 : 0);
})().catch((e) => { console.error(e); process.exit(2); });
