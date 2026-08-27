<?php
/**
 * bramka-status-bez-warunku.php — zapis statusu zamowienia MUSI chronic stan poprzedni.  3.0.0
 *
 * PO CO (recenzje PR #198, rundy 2, 4, 5): TRZY razy pod rzad ten sam wzorzec — status
 * zamowienia wpisywany jako stala, bez pytania, w jakim stanie zamowienie bylo. Wygrany spor
 * meldowal „mail wyslany" zamowieniu bez maila; cofniety zwrot to samo; „Wyslij ponownie"
 * kasowalo znacznik zwrotu. Za kazdym razem znajdowal to recenzent, po jednym egzemplarzu
 * na runde. Ta bramka pyta o CALA KLASE naraz.
 *
 * HISTORIA WERSJI — obie poprzednie mialy slepe plamy, wszystkie zmierzone przez recenzenta:
 *   1.0.0 (grep): widziala tylko `$pz`; pomijala plik bez przypisania tabeli; szukala liniowo.
 *   2.0.0 (regex w pythonie): parowala „co drugi cudzyslow". PHP zna dwa miejsca, w ktorych
 *          `"` NIE otwiera stringu — string apostrofowy i komentarz koncowy — a oba sa w tym
 *          repozytorium w uzyciu. Jeden nieparzysty `"` w takim miejscu odwracal parowanie
 *          i wycinal z analizy CALY obszar do nastepnego. Skutek zmierzony: dwa realne zapisy
 *          w ksiegowaniu zwrotow (`zakup.php:528` i `:597`) byly NIEWIDOCZNE — naprawa
 *          zabrala pokrycie zamiast je dodac. Uwaga KRYTYCZNA, i slusznie.
 *
 * 3.0.0: koniec z regexami do parowania. Uzywamy `token_get_all()` — tokenizera SAMEGO PHP.
 * Komentarze i stringi apostrofowe sa osobnymi tokenami, wiec `"` w ich srodku nie moze
 * niczego rozjechac. To jedyny sposob, ktory nie jest przyblizeniem skladni.
 *
 * ZASADA: kazdy `UPDATE <tabela zamowien> ... SET ... status=...` musi albo miec w tym samym
 * zapytaniu warunek na `status` w `WHERE`, albo niesc w promieniu trzech linii nad poczatkiem
 * zapytania jawny znacznik:   // status-bez-warunku: <dlaczego to bezpieczne>
 *
 * Wywolanie: php narzedzia-qa/bramka-status-bez-warunku.php [sciezka-repo]
 * Kody wyjscia: 0 = kazdy zapis chroniony albo jawnie zwolniony · 1 = zapis bez ochrony
 *               ALBO zero znalezionych zapisow (zero to slepota, nie czystosc)
 *               2 = nie ma czego sprawdzac (brak katalogu wtyczki)
 */

$repo    = $argv[1] ?? '.';
$wtyczka = rtrim($repo, '/') . '/wtyczki/sklep-agentow';
if (!is_dir($wtyczka)) {
	fwrite(STDERR, "STATUS-GUARD: brak $wtyczka\n");
	exit(2);
}

const TABELA   = 'sklepag_zamowienia';
const ZNACZNIK = 'status-bez-warunku:';

/**
 * Sklada z tokenow PHP liste stringow: [tresc, numer-linii].
 * Stringi z interpolacja (`"... {$pz} ..."`) PHP rozbija na wiele tokenow miedzy znakami `"`,
 * wiec zbieramy je z powrotem, odtwarzajac `{$zmienna}`.
 */
function stringi_z_pliku(string $kod): array {
	$tokeny    = token_get_all($kod);
	$wynik     = [];
	$w_stringu = false;
	$bufor     = '';
	$linia     = 0;
	$biezaca   = 1; // numer linii sledzony RECZNIE: token `"` jest zwyklym stringiem i nie niesie linii

	foreach ($tokeny as $token) {
		if (is_string($token)) {
			if ($token === '"') {
				if ($w_stringu) {
					$wynik[]   = [$bufor, $linia];
					$w_stringu = false;
					$bufor     = '';
				} else {
					$w_stringu = true;
					$bufor     = '';
					$linia     = $biezaca; // linia OTWARCIA tego stringu
				}
				continue;
			}
			$biezaca += substr_count($token, "\n");
			if ($w_stringu) {
				$bufor .= $token; // m.in. `}` zamykajace {$zmienna}
			}
			continue;
		}

		[$typ, $tresc, $nr] = [$token[0], $token[1], $token[2]];
		$biezaca = $nr + substr_count($tresc, "\n");

		// Komentarze i stringi apostrofowe to OSOBNE tokeny — `"` w ich srodku nic nie rozjezdza.
		if ($typ === T_COMMENT || $typ === T_DOC_COMMENT) {
			continue;
		}
		if (!$w_stringu && $typ === T_CONSTANT_ENCAPSED_STRING) {
			// String w apostrofach albo w cudzyslowach BEZ interpolacji — bierzemy w calosci.
			$tresc_bez = substr($tresc, 1, -1);
			$wynik[]   = [$tresc_bez, $nr];
			continue;
		}
		if ($w_stringu) {
			if ($typ === T_VARIABLE) {
				$bufor .= '{' . $tresc; // `{$pz` — domkniecie `}` przyjdzie jako string
			} elseif ($typ === T_CURLY_OPEN || $typ === T_DOLLAR_OPEN_CURLY_BRACES) {
				// sam znacznik otwarcia, tresc dopisze T_VARIABLE
			} else {
				$bufor .= $tresc;
			}
		} elseif ($typ === T_CONSTANT_ENCAPSED_STRING) {
			$wynik[] = [substr($tresc, 1, -1), $nr];
		}
	}
	return $wynik;
}

$bledy   = [];
$zbadane = 0;

$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($wtyczka));
foreach ($iterator as $plik) {
	if (!$plik->isFile() || $plik->getExtension() !== 'php') {
		continue;
	}
	$sciezka = $plik->getPathname();
	$kod     = @file_get_contents($sciezka);
	if ($kod === false) {
		continue;
	}
	$linie = explode("\n", $kod);

	// Ktore zmienne w TYM pliku wskazuja na tabele zamowien.
	preg_match_all(
		'/\$([A-Za-z_][A-Za-z0-9_]*)\s*=\s*\$wpdb->prefix\s*\.\s*[\'"]' . TABELA . '[\'"]/',
		$kod, $trafienia
	);
	$zmienne = array_unique($trafienia[1]);

	foreach (stringi_z_pliku($kod) as [$sql, $nr]) {
		if (stripos($sql, 'UPDATE') === false || stripos($sql, 'SET') === false) {
			continue;
		}
		// Tabela rozpoznawana na DWA sposoby: przez zmienna i przez nazwe wpisana wprost.
		$nasza = strpos($sql, '{$wpdb->prefix}' . TABELA) !== false;
		foreach ($zmienne as $z) {
			if (strpos($sql, '{$' . $z . '}') !== false) {
				$nasza = true;
				break;
			}
		}
		if (!$nasza) {
			continue;
		}
		$po_set = substr($sql, stripos($sql, 'SET') + 3);
		if (!preg_match('/(?<![A-Za-z0-9_])status\s*=/', $po_set)) {
			continue;
		}

		$zbadane++;
		if (preg_match('/\bWHERE\b.*?(?<![A-Za-z0-9_])status\b/is', $sql)) {
			continue;
		}
		$od       = max(0, $nr - 4);
		$kontekst = implode("\n", array_slice($linie, $od, $nr - $od));
		if (strpos($kontekst, ZNACZNIK) !== false) {
			continue;
		}
		$wzgledna = ltrim(str_replace(realpath($repo), '', realpath($sciezka)), '/');
		$bledy[]  = [$wzgledna, $nr, substr(preg_replace('/\s+/', ' ', $sql), 0, 140)];
	}
}

if ($zbadane === 0) {
	fwrite(STDERR,
		"STATUS-GUARD: nie znalazlam ANI JEDNEGO zapisu statusu w $wtyczka — wzorzec przestal\n"
		. "  pasowac albo zmienil sie ksztalt kodu. To NIE jest 'czysto'.\n");
	exit(1);
}
if ($bledy) {
	foreach ($bledy as [$p, $nr, $sql]) {
		fwrite(STDERR, "STATUS-GUARD: $p:$nr — zapis statusu bez warunku na stan poprzedni.\n");
		fwrite(STDERR, "  $sql\n");
	}
	fwrite(STDERR, "  Dodaj warunek WHERE ... AND status NOT IN (...) albo znacznik: // " . ZNACZNIK . " <powod>\n");
	exit(1);
}

echo "STATUS-GUARD: $zbadane zapisow statusu zamowienia — kazdy chroniony albo jawnie zwolniony.\n";
exit(0);
