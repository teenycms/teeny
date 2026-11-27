<?php
declare(strict_types=1);

/**
 * Official extras and the "update available" notice.
 *
 * One small catalog file on the Teeny website lists the official themes and plugins (with a
 * SHA-256 checksum for each download) and the latest Teeny version. It's fetched at most once a
 * day, only from teenycms.github.io, and only when an owner uses the Studio. Nothing about the
 * site is sent. Installs go through the same file-by-file checks as manual uploads.
 */
final class Extras
{
    public const CATALOG_URL = 'https://teenycms.github.io/extras/catalog.json';
    public const BASE = 'https://teenycms.github.io/extras/';
    private const HOSTS = ['teenycms.github.io'];
    private const MAX_CATALOG = 200 * 1024;
    private const MAX_ZIP = 8 * 1048576;
    private const MAX_AGE = 86400;

    /** Owners can turn the daily check off in Preferences → Advanced. */
    public static function checksEnabled(?array $site = null): bool
    {
        $site ??= site();
        return ($site['updateCheck'] ?? true) !== false;
    }

    /**
     * The catalog: ['latest' => ['version', 'url'], 'themes' => [...], 'plugins' => [...]], or null if never fetched.
     * @param bool $refresh fetch now if the saved copy is older than a day (or $force)
     */
    public static function catalog(bool $refresh = false, bool $force = false): ?array
    {
        $saved = Store::read('extras', []);
        $fresh = (int)($saved['time'] ?? 0) > time() - self::MAX_AGE;
        if (($refresh && !$fresh) || $force) {
            Store::write('extras', ['time' => time(), 'data' => $saved['data'] ?? null]); // one try per day, even if it fails
            if (Fonts::canDownload()) {
                [$status, $body] = http_get(self::CATALOG_URL, self::HOSTS, self::MAX_CATALOG, 5);
                $data = $status === 200 ? json_decode($body, true) : null;
                if (is_array($data)) {
                    $clean = self::clean($data);
                    Store::write('extras', ['time' => time(), 'data' => $clean]);
                    return $clean;
                }
            }
        }
        return is_array($saved['data'] ?? null) ? $saved['data'] : null;
    }

    /** Keep only well-formed entries from the catalog. */
    private static function clean(array $d): array
    {
        $ver = (string)($d['latest']['version'] ?? '');
        $out = ['latest' => preg_match('/^\d+\.\d+\.\d+$/', $ver) ? ['version' => $ver, 'url' => TEENY_REPO . '/releases/tag/v' . $ver] : null, 'themes' => [], 'plugins' => []];
        foreach (['themes' => 'theme', 'plugins' => 'plugin'] as $list => $kind) {
            foreach (array_slice(is_array($d[$list] ?? null) ? $d[$list] : [], 0, 60) as $it) {
                if (!is_array($it)) continue;
                $id = (string)($it['id'] ?? '');
                $zip = (string)($it['zip'] ?? '');
                $sha = strtolower((string)($it['sha256'] ?? ''));
                if (!preg_match('/^[a-z0-9-]{1,40}$/', $id) || !str_starts_with($zip, self::BASE) || !preg_match('~^[a-z0-9./-]+\.zip$~', substr($zip, strlen(self::BASE))) || !preg_match('/^[a-f0-9]{64}$/', $sha)) continue;
                $colors = [];
                foreach (['bg', 'text', 'accent', 'surface'] as $c) $colors[$c] = Sanitizer::color((string)($it['colors'][$c] ?? ''), '#808080');
                $out[$list][$id] = [
                    'id' => $id, 'kind' => $kind, 'zip' => $zip, 'sha256' => $sha,
                    'name' => Sanitizer::text($it['name'] ?? $id, 40), 'description' => Sanitizer::text($it['description'] ?? '', 200),
                    'version' => Sanitizer::text($it['version'] ?? '', 12), 'minTeeny' => preg_match('/^\d+\.\d+\.\d+$/', (string)($it['minTeeny'] ?? '')) ? $it['minTeeny'] : '',
                    'colors' => $colors, 'tag' => Sanitizer::text($it['tag'] ?? '', 30),
                ];
            }
        }
        return $out;
    }

    /** A newer Teeny version, or null. */
    public static function update(): ?array
    {
        if (!self::checksEnabled()) return null;
        $latest = self::catalog()['latest'] ?? null;
        return is_array($latest) && version_compare($latest['version'], TEENY_VERSION, '>') ? $latest : null;
    }

    public static function installed(array $item): bool
    {
        return $item['kind'] === 'theme' ? isset(Theme::all()[$item['id']]) : isset(Plugins::all()[$item['id']]);
    }

    /**
     * Download and install one official theme or plugin. Returns its name.
     * @throws RuntimeException with a message for the owner
     */
    public static function install(string $kind, string $id): string
    {
        $cat = self::catalog(true);
        $item = $cat[$kind === 'theme' ? 'themes' : 'plugins'][$id] ?? null;
        if (!$item) throw new RuntimeException('That item is not in the official list. Please refresh the page and try again.');
        if ($item['minTeeny'] !== '' && version_compare(TEENY_VERSION, $item['minTeeny'], '<')) throw new RuntimeException('“' . $item['name'] . '” needs Teeny ' . $item['minTeeny'] . ' or newer. Please update Teeny first.');
        if (!Packages::zipAvailable()) throw new RuntimeException('Your server can’t open zip files. Download “' . $item['name'] . '” and copy its folder by FTP instead.');
        if (!Fonts::canDownload()) throw new RuntimeException('Your web host doesn’t let Teeny download files. Download the .zip and upload it here instead.');
        [$status, $data] = http_get($item['zip'], self::HOSTS, self::MAX_ZIP, 30);
        if ($status !== 200 || $data === '') throw new RuntimeException('Downloading “' . $item['name'] . '” failed. Please try again in a minute.');
        if (!hash_equals($item['sha256'], hash('sha256', $data))) throw new RuntimeException('The download of “' . $item['name'] . '” didn’t match its checksum, so it was not installed.');
        $tmp = tempnam(sys_get_temp_dir(), 'teeny');
        if ($tmp === false || file_put_contents($tmp, $data) === false) throw new RuntimeException('Could not save the download. Please try again.');
        try {
            $files = Packages::readZip($tmp, $kind);
            $installedId = $kind === 'theme' ? Theme::installFiles($files) : Plugins::install($files);
        } finally {
            @unlink($tmp);
        }
        Activity::log($kind . '.install', $item['name'] . ' (official)');
        return $kind === 'theme' ? (Theme::all()[$installedId]['name'] ?? $item['name']) : (Plugins::all()[$installedId]['manifest']['name'] ?? $item['name']);
    }
}
