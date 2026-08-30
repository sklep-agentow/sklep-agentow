<?php
/**
 * SEO podstawowe stron sklepu:
 * title/description per produkt, canonical, OpenGraph/Twitter, JSON-LD
 * (Product z image + BreadcrumbList + FAQPage — bez obiecywania rich
 * results FAQ, Google wycofał je dla zwykłych stron w 2023), noindex na
 * „Dziękujemy", własny provider sitemap (produkty to własna tabela, nie CPT).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Produkt bieżącego widoku (null poza stroną produktu).
 * TYLKO na stronie sklepu: bez tego warunku KAŻDY adres
 * z doklejonym ?sklepag_produkt= przejmował title/canonical/OG/JSON-LD produktu
 * (zmierzone na /kontakt/ przed naprawą). Slug przez sanitize_title — ta sama
 * sanityzacja co w renderze.
 */
function sklepag_seo_biezacy_produkt() {
	if ( ! sklepag_jest_strona_z_opcji( 'sklepag_strona_sklep' ) ) {
		return null;
	}
	$slug = get_query_var( 'sklepag_produkt' );
	if ( ! is_string( $slug ) || '' === $slug ) {
		return null;
	}
	$slug         = sanitize_title( $slug );
	static $cache = array();
	if ( ! array_key_exists( $slug, $cache ) ) {
		$cache[ $slug ] = sklepag_produkt_po_slugu( $slug );
	}
	return $cache[ $slug ];
}

/**
 * Okładka produktu (assets/okladki/{slug}.png; fallback okładka sklepu).
 *
 * @param string $slug Slug produktu.
 */
function sklepag_okladka_url( $slug ) {
	$plik = 'assets/okladki/' . sanitize_file_name( $slug ) . '.png';
	if ( file_exists( SKLEPAG_KATALOG . $plik ) ) {
		return plugins_url( $plik, SKLEPAG_PLIK );
	}
	return plugins_url( 'assets/okladki/sklep.png', SKLEPAG_PLIK );
}

/**
 * <title> strony produktu: „{Produkt} — {Nazwa strony}".
 *
 * @param array $czesci Części tytułu WP.
 */
function sklepag_seo_tytul( $czesci ) {
	$p = sklepag_seo_biezacy_produkt();
	if ( $p ) {
		$czesci['title'] = $p->nazwa;
	}
	return $czesci;
}
add_filter( 'document_title_parts', 'sklepag_seo_tytul' );

/**
 * Canonical strony produktu (WP dałby canonical strony „Sklep" bez query vara).
 *
 * @param string $url Canonical wyliczony przez WP.
 */
function sklepag_seo_canonical( $url ) {
	$p = sklepag_seo_biezacy_produkt();
	if ( $p ) {
		return sklepag_url_produktu_front( $p->slug );
	}
	if ( sklepag_jest_strona_z_opcji( 'sklepag_strona_sklep' ) && '' !== (string) get_query_var( 'sklepag_produkt' ) ) {
		// TYLKO na stronie sklepu (ta sama bramka co w sklepag_seo_biezacy_produkt
		// i sklepag_status_404_wczesnie): bez niej cudza strona z doklejonym
		// ?sklepag_produkt= traciła WŁASNY canonical. Slug w adresie, produktu nie ma:
		// to 404 — bez canonicala także u gospodarza z rel_canonical WP (ta sama reguła
		// co w sklepag_seo_canonical_wlasny()). Pusty adres = rdzeń nie drukuje nic.
		return '';
	}
	return $url;
}
add_filter( 'get_canonical_url', 'sklepag_seo_canonical' );

/**
 * <link rel="canonical"> wypisany PRZEZ WTYCZKĘ, gdy gospodarz wyciął WP-owy.
 *
 * Filtr wyżej działa tylko wtedy, gdy WP w ogóle drukuje canonical (`rel_canonical`
 * na `wp_head`). Motyw gospodarza robi `remove_action( 'wp_head', 'rel_canonical' )`
 * i drukuje własne canonicale tylko dla stron z builda — strony sklepu (lista
 * i produkt) zostawały BEZ canonicala (zmierzone 30.08 na stosie: 0 znaczników).
 * Skutek: /sklep/produkt/x/ i /sklep/?sklepag_produkt=x to dla Google dwa
 * adresy jednej treści. Gdy `rel_canonical` jest podpięty, nie drukujemy nic —
 * inaczej byłyby dwa znaczniki.
 */
function sklepag_seo_canonical_wlasny() {
	// `false !==`: has_action zwraca PRIORYTET (0 też jest podpięciem) albo false.
	if ( false !== has_action( 'wp_head', 'rel_canonical' ) ) {
		return;
	}
	// Wtyczki SEO drukują własny canonical bez rel_canonical WP — z naszym byłyby dwa.
	if ( defined( 'WPSEO_VERSION' ) || class_exists( 'RankMath' ) ) {
		return;
	}
	$p = sklepag_seo_biezacy_produkt();
	if ( $p ) {
		$url = sklepag_url_produktu_front( $p->slug );
	} elseif ( '' !== (string) get_query_var( 'sklepag_produkt' ) ) {
		return; // Slug w adresie, produktu nie ma: to 404 — bez canonicala (nie wskazujemy listy).
	} elseif ( sklepag_jest_strona_z_opcji( 'sklepag_strona_sklep' ) ) {
		$url = get_permalink( (int) get_option( 'sklepag_strona_sklep' ) ); // Jawne ID: poza pętlą get_permalink() bywa puste.
	} else {
		return;
	}
	echo '<link rel="canonical" href="' . esc_url( $url ) . '">' . "\n";
}
add_action( 'wp_head', 'sklepag_seo_canonical_wlasny', 3 );

/**
 * Meta description + OpenGraph + Twitter + JSON-LD w <head> strony produktu.
 */
function sklepag_seo_head() {
	$p = sklepag_seo_biezacy_produkt();
	if ( ! $p ) {
		if ( '' !== (string) get_query_var( 'sklepag_produkt' ) ) {
			return; // Slug w adresie, produktu nie ma (404): żadnych meta listy (og:url też nie).
		}
		// LISTA /sklep/ (strona WP bez treści w edytorze → WP nie daje description; Lighthouse SEO
		// listy 92 z tego powodu). Opis = lead hero listy + nazwy produktów (istniejące,
		// zatwierdzone treści), okładka sklepu do OG/Twitter.
		if ( sklepag_jest_strona_z_opcji( 'sklepag_strona_sklep' ) ) {
			global $wpdb;
			// Jedno źródło leadu (sklepag_lead_listy) + nazwy AKTYWNYCH produktów z bazy — nic na sztywno.
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- własna tabela wtyczki, nazwa z prefiksu WP.
			$nazwy      = $wpdb->get_col( $wpdb->prepare( "SELECT nazwa FROM {$wpdb->prefix}sklepag_produkty WHERE aktywny = %d ORDER BY nazwa", 1 ) );
			$opis_listy = sklepag_lead_listy() . ( $nazwy ? ' W sprzedaży: ' . implode( ', ', array_map( 'strval', $nazwy ) ) . '.' : '' );
			$okladka    = sklepag_okladka_url( 'sklep' );
			echo '<meta name="description" content="' . esc_attr( $opis_listy ) . '">' . "\n";
			echo '<meta property="og:type" content="website">' . "\n";
			echo '<meta property="og:title" content="' . esc_attr( wp_get_document_title() ) . '">' . "\n";
			echo '<meta property="og:description" content="' . esc_attr( $opis_listy ) . '">' . "\n";
			echo '<meta property="og:url" content="' . esc_url( get_permalink() ) . '">' . "\n";
			echo '<meta property="og:image" content="' . esc_url( $okladka ) . '">' . "\n";
			echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
			echo '<meta name="twitter:image" content="' . esc_url( $okladka ) . '">' . "\n";
		}
		return;
	}

	$opis    = '' !== trim( (string) $p->opis_krotki ) ? $p->opis_krotki : wp_trim_words( (string) $p->opis_pelny, 25 );
	$url     = sklepag_url_produktu_front( $p->slug );
	$okladka = sklepag_okladka_url( $p->slug );
	$cena    = number_format( $p->cena_grosze / 100, 2, '.', '' );

	echo '<meta name="description" content="' . esc_attr( $opis ) . '">' . "\n";
	echo '<meta property="og:type" content="product">' . "\n";
	echo '<meta property="og:title" content="' . esc_attr( $p->nazwa ) . '">' . "\n";
	echo '<meta property="og:description" content="' . esc_attr( $opis ) . '">' . "\n";
	echo '<meta property="og:url" content="' . esc_url( $url ) . '">' . "\n";
	echo '<meta property="og:image" content="' . esc_url( $okladka ) . '">' . "\n";
	echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
	echo '<meta name="twitter:title" content="' . esc_attr( $p->nazwa ) . '">' . "\n";
	echo '<meta name="twitter:description" content="' . esc_attr( $opis ) . '">' . "\n";
	echo '<meta name="twitter:image" content="' . esc_url( $okladka ) . '">' . "\n";

	// JSON-LD: Product (image WYMAGANE przez Google) + okruszki.
	$sklep_url = get_permalink( (int) get_option( 'sklepag_strona_sklep' ) );
	$ld        = array(
		array(
			'@context'    => 'https://schema.org',
			'@type'       => 'Product',
			'name'        => $p->nazwa,
			'description' => $opis,
			'image'       => $okladka,
			'url'         => $url,
			'offers'      => array(
				'@type'         => 'Offer',
				'price'         => $cena,
				'priceCurrency' => $p->waluta,
				'availability'  => 'https://schema.org/InStock',
				'url'           => $url,
			),
		),
		array(
			'@context'        => 'https://schema.org',
			'@type'           => 'BreadcrumbList',
			'itemListElement' => array(
				array(
					'@type'    => 'ListItem',
					'position' => 1,
					'name'     => 'Sklep',
					'item'     => $sklep_url,
				),
				array(
					'@type'    => 'ListItem',
					'position' => 2,
					'name'     => $p->nazwa,
					'item'     => $url,
				),
			),
		),
	);

	$t = function_exists( 'sklepag_tresc_produktu' ) ? sklepag_tresc_produktu( $p->slug ) : null;
	if ( $t && ! empty( $t['faq'] ) ) {
		$pytania = array();
		foreach ( $t['faq'] as $f ) {
			$pytania[] = array(
				'@type'          => 'Question',
				'name'           => $f[0],
				'acceptedAnswer' => array(
					'@type' => 'Answer',
					'text'  => $f[1],
				),
			);
		}
		$ld[] = array(
			'@context'   => 'https://schema.org',
			'@type'      => 'FAQPage',
			'mainEntity' => $pytania,
		);
	}

	foreach ( $ld as $blok ) {
		echo '<script type="application/ld+json">' . wp_json_encode( $blok, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
	}
}
add_action( 'wp_head', 'sklepag_seo_head', 4 );

/**
 * Strona „Dziękujemy" poza indeksem (standard stron po transakcji).
 *
 * @param array $roboty Dyrektywy robots WP.
 */
function sklepag_seo_noindex_dziekujemy( $roboty ) {
	if ( sklepag_jest_strona_z_opcji( 'sklepag_strona_dziekujemy' ) ) {
		$roboty['noindex']  = true;
		$roboty['nofollow'] = true;
	}
	return $roboty;
}
add_filter( 'wp_robots', 'sklepag_seo_noindex_dziekujemy' );

/**
 * Provider sitemap dla produktów (własna tabela ≠ CPT;
 * oficjalne API rdzenia: WP_Sitemaps_Provider).
 */
function sklepag_seo_rejestruj_sitemap() {
	if ( ! function_exists( 'wp_register_sitemap_provider' ) ) {
		return;
	}

	require_once SKLEPAG_KATALOG . 'includes/class-sklepag-sitemap-produkty.php';
	wp_register_sitemap_provider( 'sklepagprodukty', new Sklepag_Sitemap_Produkty() );
}
add_action( 'init', 'sklepag_seo_rejestruj_sitemap' );
