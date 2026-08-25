/* Sonda pigułki sticky na stronie produktu — SKALIBROWANA (pada na kodzie sprzed naprawy).
 *
 * Co psuło się w kodzie: pigułkę centruje `transform: translateX(-50%)` bez !important,
 * a dwie reguły hurtowe `.sklepag-wyspa > * { transform: none !important }` (bezpiecznik
 * `sklepag-odslon` po 2,5 s bez skryptu; „ogranicz ruch" włączone przy otwartej stronie)
 * zdejmowały je — pigułka stawała lewą krawędzią na środku ekranu (środek +120 px).
 *
 * Trzy przebiegi (desktop 1440 px, przewinięte poza hero):
 *   A. bez opóźnienia — kontrola (ma przechodzić przed i po naprawie),
 *   B. front-ruch.js opóźniony 3,5 s → inline w <head> dokłada `sklepag-odslon`,
 *   C. `prefers-reduced-motion: reduce` włączone PO załadowaniu strony.
 * Warunek: pigułka fixed, widoczna (klasa stanu), opacity 1, środek w osi okna (±2 px).
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

async function proba(browser, nazwa, opoznienieMs, reducedPo) {
  const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
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
    };
  });
  await ctx.close();
  const ok = !!w && !w.hidden && w.widoczna && w.position === 'fixed' && w.opacity === '1' && Math.abs(w.srodek_px) <= 2;
  sprawdz(nazwa, ok, JSON.stringify(w));
}

(async () => {
  const browser = await chromium.launch({ executablePath: wymagajChromium() });
  try {
    await proba(browser, 'A. bez opóźnienia (kontrola): pigułka fixed i wyśrodkowana', 0, false);
    await proba(browser, 'B. front-ruch.js opóźniony 3,5 s (bezpiecznik sklepag-odslon): pigułka nadal wyśrodkowana', 3500, false);
    await proba(browser, 'C. „ogranicz ruch" włączone PO załadowaniu: pigułka nadal wyśrodkowana', 0, true);
  } finally {
    await browser.close();
  }
  console.log(bledy ? 'BLEDY: ' + bledy : 'WSZYSTKO OK');
  process.exit(bledy ? 1 : 0);
})().catch((e) => { console.error(e); process.exit(2); });
