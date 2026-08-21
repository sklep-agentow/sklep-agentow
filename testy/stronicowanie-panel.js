/* Sprawdza w PRAWDZIWEJ przeglądarce, że strona druga list panelu ISTNIEJE
   i pokazuje INNE wiersze niż pierwsza — czyli że dane powyżej dotychczasowego
   limitu naprawdę dają się obejrzeć. Tego nie da się zmierzyć z PHP: liczy się
   to, co widzi właściciel po kliknięciu „2".
   Użycie: node testy/stronicowanie-panel.js [adres] */
const { chromium } = require('playwright-core');
const { wymagajChromium } = require('./lib/chromium');
const { execFileSync } = require('child_process');
const BAZA = process.argv[2] || 'http://localhost:8890';

let bledy = 0;
function sprawdz(opis, warunek, szczegol) {
  console.log((warunek ? '  OK   ' : '  BLAD ') + opis);
  if (!warunek) { bledy++; if (szczegol) { console.log('       ' + szczegol); } }
}
function wp(kod) {
  return execFileSync('docker', ['exec', '-u', 'www-data', 'strona_wp_wordpress', 'wp', 'eval', kod],
    { encoding: 'utf8' }).trim();
}
function sprzataj() {
  wp('global $wpdb; $wpdb->query( "DELETE FROM {$wpdb->prefix}chatl_zgloszenia WHERE email LIKE \'stronpanel-%\'" );');
}

(async () => {
  sprzataj();
  /* 205 zgłoszeń: o pięć więcej, niż mieści strona (200). Jednym zapytaniem,
     żeby test nie trwał minutę. */
  wp(`global $wpdb; $w = array(); $t = current_time('mysql');
      for ( $i = 1; $i <= 205; $i++ ) { $w[] = $wpdb->prepare( "(%s,%s,'','nowe',0,%s)", sprintf('stronpanel-%03d@example.test',$i), 'wiersz '.$i, $t ); }
      $wpdb->query( "INSERT INTO {$wpdb->prefix}chatl_zgloszenia (email,wiadomosc,sesja,status,podejrzane,utworzono) VALUES " . implode(',', $w) );`);

  const user = process.env.WP_ADMIN_USER, pass = process.env.WP_ADMIN_PASS;
  if (!user || !pass) { console.error('Brak WP_ADMIN_USER/WP_ADMIN_PASS w env.'); process.exit(1); }

  const b = await chromium.launch({ executablePath: wymagajChromium() });
  const ctx = await b.newContext({ viewport: { width: 1400, height: 1000 } });
  const p = await ctx.newPage();
  await p.goto(BAZA + '/wp-login.php', { waitUntil: 'load' });
  await p.fill('#user_login', user);
  await p.fill('#user_pass', pass);
  await p.click('#wp-submit');
  await p.waitForLoadState('load');

  const maileNaStronie = async () => p.$$eval('table.widefat tbody tr td:nth-child(3)',
    (k) => k.map((e) => e.textContent.trim()).filter((t) => t.indexOf('stronpanel-') === 0));

  await p.goto(BAZA + '/wp-admin/admin.php?page=chatl-zgloszenia', { waitUntil: 'load' });
  const s1 = await maileNaStronie();
  sprawdz('strona 1 pokazuje pełną stronę wpisów', s1.length === 200, 'wierszy: ' + s1.length);

  const pasek = await p.$('.tablenav-pages');
  sprawdz('pod listą jest pasek stron', pasek !== null);
  const tekstPaska = pasek ? await pasek.textContent() : '';
  sprawdz('pasek podaje liczbę wszystkich pozycji', /205/.test(tekstPaska), tekstPaska.trim());

  const link2 = await p.$('.tablenav-pages a.page-numbers:not(.next)');
  sprawdz('jest odsyłacz do strony drugiej', link2 !== null);
  if (link2) {
    await Promise.all([p.waitForLoadState('load'), link2.click()]);
  }
  const s2 = await maileNaStronie();
  sprawdz('strona 2 pokazuje resztę wpisów', s2.length === 5, 'wierszy: ' + s2.length);

  const wspolne = s2.filter((m) => s1.indexOf(m) !== -1);
  sprawdz('i ANI JEDEN wiersz nie powtarza się z pierwszej strony', wspolne.length === 0,
    'powtórzone: ' + wspolne.slice(0, 3).join(', '));
  sprawdz('obie strony razem dają komplet 205 wpisów',
    new Set(s1.concat(s2)).size === 205, 'unikalnych: ' + new Set(s1.concat(s2)).size);

  /* Wpis, który przed tą zmianą był NIEOSIĄGALNY — 205. od końca listy. */
  sprawdz('najstarszy wpis (dotąd poza zasięgiem) jest widoczny na stronie 2',
    s2.indexOf('stronpanel-001@example.test') !== -1, s2.join(', '));

  await b.close();
  sprzataj();
  console.log('\n' + (bledy ? 'NIEPOWODZENIE: ' + bledy + ' krokow nie przeszlo' : 'WSZYSTKIE KROKI PRZESZLY'));
  process.exit(bledy ? 1 : 0);
})();
