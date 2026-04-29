<?php
/**
 * admin/auth.php
 *
 * Session guard — include at the top of every protected admin page.
 * Starts the session, redirects to login if unauthenticated,
 * and ensures a CSRF token exists for the current session.
 */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

if (empty($_SESSION['bb_admin_authed'])) {
    header('Location: /admin/login.php');
    exit;
}

// Generate a CSRF token once per session
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

define('CSRF_TOKEN', $_SESSION['csrf_token']);

/**
 * Verify the CSRF token from a POST request.
 * Terminates with 403 on failure — never returns false.
 */
function verifyCsrf(): void
{
    $submitted = $_POST['csrf_token'] ?? '';
    $expected  = $_SESSION['csrf_token'] ?? '';

    if ($expected === '' || !hash_equals($expected, $submitted)) {
        http_response_code(403);
        exit('Invalid or missing CSRF token.');
    }
}
