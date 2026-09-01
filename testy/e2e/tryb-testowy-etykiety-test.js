/* E2E: etykiety przycisków zakupu w PRZEGLĄDARCE — pułapka klasy A4b-F2 (P-3).

   PO CO TO ISTNIEJE: `assets/platnosc.js` nadpisuje napisy na przyciskach po renderze PHP
   („Otwieram płatność…", „Przetwarzam…") i musi je potem odtworzyć. Dopóki odtwarzał je
   z własnych literałów, poprawka po stronie serwera była cofana już u kupującego: strona
   przychodziła z napisem „Kup teraz", a po nieudanej próbie płatności przycisk sam wpisywał
   sobie z powrotem „Kup teraz (płatność testowa)". Strażnik PHP tego nie widzi — on czyta
   HTML z serwera, a nie to, co zrobi z nim przeglądarka.

   ⛔ Kluczowy jest KROK 2: klikamy i doprowadzamy do BŁĘDU (klucz produkcyjny-atrapa nie
   utworzy sesji), bo etykieta wraca właśnie w gałęzi `catch`. Test, który tylko wczytuje
   stronę, przespałby całą wadę.

   Stos jest WSPÓLNY: klucz sekretny zdejmujemy na czas pomiaru i oddajemy w `finally`.

   Uruchomienie: node testy/e2e/tryb-testowy-etykiety-test.js
   KODY WYJŚCIA: 0 = etykiety zgodne z trybem · 1 = wada (albo błąd przyrządu). */
const { chromium } = require('playwright-core');
const { wymagajChromium } = require('../lib/chromium');
const { execSync } = require('child_process');
const BAZA = process.env.SKLEPAG_BAZA || 'http://localhost:8890';
const KONTENER = process.env.SKLEPAG_KONTENER || 'strona_wp_wordpress';

let bledy = 0;
function sprawdz(opis, warunek, szczegol) {
  console.log((warunek ? '  OK   ' : '  BLAD ') + opis + (szczegol ? '\n       ' + szczegol : ''));
  if (!warunek) bledy++;
}

function wp(kod) {
  return execSync(`docker exec -u www-data ${KONTENER} wp eval ${JSON.stringify(kod)}`, { encoding: 'utf8' }).trim();
}
function ustawKlucz(k) {
  wp(`update_option('sklepag_stripe_klucz_sekretny', ${JSON.stringify(k)}, false); echo 'ok';`);
}

/* Atrapy świadomie KRÓTKIE — bramka poświadczeń łapie (sk|rk)_(test|live)_[A-Za-z0-9]{16,}. */
const KL_ZYWY = 'sk_' + 'live_ATRAPA';

async function etykiety(p) {
  return p.evaluate(() => {
    const kup = document.querySelector('#sklepag-kup-formularz .sklepag-kup');
    const zaplac = document.getElementById('sklepag-platnosc-zaplac');
    return {
      kup: kup ? kup.textContent.trim() : null,
      zaplac: zaplac ? zaplac.textContent.trim() : null
    };
  });
}

(async () => {
  const klucz0 = wp('echo (string) get_option("sklepag_stripe_klucz_sekretny", "");');
  let b = null;
  try {
    b = await chromium.launch({ executablePath: wymagajChromium() });
    const p = await b.newPage({ viewport: { width: 1280, height: 900 } });

    console.log('== KLUCZ PRODUKCYJNY (atrapa): kupujący nie ma prawa zobaczyć slowa „testowa” ==');
    ustawKlucz(KL_ZYWY);
    await p.goto(BAZA + '/sklep/produkt/chatbot-live/', { waitUntil: 'networkidle' });
    let e = await etykiety(p);
    sprawdz('P1 po wczytaniu: przycisk zakupu bez dopisku', e.kup === 'Kup teraz', JSON.stringify(e));
    sprawdz('P2 po wczytaniu: przycisk panelu płatności bez dopisku', e.zaplac === 'Zapłać', JSON.stringify(e));

    /* ⛔ TU MIESZKAŁA WADA: gałąź `catch` w platnosc.js odtwarzała etykietę z własnego literału.
       Zgoda jest `required` — BEZ jej zaznaczenia `reportValidity()` odbija klik, obsługa wraca
       natychmiast i przycisk NIE zmienia napisu. Test wyglądałby wtedy na zielony, nie zmierzywszy
       niczego (sprawdzone: przy niezaznaczonej zgodzie mutant „literał z powrotem" przechodził). */
    await p.check('#sklepag-kup-formularz input[name="zgoda"]');
    await p.click('#sklepag-kup-formularz .sklepag-kup');
    /* Dowód, że przebieg NAPRAWDĘ ruszył: przycisk musi przejść przez stan pośredni.
       Bez tego warunku „etykieta bez dopisku" znaczyłoby tylko tyle, że nic się nie stało. */
    let ruszylo = true;
    await p.waitForFunction(
      () => {
        const b = document.querySelector('#sklepag-kup-formularz .sklepag-kup');
        return b && b.disabled && b.textContent.indexOf('Otwieram') !== -1;
      },
      { timeout: 15000 }
    ).catch(() => { ruszylo = false; });
    sprawdz('P3a klik NAPRAWDĘ uruchomił płatność (przycisk przeszedł w „Otwieram płatność…")', ruszylo);
    await p.waitForFunction(
      () => {
        const b = document.querySelector('#sklepag-kup-formularz .sklepag-kup');
        return b && !b.disabled && b.textContent.indexOf('Otwieram') === -1;
      },
      { timeout: 30000 }
    );
    e = await etykiety(p);
    sprawdz('P3b po NIEUDANEJ próbie płatności przeglądarka odtwarza etykietę BEZ dopisku', e.kup === 'Kup teraz', JSON.stringify(e));

    console.log('== KLUCZ TESTOWY: ostrzeżenie wraca (kontrola dodatnia) ==');
    ustawKlucz('sk_' + 'test_ATRAPA');
    await p.goto(BAZA + '/sklep/produkt/chatbot-live/', { waitUntil: 'networkidle' });
    e = await etykiety(p);
    sprawdz('T1 przycisk zakupu z dopiskiem', e.kup === 'Kup teraz (płatność testowa)', JSON.stringify(e));
    sprawdz('T2 przycisk panelu płatności z dopiskiem', e.zaplac === 'Zapłać (płatność testowa)', JSON.stringify(e));
  } catch (err) {
    console.log('  BLAD przyrzad/przebieg: ' + (err && err.message ? err.message : err));
    bledy++;
  } finally {
    if (b) await b.close().catch(() => {});
    ustawKlucz(klucz0);
    const wrocil = wp('echo (string) get_option("sklepag_stripe_klucz_sekretny", "");') === klucz0;
    console.log((wrocil ? '  OK   ' : '  BLAD ') + 'klucz sekretny stosu oddany w stanie zastanym');
    if (!wrocil) bledy++;
  }
  console.log(bledy ? '\nBLEDY: ' + bledy : '\nWSZYSTKO OK');
  process.exit(bledy ? 1 : 0);
})();
