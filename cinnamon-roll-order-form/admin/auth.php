<?php
/**
 * admin/auth.php
 *
 * Session guard — include at the top of every protected admin page
 * (before any other session_start()). Starts the session with hardened cookie
 * settings, redirects to login if unauthenticated, and ensures a CSRF token.
 *
 *   adminUrl(string $path): string — URL for a file under admin/, independent
 *       of where the backend is mounted (e.g. /cinnamon-roll-order-form/admin/).
 */

declare(strict_types=1);

function startAdminSession(): void
{
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
}

function adminUrl(string $path): string
{
    $script = $_SERVER['SCRIPT_NAME'] ?? '/admin/';
    $pos    = strrpos($script, '/admin/');
    $base   = $pos === false ? '/admin/' : substr($script, 0, $pos + 7);
    return $base . ltrim($path, '/');
}

startAdminSession();

if (empty($_SESSION['bb_admin_authed'])) {
    header('Location: ' . adminUrl('login.php'));
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

    if ($expected === '' || !is_string($submitted) || !hash_equals($expected, $submitted)) {
        http_response_code(403);
        exit('Invalid or missing CSRF token.');
    }
}

/** Redirect back to the dashboard for $windowId with a flash message key. */
function backToDashboard(string $windowId, string $msg): never
{
    header('Location: ' . adminUrl('dashboard.php') . '?' . http_build_query(['window' => $windowId, 'msg' => $msg]));
    exit;
}
