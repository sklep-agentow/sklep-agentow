# Uninstall wg WordPress Plugin Handbook — destylat (subagent, 18.08)

Źródła: Handbook `plugin-basics/uninstall-methods/`, ref `wpdb::esc_like()`, sniff
`WordPress.PHP.NoSilencedErrors` (WPCS GitHub), kod uninstall WooCommerce + EDD.
Powstało na polecenie Dzidka „trzymaj się dokumentacji WP, nie rób podręcznikowych błędów".

## Wzorce, które przyjęliśmy
1. **Kasowanie katalogu w uploads:** NIE `WP_Filesystem` (wymaga `request_filesystem_
   credentials`, wyskakuje formularz FTP — nie nadaje się do auto-uninstall). WooCommerce/
   EDD nie kasują drzew katalogów wcale. Wzorzec: zwykłe PHP `is_file`+`@unlink` w pętli,
   `@rmdir` TYLKO gdy katalog pusty.
2. **Bez PHP Warning przy innym właścicielu plików** (deploy FTP userem X, PHP jako
   www-data): `@unlink`/`@rmdir` — **są na allowliście WPCS `NoSilencedErrors`**
   (`$allowedFunctionsList` zawiera `unlink`,`rmdir`), więc phpcs WordPress ich NIE
   zgłasza. WP core sam używa `@rmdir()` w `WP_Filesystem_Direct::delete()`. `is_writable()`
   bywa zawodne na części hostingów — uzupełnia, nie zastępuje `@`.
3. **DELETE opcji LIKE:** `$wpdb->prepare("... LIKE %s", $wpdb->esc_like('prefix_').'%')`.
   `esc_like()` eskejpuje `_` (SQL wildcard jednoznakowy) i `%`, WYŁĄCZNIE przed prepare.
   WC/EDD biorą skrót (goły `LIKE 'woo\_%'` bez prepare) — DZIAŁA (prefiks to literał),
   ale łamie regułę WPCS „zawsze prepare". Bierzemy wersję Handbook (esc_like+prepare).
4. **Cron:** `wp_clear_scheduled_hook('hook')` per hook. **Transienty:** brak API „usuń po
   prefiksie" → surowy DELETE z esc_like+prepare; jeśli znamy klucze — `delete_transient()`.

## Podręcznikowe błędy (recenzenci WP.org wytykają)
- Brak strażnika `if ( ! defined('WP_UNINSTALL_PLUGIN') ) exit;` (mamy ✓ w 3/3).
- `register_uninstall_hook()` z closure → fatal (serializacja) — my używamy `uninstall.php` ✓.
- Surowy `$wpdb->query` bez prepare/esc_like przy LIKE — **łapane przez Plugin Check** (to naprawiamy).
- Multisite: czyszczenie tylko bieżącej witryny (my: multisite poza zakresem — D, jedna instalacja).
- Kasowanie danych bez opt-in — mamy checkbox „usuń dane" (D22) ✓.
