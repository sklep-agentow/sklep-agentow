/* Test K4 (CRUD produktów) na żywym stacku przez PRAWDZIWĄ przeglądarkę:
   happy path z realnym zipem + walidacje serwerowe (fetch z sesją, omija
   HTML5) + kryterium #6 W TEŚCIE (zamówienie-snapshot → edycja produktu
   przez formularz → wiersz zamówienia przed==po; atrapa zamówienia usuwana,
   żeby KPI dashboardu nie liczył śmiecia). Env: WP_ADMIN_USER, WP_ADMIN_PASS,
   ZIP (ścieżka), ZRZUT (ścieżka PNG; domyślnie tmpdir systemu).
   Wyjście: linie WYNIK:..., exit 0 gdy wszystkie OK. */
const { chromium } = require('playwright-core');
const { wymagajChromium } = require('./lib/chromium');
const { execFileSync } = require('child_process');
const BAZA = 'http://localhost:8890';
const ZIP = process.env.ZIP || 'boty/paczki/chatbot-live-1.2.1.zip';
const ZRZUT = process.env.ZRZUT || require('os').tmpdir() + '/produkty-lista.png';

// wp eval w kontenerze stacka; execFileSync z tablicą argumentów = zero shella i escapowania.
const wpEval = (kod) => execFileSync(
  'docker',
  ['exec', 'strona_wp_wordpress', 'wp', '--allow-root', '--path=/var/www/html', 'eval', kod],
  { encoding: 'utf8' }
).trim();

let bledy = 0;
const wynik = (nazwa, ok, extra = '') => { console.log(`WYNIK:${ok ? 'OK ' : 'PAD'} ${nazwa}${extra ? ' — ' + extra : ''}`); if (!ok) bledy++; };

(async () => {
  const b = await chromium.launch({ executablePath: wymagajChromium() });
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
  const produktId = parseInt((p.url().match(/produkt=(\d+)/) || [])[1] || '0', 10);
  wynik('happy: redirect niesie id nowego produktu', produktId > 0, `id=${produktId}`);

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

  // 3. Deaktywacja z listy (link z nonce); .first() — poprzednie przebiegi zostawiają bliźniacze wiersze.
  await p.goto(BAZA + '/wp-admin/admin.php?page=sklepag-produkty', { waitUntil: 'load' });
  const wiersz = p.locator('tr', { hasText: 'Segregator maila TEST-K4' }).first();
  await Promise.all([p.waitForURL(/sklepag_ok=przelaczono/), wiersz.locator('a', { hasText: 'Deaktywuj' }).click()]);
  await p.goto(BAZA + '/wp-admin/admin.php?page=sklepag-produkty', { waitUntil: 'load' });
  const badge = await p.locator('tr', { hasText: 'Segregator maila TEST-K4' }).first().locator('.sklepag-badge-szary').textContent().catch(() => '');
  wynik('deaktywacja: badge „wyłączony"', /wyłączony/.test(badge || ''));

  // 4. KRYTERIUM #6: edycja produktu NIE zmienia snapshotu w zamówieniach (recenzja PR #49).
  //    Setup: atrapa zamówienia ze snapshotem produktu z kroku 1 (INSERT wprost = stan „kupione przed edycją").
  const zamId = parseInt(wpEval(
    'global $wpdb; $p = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}sklepag_produkty WHERE id = %d", ' + produktId + ' ) );'
    + ' $wpdb->insert( $wpdb->prefix . "sklepag_zamowienia", array('
    + ' "stripe_session_id" => "cs_test_snapshot_" . wp_generate_password( 12, false, false ),'
    + ' "produkt_id" => $p->id, "produkt_nazwa_kupiona" => $p->nazwa,'
    + ' "produkt_cena_kupiona" => $p->cena_grosze, "produkt_plik_kupiony" => $p->plik_sciezka,'
    + ' "email_kupujacego" => "snapshot-test@seed.local", "kwota_grosze" => $p->cena_grosze,'
    + ' "waluta" => $p->waluta, "status" => "mail_wyslany",'
    + ' "utworzono" => current_time( "mysql" ), "zmieniono" => current_time( "mysql" ) ) );'
    + ' echo $wpdb->insert_id;'
  ), 10);
  const snapshotSql = 'global $wpdb; $z = $wpdb->get_row( $wpdb->prepare( "SELECT produkt_nazwa_kupiona, produkt_cena_kupiona, produkt_plik_kupiony FROM {$wpdb->prefix}sklepag_zamowienia WHERE id = %d", ' + zamId + ' ) ); echo $z->produkt_nazwa_kupiona . "|" . $z->produkt_cena_kupiona . "|" . $z->produkt_plik_kupiony;';
  const przed = wpEval(snapshotSql);
  //    Edycja przez PRAWDZIWY formularz (ścieżka handlera, nie SQL): nowa nazwa + nowa cena.
  await p.goto(BAZA + '/wp-admin/admin.php?page=sklepag-produkty&widok=formularz&produkt=' + produktId, { waitUntil: 'load' });
  await p.fill('#sklepag-nazwa', 'TEST-K4 PO EDYCJI');
  await p.fill('#sklepag-cena', '299.00');
  await Promise.all([p.waitForURL(/sklepag_ok=zapisano/), p.click('#submit')]);
  //    Próba kontrolna pomiaru: edycja MUSI była zajść w produkcie (inaczej „snapshot
  //    niezmieniony" i „edycja cicho odbita" dają to samo zielone).
  const produktPo = wpEval('global $wpdb; $p = $wpdb->get_row( $wpdb->prepare( "SELECT nazwa, cena_grosze FROM {$wpdb->prefix}sklepag_produkty WHERE id = %d", ' + produktId + ' ) ); echo $p->nazwa . "|" . $p->cena_grosze;');
  wynik('kontrola: edycja produktu FAKTYCZNIE zaszła', produktPo === 'TEST-K4 PO EDYCJI|29900', `produkt po edycji=[${produktPo}]`);
  const po = wpEval(snapshotSql);
  wynik('kryterium #6: snapshot zamówienia NIETKNIĘTY po edycji produktu', zamId > 0 && przed === po && przed.startsWith('Segregator maila TEST-K4|19900|'), `przed=[${przed}] po=[${po}]`);
  //    Sprzątanie atrapy (dashboard nie może liczyć testowego przychodu).
  wpEval('global $wpdb; $wpdb->delete( $wpdb->prefix . "sklepag_zamowienia", array( "id" => ' + zamId + ' ) ); echo "sprzatniete";');

  await p.screenshot({ path: ZRZUT, fullPage: true });
  await b.close();
  console.log(bledy === 0 ? 'PRODUKTY-TEST: KOMPLET OK' : `PRODUKTY-TEST: ${bledy} PADÓW`);
  process.exit(bledy === 0 ? 0 : 2);
})().catch(e => { console.error('BLAD TESTU:', e.message.split('\n')[0]); process.exit(1); });
