<?php
require_once __DIR__ . '/bootstrap.php';

// 5. Kijelentkezés POST + CSRF ellenőrzéssel
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (csrf_verify($_POST['csrf_token'] ?? null)) {
        // Munkamenet ürítése
        $_SESSION = [];

        // Munkamenet-süti törlése a böngészőből
        $p = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            [
                'expires' => time() - 3600,
                'path' => $p['path'],
                'domain' => $p['domain'] ?? '',
                'secure' => !empty($_SERVER['HTTPS']),
                'httponly' => true,
                'samesite' => 'Lax'
            ]
        );

        // Munkamenet megsemmisítése
        session_destroy();

        header('Location: /belepes.php', true, 303);
        exit;
    }
}

// Ha GET kéréssel hívták vagy érvénytelen volt a CSRF token:
header('Location: /profil.php', true, 303);
exit;
