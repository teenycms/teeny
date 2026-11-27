<?php
/**
 * Builds the downloadable extras (not part of the Teeny download).
 *
 *   php tools/build-extras.php <website-extras-folder> <release-folder>
 *
 * - one zip per theme (themes-extra/<id>) and plugin (plugins-extra/<id>), into the website's extras/ folder
 * - catalog.json for "Get more themes / plugins" in the Studio, with a SHA-256 checksum for every zip
 * - teeny-themes-<version>.zip and teeny-plugins-<version>.zip packs for the GitHub release
 * Needs the PHP zip extension.
 */
declare(strict_types=1);

[$self, $siteDir, $releaseDir] = $argv + [null, null, null];
if (!$siteDir || !$releaseDir) { fwrite(STDERR, "Usage: php tools/build-extras.php <website-extras-folder> <release-folder>\n"); exit(1); }
if (!class_exists('ZipArchive')) { fwrite(STDERR, "The PHP zip extension is needed.\n"); exit(1); }
$root = dirname(__DIR__);
preg_match("/define\('TEENY_VERSION', '([^']+)'\)/", (string)file_get_contents("$root/core/bootstrap.php"), $m);
$version = $m[1] ?? '0.0.0';
@mkdir($siteDir, 0777, true);
@mkdir($releaseDir, 0777, true);
$base = 'https://teenycms.github.io/extras/';

function zipFolder(string $dir, string $top, string $dest): void
{
    @unlink($dest);
    $z = new ZipArchive();
    $z->open($dest, ZipArchive::CREATE);
    $files = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)) as $f) $files[] = $f->getPathname();
    sort($files);
    foreach ($files as $f) {
        $rel = str_replace('\\', '/', substr($f, strlen($dir) + 1));
        $z->addFile($f, $top . '/' . $rel);
        $z->setMtimeName($top . '/' . $rel, 1735689600); // fixed time, so an unchanged extra keeps the same checksum
    }
    $z->close();
}

$catalog = ['latest' => ['version' => $version], 'themes' => [], 'plugins' => []];
$packs = ['themes' => [], 'plugins' => []];
foreach (['themes' => ['themes-extra', 'theme', 'theme.json'], 'plugins' => ['plugins-extra', 'plugin', 'plugin.json']] as $list => [$folder, $kind, $manifestName]) {
    foreach (glob("$root/$folder/*", GLOB_ONLYDIR) ?: [] as $dir) {
        $id = basename($dir);
        $man = json_decode((string)file_get_contents("$dir/$manifestName"), true);
        $file = "$kind-$id.zip";
        zipFolder($dir, $id, "$siteDir/$file");
        $packs[$list][] = "$siteDir/$file";
        $entry = ['id' => $id, 'name' => $man['name'] ?? $id, 'description' => $man['description'] ?? '', 'version' => $man['version'] ?? '1.0',
            'minTeeny' => '1.6.0', 'zip' => $base . $file, 'sha256' => hash_file('sha256', "$siteDir/$file")];
        if ($kind === 'theme') {
            $d = $man['defaults'] ?? [];
            $entry['colors'] = ['bg' => $d['bg'] ?? '', 'text' => $d['text'] ?? '', 'accent' => $d['accent'] ?? '', 'surface' => $d['surface'] ?? ''];
        } else {
            $entry['tag'] = isset($man['widget']) ? 'interactive' : '';
        }
        $catalog[$list][] = $entry;
        echo "$file  " . filesize("$siteDir/$file") . " bytes\n";
    }
}
file_put_contents("$siteDir/catalog.json", json_encode($catalog, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");

$readme = [
    'themes' => "Teeny CMS themes\n\nEach .zip here is one theme. In Teeny Studio, open Style, scroll to Your themes, and upload the .zip under \"Add a theme\".\nOr unzip it and copy the folder into themes/ by FTP.\n",
    'plugins' => "Teeny CMS plugins\n\nEach .zip here is one plugin. In Teeny Studio, open Plugins and upload the .zip under \"Add a plugin\". New plugins start switched off.\nOr unzip it and copy the folder into plugins/ by FTP.\n",
];
foreach ($packs as $list => $zips) {
    $dest = "$releaseDir/teeny-$list-$version.zip";
    @unlink($dest);
    $z = new ZipArchive();
    $z->open($dest, ZipArchive::CREATE);
    foreach ($zips as $f) $z->addFile($f, "teeny-$list/" . basename($f));
    $z->addFromString("teeny-$list/README.txt", $readme[$list]);
    $z->close();
    echo basename($dest) . "  " . filesize($dest) . " bytes\n";
}
echo "catalog.json written (Teeny $version)\n";
