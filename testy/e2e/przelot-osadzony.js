/* E2E płatności OSADZONEJ (0.9.25): zakup kartą testową w formularzu na NASZEJ stronie produktu.
   Wymaga klucza publikowalnego w Ustawieniach stosu i Stripe.js. Pola e-mail/karta żyją w ramkach
   Stripe — wypełniane przez page.frames(). Kod wyjścia: 0 = powrót na „Dziękujemy” z sesją + (opcjonalnie)
   status mail_wyslany; 2 = brak powrotu; 1 = błąd. Bez webhooka na stosie status domyka reconciliation
   (SKLEPAG_RECON=1 → skrypt woła wp eval po 125 s). Adres stosu: SKLEPAG_BAZA (domyślnie :8890). */
const { chromium } = require('playwright-core');
const { wymagajChromium } = require('../lib/chromium');
const BAZA = process.env.SKLEPAG_BAZA || 'http://localhost:8890';
const { execSync } = require('child_process');

async function wypelnijWRamkach(p, dopasowania) {
  for (const [wzor, wartosc] of dopasowania) {
    let ok = false;
    for (const fr of p.frames()) {
      const pole = fr.locator(`input[name*="${wzor}" i], input[id*="${wzor}" i], input[placeholder*="${wzor}" i], input[autocomplete*="${wzor}" i]`).first();
      if (await pole.count().catch(() => 0)) {
        await pole.fill(wartosc, { timeout: 5000 }).then(() => { ok = true; }).catch(() => {});
        if (ok) break;
      }
    }
    console.log((ok ? 'OK  ' : 'BRAK') + ' pole ~' + wzor);
  }
}

(async () => {
  const b = await chromium.launch({ executablePath: wymagajChromium() });
  const p = await b.newPage({ viewport: { width: 1280, height: 900 } });
  const bledy = [];
  p.on('console', m => { if (m.type() === 'error') bledy.push(m.text().slice(0, 100)); });
  await p.goto(BAZA + '/sklep/produkt/chatbot-live/', { waitUntil: 'networkidle' });
  await p.check('#sklepag-zgoda-1');
  if (process.env.SKLEPAG_FAKTURA) {
    await p.evaluate(() => { document.querySelector('.sklepag-faktura').open = true; });
    await p.fill('input[name="faktura_nazwa"]', 'Firma Osadzona E2E');
    await p.fill('input[name="faktura_nip"]', '5260250274');
    await p.fill('textarea[name="faktura_adres"]', 'ul. Osadzona 9, 00-001 Warszawa');
  }
  await p.click('#sklepag-kup-formularz .sklepag-kup');
  await p.waitForSelector('#sklepag-platnosc:not([hidden])', { timeout: 20000 });
  await p.waitForTimeout(6000); // ramki Stripe
  const blad = await p.evaluate(() => (document.getElementById('sklepag-platnosc-blad') || {}).textContent);
  console.log('PANEL:', blad ? 'BLAD: ' + blad : 'bez bledu');
  for (const fr of p.frames()) {
    const nazwy = await fr.$$eval('input', els => els.map(e => e.name || e.id || e.placeholder).filter(Boolean)).catch(() => []);
    if (nazwy.length) console.log('RAMKA:', nazwy.join(' | ').slice(0, 160));
  }
  // Payment Element pokazuje kafelki metod (na koncie z BLIK-iem: „BLIK” i „Card”) — pola karty są po kliknięciu „Card”.
  let kafelek = false;
  for (const fr of p.frames()) {
    const t = fr.locator('[role=button]:has-text("Karta"), button:has-text("Karta"), [role=tab]:has-text("Karta"), [role=button]:has-text("Card"), button:has-text("Card"), [role=tab]:has-text("Card")').first();
    if (await t.count().catch(() => 0)) { await t.click({ timeout: 5000 }).then(() => { kafelek = true; }).catch(() => {}); if (kafelek) break; }
  }
  console.log('KAFELEK KARTA:', kafelek ? 'klikniety' : 'brak (moze juz otwarty)');
  await p.waitForTimeout(3000);
  await wypelnijWRamkach(p, [['email', 'kupujacy-osadzony@test.local'], ['number', '4242424242424242'], ['expiry', '12 / 30'], ['cvc', '123']]);
  await wypelnijWRamkach(p, [['name', 'Tester Osadzony'], ['postal', '00-001']]);
  await p.waitForTimeout(1500);
  const wlaczony = await p.evaluate(() => !document.getElementById('sklepag-platnosc-zaplac').disabled);
  console.log('ZAPLAC wlaczony:', wlaczony);
  await p.click('#sklepag-platnosc-zaplac');
  let powrot = false;
  try { await p.waitForURL(u => u.toString().includes('dziekujemy'), { timeout: 90000 }); powrot = true; } catch (e) {}
  const url = p.url();
  console.log('POWROT:', powrot ? url.split('?')[0] + ' sesja=' + ((url.match(/sesja=(cs_test_[A-Za-z0-9]+)/) || [])[1] || 'BRAK').slice(0, 20) : 'BRAK (' + url.split('?')[0] + ')');
  if (!powrot) {
    const b2 = await p.evaluate(() => (document.getElementById('sklepag-platnosc-blad') || {}).textContent);
    console.log('BLAD PANELU:', b2 || '-'); await p.screenshot({ path: 'osadzony-porazka.png' });
    /* Stripe wystawia niewidzialną hCaptcha dla zautomatyzowanej przeglądarki (zmierzone 30.08: ramka
       `hcaptcha-invisible`, POST api.hcaptcha.com/getcaptcha) — confirm() czeka na zagadkę, której robot nie
       rozwiąże. To NIE jest wada sklepu: kod wyjścia 3 = „zatrzymane na captchy Stripe”, dowód kliknięcia
       karty w osadzonym formularzu robi człowiek w zwykłej przeglądarce. */
    const captcha = p.frames().some(f => /hcaptcha/i.test(f.url()));
    if (captcha) { console.log('CAPTCHA STRIPE: tak — automat zatrzymany na hCaptcha (nie wada sklepu); dowód ręczny w przeglądarce'); await b.close(); process.exit(3); }
  }
  console.log('KONSOLA:', bledy.slice(0, 3).join(' | ') || 'brak');
  const cs = (url.match(/sesja=(cs_test_[A-Za-z0-9]+)/) || [])[1];
  let status = 'brak';
  if (powrot && cs) {
    if (process.env.SKLEPAG_RECON) {
      console.log('czekam 125 s na próg reconciliation...'); await new Promise(r => setTimeout(r, 125000));
      execSync("docker exec -u www-data strona_wp_wordpress wp eval 'sklepag_dociagnij_zalegle(0);' 2>/dev/null");
    }
    for (let i = 0; i < 15; i++) {
      const s = await (await fetch(BAZA + '/wp-json/sklepag/v1/status?sesja=' + cs)).json(); status = s.status;
      if (status === 'mail_wyslany' || status === 'mail_blad') break;
      await new Promise(r => setTimeout(r, 2000));
    }
  }
  console.log('STATUS KONCOWY:', status);
  await b.close();
  process.exit(powrot ? 0 : 2);
})().catch(e => { console.error('E2E BLAD:', e.message.split('\n')[0]); process.exit(1); });
