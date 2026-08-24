/* Zrzuty ekranów do INSTRUKCJI obu botów — maszynowo, przez prawdziwą przeglądarkę.
   NARZĘDZIE (nie test): linia bazowa pomija je jawnie, jak zrzuty-panelu.js.

   Czemu skryptem, a nie ręcznie: zdjęcia starzeją się przy każdej zmianie UI,
   a ręczne kadry rozjeżdżają się między sobą. Wzorzec i sześć pułapek z MP:
   kadr DO ELEMENTU (#wpbody-content), nie pełna strona (stopki/wizytówki);
   przeglądarka po polsku (pola dat!); czekanie na DOWÓD ekranu (selektor),
   nie na "network idle"; front: okno czatu fotografowane jako element.

   ⛔ NARZĘDZIE PRZESTAWIA WSPÓLNY STOS: (1) twardy RESET materiału demo Segregatora
   (kategorie+reguły+maile → seed od zera; to stan kanoniczny stosu testowego, ale
   kasuje, co było) — wymaga jawnej zgody ZRZUTY_RESET_DEMO=1; (2) wgrywa OBA katalogi
   wtyczek z TEGO drzewa na stos (docker cp), żeby zdjęcia pokazywały wersję z paczki,
   nie to, co akurat wisi na stosie; (3) na czas strzałów zdejmuje klucz API ChatBota
   i konfigurację skrzynki — MIGAWKI TRZYMA W PAMIĘCI PROCESU (nie w bazie: opcja-
   sierota z hasłem przeżyłaby pad skryptu i odinstalowanie wtyczki), przywraca
   w finally — także po błędzie.

   Użycie: ZRZUTY_RESET_DEMO=1 WP_ADMIN_USER=... WP_ADMIN_PASS=... node testy/zrzuty-instrukcje.js
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
  if (process.env.ZRZUTY_RESET_DEMO !== '1') {
    console.error('To narzedzie RESETUJE demo Segregatora i przestawia stos — uruchom z ZRZUTY_RESET_DEMO=1 (swiadomie).');
    process.exit(2);
  }
  /* Stos = TO drzewo (uwaga 6 recenzji #169 r3): zdjecia maja pokazywac wersje,
     ktora jedzie w paczce. */
  for (const w of ['chatbot-live', 'segregator-maila']) {
    try {
      execFileSync('docker', ['cp', 'wtyczki/' + w + '/.', 'strona_wp_wordpress:/var/www/html/wp-content/plugins/' + w + '/']);
    } catch (e) { console.error('docker cp ' + w + ' PADL — stos nie odpowiada wersji z drzewa'); process.exit(1); }
  }
  /* MIGAWKI W PAMIECI (json_encode -> zmienna node); zadnych opcji *_migawka_* w bazie. */
  const migawka = { klucz: null, skrzynka: null, haslo: null };
  const czytaj = (opt) => wpEv('echo json_encode(get_option("' + opt + '"));');
  migawka.klucz = czytaj('chatl_klucz_api');
  migawka.skrzynka = czytaj('segm_skrzynka');
  migawka.haslo = czytaj('segm_imap_haslo');
  if (migawka.klucz === null || migawka.skrzynka === null || migawka.haslo === null) {
    console.error('nie odczytam migawek — nie ruszam stosu'); process.exit(1);
  }
  const zdejmij = wpEv('delete_option("chatl_klucz_api"); delete_option("segm_skrzynka"); delete_option("segm_imap_haslo"); wp_cache_flush(); echo "OK";');
  if (zdejmij !== 'OK') { console.error('zdjecie opcji na czas zrzutow PADLO'); process.exit(1); }
  const przywrocMigawki = () => {
    const kod = ['chatl_klucz_api', 'segm_skrzynka', 'segm_imap_haslo'].map((opt, i) => {
      const v = [migawka.klucz, migawka.skrzynka, migawka.haslo][i];
      return v === 'false' ? 'delete_option("' + opt + '");'
        : '$v = json_decode(' + JSON.stringify(v) + ', true); update_option("' + opt + '", $v, false);';
    }).join(' ') + ' wp_cache_flush(); echo get_option("chatl_klucz_api") || ' + (migawka.klucz === 'false' ? '1' : '0') + ' ? "OK" : "PUSTO";';
    const w = wpEv(kod);
    if (w !== 'OK') { console.error('PRZYWROCENIE MIGAWEK PADLO (' + w + ') — sprawdz klucz i skrzynke RECZNIE!'); bledy++; return false; }
    return true;
  };
  /* WAZNA B (#169 r2): zdjecia segregatora maja pokazywac KANONICZNE demo
   * (5 kategorii / 8 regul / 30 maili z instrukcji) — a wspolny stos dryfuje.
   * Twardy reset materialu demo przed strzalami; to stos testowy, seed jest
   * jego stanem kanonicznym. */
  /* Od tego miejsca stos jest PRZESTAWIONY — kazda sciezka wyjscia MUSI przejsc
     przez finally z przywrocMigawki() (process.exit omija finally, wiec bledy
     krytyczne леca throw-em do glownego catch po przywroceniu). */
  try {
  const seed = wpEv('global $wpdb; $p=$wpdb->prefix; $wpdb->query("DELETE FROM {$p}segm_reguly"); $wpdb->query("DELETE FROM {$p}segm_kategorie"); $wpdb->query("DELETE FROM {$p}segm_maile"); segm_seed_demo(); echo $wpdb->get_var("SELECT COUNT(*) FROM {$p}segm_kategorie")."/".$wpdb->get_var("SELECT COUNT(*) FROM {$p}segm_reguly")."/".$wpdb->get_var("SELECT COUNT(*) FROM {$p}segm_maile");');
  if (seed !== '5/8/30') { throw new Error('seed demo nie dal 5/8/30, mam: ' + seed + ' — nie strzelam falszywych liczb'); }
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

  } finally {
    przywrocMigawki(); /* wraca ZAWSZE — takze po throw w srodku strzalow */
    await b.close();
  }
  console.log('\nZROBIONE: ' + zrobione + ', BLEDOW: ' + bledy);
  process.exit(bledy ? 1 : 0);
})().catch((e) => { console.error(e); process.exit(1); });
