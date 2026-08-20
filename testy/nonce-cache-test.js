/* L1 — nonce w cache'owanej stronie. Test-straznik, kod wyjscia 1 przy porazce.
 *
 * Co dokladnie psulo sie u klienta: nonce wchodzi do HTML-a strony, a wtyczka
 * albo usluga cache (LiteSpeed, WP Rocket, W3TC, Cloudflare) serwuje ten HTML
 * dluzej, niz nonce zyje. Wtedy KAZDE pytanie konczylo sie odmowa, gosc widzial
 * "Cos poszlo nie tak", a wlasciciel nie mial o tym pojecia.
 *
 * Test sprawdza trzy rzeczy, bo naprawa ma trzy czesci:
 *   A. serwer odmawia ROZPOZNAWALNIE (kod 'nonce') i ODNOTOWUJE odrzucenie,
 *   B. widget sam dociaga swiezy nonce i powtarza — gosc konczy ROZMOWA,
 *      nie komunikatem o awarii,
 *   C. wlasciciel WIDZI w panelu, ze gosciom odbija sie nonce.
 *
 * Wymaga playwright-core, stacku i WP_ADMIN_USER/WP_ADMIN_PASS.
 * Uzycie: node testy/nonce-cache-test.js [adres]
 */
const { chromium } = require('playwright-core');
const { wymagajChromium } = require('./lib/chromium');
const { execFileSync } = require('child_process');
const BAZA = process.argv[2] || 'http://localhost:8890';
const KONTENER = 'strona_wp_wordpress';

let bledy = 0;
function sprawdz(opis, warunek, szczegol) {
  console.log((warunek ? '  OK   ' : '  BLAD ') + opis);
  if (szczegol) { console.log('       ' + szczegol); }
  if (!warunek) { bledy++; }
}
function wp(args) {
  try {
    return execFileSync('docker', ['exec', '-u', 'www-data', KONTENER, 'wp'].concat(args), { encoding: 'utf8' }).trim();
  } catch (e) { return ''; }
}
function licznikOdrzucen() {
  const s = wp(['option', 'get', 'chatl_blad_nonce', '--format=json']);
  try { return JSON.parse(s).ile; } catch (e) { return 0; }
}

(async () => {
  const user = process.env.WP_ADMIN_USER, pass = process.env.WP_ADMIN_PASS;
  if (!user || !pass) { console.error('Brak WP_ADMIN_USER/WP_ADMIN_PASS w env.'); process.exit(1); }
  wp(['option', 'delete', 'chatl_blad_nonce']);

  console.log('[A] serwer: zle zabezpieczenie -> rozpoznawalna odmowa + slad dla wlasciciela');
  const przed = licznikOdrzucen();
  const body = new URLSearchParams({
    action: 'chatl_pytanie',
    nonce: 'nonce-z-cache-sprzed-doby',
    sesja: 'test-nonce-0123456789abcdef0123',
    pytanie: 'ile kosztuje wasza usluga',
  });
  const odp = await fetch(BAZA + '/wp-admin/admin-ajax.php', { method: 'POST', body: body });
  const json = await odp.json();
  console.log('       HTTP ' + odp.status + '  ' + JSON.stringify(json));
  sprawdz('zly nonce konczy sie odmowa 403', odp.status === 403);
  sprawdz('odpowiedz to POPRAWNY JSON wtyczki, nie surowe -1', typeof json === 'object' && json !== null);
  sprawdz('odmowa ma kod "nonce" (po nim widget wie, ze ma ponowic)',
    json && json.data && json.data.kod === 'nonce');
  const po = licznikOdrzucen();
  sprawdz('odrzucenie zostalo ODNOTOWANE dla wlasciciela', po === przed + 1, 'licznik: ' + przed + ' -> ' + po);

  const b = await chromium.launch({ executablePath: wymagajChromium() });
  const p = await b.newPage({ viewport: { width: 1280, height: 900 } });

  console.log('\n[B] widget: pierwsza proba odbita, gosc i tak dostaje odpowiedz');
  /* Podkladamy DOKLADNIE to, co dzieje sie przy cache: pierwsze pytanie wraca
     jako surowe "-1" (tak odpowiada warstwa WordPressa/wtyczka bezpieczenstwa).
     Kolejne zadania przepuszczamy — w tym dociagniecie swiezego nonce'a. */
  let odbite = 0;
  let nonceDociagniety = 0;
  await p.route('**/wp-admin/admin-ajax.php', async (route) => {
    const dane = route.request().postData() || '';
    if (dane.indexOf('chatl_nonce') !== -1) { nonceDociagniety++; return route.continue(); }
    if (dane.indexOf('chatl_pytanie') !== -1 && odbite === 0) {
      odbite++;
      return route.fulfill({ status: 403, contentType: 'text/html', body: '-1' });
    }
    return route.continue();
  });

  await p.goto(BAZA + '/', { waitUntil: 'load' });
  await p.click('.chatl-babelek');
  await p.fill('.chatl-pole-pytania', 'ile kosztuje wasza usluga');
  await p.click('.chatl-wyslij-pytanie');
  await p.waitForFunction(
    () => document.querySelectorAll('.chatl-wiadomosci .chatl-bot').length >= 2,
    null, { timeout: 20000 }
  );
  const rozmowa = await p.$eval('.chatl-wiadomosci', (e) => e.textContent.replace(/\s+/g, ' ').trim());
  console.log('       pierwsza proba odbita: ' + odbite + ', swiezy nonce dociagniety: ' + nonceDociagniety);
  console.log('       ' + rozmowa.slice(-200));
  sprawdz('widget dociagnal swiezy nonce', nonceDociagniety >= 1);
  sprawdz('gosc NIE zobaczyl komunikatu o awarii', rozmowa.indexOf('Coś poszło nie tak') === -1);
  sprawdz('gosc dostal prawdziwa odpowiedz bota', rozmowa.length > 0 && !/^\s*$/.test(rozmowa.slice(-80)));
  await p.unroute('**/wp-admin/admin-ajax.php');

  console.log('\n[C] panel: wlasciciel widzi, ze gosciom odbija sie zabezpieczenie');
  await p.goto(BAZA + '/wp-login.php', { waitUntil: 'load' });
  await p.fill('#user_login', user);
  await p.fill('#user_pass', pass);
  await p.click('#wp-submit');
  await p.waitForLoadState('load');
  await p.goto(BAZA + '/wp-admin/admin.php?page=chatl-qa', { waitUntil: 'load' });
  const panel = await p.$eval('.wrap', (e) => e.textContent.replace(/\s+/g, ' ').trim());
  sprawdz('jest czerwone ostrzezenie o odrzuconych zabezpieczeniach',
    /Gościom nie działa czat/.test(panel));
  sprawdz('ostrzezenie podaje LICZBE odrzucen', /Odrzuconych żądań/.test(panel));
  sprawdz('ostrzezenie wskazuje przyczyne (cache) i co zrobic',
    /cache/i.test(panel) && /wyłącz cache/i.test(panel));

  const zamknij = await p.$('a[href*="chatl_nonce_zamknij"]');
  sprawdz('da sie ukryc ostrzezenie', zamknij !== null);
  if (zamknij) {
    await zamknij.click();
    await p.waitForLoadState('load');
    const poZamknieciu = await p.$eval('.wrap', (e) => e.textContent);
    sprawdz('po ukryciu ostrzezenie znika', !/Gościom nie działa czat/.test(poZamknieciu));
    sprawdz('znacznik skasowany z bazy', licznikOdrzucen() === 0);
  }

  await b.close();
  console.log('\n' + (bledy ? 'NIEPOWODZENIE: ' + bledy + ' krokow nie przeszlo' : 'WSZYSTKIE KROKI PRZESZLY'));
  process.exit(bledy ? 1 : 0);
})().catch((e) => { console.error(e); process.exit(1); });
