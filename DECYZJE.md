# DECYZJE PROJEKTOWE — rejestr (decyzja + uzasadnienie + odrzucone alternatywy)

Zamiennik pytań do zlecającego (egzamin ich zakazuje). Decyzje „drogo cofnąć" klepie Dzidek.

| # | Decyzja | Uzasadnienie / źródło | Odrzucone alternatywy | Kto |
|---|---------|----------------------|----------------------|-----|
| D1 | Zaplecze: Cloudflare Worker+D1+R2, frontend czysty statyczny | 0 zł, limity potwierdzone; strona-gospodarz nie wykona kodu | Vercel free (zakaz komercji — cytat), Supabase (drugie konto, D1 wystarcza), VPS (koszt ×4), WooCommerce (wymaga WP) | Dzidek 16.08 |
| D2 | Płatności: Stripe Checkout, tryb testowy na egzamin | zero opłat stałych, hostowana strona płatności (PCI u nich), tryb testowy bez KYC | P24/PayU (firma niepewna), Tpay (opłata aktywacyjna), Polar/Gumroad (panel i baza u nich = egzamin nie nasz) | Dzidek 16.08 |
| D3 | Panel admina NA ORIGINIE WORKERA | wspólny origin z gospodarzem = kradzież sesji przez skrypty hosta (zbieżność 5 krytyk) | panel w katalogu sklep/ na stronie hosta | Gosia 17.08 (runda 1) |
| D4 | Pobrania przez endpoint Workera (HMAC, streaming, atomowy licznik PRZY STARCIE, limit 10, bez Accept-Ranges) | presigned URL nie liczy pobrań; pełności transferu nie da się wykryć; skanery poczty zużywają sloty | presigned GET z R2; licznik „po pełnym pobraniu" | Gosia 17.08 (runda 1+2) |
| D5 | Webhook: INSERT ON CONFLICT + wznowienie; błąd aż do domknięcia; mail_w_toku przed wysyłką; okno duplikatu maila zaakceptowane | ponowienia Stripe DOKAŃCZAJĄ robotę; lepszy podwójny mail niż żaden | zwracanie 200 przy częściowym przetworzeniu; własna kolejka (przerost) | Gosia 17.08 |
| D6 | BEZ przecen i BEZ sekcji opinii w v1 | Omnibus: obniżki wymagają historii cen, opinie wymagają weryfikacji kupujących — nie mamy ani jednego, ani drugiego | tabela historia_cen + system opinii (przerost; lekcja „16 tabel zamiast 4") | Gosia 17.08 |
| D7 | Kursy poza zakresem; „usuwanie" produktów = deaktywacja przy zamówieniach; snapshot produktu w zamówieniu | kursy = egzamin innego zdającego (Dzidek); integralność historii = wzorzec kartki MP | twarde DELETE z kaskadą | Dzidek+Gosia 17.08 |
| D8 | Cel nr 1 = zdać egzamin; wszystko w trybie testowym; produkcja = jawny rozdział różnic (nie „podmiana kluczy") | decyzja Dzidka 16.08 ~23:45; krytyki B2/C3: obietnica „podmiany kluczy" to fałsz | budowa od razu produkcyjna | Dzidek 16.08 |
| D9 | Repo prywatne na koncie klienckim; blokada main = lokalny hook (GitHub free nie da protection na prywatnym) | kod = produkt na sprzedaż dla 4 osób; MP było publiczne — tu świadomie nie | repo publiczne z twardą protection | Gosia 17.08 (Dzidek może zmienić) |
| D10 | SEO-minimum świadomie ograniczone: produkty renderowane z API, meta ogólne, JSON-LD skryptem | konflikt z wymogiem FUNKCJONALNYM „nowy produkt bez zmian kodu" — litera notatki wygrywa z naszym dodatkiem | pre-render produktów (łamie CRUD-bez-rebuilda) | Gosia 17.08 (runda 2) |
| D11 | Anonimizacja: email→NULL + usunieto_dnia; retencja logów 90 dni; DPA u dostawców w instrukcji | skrót emaila to nadal dana osobowa (krytyka 6); kwoty/czasy zostają dla dashboardu | hash emaila; twarde kasowanie wierszy | Gosia 17.08 (runda 2) |
| D12 | Interpretacje litery notatki: S-04 „w jakim czasie"=rozkład w czasie · S-07 bez kont kupujących · S-09 „kto"=login_wpisany + log akcji | MAPA-SPEC.md — do klepnięcia | — | ⬜ czeka na Dzidka |
| D13 | Wariant A wpięcia (link w źródle) preferowany; B (skrypt) tylko fallback | manipulacja cudzym DOM krucha (hydracja/re-render); tylko zmiana w źródle przeżywa rebuild | wstrzykiwanie DOM jako równorzędne | Gosia 17.08 (runda 2) |
| D14 | Bank mutantów + kalibracja audytu ZOSTAJE mimo uwagi „przerost" | MP zmierzone 4.08: audyt bez kalibracji dał „zero znalezisk" i był bezwartościowy; koszt ~30 min | rezygnacja z kalibracji | Gosia 17.08 |
