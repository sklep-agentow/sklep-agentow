// ⛔ SPRZĄTANIE PO BIEGU (I-22): ten przelot zostawia na WSPÓLNYM stanowisku prawdziwe zamówienie kupujacy-e2e@test.local,
//    które liczy się do przychodu na Pulpicie. Po każdym biegu: bash narzedzia-qa/stanowisko/sprzatnij-zamowienia-testowe.sh --kasuj
/* E2E kości v2: akordeon "card" + pola karty w iframe'ach Elements. */
const { chromium } = require('playwright-core');
const { wymagajChromium } = require('../lib/chromium');
const BAZA = process.env.SKLEPAG_BAZA || 'http://localhost:8890';

async function wypelnijWRamkach(p, dopasowania) {
  for (const [wzor, wartosc] of dopasowania) {
    let ok = false;
    for (const fr of p.frames()) {
      const pole = fr.locator(`input[name*="${wzor}" i], input[id*="${wzor}" i], input[placeholder*="${wzor}" i]`).first();
      if (await pole.count().catch(() => 0)) {
        await pole.fill(wartosc, { timeout: 5000 }).then(() => { ok = true; }).catch(() => {});
        if (ok) break;
      }
    }
    console.log((ok ? 'OK  ' : 'BRAK') + ' pole ~' + wzor);
  }
}

(async () => {
  // Od K5 (PR 2) formularz zakupu żyje na STRONIE PRODUKTU — nonce bierzemy stamtąd,
  // idąc linkiem "Zobacz szczegóły" z listy (odporne na permalinki i ich brak).
  const lista = await (await fetch(BAZA + '/sklep/')).text();
  const linkProduktu = (lista.match(/href="([^"]*(?:\/produkt\/|sklepag_produkt=)[^"]*)"/) || [])[1].replace(/&#0?38;|&amp;/g, '&');
  const strona = await (await fetch(linkProduktu)).text();
  const nonce = strona.match(/name="_wpnonce" value="([a-f0-9]+)"/)[1];
  const odp = await fetch(BAZA + '/wp-admin/admin-post.php', {
    method: 'POST', redirect: 'manual',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: 'action=sklepag_kup&produkt_id=1&zgoda=1&_wpnonce=' + nonce + (process.env.SKLEPAG_FAKTURA ? '&faktura_nazwa=Firma+Probna+E2E&faktura_nip=5260250274&faktura_adres=ul.+Testowa+5,+00-950+Warszawa' : ''),
  });
  const url = odp.headers.get('location');
  const cs = (url.match(/cs_test_[A-Za-z0-9]+/) || [])[0];
  console.log('SESJA:', cs);

  const b = await chromium.launch({ executablePath: wymagajChromium() });
  const p = await b.newPage();
  await p.goto(url, { waitUntil: 'load', timeout: 45000 });
  await p.waitForTimeout(5000);

  await p.fill('input#email', 'kupujacy-e2e@test.local');

  // Rozwinięcie metody „karta": przycisk → radio → tekst (z wymuszeniem).
  let rozwiniete = false;
  const proby = [
    () => p.locator('[data-testid="card-accordion-item-button"]').click({ timeout: 8000, force: true }),
    () => p.locator('input#payment-method-accordion-item-title-card').check({ timeout: 8000, force: true }),
    () => p.getByText(/^(Card|Karta)$/i).first().click({ timeout: 8000, force: true }),
  ];
  for (const proba of proby) {
    try { await proba(); rozwiniete = true; break; } catch (e) { /* następna strategia */ }
  }
  console.log('AKORDEON KARTA:', rozwiniete ? 'rozwiniety' : 'PORAZKA');
  if (!rozwiniete) { await p.screenshot({ path: 'akordeon-porazka.png' }); }
  await p.waitForTimeout(3000);

  // Spis pól we wszystkich ramkach (diagnostyka w dowodzie).
  for (const fr of p.frames()) {
    const nazwy = await fr.$$eval('input', els => els.map(e => e.name || e.id || e.placeholder).filter(Boolean)).catch(() => []);
    if (nazwy.length) console.log('RAMKA:', nazwy.join(' | '));
  }

  await wypelnijWRamkach(p, [
    ['number', '4242424242424242'],
    ['expiry', '12 / 30'],
    ['cvc', '123'],
  ]);
  // Imię/nazwisko i kod pocztowy bywają w ramce albo głównym dokumencie.
  await wypelnijWRamkach(p, [['billingName', 'Tester Kosci'], ['postal', '00-001']]);
  const krajRamka = p.frames().find(() => true);
  for (const fr of p.frames()) {
    const sel = fr.locator('select[name*="country" i], select[id*="country" i]').first();
    if (await sel.count().catch(() => 0)) { await sel.selectOption('PL').catch(() => {}); break; }
  }

  await p.click('[data-testid="hosted-payment-submit-button"]');
  await p.waitForURL(u => u.toString().startsWith(BAZA), { timeout: 90000 });
  console.log('POWROT:', p.url().split('?')[0]);

  let status = 'oczekuje';
  for (let i = 0; i < 30; i++) {
    const s = await (await fetch(BAZA + '/wp-json/sklepag/v1/status?sesja=' + cs)).json();
    status = s.status;
    if (status === 'mail_wyslany' || status === 'mail_blad') break;
    await new Promise(r => setTimeout(r, 2000));
  }
  console.log('STATUS KONCOWY:', status);
  console.log('EKRAN DZIEKUJEMY:', ((await p.textContent('.sklepag-status').catch(() => '')) || '').trim());
  await b.close();
  process.exit(status === 'mail_wyslany' ? 0 : 2);
})().catch(e => { console.error('E2E BLAD:', e.message.split('\n')[0]); process.exit(1); });
