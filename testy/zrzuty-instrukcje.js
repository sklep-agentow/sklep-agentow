/* Zrzuty ekranów do INSTRUKCJI obu botów — maszynowo, przez prawdziwą przeglądarkę.
   NARZĘDZIE (nie test): linia bazowa pomija je jawnie, jak zrzuty-panelu.js.

   Czemu skryptem, a nie ręcznie: zdjęcia starzeją się przy każdej zmianie UI,
   a ręczne kadry rozjeżdżają się między sobą. Wzorzec i sześć pułapek z MP:
   kadr DO ELEMENTU (#wpbody-content), nie pełna strona (stopki/wizytówki);
   przeglądarka po polsku (pola dat!); czekanie na DOWÓD ekranu (selektor),
   nie na "network idle"; front: okno czatu fotografowane jako element.

   Użycie: WP_ADMIN_USER=... WP_ADMIN_PASS=... node testy/zrzuty-instrukcje.js
   Wyjście: wtyczki/chatbot-live/zrzuty/*.png + wtyczki/segregator-maila/zrzuty/*.png */
const { chromium } = require('playwright-core');
const { wymagajChromium } = require('./lib/chromium');
const { zaloguj } = require('./lib/logowanie');
const fs = require('fs');
const BAZA = process.argv[2] || 'http://localhost:8890';

const ADMIN = [
  /* [wtyczka, nazwa pliku, slug strony, selektor-DOWÓD ekranu] */
  /* [wtyczka, plik, slug, DOWOD ekranu, KADR (opcjonalnie — domyslnie caly #wpbody-content)] */
  ['chatbot-live', 'ustawienia', 'chatl-ustawienia', '#wpbody-content form', '#wpbody-content form'],
  ['chatbot-live', 'zgloszenia', 'chatl-zgloszenia', '#wpbody-content table'],
  /* Statystyki: kadr GORNY (kafle + ruch dzienny) — dolna lista luk niesie
     tresci pytan z materialu testowego stosu, a stopka WP wizytowke silnika. */
  ['chatbot-live', 'statystyki', 'chatl-statystyki', '#wpbody-content', '#wpbody-content', 790],
  ['segregator-maila', 'skrzynka-imap', 'segm-skrzynka', '#wpbody-content form', '#wpbody-content form'],
  ['segregator-maila', 'reguly', 'segm-reguly', '#wpbody-content table, #wpbody-content form'],
  ['segregator-maila', 'kategorie', 'segm-kategorie', '#wpbody-content'],
  ['segregator-maila', 'probka-demo', 'segm-probka', '#wpbody-content'],
  ['segregator-maila', 'przebiegi', 'segm-przebiegi', '#wpbody-content'],
  ['segregator-maila', 'ustawienia', 'segm-ustawienia', '#wpbody-content form', '#wpbody-content form'],
];

(async () => {
  const user = process.env.WP_ADMIN_USER, pass = process.env.WP_ADMIN_PASS;
  if (!user || !pass) { console.error('Brak WP_ADMIN_USER/WP_ADMIN_PASS w env.'); process.exit(1); }
  for (const w of ['chatbot-live', 'segregator-maila']) {
    fs.mkdirSync('wtyczki/' + w + '/zrzuty', { recursive: true });
  }
  const b = await chromium.launch({
    executablePath: wymagajChromium(),
    args: ['--lang=pl-PL,pl'],
    env: { ...process.env, LANG: 'pl_PL.UTF-8' },
  });
  const p = await b.newPage({ viewport: { width: 1280, height: 1500 } }); /* wyzszy niz kazdy clip — page.screenshot(clip) obcina do viewportu */
  p.setDefaultTimeout(60000);
  let zrobione = 0, bledy = 0;

  await zaloguj(p, BAZA, user, pass);

  /* D2 recenzji #169: zrzut ustawien ChatBota strzelamy przy PUSTYM kluczu API
     (ogon prawdziwego klucza wchodzil w kadr). Migawka wraca po strzale. */
  const { execFileSync } = require('child_process');
  const wpEv = (kod) => { try { return execFileSync('docker', ['exec', '-u', 'www-data', 'strona_wp_wordpress', 'wp', 'eval', kod], { encoding: 'utf8' }).trim(); } catch (e) { return null; }
  };
  const kluczByl = wpEv('echo get_option("chatl_klucz_api") ? "TAK" : "NIE";');
  if (kluczByl === null) { console.error('nie odczytam stanu klucza — nie ruszam stosu'); process.exit(1); }
  if (kluczByl === 'TAK') {
    const m = wpEv('update_option("chatl_klucz_api_migawka_zrzut", get_option("chatl_klucz_api"), false); delete_option("chatl_klucz_api"); wp_cache_flush(); echo "OK";');
    if (m !== 'OK') { console.error('migawka klucza PADLA — nie ruszam stosu dalej'); process.exit(1); }
  }
  /* WAZNA A (#169 r2): zrzut skrzynki NIE moze pokazywac naszego kontenera
   * (segm_imap:3143, bez szyfrowania, haslo zapisane) — to anty-wzorzec, przed
   * ktorym tekst obok ostrzega. Migawka konfiguracji skrzynki -> czysty formularz
   * (placeholdery imap.przyklad.pl / 993 / SSL) -> przywrocenie po strzale. */
  const skrzMig = wpEv('update_option("segm_skrzynka_migawka_zrzut", array(get_option("segm_skrzynka"), get_option("segm_imap_haslo")), false); delete_option("segm_skrzynka"); delete_option("segm_imap_haslo"); wp_cache_flush(); echo "OK";');
  if (skrzMig !== 'OK') { console.error('migawka skrzynki PADLA — nie ruszam stosu dalej'); process.exit(1); }
  /* WAZNA B (#169 r2): zdjecia segregatora maja pokazywac KANONICZNE demo
   * (5 kategorii / 8 regul / 30 maili z instrukcji) — a wspolny stos dryfuje.
   * Twardy reset materialu demo przed strzalami; to stos testowy, seed jest
   * jego stanem kanonicznym. */
  const seed = wpEv('global $wpdb; $p=$wpdb->prefix; $wpdb->query("DELETE FROM {$p}segm_reguly"); $wpdb->query("DELETE FROM {$p}segm_kategorie"); $wpdb->query("DELETE FROM {$p}segm_maile"); segm_seed_demo(); echo $wpdb->get_var("SELECT COUNT(*) FROM {$p}segm_kategorie")."/".$wpdb->get_var("SELECT COUNT(*) FROM {$p}segm_reguly")."/".$wpdb->get_var("SELECT COUNT(*) FROM {$p}segm_maile");');
  if (seed !== '5/8/30') { console.error('seed demo nie dal 5/8/30, mam: ' + seed + ' — nie strzelam falszywych liczb'); process.exit(1); }
  console.log('  seed demo: 5/8/30 potwierdzone');

  for (const [wtyczka, nazwa, slug, dowod, kadr, maxH] of ADMIN) {
    const cel = 'wtyczki/' + wtyczka + '/zrzuty/' + nazwa + '.png';
    try {
      await p.goto(BAZA + '/wp-admin/admin.php?page=' + slug, { waitUntil: 'load' });
      await p.waitForSelector(dowod); /* przecinek = "lub" w Playwright — czekamy na KTORYKOLWIEK dowod */
      /* Paski aktualizacji WP to srodowisko testowe, nie produkt — precz z kadru. */
      await p.addStyleTag({ content: '.update-nag{display:none!important} #wpfooter{display:none!important}' });
      const el = p.locator(kadr || '#wpbody-content').first();
      if (maxH) {
        const box = await el.boundingBox();
        await p.screenshot({ path: cel, clip: { x: box.x, y: box.y, width: box.width, height: Math.min(box.height, maxH) } });
      } else {
        await el.screenshot({ path: cel });
      }
      console.log('  OK   ' + cel);
      zrobione++;
    } catch (e) {
      console.log('  BLAD ' + cel + ' — ' + e.message.split('\n')[0]);
      bledy++;
    }
  }

  /* Front: widget ChatBota oczami gościa — osobna, NIEZALOGOWANA karta
     (admin widzi pasek WP, gość nie — zdjęcie ma pokazywać prawdę gościa). */
  const g = await b.newPage({ viewport: { width: 1280, height: 900 } });
  g.setDefaultTimeout(60000);
  try {
    await g.goto(BAZA + '/', { waitUntil: 'load' });
    await g.waitForSelector('.chatl-babelek');
    await g.locator('.chatl-babelek').screenshot({ path: 'wtyczki/chatbot-live/zrzuty/widget-babelek.png' });
    await g.click('.chatl-babelek');
    await g.waitForSelector('.chatl-okno.chatl-otwarty');
    await g.waitForTimeout(400); /* koniec animacji otwarcia */
    await g.locator('.chatl-okno').screenshot({ path: 'wtyczki/chatbot-live/zrzuty/widget-okno.png' });
    await g.click('.chatl-przycisk-kontakt');
    await g.waitForSelector('.chatl-formularz.chatl-otwarty');
    await g.waitForTimeout(300);
    await g.locator('.chatl-okno').screenshot({ path: 'wtyczki/chatbot-live/zrzuty/widget-formularz.png' });
    console.log('  OK   widget: babelek, okno, formularz');
    zrobione += 3;
  } catch (e) {
    console.log('  BLAD widget — ' + e.message.split('\n')[0]);
    bledy++;
  }

  if (kluczByl === 'TAK') {
    const w = wpEv('update_option("chatl_klucz_api", get_option("chatl_klucz_api_migawka_zrzut"), false); delete_option("chatl_klucz_api_migawka_zrzut"); wp_cache_flush(); echo get_option("chatl_klucz_api") ? "OK" : "PUSTO";');
    if (w !== 'OK') { console.error('PRZYWROCENIE KLUCZA PADLO (' + w + ') — klucz wlasciciela mogl zostac skasowany!'); bledy++; }
  }
  const skrzW = wpEv('$m = get_option("segm_skrzynka_migawka_zrzut"); if (is_array($m)) { if (false !== $m[0]) { update_option("segm_skrzynka", $m[0]); } if (false !== $m[1]) { update_option("segm_imap_haslo", $m[1]); } } delete_option("segm_skrzynka_migawka_zrzut"); wp_cache_flush(); echo "OK";');
  if (skrzW !== 'OK') { console.error('PRZYWROCENIE SKRZYNKI PADLO — konfiguracja mogla zostac wyczyszczona!'); bledy++; }
  await b.close();
  console.log('\nZROBIONE: ' + zrobione + ', BLEDOW: ' + bledy);
  process.exit(bledy ? 1 : 0);
})().catch((e) => { console.error(e); process.exit(1); });
