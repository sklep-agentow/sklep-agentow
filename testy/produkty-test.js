/* Test K4 (CRUD produktów) na żywym stacku przez PRAWDZIWĄ przeglądarkę:
   happy path z realnym zipem + walidacje serwerowe (fetch z sesją, omija
   HTML5) + kryterium #6 (edycja nie zmienia snapshotu — mierzone wp-cli
   OSOBNO po teście). Env: WP_ADMIN_USER, WP_ADMIN_PASS, ZIP (ścieżka).
   Wyjście: linie WYNIK:..., exit 0 gdy wszystkie OK. */
const { chromium } = require('playwright-core');
const BAZA = 'http://localhost:8890';
const ZIP = process.env.ZIP || 'boty/paczki/chatbot-live-1.2.0.zip';

let bledy = 0;
const wynik = (nazwa, ok, extra = '') => { console.log(`WYNIK:${ok ? 'OK ' : 'PAD'} ${nazwa}${extra ? ' — ' + extra : ''}`); if (!ok) bledy++; };

(async () => {
  const b = await chromium.launch({ executablePath: '/root/.cache/ms-playwright/chromium-1234/chrome-linux64/chrome' });
  const p = await b.newPage({ viewport: { width: 1440, height: 1000 } });
  await p.goto(BAZA + '/wp-login.php', { waitUntil: 'load' });
  await p.fill('#user_login', process.env.WP_ADMIN_USER);
  await p.fill('#user_pass', process.env.WP_ADMIN_PASS);
  await Promise.all([p.waitForURL(/wp-admin/), p.click('#wp-submit')]);

  // 1. HAPPY: nowy produkt z PRAWDZIWYM zipem przez formularz UI.
  await p.goto(BAZA + '/wp-admin/admin.php?page=sklepag-produkty&widok=formularz', { waitUntil: 'load' });
  await p.fill('#sklepag-nazwa', 'Segregator maila TEST-K4');
  await p.fill('#sklepag-opis-krotki', 'Opis krótki testowy K4.');
  await p.fill('#sklepag-cena', '199.00');
  await p.setInputFiles('#sklepag-plik', ZIP);
  await p.check('input[name="aktywny"]');
  await Promise.all([p.waitForURL(/sklepag_ok=plik/, { timeout: 30000 }), p.click('#submit')]);
  const potw = await p.textContent('.notice-success').catch(() => '');
  wynik('happy: zapis z zipem POTWIERDZONY', /POTWIERDZONY/.test(potw || ''));

  // Nonce do walidacji serwerowych (fetch w sesji przeglądarki).
  await p.goto(BAZA + '/wp-admin/admin.php?page=sklepag-produkty&widok=formularz', { waitUntil: 'load' });
  const nonce = await p.inputValue('#_wpnonce');

  const postProdukt = async (pola, zBlednymPlikiem = false) => p.evaluate(async ({ pola, nonce, zBlednymPlikiem }) => {
    const fd = new FormData();
    fd.append('action', 'sklepag_produkt_zapisz');
    fd.append('_wpnonce', nonce);
    fd.append('produkt_id', '0');
    for (const [k, v] of Object.entries(pola)) fd.append(k, v);
    if (zBlednymPlikiem) fd.append('plik', new File(['to nie jest zip — zwykly tekst'], 'podroba.zip', { type: 'application/zip' }));
    const r = await fetch('/wp-admin/admin-post.php', { method: 'POST', body: fd, redirect: 'follow' });
    return decodeURIComponent(r.url);
  }, { pola, nonce, zBlednymPlikiem });

  // 2. Walidacje serwerowe (kryterium #7) — HTML5 ominięte fetchem.
  let u = await postProdukt({ nazwa: '', cena: '10', waluta: 'PLN' });
  wynik('walidacja: pusta nazwa odrzucona', /Nazwa nie może/.test(u));
  u = await postProdukt({ nazwa: 'Test cena zero', cena: '0', waluta: 'PLN' });
  wynik('walidacja: cena 0 odrzucona', /większa od zera/.test(u));
  u = await postProdukt({ nazwa: 'Test aktywny bez pliku', cena: '10', waluta: 'PLN', aktywny: '1' });
  wynik('walidacja: aktywacja bez pliku odrzucona', /bez POTWIERDZONEGO pliku/.test(u));
  u = await postProdukt({ nazwa: 'Test podroba zipa', cena: '10', waluta: 'PLN' }, true);
  wynik('walidacja: nie-zip (MIME z treści) odrzucony', /nie jest zipem/.test(u));

  // 3. Deaktywacja z listy (link z nonce).
  await p.goto(BAZA + '/wp-admin/admin.php?page=sklepag-produkty', { waitUntil: 'load' });
  const wiersz = p.locator('tr', { hasText: 'Segregator maila TEST-K4' });
  await Promise.all([p.waitForURL(/sklepag_ok=przelaczono/), wiersz.locator('a', { hasText: 'Deaktywuj' }).click()]);
  await p.goto(BAZA + '/wp-admin/admin.php?page=sklepag-produkty', { waitUntil: 'load' });
  const badge = await p.locator('tr', { hasText: 'TEST-K4' }).locator('.sklepag-badge-szary').textContent().catch(() => '');
  wynik('deaktywacja: badge „wyłączony"', /wyłączony/.test(badge || ''));

  await p.screenshot({ path: '/tmp/claude-0/-root-praca-warsztat/edaa8249-40a6-4659-9a1d-de81e018d84a/scratchpad/zrzuty/produkty-lista.png', fullPage: true });
  await b.close();
  console.log(bledy === 0 ? 'PRODUKTY-TEST: KOMPLET OK' : `PRODUKTY-TEST: ${bledy} PADÓW`);
  process.exit(bledy === 0 ? 0 : 2);
})().catch(e => { console.error('BLAD TESTU:', e.message.split('\n')[0]); process.exit(1); });
