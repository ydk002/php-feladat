<?php

function request($method, $url, $data = [], $cookies = [], $followRedirects = false) {
    $ch = curl_init();
    
    $cookieStr = '';
    foreach ($cookies as $k => $v) {
        $cookieStr .= "$k=$v; ";
    }
    
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, $followRedirects);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
    curl_setopt($ch, CURLOPT_TIMEOUT, 6);
    curl_setopt($ch, CURLOPT_FORBID_REUSE, true);
    curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    }
    
    if ($cookieStr) {
        curl_setopt($ch, CURLOPT_COOKIE, rtrim($cookieStr, '; '));
    }

    $raw = curl_exec($ch);
    $info = curl_getinfo($ch);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $headerText = substr($raw, 0, $headerSize);
    $body = substr($raw, $headerSize);

    // Sütik kinyerése
    preg_match_all('/^Set-Cookie:\s*([^;=]+)=([^;]+)/mi', $headerText, $matches);
    $newCookies = $cookies;
    for ($i = 0; $i < count($matches[1]); $i++) {
        $newCookies[trim($matches[1][$i])] = trim($matches[2][$i]);
    }

    // Location kinyerése
    preg_match('/^Location:\s*(.*)$/mi', $headerText, $locMatches);
    $location = isset($locMatches[1]) ? trim($locMatches[1]) : null;

    curl_close($ch);

    return [
        'status' => $info['http_code'],
        'headers' => $headerText,
        'body' => $body,
        'cookies' => $newCookies,
        'location' => $location
    ];
}

function getCsrfToken($body) {
    preg_match('/name="csrf_token"\s+value="([^"]+)"/', $body, $m);
    return $m[1] ?? '';
}

function isPortOpen($host, $port) {
    $fp = @fsockopen($host, $port, $errno, $errstr, 0.3);
    if ($fp) {
        fclose($fp);
        return true;
    }
    return false;
}

echo "=== ELLENŐRZŐ TESZT INDÍTÁSA ===\n\n";

$phpBin = PHP_BINARY;
$testPort = 8085;
$base = "http://127.0.0.1:$testPort";

echo "Önálló teszt-szerver indítása a(z) $testPort porton...\n";

// Elindítunk egy izolált tesztszervert a háttérben
$descriptors = [
    0 => ['pipe', 'r'],
    1 => ['pipe', 'w'],
    2 => ['pipe', 'w']
];

$cmd = "\"$phpBin\" -S 127.0.0.1:$testPort -t \"" . __DIR__ . "\"";
$serverProcess = proc_open($cmd, $descriptors, $pipes);

// Gondoskodunk a folyamat leállításáról kilépéskor
register_shutdown_function(function() use ($serverProcess) {
    if ($serverProcess && is_resource($serverProcess)) {
        proc_terminate($serverProcess);
        proc_close($serverProcess);
    }
});

// Várunk, amíg a tesztszerver elérhetővé válik
$ready = false;
for ($i = 0; $i < 20; $i++) {
    usleep(150000); // 150ms
    if (isPortOpen('127.0.0.1', $testPort)) {
        $ready = true;
        break;
    }
}

if (!$ready) {
    die("HIBA: Nem sikerült elindítani a tesztszervert a 127.0.0.1:$testPort címen.\n");
}

echo "Tesztszerver aktív: $base\n\n";

// 1. Regisztráció GET tokenért
$res = request('GET', "$base/regisztracio.php");
if ($res['status'] === 0 || empty($res['body'])) {
    die("HIBA: Nem érkezett válasz a szervertől.\n");
}

$token = getCsrfToken($res['body']);
$cookies = $res['cookies'];
assert(!empty($token), "CSRF token nem található a regisztrációs oldalon");

// 1. Teszt: POST /regisztracio.php -> 303 -> /belepes.php
echo "1. Teszt: Regisztráció kérés elküldése...\n";
$email = "teszt_" . time() . "_" . mt_rand(1000, 9999) . "@portal.hu";
$regRes = request('POST', "$base/regisztracio.php", [
    'csrf_token' => $token,
    'nev' => 'Minta Janos',
    'email' => $email,
    'jelszo' => 'NagyonTitkos123!',
    'jelszo2' => 'NagyonTitkos123!'
], $cookies);

echo "   HTTP Státusz: {$regRes['status']} (Elvárt: 303)\n";
echo "   Átirányítás célja: {$regRes['location']} (Elvárt: /belepes.php)\n";
assert($regRes['status'] === 303, "A státuszkódnak 303-nak kell lennie");
assert($regRes['location'] === '/belepes.php', "A Location fejlécnek /belepes.php-re kell mutatnia");

// 2. Teszt: Adatbázis ellenőrzése ($2y$12$ hash, sehol nyers jelszó)
echo "\n2. Teszt: Adatbázisban a hash ellenőrzése...\n";
require_once __DIR__ . '/bootstrap.php';
$stmt = db()->prepare("SELECT * FROM felhasznalo WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch();
assert($user !== false, "Felhasználó nem található az adatbázisban");
echo "   Tárolt hash: {$user['jelszo_hash']}\n";
assert(str_starts_with($user['jelszo_hash'], '$2y$12$'), "A hash nem \$2y\$12\$ kezdetű bcrypt!");
assert(!str_contains($user['jelszo_hash'], 'NagyonTitkos123!'), "A jelszó nem hash formátumban van tárolva!");
echo "   -> Jelszó biztonságosan hash-elve (\$2y\$12\$...), nyers jelszó nincs az adatbázisban: OK\n";

// 3. Teszt: Másodszori regisztráció ugyanazzal az e-maillel (UNIQUE kényszer)
echo "\n3. Teszt: Duplikált regisztráció (UNIQUE-sértés)...\n";
$regPageAgain = request('GET', "$base/regisztracio.php", [], $cookies);
$token2 = getCsrfToken($regPageAgain['body']);
$dupRes = request('POST', "$base/regisztracio.php", [
    'csrf_token' => $token2,
    'nev' => 'Masik Nev',
    'email' => $email,
    'jelszo' => 'NagyonTitkos123!',
    'jelszo2' => 'NagyonTitkos123!'
], $regPageAgain['cookies']);
assert(str_contains($dupRes['body'], 'Ezzel az e-maillel már regisztráltak.'), "Nem jelent meg a barátságos hibaüzenet");
echo "   -> Barátságos hibaüzenet megjelent: 'Ezzel az e-maillel már regisztráltak.': OK\n";

// 4. Teszt: Rossz jelszó -> 401 és „Hibás e-mail vagy jelszó.”
echo "\n4. Teszt: Rossz jelszóval bejelentkezés...\n";
$loginPage = request('GET', "$base/belepes.php");
$loginToken = getCsrfToken($loginPage['body']);
$loginCookies = $loginPage['cookies'];

$badLogin = request('POST', "$base/belepes.php", [
    'csrf_token' => $loginToken,
    'email' => $email,
    'jelszo' => 'RosszJelszo'
], $loginCookies);
echo "   HTTP Státusz: {$badLogin['status']} (Elvárt: 401)\n";
assert($badLogin['status'] === 401, "Rossz jelszó esetén 401-et kell kapni");
assert(str_contains($badLogin['body'], 'Hibás e-mail vagy jelszó.'), "Hibaüzenet hiányzik");
echo "   -> 401-es kód és 'Hibás e-mail vagy jelszó.' megérkezett: OK\n";

// 5. Teszt: 5. sikertelen próba után 429
echo "\n5. Teszt: Rate limiting (5 hibás kísérlet)...\n";
for ($i = 2; $i <= 5; $i++) {
    $lp = request('GET', "$base/belepes.php", [], $loginCookies);
    $tok = getCsrfToken($lp['body']);
    $badLogin = request('POST', "$base/belepes.php", [
        'csrf_token' => $tok,
        'email' => $email,
        'jelszo' => 'RosszJelszo' . $i
    ], $loginCookies);
}
echo "   5. próba utáni HTTP Státusz: {$badLogin['status']} (Elvárt: 429)\n";
assert($badLogin['status'] === 429, "5. próbálkozás után 429 Too Many Requests-et kell kapni");
assert(str_contains($badLogin['body'], 'Túl sok hibás bejelentkezési kísérlet'), "429-es üzenet hiányzik");
echo "   -> 429 Too Many Requests működik: OK\n";

// 6. Teszt: Sikeres belépés, új PHPSESSID (Set-Cookie) és fixáció védelem
echo "\n6. Teszt: Sikeres belépés és munkamenet-fixáció megelőzése...\n";
// Új tiszta kliens
$freshPage = request('GET', "$base/belepes.php");
$oldSessionId = $freshPage['cookies']['PHPSESSID'] ?? null;
$tok = getCsrfToken($freshPage['body']);
$freshCookies = $freshPage['cookies'];

$goodLogin = request('POST', "$base/belepes.php", [
    'csrf_token' => $tok,
    'email' => $email,
    'jelszo' => 'NagyonTitkos123!'
], $freshCookies);

echo "   HTTP Státusz: {$goodLogin['status']} (Elvárt: 303)\n";
echo "   Location: {$goodLogin['location']} (Elvárt: /profil.php)\n";
$newSessionId = $goodLogin['cookies']['PHPSESSID'] ?? null;
echo "   Régi Session ID: $oldSessionId\n";
echo "   Új Session ID:   $newSessionId\n";
assert($goodLogin['status'] === 303, "Sikeres belépés után 303 átirányítás kell");
assert($goodLogin['location'] === '/profil.php', "Location /profil.php kell legyen");
assert($newSessionId !== null && $newSessionId !== $oldSessionId, "session_regenerate_id-nek új azonosítót kellett adnia!");

// Ellenőrizzük, hogy a támadó régi (fixált) session azonosítója valóban érvénytelen-e
$fixationAttempt = request('GET', "$base/profil.php", [], ['PHPSESSID' => $oldSessionId]);
assert($fixationAttempt['status'] === 302 && $fixationAttempt['location'] === '/belepes.php', "A régi session azonosítónak érvénytelennek kell lennie!");
echo "   -> Új PHPSESSID kiállítva, a támadó régi azonosítója érvénytelen (302 -> /belepes.php): OK\n";

// 7. Teszt: Védett profil oldal megnyitása
echo "\n7. Teszt: Védett profil oldal elérése belépve...\n";
$profilePage = request('GET', "$base/profil.php", [], $goodLogin['cookies']);
echo "   HTTP Státusz: {$profilePage['status']}\n";
assert($profilePage['status'] === 200, "Profil oldalnak 200-zal kell betöltődnie");
assert(str_contains($profilePage['body'], 'Minta Janos'), "Profil oldal nem tartalmazza a nevet");
assert(str_contains($profilePage['body'], 'Kijelentkezés (POST)'), "Kijelentkező gomb hiányzik");
echo "   -> Profil oldal sikeresen betöltve a felhasználó adataival: OK\n";

// 8. Teszt: Kijelentkezés (POST + CSRF) és utána profil elérése (302 -> /belepes.php)
echo "\n8. Teszt: Kijelentkezés és védett oldal visszairányítása...\n";
$logoutToken = getCsrfToken($profilePage['body']);
$logoutRes = request('POST', "$base/kilepes.php", [
    'csrf_token' => $logoutToken
], $goodLogin['cookies']);
echo "   Kijelentkezés státusz: {$logoutRes['status']} (Elvárt: 303)\n";
echo "   Location: {$logoutRes['location']} (Elvárt: /belepes.php)\n";

// Most meglátogatjuk a /profil.php-t a kijelentkeztetett sütikkel
$afterLogoutProfile = request('GET', "$base/profil.php", [], $logoutRes['cookies']);
echo "   Kijelentkezés után /profil.php státusz: {$afterLogoutProfile['status']} (Elvárt: 302)\n";
echo "   Location: {$afterLogoutProfile['location']} (Elvárt: /belepes.php)\n";
assert($afterLogoutProfile['status'] === 302, "Kijelentkezés után a profil 302-vel kell átirányítson");
assert($afterLogoutProfile['location'] === '/belepes.php', "302 átirányítás célja /belepes.php kell legyen");
echo "   -> Kijelentkezés után a profil 302-vel a belépéshez irányít: OK\n";

echo "\n=============================================\n";
echo " MINDEN ELVÁRT EREDMÉNY SIKERESEN TELJESÜLT! \n";
echo "=============================================\n";
