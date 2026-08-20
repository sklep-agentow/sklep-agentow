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

	/*
	 * Identyfikator sesji wydaje SERWER. Wczesniej wymyslala go przegladarka, ze
	 * sciezka zapasowa 'chatl-' + Date.now() + Math.random() — a Math.random() nie
	 * jest generatorem kryptograficznym i nigdy nie mial nim byc. crypto.randomUUID()
	 * wymaga HTTPS, wiec na witrynie po HTTP ta slaba sciezka byla REGULA, nie
	 * wyjatkiem. Od chwili, gdy publiczny punkt odpytywania na podstawie sesji WYDAJE
	 * tresc rozmowy z operatorem, zgadniety identyfikator to podsluch cudzej rozmowy.
	 */
	function wczytajSesje() {
		try {
			var id = window.sessionStorage.getItem('chatl_sesja');
			/* Sesje wydane przez SERWER maja dokladnie 32 znaki alfanumeryczne.
			   Cokolwiek innego to pozostalosc po wersji, w ktorej identyfikator
			   wymyslala przegladarka (UUID z myslnikami albo 'chatl-<czas>-<random>')
			   — takiej nie da sie juz uzyc, bo nie ma do niej sekretu. Wymieniamy
			   ja po cichu na nowa, zamiast zostawiac goscia z czatem, ktory
			   milczy bez wyjasnienia. */
			return (id && /^[a-zA-Z0-9]{32}$/.test(id)) ? id : null;
		} catch (e) { return null; }
	}

	function zapamietajSesje(id) {
		try {
			window.sessionStorage.setItem('chatl_sesja', id);
		} catch (e) { /* sessionStorage niedostepny — sesja jednorazowa */ }
	}

	function el(tag, klasa, tekst) {
		var e = document.createElement(tag);
		if (klasa) { e.className = klasa; }
		if (tekst) { e.textContent = tekst; }
		return e;
	}

	var sesja = wczytajSesje();
	var okno, listaWiadomosci, formularzKontaktu, zamknijFormularz;
	/* Ustawiane przy budowie okna — zamkniecie czatu (takze klawiszem Escape)
	   ma UCISZYC odpytywanie, inaczej zamkniete okno dalej obciaza serwer. */
	var stopOdpytywania = function () {};

	/* Zwraca obietnice sesji: z pamieci karty albo swiezo wydana przez serwer. */
	function dajSesje() {
		if (sesja) { return Promise.resolve(sesja); }
		var body = new FormData();
		body.append('action', 'chatl_sesja');
		return fetch(dane.ajaxUrl, { method: 'POST', body: body })
			.then(function (o) { return o.json(); })
			.then(function (o) {
				if (o && o.success && o.data && o.data.sesja) {
					sesja = o.data.sesja;
					zapamietajSesje(sesja);
				}
				return sesja;
			});
	}

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
		return dajSesje().then(function (id) {
			var body = new FormData();
			body.append('action', akcja);
			body.append('nonce', nonce);
			body.append('sesja', id || '');
			Object.keys(pola).forEach(function (k) { body.append(k, pola[k]); });
			return fetch(dane.ajaxUrl, { method: 'POST', body: body })
				.then(function (odp) { return odp.json(); });
		});
	}

	/*
	 * Sesja bez waznego sekretu (np. po aktualizacji wtyczki albo gdy ciastko
	 * przepadlo) konczy sie kodem `sesja`. Wtedy: nowa sesja i JEDNO ponowienie.
	 * Jedno, nie w petli — jesli przegladarka blokuje ciastka pierwszej strony,
	 * druga proba padnie tak samo, a gosc ma dostac uczciwy komunikat zamiast
	 * czatu, ktory w kolko probuje i milczy.
	 */
	function zadanieZOdnowieniem(akcja, pola) {
		return zadanie(akcja, pola).then(function (odp) {
			if (odp && !odp.success && odp.data && 'sesja' === odp.data.kod) {
				sesja = null;
				try { window.sessionStorage.removeItem('chatl_sesja'); } catch (e) { /* jw. */ }
				return zadanie(akcja, pola);
			}
			return odp;
		});
	}

	/*
	 * Sygnal "bot pisze". Bez niego gosc patrzyl na nieruchomy ekran: w trybie AI
	 * timeout to 9 sekund plus jedno ponowienie, czyli do 18 sekund ciszy. Czlowiek
	 * uznaje wtedy, ze sie zawiesilo, i klika drugi raz — a kazde kliniecie to
	 * kolejne wywolanie modelu na rachunku wlasciciela.
	 *
	 * Dymek jest ELEMENTEM LISTY WIADOMOSCI, nie nakladka: dzieki temu sam
	 * przewija sie na dol razem z rozmowa i znika przez usuniecie wezla.
	 */
	var dymekPisania = null;

	function pokazPisanie() {
		if (dymekPisania) { return; }
		var w = el('div', 'chatl-wiadomosc chatl-bot chatl-pisze');
		var d = el('div', 'chatl-dymek');
		/* aria-live: czytnik ekranu ma OGLOSIC, ze bot pracuje — inaczej osoba
		   niewidzaca dostaje te sama cisze co wczesniej, tylko bez wskaznika. */
		d.setAttribute('aria-live', 'polite');
		d.setAttribute('aria-label', 'Bot pisze odpowiedź');
		for (var i = 0; i < 3; i++) { d.appendChild(el('span', 'chatl-kropka')); }
		w.appendChild(d);
		listaWiadomosci.appendChild(w);
		listaWiadomosci.scrollTop = listaWiadomosci.scrollHeight;
		dymekPisania = w;
	}

	function ukryjPisanie() {
		if (dymekPisania && dymekPisania.parentNode) {
			dymekPisania.parentNode.removeChild(dymekPisania);
		}
		dymekPisania = null;
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
		return zadanieZOdnowieniem(akcja, pola)
			.catch(function () { return null; })
			.then(function (odp) {
				if (!toZlyNonce(odp)) { return odp; }
				return odswiezNonce().then(function (swiezy) {
					if (!swiezy) {
						if (odp === null) { return Promise.reject(new Error('brak odpowiedzi')); }
						return odp;
					}
					return zadanieZOdnowieniem(akcja, pola);
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
		stopOdpytywania();
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

		/* Pasek rozmowcy stoi NAD lista wiadomosci i jest widoczny caly czas —
		   gosc ma wiedziec, komu pisze, ZANIM napisze, a nie po fakcie. */
		var pasekRozmowcy = el('div', 'chatl-pasek-rozmowcy', dane.botPasek);
		pasekRozmowcy.setAttribute('role', 'status');

		listaWiadomosci = el('div', 'chatl-wiadomosci');
		okno.appendChild(pasekRozmowcy);
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

		/*
		 * PRZEJECIE ROZMOWY PRZEZ CZLOWIEKA
		 *
		 * Rytm odpytywania podaje SERWER w kazdej odpowiedzi (pole `interwal`),
		 * a nie ten plik: dzieki temu zmiana rytmu nie wymaga wysylania klientom
		 * nowego JS. Przy zamknietym oknie NIE pytamy w ogole — nie ma rozmowy,
		 * nie ma po co obciazac serwera kupujacego.
		 */
		var odpytywanieId = null;
		var ostatnieId = 0;
		var stanRozmowy = 'bot';

		function ustawPasek(stan) {
			if (stan === stanRozmowy) { return; }
			stanRozmowy = stan;
			pasekRozmowcy.textContent = ('czlowiek' === stan) ? dane.ludzkiPasek : dane.botPasek;
			pasekRozmowcy.className = 'chatl-pasek-rozmowcy' + ('czlowiek' === stan ? ' chatl-pasek-czlowiek' : '');
			/* Czytnik ekranu ma to OGLOSIC: zmiana rozmowcy jest wazniejsza niz
			   jakikolwiek inny komunikat w oknie, a osoba niewidzaca nie zobaczy
			   zmiany koloru paska. */
			pasekRozmowcy.setAttribute('role', 'status');
		}

		function odpytaj() {
			if (!sesja) { return Promise.resolve(); }
			return fetch(dane.restUrl, {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify({
					sesja: sesja,
					od_id: ostatnieId,
					ukryta: (document.visibilityState === 'hidden')
				})
			})
				.then(function (o) { return o.json(); })
				.then(function (o) {
					if (!o || !o.stan) { return; }
					ustawPasek(o.stan);
					(o.nowe || []).forEach(function (w) {
						ostatnieId = Math.max(ostatnieId, w.id);
						dodajWiadomosc('system' === w.rola ? 'system' : 'operator', w.tresc);
					});
					zaplanujOdpytanie(o.interwal);
				})
				.catch(function () {
					/* Blad sieci nie moze zatrzymac odpytywania na zawsze — probujemy
					   dalej, ale rzadziej, zeby nie dobijac serwera, ktory ma klopot. */
					zaplanujOdpytanie(30);
				});
		}

		function zaplanujOdpytanie(sekundy) {
			if (odpytywanieId) { window.clearTimeout(odpytywanieId); }
			if (!okno.classList.contains('chatl-otwarty')) { return; }
			var s = Math.max(3, parseInt(sekundy, 10) || 15);
			odpytywanieId = window.setTimeout(odpytaj, s * 1000);
		}

		function zatrzymajOdpytywanie() {
			if (odpytywanieId) { window.clearTimeout(odpytywanieId); }
			odpytywanieId = null;
		}
		stopOdpytywania = zatrzymajOdpytywanie;

		/* Karta wraca na wierzch = pytamy OD RAZU: gosc wlasnie wrocil do rozmowy
		   i najgorsze, co moze zobaczyc, to cisza przez pelny interwal. */
		document.addEventListener('visibilitychange', function () {
			if ('visible' === document.visibilityState && okno.classList.contains('chatl-otwarty')) {
				odpytaj();
			}
		});

		formPytania.addEventListener('submit', function (zd) {
			zd.preventDefault();
			var pytanie = polePytania.value.trim();
			/* Blokada dziala tez jako straz przed podwojnym wyslaniem: dopoki
			   trwa poprzednie pytanie, drugie nie ma jak wyjsc. */
			if (!pytanie || wyslijBtn.disabled) { return; }
			polePytania.value = '';
			dodajWiadomosc('gosc', pytanie);
			polePytania.disabled = true;
			wyslijBtn.disabled = true;
			pokazPisanie();
			wyslij('chatl_pytanie', { pytanie: pytanie })
				.then(function (odp) {
					if (odp.success && odp.data && odp.data.przejeta) {
						/* Rozmowe prowadzi czlowiek: bot MILCZY, a odpowiedz
						   operatora przyjdzie odpytywaniem. Zadnego pustego dymka. */
						odpytaj();
					} else if (odp.success) {
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
				})
				.finally(function () {
					/* finally, nie then: po bledzie gosc TEZ musi odzyskac pole,
					   inaczej jedna nieudana proba zamyka mu czat na stale. */
					ukryjPisanie();
					polePytania.disabled = false;
					wyslijBtn.disabled = false;
					polePytania.focus();
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
				/* Pierwsze pytanie OD RAZU po otwarciu: operator mogl przejac
				   rozmowe, gdy okno bylo zamkniete i nikt nie pytal. */
				dajSesje().then(odpytaj);
			} else {
				zatrzymajOdpytywanie();
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
