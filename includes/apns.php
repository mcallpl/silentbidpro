<?php
// ============================================================
// APNs — native iOS push (token-based / .p8 auth over HTTP/2).
// Inert until APNS_KEY_ID + APNS_AUTH_KEY (the .p8 PEM) are configured, so it's
// safe to ship and wire in before the key exists.
// ============================================================

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/db-helpers.php';

function apnsConfigured(): bool {
    return defined('APNS_KEY_ID') && APNS_KEY_ID !== ''
        && defined('APNS_AUTH_KEY') && APNS_AUTH_KEY !== ''
        && defined('APNS_TEAM_ID') && APNS_TEAM_ID !== ''
        && defined('APNS_BUNDLE_ID') && APNS_BUNDLE_ID !== '';
}

function apnsB64Url(string $bin): string {
    return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
}

/**
 * Convert an openssl DER ECDSA signature to raw JOSE R||S (64 bytes for ES256).
 */
function apnsDerToJose(string $der): ?string {
    $part = 32; // P-256
    $off = 0; $len = strlen($der);
    if ($len < 8 || ord($der[$off++]) !== 0x30) return null;   // SEQUENCE
    $seqLen = ord($der[$off++]);
    if ($seqLen & 0x80) { $off += ($seqLen & 0x7f); }          // long-form length (rare here)
    if (ord($der[$off++]) !== 0x02) return null;               // INTEGER r
    $rLen = ord($der[$off++]); $r = substr($der, $off, $rLen); $off += $rLen;
    if (ord($der[$off++]) !== 0x02) return null;               // INTEGER s
    $sLen = ord($der[$off++]); $s = substr($der, $off, $sLen);
    $r = ltrim($r, "\x00"); $s = ltrim($s, "\x00");
    if (strlen($r) > $part || strlen($s) > $part) return null;
    return str_pad($r, $part, "\x00", STR_PAD_LEFT) . str_pad($s, $part, "\x00", STR_PAD_LEFT);
}

/** Build (and cache for the request) the APNs ES256 provider JWT. */
function apnsProviderToken() {
    static $cached = null;
    if ($cached && $cached['exp'] > time() + 60) return $cached['jwt'];
    if (!apnsConfigured()) return false;

    $header = apnsB64Url(json_encode(['alg' => 'ES256', 'kid' => APNS_KEY_ID]));
    $iat = time();
    $claims = apnsB64Url(json_encode(['iss' => APNS_TEAM_ID, 'iat' => $iat]));
    $signingInput = $header . '.' . $claims;

    $pkey = openssl_pkey_get_private(APNS_AUTH_KEY);
    if ($pkey === false) { error_log('[APNS] Could not load .p8 auth key'); return false; }

    $der = '';
    if (!openssl_sign($signingInput, $der, $pkey, OPENSSL_ALGO_SHA256)) {
        error_log('[APNS] openssl_sign failed'); return false;
    }
    $jose = apnsDerToJose($der);
    if ($jose === null) { error_log('[APNS] DER->JOSE failed'); return false; }

    $jwt = $signingInput . '.' . apnsB64Url($jose);
    $cached = ['jwt' => $jwt, 'exp' => $iat + 3000]; // refresh well before APNs' ~60min limit
    return $jwt;
}

/**
 * Send an alert push to every active iOS device of a user.
 * @param array $data extra top-level keys (e.g. ['item_id' => 5]) merged into the payload
 */
function sendApnsToUser($user_id, string $title, string $body, array $data = []): void {
    if (!apnsConfigured() || !dbTableExists('device_tokens')) return;

    $tokens = dbGetAll(
        "SELECT id, token FROM device_tokens WHERE user_id = ? AND is_active = 1 AND platform = 'ios'",
        [(int)$user_id]
    );
    if (!$tokens) return;

    $jwt = apnsProviderToken();
    if (!$jwt) return;

    $host = (defined('APNS_USE_SANDBOX') && APNS_USE_SANDBOX)
        ? 'api.sandbox.push.apple.com' : 'api.push.apple.com';
    $payload = json_encode(array_merge(
        ['aps' => ['alert' => ['title' => $title, 'body' => $body], 'sound' => 'default']],
        $data
    ));

    foreach ($tokens as $t) {
        $ch = curl_init("https://{$host}/3/device/{$t['token']}");
        curl_setopt_array($ch, [
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_2_0,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_HTTPHEADER     => [
                'authorization: bearer ' . $jwt,
                'apns-topic: ' . APNS_BUNDLE_ID,
                'apns-push-type: alert',
                'content-type: application/json',
            ],
        ]);
        $resp = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // 410 Unregistered / 400 BadDeviceToken → the token is dead, deactivate it.
        if ($code === 410 || ($code === 400 && strpos((string)$resp, 'BadDeviceToken') !== false)) {
            dbUpdate("UPDATE device_tokens SET is_active = 0 WHERE id = ?", [(int)$t['id']]);
        } elseif ($code !== 200) {
            error_log("[APNS] send failed ($code): " . substr((string)$resp, 0, 200));
        }
    }
}
