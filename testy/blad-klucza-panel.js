/* L4 — czy WLASCICIEL naprawde to zobaczy. Straznik, kod wyjscia 1.
 *
 * Czesc logiczna jest w testy/blad-klucza-test.php; tutaj sprawdzamy to, czego
 * tamten nie dosiegnie: czerwony pasek w panelu i przycisk "Przetestuj klucz".
 *
 * Zeby test byl deterministyczny i nie kosztowal tokenow, podkladamy wtyczke
 * mu-plugin, ktora filtrem chatl_ai_url kieruje wywolanie na petle zwrotna —
 * ten sam punkt rozszerzenia, ktorym wtyczka wpuszcza proxy.
 *
 * Wymaga playwright-core, stacku i WP_ADMIN_USER/WP_ADMIN_PASS.
 */
const { chromium } = require('playwright-core');
const { wymagajChromium } = require('./lib/chromium');
const { zaloguj } = require('./lib/logowanie');
const { execFileSync } = require('child_process');
const BAZA = process.argv[2] || 'http://localhost:8890';
const KONTENER = 'strona_wp_wordpress';
const MU = '/var/www/html/wp-content/mu-plugins/chatl-test-api.php';

let bledy = 0;
function sprawdz(opis, warunek, szczegol) {
  console.log((warunek ? '  OK   ' : '  BLAD ') + opis);
  if (szczegol) { console.log('       ' + szczegol); }
  if (!warunek) { bledy++; }
}
function dexec(args) {
  try { return execFileSync('docker', ['exec'].concat(args), { encoding: 'utf8' }).trim(); } catch (e) { return ''; }
}
function wp(args) { return dexec(['-u', 'www-data', KONTENER, 'wp'].concat(args)); }

function podlozApi(adres) {
  const kod = "<?php add_filter('chatl_ai_url', function () { return '" + adres + "'; }, 99);";
  dexec([KONTENER, 'mkdir', '-p', '/var/www/html/wp-content/mu-plugins']);
  dexec([KONTENER, 'bash', '-c', "printf '%s' " + JSON.stringify(kod) + " > " + MU]);
  dexec([KONTENER, 'chown', 'www-data:www-data', MU]);
}
function sprzatnijApi() { dexec([KONTENER, 'rm', '-f', MU]); }

(async () => {
  const user = process.env.WP_ADMIN_USER, pass = process.env.WP_ADMIN_PASS;
  if (!user || !pass) { console.error('Brak WP_ADMIN_USER/WP_ADMIN_PASS w env.'); process.exit(1); }

  const b = await chromium.launch({ executablePath: wymagajChromium() });
  const p = await b.newPage({ viewport: { width: 1400, height: 950 } });
  p.setDefaultTimeout(60000); // 60 s: pelna linia bazowa na 2 vCPU dlawi 30-sekundowy default (24.08)
  await zaloguj(p, BAZA, user, pass);

  console.log('[1] czerwony pasek, gdy ostatnie wywolanie API padlo');
  wp(['option', 'update', 'chatl_blad_ai',
    '{"kiedy":"2026-08-20 12:00:00","kod":401,"tresc":"invalid x-api-key"}',
    '--format=json', '--autoload=no']);
  await p.goto(BAZA + '/wp-admin/admin.php?page=chatl-qa', { waitUntil: 'load' });
  const panel = await p.$eval('#wpbody-content', (e) => e.textContent.replace(/\s+/g, ' ').trim());
  sprawdz('jest pasek "Tryb AI nie dziala"', /Tryb AI nie działa/.test(panel));
  sprawdz('pasek mowi, ze bot odpowiada z regul', /odpowiada wyłącznie z reguł/.test(panel));
  sprawdz('pasek podaje kod HTTP', /401/.test(panel));
  sprawdz('pasek podaje date ostatniego bledu', /2026-08-20 12:00:00/.test(panel));
  sprawdz('przy 401 pasek mowi WPROST, ze klucz jest zly',
    /klucz|wygasł/i.test(panel), panel.slice(panel.indexOf('Tryb AI nie działa'), panel.indexOf('Tryb AI nie działa') + 260));
  sprawdz('pasek prowadzi do ustawien', (await p.$('.notice-error a[href*="page=chatl-ustawienia"]')) !== null);

  console.log('\n[2] przycisk "Przetestuj klucz" w ustawieniach');
  await p.goto(BAZA + '/wp-admin/admin.php?page=chatl-ustawienia', { waitUntil: 'load' });
  const przycisk = await p.$('a[href*="chatl_test_klucza"]');
  sprawdz('jest przycisk testu klucza', przycisk !== null);
  const opisTestu = await p.$eval('#wpbody-content', (e) => e.textContent.replace(/\s+/g, ' ').trim());
  sprawdz('opis mowi, ze test jest tani i nie zjada limitu',
    /max_tokens = 1/.test(opisTestu) && /nie zużywa dziennego limitu/.test(opisTestu));

  console.log('\n[3] test klucza przy NIEDZIALAJACYM API konczy sie czytelnym bledem');
  podlozApi('http://127.0.0.1/nie-ma-takiej-strony-chatl-test/');
  await p.click('a[href*="chatl_test_klucza"]');
  await p.waitForLoadState('load');
  const poTescie = await p.$eval('#wpbody-content', (e) => e.textContent.replace(/\s+/g, ' ').trim());
  console.log('       ' + (poTescie.match(/API[^.]{0,120}\./) || [''])[0]);
  sprawdz('wynik testu pokazany jako blad', (await p.$('.notice-error')) !== null);
  sprawdz('blad mowi, co konkretnie odpowiedzialo API', /404/.test(poTescie));

  console.log('\n[4] test klucza przy DZIALAJACYM API zdejmuje pasek');
  podlozApi('http://127.0.0.1/');
  await p.click('a[href*="chatl_test_klucza"]');
  await p.waitForLoadState('load');
  const poSukcesie = await p.$eval('#wpbody-content', (e) => e.textContent.replace(/\s+/g, ' ').trim());
  sprawdz('wynik testu pokazany jako sukces', /Klucz API działa/.test(poSukcesie));
  sprawdz('czerwony pasek zniknal', !/Tryb AI nie działa/.test(poSukcesie));
  sprawdz('znacznik skasowany z bazy', '' === wp(['option', 'get', 'chatl_blad_ai']));

  sprzatnijApi();
  await b.close();
  console.log('\n' + (bledy ? 'NIEPOWODZENIE: ' + bledy + ' krokow nie przeszlo' : 'WSZYSTKIE KROKI PRZESZLY'));
  process.exit(bledy ? 1 : 0);
})().catch((e) => { sprzatnijApi(); console.error(e); process.exit(1); });
