<?php
declare(strict_types=1);

/**
 * Image uploads. Only real images are accepted; they are renamed randomly, and
 * re-encoded with GD when available (which strips hidden payloads and metadata).
 */
final class Media
{
    private const TYPES = [
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_PNG  => 'png',
        IMAGETYPE_GIF  => 'gif',
        IMAGETYPE_WEBP => 'webp',
    ];
    private const ALLOWED_EXT = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    private const MAX_SIDE = 2400;
    private const THUMB_SIDE = 800;

    public static function validRef(mixed $ref): bool
    {
        return is_string($ref) && (bool)preg_match('~^uploads/[a-f0-9]{16}\.(jpg|png|gif|webp)$~', $ref);
    }

    public static function exists(string $ref): bool
    {
        return self::validRef($ref) && is_file(TEENY_ROOT . '/' . $ref);
    }

    public static function thumb(string $ref): string
    {
        $t = preg_replace('~\.(\w+)$~', '-sm.$1', $ref);
        return ($t && is_file(TEENY_ROOT . '/' . $t)) ? $t : $ref;
    }

    public static function all(): array
    {
        $data = Store::read('media', ['items' => []]);
        $items = is_array($data['items'] ?? null) ? $data['items'] : [];
        uasort($items, static fn($a, $b) => ($b['uploaded'] ?? 0) <=> ($a['uploaded'] ?? 0));
        return $items;
    }

    public static function forClient(): array
    {
        $out = [];
        foreach (self::all() as $name => $m) {
            $ref = 'uploads/' . $name;
            $out[] = [
                'name'  => $name,
                'ref'   => $ref,
                'url'   => url($ref),
                'thumb' => url(self::thumb($ref)),
                'alt'   => $m['alt'] ?? '',
                'w'     => $m['w'] ?? 0,
                'h'     => $m['h'] ?? 0,
                'size'  => fmt_bytes((int)($m['size'] ?? 0)),
                'original' => $m['original'] ?? $name,
            ];
        }
        return $out;
    }

    /** @throws RuntimeException with a user-friendly message */
    public static function upload(array $file, string $userId): array
    {
        $err = $file['error'] ?? UPLOAD_ERR_NO_FILE;
        if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {
            throw new RuntimeException('That file is too large.');
        }
        if ($err !== UPLOAD_ERR_OK || !is_string($file['tmp_name'] ?? null) || !is_uploaded_file($file['tmp_name'])) {
            throw new RuntimeException('Upload failed. Please try again.');
        }
        $tmp = $file['tmp_name'];
        $size = (int)filesize($tmp);
        if ($size <= 0 || $size > TEENY_MAX_UPLOAD_MB * 1048576) {
            throw new RuntimeException('Images must be smaller than ' . TEENY_MAX_UPLOAD_MB . ' MB.');
        }
        $origName = Sanitizer::text((string)($file['name'] ?? 'image'), 120);
        $origExt = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
        if (!in_array($origExt, self::ALLOWED_EXT, true)) {
            throw new RuntimeException('Only JPG, PNG, GIF and WebP images are allowed.');
        }
        $info = @getimagesize($tmp);
        if (!$info || !isset(self::TYPES[$info[2]])) {
            throw new RuntimeException('This file is not a valid image.');
        }
        [$w, $h, $type] = $info;
        if ($w < 1 || $h < 1 || $w > 12000 || $h > 12000 || $w * $h > 60000000) {
            throw new RuntimeException('Image dimensions are too large.');
        }
        if (function_exists('finfo_open')) {
            $fi = finfo_open(FILEINFO_MIME_TYPE);
            $mime = $fi ? (string)finfo_file($fi, $tmp) : '';
            if ($fi) finfo_close($fi);
            if ($mime !== '' && !str_starts_with($mime, 'image/')) {
                throw new RuntimeException('This file is not a valid image.');
            }
        }
        $head = (string)file_get_contents($tmp);
        if (preg_match('~<\?php|<script|<html|<\?=\s*\$~i', $head)) {
            throw new RuntimeException('This image contains suspicious content and was rejected.');
        }
        unset($head);

        $ext = self::TYPES[$type];
        $name = random_id(8) . '.' . $ext;
        $dest = TEENY_UPLOADS . '/' . $name;
        if (!is_dir(TEENY_UPLOADS)) @mkdir(TEENY_UPLOADS, 0755, true);

        $saved = false;
        if ($type !== IMAGETYPE_GIF && self::gdCan($type)) {
            $saved = self::reencode($tmp, $dest, $type, $w, $h, self::MAX_SIDE);
            if ($saved) {
                $dims = @getimagesize($dest);
                if ($dims) [$w, $h] = $dims;
                if (max($w, $h) > self::THUMB_SIDE) {
                    self::reencode($dest, preg_replace('~\.(\w+)$~', '-sm.$1', $dest), $type, $w, $h, self::THUMB_SIDE);
                }
            }
        }
        // Still GIFs are re-saved too, which drops anything hidden in the file. Animated GIFs are kept
        // as uploaded (GD would lose the animation); uploads/ never runs code, so they're safe to serve.
        if (!$saved && $type === IMAGETYPE_GIF && self::gdAvailable() && function_exists('imagecreatefromgif') && function_exists('imagegif')
            && substr_count((string)file_get_contents($tmp), "\x21\xF9\x04") <= 1) {
            $gif = @imagecreatefromgif($tmp);
            if ($gif) {
                $saved = imagegif($gif, $dest);
                imagedestroy($gif);
            }
        }
        if (!$saved && !move_uploaded_file($tmp, $dest)) {
            throw new RuntimeException('Could not save the image. Check that the uploads folder is writable.');
        }
        @chmod($dest, 0644);

        $item = [
            'original' => $origName,
            'alt'      => '',
            'w'        => $w,
            'h'        => $h,
            'size'     => (int)filesize($dest),
            'uploaded' => time(),
            'by'       => $userId,
        ];
        Store::update('media', static function (array $d) use ($name, $item): array {
            $d['items'][$name] = $item;
            return $d;
        }, ['items' => []]);
        return ['name' => $name] + $item;
    }

    public static function setAlt(string $name, string $alt): bool
    {
        if (!preg_match('~^[a-f0-9]{16}\.(jpg|png|gif|webp)$~', $name)) return false;
        $found = false;
        Store::update('media', static function (array $d) use ($name, $alt, &$found): array {
            if (isset($d['items'][$name])) {
                $d['items'][$name]['alt'] = Sanitizer::text($alt, 200);
                $found = true;
            }
            return $d;
        }, ['items' => []]);
        return $found;
    }

    public static function delete(string $name): bool
    {
        if (!preg_match('~^[a-f0-9]{16}\.(jpg|png|gif|webp)$~', $name)) return false;
        $file = TEENY_UPLOADS . '/' . $name;
        $thumb = preg_replace('~\.(\w+)$~', '-sm.$1', $file);
        if (is_file($file)) @unlink($file);
        if ($thumb && is_file($thumb)) @unlink($thumb);
        Store::update('media', static function (array $d) use ($name): array {
            unset($d['items'][$name]);
            return $d;
        }, ['items' => []]);
        Cache::clear(); // pages that used this image must be re-rendered
        return true;
    }

    public static function gdAvailable(): bool
    {
        return function_exists('imagecreatetruecolor');
    }

    private static function gdCan(int $type): bool
    {
        if (!self::gdAvailable()) return false;
        return match ($type) {
            IMAGETYPE_JPEG => function_exists('imagecreatefromjpeg') && function_exists('imagejpeg'),
            IMAGETYPE_PNG  => function_exists('imagecreatefrompng') && function_exists('imagepng'),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') && function_exists('imagewebp'),
            default        => false,
        };
    }

    private static function reencode(string $src, string $dest, int $type, int $w, int $h, int $maxSide): bool
    {
        $img = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($src),
            IMAGETYPE_PNG  => @imagecreatefrompng($src),
            IMAGETYPE_WEBP => @imagecreatefromwebp($src),
            default        => false,
        };
        if (!$img) return false;

        // Respect camera orientation for JPEG photos.
        if ($type === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
            $exif = @exif_read_data($src);
            $o = (int)($exif['Orientation'] ?? 1);
            $angle = [3 => 180, 6 => -90, 8 => 90][$o] ?? 0;
            if ($angle !== 0) {
                $rot = imagerotate($img, $angle, 0);
                if ($rot) {
                    imagedestroy($img);
                    $img = $rot;
                    $w = imagesx($img);
                    $h = imagesy($img);
                }
            }
        }

        $scale = min(1, $maxSide / max($w, $h));
        $nw = max(1, (int)round($w * $scale));
        $nh = max(1, (int)round($h * $scale));
        $out = imagecreatetruecolor($nw, $nh);
        if ($type !== IMAGETYPE_JPEG) {
            imagealphablending($out, false);
            imagesavealpha($out, true);
            imagefill($out, 0, 0, imagecolorallocatealpha($out, 0, 0, 0, 127));
        }
        imagecopyresampled($out, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
        $ok = match ($type) {
            IMAGETYPE_JPEG => imagejpeg($out, $dest, 84),
            IMAGETYPE_PNG  => imagepng($out, $dest, 7),
            IMAGETYPE_WEBP => imagewebp($out, $dest, 84),
            default        => false,
        };
        imagedestroy($img);
        imagedestroy($out);
        return (bool)$ok;
    }
}
