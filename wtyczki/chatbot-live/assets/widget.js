/**
 * ChatBot LIVE — widget czatu.
 * Treści wstawiane WYŁĄCZNIE przez textContent (czat = wejście niezaufane, zero innerHTML).
 * Sesja żyje w sessionStorage — bez pamięci między wizytami (granica jawna).
 */
(function () {
	'use strict';

	if (typeof window.chatlDane === 'undefined') {
		return;
	}

	var dane = window.chatlDane;

	function sesjaId() {
		var id = null;
		try {
			id = window.sessionStorage.getItem('chatl_sesja');
		} catch (e) { /* sessionStorage niedostępny — sesja jednorazowa */ }
		if (!id) {
			id = (window.crypto && window.crypto.randomUUID)
				? window.crypto.randomUUID()
				: 'chatl-' + Date.now() + '-' + Math.random().toString(36).slice(2, 10);
			try {
				window.sessionStorage.setItem('chatl_sesja', id);
			} catch (e) { /* jw. */ }
		}
		return id;
	}

	function el(tag, klasa, tekst) {
		var e = document.createElement(tag);
		if (klasa) { e.className = klasa; }
		if (tekst) { e.textContent = tekst; }
		return e;
	}

	var sesja = sesjaId();
	var okno, listaWiadomosci, formularzKontaktu, zamknijFormularz;

	var TYTUL_FORMULARZA = 'Zostaw wiadomość — odezwiemy się osobiście.';

	function dodajWiadomosc(rola, tekst) {
		var w = el('div', 'chatl-wiadomosc chatl-' + rola);
		w.appendChild(el('div', 'chatl-dymek', tekst));
		listaWiadomosci.appendChild(w);
		listaWiadomosci.scrollTop = listaWiadomosci.scrollHeight;
	}

	/* Nonce trzymamy w ZMIENNEJ, nie czytamy za kazdym razem z chatlDane:
	   po odswiezeniu ma obowiazywac nowy, a chatlDane przyszlo z HTML-a. */
	var nonce = dane.nonce;

	function zadanie(akcja, pola) {
		var body = new FormData();
		body.append('action', akcja);
		body.append('nonce', nonce);
		body.append('sesja', sesja);
		Object.keys(pola).forEach(function (k) { body.append(k, pola[k]); });
		return fetch(dane.ajaxUrl, { method: 'POST', body: body })
			.then(function (odp) { return odp.json(); });
	}

	function odswiezNonce() {
		var body = new FormData();
		body.append('action', 'chatl_nonce');
		return fetch(dane.ajaxUrl, { method: 'POST', body: body })
			.then(function (odp) { return odp.json(); })
			.then(function (odp) {
				if (odp && odp.success && odp.data && odp.data.nonce) {
					nonce = odp.data.nonce;
					return true;
				}
				return false;
			})
			.catch(function () { return false; });
	}

	/* Czy ta odpowiedz to "nonce sie nie zgadza"?
	   Dwa ksztalty, bo sa dwa zrodla: nasz wlasny blad z kodem 'nonce' oraz
	   surowe "-1" z warstwy WordPressa albo z wtyczki bezpieczenstwa, ktore
	   po odp.json() jest LICZBA — wtedy odp.success jest undefined. */
	function toZlyNonce(odp) {
		if (odp === null || typeof odp !== 'object') { return true; }
		if (typeof odp.success === 'undefined') { return true; }
		return odp.success === false && odp.data && odp.data.kod === 'nonce';
	}

	/*
	 * Nonce wchodzi do HTML-a strony, a strona bywa serwowana z cache (LiteSpeed,
	 * WP Rocket, W3TC, Cloudflare) dluzej, niz nonce zyje. Bez tego ponowienia
	 * czat przestawal dzialac u WSZYSTKICH gosci naraz, a wlasciciel nie mial
	 * o tym pojecia — gosc widzial tylko "Cos poszlo nie tak".
	 *
	 * Ponawiamy DOKLADNIE RAZ: druga porazka to juz prawdziwa awaria i wtedy
	 * gosc ma prawo zobaczyc komunikat, zamiast czekac w petli.
	 */
	function wyslij(akcja, pola) {
		return zadanie(akcja, pola)
			.catch(function () { return null; })
			.then(function (odp) {
				if (!toZlyNonce(odp)) { return odp; }
				return odswiezNonce().then(function (swiezy) {
					if (!swiezy) {
						if (odp === null) { return Promise.reject(new Error('brak odpowiedzi')); }
						return odp;
					}
					return zadanie(akcja, pola);
				});
			});
	}

	function pokazFormularz(powod) {
		if (formularzKontaktu.classList.contains('chatl-otwarty')) {
			formularzKontaktu.querySelector('input').focus();
			return;
		}
		formularzKontaktu.classList.add('chatl-otwarty');
		if (powod) {
			formularzKontaktu.querySelector('.chatl-form-tytul').textContent = powod;
		}
		formularzKontaktu.querySelector('input').focus();
	}

	function zbudujFormularzKontaktu() {
		var f = el('form', 'chatl-formularz');

		var pasekTytulu = el('div', 'chatl-form-pasek');
		pasekTytulu.appendChild(el('div', 'chatl-form-tytul', TYTUL_FORMULARZA));
		/*
		 * Wyjscie z formularza. Dotad jedynym wyjsciem byla UDANA wysylka:
		 * gosc, ktory zmienil zdanie, mial formularz na stale — takze po
		 * zamknieciu i ponownym otwarciu czatu.
		 */
		var zamknijForm = el('button', 'chatl-form-zamknij', '×');
		zamknijForm.type = 'button';
		zamknijForm.setAttribute('aria-label', 'Zamknij formularz wiadomości');
		zamknijForm.addEventListener('click', function () { zamknijFormularz(); });
		pasekTytulu.appendChild(zamknijForm);
		f.appendChild(pasekTytulu);

		var email = el('input', 'chatl-pole');
		email.type = 'email';
		email.required = true;
		email.placeholder = 'Twój e-mail';
		email.maxLength = 190;
		email.setAttribute('aria-label', 'Twój adres e-mail');
		f.appendChild(email);

		var tresc = el('textarea', 'chatl-pole');
		tresc.required = true;
		tresc.placeholder = 'W czym możemy pomóc?';
		tresc.maxLength = 2000;
		tresc.rows = 3;
		tresc.setAttribute('aria-label', 'Treść wiadomości');
		f.appendChild(tresc);

		/*
		 * Informacja o przetwarzaniu danych PRZED polem zgody: co zbieramy,
		 * po co, jak długo. Link do polityki tylko, gdy wlasciciel podal adres
		 * w ustawieniach — pusty adres = sama informacja, bez martwego linku.
		 */
		var info = el('p', 'chatl-info-prywatnosc', dane.prywatnosc);
		if (dane.polityka) {
			info.appendChild(document.createTextNode(' '));
			var link = el('a', null, 'Polityka prywatności');
			link.href = dane.polityka;
			link.target = '_blank';
			link.rel = 'noopener noreferrer';
			info.appendChild(link);
		}
		f.appendChild(info);

		var zgodaEtykieta = el('label', 'chatl-zgoda');
		var zgoda = el('input');
		zgoda.type = 'checkbox';
		zgoda.required = true;
		zgodaEtykieta.appendChild(zgoda);
		zgodaEtykieta.appendChild(document.createTextNode(' ' + dane.zgodaTekst));
		f.appendChild(zgodaEtykieta);

		var przycisk = el('button', 'chatl-wyslij-zgloszenie', 'Wyślij wiadomość');
		przycisk.type = 'submit';
		f.appendChild(przycisk);

		/*
		 * Zamkniecie = pelny reset: gosc zrezygnowal, wiec nie zostawiamy jego
		 * adresu i tresci w ukrytym formularzu ani zgody zaznaczonej „na potem".
		 */
		zamknijFormularz = function () {
			f.classList.remove('chatl-otwarty');
			email.value = '';
			tresc.value = '';
			zgoda.checked = false;
			f.querySelector('.chatl-form-tytul').textContent = TYTUL_FORMULARZA;
		};

		f.addEventListener('submit', function (zd) {
			zd.preventDefault();
			przycisk.disabled = true;
			wyslij('chatl_zgloszenie', { email: email.value, wiadomosc: tresc.value, zgoda: zgoda.checked ? '1' : '' })
				.then(function (odp) {
					if (odp.success) {
						zamknijFormularz();
						dodajWiadomosc('bot', odp.data.komunikat);
					} else {
						dodajWiadomosc('bot', (odp.data && odp.data.komunikat) || 'Nie udało się wysłać. Spróbuj ponownie.');
					}
				})
				.catch(function () {
					dodajWiadomosc('bot', 'Nie udało się wysłać. Spróbuj ponownie.');
				})
				.finally(function () { przycisk.disabled = false; });
		});

		return f;
	}

	/* Zamkniecie okna czatu zwija TAKZE formularz — inaczej wracal otwarty. */
	function zamknijOkno() {
		okno.classList.remove('chatl-otwarty');
		if (zamknijFormularz) { zamknijFormularz(); }
	}

	function zbudujOkno() {
		okno = el('div', 'chatl-okno');

		var naglowek = el('div', 'chatl-naglowek');
		naglowek.appendChild(el('span', 'chatl-tytul', 'Czat z nami'));
		var zamknij = el('button', 'chatl-zamknij', '×');
		zamknij.type = 'button';
		zamknij.setAttribute('aria-label', 'Zamknij czat');
		zamknij.addEventListener('click', function () { zamknijOkno(); });
		naglowek.appendChild(zamknij);
		okno.appendChild(naglowek);

		listaWiadomosci = el('div', 'chatl-wiadomosci');
		okno.appendChild(listaWiadomosci);

		/* STAŁY przycisk „zostaw wiadomość" — ścieżka do człowieka od pierwszej chwili. */
		var pasek = el('div', 'chatl-pasek-kontakt');
		var kontakt = el('button', 'chatl-przycisk-kontakt', '✉ Zostaw wiadomość');
		kontakt.type = 'button';
		kontakt.addEventListener('click', function () {
			pokazFormularz('Zostaw wiadomość — odezwiemy się osobiście.');
		});
		pasek.appendChild(kontakt);
		okno.appendChild(pasek);

		formularzKontaktu = zbudujFormularzKontaktu();
		okno.appendChild(formularzKontaktu);

		var formPytania = el('form', 'chatl-form-pytania');
		var polePytania = el('input', 'chatl-pole-pytania');
		polePytania.type = 'text';
		polePytania.placeholder = 'Zadaj pytanie…';
		polePytania.maxLength = 500;
		polePytania.autocomplete = 'off';
		polePytania.setAttribute('aria-label', 'Twoje pytanie');
		formPytania.appendChild(polePytania);
		var wyslijBtn = el('button', 'chatl-wyslij-pytanie', '➤');
		wyslijBtn.type = 'submit';
		wyslijBtn.setAttribute('aria-label', 'Wyślij pytanie');
		formPytania.appendChild(wyslijBtn);

		formPytania.addEventListener('submit', function (zd) {
			zd.preventDefault();
			var pytanie = polePytania.value.trim();
			if (!pytanie) { return; }
			polePytania.value = '';
			dodajWiadomosc('gosc', pytanie);
			wyslij('chatl_pytanie', { pytanie: pytanie })
				.then(function (odp) {
					if (odp.success) {
						dodajWiadomosc('bot', odp.data.odpowiedz);
						if (!odp.data.trafiono) {
							pokazFormularz('Zostaw maila i pytanie — wrócimy z odpowiedzią.');
						}
					} else {
						dodajWiadomosc('bot', (odp.data && odp.data.komunikat) || 'Coś poszło nie tak. Spróbuj ponownie.');
					}
				})
				.catch(function () {
					dodajWiadomosc('bot', 'Coś poszło nie tak. Spróbuj ponownie.');
				});
		});
		okno.appendChild(formPytania);

		/* Nota o zapisie rozmowy — PRZED powitaniem, zeby gosc przeczytal ja
		   zanim cokolwiek napisze. Nie jest dymkiem bota: to informacja
		   wlasciciela strony, a nie wypowiedz w rozmowie. Sklada sie z wezlow
		   tekstowych i (opcjonalnie) odnosnika — bez innerHTML. */
		if (dane.notaZapisu) {
			var nota = el('div', 'chatl-nota', dane.notaZapisu);
			if (dane.polityka) {
				nota.appendChild(document.createTextNode(' '));
				var link = document.createElement('a');
				link.href = dane.polityka;
				link.target = '_blank';
				link.rel = 'noopener';
				link.textContent = dane.notaLink || 'Polityka prywatności';
				nota.appendChild(link);
			}
			listaWiadomosci.appendChild(nota);
		}

		dodajWiadomosc('bot', dane.powitanie);
		return okno;
	}

	function start() {
		var korzen = el('div', 'chatl-korzen');
		/* Pozycja z ustawien wlasciciela. Klasa idzie na korzen ZANIM element
		   trafi do dokumentu, wiec widget nigdy nie mignie po zlej stronie. */
		if (chatlDane.pozycja === 'lewo') { korzen.className += ' chatl-lewo'; }
		korzen.appendChild(zbudujOkno());

		var babelek = el('button', 'chatl-babelek', '💬');
		babelek.type = 'button';
		babelek.setAttribute('aria-label', 'Otwórz czat');
		babelek.addEventListener('click', function () {
			okno.classList.toggle('chatl-otwarty');
			if (okno.classList.contains('chatl-otwarty')) {
				okno.querySelector('.chatl-pole-pytania').focus();
			}
		});
		korzen.appendChild(babelek);

		/*
		 * Escape: najpierw wychodzi z formularza (wezsze okno), przy drugim
		 * nacisnieciu zamyka caly czat — kolejnosc jak w oknach modalnych.
		 */
		document.addEventListener('keydown', function (zd) {
			if ('Escape' !== zd.key && 'Esc' !== zd.key) { return; }
			if (formularzKontaktu.classList.contains('chatl-otwarty')) {
				zamknijFormularz();
				okno.querySelector('.chatl-pole-pytania').focus();
			} else if (okno.classList.contains('chatl-otwarty')) {
				zamknijOkno();
				babelek.focus();
			}
		});

		document.body.appendChild(korzen);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', start);
	} else {
		start();
	}
})();
