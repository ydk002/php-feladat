# PHP mintaalkalmazás 05 / 24 – Regisztráció és bejelentkezés

Hallgatói portál mini-változata: biztonságos regisztráció, bejelentkezés, csak bejelentkezve elérhető **„Profilom”** oldal és védett kijelentkezés. A projekt tartalmazza a klasszikus szerveroldali **PHP + PDO (SQLite)** megvalósítást, valamint a felhőalapú **Firebase Authentication + Cloud Firestore** megfelelőjét is.

---

## Főbb biztonsági megoldások

1. **Jelszavak biztonságos tárolása (`password_hash` / `password_verify`):**
   - A jelszót soha nem tároljuk nyers szövegként, kizárólag a sózott (salted) `$2y$12$…` bcrypt hash-ét.
   - Bejelentkezéskor a `password_needs_rehash()` ellenőrzi, hogy szükséges-e a hash frissítése.
2. **Adatbázis-szintű egyediség (`UNIQUE`):**
   - Az e-mail cím egyediségét az adatbázis `UNIQUE` kényszere garantálja (elkerülve a *SELECT, majd INSERT* versenyhelyzetet / TOCTOU hibát).
   - Ütközéskor a PDO `23000`-es SQLSTATE hibakódját elkapva barátságos üzenetet kap a felhasználó: *„Ezzel az e-maillel már regisztráltak.”*
3. **Fiók létezésének elfedése (User Enumeration elleni védelem):**
   - Sikertelen belépésnél (akár az e-mail nem létezik, akár a jelszó hibás) egységesen `401 Unauthorized` státuszkód és *„Hibás e-mail vagy jelszó.”* üzenet jelenik meg.
4. **Brute-force elleni védelem (Rate Limiting):**
   - 5 egymást követő hibás bejelentkezési kísérlet után a szerver 5 percre zárolja a próbálkozást, és `429 Too Many Requests` HTTP státuszkóddal válaszol.
5. **Munkamenet-fixáció (Session Fixation) elleni védelem:**
   - `session.use_strict_mode = 1`: a szerver csak saját maga által kiadott munkamenet-azonosítót fogad el.
   - Sikeres belépéskor a `session_regenerate_id(true)` új `PHPSESSID` sütit állít ki, és érvényteleníti a régit.
   - A munkamenet-süti `HttpOnly`, `SameSite=Lax` és (HTTPS esetén) `Secure` attribútumokkal van ellátva.
6. **Védett oldal és biztonságos kijelentkezés (POST + CSRF):**
   - A `/profil.php` elején a `bejelentkezve_kell()` függvény ellenőrzi a jogosultságot; hiányában `302 Found` válasszal a `/belepes.php`-re irányít.
   - A kijelentkezés (`/kilepes.php`) kizárólag `POST` kéréssel és érvényes CSRF tokennel hajtható végre, üríti a `$_SESSION` tömböt, törli a munkamenet-sütit, majd meghívja a `session_destroy()`-t.
7. **XSS és SQL Injection elleni védelem:**
   - Minden adatbázis-művelet paraméterezett lekérdezést (PDO prepared statements) használ.
   - Minden HTML kimenet a `h()` (`htmlspecialchars`) segédfüggvényen keresztül jelenik meg.

---

## Projektstruktúra

```text
php_minta_05/
├── bootstrap.php          # Session-beállítások, PDO SQLite kapcsolat és táblalétrehozás, CSRF és Auth segédfüggvények
├── index.php              # Kezdőoldal: belépve a /profil.php-ra, egyébként a /belepes.php-re irányít
├── regisztracio.php       # Regisztrációs űrlap, validáció, password_hash(), UNIQUE (23000) kezelés
├── belepes.php            # Bejelentkezés, 401/429 rate-limiting, password_verify(), session_regenerate_id(true)
├── profil.php             # Védett „Profilom” oldal (bejelentkezve_kell()), kijelentkező POST űrlap
├── kilepes.php            # Kijelentkezés (POST + CSRF ellenőrzés, süti törlés, session_destroy)
├── teszt.php              # Önálló automata ellenőrző tesztszkript (mind a 8 követelményt teszteli)
├── adatbazis.sqlite       # Automatikusan létrejövő SQLite adatbázis
├── .vscode/
│   └── tasks.json         # VS Code feladatok a szerver és a tesztek egykattintásos indításához
└── firebase_auth/         # Felhőalapú (Firebase Auth + Firestore) referencia-megvalósítás
    ├── firestore.rules    # Deklaratív Firestore biztonsági szabályok (/profilok/{uid})
    ├── app.js             # Firebase Modular SDK kliensoldali függvények (regisztral, belep, kilep)
    └── index.html         # Egyoldalas (SPA) felület a Firebase változathoz
```

---

## Futtatás helyben (PHP változat)

### 1. Indítás terminálból (vagy VS Code-ból)
Nyiss egy terminált a `php_minta_05` mappában, majd indítsd el a PHP beépített fejlesztői szerverét:

```bash
php -S localhost:8000
```

Ezután nyisd meg a böngésződben:
**[http://localhost:8000](http://localhost:8000)**

*(VS Code-ban a **Terminal → Run Task... → `PHP: Start Server`** menüponttal is elindítható.)*

---

## Automata ellenőrző teszt futtatása

A projekt tartalmaz egy önálló integrációs tesztszkriptet (`teszt.php`), amely automatikusan elindít egy ideiglenes tesztszervert a háttérben, végigfuttatja az összes elvárt forgatókönyvet, majd leállítja a tesztszervert:

```bash
php teszt.php
```

### Mit ellenőriz a `teszt.php`?
1. `POST /regisztracio.php` → `303 See Other` → `Location: /belepes.php`.
2. Az adatbázisban `$2y$12$…` kezdetű bcrypt hash tárolódik, nyers jelszó sehol nem szerepel.
3. Ugyanazzal az e-maillel történő második regisztrációkor `UNIQUE`-sértés kezelése és barátságos hibaüzenet.
4. Hibás jelszóval történő belépéskor `401 Unauthorized` és *„Hibás e-mail vagy jelszó.”*.
5. Az 5. sikertelen bejelentkezési kísérlet után `429 Too Many Requests` válasz.
6. Sikeres belépéskor új `PHPSESSID` kiállítása (`Set-Cookie`), átirányítás (`303`) a `/profil.php`-ra, valamint annak igazolása, hogy a régi (fixált) munkamenet-azonosító érvénytelen (`302` → `/belepes.php`).
7. Bejelentkezve a `/profil.php` `200 OK` státusszal betölti a hallgató adatait.
8. `POST /kilepes.php` után a `/profil.php` közvetlen elérése `302 Found` válasszal visszairányít a `/belepes.php`-re.

---

## PHP Backend vs. Firebase (BaaS) összehasonlítás

| Szempont | PHP + SQLite (`php_minta_05`) | Firebase (`firebase_auth`) |
|---|---|---|
| **Jelszókezelés** | `password_hash()` és `password_verify()` | Firebase Authentication kezeli (Firestore-ban tilos jelszót tárolni) |
| **Hitelesítési állapot** | Szerveroldali `$_SESSION` + `HttpOnly` süti | Kliensoldali JWT / ID token (`onAuthStateChanged`) |
| **Session Fixation védelem** | `session_regenerate_id(true)` + `use_strict_mode` | Automatikus tokenfrissítés a Firebase SDK-ban |
| **Brute-force védelem** | Saját számláló a munkamenetben (5 hiba → `429`) | Beépített (`auth/too-many-requests`) |
| **Jogosultság-ellenőrzés** | Szerveroldali PHP (`bejelentkezve_kell()`) | Szerveroldali `firestore.rules` (`request.auth.uid == uid`) |
