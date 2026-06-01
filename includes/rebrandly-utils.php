<?php
// ============================================================
// REBRANDLY UTILITIES — URL shortening and QR code generation
// ============================================================

class RebrandlyUtils {
    private static $api_key = REBRANDLY_API_KEY;
    private static $api_url = 'https://api.rebrandly.com/v1';

    public static function createShortUrl($longUrl, $title = null) {
        $data = [
            'destination' => $longUrl,
            'domain' => ['fullName' => 'rebrand.ly']
        ];

        if ($title) {
            $data['title'] = $title;
        }

        $response = self::makeRequest('/links', 'POST', $data);

        if (isset($response['shortUrl'])) {
            return $response['shortUrl'];
        }

        error_log("Rebrandly error creating short URL: " . json_encode($response));
        return null;
    }

    public static function getQRCode($shortUrl) {
        // Rebrandly provides QR codes for shortened URLs
        // Format: https://api.rebrandly.com/qr?url=<short_url>
        return 'https://api.rebrandly.com/qr?url=' . urlencode($shortUrl);
    }

    private static function makeRequest($endpoint, $method = 'GET', $data = null) {
        $ch = curl_init(self::$api_url . $endpoint);

        $headers = [
            'Content-Type: application/json',
            'apikey: ' . self::$api_key
        ];

        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true
        ]);

        if ($data && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300) {
            return json_decode($response, true);
        }

        return ['error' => "HTTP $httpCode", 'response' => $response];
    }
}
