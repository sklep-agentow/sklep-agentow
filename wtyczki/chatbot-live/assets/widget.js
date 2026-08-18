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
	var okno, listaWiadomosci, formularzKontaktu;

	function dodajWiadomosc(rola, tekst) {
		var w = el('div', 'chatl-wiadomosc chatl-' + rola);
		w.appendChild(el('div', 'chatl-dymek', tekst));
		listaWiadomosci.appendChild(w);
		listaWiadomosci.scrollTop = listaWiadomosci.scrollHeight;
	}

	function wyslij(akcja, pola) {
		var body = new FormData();
		body.append('action', akcja);
		body.append('nonce', dane.nonce);
		body.append('sesja', sesja);
		Object.keys(pola).forEach(function (k) { body.append(k, pola[k]); });
		return fetch(dane.ajaxUrl, { method: 'POST', body: body })
			.then(function (odp) { return odp.json(); });
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
		f.appendChild(el('div', 'chatl-form-tytul', 'Zostaw wiadomość — odezwiemy się osobiście.'));

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

		var przycisk = el('button', 'chatl-wyslij-zgloszenie', 'Wyślij wiadomość');
		przycisk.type = 'submit';
		f.appendChild(przycisk);

		f.addEventListener('submit', function (zd) {
			zd.preventDefault();
			przycisk.disabled = true;
			wyslij('chatl_zgloszenie', { email: email.value, wiadomosc: tresc.value })
				.then(function (odp) {
					if (odp.success) {
						f.classList.remove('chatl-otwarty');
						email.value = '';
						tresc.value = '';
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

	function zbudujOkno() {
		okno = el('div', 'chatl-okno');

		var naglowek = el('div', 'chatl-naglowek');
		naglowek.appendChild(el('span', 'chatl-tytul', 'Czat z nami'));
		var zamknij = el('button', 'chatl-zamknij', '×');
		zamknij.type = 'button';
		zamknij.setAttribute('aria-label', 'Zamknij czat');
		zamknij.addEventListener('click', function () { okno.classList.remove('chatl-otwarty'); });
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

		dodajWiadomosc('bot', dane.powitanie);
		return okno;
	}

	function start() {
		var korzen = el('div', 'chatl-korzen');
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

		document.body.appendChild(korzen);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', start);
	} else {
		start();
	}
})();
