/* Renderuje diagramy z każdej wtyczki (DIAGRAM-PRZEPLYWU.svg) do PNG @2x dla paczki klienta.
   Źródłem jest SVG w katalogu wtyczki — wersjonowany, czytelny w diffie; PNG powstaje z niego
   jednym poleceniem, więc nigdy nie rozjeżdża się z rysunkiem, który klient ma w zipie.
   Wymaga playwright-core i chromium z ms-playwright (jak reszta testów przeglądarkowych).
   Użycie: node dokumentacja/render-diagramy.js            → dokumentacja/diagramy/<nazwa>.png
   EXIT 0 = wszystkie diagramy zapisane · 1 = któregoś nie udało się wyrenderować · 2 = brak chromium */
const { chromium } = require('playwright-core');
const { wymagajChromium } = require('../testy/lib/chromium');
const fs = require('fs');
const path = require('path');

const KORZEN = path.resolve(__dirname, '..');
const CEL = path.join(KORZEN, 'dokumentacja', 'diagramy');
// nazwa pliku PNG opisuje TREŚĆ rysunku (tytuł z <title> SVG), nie nazwę techniczną
const MAPA = [
  ['wtyczki/sklep-agentow/DIAGRAM-PRZEPLYWU.svg',    '01-sklep-droga-zakupu.png'],
  ['wtyczki/chatbot-live/DIAGRAM-PRZEPLYWU.svg',     '02-chatbot-przeplyw-rozmowy.png'],
  ['wtyczki/segregator-maila/DIAGRAM-PRZEPLYWU.svg', '03-segregator-przeplyw-poczty.png'],
];

(async () => {
  fs.mkdirSync(CEL, { recursive: true });
  const b = await chromium.launch({ executablePath: wymagajChromium(), args: ['--lang=pl-PL'] });
  const k = await b.newContext({ deviceScaleFactor: 2, locale: 'pl-PL', colorScheme: 'light' });
  const p = await k.newPage();
  let bledy = 0;
  for (const [zrodlo, wynik] of MAPA) {
    const plik = path.join(KORZEN, zrodlo);
    if (!fs.existsSync(plik)) { console.error('BRAK ŹRÓDŁA:', zrodlo); bledy++; continue; }
    const svg = fs.readFileSync(plik, 'utf8');
    const m = svg.match(/viewBox="0 0 (\d+) (\d+)"/);
    const w = m ? parseInt(m[1], 10) : 1400, h = m ? parseInt(m[2], 10) : 900;
    await p.setViewportSize({ width: w, height: h });
    await p.setContent(`<!doctype html><html lang="pl"><body style="margin:0;background:#fff">${svg}</body></html>`);
    await p.waitForTimeout(200);
    const el = await p.$('svg');
    if (!el) { console.error('BRAK <svg> po wczytaniu:', zrodlo); bledy++; continue; }
    await el.screenshot({ path: path.join(CEL, wynik), omitBackground: false });
    const rozmiar = fs.statSync(path.join(CEL, wynik)).size;
    console.log(`DIAGRAM: ${wynik} (${w}x${h} @2x, ${Math.round(rozmiar / 1024)} KB) ← ${zrodlo}`);
  }
  await b.close();
  console.log(bledy ? `NIE WYSZŁO: ${bledy}` : `GOTOWE — ${MAPA.length} diagramy w dokumentacja/diagramy/`);
  process.exit(bledy ? 1 : 0);
})().catch((e) => { console.error(e.message); process.exit(2); });
