<?php
declare(strict_types=1);

/**
 * Sends Teeny's emails (password resets, contact form alerts).
 *
 * Two ways to send, chosen in Preferences → Email:
 *  - "php":  the host's built-in PHP mail(). Simple, but some hosts block it or it lands in spam.
 *  - "smtp": a real mailbox (your host's email account, Gmail, Zoho, Brevo…). Much more reliable.
 *
 * All header values are stripped of line breaks (no header injection), the body is base64-encoded,
 * and the SMTP password is never shown back, logged or included in backups.
 */
final class Mailer
{
    /** Why the last send failed, in plain words ('' after success). */
    public static string $lastError = '';

    public static function settings(): array
    {
        $m = site()['mail'] ?? [];
        return [
            'method'     => ($m['method'] ?? 'php') === 'smtp' ? 'smtp' : 'php',
            'fromEmail'  => (string)($m['fromEmail'] ?? ''),
            'fromName'   => (string)($m['fromName'] ?? ''),
            'smtpHost'   => (string)($m['smtpHost'] ?? ''),
            'smtpPort'   => (int)($m['smtpPort'] ?? 587),
            'smtpSecure' => in_array($m['smtpSecure'] ?? 'tls', ['tls', 'ssl', 'none'], true) ? (string)($m['smtpSecure'] ?? 'tls') : 'tls',
            'smtpUser'   => (string)($m['smtpUser'] ?? ''),
            'smtpPass'   => (string)($m['smtpPass'] ?? ''),
            'smtpNoVerify' => !empty($m['smtpNoVerify']),
        ];
    }

    /** Domain used for the default sender address and the SMTP greeting. */
    public static function domain(): string
    {
        $host = (string)parse_url((string)(site()['siteUrl'] ?? ''), PHP_URL_HOST);
        if ($host === '') $host = (string)($_SERVER['SERVER_NAME'] ?? 'localhost');
        return preg_match('/^[a-z0-9.-]{1,253}$/i', $host) ? strtolower($host) : 'localhost';
    }

    public static function fromAddress(): string
    {
        $s = self::settings();
        if (filter_var($s['fromEmail'], FILTER_VALIDATE_EMAIL)) return $s['fromEmail'];
        if ($s['method'] === 'smtp' && filter_var($s['smtpUser'], FILTER_VALIDATE_EMAIL)) return $s['smtpUser'];
        return 'no-reply@' . preg_replace('/^www\./', '', self::domain());
    }

    /** Is sending set up well enough to try? */
    public static function configured(): bool
    {
        $s = self::settings();
        return $s['method'] === 'smtp' ? $s['smtpHost'] !== '' : function_exists('mail');
    }

    public static function send(string $to, string $subject, string $body, string $replyTo = ''): bool
    {
        self::$lastError = '';
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            self::$lastError = 'The recipient email address is not valid.';
            return false;
        }
        $line = static fn(string $v): string => trim((string)preg_replace('/[\r\n\t]+/', ' ', $v));
        $s = self::settings();
        $from = self::fromAddress();
        $name = $line($s['fromName'] !== '' ? $s['fromName'] : (string)(site()['title'] ?? 'Teeny CMS'));
        $headers = [
            'Date: ' . date('r'),
            'From: =?UTF-8?B?' . base64_encode($name) . '?= <' . $from . '>',
            'Message-ID: <' . random_id(12) . '@' . self::domain() . '>',
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
            'X-Mailer: Teeny CMS',
        ];
        if ($replyTo !== '' && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) $headers[] = 'Reply-To: ' . $replyTo;
        $encSubject = '=?UTF-8?B?' . base64_encode($line($subject)) . '?=';
        $encBody = chunk_split(base64_encode(str_replace(["\r\n", "\n"], ["\n", "\r\n"], $body)), 76, "\r\n");

        return $s['method'] === 'smtp'
            ? self::viaSmtp($s, $from, $to, $encSubject, $encBody, $headers)
            : self::viaPhp($from, $to, $encSubject, $encBody, $headers);
    }

    private static function viaPhp(string $from, string $to, string $subject, string $body, array $headers): bool
    {
        if (!function_exists('mail')) {
            self::$lastError = 'PHP mail() is switched off on this server. Use SMTP instead.';
            return false;
        }
        $h = implode("\r\n", $headers);
        // "-f" sets the return address, which many hosts need for delivery; some don't allow it, so try without too.
        $ok = @mail($to, $subject, $body, $h, '-f' . $from);
        if (!$ok) $ok = @mail($to, $subject, $body, $h);
        if (!$ok) {
            self::$lastError = 'Your server refused to send the email (PHP mail() failed). Ask your host to enable email, or switch to SMTP.';
        }
        return $ok;
    }

    private static function viaSmtp(array $s, string $from, string $to, string $subject, string $body, array $headers): bool
    {
        if ($s['smtpHost'] === '') {
            self::$lastError = 'SMTP is selected but no SMTP server is filled in.';
            return false;
        }
        $verify = !$s['smtpNoVerify'];
        $ctx = stream_context_create(['ssl' => [
            'verify_peer' => $verify, 'verify_peer_name' => $verify, 'allow_self_signed' => !$verify,
            'peer_name' => $s['smtpHost'], 'SNI_enabled' => true,
        ]]);
        $remote = ($s['smtpSecure'] === 'ssl' ? 'ssl://' : 'tcp://') . $s['smtpHost'] . ':' . $s['smtpPort'];
        $fp = @stream_socket_client($remote, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx);
        if (!$fp) {
            self::$lastError = 'Could not connect to ' . $s['smtpHost'] . ':' . $s['smtpPort'] . ' (' . ($errstr ?: 'connection failed') . '). Check the server name, port and security setting.';
            return false;
        }
        stream_set_timeout($fp, 20);
        $read = static function () use ($fp): string {
            $data = '';
            while (($l = fgets($fp, 1024)) !== false) {
                $data .= $l;
                if (strlen($l) < 4 || $l[3] === ' ') break;
            }
            return $data;
        };
        $cmd = static function (?string $c, array $expect) use ($fp, $read): string {
            if ($c !== null) fwrite($fp, $c . "\r\n");
            $r = $read();
            if (!in_array((int)substr($r, 0, 3), $expect, true)) {
                throw new RuntimeException(trim($r) !== '' ? trim($r) : 'The server stopped answering.');
            }
            return $r;
        };
        try {
            $cmd(null, [220]);
            $ehlo = 'EHLO ' . self::domain();
            $caps = $cmd($ehlo, [250]);
            if ($s['smtpSecure'] === 'tls') {
                $cmd('STARTTLS', [220]);
                $method = STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT') ? STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT : 0);
                if (!@stream_socket_enable_crypto($fp, true, $method)) {
                    throw new RuntimeException('The secure (TLS) connection could not be started. Try port 465 with SSL, or check the certificate setting.');
                }
                $caps = $cmd($ehlo, [250]);
            }
            if ($s['smtpUser'] !== '') {
                if (preg_match('/AUTH[ =][^\r\n]*\bLOGIN\b/i', $caps)) {
                    $cmd('AUTH LOGIN', [334]);
                    $cmd(base64_encode($s['smtpUser']), [334]);
                    $cmd(base64_encode($s['smtpPass']), [235]);
                } else {
                    $cmd('AUTH PLAIN ' . base64_encode("\0" . $s['smtpUser'] . "\0" . $s['smtpPass']), [235]);
                }
            }
            $cmd('MAIL FROM:<' . $from . '>', [250]);
            $cmd('RCPT TO:<' . $to . '>', [250, 251]);
            $cmd('DATA', [354]);
            $msg = implode("\r\n", array_merge($headers, ['To: <' . $to . '>', 'Subject: ' . $subject])) . "\r\n\r\n" . $body;
            $msg = (string)preg_replace('/^\./m', '..', $msg); // SMTP "dot-stuffing"
            fwrite($fp, $msg . "\r\n.\r\n");
            $cmd(null, [250]);
            try { $cmd('QUIT', [221, 250]); } catch (RuntimeException) { /* already sent */ }
            fclose($fp);
            return true;
        } catch (RuntimeException $ex) {
            @fclose($fp);
            $reply = substr($ex->getMessage(), 0, 220);
            $hint = '';
            if (preg_match('/^(535|534|530)/', $reply)) $hint = ' Check the SMTP username and password (Gmail needs an "app password").';
            elseif (preg_match('/^(550|553|554)/', $reply)) $hint = ' The server may only allow sending from your own mailbox address. Set "From address" to it.';
            self::$lastError = 'The mail server said: ' . $reply . $hint;
            return false;
        }
    }
}
