<?php
/**
 * api/helpers/captcha.php
 *
 * hCaptcha server-side verification for checkout.
 *
 * Public API:
 *   verifyCaptcha(mixed $token): ?string  — null when OK, otherwise a customer-facing error
 *
 * HCAPTCHA_SECRET empty: allowed in sandbox (check skipped). Production refuses
 * an empty secret or the public always-pass test secret.
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';

final class CaptchaMisconfigured extends RuntimeException {}

const HCAPTCHA_TEST_SECRET = '0x0000000000000000000000000000000000000000';

function verifyCaptcha(mixed $token): ?string
{
    load_env();
    $secret       = $_ENV['HCAPTCHA_SECRET'] ?? '';
    $isProduction = ($_ENV['APP_ENV'] ?? 'sandbox') === 'production';

    if ($isProduction && ($secret === '' || $secret === HCAPTCHA_TEST_SECRET)) {
        throw new CaptchaMisconfigured('HCAPTCHA_SECRET must be a real key in production');
    }
    if ($secret === '') {
        return null;
    }

    if (!is_string($token) || $token === '' || strlen($token) > 8192) {
        return 'Please complete the “I am human” check.';
    }

    $ch = curl_init('https://api.hcaptcha.com/siteverify');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query([
            'secret'   => $secret,
            'response' => $token,
            'remoteip' => $_SERVER['REMOTE_ADDR'] ?? '',
            'sitekey'  => $_ENV['HCAPTCHA_SITE_KEY'] ?? '',
        ]),
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT        => 10,
    ]);
    $raw = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err !== '') {
        error_log("[bachata-bakery] hCaptcha verify transport error: {$err}");
        return 'We couldn’t verify the “I am human” check. Please try again.';
    }

    $data = json_decode((string) $raw, true) ?? [];
    if (($data['success'] ?? false) !== true) {
        error_log('[bachata-bakery] hCaptcha rejected: ' . implode(',', $data['error-codes'] ?? []));
        return 'The “I am human” check expired or failed. Please complete it again.';
    }

    return null;
}
