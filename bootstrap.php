<?php
// 2. Biztonságos session-beállítások
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    ini_set('session.use_strict_mode', '1');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => !empty($_SERVER['HTTPS']),
    ]);
    session_start();
}

/**
 * Adatbázis-kapcsolat biztosítása PDO-val (SQLite fájlalapú adatbázis).
 * Automatikusan létrehozza a táblát, amennyiben még nem létezik.
 */
function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dbPath = __DIR__ . '/adatbazis.sqlite';
        $pdo = new PDO('sqlite:' . $dbPath, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        // 1. Felhasználói tábla létrehozása (SQLite specifikus szintaxis)
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS felhasznalo (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                nev VARCHAR(80) NOT NULL,
                email VARCHAR(254) NOT NULL UNIQUE,
                jelszo_hash VARCHAR(255) NOT NULL,
                letrehozva TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        ");
    }
    return $pdo;
}

/**
 * 5. Védett oldal auth-ellenőrzése
 */
function bejelentkezve_kell(): int {
    if (empty($_SESSION['uid'])) {
        header('Location: /belepes.php', true, 302);
        exit;
    }
    return (int)$_SESSION['uid'];
}

/**
 * CSRF token lekérése vagy generálása
 */
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * CSRF token ellenőrzése
 */
function csrf_verify(?string $token): bool {
    return !empty($token) && !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Biztonságos HTML kódolás XSS ellen
 */
function h(?string $str): string {
    return htmlspecialchars($str ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
