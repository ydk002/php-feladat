<?php
require_once __DIR__ . '/bootstrap.php';

// Ha már be van jelentkezve, vigyük a profiljára
if (!empty($_SESSION['uid'])) {
    header('Location: /profil.php', true, 303);
    exit;
}

$hibak = [];
$nev = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $hibak['altalanos'] = 'Érvénytelen vagy lejárt biztonsági token (CSRF).';
    } else {
        $nev = trim($_POST['nev'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $jelszo = $_POST['jelszo'] ?? '';
        $jelszo2 = $_POST['jelszo2'] ?? '';

        // Validáció
        if (mb_strlen($nev) < 2 || mb_strlen($nev) > 80) {
            $hibak['nev'] = 'A név megadása kötelező (2–80 karakter).';
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 254) {
            $hibak['email'] = 'Kérjük, adjon meg egy érvényes e-mail címet.';
        }

        if (mb_strlen($jelszo) < 10) {
            $hibak['jelszo'] = 'A jelszónak legalább 10 karakter hosszúnak kell lennie.';
        } elseif ($jelszo !== $jelszo2) {
            $hibak['jelszo2'] = 'A két jelszó nem egyezik meg.';
        }

        // Ha nincs hiba, mentés az adatbázisba
        if (empty($hibak)) {
            // 3. Regisztráció hash-sel és UNIQUE-kezeléssel
            $hash = password_hash($jelszo, PASSWORD_DEFAULT, ['cost' => 12]);
            try {
                $stmt = db()->prepare('INSERT INTO felhasznalo (nev, email, jelszo_hash) VALUES (?, ?, ?)');
                $stmt->execute([$nev, mb_strtolower($email), $hash]);

                $_SESSION['siker_uzenet'] = 'Sikeres regisztráció! Most már bejelentkezhet.';
                header('Location: /belepes.php', true, 303);
                exit;
            } catch (PDOException $ex) {
                // 23000: Integrity constraint violation (UNIQUE kulcs megsértése)
                if ($ex->getCode() === '23000') {
                    $hibak['email'] = 'Ezzel az e-maillel már regisztráltak.';
                } else {
                    throw $ex;
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Regisztráció - Hallgatói Portál</title>
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
        input[type="text"],
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
        .error {
            color: #dc2626;
            font-size: 0.825rem;
            margin-top: 0.35rem;
        }
        .alert-error {
            background-color: #fee2e2;
            color: #991b1b;
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
        <h1>Hallgatói Portál<br><small style="font-size: 1rem; color: #6b7280;">Regisztráció</small></h1>

        <?php if (!empty($hibak['altalanos'])): ?>
            <div class="alert-error"><?= h($hibak['altalanos']) ?></div>
        <?php endif; ?>

        <form method="POST" action="/regisztracio.php" novalidate>
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">

            <div class="form-group">
                <label for="nev">Teljes név</label>
                <input type="text" id="nev" name="nev" value="<?= h($nev) ?>" required>
                <?php if (!empty($hibak['nev'])): ?>
                    <div class="error"><?= h($hibak['nev']) ?></div>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="email">E-mail cím</label>
                <input type="email" id="email" name="email" value="<?= h($email) ?>" required>
                <?php if (!empty($hibak['email'])): ?>
                    <div class="error"><?= h($hibak['email']) ?></div>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="jelszo">Jelszó (legalább 10 karakter)</label>
                <input type="password" id="jelszo" name="jelszo" required>
                <?php if (!empty($hibak['jelszo'])): ?>
                    <div class="error"><?= h($hibak['jelszo']) ?></div>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="jelszo2">Jelszó ismétlése</label>
                <input type="password" id="jelszo2" name="jelszo2" required>
                <?php if (!empty($hibak['jelszo2'])): ?>
                    <div class="error"><?= h($hibak['jelszo2']) ?></div>
                <?php endif; ?>
            </div>

            <button type="submit">Regisztráció</button>
        </form>

        <div class="links">
            Van már fiókod? <a href="/belepes.php">Jelentkezz be itt!</a>
        </div>
    </div>
</body>
</html>
