/* Płatność OSADZONA (0.9.25): formularz karty Stripe na naszej stronie produktu, w kolorach strony.
   Wzorzec: Checkout Sessions API + Elements (Stripe.js „dahlia”): serwer tworzy sesję `ui_mode=elements`
   i oddaje `client_secret`; my montujemy elementy Stripe (e-mail, karta) i wołamy `confirm()`; Stripe
   po zapłacie przekierowuje na `return_url` = nasza strona „Dziękujemy” (ta sama co dotąd).
   Dane karty nigdy nie przechodzą przez naszą stronę — siedzą w ramce Stripe (PCI po ich stronie).
   Bez tego skryptu (brak JS, brak klucza publikowalnego) formularz idzie zwykłym POST-em na stronę
   hostowaną Stripe — zapas, który działał do 0.9.24. */
(function () {
  'use strict';
  var cfg = window.sklepagPlatnosc;
  var form = document.getElementById('sklepag-kup-formularz');
  var panel = document.getElementById('sklepag-platnosc');
  if (!cfg || !cfg.pk || !form || !panel || typeof window.Stripe !== 'function') { return; }

  var btnZaplac = document.getElementById('sklepag-platnosc-zaplac');
  var btnWroc = document.getElementById('sklepag-platnosc-wroc');
  /* ⛔ ETYKIETY BIERZEMY Z DOM-u, NIE Z KODU. Skrypt nadpisuje napisy na przyciskach („Otwieram
     płatność…", „Przetwarzam…") i musi je potem ODTWORZYĆ. Dopóki odtwarzał je z własnych literałów,
     niósł drugą kopię tekstu: serwer mógł przysłać „Kup teraz", a przeglądarka i tak wpisywała
     „Kup teraz (płatność testowa)". Poprawka po stronie PHP była tam cofana w locie.
     Zapamiętane raz, przy starcie, zanim cokolwiek je zmieni. */
  var przyciskKup = form.querySelector('.sklepag-kup');
  var etykietaKup = przyciskKup ? przyciskKup.textContent : '';
  var etykietaZaplac = btnZaplac ? btnZaplac.textContent : '';
  var blad = document.getElementById('sklepag-platnosc-blad');
  var checkout = null;
  var actions = null;
  var wToku = false;

  function pokazBlad(tekst) {
    blad.textContent = tekst || '';
    blad.hidden = !tekst;
  }

  /* Paleta z tych samych zmiennych CSS co reszta sklepu (front.css) — jedno źródło prawdy o kolorach;
     wartości domyślne tylko na wypadek, gdyby arkusz nie doszedł. */
  function kolor(nazwa, domyslny) {
    try {
      /* Tokeny sklepu żyją na `.sklepag-wyspa` (front.css), nie na <html> — pytamy element, który je ma. */
      var zrodlo = document.querySelector('.sklepag-wyspa') || panel || document.documentElement;
      var v = getComputedStyle(zrodlo).getPropertyValue(nazwa).trim();
      return v || domyslny;
    } catch (e) { return domyslny; }
  }

  function appearance() {
    var k = {
      limonka: kolor('--sklepag-limonka', '#bfff38'),
      tlo: kolor('--sklepag-wegiel', '#101116'),
      papier: kolor('--sklepag-papier', '#f5f5f5'),
      pyl: kolor('--sklepag-pyl', '#8f929c')
    };
    return {
      theme: 'night',
      variables: {
        colorPrimary: k.limonka,
        colorBackground: k.tlo,
        colorText: k.papier,
        colorTextSecondary: k.pyl,
        colorTextPlaceholder: k.pyl,
        colorDanger: '#ffb454',
        fontFamily: 'Geist, "Segoe UI", Helvetica, Arial, sans-serif',
        fontSizeBase: '15px',
        borderRadius: '6px',
        spacingUnit: '4px'
      },
      rules: {
        '.Input': { border: '1px solid rgba(245,245,245,0.14)', boxShadow: 'none' },
        '.Input:focus': { border: '1px solid ' + k.limonka, boxShadow: 'none' },
        '.Label': { color: k.pyl, fontSize: '12px', letterSpacing: '0.08em', textTransform: 'uppercase' },
        '.Tab': { border: '1px solid rgba(245,245,245,0.14)' },
        '.Tab--selected': { border: '1px solid ' + k.limonka, color: k.papier }
      }
    };
  }

  /* Sesja z naszego serwera: ten sam endpoint co zwykły POST, z `osadzona=1` → JSON { clientSecret }. */
  function utworzSesje() {
    var dane = new FormData(form);
    dane.set('osadzona', '1');
    return fetch(cfg.endpoint, { method: 'POST', body: dane, credentials: 'same-origin' })
      .then(function (r) {
        /* Odpowiedź czytamy jako TEKST i dopiero próbujemy JSON-a: strona błędu hostingu (502, 503,
           limit czasu — HTML, nie JSON) dawała kupującemu surowe „SyntaxError: Unexpected token <”.
           Teraz dostaje zdanie po polsku z kodem HTTP, a przycisk wraca do „Kup teraz”. */
        return r.text().then(function (t) {
          var j = null;
          try { j = JSON.parse(t); } catch (e) { j = null; }
          if (!j && !r.ok) {
            throw new Error('Serwer sklepu odpowiedział błędem (HTTP ' + r.status + '). Odczekaj chwilę i spróbuj ponownie.');
          }
          if (!j) {
            throw new Error('Serwer sklepu odpowiedział w nieoczekiwany sposób. Odśwież stronę i spróbuj ponownie.');
          }
          return { ok: r.ok, j: j };
        });
      })
      .then(function (w) {
        if (!w.ok || !w.j || !w.j.clientSecret) {
          throw new Error((w.j && w.j.blad) || 'Nie udało się rozpocząć płatności. Spróbuj ponownie za chwilę.');
        }
        return w.j.clientSecret;
      });
  }

  function zamontuj(clientSecret) {
    /* Polskie etykiety w ramkach Stripe („Karta”, „Numer karty”, „Data ważności”) — reszta sklepu jest po polsku.
       developerTools: pływający „testing assistant” Stripe’a (guzik w prawym dolnym rogu, tylko na kluczu
       testowym) wyłączony — nachodził na bąbelek ChatBota i mylił oglądających; na kluczu live i tak go nie ma
       (docs.stripe.com/sdks/stripejs-testing-assistant, sekcja „Hide the testing assistant”). */
    var stripe = window.Stripe(cfg.pk, { locale: 'pl', developerTools: { assistant: { enabled: false } } });
    checkout = stripe.initCheckoutElementsSdk({
      clientSecret: Promise.resolve(clientSecret),
      elementsOptions: { appearance: appearance() }
    });
    checkout.on('change', function (session) {
      btnZaplac.disabled = !session.canConfirm || wToku;
    });
    /* `loaderror` = ramka Stripe nie wstała (zły klucz publikowalny, blokada sieci, CSP). Bez obsługi
       panel byłby pusty i niemy; z obsługą kupujący widzi powód i ma drogę zapasową („Wróć” → zwykły
       przycisk bez JS-owej ścieżki nie istnieje, więc podpowiadamy odświeżenie i kontakt). */
    var naBladLadowania = function (e) {
      var opis = e && e.error && e.error.message ? e.error.message : 'nieznany błąd Stripe';
      pokazBlad('Nie udało się załadować formularza płatności (' + opis + '). Odśwież stronę i spróbuj ponownie; jeśli to się powtarza, napisz do właściciela sklepu.');
      btnZaplac.disabled = true;
    };
    var kontakt = checkout.createContactDetailsElement();
    kontakt.on('loaderror', naBladLadowania);
    kontakt.mount('#sklepag-platnosc-kontakt');
    var karta = checkout.createPaymentElement();
    karta.on('loaderror', naBladLadowania);
    karta.mount('#sklepag-platnosc-karta');
    /* ⛔ `loadActions()` NIE oddaje obiektu akcji — oddaje KOPERTĘ `{ type, actions }`.
       Przypisanie tu całej odpowiedzi zostawiało `actions` bez metody `confirm()`, więc klik
       w „Zapłać" leciał `TypeError: actions.confirm is not a function`. Skutek dla kupującego:
       napis zmieniał się na „Przetwarzam…", przycisk gasł I TAK ZOSTAWAŁ, żaden komunikat się
       nie pokazywał, zamówienie nie powstawało, mail nie szedł. Sklep NIE SPRZEDAWAŁ.
       (Zmierzony kształt: `{ type: 'success', actions: { confirm, getSession, … } }`.) */
    return checkout.loadActions().then( function ( odpowiedz ) {
      var akcje = odpowiedz && odpowiedz.actions;
      if ( ! akcje || typeof akcje.confirm !== 'function' ) {
        /* ⛔ KONTRAKT SPRAWDZAMY PRZY MONTOWANIU, nie przy kliknięciu. Kupujący dowiaduje się
           o awarii, ZANIM wpisze numer karty, a nie po. Gdyby Stripe kiedyś zmienił kształt
           odpowiedzi, będzie to widoczny błąd z drogą wyjścia, a nie martwy przycisk. */
        var powod = odpowiedz && odpowiedz.error && odpowiedz.error.message
          ? odpowiedz.error.message
          : 'nieoczekiwany kształt odpowiedzi Stripe';
        throw new Error( 'Nie udało się przygotować płatności (' + powod + '). Odśwież stronę i spróbuj ponownie; jeśli to się powtarza, napisz do właściciela sklepu.' );
      }
      actions = akcje;
    } );
  }

  form.addEventListener('submit', function (e) {
    if (!form.reportValidity()) { return; }
    e.preventDefault();
    if (wToku) { return; }
    wToku = true;
    pokazBlad('');
    var przycisk = form.querySelector('.sklepag-kup');
    if (przycisk) { przycisk.disabled = true; przycisk.textContent = 'Otwieram płatność…'; }
    utworzSesje()
      .then(zamontuj)
      .then(function () {
        form.hidden = true;
        panel.hidden = false;
        panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
        wToku = false;
      })
      .catch(function (err) {
        wToku = false;
        if (przycisk) { przycisk.disabled = false; przycisk.textContent = etykietaKup; }
        /* Błąd naszego serwera (np. zły NIP) pokazujemy przy formularzu; błąd Stripe.js — też, ale
           z zapasem: kupujący może kliknąć jeszcze raz, a bez JS trafi na stronę hostowaną. */
        var gdzie = form.querySelector('.sklepag-zakup-notka');
        if (gdzie) { gdzie.textContent = String(err && err.message ? err.message : err); gdzie.classList.add('sklepag-zakup-notka--blad'); }
      });
  });

  btnZaplac.addEventListener('click', function () {
    if (wToku) { return; }
    /* ⛔ Brak `actions` kończył się CICHYM `return`: klik nie robił nic i nie mówił nic.
       Kupujący klikał „Zapłać" w kółko i nie miał jak się dowiedzieć, że nie ma czym zapłacić. */
    if (!actions) {
      pokazBlad('Płatność nie jest gotowa — formularz Stripe nie wstał. Kliknij „Wróć", odśwież stronę i spróbuj ponownie.');
      return;
    }
    wToku = true;
    btnZaplac.disabled = true;
    btnZaplac.textContent = 'Przetwarzam…';
    pokazBlad('');
    var odblokuj = function () {
      wToku = false;
      btnZaplac.disabled = false;
      btnZaplac.textContent = etykietaZaplac;
    };
    /* ⛔ `confirm()` potrafi rzucić SYNCHRONICZNIE — zanim powstanie jakakolwiek obietnica.
       Taki wyjątek NIE przechodzi przez `.catch()` niżej: `odblokuj()` nie biegło, przycisk
       zostawał martwy na „Przetwarzam…", a kupujący nie dostawał ani słowa. Dokładnie tak
       wyglądała wada „sklep nie sprzedaje". Ta osłona zamyka KLASĘ: jakikolwiek przyszły
       wyjątek na tej ścieżce kończy się komunikatem i odblokowanym przyciskiem, nie ciszą. */
    try {
    actions.confirm().then(function (wynik) {
      /* Tu trafiamy TYLKO przy natychmiastowym błędzie (np. karta odrzucona). Przy sukcesie Stripe
         przekierowuje na return_url (strona „Dziękujemy”), a 3-D Secure obsługuje w swojej ramce. */
      if (wynik && wynik.type === 'error') {
        pokazBlad(wynik.error && wynik.error.message ? wynik.error.message : 'Płatność nie przeszła. Sprawdź dane karty i spróbuj ponownie.');
      }
      odblokuj();
    }).catch(function (err) {
      /* Odrzucona obietnica (sieć, wyjątek Stripe.js) — bez tej gałęzi `wToku` zostawało true i ani
         „Zapłać”,ani „Wróć” nie dało się użyć. */
      pokazBlad(err && err.message ? err.message : 'Płatność przerwana. Spróbuj ponownie.');
      odblokuj();
    });
    } catch (err) {
      pokazBlad(err && err.message ? err.message : 'Płatność nie ruszyła. Odśwież stronę i spróbuj ponownie.');
      odblokuj();
    }
  });

  btnWroc.addEventListener('click', function () {
    panel.hidden = true;
    form.hidden = false;
    var przycisk = form.querySelector('.sklepag-kup');
    if (przycisk) { przycisk.disabled = false; przycisk.textContent = etykietaKup; }
    form.scrollIntoView({ behavior: 'smooth', block: 'start' });
  });
})();
