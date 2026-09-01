/* A4b — SONDA: czy po nieudanym „Zapłać” kupujący ma DROGĘ POWROTU.
 *
 * Kontekst: `assets/platnosc.js:150` rzuca `TypeError` (patrz A4b-sonda-actions.js),
 * więc `wToku` zostaje `true` — nie zeruje go ani gałąź `.then`, ani `.catch`
 * (wyjątek leci synchronicznie, obok obietnicy). Pytanie do zmierzenia brzmi:
 * czy „Wróć” + ponowne „Kup teraz” wyprowadzają z tego stanu, czy nie.
 *
 * Mierzymy trzy kliknięcia po kolei i stan ekranu po każdym.
 *
 * Uruchomienie:
 *   flock /root/praca/zlecenia/wymiana/stos.lock node audyt/plamy/A4b-sonda-slepy-zaulek.js
 */
const path = require('path');
const { chromium } = require('playwright-core');
const { wymagajChromium } = require('../../testy/lib/chromium');
const BAZA = process.env.SKLEPAG_BAZA || 'http://localhost:8890';
const KAT = path.join(__dirname, 'A4b-zrzuty');

const stan = (p) => p.evaluate(() => {
  const f = document.getElementById('sklepag-kup-formularz');
  const pan = document.getElementById('sklepag-platnosc');
  const zap = document.getElementById('sklepag-platnosc-zaplac');
  const kup = f ? f.querySelector('.sklepag-kup') : null;
  const bl = document.getElementById('sklepag-platnosc-blad');
  return {
    formularzSchowany: f ? f.hidden : null,
    panelSchowany: pan ? pan.hidden : null,
    napisZaplac: zap ? zap.textContent.trim() : null,
    zaplacZablokowany: zap ? zap.disabled : null,
    napisKup: kup ? kup.textContent.trim() : null,
    kupZablokowany: kup ? kup.disabled : null,
    komunikatBledu: bl ? ((bl.textContent || '').trim() || '(pusty)') + (bl.hidden ? ' [SCHOWANY]' : ' [WIDOCZNY]') : null,
  };
});

(async () => {
  const b = await chromium.launch({ executablePath: wymagajChromium() });
  const p = await b.newPage({ viewport: { width: 1440, height: 900 } });
  const wyjatki = [];
  p.on('pageerror', (e) => wyjatki.push(String(e).slice(0, 120)));

  await p.goto(BAZA + '/sklep/produkt/chatbot-live/', { waitUntil: 'load', timeout: 45000 });
  await p.waitForTimeout(1500);
  await p.check('#sklepag-kup-formularz input[name="zgoda"]');
  await p.click('#sklepag-kup-formularz .sklepag-kup');
  await p.waitForSelector('#sklepag-platnosc:not([hidden])', { timeout: 25000 });
  await p.waitForTimeout(8000);
  console.log('1. panel otwarty          :', JSON.stringify(await stan(p)));

  /* Bez wypełnionej karty „Zapłać” jest SŁUSZNIE zablokowany (`session.canConfirm`).
     Żeby zmierzyć ślepy zaułek, trzeba najpierw doprowadzić przycisk do stanu
     aktywnego — czyli zrobić to, co robi kupujący. */
  for (const fr of p.frames()) {
    const t = fr.locator('[role=button]:has-text("Karta"), button:has-text("Karta"), [role=tab]:has-text("Karta"), [role=button]:has-text("Card"), button:has-text("Card")').first();
    if (await t.count().catch(() => 0)) { await t.click({ timeout: 5000 }).catch(() => {}); break; }
  }
  await p.waitForTimeout(2500);
  for (const [wzor, wartosc] of [['email', 'audyt-a4b-zaulek@test.local'], ['number', '4242424242424242'], ['expiry', '12 / 30'], ['cvc', '123'], ['name', 'Audyt A4b']]) {
    for (const fr of p.frames()) {
      const pole = fr.locator(`input[name*="${wzor}" i], input[id*="${wzor}" i], input[placeholder*="${wzor}" i], input[autocomplete*="${wzor}" i]`).first();
      if (await pole.count().catch(() => 0)) { await pole.fill(wartosc, { timeout: 5000 }).catch(() => {}); break; }
    }
  }
  await p.waitForTimeout(2000);
  console.log('1b. karta wypełniona      :', JSON.stringify(await stan(p)));

  await p.click('#sklepag-platnosc-zaplac');
  await p.waitForTimeout(4000);
  console.log('2. po kliknięciu ZAPŁAĆ   :', JSON.stringify(await stan(p)));

  await p.click('#sklepag-platnosc-wroc');
  await p.waitForTimeout(2000);
  console.log('3. po kliknięciu WRÓĆ     :', JSON.stringify(await stan(p)));

  await p.click('#sklepag-kup-formularz .sklepag-kup');
  await p.waitForTimeout(5000);
  console.log('4. po ponownym KUP TERAZ  :', JSON.stringify(await stan(p)));
  console.log('   URL:', p.url().split('?')[0]);
  console.log('   WYJĄTKI:', JSON.stringify(wyjatki));
  await p.screenshot({ path: path.join(KAT, 'zakup-C-slepy-zaulek.png') });
  await b.close();
})().catch((e) => { console.error('SONDA PADŁA:', e); process.exit(1); });
