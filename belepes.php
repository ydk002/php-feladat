<?php
require_once __DIR__ . '/bootstrap.php';

// Ha már be van jelentkezve, vigyük a profiljára
if (!empty($_SESSION['uid'])) {
    header('Location: /profil.php', true, 303);
    exit;
}

$hiba = '';
$siker = $_SESSION['siker_uzenet'] ?? null;
unset($_SESSION['siker_uzenet']);

// Munkamenet alapú rate-limiting: 5 hibás kísérlet után 5 perc tiltás
$most = time();
$tiltas_idotartam = 5 * 60; // 5 perc (300 másodperc)

if (!empty($_SESSION['tiltva_eddig']) && $_SESSION['tiltva_eddig'] > $most) {
    http_response_code(429);
    $hatralevo = $_SESSION['tiltva_eddig'] - $most;
    $percek = ceil($hatralevo / 60);
    $hiba = "Túl sok hibás bejelentkezési kísérlet! Kérjük, várjon még {$percek} percet a következő próbálkozásig. (HTTP 429)";
} elseif (!empty($_SESSION['tiltva_eddig']) && $_SESSION['tiltva_eddig'] <= $most) {
    // Lejárt az 5 perc büntetés, nullázzuk a számlálót
    unset($_SESSION['tiltva_eddig']);
    $_SESSION['hibas_probak'] = 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_SESSION['tiltva_eddig'])) {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $hiba = 'Érvénytelen vagy lejárt biztonsági token (CSRF).';
    } else {
        $email = trim($_POST['email'] ?? '');
        $jelszo = $_POST['jelszo'] ?? '';

        // 4. Bejelentkezés
        $stmt = db()->prepare('SELECT id, jelszo_hash FROM felhasznalo WHERE email = ?');
        $stmt->execute([mb_strtolower($email)]);
        $f = $stmt->fetch();

        if ($f && password_verify($jelszo, $f['jelszo_hash'])) {
            // Sikeres belépés: munkamenet-fixáció megelőzése
            session_regenerate_id(true);
            $_SESSION['uid'] = (int)$f['id'];
            $_SESSION['hibas_probak'] = 0;
            unset($_SESSION['tiltva_eddig']);

            // Ha a jelszó hash algoritmus vagy költségfaktor elavult, frissítsük
            if (password_needs_rehash($f['jelszo_hash'], PASSWORD_DEFAULT, ['cost' => 12])) {
                $ujHash = password_hash($jelszo, PASSWORD_DEFAULT, ['cost' => 12]);
                $updateStmt = db()->prepare('UPDATE felhasznalo SET jelszo_hash = ? WHERE id = ?');
                $updateStmt->execute([$ujHash, $f['id']]);
            }

            header('Location: /profil.php', true, 303);
            exit;
        }

        // Sikertelen belépés: hibaszámláló növelése és általános hibaüzenet
        $_SESSION['hibas_probak'] = ($_SESSION['hibas_probak'] ?? 0) + 1;

        if ($_SESSION['hibas_probak'] >= 5) {
            $_SESSION['tiltva_eddig'] = $most + $tiltas_idotartam;
            http_response_code(429);
            $hiba = 'Túl sok hibás bejelentkezési kísérlet! Kérjük, várjon 5 percet. (HTTP 429)';
        } else {
            http_response_code(401);
            $hiba = 'Hibás e-mail vagy jelszó.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bejelentkezés - Hallgatói Portál</title>
    <style>
        body {
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background-color: #f3f4f6;
            margin: 0;
            padding: 2rem 1rem;
            display: flex;
            justify-content: center;
        }
        .container {
            background: #ffffff;
            border-radius: 8px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            max-width: 420px;
            width: 100%;
            padding: 2rem;
        }
        h1 {
            margin-top: 0;
            font-size: 1.5rem;
            color: #1f2937;
            text-align: center;
        }
        .form-group {
            margin-bottom: 1.25rem;
        }
        label {
            display: block;
            margin-bottom: 0.35rem;
            font-weight: 500;
            color: #374151;
            font-size: 0.9rem;
        }
        input[type="email"],
        input[type="password"] {
            width: 100%;
            box-sizing: border-box;
            padding: 0.65rem 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 0.95rem;
        }
        input:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
        }
        .alert-error {
            background-color: #fee2e2;
            color: #991b1b;
            padding: 0.75rem 1rem;
            border-radius: 6px;
            margin-bottom: 1.25rem;
            font-size: 0.9rem;
        }
        .alert-success {
            background-color: #dcfce7;
            color: #166534;
            padding: 0.75rem 1rem;
            border-radius: 6px;
            margin-bottom: 1.25rem;
            font-size: 0.9rem;
        }
        button {
            width: 100%;
            background-color: #2563eb;
            color: white;
            font-weight: 600;
            padding: 0.75rem;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 1rem;
        }
        button:hover {
            background-color: #1d4ed8;
        }
        button:disabled {
            background-color: #9ca3af;
            cursor: not-allowed;
        }
        .links {
            margin-top: 1.5rem;
            text-align: center;
            font-size: 0.9rem;
            color: #6b7280;
        }
        .links a {
            color: #2563eb;
            text-decoration: none;
        }
        .links a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Hallgatói Portál<br><small style="font-size: 1rem; color: #6b7280;">Bejelentkezés</small></h1>

        <?php if ($siker): ?>
            <div class="alert-success"><?= h($siker) ?></div>
        <?php endif; ?>

        <?php if ($hiba): ?>
            <div class="alert-error"><?= h($hiba) ?></div>
        <?php endif; ?>

        <form method="POST" action="/belepes.php" novalidate>
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">

            <div class="form-group">
                <label for="email">E-mail cím</label>
                <input type="email" id="email" name="email" value="<?= h($_POST['email'] ?? '') ?>" required <?= !empty($_SESSION['tiltva_eddig']) ? 'disabled' : '' ?>>
            </div>

            <div class="form-group">
                <label for="jelszo">Jelszó</label>
                <input type="password" id="jelszo" name="jelszo" required <?= !empty($_SESSION['tiltva_eddig']) ? 'disabled' : '' ?>>
            </div>

            <button type="submit" <?= !empty($_SESSION['tiltva_eddig']) ? 'disabled' : '' ?>>Belépés</button>
        </form>

        <div class="links">
            Még nincs fiókod? <a href="/regisztracio.php">Regisztrálj itt!</a>
        </div>
    </div>
</body>
</html>
