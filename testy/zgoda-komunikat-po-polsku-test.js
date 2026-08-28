/* Blokada zakupu bez zgody mowi do kupujacego PO POLSKU.
 *
 * Sklep jest w calosci polski, ale pole zgody stalo na samym `required`, wiec
 * komunikat rysowala przegladarka — po angielsku („Please check this box if you
 * want to proceed.”), zaleznie od jezyka przegladarki klienta. Byly to jedyne
 * obcojezyczne zdania, jakie widzial kupujacy — przy platnosci ORAZ przy wysylaniu
 * opinii (patrz sekcja [6] nizej).
 *
 * Test pilnuje DWOCH rzeczy naraz, bo jedna bez drugiej jest bezwartosciowa:
 *   1) zakup bez zgody nadal NIE przechodzi (obietnica prawna, nie kosmetyka),
 *   2) komunikat, ktory widzi czlowiek, jest po polsku i NIE jest natywnym
 *      dymkiem przegladarki.
 *
 * Serwer odrzuca zakup bez zgody niezaleznie od JS (`includes/zakup.php`), wiec
 * ten test dotyczy warstwy jezyka — ale wprost sprawdza, ze naprawa jezyka nie
 * rozszczelnila blokady.
 *
 * ⛔ Test obejmuje OBA formularze, ktore widzi kupujacy — strone produktu ORAZ formularz
 * opinii. Ten drugi rysuje sie samodzielnym dokumentem bez `wp_head()` (link z maila), wiec
 * nie widzi assetow sklepu i ma wlasna kopie procedury. Naprawa jednego bez drugiego to
 * zamkniecie EGZEMPLARZA zamiast KLASY — recenzja #203 zlapala dokladnie to.
 *
 * Wymaga playwright-core i dzialajacego stacku. Bez logowania (strony publiczne).
 * Uzycie: node testy/zgoda-komunikat-po-polsku-test.js [adres]
 */
const { chromium } = require('playwright-core');
const { wymagajChromium } = require('./lib/chromium');
const { execFileSync } = require('child_process');
/* Token opinii jest jednorazowym poswiadczeniem kupujacego — do logu (takze do przebiegu
   CI) idzie wylacznie w wersji zamaskowanej. */
const bezTokenu = (s) => String(s).replace(/([?&]t=)[^&\s]+/g, '$1…');
const KONTENER = 'strona_wp_wordpress';
const BAZA = process.argv[2] || 'http://localhost:8890';
const PRODUKT = BAZA + '/sklep/produkt/chatbot-live/';

let bledy = 0;
function sprawdz(opis, warunek, szczegol) {
  console.log((warunek ? '  OK   ' : '  BLAD ') + opis);
  if (szczegol) { console.log('       ' + szczegol); }
  if (!warunek) { bledy++; }
}

(async () => {
  const b = await chromium.launch({ executablePath: wymagajChromium() });
  const p = await b.newPage({ viewport: { width: 1280, height: 900 } });
  p.setDefaultTimeout(60000); // 2 vCPU: domyslne 30 s bywa za malo pod pelna linia bazowa
  /* Zaden test nie ma prawa wyjsc na prawdziwego Stripe'a. */
  await p.route('**://*.stripe.com/**', (r) => r.abort());

  console.log('[1] pole zgody istnieje, jest puste i wymagane');
  await p.goto(PRODUKT, { waitUntil: 'load' });
  const pole = await p.$('input[name="zgoda"]');
  sprawdz('pole zgody jest na stronie produktu', pole !== null);
  if (!pole) { await b.close(); process.exit(1); }
  const stan = await pole.evaluate((e) => ({ checked: e.checked, required: e.required }));
  sprawdz('zgoda nie jest zaznaczona z gory', stan.checked === false);
  sprawdz('zgoda jest polem wymaganym', stan.required === true);

  console.log('[2] proba zakupu BEZ zgody');
  const przed = p.url();
  await p.click('button[type="submit"], input[type="submit"]');
  await p.waitForTimeout(1500);

  console.log('[3] blokada trzyma — to jest obietnica wazniejsza niz jezyk');
  sprawdz('nie wyszlismy ze strony produktu', p.url() === przed, 'url: ' + p.url());

  console.log('[4] komunikat dla czlowieka jest po polsku');
  const kom = await pole.evaluate((e) => e.validationMessage || '');
  sprawdz('komunikat nie jest pusty', kom.trim().length > 0, 'komunikat: "' + kom + '"');
  sprawdz('komunikat NIE jest natywnym dymkiem po angielsku',
    !/please|check this box|proceed|fill out this field/i.test(kom),
    'komunikat: "' + kom + '"');
  sprawdz('komunikat jest po polsku (ma polskie znaki albo polskie slowo kluczowe)',
    /[ąćęłńóśźż]/i.test(kom) || /\b(zaznacz|zgod|musisz|potwierdz)/i.test(kom),
    'komunikat: "' + kom + '"');

  console.log('[5] po zaznaczeniu zgody komunikat znika (nie zostaje przyklejony)');
  await pole.check();
  const poZaznaczeniu = await pole.evaluate((e) => { e.checkValidity(); return e.validationMessage || ''; });
  sprawdz('zaznaczona zgoda nie zostawia komunikatu bledu', poZaznaczeniu.trim() === '',
    'komunikat: "' + poZaznaczeniu + '"');

  console.log('[6] FORMULARZ OPINII — ten sam blad w drugim miejscu (klasa, nie egzemplarz)');
  /* ⛔ `sklepag_opinia_zalazek()` NIE JEST ODCZYTEM — mintuje nowy token i nadpisuje
     `token_hash` istniejacego wiersza `zalazek` (`opinie.php:54-63`). Zawolane wprost
     unieważniloby link, ktory kupujacy dostal mailem przy zakupie (`zakup.php:854`).
     Dlatego robimy to, co produkt robi u siebie w `admin-zamowienie.php:64-67`:
     zapamietujemy stan SPRZED rotacji i oddajemy go po pomiarze. Gdy wiersza nie bylo,
     kasujemy ten, ktory sami wstawilismy — test nie zostawia po sobie zaproszen do opinii,
     ktorych nikt nie wyslal. Przywrocenie leci w `finally`, wiec dziala takze gdy test padnie. */
  const wp = (kod) => execFileSync('docker', ['exec', '-u', 'www-data', KONTENER, 'wp', 'eval', kod], { encoding: 'utf8' }).trim();
  const surowe = wp(`
    global $wpdb;
    $tab = $wpdb->prefix . 'sklepag_opinie';
    foreach ( $wpdb->get_results( "SELECT id,produkt_id FROM {$wpdb->prefix}sklepag_zamowienia ORDER BY id DESC LIMIT 12" ) as $z ) {
      $przed = $wpdb->get_row( $wpdb->prepare( "SELECT token_hash, token_wygasa, zmieniono FROM {$tab} WHERE zamowienie_id=%d AND status='zalazek'", $z->id ) );
      $t = sklepag_opinia_zalazek( (int) $z->id, (int) $z->produkt_id );
      if ( '' === $t ) { continue; }
      echo implode( '|', array(
        admin_url( 'admin-post.php' ) . '?action=sklepag_ocen&z=' . (int) $z->id . '&t=' . $t,
        (int) $z->id,
        $przed ? $przed->token_hash  : '',
        $przed ? $przed->token_wygasa : '',
        $przed ? $przed->zmieniono    : '',
        $przed ? '1' : '0'
      ) );
      return;
    }
  `);
  const [link, zamId, hashPrzed, wygasaPrzed, zmienionoPrzed, bylWiersz] = surowe.split('|');
  const przywroc = () => {
    if (!zamId) { return; }
    try {
      wp(bylWiersz === '1'
        ? `global $wpdb; $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->prefix}sklepag_opinie SET token_hash=%s, token_wygasa=%s, zmieniono=%s WHERE zamowienie_id=%d AND status='zalazek'", '${hashPrzed}', '${wygasaPrzed}', '${zmienionoPrzed}', ${zamId} ) ); echo 'ODDANE';`
        : `global $wpdb; $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}sklepag_opinie WHERE zamowienie_id=%d AND status='zalazek'", ${zamId} ) ); echo 'SKASOWANE';`);
    } catch (e) { console.error('  UWAGA: nie udalo sie oddac stanu tokenu opinii dla zamowienia ' + zamId + ': ' + e.message); }
  };

  try {
    sprawdz('udalo sie zbudowac link do formularza opinii', /action=sklepag_ocen/.test(link || ''),
      link ? bezTokenu(link) : '(pusty — wszystkie zamowienia maja juz opinie)');

    if (/action=sklepag_ocen/.test(link || '')) {
      await p.goto(link, { waitUntil: 'load' });
      const przedOpinia = p.url();
      await p.click('button[type="submit"]');
      await p.waitForTimeout(1200);
      sprawdz('formularz opinii nie przeszedl z pustymi polami', p.url() === przedOpinia, 'url: ' + bezTokenu(p.url()));

      for (const nazwa of ['sklepag_imie', 'sklepag_tresc', 'sklepag_zgoda']) {
        const kom = await p.evaluate((n) => {
          const e = document.querySelector('[name="' + n + '"]');
          if (!e) { return null; }
          e.checkValidity();
          return e.validationMessage || '';
        }, nazwa);
        sprawdz(nazwa + ': komunikat NIE jest natywnym dymkiem po angielsku',
          kom !== null && !/please|fill out this field|check this box|proceed/i.test(kom),
          'komunikat: "' + kom + '"');
        sprawdz(nazwa + ': komunikat jest po polsku',
          kom !== null && /[ąćęłńóśźż]/i.test(kom), 'komunikat: "' + kom + '"');
      }

      console.log('[7] wypelnione pola nie zostawiaja przyklejonego bledu');
      await p.fill('[name="sklepag_imie"]', 'Sonda');
      await p.fill('[name="sklepag_tresc"]', 'Tresc probna, dosc dluga zeby przejsc walidacje.');
      await p.check('[name="sklepag_zgoda"]');
      const resztki = await p.evaluate(() => ['sklepag_imie', 'sklepag_tresc', 'sklepag_zgoda']
        .map((n) => { const e = document.querySelector('[name="' + n + '"]'); e.checkValidity(); return e.validationMessage; })
        .filter((m) => m !== ''));
      sprawdz('po wypelnieniu zaden komunikat nie zostal przyklejony', resztki.length === 0, JSON.stringify(resztki));
      /* ⛔ Formularza NIE wysylamy: wyslanie zamknieloby zalazek na dobre, a wtedy zadne
         przywrocenie by juz nie pomoglo. */
    }
  } finally {
    przywroc();
  }

  console.log('[8] stan tokenu opinii oddany — link kupujacego dziala jak przed testem');
  const poTescie = zamId ? wp(`
    global $wpdb;
    $r = $wpdb->get_row( $wpdb->prepare( "SELECT token_hash FROM {$wpdb->prefix}sklepag_opinie WHERE zamowienie_id=%d AND status='zalazek'", ${zamId} ) );
    echo $r ? $r->token_hash : 'BRAK-WIERSZA';
  `) : 'BRAK-WIERSZA';
  sprawdz('token opinii wrocil do stanu sprzed testu',
    bylWiersz === '1' ? poTescie === hashPrzed : poTescie === 'BRAK-WIERSZA',
    bylWiersz === '1' ? ('zgodny ze skrotem sprzed testu: ' + (poTescie === hashPrzed)) : ('wiersz wstawiony przez test skasowany: ' + (poTescie === 'BRAK-WIERSZA')));

  await b.close();
  console.log('\n' + (bledy ? 'NIEPOWODZENIE: ' + bledy + ' krokow nie przeszlo' : 'WSZYSTKIE KROKI PRZESZLY'));
  process.exit(bledy ? 1 : 0);
})().catch((e) => { console.error(e); process.exit(1); });
