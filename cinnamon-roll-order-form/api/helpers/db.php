<?php
/**
 * api/helpers/db.php
 *
 * PDO connection singleton.
 * Call db() anywhere to get the shared PDO instance.
 *
 * Usage:
 *   require_once __DIR__ . '/db.php';
 *   $pdo = db();
 *   $stmt = $pdo->prepare('SELECT ...');
 */

declare(strict_types=1);

// ----------------------------------------------------------------
// .env loader
// Reads KEY=VALUE pairs from the project-root .env file.
// Does NOT override variables already set in the environment,
// which lets server-level env vars take precedence in production.
// ----------------------------------------------------------------
function load_env(): void
{
    static $loaded = false;
    if ($loaded) {
        return;
    }

    // Project root is two levels up from api/helpers/
    $envFile = dirname(__DIR__, 2) . '/.env';

    if (!is_file($envFile)) {
        throw new RuntimeException('.env file not found at: ' . $envFile);
    }

    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        // Skip comments
        if (str_starts_with(ltrim($line), '#')) {
            continue;
        }

        if (!str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $key   = trim($key);
        $value = trim($value);

        // Strip inline comments (e.g. VALUE=foo # comment)
        if (str_contains($value, ' #')) {
            $value = trim(explode(' #', $value, 2)[0]);
        }

        // Strip surrounding quotes
        if (preg_match('/^(["\']).*\1$/', $value)) {
            $value = substr($value, 1, -1);
        }

        if ($key !== '' && !array_key_exists($key, $_ENV)) {
            $_ENV[$key]    = $value;
            putenv("{$key}={$value}");
        }
    }

    $loaded = true;
}

// ----------------------------------------------------------------
// PDO singleton
// ----------------------------------------------------------------
function db(): PDO
{
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    load_env();

    $host   = $_ENV['DB_HOST'] ?? 'localhost';
    $dbname = $_ENV['DB_NAME'] ?? '';
    $user   = $_ENV['DB_USER'] ?? '';
    $pass   = $_ENV['DB_PASS'] ?? '';

    if ($dbname === '' || $user === '') {
        throw new RuntimeException('DB_NAME and DB_USER must be set in .env');
    }

    $dsn = "mysql:host={$host};dbname={$dbname};charset=utf8mb4";

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,  // real prepared statements only
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
    ];

    $pdo = new PDO($dsn, $user, $pass, $options);

    return $pdo;
}
