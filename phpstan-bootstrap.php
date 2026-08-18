<?php
/**
 * Bootstrap DLA ANALIZY phpstan (narzędzie QA repo, NIE część paczek).
 *
 * Stałe wtyczek liczone funkcją (plugin_dir_path/url, __FILE__) nie są
 * odkrywalne statycznie — phpstan analizuje pliki osobno i nie widzi
 * define() z plików głównych. Wartości tutaj są atrapami: liczy się
 * istnienie symbolu i TYP, nie treść.
 * Stałe literalne (np. CHATL_WERSJA) phpstan odkrywa sam — nie dublujemy.
 */

// Ścieżki katalogowe REALNE (względem repo): phpstan sprawdza istnienie plików
// w require_once( SKLEPAG_KATALOG . '...' ) — atrapa ścieżki wysadza analizę.
define( 'SKLEPAG_PLIK', __DIR__ . '/wtyczki/sklep-agentow/sklep-agentow.php' );
define( 'SKLEPAG_KATALOG', __DIR__ . '/wtyczki/sklep-agentow/' );
define( 'CHATL_KATALOG', __DIR__ . '/wtyczki/chatbot-live/' );
define( 'CHATL_URL', 'https://example.invalid/wp-content/plugins/chatbot-live/' );
define( 'SEGM_KATALOG', __DIR__ . '/wtyczki/segregator-maila/' );
