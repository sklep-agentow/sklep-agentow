/**
 * Pojawianie się sekcji sklepu przy przewijaniu — spójnie z motywem gospodarza
 * (feedback z przeglądu 20.08: „podstrona sklepu nie ma animacji jak reszta strony").
 *
 * ⛔ ZASADA BEZPIECZEŃSTWA: CSS ukrywa sekcje TYLKO gdy na <html> jest klasa `sklepag-js`
 * (inline w <head>, przed renderem — warunkowo: IntersectionObserver + brak „ogranicz
 * ruch"). Ten skrypt tylko ODSŁANIA i zgłasza gotowość klasą `sklepag-ruch-ok`; gdy jej
 * nie zgłosi w 2,5 s (nie dojechał, padł), inline dodaje `sklepag-odslon` i CSS pokazuje
 * wszystko. Bez JavaScriptu klasa nie powstaje i nic nie jest ukryte. Nic nie znika.
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
		Array.prototype.forEach.call( wyspa.children, function ( sekcja ) {
			obserwator.observe( sekcja );
		} );
		// Elementy z własnym wejściem WEWNĄTRZ długiej sekcji (fazy galerii) — obserwowane osobno,
		// żeby ich kaskada odpalała, gdy same wchodzą w ekran, a nie razem z górą sekcji.
		Array.prototype.forEach.call( wyspa.querySelectorAll( '.sklepag-obserwuj' ), function ( element ) {
			obserwator.observe( element );
		} );
	} );
	// Gotowość: bezpiecznik z <head> (sklepag-odslon po 2,5 s) odpala tylko BEZ tej klasy.
	document.documentElement.classList.add( 'sklepag-ruch-ok' );

	// Paralaksa panelu w hero listy (runda stron 25.08): okno jedzie o ułamek wolniej niż
	// strona — tylko zmienna CSS, transform składa się w arkuszu. Pasywny nasłuch + rAF,
	// zero pracy poza pierwszym ekranem. Stoi tu, za tą samą bramką „ogranicz ruch".
	var okno = document.querySelector( '.sklepag-panel-okno' );
	if ( okno ) {
		var czeka = false;
		var przesun = function () {
			czeka = false;
			var y = window.scrollY || window.pageYOffset || 0;
			okno.style.setProperty( '--sklepag-par', Math.min( y * 0.12, 72 ) + 'px' );
		};
		window.addEventListener( 'scroll', function () {
			if ( ! czeka ) {
				czeka = true;
				window.requestAnimationFrame( przesun );
			}
		}, { passive: true } );
	}
}() );

/* ===== Koszyk A (25.08): lightbox galerii + sticky CTA ===== */
(function () {
	'use strict';
	var bezRuchu = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

	// Nagrania w kaflach faz: plakat (stop-klatka) gaśnie dopiero, gdy wideo NAPRAWDĘ gra —
	// obraz jest od pierwszej klatki, bez „suchego" startu. Przy „ogranicz ruch" nagranie
	// stoi na plakacie: zero ruchu, o który użytkownik nie prosił.
	Array.prototype.forEach.call(document.querySelectorAll('.sklepag-kafel--wideo video'), function (wideo) {
		var kafel = wideo.closest('.sklepag-kafel');
		var przycisk = kafel ? kafel.querySelector('.sklepag-kafel-odtworz') : null;
		// ⛔ PRZYCISK JEST TERAZ ZAWSZE. Wcześniej wychodził
		// z `hidden` dopiero przy blokadzie autoodtwarzania — a element z `display:none`
		// przeglądarka POMIJA w kolejności Tab, więc przy działającym autoplay nagranie nie
		// miało ŻADNEJ ścieżki z klawiatury i żadnego widocznego sygnału dla myszy.
		// Kryterium 2.2.2 (zatrzymaj ruch) wymaga tego niezależnie od autoplay.
		// `hidden` zostaje w HTML na wypadek BRAKU JS — przycisk, którego nie ma czym obsłużyć,
		// byłby gorszy niż jego brak; tu odsłaniamy go w chwili, w której zaczyna działać.
		// Tekst budujemy węzłami, nie `innerHTML` — treść jest stała, ale w tym pliku nie
		// zostawiamy wzorca, który przy następnej zmianie mógłby przyjąć dane z zewnątrz.
		var etykieta = function () {
			if (!przycisk) { return; }
			var gra = !wideo.paused && !wideo.ended;
			var znak = document.createElement('span');
			znak.setAttribute('aria-hidden', 'true');
			znak.textContent = gra ? '⏸' : '▶';
			przycisk.textContent = '';
			przycisk.appendChild(znak);
			przycisk.appendChild(document.createTextNode(gra ? ' Zatrzymaj nagranie' : ' Odtwórz nagranie'));
			przycisk.setAttribute('aria-pressed', gra ? 'true' : 'false');
		};
		// ⛔ PAUZA CZŁOWIEKA MUSI PRZEŻYĆ WŁASNE MECHANIZMY TEJ STRONY. Poniżej chodzi timer
		// pilnujący, żeby nagranie w kadrze nie stało, oraz obserwator wznawiający je przy
		// powrocie w kadr — oba widzą wyłącznie `paused` i nie odróżniają zacięcia od
		// świadomego zatrzymania. Bez tej flagi zatrzymanie żyło najwyżej do najbliższego
		// tiku (2 s), a kryterium „da się zatrzymać ruch" mówi o nagraniach dłuższych niż 5 s.
		// Flagę zdejmuje WYŁĄCZNIE ponowne uruchomienie przez człowieka.
		var zatrzymalCzlowiek = false;
		if (przycisk) {
			przycisk.hidden = false;
			etykieta();
			wideo.addEventListener('play', etykieta);
			wideo.addEventListener('pause', etykieta);
			// ⛔ `stopPropagation`: klik bąbelkujący do nasłuchu na kaflu wznowiłby nagranie
			// natychmiast po pauzie (kafel wznawia, gdy widzi `paused`) — przycisk pauzy
			// wyglądałby wtedy na zepsuty.
			przycisk.addEventListener('click', function (e) {
				e.preventDefault();
				e.stopPropagation();
				if (wideo.paused || wideo.ended) {
					zatrzymalCzlowiek = false;
					wideo.play();
				} else {
					zatrzymalCzlowiek = true;
					wideo.pause();
				}
				etykieta();
			});
		}
		if (bezRuchu) {
			// „Ogranicz ruch": nie startujemy sami, ale sterowanie ZOSTAJE — użytkownik prosił
			// o brak ruchu, którego nie zamawiał, nie o odebranie mu nagrania.
			wideo.removeAttribute('autoplay');
			wideo.pause();
			etykieta();
			return;
		}
		var zablokuj = function () {
			if (kafel) { kafel.classList.add('sklepag-kafel--zablokowane'); }
			etykieta();
		};
		var odblokuj = function () {
			if (kafel) { kafel.classList.remove('sklepag-kafel--zablokowane'); }
			etykieta();
		};
		var gra = function () {
			if (kafel) { kafel.classList.add('sklepag-kafel--gra'); }
			odblokuj();
		};
		wideo.addEventListener('playing', gra);
		if (!wideo.paused && wideo.readyState >= 3) { gra(); }
		// Przeglądarka z blokadą autoodtwarzania (np. Brave) odrzuca play() bez gestu — wtedy plakat
		// zostaje, a kafel dostaje przycisk „Odtwórz" (klik / Enter = gest użytkownika). Próba, gdy
		// kafel wjeżdża w kadr (Chromium i tak pauzuje autoplay poza kadrem).
		// ⛔ JEDEN PUNKT, PRZEZ KTÓRY PRZECHODZI KAŻDE AUTOMATYCZNE URUCHOMIENIE — obserwator
		// kadru, strażnik postępu i gałąź bez IntersectionObserver. Warunek stoi TUTAJ, a nie
		// przy wywołaniach, bo trzy sprawdzenia w trzech miejscach to trzy okazje, żeby przy
		// następnej zmianie zapomnieć o jednym. Człowiek zatrzymał — maszyna nie wznawia.
		var sprobuj = function () {
			if (zatrzymalCzlowiek) { return; }
			var obietnica = wideo.play();
			if (obietnica && obietnica.catch) {
				obietnica.catch(zablokuj);
			}
		};
		var wKadrze = false;
		if ('IntersectionObserver' in window) {
			new IntersectionObserver(function (w) {
				wKadrze = w[0].isIntersecting;
				if (wKadrze && wideo.paused) { sprobuj(); }
			}, { threshold: 0.25 }).observe(wideo);
		} else {
			wKadrze = true;
			sprobuj();
		}
		// Strażnik: nagranie W KADRZE ma iść do przodu. Stoi (blokada bez odrzucenia play(), zacięcie
		// ładowania) → przycisk „Odtwórz", JEDNA ponowna próba i STOP timera — dalej rusza je tylko
		// gest użytkownika (bez clearInterval play() leciał co 2 s bez końca przy blokadzie
		// autoodtwarzania). Timer wraca, gdy nagranie naprawdę gra (`playing`). Poza kadrem
		// Chromium pauzuje autoplay samo — to nie blokada.
		var ostatniCzas = -1;
		var straznik = null;
		var zatrzymajStraznika = function () {
			if (straznik !== null) { window.clearInterval(straznik); straznik = null; }
		};
		var uruchomStraznika = function () {
			if (straznik !== null) { return; }
			ostatniCzas = -1;
			straznik = window.setInterval(function () {
				if (!wKadrze) { ostatniCzas = -1; return; }
				// Zatrzymane ręką to nie zacięcie: strażnik schodzi z drogi i NIE oznacza kafla
				// jako zablokowanego, bo nic tu nie zawiodło. Wróci przy zdarzeniu `playing`,
				// czyli gdy człowiek sam wznowi.
				if (zatrzymalCzlowiek) {
					zatrzymajStraznika();
					return;
				}
				var stoi = wideo.paused || wideo.currentTime === ostatniCzas;
				ostatniCzas = wideo.currentTime;
				if (!stoi) {
					odblokuj();
					return;
				}
				zatrzymajStraznika();
				zablokuj();
				sprobuj();
			}, 2000);
		};
		wideo.addEventListener('playing', uruchomStraznika);
		uruchomStraznika();
		if (kafel) {
			kafel.addEventListener('click', function (e) {
				if (kafel.classList.contains('sklepag-kafel--zablokowane') || wideo.paused) {
					e.preventDefault();
					// Klik w kafel to gest CZŁOWIEKA proszącego o odtworzenie — zdejmuje jego
					// własną wcześniejszą pauzę (inaczej blokada, którą sam postawił, nie dałaby
					// się odkliknąć niczym poza przyciskiem).
					zatrzymalCzlowiek = false;
					sprobuj();
				}
			});
		}
	});

	// Lightbox: dialog z pełnym ekranem, podpisem z kafla i przyciskiem zamknięcia;
	// Esc / klik w tło albo obraz zamyka; strona pod spodem nie przewija się; fokus wraca do kafla.
	var otworzLightbox = function (link) {
		var maly = link.querySelector('img');
		var podpis = link.getAttribute('data-podpis') || '';
		var tlo = document.createElement('div');
		tlo.className = 'sklepag-lightbox-tlo';
		tlo.setAttribute('role', 'dialog');
		tlo.setAttribute('aria-modal', 'true');
		tlo.setAttribute('aria-label', podpis || 'Powiększony zrzut');
		var zamknij = document.createElement('button');
		zamknij.type = 'button';
		zamknij.className = 'sklepag-lightbox-zamknij';
		zamknij.setAttribute('aria-label', 'Zamknij powiększenie');
		zamknij.textContent = '×';
		var ramka = document.createElement('figure');
		ramka.className = 'sklepag-lightbox-ramka';
		var duzy = document.createElement('img');
		duzy.src = link.getAttribute('href');
		duzy.alt = (maly && maly.alt) || '';
		ramka.appendChild(duzy);
		if (podpis) {
			var opis = document.createElement('figcaption');
			opis.className = 'sklepag-mono-etykieta';
			opis.textContent = podpis;
			ramka.appendChild(opis);
		}
		tlo.appendChild(zamknij);
		tlo.appendChild(ramka);
		// Pułapka fokusu: dialog modalny nie wypuszcza Tab-a na stronę pod spodem — fokus krąży po
		// elementach dialogu (dziś: przycisk zamknięcia), a gdy uciekł, wraca do pierwszego.
		var naKlawisz = function (ev) {
			if (ev.key === 'Escape') { sprzatnij(); return; }
			if (ev.key !== 'Tab') { return; }
			var pola = tlo.querySelectorAll('button, [href], [tabindex]:not([tabindex="-1"])');
			if (!pola.length) { ev.preventDefault(); return; }
			var pierwsze = pola[0], ostatnie = pola[pola.length - 1], aktywne = document.activeElement;
			if (ev.shiftKey && aktywne === pierwsze) { ev.preventDefault(); ostatnie.focus(); }
			else if (!ev.shiftKey && aktywne === ostatnie) { ev.preventDefault(); pierwsze.focus(); }
			else if (!tlo.contains(aktywne)) { ev.preventDefault(); pierwsze.focus(); }
		};
		var sprzatnij = function () {
			tlo.remove();
			document.documentElement.classList.remove('sklepag-lightbox-otwarty');
			document.removeEventListener('keydown', naKlawisz);
			link.focus();
		};
		tlo.addEventListener('click', function (ev) {
			if (ev.target === tlo || ev.target === zamknij || ev.target === duzy) { sprzatnij(); }
		});
		document.addEventListener('keydown', naKlawisz);
		document.documentElement.classList.add('sklepag-lightbox-otwarty');
		document.body.appendChild(tlo);
		zamknij.focus();
	};
	document.addEventListener('click', function (e) {
		var link = e.target.closest('.sklepag-lightbox');
		if (link) {
			e.preventDefault();
			otworzLightbox(link);
		}
	});
	// Sticky CTA: pokazuje się po minięciu hero, znika przy widocznym formularzu zakupu.
	var pasek = document.querySelector('.sklepag-sticky');
	var hero = document.querySelector('.sklepag-hero');
	var formularz = document.getElementById('sklepag-kup-formularz');
	if (pasek && hero && 'IntersectionObserver' in window) {
		// `hidden` schodzi raz (bez JS pigułki nie ma wcale); dalej steruje KLASA stanu, żeby CSS mógł
		// zrobić wjazd z dołu i zjazd z wygaszeniem zamiast przeskoku display (przegląd na żywo 25.08).
		pasek.removeAttribute('hidden');
		var heroWidoczne = true, formularzWidoczny = false;
		var przelacz = function () {
			pasek.classList.toggle('sklepag-sticky--widoczna', !heroWidoczne && !formularzWidoczny);
		};
		new IntersectionObserver(function (w) { heroWidoczne = w[0].isIntersecting; przelacz(); }).observe(hero);
		if (formularz) {
			new IntersectionObserver(function (w) { formularzWidoczny = w[0].isIntersecting; przelacz(); }).observe(formularz);
		}
	}
	/* Blokada zgody mówi do kupującego PO POLSKU.
	   Pole stoi na samym `required`, więc bez tego komunikat rysuje przeglądarka —
	   po ANGIELSKU („Please check this box if you want to proceed.”), zależnie od jej
	   ustawień językowych. To jedyny obcojęzyczny tekst w całym sklepie i pojawia się
	   dokładnie w chwili płatności.
	   Zakup bez zgody odrzuca TEŻ serwer (`includes/zakup.php`), niezależnie od JS —
	   więc gdy ten skrypt nie wstanie, blokada trzyma dalej, tylko po angielsku.
	   Komunikat ustawiamy Z GÓRY i przestawiamy przy każdej zmianie, zamiast liczyć
	   na to, że podmiana w trakcie zdarzenia `invalid` zdąży przed narysowaniem dymka. */
	var KOMUNIKAT_ZGODY = 'Zaznacz zgodę, żeby przejść do płatności.';
	Array.prototype.forEach.call(document.querySelectorAll('input[name="zgoda"]'), function (pole) {
		var ustaw = function () { pole.setCustomValidity(pole.checked ? '' : KOMUNIKAT_ZGODY); };
		ustaw();
		pole.addEventListener('change', ustaw);
		pole.addEventListener('invalid', ustaw);
	});
})();
