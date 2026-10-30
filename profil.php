<?php
require_once __DIR__ . '/bootstrap.php';

// 5. Védett oldal ellenőrzése
$uid = bejelentkezve_kell();

// Felhasználó adatainak lekérése az adatbázisból
$stmt = db()->prepare('SELECT id, nev, email, letrehozva FROM felhasznalo WHERE id = ?');
$stmt->execute([$uid]);
$user = $stmt->fetch();

if (!$user) {
    // Ha az adatbázisból időközben törölték volna
    $_SESSION = [];
    header('Location: /belepes.php', true, 302);
    exit;
}
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profilom - Hallgatói Portál</title>
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
            max-width: 480px;
            width: 100%;
            padding: 2rem;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 1rem;
            margin-bottom: 1.5rem;
        }
        h1 {
            margin: 0;
            font-size: 1.4rem;
            color: #111827;
        }
        .badge {
            background: #dbeafe;
            color: #1e40af;
            font-size: 0.75rem;
            font-weight: 600;
            padding: 0.25rem 0.5rem;
            border-radius: 9999px;
            text-transform: uppercase;
        }
        .profile-data {
            margin-bottom: 2rem;
        }
        .profile-item {
            display: flex;
            flex-direction: column;
            margin-bottom: 1rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px dashed #e5e7eb;
        }
        .profile-item:last-child {
            border-bottom: none;
        }
        .profile-label {
            font-size: 0.8rem;
            font-weight: 600;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .profile-value {
            font-size: 1.05rem;
            color: #1f2937;
            margin-top: 0.25rem;
            font-weight: 500;
        }
        .logout-form button {
            width: 100%;
            background-color: #ef4444;
            color: white;
            font-weight: 600;
            padding: 0.75rem;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 1rem;
            transition: background-color 0.15s ease-in-out;
        }
        .logout-form button:hover {
            background-color: #dc2626;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Profilom</h1>
            <span class="badge">Védett oldal</span>
        </div>

        <div class="profile-data">
            <div class="profile-item">
                <span class="profile-label">Azonosító (UID)</span>
                <span class="profile-value">#<?= h((string)$user['id']) ?></span>
            </div>
            <div class="profile-item">
                <span class="profile-label">Név</span>
                <span class="profile-value"><?= h($user['nev']) ?></span>
            </div>
            <div class="profile-item">
                <span class="profile-label">E-mail cím</span>
                <span class="profile-value"><?= h($user['email']) ?></span>
            </div>
            <div class="profile-item">
                <span class="profile-label">Regisztráció időpontja</span>
                <span class="profile-value"><?= h($user['letrehozva']) ?></span>
            </div>
        </div>

        <form method="POST" action="/kilepes.php" class="logout-form">
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
            <button type="submit">Kijelentkezés (POST)</button>
        </form>
    </div>
</body>
</html>
