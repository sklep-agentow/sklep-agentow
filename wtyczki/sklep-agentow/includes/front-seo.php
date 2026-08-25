<?php
/**
 * SEO podstawowe stron sklepu (K5 PR 2; PLAN-WYGLADU-K5 §7, D27):
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
	if ( ! is_page( (int) get_option( 'sklepag_strona_sklep' ) ) ) {
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
	return $p ? sklepag_url_produktu_front( $p->slug ) : $url;
}
add_filter( 'get_canonical_url', 'sklepag_seo_canonical' );

/**
 * Meta description + OpenGraph + Twitter + JSON-LD w <head> strony produktu.
 */
function sklepag_seo_head() {
	$p = sklepag_seo_biezacy_produkt();
	if ( ! $p ) {
		// LISTA /sklep/ (strona WP bez treści w edytorze → WP nie daje description; Lighthouse SEO
		// listy 92 z tego powodu — audyt 25.08). Opis = lead hero listy + nazwy produktów (istniejące,
		// zatwierdzone treści), okładka sklepu do OG/Twitter.
		if ( is_page( (int) get_option( 'sklepag_strona_sklep' ) ) ) {
			$opis_listy = 'Gotowe wtyczki-boty dla Twojej firmy: ChatBot LIVE i Segregator maila. Instalujesz jak każdą wtyczkę WordPressa — działają od pierwszej minuty.';
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
	if ( is_page( (int) get_option( 'sklepag_strona_dziekujemy' ) ) ) {
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
