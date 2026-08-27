/* Kafel z nagraniem ma być OSIĄGALNY Z KLAWIATURY i dać się ZATRZYMAĆ (audyt K7, A5 + C2).
 *
 * Znalezisko audytora: przycisk „Odtwórz nagranie" miał `hidden` (czyli `display:none`) dopóki
 * przeglądarka nie odrzuciła autoodtwarzania. Element `display:none` wypada z kolejności Tab,
 * więc przy DZIAŁAJĄCYM autoplay nagranie nie miało ani widocznego sygnału (A5), ani żadnej
 * ścieżki klawiaturowej (C2). Audytor przeszedł pełną sekwencję 32 elementów tabbable na
 * stronie produktu i kafla wideo nie było tam ani razu.
 *
 * Ten test mierzy TRZY rzeczy, bo naprawa bez którejkolwiek z nich jest połowiczna:
 *   1. przycisk jest w kolejności Tab (dochodzi do niego sam Tab, bez myszy),
 *   2. Enter na nim ZATRZYMUJE grające nagranie (kryterium 2.2.2 — da się zatrzymać ruch),
 *   3. kolejny Enter je wznawia, a etykieta mówi, co się właśnie stanie.
 *
 * ⛔ Test nie ufa `autoplay` — gdy nagranie nie zdąży ruszyć, mówi to wprost i kończy się
 *    kodem 2 (błąd przyrządu), zamiast zaliczyć pauzę, której nie było czego zatrzymać.
 *
 * Wymaga: playwright-core + Chromium (testy/lib/chromium.js).
 * Użycie: node testy/e2e/kafel-wideo-klawiatura.js [adres-bazy] [slug-produktu]
 * Kody wyjścia: 0 = wszystko przeszło · 1 = wada dostępności · 2 = błąd przyrządu.
 */
const { chromium } = require('playwright-core');
const { wymagajChromium } = require('../lib/chromium');

const argv = process.argv.slice(2);
const BAZA = argv.find((a) => !a.startsWith('--')) || 'http://localhost:8890';
const SLUG = argv.filter((a) => !a.startsWith('--'))[1] || 'chatbot-live';
const ADRES = `${BAZA.replace(/\/$/, '')}/sklep/produkt/${SLUG}/`;
const MAX_TABOW = 60;

let bledy = 0;
const ok = (opis) => console.log('  OK   ' + opis);
const zle = (opis, szczegol) => { console.log('  BLAD ' + opis); if (szczegol) console.log('       ' + szczegol); bledy += 1; };
const przyrzad = (powod) => { console.error('PRZYRZAD: ' + powod); process.exit(2); };

(async () => {
  await wymagajChromium();
  const browser = await chromium.launch();
  const strona = await browser.newPage({ viewport: { width: 1440, height: 900 } });
  console.log('=== kafel-wideo-klawiatura.js — ' + ADRES + ' ===');

  const odp = await strona.goto(ADRES, { waitUntil: 'domcontentloaded' });
  if (!odp || odp.status() >= 400) { await browser.close(); przyrzad('strona produktu odpowiedziala kodem ' + (odp ? odp.status() : 'brak')); }

  const kafel = strona.locator('.sklepag-kafel--wideo').first();
  if (await kafel.count() === 0) { await browser.close(); przyrzad('na tej stronie nie ma kafla z nagraniem — nie ma czego mierzyc'); }
  await kafel.scrollIntoViewIfNeeded();

  const przycisk = kafel.locator('.sklepag-kafel-odtworz');
  if (await przycisk.count() === 0) { await browser.close(); przyrzad('kafel nie ma przycisku .sklepag-kafel-odtworz'); }

  // 1. WIDOCZNOSC (A5) — element ma byc realnie widoczny, nie tylko obecny w drzewie.
  const widoczny = await przycisk.isVisible();
  if (widoczny) { ok('przycisk nagrania jest widoczny (nie display:none)'); } else { zle('przycisk nagrania NIE jest widoczny', 'to jest dokladnie stan sprzed naprawy: hidden => display:none'); }

  // 2. KOLEJNOSC TAB (C2) — dochodzimy do niego samym Tabem, jak klawiaturowiec.
  await strona.evaluate(() => document.body.focus());
  let doszedl = false;
  let krokow = 0;
  for (let i = 0; i < MAX_TABOW; i += 1) {
    await strona.keyboard.press('Tab');
    krokow += 1;
    const trafiony = await strona.evaluate(() => !!(document.activeElement && document.activeElement.classList && document.activeElement.classList.contains('sklepag-kafel-odtworz')));
    if (trafiony) { doszedl = true; break; }
  }
  if (doszedl) { ok('Tab dochodzi do przycisku nagrania (po ' + krokow + ' krokach)'); } else { zle('Tab NIE dochodzi do przycisku nagrania w ' + MAX_TABOW + ' krokach', 'klawiaturowiec nie ma zadnej drogi do tego nagrania'); }

  if (doszedl) {
    // 3. ZATRZYMANIE (2.2.2) — najpierw upewniamy sie, ze JEST co zatrzymywac.
    await strona.waitForTimeout(1200);
    const gralo = await kafel.locator('video').evaluate((w) => !w.paused);
    if (!gralo) { await browser.close(); przyrzad('nagranie nie ruszylo samo — nie da sie zmierzyc zatrzymania (uruchom z przegladarka, ktora pozwala na autoplay wyciszonych nagran)'); }

    await strona.keyboard.press('Enter');
    await strona.waitForTimeout(300);
    const stoi = await kafel.locator('video').evaluate((w) => w.paused);
    if (stoi) { ok('Enter na przycisku ZATRZYMUJE nagranie'); } else { zle('Enter nie zatrzymal nagrania', 'kryterium 2.2.2: ruch trwajacy dluzej niz 5 s musi dac sie zatrzymac'); }

    const etykietaPoStopie = (await przycisk.textContent() || '').trim();
    if (/Odtw/i.test(etykietaPoStopie)) { ok('etykieta mowi, co zrobi kolejne nacisniecie: ' + etykietaPoStopie); } else { zle('etykieta po zatrzymaniu nie zapowiada wznowienia', 'widziano: ' + etykietaPoStopie); }

    await strona.keyboard.press('Enter');
    await strona.waitForTimeout(500);
    const znowuGra = await kafel.locator('video').evaluate((w) => !w.paused);
    if (znowuGra) { ok('kolejny Enter WZNAWIA nagranie'); } else { zle('kolejny Enter nie wznowil nagrania'); }
  }

  await browser.close();
  console.log('');
  if (bledy === 0) { console.log('WYNIK: OK — nagranie osiagalne z klawiatury i zatrzymywalne.'); process.exit(0); }
  console.log('WYNIK: BLEDY = ' + bledy);
  process.exit(1);
})().catch((e) => przyrzad(e && e.message ? e.message : String(e)));
