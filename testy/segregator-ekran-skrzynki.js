/* STRAŻNIK pierwszych minut na ekranie „Skrzynka (IMAP)" (WK-5, WK-15, WK-17).
 *
 * Dziennik kupującego (audyt/boty/WCIELENIE-KUPUJACY.md, 19:55-19:56):
 *   - wypełnił formularz, „Sprawdź połączenie" został szary i NIC nie mówiło
 *     dlaczego (WK-5) — a powodem jest to, że trzeba najpierw ZAPISAĆ;
 *   - ekran nie mówił, skąd wziąć „hasło aplikacji" (WK-15);
 *   - po „Zapisz połączenie" nie zobaczył potwierdzenia (WK-17) — komunikat
 *     ISTNIAŁ, ale renderował się w środku strony jako szósty pasek, pod
 *     dwoma paskami WordPressa i trzema paskami wtyczki.
 *
 * Co sprawdza:
 *   [1] przy niezapisanej skrzynce opis przy szarym przycisku mówi o ZAPISIE
 *       i wymienia brakujące pola;
 *   [2] opis pola hasła mówi, SKĄD je wziąć, i odsyła do sekcji instrukcji
 *       po TYTULE (nie po numerze — numery przesuwają się przy scaleniach);
 *   [3] po zapisie zielony komunikat jest widoczny i stoi PRZED paskami
 *       wtyczki (błąd AI, błąd bazy, wskazówka startowa) — mierzone pozycją
 *       w DOM, nie pikselami;
 *   [4] (przypadek bez wady) po zapisie kompletnych danych przycisk
 *       „Sprawdź połączenie" jest aktywny, a opis wraca do wersji normalnej;
 *   [5] komunikat jest jednorazowy — po odświeżeniu strony już go nie ma.
 *
 * Test ODDAJE wspólnemu stosowi ustawienia skrzynki i hasło sprzed próby.
 * Uruchomienie (WYMAGA flock — stos i limiter logowań są wspólne):
 *   set -a; . /root/.claude/sklep-admin-roboczy.env; set +a
 *   flock /root/praca/zlecenia/wymiana/stos.lock node testy/segregator-ekran-skrzynki.js
 * EXIT 0 = wszystko OK, 1 = któryś punkt padł.
 */
const { chromium } = require('playwright-core');
const { wymagajChromium } = require('./lib/chromium');
const { execFileSync } = require('child_process');
const BAZA = process.argv[2] || 'http://localhost:8890';
const KONTENER = 'strona_wp_wordpress';

let bledy = 0;
function sprawdz(opis, warunek, szczegol) {
  console.log((warunek ? '  OK   ' : '  BLAD ') + opis);
  if (szczegol) { console.log('       ' + String(szczegol).slice(0, 300)); }
  if (!warunek) { bledy++; }
}
/* `wp eval` = WP-CLI z PHP-em wpisanym W TYM PLIKU (stały tekst, bez danych
   z zewnątrz) — ten sam sposób, co w pozostałych testach repo. Dane wejściowe
   idą przez plik JSON, nie przez sklejanie napisów. */
function wp(args) {
  return execFileSync('docker', ['exec', '-u', 'www-data', KONTENER, 'wp', '--path=/var/www/html'].concat(args), { encoding: 'utf8' }).trim();
}

(async () => {
  const user = process.env.WP_ADMIN_USER, pass = process.env.WP_ADMIN_PASS;
  if (!user || !pass) { console.error('Brak WP_ADMIN_USER/WP_ADMIN_PASS w env.'); process.exit(1); }

  /* Stan sprzed próby wraca ZAWSZE — to wspólny stos warsztatowy. */
  const stan = wp(['eval', 'echo json_encode( array( "u" => get_option( "segm_skrzynka", null ), "h" => get_option( "segm_imap_haslo", null ) ) );']);
  const b = await chromium.launch({ executablePath: wymagajChromium() });
  const p = await b.newPage({ viewport: { width: 1280, height: 1000 } });
  try {
    await p.goto(BAZA + '/wp-login.php', { waitUntil: 'load' });
    await p.fill('#user_login', user);
    await p.fill('#user_pass', pass);
    await p.click('#wp-submit');
    await p.waitForLoadState('load');

    console.log('[1] skrzynka NIEskonfigurowana: przycisk „Sprawdz polaczenie" mowi, dlaczego jest szary');
    wp(['option', 'delete', 'segm_skrzynka']);
    wp(['option', 'delete', 'segm_imap_haslo']);
    await p.goto(BAZA + '/wp-admin/admin.php?page=segm-skrzynka', { waitUntil: 'load' });
    const formTest = 'form:has(input[name="action"][value="segm_skrzynka_test"])';
    const wylaczony = await p.$eval(formTest + ' input[type="submit"]', (e) => e.disabled);
    const opis = await p.$eval(formTest + ' .description', (e) => e.innerText.replace(/\s+/g, ' ').trim());
    sprawdz('przycisk jest nieaktywny (skrzynka niezapisana)', wylaczony === true);
    sprawdz('opis mowi o ZAPISANIU danych, nie tylko o uzupelnieniu pol', /ZAPISANE|Zapisz połączenie/.test(opis), opis);
    sprawdz('opis wymienia brakujace pola', /serwer IMAP/.test(opis) && /login/.test(opis) && /hasło aplikacji/.test(opis), opis);

    console.log('\n[2] pole hasla mowi, SKAD wziac haslo aplikacji, i odsyla po TYTULE sekcji');
    const opisHasla = await p.$eval('#segm-haslo', (e) => (e.closest('td') || e.parentElement).innerText.replace(/\s+/g, ' ').trim());
    sprawdz('jest zdanie „Skąd je wziąć"', /Skąd je wziąć/.test(opisHasla), opisHasla.slice(0, 200));
    sprawdz('podana jest sciezka u dostawcy (Gmail)', /Hasła do aplikacji/.test(opisHasla));
    sprawdz('odsylacz do instrukcji po TYTULE sekcji, nie po numerze',
      /INSTRUKCJA\.md/.test(opisHasla) && /Hasło aplikacji, nigdy hasło do konta/.test(opisHasla) && !/rozdział\s*\d/.test(opisHasla));

    console.log('\n[3] po zapisie komunikat jest widoczny i stoi PRZED paskami wtyczki');
    await p.fill('#segm-host', 'segm_imap');
    await p.fill('#segm-port', '3143');
    await p.selectOption('#segm-szyfrowanie', 'brak').catch(() => {});
    await p.fill('#segm-login', 'szef');
    await p.fill('#segm-haslo', 'tajne1234');
    await p.click('form:has(input[name="action"][value="segm_skrzynka_zapisz"]) input[type="submit"]');
    await p.waitForLoadState('load');
    const paski = await p.$$eval('#wpbody-content .notice', (ns) => ns.map((n) => ({
      klasa: n.className,
      tekst: n.innerText.replace(/\s+/g, ' ').trim().slice(0, 80),
    })));
    paski.forEach((n, i) => console.log('       [' + i + '] ' + n.klasa + ' :: ' + n.tekst));
    const idxOk = paski.findIndex((n) => /notice-success/.test(n.klasa) && /Zapisano/.test(n.tekst));
    const idxWtyczki = paski.findIndex((n) => /Segregator maila: zapis do bazy|Tryb AI nie działa|Zacznij od próbki/.test(n.tekst));
    sprawdz('zielony komunikat „Zapisano…" jest na stronie', idxOk >= 0);
    sprawdz('komunikat jest WIDOCZNY', idxOk >= 0 && (await p.$eval('.notice-success', (e) => e.offsetParent !== null)));
    sprawdz('komunikat stoi PRZED paskami wtyczki (bledy AI/bazy, wskazowka)',
      idxOk >= 0 && (idxWtyczki === -1 || idxOk < idxWtyczki), 'komunikat=' + idxOk + ', pierwszy pasek wtyczki=' + idxWtyczki);

    console.log('\n[4] BEZ WADY: po zapisie kompletnych danych przycisk dziala i opis wraca do normalnego');
    const wylaczony2 = await p.$eval(formTest + ' input[type="submit"]', (e) => e.disabled);
    const opis2 = await p.$eval(formTest + ' .description', (e) => e.innerText.replace(/\s+/g, ' ').trim());
    sprawdz('przycisk „Sprawdz polaczenie" jest aktywny', wylaczony2 === false);
    sprawdz('opis nie straszy juz zapisem', /Łączy się naprawdę/.test(opis2), opis2);

    console.log('\n[5] komunikat jest jednorazowy (transient skasowany po pokazaniu)');
    await p.reload({ waitUntil: 'load' });
    sprawdz('po odswiezeniu komunikatu juz nie ma', (await p.$('.notice-success')) === null);
  } finally {
    await b.close();
    require('fs').writeFileSync('/tmp/segm-ekran-stan.json', stan);
    execFileSync('docker', ['cp', '/tmp/segm-ekran-stan.json', KONTENER + ':/tmp/segm-ekran-stan.json']);
    console.log('\n' + wp(['eval',
      '$s = json_decode( file_get_contents( "/tmp/segm-ekran-stan.json" ), true );' +
      'if ( null === $s["u"] ) { delete_option( "segm_skrzynka" ); } else { update_option( "segm_skrzynka", $s["u"] ); }' +
      'delete_option( "segm_imap_haslo" );' +
      'if ( null !== $s["h"] ) { add_option( "segm_imap_haslo", $s["h"], "", false ); }' +
      'echo "stan skrzynki sprzed proby przywrocony";',
    ]));
  }
  console.log(bledy ? '\nBLEDOW: ' + bledy : '\nWSZYSTKIE KROKI PRZESZLY');
  process.exit(bledy ? 1 : 0);
})();
