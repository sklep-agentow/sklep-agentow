// ⛔ SPRZĄTANIE PO BIEGU (I-22): ten przelot zostawia na WSPÓLNYM stanowisku prawdziwe zamówienie kupujacy-osadzony@test.local,
//    które liczy się do przychodu na Pulpicie. Po każdym biegu: bash narzedzia-qa/stanowisko/sprzatnij-zamowienia-testowe.sh --kasuj
/* E2E płatności OSADZONEJ (0.9.25): zakup kartą testową w formularzu na NASZEJ stronie produktu.
   Wymaga klucza publikowalnego w Ustawieniach stosu i Stripe.js. Pola e-mail/karta żyją w ramkach
   Stripe — wypełniane przez page.frames(). Bez webhooka na stosie status domyka reconciliation
   (SKLEPAG_RECON=1 → skrypt woła wp eval po 125 s). Adres stosu: SKLEPAG_BAZA (domyślnie :8890).

   KODY WYJŚCIA: 0 = powrót na „Dziękujemy” z sesją · 1 = WADA SKLEPU (wyjątek na stronie albo
   błąd przyrządu) · 2 = brak powrotu, przyczyny nie umiem nazwać · 3 = zatrzymane na captchy Stripe.

   ⛔ DWIE RZECZY, KTÓRE TEN STRAŻNIK ROBIŁ ŹLE i przez które przespał wadę „sklep nie sprzedaje”
   (znalezisko A4b-F2, 1.09):

   1. NIE SŁUCHAŁ `pageerror`. Łapał wyłącznie `console` typu error, a NIEZŁAPANY WYJĄTEK w kodzie
      strony nie idzie przez `console` Playwrighta — leci jako `pageerror`. `TypeError: actions.confirm
      is not a function` przelatywał więc przez palce i strażnik nie miał go z czego zobaczyć.

   2. ROZPOZNAWAŁ CAPTCHĘ PO SAMEJ OBECNOŚCI RAMKI `hcaptcha`. Stripe.js wstawia niewidzialną ramkę
      hCaptcha ZAWSZE, także wtedy, gdy nic nie blokuje — więc warunek był praktycznie zawsze prawdziwy
      i każda porażka ścieżki zakupu kończyła się meldunkiem „nie wada sklepu" i kodem 3. Kontrola,
      która z założenia nie potrafi obwinić produktu, nie jest kontrolą.

   Teraz kolejność werdyktów jest częścią kontraktu: NAJPIERW nasze wyjątki (bo wyjątek dowodzi, że
   `confirm()` w ogóle nie wystartował — captcha jest wtedy nieistotna), a captcha usprawiedliwia
   przebieg TYLKO wtedy, gdy nie było ani jednego wyjątku ORAZ hCaptcha naprawdę wystawiła zagadkę
   (żądanie `getcaptcha`/`checkcaptcha`), a nie tylko wstawiła ramkę. */
const { chromium } = require('playwright-core');
const { wymagajChromium } = require('../lib/chromium');
const BAZA = process.env.SKLEPAG_BAZA || 'http://localhost:8890';
const { execSync } = require('child_process');
/* Zrzut porażki szedł do katalogu roboczego, czyli do korzenia repo. Domyślnie idzie teraz
   do katalogu tymczasowego — zabłąkany PNG w drzewie brudzi gałąź i wpada pod bramki paczki. */
const ZRZUT_PORAZKI = process.env.SKLEPAG_ZRZUT || require('os').tmpdir() + '/osadzony-porazka.png';

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
  /* ⛔ NIEZŁAPANY WYJĄTEK NA STRONIE nie idzie przez `console` — to osobne zdarzenie `pageerror`.
     Bez tego nasłuchu strażnik był ślepy na dokładnie tę klasę, która zatrzymała sprzedaż. */
  const wyjatki = [];
  p.on('pageerror', e => {
    const opis = e && e.stack ? String(e.stack).split('\n').slice(0, 2).map(x => x.trim()).join('  ←  ') : String(e);
    wyjatki.push(opis.slice(0, 300));
  });
  /* Dowód, że hCaptcha NAPRAWDĘ wystawiła zagadkę, a nie tylko wstawiła niewidzialną ramkę. */
  const zadaniaCaptchy = [];
  p.on('request', r => { if (/hcaptcha\.com\/[^?]*(getcaptcha|checkcaptcha)/i.test(r.url())) { zadaniaCaptchy.push(r.url().split('?')[0]); } });
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
  /* ⛔ WYJĄTEK NA STRONIE JEST WERDYKTEM SAM W SOBIE i sprawdzamy go PRZED wszystkim innym —
     także wtedy, gdy powrót jakimś cudem nastąpił. Niezłapany błąd na ścieżce płatności to wada
     sklepu, nawet jeśli kupujący dobrnął gdzieś dalej. */
  if (wyjatki.length) {
    console.log('WYJATKI STRONY (' + wyjatki.length + ') — WADA SKLEPU:');
    wyjatki.slice(0, 5).forEach(w => console.log('   ' + w));
    const b3 = await p.evaluate(() => (document.getElementById('sklepag-platnosc-blad') || {}).textContent);
    const st = await p.evaluate(() => { const z = document.getElementById('sklepag-platnosc-zaplac'); return z ? (z.disabled ? 'zablokowany' : 'wlaczony') + ' / "' + z.textContent.trim() + '"' : 'brak'; });
    console.log('PRZYCISK ZAPLAC:', st);
    console.log('KOMUNIKAT DLA KUPUJACEGO:', b3 && b3.trim() ? b3.trim() : 'ZADEN — kupujacy nie wie, co sie stalo');
    await p.screenshot({ path: ZRZUT_PORAZKI }).catch(() => {});
    await b.close();
    process.exit(1);
  }
  if (!powrot) {
    const b2 = await p.evaluate(() => (document.getElementById('sklepag-platnosc-blad') || {}).textContent);
    console.log('BLAD PANELU:', b2 || '-'); await p.screenshot({ path: ZRZUT_PORAZKI }).catch(() => {});
    /* Stripe wystawia niewidzialną hCaptcha dla zautomatyzowanej przeglądarki (zmierzone 30.08: ramka
       `hcaptcha-invisible`, POST api.hcaptcha.com/getcaptcha) — confirm() czeka na zagadkę, której robot nie
       rozwiąże. To NIE jest wada sklepu — ale usprawiedliwia przebieg TYLKO z dowodem, że zagadka
       naprawdę padła. Sama ramka nie wystarcza: Stripe.js wstawia ją zawsze. */
    const captchaRamka = p.frames().some(f => /hcaptcha/i.test(f.url()));
    console.log('CAPTCHA: ramka=' + captchaRamka + ' zadania=' + (zadaniaCaptchy.length ? zadaniaCaptchy.join(' | ') : 'BRAK'));
    if (captchaRamka && zadaniaCaptchy.length) {
      console.log('CAPTCHA STRIPE: tak — automat zatrzymany na hCaptcha (zagadka wystawiona, zero wyjatkow strony); dowod reczny w przegladarce');
      await b.close(); process.exit(3);
    }
    console.log('BRAK POWROTU i BRAK dowodu captchy — nie umiem tego nazwac „nie wada sklepu". Kod 2.');
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
