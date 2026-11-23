<?php
declare(strict_types=1);

/**
 * Contact form messages. Spam protection without CAPTCHAs or external services:
 * a hidden honeypot field, a JavaScript timing check, per-visitor rate limits and a daily cap.
 */
final class Inbox
{
    private const MAX_MESSAGES = 1000;
    private const PER_IP_HOUR = 5;
    private const PER_DAY = 200;

    public static function all(): array
    {
        $d = Store::read('inbox', ['messages' => []]);
        return is_array($d['messages'] ?? null) ? $d['messages'] : [];
    }

    public static function unread(): int
    {
        return count(array_filter(self::all(), static fn($m) => empty($m['read'])));
    }

    /** Handle a submission. Returns the form state for Renderer::$form. */
    public static function submit(array $page, array $post): array
    {
        $blockId = (string)($post['_block'] ?? '');
        $block = null;
        foreach ($page['blocks'] ?? [] as $b) {
            if (($b['type'] ?? '') === 'contact' && $b['id'] === $blockId) $block = $b;
        }
        if (!$block) return [];

        $old = [
            'name'    => Sanitizer::text($post['name'] ?? '', 100),
            'email'   => Sanitizer::text($post['email'] ?? '', 200),
            'phone'   => Sanitizer::text($post['phone'] ?? '', 40),
            'message' => Sanitizer::text($post['message'] ?? '', 5000, true),
        ];
        $fail = static fn(string $msg) => ['block' => $blockId, 'error' => $msg, 'old' => $old];

        // Honeypot filled in → a bot. Pretend everything went fine.
        if (trim((string)($post['website'] ?? '')) !== '') {
            return ['block' => $blockId, 'sent' => true];
        }
        $elapsed = (int)($post['_t'] ?? 0);
        if ($elapsed < 3) {
            return $fail('Please take a moment to fill in the form (JavaScript must be enabled), then try again.');
        }
        if ($old['name'] === '') return $fail('Please enter your name.');
        if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) return $fail('Please enter a valid email address.');
        if (mb_strlen_safe($old['message']) < 2) return $fail('Please write a message.');
        if (preg_match_all('~https?://~i', $old['message']) > 3) return $fail('Your message contains too many links.');

        // Rate limits
        $ipKey = substr(hash('sha256', 'form|' . client_ip()), 0, 24);
        $allowed = true;
        Store::update('formlimit', static function (array $d) use ($ipKey, &$allowed): array {
            $now = time();
            $today = date('Y-m-d');
            if (($d['day'] ?? '') !== $today) $d = ['day' => $today, 'count' => 0, 'ips' => []];
            $hits = array_values(array_filter($d['ips'][$ipKey] ?? [], static fn($t) => $t > $now - 3600));
            if (count($hits) >= self::PER_IP_HOUR || (int)$d['count'] >= self::PER_DAY) {
                $allowed = false;
                return $d;
            }
            $hits[] = $now;
            $d['ips'][$ipKey] = $hits;
            $d['count'] = (int)$d['count'] + 1;
            return $d;
        });
        if (!$allowed) return $fail('Too many messages were sent. Please try again later.');

        $message = [
            'id'      => random_id(6),
            'time'    => time(),
            'page'    => $page['title'] ?? '',
            'pageId'  => $page['id'] ?? '',
            'name'    => $old['name'],
            'email'   => $old['email'],
            'phone'   => $old['phone'],
            'message' => $old['message'],
            'read'    => false,
        ];
        Store::update('inbox', static function (array $d) use ($message): array {
            $list = is_array($d['messages'] ?? null) ? $d['messages'] : [];
            array_unshift($list, $message);
            $d['messages'] = array_slice($list, 0, self::MAX_MESSAGES);
            return $d;
        }, ['messages' => []]);
        self::notify($message);
        return ['block' => $blockId, 'sent' => true];
    }

    /* ---------- newsletter sign-ups ---------- */

    private const MAX_SUBSCRIBERS = 20000;

    public static function subscribers(): array
    {
        $d = Store::read('subscribers', ['items' => []]);
        return is_array($d['items'] ?? null) ? $d['items'] : [];
    }

    /** Handle a newsletter form. Same spam protection as the contact form. */
    public static function subscribe(array $page, array $post): array
    {
        $blockId = (string)($post['_block'] ?? '');
        $found = false;
        foreach ($page['blocks'] ?? [] as $b) {
            if (($b['type'] ?? '') === 'newsletter' && $b['id'] === $blockId) $found = true;
        }
        if (!$found) return [];
        $email = strtolower(Sanitizer::text($post['email'] ?? '', 200));
        $fail = static fn(string $msg) => ['block' => $blockId, 'error' => $msg, 'old' => ['email' => $email]];
        if (trim((string)($post['website'] ?? '')) !== '') return ['block' => $blockId, 'sent' => true];
        if ((int)($post['_t'] ?? 0) < 2) return $fail('Please wait a moment and try again (JavaScript must be enabled).');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return $fail('Please enter a valid email address.');
        if (!self::allowSubmission()) return $fail('Too many sign-ups from your connection. Please try again later.');
        Store::update('subscribers', static function (array $d) use ($email, $page): array {
            $items = is_array($d['items'] ?? null) ? $d['items'] : [];
            foreach ($items as $it) {
                if (($it['email'] ?? '') === $email) return $d; // already subscribed: say thanks, store nothing new
            }
            array_unshift($items, ['email' => $email, 'time' => time(), 'page' => $page['title'] ?? '']);
            $d['items'] = array_slice($items, 0, self::MAX_SUBSCRIBERS);
            return $d;
        }, ['items' => []]);
        return ['block' => $blockId, 'sent' => true];
    }

    public static function removeSubscriber(string $email): void
    {
        $email = strtolower(trim($email));
        Store::update('subscribers', static function (array $d) use ($email): array {
            $d['items'] = array_values(array_filter($d['items'] ?? [], static fn($it) => ($it['email'] ?? '') !== $email));
            return $d;
        }, ['items' => []]);
    }

    /** CSV for spreadsheets. Cells that could be read as formulas are neutralised. */
    public static function subscribersCsv(): string
    {
        $safe = static function (string $v): string {
            if ($v !== '' && strpbrk($v[0], "=+-@\t\r") !== false) $v = "'" . $v;
            return '"' . str_replace('"', '""', $v) . '"';
        };
        $out = "\xEF\xBB\xBF" . "email,signed_up,page\r\n";
        foreach (self::subscribers() as $it) {
            $out .= $safe((string)$it['email']) . ',' . $safe(date('Y-m-d H:i', (int)$it['time'])) . ',' . $safe((string)($it['page'] ?? '')) . "\r\n";
        }
        return $out;
    }

    /** Shared per-visitor and per-day limits for public forms. */
    private static function allowSubmission(): bool
    {
        $ipKey = substr(hash('sha256', 'form|' . client_ip()), 0, 24);
        $allowed = true;
        Store::update('formlimit', static function (array $d) use ($ipKey, &$allowed): array {
            $now = time();
            $today = date('Y-m-d');
            if (($d['day'] ?? '') !== $today) $d = ['day' => $today, 'count' => 0, 'ips' => []];
            $hits = array_values(array_filter($d['ips'][$ipKey] ?? [], static fn($t) => $t > $now - 3600));
            if (count($hits) >= self::PER_IP_HOUR || (int)$d['count'] >= self::PER_DAY) {
                $allowed = false;
                return $d;
            }
            $hits[] = $now;
            $d['ips'][$ipKey] = $hits;
            $d['count'] = (int)$d['count'] + 1;
            return $d;
        });
        return $allowed;
    }

    public static function update(callable $fn): void
    {
        Store::update('inbox', static function (array $d) use ($fn): array {
            $d['messages'] = array_values($fn(is_array($d['messages'] ?? null) ? $d['messages'] : []));
            return $d;
        }, ['messages' => []]);
    }

    private static function notify(array $m): void
    {
        $site = site();
        $to = (string)($site['notifyEmail'] ?? '');
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) return;
        $noBreaks = static fn(string $s) => trim((string)preg_replace('/[\r\n]+/', ' ', $s));
        $subject = 'New message from ' . $noBreaks($m['name']) . ' on ' . $noBreaks((string)($site['title'] ?? 'your site'));
        $body = "You have a new message from your website.\n\n"
            . "Name:    {$m['name']}\nEmail:   {$m['email']}\n" . ($m['phone'] !== '' ? "Phone:   {$m['phone']}\n" : '')
            . "Page:    {$m['page']}\n\n{$m['message']}\n\nTeeny CMS (read all your messages in the Studio, under Messages)\n";
        if (!Mailer::send($to, $subject, $body, (string)$m['email'])) {
            Activity::log('email.failed', 'New message alert: ' . Mailer::$lastError);
        }
    }
}

function mb_strlen_safe(string $s): int
{
    return function_exists('mb_strlen') ? mb_strlen($s, 'UTF-8') : (int)preg_match_all('/./us', $s);
}
