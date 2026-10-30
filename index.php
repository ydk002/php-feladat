<?php
require_once __DIR__ . '/bootstrap.php';

if (!empty($_SESSION['uid'])) {
    header('Location: /profil.php', true, 302);
    exit;
}

header('Location: /belepes.php', true, 302);
exit;
