/* Zrzuty ekranów panelu wp-admin przez PRAWDZIWĄ przeglądarkę (chromium
   z ms-playwright) — do sędziego wyglądu i instrukcji. Wymaga playwright-core
   (npm install --no-save playwright-core) i poświadczeń admina w env:
   WP_ADMIN_USER + WP_ADMIN_PASS (nigdy w kodzie).
   Użycie: WP_ADMIN_USER=... WP_ADMIN_PASS=... node testy/zrzuty-panelu.js [katalog-wyjscia] */
const { chromium } = require('playwright-core');
const BAZA = 'http://localhost:8890';
const GDZIE = process.argv[2] || 'zrzuty';
const fs = require('fs');

const EKRANY = [
  ['dashboard-7dni', '/wp-admin/admin.php?page=sklepag&okres=7dni'],
  ['dashboard-30dni', '/wp-admin/admin.php?page=sklepag&okres=30dni'],
  ['dashboard-filtr-blad', '/wp-admin/admin.php?page=sklepag&status=mail_blad'],
  ['dziennik-logowania', '/wp-admin/admin.php?page=sklepag-dziennik&zakladka=logowania'],
  ['dziennik-zdarzenia', '/wp-admin/admin.php?page=sklepag-dziennik&zakladka=zdarzenia'],
  ['ustawienia', '/wp-admin/admin.php?page=sklepag-ustawienia'],
  ['zamowienie', '/wp-admin/admin.php?page=sklepag-zamowienie&zamowienie=1'],
];

(async () => {
  const user = process.env.WP_ADMIN_USER, pass = process.env.WP_ADMIN_PASS;
  if (!user || !pass) { console.error('Brak WP_ADMIN_USER/WP_ADMIN_PASS w env.'); process.exit(1); }
  fs.mkdirSync(GDZIE, { recursive: true });

  const b = await chromium.launch({ executablePath: '/root/.cache/ms-playwright/chromium-1234/chrome-linux64/chrome' });
  const p = await b.newPage({ viewport: { width: 1440, height: 1000 } });

  await p.goto(BAZA + '/wp-login.php', { waitUntil: 'load' });
  await p.fill('#user_login', user);
  await p.fill('#user_pass', pass);
  await Promise.all([p.waitForURL(/wp-admin/, { timeout: 30000 }), p.click('#wp-submit')]);
  console.log('ZALOGOWANO:', p.url().split('?')[0]);

  for (const [nazwa, sciezka] of EKRANY) {
    await p.goto(BAZA + sciezka, { waitUntil: 'load' });
    await p.waitForTimeout(400);
    await p.screenshot({ path: `${GDZIE}/${nazwa}.png`, fullPage: true });
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
