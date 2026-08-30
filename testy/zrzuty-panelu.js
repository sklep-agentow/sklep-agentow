/* Zrzuty ekranów panelu wp-admin przez PRAWDZIWĄ przeglądarkę (chromium
   z ms-playwright) — do sędziego wyglądu i instrukcji. Wymaga playwright-core
   (npm install --no-save playwright-core) i poświadczeń admina w env:
   WP_ADMIN_USER + WP_ADMIN_PASS (nigdy w kodzie).
   Użycie: WP_ADMIN_USER=... WP_ADMIN_PASS=... node testy/zrzuty-panelu.js [katalog-wyjscia] */
const { chromium } = require('playwright-core');
const { wymagajChromium } = require('./lib/chromium');
const BAZA = 'http://localhost:8890';
const GDZIE = process.argv[2] || 'zrzuty';
const fs = require('fs');

const EKRANY = [
  ['dashboard-7dni', '/wp-admin/admin.php?page=sklepag&okres=7dni'],
  ['dashboard-wskazniki', '/wp-admin/admin.php?page=sklepag&okres=7dni', { fullPage: false, clip: { x: 0, y: 0, width: 1440, height: 640 } }],
  ['dashboard-30dni', '/wp-admin/admin.php?page=sklepag&okres=30dni'],
  ['dashboard-filtr-blad', '/wp-admin/admin.php?page=sklepag&status=mail_blad'],
  ['dziennik-logowania', '/wp-admin/admin.php?page=sklepag-dziennik&zakladka=logowania'],
  ['dziennik-zdarzenia', '/wp-admin/admin.php?page=sklepag-dziennik&zakladka=zdarzenia'],
  ['ustawienia', '/wp-admin/admin.php?page=sklepag-ustawienia'],
  ['zamowienie', '/wp-admin/admin.php?page=sklepag-zamowienie&zamowienie=1'],
  // tylko lewa karta zamówienia — oś czasu po prawej niesie nazwy kont i adresy sieci stosu
  ['zamowienie-naglowek', '/wp-admin/admin.php?page=sklepag-zamowienie&zamowienie=1', { fullPage: false, clip: { x: 0, y: 0, width: 1000, height: 440 } }],
  ['produkty', '/wp-admin/admin.php?page=sklepag-produkty'],
  // Formularz NOWEGO produktu (pusty): zrzut jedzie do instrukcji klienta, więc bez danych testowych stosu.
  ['produkt-formularz', '/wp-admin/admin.php?page=sklepag-produkty&widok=formularz'],
  ['opinie', '/wp-admin/admin.php?page=sklepag-opinie'],
  // Lista sklepu: tylko góra strony (hero + kafle) — pełna strona ma sekcje odsłaniane dopiero przy przewijaniu.
  ['sklep-front', '/sklep/', { fullPage: false, clip: { x: 0, y: 0, width: 1440, height: 1460 } }],
  ['produkt-front', '/sklep/produkt/chatbot-live/'],
];

(async () => {
  const user = process.env.WP_ADMIN_USER, pass = process.env.WP_ADMIN_PASS;
  if (!user || !pass) { console.error('Brak WP_ADMIN_USER/WP_ADMIN_PASS w env.'); process.exit(1); }
  fs.mkdirSync(GDZIE, { recursive: true });

  const b = await chromium.launch({ executablePath: wymagajChromium() });
  const p = await b.newPage({ viewport: { width: 1440, height: 1000 } });
  // Zrzuty jadą do instrukcji klienta: pasek admina nie może nieść nazwy NASZEGO konta ani witryny stosu,
  // a strony sklepu (front) robimy w OSOBNYM, niezalogowanym kontekście — kupujący nie widzi paska WP.
  const bezSladow = async (strona) => strona.addStyleTag({ content: '#wp-admin-bar-my-account, #wp-admin-bar-site-name, #wp-admin-bar-updates, #wp-admin-bar-comments, #wp-admin-bar-new-content, #wp-admin-bar-search { visibility: hidden !important; }' });
  const gosc = await b.newContext({ viewport: { width: 1440, height: 1000 } });
  p.setDefaultTimeout(60000); // 60 s: pelna linia bazowa na 2 vCPU dlawi 30-sekundowy default (24.08)

  await p.goto(BAZA + '/wp-login.php', { waitUntil: 'load' });
  await p.fill('#user_login', user);
  await p.fill('#user_pass', pass);
  await Promise.all([p.waitForURL(/wp-admin/, { timeout: 30000 }), p.click('#wp-submit')]);
  console.log('ZALOGOWANO:', p.url().split('?')[0]);

  for (const [nazwa, sciezka, opcje] of EKRANY) {
    const front = !sciezka.startsWith('/wp-admin/');
    const s = front ? await gosc.newPage() : p;
    await s.goto(BAZA + sciezka, { waitUntil: 'load' });
    await s.waitForTimeout(400);
    if (!front) await bezSladow(s);
    await s.screenshot({ path: `${GDZIE}/${nazwa}.png`, fullPage: true, ...(opcje || {}) });
    if (front) await s.close();
    console.log('ZRZUT:', nazwa);
  }

  // Wersja wąska (RWD — kryterium #8 sędziego): dashboard na 480 px.
  await p.setViewportSize({ width: 480, height: 900 });
  await p.goto(BAZA + '/wp-admin/admin.php?page=sklepag&okres=7dni', { waitUntil: 'load' });
  await p.waitForTimeout(400);
  await p.screenshot({ path: `${GDZIE}/dashboard-waski-480.png`, fullPage: true });
  console.log('ZRZUT: dashboard-waski-480');

  await b.close();
  console.log('GOTOWE — katalog:', GDZIE);
})().catch(e => { console.error('BLAD ZRZUTOW:', e.message.split('\n')[0]); process.exit(1); });
