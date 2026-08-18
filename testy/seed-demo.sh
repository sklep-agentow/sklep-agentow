#!/bin/bash
# Seed danych DEMO na stacku warsztatowym (kontener strona_wp_wordpress):
# produkt (gdy brak) + zamówienia rozłożone po ostatnich 30 dniach z różnymi
# statusami — pod testy dashboardu K3 i sędziego wyglądu. IDEMPOTENTNY po
# swojemu: zamówienia seedowe rozpoznaje po mailach @seed.local i NIE dubluje.
# Użycie: bash testy/seed-demo.sh [ile_zamowien=24]
set -euo pipefail

ILE="${1:-24}"

docker exec -e ILE="$ILE" strona_wp_wordpress wp --allow-root --path=/var/www/html eval '
global $wpdb;
$tp = $wpdb->prefix . "sklepag_produkty";
$tz = $wpdb->prefix . "sklepag_zamowienia";

// 1. Produkt demo, gdy tabela pusta (plik = malutki zip w katalogu chronionym).
$produkt = $wpdb->get_row("SELECT * FROM {$tp} WHERE aktywny = 1 ORDER BY id LIMIT 1");
if ( ! $produkt ) {
    $kat = get_option("sklepag_katalog_chroniony");
    if ( ! $kat || ! is_dir($kat) ) { echo "BLAD: brak katalogu chronionego — aktywuj wtyczke.\n"; exit(1); }
    $plik = trailingslashit($kat) . "produkt-seed.bin";
    if ( ! file_exists($plik) ) {
        $zip = new ZipArchive(); $zip->open($plik, ZipArchive::CREATE);
        $zip->addFromString("czytaj-mnie.txt", "Paczka DEMO seedu warsztatowego.\n"); $zip->close();
    }
    $wpdb->insert($tp, ["nazwa" => "ChatBot LIVE", "slug" => "chatbot-live",
        "opis_krotki" => "Bot czatu na stronę: odpowiada z bazy pytań i zbiera kontakty.",
        "opis_pelny" => "Produkt demo seedu.", "cena_grosze" => 14900, "waluta" => "PLN",
        "plik_sciezka" => $plik, "plik_rozmiar" => filesize($plik),
        "plik_sha256" => hash_file("sha256", $plik), "aktywny" => 1,
        "utworzono" => current_time("mysql"), "zmieniono" => current_time("mysql")]);
    $produkt = $wpdb->get_row("SELECT * FROM {$tp} WHERE id = " . (int) $wpdb->insert_id);
    echo "produkt seed: id=", $produkt->id, "\n";
}

// 2. Zamówienia @seed.local — dokładamy do zadanej liczby, nie dublujemy.
$jest = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$tz} WHERE email_kupujacego LIKE \"%@seed.local\"");
$cel  = max(0, (int) getenv("ILE"));
$statusy = ["mail_wyslany","mail_wyslany","mail_wyslany","mail_wyslany","mail_wyslany",
            "mail_wyslany","mail_wyslany","oplacone","mail_w_toku","mail_blad"];
$teraz = strtotime(current_time("mysql"));
mt_srand(20260818); // Powtarzalny rozklad.
for ($i = $jest; $i < $cel; $i++) {
    $dni_temu = mt_rand(0, 29); $godz = mt_rand(8, 21);
    $ts = strtotime("today", $teraz) - $dni_temu * 86400 + $godz * 3600 + mt_rand(0, 3599);
    if ($ts > $teraz) { $ts = $teraz - mt_rand(60, 3600); } // Zero zamówień z przyszłości (dzisiejsze godziny > teraz).
    $kiedy = gmdate("Y-m-d H:i:s", $ts);
    $status = $statusy[array_rand($statusy)];
    $kwota = $produkt->cena_grosze;
    $wpdb->insert($tz, [
        "stripe_session_id" => "cs_test_seed_" . wp_generate_password(24, false, false),
        "produkt_id" => $produkt->id, "produkt_nazwa_kupiona" => $produkt->nazwa,
        "produkt_cena_kupiona" => $kwota, "produkt_plik_kupiony" => $produkt->plik_sciezka,
        "email_kupujacego" => "kupujacy-" . ($i + 1) . "@seed.local",
        "kwota_grosze" => $kwota, "waluta" => $produkt->waluta, "status" => $status,
        "zgoda_tresc" => SKLEPAG_ZGODA_TRESC, "zgoda_czas" => $kiedy,
        "token_pobrania" => hash("sha256", "seed" . $i), "link_wygasa" => gmdate("Y-m-d H:i:s", strtotime($kiedy) + 72 * 3600),
        "pobrania_limit" => 10, "pobrania_uzyte" => ("mail_wyslany" === $status ? mt_rand(0, 3) : 0),
        "mail_proby" => ("mail_blad" === $status ? 3 : 0),
        "mail_tresc" => "Treść maila seed (podgląd panelu) dla zamówienia " . ($i + 1) . ".",
        "utworzono" => $kiedy, "zmieniono" => $kiedy,
    ]);
}
echo "zamowien seed: ", (int) $wpdb->get_var("SELECT COUNT(*) FROM {$tz} WHERE email_kupujacego LIKE \"%@seed.local\""), " (cel {$cel})\n";
echo "wszystkich zamowien: ", (int) $wpdb->get_var("SELECT COUNT(*) FROM {$tz}"), "\n";
'
