<?php
declare(strict_types=1);

/**
 * Safe reading of uploaded theme/plugin .zip files.
 *
 * Nothing from an archive touches the disk until every entry has passed these checks:
 *  - only an allow-list of file names/types per package kind (no PHP, HTML pages, SVG, executables…)
 *  - no absolute paths, "..", backslashes, drive letters or NUL bytes (zip-slip)
 *  - no symbolic links
 *  - limits on file count, per-file size, total size and compression ratio (zip bombs)
 *  - content checks: real images, real fonts, valid JSON
 * Extraction then happens into a temporary folder that is swapped in atomically.
 */
final class Packages
{
    private const MAX_UPLOAD = 8 * 1048576;
    private const MAX_FILES = 120;
    private const MAX_FILE = 3 * 1048576;
    private const MAX_TOTAL = 15 * 1048576;
    private const MAX_RATIO = 150;
    private const JUNK = '~(^|/)(__MACOSX/|\.DS_Store$|Thumbs\.db$|desktop\.ini$)~i';

    public const ALLOWED = [
        'theme'  => '~^(theme\.json|theme\.css|readme\.txt|license\.txt|fonts/[a-z0-9_.-]{1,60}\.woff2?|images/[a-z0-9_.-]{1,60}\.(jpe?g|png|gif|webp))$~i',
        'plugin' => '~^(plugin\.json|template\.html|style\.css|widget\.js|widget\.css|readme\.txt|license\.txt|images/[a-z0-9_.-]{1,60}\.(jpe?g|png|gif|webp))$~i',
    ];

    public static function zipAvailable(): bool
    {
        return class_exists('ZipArchive');
    }

    /** @return array<string,string> relative path => file contents  @throws RuntimeException */
    public static function readUpload(array $file, string $kind): array
    {
        if (!isset(self::ALLOWED[$kind])) throw new RuntimeException('Unknown package type.');
        $err = $file['error'] ?? UPLOAD_ERR_NO_FILE;
        if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) throw new RuntimeException('That file is too large.');
        if ($err !== UPLOAD_ERR_OK || !is_string($file['tmp_name'] ?? null) || !is_uploaded_file($file['tmp_name'])) {
            throw new RuntimeException('Upload failed. Please choose a .zip file and try again.');
        }
        if (strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION)) !== 'zip') {
            throw new RuntimeException('Please upload a .zip file.');
        }
        if ((int)filesize($file['tmp_name']) > self::MAX_UPLOAD) throw new RuntimeException('Packages must be smaller than 8 MB.');
        if (!self::zipAvailable()) {
            throw new RuntimeException('Your server cannot open zip files. Unzip the package on your computer and copy the folder into "' . $kind . 's/" by FTP instead.');
        }
        return self::readZip($file['tmp_name'], $kind);
    }

    /** @return array<string,string> */
    public static function readZip(string $path, string $kind): array
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) throw new RuntimeException('This is not a valid .zip file.');
        try {
            $n = $zip->numFiles;
            if ($n < 1) throw new RuntimeException('The .zip file is empty.');
            if ($n > self::MAX_FILES) throw new RuntimeException('The package contains too many files (max ' . self::MAX_FILES . ').');
            $entries = [];
            $total = 0;
            for ($i = 0; $i < $n; $i++) {
                $st = $zip->statIndex($i);
                if (!$st) throw new RuntimeException('The .zip file is damaged.');
                $name = (string)$st['name'];
                if ($name === '' || str_contains($name, "\0") || str_contains($name, '\\')) {
                    throw new RuntimeException('The package contains an unsafe file name.');
                }
                if (str_ends_with($name, '/') || preg_match(self::JUNK, $name)) continue;
                if ($name[0] === '/' || preg_match('~^[a-z]:~i', $name) || preg_match('~(^|/)\.\.?(/|$)~', $name)) {
                    throw new RuntimeException('The package tries to write outside its folder ("' . substr($name, 0, 60) . '") and was rejected.');
                }
                if ($zip->getExternalAttributesIndex($i, $opsys, $attr) && $opsys === ZipArchive::OPSYS_UNIX && (($attr >> 16) & 0170000) === 0120000) {
                    throw new RuntimeException('The package contains a symbolic link ("' . substr($name, 0, 60) . '") and was rejected.');
                }
                $size = (int)$st['size'];
                if ($size > self::MAX_FILE) throw new RuntimeException('"' . substr($name, 0, 60) . '" is too large (max 3 MB per file).');
                $total += $size;
                if ($total > self::MAX_TOTAL) throw new RuntimeException('The package is too large when unpacked (max 15 MB).');
                $comp = max(1, (int)$st['comp_size']);
                if ($size > 65536 && $size / $comp > self::MAX_RATIO) throw new RuntimeException('The package looks like a "zip bomb" and was rejected.');
                $entries[$i] = $name;
            }
            if (!$entries) throw new RuntimeException('The .zip file is empty.');

            // Allow everything to sit inside one top-level folder (as most zip tools do).
            $first = explode('/', reset($entries))[0];
            $strip = true;
            foreach ($entries as $name) {
                if (!str_starts_with($name, $first . '/')) { $strip = false; break; }
            }
            $files = [];
            foreach ($entries as $i => $name) {
                $rel = $strip ? substr($name, strlen($first) + 1) : $name;
                if (!preg_match(self::ALLOWED[$kind], $rel)) {
                    throw new RuntimeException('"' . substr($rel, 0, 60) . '" is not allowed in a ' . $kind . '. ' . self::allowedHelp($kind));
                }
                $rel = strtolower($rel);
                $data = $zip->getFromIndex($i, self::MAX_FILE + 1);
                if (!is_string($data) || strlen($data) > self::MAX_FILE) throw new RuntimeException('Could not read "' . $rel . '".');
                self::checkContent($rel, $data);
                $files[$rel] = $data;
            }
            return $files;
        } finally {
            $zip->close();
        }
    }

    public static function allowedHelp(string $kind): string
    {
        return $kind === 'theme'
            ? 'Themes may only contain theme.json, theme.css, fonts/*.woff2 and images/*.jpg|png|gif|webp.'
            : 'Plugins may only contain plugin.json, template.html, style.css, widget.js, widget.css and images/*.jpg|png|gif|webp.';
    }

    /** Verify that images are real images, fonts are real fonts and text files are valid UTF-8 text. */
    public static function checkContent(string $rel, string $data): void
    {
        $ext = strtolower(pathinfo($rel, PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
            $info = @getimagesizefromstring($data);
            if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP], true) || preg_match('~<\?php|<script~i', $data)) {
                throw new RuntimeException('"' . $rel . '" is not a valid image.');
            }
        } elseif ($ext === 'woff2' || $ext === 'woff') {
            $magic = substr($data, 0, 4);
            if (($ext === 'woff2' && $magic !== 'wOF2') || ($ext === 'woff' && $magic !== 'wOFF')) {
                throw new RuntimeException('"' . $rel . '" is not a valid font file.');
            }
        } else {
            if (!preg_match('//u', $data)) throw new RuntimeException('"' . $rel . '" must be a UTF-8 text file.');
            if (str_contains($data, "\0")) throw new RuntimeException('"' . $rel . '" contains binary data.');
        }
    }

    /** Write files into $target via a temporary folder, replacing any previous version. */
    public static function writeFolder(string $target, array $files): void
    {
        $parent = dirname($target);
        if (!is_dir($parent) && !@mkdir($parent, 0755, true)) throw new RuntimeException('Cannot create folder ' . basename($parent) . '/.');
        if (!is_writable($parent)) throw new RuntimeException('The "' . basename($parent) . '" folder is not writable. Set its permission to 755 or 775.');
        $tmp = $parent . '/.tmp-' . random_id(6);
        if (!@mkdir($tmp, 0755)) throw new RuntimeException('Cannot write the package to disk.');
        try {
            foreach ($files as $rel => $data) {
                $dest = $tmp . '/' . $rel;
                if (!is_dir(dirname($dest))) @mkdir(dirname($dest), 0755, true);
                if (@file_put_contents($dest, $data) === false) throw new RuntimeException('Cannot write ' . $rel . '.');
                @chmod($dest, 0644);
            }
            $old = null;
            if (is_dir($target)) {
                $old = $parent . '/.old-' . random_id(6);
                if (!@rename($target, $old)) throw new RuntimeException('Cannot replace the existing version.');
            }
            if (!@rename($tmp, $target)) {
                if ($old) @rename($old, $target);
                throw new RuntimeException('Cannot install the package.');
            }
            if ($old) self::removeDir($old, $parent);
        } finally {
            if (is_dir($tmp)) self::removeDir($tmp, $parent);
        }
    }

    /** Recursively delete a folder, refusing anything outside $base. */
    public static function removeDir(string $dir, string $base): void
    {
        $realBase = realpath($base);
        $real = realpath($dir);
        if (!$realBase || !$real || !str_starts_with($real, $realBase . DIRECTORY_SEPARATOR) || is_link($dir)) return;
        $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($real, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($items as $item) {
            $item->isDir() && !$item->isLink() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
        @rmdir($real);
    }

    /** Zip a folder for download. Returns the temp file path. */
    public static function zipFolder(string $dir, string $rootName): string
    {
        if (!self::zipAvailable()) throw new RuntimeException('Your server cannot create zip files.');
        $tmp = (string)tempnam(sys_get_temp_dir(), 'teeny');
        $zip = new ZipArchive();
        $zip->open($tmp, ZipArchive::OVERWRITE);
        $base = realpath($dir);
        $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator((string)$base, FilesystemIterator::SKIP_DOTS));
        foreach ($items as $item) {
            if ($item->isDir() || $item->isLink()) continue;
            $rel = str_replace('\\', '/', substr($item->getPathname(), strlen((string)$base) + 1));
            if (str_starts_with(basename($rel), '.')) continue;
            $zip->addFile($item->getPathname(), $rootName . '/' . $rel);
        }
        $zip->close();
        return $tmp;
    }

    public static function sendZip(string $path, string $filename): never
    {
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . preg_replace('/[^a-z0-9._-]/i', '', $filename) . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        @unlink($path);
        exit;
    }
}
