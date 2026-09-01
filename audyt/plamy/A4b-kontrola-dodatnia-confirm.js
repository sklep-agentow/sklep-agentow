/* A4b — KONTROLA DODATNIA do znaleziska „Zapłać nie potwierdza płatności”.
 *
 * Teza z sondy: `checkout.loadActions()` oddaje OPAKOWANIE `{ type, actions }`,
 * a `assets/platnosc.js:150` woła `confirm()` na opakowaniu — stąd
 * `TypeError: actions.confirm is not a function` i przycisk zawieszony
 * na „Przetwarzam…”.
 *
 * Kontrola dodatnia musi pokazać, że to NASZ kształt obiektu blokuje płatność,
 * a nie obrona Stripe’a przed robotem (klasa 60 ściągi — hCaptcha przy `confirm()`).
 * Dlatego ten skrypt robi DOKŁADNIE to samo co przycisk, tylko sięga po metodę
 * poziom niżej: `(await checkout.loadActions()).actions.confirm()`.
 *   - dojedzie na „Dziękujemy”  → droga jest przejezdna, blokuje wyłącznie nasz błąd,
 *   - stanie na hCaptcha/3DS    → zapisujemy to wprost i nie udajemy dowodu.
 *
 * ⚠️ Skutek uboczny: to jest PRAWDZIWY zakup w trybie TESTOWYM Stripe (karta
 * 4242…) — zakłada wiersz w `wp_sklepag_zamowienia`. Zapisane w raporcie.
 *
 * Uruchomienie:
 *   flock /root/praca/zlecenia/wymiana/stos.lock node audyt/plamy/A4b-kontrola-dodatnia-confirm.js
 */
const path = require('path');
const { chromium } = require('playwright-core');
const { wymagajChromium } = require('../../testy/lib/chromium');
const BAZA = process.env.SKLEPAG_BAZA || 'http://localhost:8890';
const KAT = path.join(__dirname, 'A4b-zrzuty');

async function wypelnijWRamkach(p, dopasowania) {
  const wynik = {};
  for (const [wzor, wartosc] of dopasowania) {
    let ok = false;
    for (const fr of p.frames()) {
      const pole = fr.locator(`input[name*="${wzor}" i], input[id*="${wzor}" i], input[placeholder*="${wzor}" i], input[autocomplete*="${wzor}" i]`).first();
      if (await pole.count().catch(() => 0)) {
        await pole.fill(wartosc, { timeout: 5000 }).then(() => { ok = true; }).catch(() => {});
        if (ok) { break; }
      }
    }
    wynik[wzor] = ok;
  }
  return wynik;
}

(async () => {
  const b = await chromium.launch({ executablePath: wymagajChromium() });
  const p = await b.newPage({ viewport: { width: 1440, height: 900 } });
  const wyjatki = [];
  p.on('pageerror', (e) => wyjatki.push({ tekst: String(e).slice(0, 200), stos: (e.stack || '').split('\n').slice(0, 4).join(' | ').slice(0, 400) }));

  await p.addInitScript(() => {
    window.__A4B = {};
    let prawdziwy;
    Object.defineProperty(window, 'Stripe', {
      configurable: true,
      get() {
        if (!prawdziwy) { return undefined; }
        const z = function (...a) {
          const s = prawdziwy.apply(this, a);
          const o = s.initCheckoutElementsSdk;
          s.initCheckoutElementsSdk = function (...b) { const c = o.apply(this, b); window.__A4B.checkout = c; return c; };
          return s;
        };
        Object.assign(z, prawdziwy);
        return z;
      },
      set(v) { prawdziwy = v; },
    });
  });

  await p.goto(BAZA + '/sklep/produkt/chatbot-live/', { waitUntil: 'load', timeout: 45000 });
  await p.waitForTimeout(1500);
  await p.check('#sklepag-zgoda-1');
  await p.click('#sklepag-kup-formularz .sklepag-kup');
  await p.waitForSelector('#sklepag-platnosc:not([hidden])', { timeout: 25000 });
  await p.waitForTimeout(8000);

  for (const fr of p.frames()) {
    const t = fr.locator('[role=button]:has-text("Karta"), button:has-text("Karta"), [role=tab]:has-text("Karta"), [role=button]:has-text("Card"), button:has-text("Card"), [role=tab]:has-text("Card")').first();
    if (await t.count().catch(() => 0)) { await t.click({ timeout: 5000 }).catch(() => {}); break; }
  }
  await p.waitForTimeout(2500);
  const pola = await wypelnijWRamkach(p, [['email', 'audyt-a4b-kd@test.local'], ['number', '4242424242424242'], ['expiry', '12 / 30'], ['cvc', '123']]);
  Object.assign(pola, await wypelnijWRamkach(p, [['name', 'Audyt A4b KD'], ['postal', '00-001']]));
  await p.waitForTimeout(1500);
  console.log('POLA:', JSON.stringify(pola));

  /* --- PRZEBIEG A: dokładnie to, co robi przycisk produktu (wersja z repo). --- */
  await p.click('#sklepag-platnosc-zaplac');
  await p.waitForTimeout(6000);
  const poKliknieciu = await p.evaluate(() => {
    const btn = document.getElementById('sklepag-platnosc-zaplac');
    const bl = document.getElementById('sklepag-platnosc-blad');
    return {
      napisNaPrzycisku: btn ? btn.textContent.trim() : null,
      przyciskZablokowany: btn ? btn.disabled : null,
      trescBledu: bl ? (bl.textContent || '').trim() : null,
      bladSchowany: bl ? bl.hidden : null,
      url: location.href.split('?')[0],
    };
  });
  await p.screenshot({ path: path.join(KAT, 'zakup-A-przycisk-produktu.png') });
  console.log('PO KLIKNIĘCIU „ZAPŁAĆ” (kod z repo):', JSON.stringify(poKliknieciu, null, 1));
  console.log('WYJĄTKI:', JSON.stringify(wyjatki, null, 1));

  /* --- PRZEBIEG B: ta sama chwila, ale confirm() wołany na WŁAŚCIWYM obiekcie. --- */
  /* `confirm()` przy powodzeniu PRZEKIEROWUJE — kontekst wykonania ginie w środku
     `evaluate` i Playwright zgłasza „Execution context was destroyed”. To jest
     SUKCES kontroli, nie awaria: łapiemy go i nazywamy po imieniu. */
  let b2;
  try {
    b2 = await p.evaluate(async () => {
      const a = await window.__A4B.checkout.loadActions();
      if (!a || !a.actions || typeof a.actions.confirm !== 'function') { return { start: 'BRAK actions.actions.confirm' }; }
      const w = await a.actions.confirm();
      return { start: 'wywołane', typ: w && w.type, blad: w && w.error && w.error.message };
    });
  } catch (e) {
    b2 = /context was destroyed|Execution context/i.test(String(e))
      ? { start: 'PRZEKIEROWANIE — kontekst zniszczony nawigacją (confirm ruszył)' }
      : { start: 'wyjątek', tekst: String(e).slice(0, 200) };
  }
  console.log('CONFIRM NA WŁAŚCIWYM OBIEKCIE:', JSON.stringify(b2));
  let doszlo = false;
  try { await p.waitForURL((u) => u.toString().includes('dziekujemy'), { timeout: 60000 }); doszlo = true; } catch (e) { /* poniżej */ }
  await p.waitForTimeout(3000);
  await p.screenshot({ path: path.join(KAT, 'zakup-B-confirm-poprawny.png') });
  const captcha = p.frames().some((f) => /hcaptcha|recaptcha/i.test(f.url()));
  console.log('WYNIK B: doszłoNaDziękujemy=' + doszlo + '  url=' + p.url().split('?')[0] + '  ramkaCaptcha=' + captcha);
  if (doszlo) {
    await p.waitForTimeout(5000);
    const t = await p.evaluate(() => {
      const el = document.querySelector('.sklepag-dziekujemy');
      return el ? (el.textContent || '').replace(/\s+/g, ' ').trim().slice(0, 300) : 'BRAK';
    });
    console.log('DZIĘKUJEMY:', t);
    await p.screenshot({ path: path.join(KAT, 'zakup-B-dziekujemy.png') });
  }
  await b.close();
})().catch((e) => { console.error('KONTROLA PADŁA:', e); process.exit(1); });
