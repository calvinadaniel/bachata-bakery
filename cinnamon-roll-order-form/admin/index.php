<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Location: ' . (empty($_SESSION['bb_admin_authed']) ? 'login.php' : 'dashboard.php'));
exit;
