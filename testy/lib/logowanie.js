/* Logowanie do wp-admin ODPORNE na obciazony stos — wspolne dla testow przegladarkowych.
 *
 * Czemu istnieje: pod pelna linia bazowa (2 vCPU: PHP+MariaDB+Chromium naraz)
 * pierwszy POST na wp-login bywa gubiony (ciastko nie siada) i kazde kolejne
 * `goto` odbija na ekran logowania. Testy z recznym, jednostrzalowym loginem
 * czekaly wtedy pelny limit na elementy admina, ktorych na stronie logowania
 * nie ma — i padaly LOSOWO, po jednym na przebieg (24.08: ekran-skrzynki 2x,
 * nonce-cache 1x). Jeden helper = jedna naprawa klasy zamiast lataniny per plik.
 *
 * Uzycie:  const { zaloguj } = require('./lib/logowanie');
 *          await zaloguj(p, BAZA, user, pass);
 * Rzuca Errorem z czytelnym opisem, gdy po trzech probach nadal nie ma sesji.
 */
async function zaloguj(p, BAZA, user, pass) {
  for (let proba = 1; proba <= 3; proba++) {
    await p.goto(BAZA + '/wp-login.php', { waitUntil: 'load' });
    if (!(await p.$('#user_login'))) { return; } /* przekierowalo do admina — sesja juz jest */
    await p.fill('#user_login', user);
    await p.fill('#user_pass', pass);
    await p.click('#wp-submit');
    await p.waitForLoadState('load');
    if (!p.url().includes('wp-login.php')) { return; }
  }
  throw new Error('zaloguj(): po 3 probach nadal ekran logowania (url=' + p.url() + ') — zle dane albo stos nie wydaje ciastka');
}
module.exports = { zaloguj };
