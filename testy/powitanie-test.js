/* Ekran powitalny po aktywacji — pelny cykl zycia.
 *
 * Sprawdza obietnice, ktora latwo zlamac: ekran ma pokazac sie RAZ po
 * aktywacji, dac sie zamknac i NIE WRACAC — takze po ponownej aktywacji
 * wtyczki — a pamiec ma byc PER UZYTKOWNIK, nie globalna.
 *
 * Aktywacje/deaktywacje robi wp-cli w kontenerze (test steruje stanem, zamiast
 * zakladac, ze ktos ustawil go recznie).
 *
 * Wymaga playwright-core, dzialajacego stacku i WP_ADMIN_USER/WP_ADMIN_PASS.
 * Uzycie: node testy/powitanie-test.js [adres]
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
  return execFileSync('docker', ['exec', '-u', 'www-data', KONTENER, 'wp'].concat(args), { encoding: 'utf8' }).trim();
}
/* Kasowanie nieistniejacego pola to dla wp-cli blad, a dla nas poprawny stan
   startowy ("tego pola i tak ma nie byc") — dlatego osobny, wybaczajacy wariant. */
function wpMiekko(args) {
  try { return wp(args); } catch (e) { return ''; }
}

(async () => {
  const user = process.env.WP_ADMIN_USER, pass = process.env.WP_ADMIN_PASS;
  if (!user || !pass) { console.error('Brak WP_ADMIN_USER/WP_ADMIN_PASS w env.'); process.exit(1); }

  /* Stan startowy: ten administrator NIE widzial jeszcze powitania. */
  wpMiekko(['user', 'meta', 'delete', user, 'chatl_powitanie_zamkniete']);
  const b = await chromium.launch({ executablePath: wymagajChromium() });
  const p = await b.newPage({ viewport: { width: 1280, height: 900 } });
  await p.goto(BAZA + '/wp-login.php', { waitUntil: 'load' });
  await p.fill('#user_login', user);
  await p.fill('#user_pass', pass);
  await p.click('#wp-submit');
  await p.waitForLoadState('load');

  console.log('[1] swieza aktywacja prowadzi na ekran powitalny');
  wp(['plugin', 'deactivate', 'chatbot-live']);
  wp(['plugin', 'activate', 'chatbot-live']);
  await p.goto(BAZA + '/wp-admin/', { waitUntil: 'load' });
  sprawdz('po aktywacji ladujemy na stronie powitania', /page=chatl-powitanie/.test(p.url()), p.url());
  const tresc = await p.$eval('.wrap', (e) => e.textContent.replace(/\s+/g, ' ').trim());
  sprawdz('jest podziekowanie', /Dziękujemy/.test(tresc));
  sprawdz('sa trzy kroki', /Krok 1[\s\S]*Krok 2[\s\S]*Krok 3/.test(tresc));
  sprawdz('jest odnosnik do ustawien', (await p.$('a[href*="page=chatl-ustawienia"]')) !== null);
  sprawdz('jest odnosnik do bazy Q&A', (await p.$('a[href*="page=chatl-qa"]')) !== null);
  sprawdz('powitanie wskazuje pliki instrukcji', /INSTRUKCJA\.md/.test(tresc) && /CHANGELOG\.md/.test(tresc));
  sprawdz('strony powitania NIE MA w menu wtyczki',
    (await p.$$eval('#adminmenu a', (a) => a.map((e) => e.getAttribute('href') || '')))
      .every((h) => h.indexOf('page=chatl-powitanie') === -1));

  console.log('\n[2] ekran nie wraca przy kolejnym wejsciu do panelu (bez zamykania)');
  await p.goto(BAZA + '/wp-admin/', { waitUntil: 'load' });
  sprawdz('drugie wejscie do panelu juz nie przekierowuje', !/page=chatl-powitanie/.test(p.url()), p.url());

  console.log('\n[3] przycisk zamkniecia zapamietuje decyzje');
  await p.goto(BAZA + '/wp-admin/admin.php?page=chatl-powitanie', { waitUntil: 'load' });
  await p.click('a.button-primary[href*="chatl_powitanie_zamknij"]');
  await p.waitForLoadState('load');
  sprawdz('po zamknieciu ladujemy w bazie Q&A', /page=chatl-qa/.test(p.url()), p.url());
  const meta = wpMiekko(['user', 'meta', 'get', user, 'chatl_powitanie_zamkniete']);
  sprawdz('decyzja zapisana w metadanych TEGO uzytkownika', '1' === meta, 'meta = ' + JSON.stringify(meta));

  console.log('\n[4] po zamknieciu nie wraca NAWET po ponownej aktywacji');
  wp(['plugin', 'deactivate', 'chatbot-live']);
  wp(['plugin', 'activate', 'chatbot-live']);
  await p.goto(BAZA + '/wp-admin/', { waitUntil: 'load' });
  sprawdz('ponowna aktywacja nie przywraca ekranu', !/page=chatl-powitanie/.test(p.url()), p.url());

  console.log('\n[5] pamiec jest PER UZYTKOWNIK, nie globalna');
  /* Kasujemy metadane tego konta i aktywujemy ponownie: gdyby zamkniecie bylo
     zapisane globalnie (w opcji), ekran juz by nie wrocil mimo czystych
     metadanych — i ten krok by padl. */
  wpMiekko(['user', 'meta', 'delete', user, 'chatl_powitanie_zamkniete']);
  wp(['plugin', 'deactivate', 'chatbot-live']);
  wp(['plugin', 'activate', 'chatbot-live']);
  await p.goto(BAZA + '/wp-admin/', { waitUntil: 'load' });
  sprawdz('konto z czystymi metadanymi znowu widzi powitanie', /page=chatl-powitanie/.test(p.url()), p.url());

  /* Sprzatanie: zostawiamy stack tak, zeby powitanie nie wyskakiwalo kolejnym testom. */
  await p.goto(BAZA + '/wp-admin/admin.php?page=chatl-powitanie', { waitUntil: 'load' });
  await p.click('a.button-primary[href*="chatl_powitanie_zamknij"]');
  await p.waitForLoadState('load');

  await b.close();
  console.log('\n' + (bledy ? 'NIEPOWODZENIE: ' + bledy + ' krokow nie przeszlo' : 'WSZYSTKIE KROKI PRZESZLY'));
  process.exit(bledy ? 1 : 0);
})().catch((e) => { console.error(e); process.exit(1); });
