/* SONDA E7 — strona "Dziekujemy" obiecuje "(strona odswieza status sama)".
   Mierzy: ile razy naprawde odpytuje status i co stoi na ekranie PO tym, jak przestanie. */
const { chromium } = require('playwright-core');
const { wymagajChromium } = require('../../testy/lib/chromium');
const BAZA = process.env.SKLEPAG_BAZA || 'http://localhost:8890';
const SESJA = 'cs_test_sonda_audytu_' + Date.now();

(async () => {
  const b = await chromium.launch({ executablePath: wymagajChromium() });
  const p = await b.newPage();
  let odpytania = 0, ostatnie = 0;
  p.on('request', (r) => {
    if (r.url().includes('sklepag/v1/status')) { odpytania += 1; ostatnie = Date.now(); }
  });
  const start = Date.now();
  await p.goto(BAZA + '/dziekujemy-za-zakup/?sesja=' + SESJA, { waitUntil: 'load', timeout: 45000 });

  const pomiar = async (etykieta) => {
    const t = await p.locator('.sklepag-status').first().textContent().catch(() => '(brak elementu)');
    console.log(`[${String(Math.round((Date.now() - start) / 1000)).padStart(3)} s] ${etykieta}: odpytan=${odpytania} · ekran: "${(t || '').trim()}"`);
  };
  await p.waitForTimeout(10000);  await pomiar('po 10 s');
  await p.waitForTimeout(50000);  await pomiar('po 60 s');
  await p.waitForTimeout(60000);  await pomiar('po 120 s');
  await p.waitForTimeout(45000);  await pomiar('po 165 s');
  const cisza = Math.round((Date.now() - ostatnie) / 1000);
  await p.waitForTimeout(45000);  await pomiar('po 210 s');
  const cisza2 = Math.round((Date.now() - ostatnie) / 1000);
  console.log(`\nODPYTAN LACZNIE: ${odpytania}`);
  console.log(`CISZA od ostatniego odpytania: ${cisza2} s (przy pomiarze 165 s bylo ${cisza} s)`);
  const tekst = ((await p.locator('.sklepag-status').first().textContent()) || '').trim();
  console.log(`EKRAN NA KONCU: "${tekst}"`);
  const obiecuje = /odświeża status sama/i.test(tekst);
  const stoi = cisza2 > 20;
  console.log(`\nWERDYKT: strona obiecuje samoodswiezanie: ${obiecuje ? 'TAK' : 'NIE'} · odpytywanie STOI: ${stoi ? 'TAK' : 'NIE'}`);
  await b.close();
  process.exit(obiecuje && stoi ? 3 : 0);   // 3 = zdanie na ekranie przestalo byc prawda
})().catch((e) => { console.error('BLAD SONDY:', e.message); process.exit(2); });
