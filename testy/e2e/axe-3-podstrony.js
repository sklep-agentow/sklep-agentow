/* axe-core (WCAG 2.0/2.1 A/AA + best-practice) dla 3 podstron sklepu — desktop 1440 i telefon 390,
 * po wolnym scrollu (sekcje odsłonięte). Wynik: naruszenia per strona (id reguły, waga, liczba węzłów,
 * pierwszy selektor) + JSON. Kod wyjścia 1 przy jakimkolwiek naruszeniu serious/critical, 2 = błąd przyrządu.
 *
 * Pułapka (audyt 25.08): elementy z animacją opacity (np. tagi maili w panelu hero) potrafią dać
 * fałszywy „color-contrast" z klatki w pół przejścia — powtórz z `--bez-ruchu` (prefers-reduced-motion:
 * reduce, panel w stanie końcowym); jeśli naruszenie znika, to animacja, nie kolory.
 *
 * Wymaga: playwright-core + Chromium (testy/lib/chromium.js), axe-core (npm -g lub node_modules).
 * Użycie: node testy/e2e/axe-3-podstrony.js [adres-bazy] [--bez-ruchu]
 *   SKLEPAG_PRZEKIERUJ_HOST=host — gdy siteurl stacku wskazuje inny host (np. tunel).
 */
const { chromium } = require('playwright-core');
const { wymagajChromium } = require('../lib/chromium');
const path = require('path');
const fs = require('fs');

const argv = process.argv.slice(2);
const BEZ_RUCHU = argv.includes('--bez-ruchu');
const BAZA = argv.find((a) => !a.startsWith('--')) || 'http://localhost:8890';
const PRZEKIERUJ = process.env.SKLEPAG_PRZEKIERUJ_HOST || '';
const KANDYDACI_AXE = [
  process.env.AXE_CORE_PATH,
  path.join(process.cwd(), 'node_modules', 'axe-core', 'axe.min.js'),
  '/usr/lib/node_modules/axe-core/axe.min.js',
  '/usr/local/lib/node_modules/axe-core/axe.min.js',
].filter(Boolean);
const AXE = KANDYDACI_AXE.find((p) => fs.existsSync(p));
if (!AXE) { console.error('brak axe.min.js — npm install -g axe-core albo AXE_CORE_PATH=/sciezka/axe.min.js'); process.exit(2); }

const STRONY = [['sklep', '/sklep/'], ['chatbot', '/sklep/produkt/chatbot-live/'], ['segregator', '/sklep/produkt/segregator-maila/']];
const WYNIK = {};
let powazne = 0;

(async () => {
  const browser = await chromium.launch({ executablePath: wymagajChromium() });
  try {
    for (const [nazwa, sciezka] of STRONY) {
      for (const [tryb, vp, mobile] of [['desktop', { width: 1440, height: 900 }, false], ['mobile', { width: 390, height: 844 }, true]]) {
        const ctx = await browser.newContext({ viewport: vp, isMobile: mobile, hasTouch: mobile, deviceScaleFactor: mobile ? 2 : 1, reducedMotion: BEZ_RUCHU ? 'reduce' : 'no-preference' });
        const page = await ctx.newPage();
        page.setDefaultTimeout(60000);
        await page.route('**/*', (r) => { const u = r.request().url(); return PRZEKIERUJ && u.includes(PRZEKIERUJ) ? r.continue({ url: u.replace(/https?:\/\/[^/]+/, BAZA) }) : r.continue(); });
        await page.goto(BAZA + sciezka, { waitUntil: 'networkidle' });
        const wys = await page.evaluate(() => document.documentElement.scrollHeight);
        for (let y = 0; y < wys; y += 400) { await page.evaluate((yy) => window.scrollTo(0, yy), y); await page.waitForTimeout(120); }
        await page.evaluate(() => window.scrollTo(0, 0));
        await page.waitForTimeout(800);
        await page.addScriptTag({ path: AXE });
        const r = await page.evaluate(async () => {
          const res = await window.axe.run(document, { runOnly: { type: 'tag', values: ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'best-practice'] } });
          return res.violations.map((v) => ({ id: v.id, impact: v.impact, tags: v.tags.filter((t) => t.startsWith('wcag')).join(','), help: v.help, wezly: v.nodes.length, pierwszy: (v.nodes[0] && v.nodes[0].target.join(' ')) || '' }));
        });
        WYNIK[nazwa + '-' + tryb] = r;
        console.log(`=== ${nazwa} ${tryb}${BEZ_RUCHU ? ' (bez ruchu)' : ''}: ${r.length} naruszeń`);
        for (const v of r) {
          if (v.impact === 'serious' || v.impact === 'critical') { powazne++; }
          console.log(`  [${v.impact}] ${v.id} (${v.tags}) ×${v.wezly} — ${v.help} — np. ${v.pierwszy.slice(0, 90)}`);
        }
        await ctx.close();
      }
    }
  } finally {
    await browser.close();
  }
  const plik = path.join(process.env.AXE_WYNIK_DIR || '/tmp', 'axe-wynik' + (BEZ_RUCHU ? '-bez-ruchu' : '') + '.json');
  fs.writeFileSync(plik, JSON.stringify(WYNIK, null, 1));
  console.log(`POWAŻNE (serious/critical): ${powazne} · JSON: ${plik}`);
  process.exit(powazne ? 1 : 0);
})().catch((e) => { console.error(e); process.exit(2); });
