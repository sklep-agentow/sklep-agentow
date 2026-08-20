/**
 * Pojawianie się sekcji sklepu przy przewijaniu — spójnie z motywem gospodarza
 * (feedback Dzidka 20.08: „podstrona sklepu nie ma animacji jak reszta strony").
 *
 * ⛔ ZASADA BEZPIECZEŃSTWA: CSS ukrywa sekcje TYLKO gdy jest klasa `sklepag-js`
 * na <html> (dodawana inline w <head> przed renderem) ORAZ klasa `sklepag-ruch`
 * na wyspie (dodawana tutaj). Bez JavaScriptu, przy błędzie skryptu albo przy
 * `prefers-reduced-motion` — treść jest po prostu widoczna. Nic nie znika.
 *
 * Parametry ruchu zmierzone z motywu Automatic AI (volt.js + chunks CSS, 20.08):
 * przesunięcie 26px, opacity 0.3s ease-out, transform 0.5s cubic-bezier.
 * Własny mechanizm zamiast doczepiania się do klas motywu — wtyczka musi działać
 * na KAŻDYM motywie, a cudza klasa `reveal` bez cudzego obserwatora = znikająca treść.
 */
( function () {
	'use strict';

	var wyspy = document.querySelectorAll( '.sklepag-wyspa' );
	if ( ! wyspy.length ) {
		return;
	}

	// Szanujemy ustawienie systemowe „ogranicz ruch" — wtedy zero animacji.
	var bezRuchu = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
	if ( bezRuchu || ! ( 'IntersectionObserver' in window ) ) {
		return;
	}

	var obserwator = new IntersectionObserver( function ( wpisy ) {
		for ( var i = 0; i < wpisy.length; i++ ) {
			if ( wpisy[ i ].isIntersecting ) {
				wpisy[ i ].target.classList.add( 'sklepag-widoczne' );
				obserwator.unobserve( wpisy[ i ].target );
			}
		}
	}, {
		// Sekcja pokazuje się, gdy wejdzie kawałkiem w ekran (nie dopiero po całości).
		rootMargin: '0px 0px -8% 0px',
		threshold: 0.05,
	} );

	Array.prototype.forEach.call( wyspy, function ( wyspa ) {
		wyspa.classList.add( 'sklepag-ruch' );
		Array.prototype.forEach.call( wyspa.children, function ( sekcja ) {
			obserwator.observe( sekcja );
		} );
	} );
}() );
