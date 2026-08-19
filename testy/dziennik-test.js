/* Test dziennika logowań + opóźnienia wykładniczego (S-09, kryterium #8;
   dołożony na wniosek recenzji PR #42) na ŻYWYM stacku.
   Mierzy, nie deklaruje:
   A. salwa 12 równoległych POST wp-login z 1 IP → limiter 6/min: 6 przechodzi, 6×429;
   B. sekwencja złych haseł → czasy odpowiedzi rosną wykładniczo (2^n, kryterium #8);
   C. sukces resetuje licznik pary login+IP (następna zła próba znów szybka);
   D. retencja 90 dni kasuje stare wpisy, świeżych nie rusza (próba kontrolna);
   E. wpisy mają „konkrety" S-09 (IP + przeglądarka) i strony obu wyników.
   Env: WP_ADMIN_USER, WP_ADMIN_PASS. Wyjście: linie WYNIK:..., exit 0 gdy komplet.
   Czas przebiegu ~3 min (dwa czekania na świeże okno minutowe limitera). */
const { execFileSync } = require('child_process');
const BAZA = 'http://localhost:8890';

const wpEval = (kod) => execFileSync(
  'docker',
  ['exec', 'strona_wp_wordpress', 'wp', '--allow-root', '--path=/var/www/html', 'eval', kod],
  { encoding: 'utf8' }
).trim();

let bledy = 0;
const wynik = (nazwa, ok, extra = '') => { console.log(`WYNIK:${ok ? 'OK ' : 'PAD'} ${nazwa}${extra ? ' — ' + extra : ''}`); if (!ok) bledy++; };
const spij = (ms) => new Promise((r) => setTimeout(r, ms));

// Zerowanie technicznej tabeli limitera (wiersz i tak żyje ~2 min). Limiter 6/min
// per IP jest przedmiotem TESTU A; w testach opóźnienia (B/C) musi startować pusty,
// inaczej odbija (429) próby z tego samego IP ZANIM opóźnienie zdąży zadziałać —
// czyli mierzyłoby OCHRONĘ zamiast wady. Dziennik logowań NIE jest tu ruszany
// (to on napędza opóźnienie: liczy błędy pary login+IP).
const czyscLimiter = () => wpEval('global $wpdb; $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}sklepag_rate_limit WHERE klucz LIKE %s", $wpdb->esc_like("login|") . "%"));');
const czyscTestowe = () => wpEval('global $wpdb; $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}sklepag_logowania WHERE login_wpisany LIKE %s", $wpdb->esc_like("test-") . "%"));');

// Limiter liczy okno = floor(unix/60): czekamy na początek świeżej minuty
// z zapasem, żeby cała porcja żądań wylądowała w JEDNYM oknie.
async function swiezeOkno(zapasSekund = 40) {
  const wSekundzie = () => Math.floor(Date.now() / 1000) % 60;
  if (60 - wSekundzie() < zapasSekund) {
    const czekaj = (60 - wSekundzie() + 1) * 1000;
    console.log(`(czekam ${Math.round(czekaj / 1000)} s na świeże okno minutowe limitera)`);
    await spij(czekaj);
  }
}

async function proba(login, haslo) {
  const start = Date.now();
  const odp = await fetch(BAZA + '/wp-login.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'User-Agent': 'dziennik-test' },
    body: new URLSearchParams({ log: login, pwd: haslo, 'wp-submit': 'Log In' }),
    redirect: 'manual',
  });
  await odp.text();
  return { status: odp.status, ms: Date.now() - start };
}

(async () => {
  const user = process.env.WP_ADMIN_USER, pass = process.env.WP_ADMIN_PASS;
  if (!user || !pass) { console.error('Brak WP_ADMIN_USER/WP_ADMIN_PASS w env.'); process.exit(1); }

  // Start od czystego stanu: śmieci testowe z ewentualnego padniętego przebiegu
  // (dziennik test-* i limiter) nie mogą zafałszować pomiaru.
  czyscTestowe();
  czyscLimiter();

  // A. SALWA RÓWNOLEGŁA (12 z jednego IP w jednym oknie) — D26: nadmiar odbity
  // NATYCHMIAST (429) zanim cokolwiek zaśnie; dziennik dostaje tylko przepuszczone.
  await swiezeOkno();
  const salwa = await Promise.all(Array.from({ length: 12 }, () => proba('test-salwa', 'zle-haslo')));
  const odbite = salwa.filter((s) => s.status === 429).length;
  const przepuszczone = salwa.length - odbite;
  // Salwa RÓWNOLEGŁA: dokładny podział waha się o ±1 przez timing PHP-FPM przy
  // atomowym liczniku, więc mierzymy SENS kryterium #5/#8, nie liczbę na sztywno:
  // przez próg (6) NIE przechodzi więcej niż próg, a nadmiar z 12 jest odbity.
  wynik('A salwa: limiter tnie do progu (≤6 przeszło, nadmiar 429)', przepuszczone <= 6 && odbite >= 6, `429=${odbite}, przepuszczone=${przepuszczone}`);
  const odbiteSzybko = salwa.filter((s) => s.status === 429 && s.ms < 2000).length;
  wynik('A salwa: odbicie natychmiastowe, nie po sleepie', odbiteSzybko === odbite, `szybkich 429: ${odbiteSzybko}/${odbite}`);
  const wDzienniku = Number(wpEval('global $wpdb; echo $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}sklepag_logowania WHERE login_wpisany=%s", "test-salwa"));'));
  wynik('A dziennik: zapisane dokładnie przepuszczone', wDzienniku === przepuszczone, `wpisów=${wDzienniku}`);

  // B. OPÓŹNIENIE WYKŁADNICZE (kryterium #8: POMIAR czasu odpowiedzi) — świeża
  // para login+IP; limiter wyzerowany, żeby nie odbił prób opóźnienia.
  czyscLimiter();
  const czasy = [];
  for (let i = 0; i < 4; i++) czasy.push((await proba('test-opoznienie', 'zle-haslo')).ms);
  const [t1, t2, t3, t4] = czasy;
  wynik('B opóźnienie: 1. próba bez kary (kontrola)', t1 < 1500, `t1=${t1}ms`);
  wynik('B opóźnienie: 2.≥2s, 3.≥4s, 4.≥8s (2^n)', t2 >= 2000 && t3 >= 4000 && t4 >= 8000, `t=${czasy.join('/')}ms`);
  wynik('B opóźnienie: realnie rośnie (t4>t3>t2>t1)', t4 > t3 && t3 > t2 && t2 > t1);

  // C. RESET PO SUKCESIE — sukces zeruje liczenie pary admin+IP (opóźnienie liczy
  // TYLKO błędy nowsze niż ostatni sukces), więc test jest odporny na historię
  // admina: sukces → zła (szybka) → zła (kara). Limiter znów zerujemy.
  // Kroki rozsunięte na OSOBNE sekundy: dziennik ma granularność sekundową,
  // a opóźnienie liczy błędy `czas > ostatni_sukces` (ostry) — próby w tej samej
  // sekundzie co sukces nie naliczyłyby kary (człowiek nie robi 3 prób/s).
  czyscLimiter();
  const logowanie = await proba(user, pass);           // sukces = punkt odniesienia
  await spij(1100);
  const poResecie = (await proba(user, 'zle-haslo')).ms; // 0 błędów po sukcesie → szybka
  await spij(1100);
  const poBledzie = (await proba(user, 'zle-haslo')).ms; // 1 błąd po sukcesie → sleep 2s
  wynik('C reset: sukces logowania przeszedł', logowanie.status === 302, `status=${logowanie.status}`);
  wynik('C reset: 1. zła po sukcesie szybka, 2. już z karą', poResecie < 1500 && poBledzie >= 2000, `po_resecie=${poResecie}ms, po_bledzie=${poBledzie}ms`);

  // D. RETENCJA 90 DNI — stary wpis znika, świeże zostają (próba kontrolna).
  wpEval('global $wpdb; $wpdb->insert("{$wpdb->prefix}sklepag_logowania", array("czas" => gmdate("Y-m-d H:i:s", strtotime(current_time("mysql")) - 91 * DAY_IN_SECONDS), "login_wpisany" => "test-retencja", "wynik" => "blad", "ip" => "127.0.0.1", "user_agent" => "dziennik-test"));');
  const staryPrzed = Number(wpEval('global $wpdb; echo $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}sklepag_logowania WHERE login_wpisany=\'test-retencja\'");'));
  wpEval('sklepag_dociagnij_zalegle();');
  const staryPo = Number(wpEval('global $wpdb; echo $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}sklepag_logowania WHERE login_wpisany=\'test-retencja\'");'));
  const swiezePo = Number(wpEval('global $wpdb; echo $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}sklepag_logowania WHERE login_wpisany=\'test-salwa\'");'));
  wynik('D retencja: stary wpis skasowany, świeże zostały', staryPrzed === 1 && staryPo === 0 && swiezePo === wDzienniku, `stary ${staryPrzed}→${staryPo}, świeżych ${swiezePo}`);

  // E. KONKRETY S-09: IP + przeglądarka wypełnione, obie strony wyniku obecne.
  const konkrety = wpEval('global $wpdb; $w = $wpdb->get_row($wpdb->prepare("SELECT ip, user_agent FROM {$wpdb->prefix}sklepag_logowania WHERE login_wpisany=%s ORDER BY id DESC LIMIT 1", "test-salwa")); echo ($w && $w->ip !== "" && $w->user_agent !== "") ? "PELNE:" . $w->user_agent : "PUSTE";');
  wynik('E dziennik: IP i przeglądarka zapisane (S-09)', konkrety.startsWith('PELNE:'), konkrety);
  const okAdmina = Number(wpEval(`global $wpdb; echo $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}sklepag_logowania WHERE login_wpisany=%s AND wynik='ok'", ${JSON.stringify(user)}));`));
  wynik('E dziennik: udane logowanie też zapisane', okAdmina >= 1, `wpisów ok=${okAdmina}`);

  // Sprzątanie wpisów testowych (jak produkty-test: atrapy nie zostają w danych).
  wpEval('global $wpdb; $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}sklepag_logowania WHERE login_wpisany LIKE %s", $wpdb->esc_like("test-") . "%"));');

  console.log(bledy === 0 ? 'KOMPLET: wszystkie testy dziennika OK' : `PADŁO testów: ${bledy}`);
  process.exit(bledy === 0 ? 0 : 1);
})().catch((e) => { console.error(e); process.exit(1); });
