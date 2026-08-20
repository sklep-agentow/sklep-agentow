/* Pomiar RAMEK wszystkich tekstów w SVG prawdziwą przeglądarką (getBBox).
 *
 * Po co osobno od kontroli prostokątów: SVG NIE zawija tekstu. Element <text>
 * z poprawną współrzędną x rozciąga się dokładnie tyle, ile zajmą glify —
 * i cicho wychodzi poza kadr. Sprawdzanie samego punktu startowego tego NIE
 * łapie (tak przeszła urwana stopka w pierwszej wersji diagramu).
 *
 * Sprawdza dwie rzeczy dla każdego <text> i <tspan>:
 *   1) czy ramka mieści się w viewBox,
 *   2) czy tekst mieści się w prostokącie, w którym się zaczyna (jeśli w jakimś).
 *
 * Wymaga playwright-core. Uruchamiać Z KATALOGU REPO:
 *   node testy/svg-pomiar-tekstow.js [plik.svg] [margines-w-boksie]
 * Kod wyjścia 0 = wszystko się mieści, 1 = coś wystaje.
 */
const { chromium } = require('playwright-core');
const path = require('path');

const PLIK = process.argv[2] || 'wtyczki/chatbot-live/DIAGRAM-PRZEPLYWU.svg';
const MARGINES = Number(process.argv[3] || 12); // zapas wewnątrz prostokąta

(async () => {
  const b = await chromium.launch({
    executablePath: '/root/.cache/ms-playwright/chromium-1234/chrome-linux64/chrome',
  });
  const p = await b.newPage();
  await p.goto('file://' + path.resolve(PLIK), { waitUntil: 'load' });

  const wynik = await p.evaluate((margines) => {
    const svg = document.querySelector('svg');
    const vb = svg.viewBox.baseVal;
    const W = vb.width, H = vb.height;

    const prostokaty = [...svg.querySelectorAll('rect')]
      .map((r) => ({
        x: r.x.baseVal.value, y: r.y.baseVal.value,
        w: r.width.baseVal.value, h: r.height.baseVal.value,
      }))
      .filter((r) => !(r.w >= W && r.h >= H)); // pomijamy tło

    const zle = [];
    const wszystkie = [];
    for (const el of svg.querySelectorAll('text, tspan')) {
      const t = (el.textContent || '').trim();
      if (!t) { continue; }
      const bb = el.getBBox();
      const prawa = bb.x + bb.width, dol = bb.y + bb.height;
      const wpis = { tekst: t.slice(0, 46), pelny: t, x: Math.round(bb.x), prawa: Math.round(prawa), dol: Math.round(dol), szer: Math.round(bb.width) };
      const st = getComputedStyle(el);
      wpis.font = parseFloat(st.fontSize);
      wpis.mono = /mono/i.test(st.fontFamily) ? 1 : 0;
      wpis.bold = (parseInt(st.fontWeight, 10) || 400) >= 600 ? 1 : 0;
      wszystkie.push(wpis);

      if (bb.x < 0 || bb.y < 0 || prawa > W || dol > H) {
        zle.push({ ...wpis, powod: 'poza viewBox ' + W + 'x' + H });
        continue;
      }
      // W którym prostokącie zaczyna się ten tekst?
      const boks = prostokaty.find((r) => bb.x >= r.x && bb.x <= r.x + r.w && bb.y >= r.y && bb.y <= r.y + r.h);
      if (boks && prawa > boks.x + boks.w - margines) {
        zle.push({ ...wpis, powod: 'wychodzi z ramki (prawa krawedz boksu ' + Math.round(boks.x + boks.w) + ')' });
      }
    }
    return { W, H, ile: wszystkie.length, zle, wszystkie, najszersze: [...wszystkie].sort((a, c) => c.prawa - a.prawa).slice(0, 5) };
  }, MARGINES);

  await b.close();

  if (process.env.DUMP) {
    for (const w of wynik.wszystkie) { console.log(w.font + '\t' + w.mono + '\t' + w.bold + '\t' + w.szer + '\t' + w.pelny); }
    process.exit(0);
  }
  console.log('plik: ' + PLIK);
  console.log('viewBox: ' + wynik.W + ' x ' + wynik.H);
  console.log('zmierzonych tekstow (text + tspan): ' + wynik.ile);
  console.log('\nnajdalej siegajace ramki:');
  for (const w of wynik.najszersze) {
    console.log('  prawa=' + String(w.prawa).padStart(5) + '  szer=' + String(w.szer).padStart(4) + '  "' + w.tekst + '"');
  }
  if (wynik.zle.length) {
    console.log('\nPRZEPELNIENIA (' + wynik.zle.length + '):');
    for (const z of wynik.zle) {
      console.log('  x=' + z.x + ' prawa=' + z.prawa + ' dol=' + z.dol + '  [' + z.powod + ']');
      console.log('     "' + z.tekst + '"');
    }
    console.log('\nNIEPOWODZENIE: ' + wynik.zle.length + ' tekstow nie miesci sie');
    process.exit(1);
  }
  console.log('\nWSZYSTKIE TEKSTY SIE MIESZCZA');
  process.exit(0);
})().catch((e) => { console.error(e); process.exit(1); });
