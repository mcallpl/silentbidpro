<?php
// ============================================================
// MAILER — authenticated SMTP (SSL) with a branded purchase
// receipt / welcome email for auction winners.
//
// Minimal dependency-free SMTP client (AUTH LOGIN over ssl://).
// Sending is a silent no-op when SMTP is not configured, so dev
// environments and tests never try to reach a mail server.
// ============================================================

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/db-helpers.php';
require_once __DIR__ . '/fulfillment.php';

/**
 * Is outgoing email configured?
 */
function mailerConfigured() {
    return SENDGRID_API_KEY !== ''
        || (SMTP_HOST !== '' && SMTP_USER !== '' && SMTP_PASS !== '');
}

/**
 * Send one HTML email. SendGrid first (verified sender), SMTP fallback.
 * @param string $from_name Optional display name override (e.g. the org).
 * @return array ['success'=>bool, 'error'=>string|null]
 */
function sendEmail($to_email, $to_name, $subject, $html_body, $text_body = '', $from_name = '') {
    if (!filter_var($to_email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'error' => 'Invalid recipient address'];
    }
    $from_name = $from_name !== '' ? $from_name : MAIL_FROM_NAME;

    if (SENDGRID_API_KEY !== '') {
        return sendViaSendGrid($to_email, $to_name, $subject, $html_body, $text_body, $from_name);
    }
    return sendViaSmtp($to_email, $to_name, $subject, $html_body, $text_body, $from_name);
}

/**
 * SendGrid v3 mail/send.
 */
function sendViaSendGrid($to_email, $to_name, $subject, $html_body, $text_body, $from_name) {
    $content = [];
    if ($text_body !== '') {
        $content[] = ['type' => 'text/plain', 'value' => $text_body];
    }
    $content[] = ['type' => 'text/html', 'value' => $html_body];

    $payload = json_encode([
        'personalizations' => [[
            'to' => [array_filter(['email' => $to_email, 'name' => $to_name ?: null])]
        ]],
        'from' => ['email' => MAIL_FROM_EMAIL, 'name' => $from_name],
        'reply_to' => ['email' => MAIL_FROM_EMAIL, 'name' => $from_name],
        'subject' => $subject,
        'content' => $content
    ]);

    $ch = curl_init('https://api.sendgrid.com/v3/mail/send');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . SENDGRID_API_KEY,
            'Content-Type: application/json'
        ]
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code >= 200 && $code < 300) {
        return ['success' => true, 'error' => null];
    }
    error_log("[MAIL] SendGrid send to {$to_email} failed ({$code}): " . substr((string)$body, 0, 300));
    return ['success' => false, 'error' => "SendGrid HTTP {$code}"];
}

/**
 * Authenticated SMTP over implicit SSL (fallback transport).
 */
function sendViaSmtp($to_email, $to_name, $subject, $html_body, $text_body, $from_name) {
    if (SMTP_HOST === '' || SMTP_USER === '' || SMTP_PASS === '') {
        return ['success' => false, 'error' => 'SMTP not configured'];
    }

    $from_email = SMTP_USER;
    if ($text_body === '') {
        $text_body = trim(preg_replace('/[ \t]+/', ' ', strip_tags(
            preg_replace('/<(br|\/p|\/div|\/h[1-6]|\/tr)>/i', "\n", $html_body)
        )));
    }

    $boundary = 'sbp-' . bin2hex(random_bytes(12));
    $headers = 'From: =?UTF-8?B?' . base64_encode($from_name) . "?= <{$from_email}>\r\n"
        . "To: =?UTF-8?B?" . base64_encode($to_name ?: $to_email) . "?= <{$to_email}>\r\n"
        . 'Subject: =?UTF-8?B?' . base64_encode($subject) . "?=\r\n"
        . 'Date: ' . date('r') . "\r\n"
        . 'Message-ID: <' . bin2hex(random_bytes(16)) . '@' . preg_replace('/^.*@/', '', $from_email) . ">\r\n"
        . "MIME-Version: 1.0\r\n"
        . "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";

    $body = "--{$boundary}\r\n"
        . "Content-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
        . chunk_split(base64_encode($text_body))
        . "--{$boundary}\r\n"
        . "Content-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
        . chunk_split(base64_encode($html_body))
        . "--{$boundary}--\r\n";

    $errno = 0; $errstr = '';
    $fp = @stream_socket_client('ssl://' . SMTP_HOST . ':' . SMTP_PORT, $errno, $errstr, 20);
    if (!$fp) {
        error_log("[MAIL] SMTP connect failed: {$errno} {$errstr}");
        return ['success' => false, 'error' => "connect: {$errstr}"];
    }
    stream_set_timeout($fp, 20);

    $read = function () use ($fp) {
        $data = '';
        while (($line = fgets($fp, 515)) !== false) {
            $data .= $line;
            if (strlen($line) < 4 || $line[3] !== '-') break; // last line of reply
        }
        return $data;
    };
    $cmd = function ($c, $expect) use ($fp, $read) {
        if ($c !== null) fwrite($fp, $c . "\r\n");
        $r = $read();
        if (strpos($r, (string)$expect) !== 0) {
            throw new Exception("SMTP unexpected reply to '" . substr((string)$c, 0, 12) . "…': " . trim($r));
        }
        return $r;
    };

    try {
        $cmd(null, 220);
        $cmd('EHLO silentbidpro.com', 250);
        $cmd('AUTH LOGIN', 334);
        $cmd(base64_encode(SMTP_USER), 334);
        $cmd(base64_encode(SMTP_PASS), 235);
        $cmd('MAIL FROM:<' . $from_email . '>', 250);
        $cmd('RCPT TO:<' . $to_email . '>', 250);
        $cmd('DATA', 354);
        fwrite($fp, $headers . "\r\n" . $body . ".\r\n");
        $cmd(null, 250);
        $cmd('QUIT', 221);
        fclose($fp);
        return ['success' => true, 'error' => null];
    } catch (Exception $e) {
        @fclose($fp);
        error_log('[MAIL] send to ' . $to_email . ' failed: ' . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Branded HTML for a purchase receipt / thank-you. Fully generic:
 * organization, event, colors, and pickup text all come from the event.
 *
 * @param string $recipient_name
 * @param array  $items  [['title'=>..., 'amount'=>float], ...]
 * @param array  $ctx    ['organization_name','event_name','brand_primary','brand_accent','pickup_text','my_bids_url','intro_html' (optional override)]
 * @return array ['subject'=>..., 'html'=>...]
 */
function buildPurchaseReceiptEmail($recipient_name, $items, $ctx) {
    $org = trim($ctx['organization_name'] ?? '') ?: 'Silent Bid Pro';
    $event = trim($ctx['event_name'] ?? '');
    $primary = $ctx['brand_primary'] ?? '#315fcb';
    $accent = $ctx['brand_accent'] ?? '#d99a2b';
    $pickup = $ctx['pickup_text'] ?? '';
    $my_bids = $ctx['my_bids_url'] ?? (rtrim(APP_DOMAIN, '/') . '/my-bids.php');
    $first = trim(explode(' ', trim($recipient_name))[0] ?: 'Friend');
    $first = htmlspecialchars(ucfirst(strtolower($first)) === $first || strtoupper($first) === $first ? ucwords(strtolower($first)) : $first);

    $total = 0.0;
    $rows_html = '';
    foreach ($items as $it) {
        $total += (float)$it['amount'];
        $rows_html .= '<tr>'
            . '<td style="padding:10px 0;border-bottom:1px solid #eee;font-size:15px;color:#333;">' . htmlspecialchars($it['title']) . '</td>'
            . '<td style="padding:10px 0;border-bottom:1px solid #eee;font-size:15px;color:#333;text-align:right;white-space:nowrap;">$' . number_format((float)$it['amount'], 2) . '</td>'
            . '</tr>';
    }
    $n = count($items);

    $default_intro = '<p style="margin:0 0 16px;font-size:16px;line-height:1.65;color:#333;">'
        . 'Wonderful news — your payment has been received, and your winning '
        . ($n === 1 ? 'bid is' : 'bids are') . ' confirmed. Thank you for your generosity: every dollar of your '
        . ($n === 1 ? 'purchase' : 'purchases') . ' goes to work for ' . htmlspecialchars($org) . '\'s mission.</p>';
    $intro = $ctx['intro_html'] ?? $default_intro;

    $pickup_html = $pickup !== ''
        ? '<div style="margin:24px 0 0;padding:18px 20px;background:#f6f9f7;border-radius:10px;">'
            . '<p style="margin:0 0 8px;font-weight:bold;font-size:15px;color:#2a2a2a;">📦 Getting your item' . ($n === 1 ? '' : 's') . '</p>'
            . '<p style="margin:0;font-size:14px;line-height:1.7;color:#555;">' . nl2br(htmlspecialchars($pickup)) . '</p>'
          . '</div>'
        : '';

    $subject = ($event !== '' ? $event : $org) . ' — your ' . ($n === 1 ? 'item is' : $n . ' items are') . ' confirmed. Thank you! 🎉';

    $html = '<div style="margin:0;padding:0;background:#f4f4f2;">'
        . '<div style="max-width:600px;margin:0 auto;padding:32px 16px;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif;">'
        .   '<div style="background:linear-gradient(135deg,' . htmlspecialchars($primary) . ',' . htmlspecialchars($accent) . ');border-radius:14px 14px 0 0;padding:32px 32px 26px;">'
        .     '<p style="margin:0;font-size:13px;letter-spacing:2px;text-transform:uppercase;color:rgba(255,255,255,0.85);">' . htmlspecialchars($org) . '</p>'
        .     '<h1 style="margin:8px 0 0;font-size:26px;line-height:1.25;color:#ffffff;">Thank you, ' . $first . ' — you made a difference today. 💛</h1>'
        .   '</div>'
        .   '<div style="background:#ffffff;border-radius:0 0 14px 14px;padding:32px;">'
        .     $intro
        .     '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:20px 0 0;border-collapse:collapse;">'
        .       '<tr><th align="left" style="padding:0 0 8px;font-size:12px;letter-spacing:1px;text-transform:uppercase;color:#888;">Your item' . ($n === 1 ? '' : 's') . '</th>'
        .       '<th align="right" style="padding:0 0 8px;font-size:12px;letter-spacing:1px;text-transform:uppercase;color:#888;">Paid</th></tr>'
        .       $rows_html
        .       '<tr><td style="padding:14px 0 0;font-size:16px;font-weight:bold;color:#111;">Total</td>'
        .       '<td style="padding:14px 0 0;font-size:16px;font-weight:bold;color:#111;text-align:right;">$' . number_format($total, 2) . '</td></tr>'
        .     '</table>'
        .     $pickup_html
        .     '<div style="margin:28px 0 0;text-align:center;">'
        .       '<a href="' . htmlspecialchars($my_bids) . '" style="display:inline-block;background:' . htmlspecialchars($primary) . ';color:#ffffff;text-decoration:none;font-size:15px;font-weight:bold;padding:13px 28px;border-radius:10px;">View your receipt &amp; items</a>'
        .     '</div>'
        .     '<p style="margin:28px 0 0;font-size:14px;line-height:1.7;color:#666;">With heartfelt appreciation,<br/>'
        .     '<strong style="color:#333;">The ' . htmlspecialchars($org) . ' team</strong>'
        .     ($event !== '' ? '<br/><span style="color:#999;">' . htmlspecialchars($event) . '</span>' : '') . '</p>'
        .   '</div>'
        .   '<p style="margin:18px 0 0;text-align:center;font-size:12px;color:#9a9a9a;">Powered by Silent Bid Pro — silent auctions made warm, simple, and professional.<br/>'
        .   '<a href="' . htmlspecialchars(rtrim(APP_DOMAIN, '/')) . '" style="color:#9a9a9a;">silentbidpro.com</a></p>'
        . '</div></div>';

    return ['subject' => $subject, 'html' => $html];
}

/**
 * Send the purchase receipt/thank-you for a set of PAID transactions.
 * Idempotent per transaction (audit_log RECEIPT_EMAIL_SENT). Silent no-op
 * when the user has no email or SMTP is unconfigured.
 *
 * @param int   $user_id
 * @param int[] $tx_ids  Paid transaction ids to cover
 * @return bool true when an email was sent
 */
function sendPurchaseReceiptEmail($user_id, array $tx_ids) {
    if (!mailerConfigured() || !$tx_ids) {
        return false;
    }

    $user = dbGetRow("SELECT id, full_name, email FROM users WHERE id = ?", [(int)$user_id]);
    if (!$user || empty($user['email'])) {
        return false;
    }

    $placeholders = implode(',', array_fill(0, count($tx_ids), '?'));
    $params = array_map('intval', $tx_ids);

    // Skip anything already covered by a receipt email.
    $already = dbGetValue(
        "SELECT COUNT(*) FROM audit_log
         WHERE event_type = 'RECEIPT_EMAIL_SENT' AND user_id = ?
           AND item_id IN (SELECT item_id FROM transactions WHERE id IN ({$placeholders}))",
        array_merge([(int)$user_id], $params)
    );

    $rows = dbGetAll(
        "SELECT t.id AS tx_id, t.amount, i.id AS item_id, i.title, i.event_id
         FROM transactions t JOIN items i ON i.id = t.item_id
         WHERE t.id IN ({$placeholders}) AND t.user_id = ? AND t.status = 'paid'",
        array_merge($params, [(int)$user_id])
    );
    if (!$rows || (int)$already >= count($rows)) {
        return false;
    }

    $event_id = (int)$rows[0]['event_id'];
    $ctx_row = dbGetRow(
        "SELECT e.name AS event_name, o.name AS organization_name,
                COALESCE(e.brand_primary, o.brand_primary) AS brand_primary,
                COALESCE(e.brand_accent, o.brand_accent) AS brand_accent
         FROM events e JOIN organizations o ON o.id = e.organization_id
         WHERE e.id = ?",
        [$event_id]
    ) ?: [];

    $email = buildPurchaseReceiptEmail($user['full_name'] ?: 'Friend', array_map(function ($r) {
        return ['title' => $r['title'], 'amount' => (float)$r['amount']];
    }, $rows), [
        'organization_name' => $ctx_row['organization_name'] ?? '',
        'event_name' => $ctx_row['event_name'] ?? '',
        'brand_primary' => $ctx_row['brand_primary'] ?? '#315fcb',
        'brand_accent' => $ctx_row['brand_accent'] ?? '#d99a2b',
        'pickup_text' => getPickupInstructions($event_id)
    ]);

    $org_from = trim($ctx_row['organization_name'] ?? '');
    $result = sendEmail(
        $user['email'],
        $user['full_name'] ?: '',
        $email['subject'],
        $email['html'],
        '',
        $org_from !== '' ? $org_from . ' via Silent Bid Pro' : ''
    );

    if ($result['success']) {
        foreach ($rows as $r) {
            dbInsert(
                "INSERT INTO audit_log (event_type, user_id, item_id, description, created_at)
                 VALUES (?, ?, ?, ?, NOW())",
                ['RECEIPT_EMAIL_SENT', (int)$user_id, (int)$r['item_id'],
                 'Receipt email sent to ' . $user['email'] . ' (tx ' . (int)$r['tx_id'] . ')']
            );
        }
    }

    return $result['success'];
}

?>
