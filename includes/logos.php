<?php
/**
 * Document logos, per barangay.
 *
 * A certificate uses four images. Each barangay can upload its own copy of
 * every one (Secretary > Document Logos); until it does, the built-in file
 * shipped with the system is used, so documents keep working either way.
 *
 *   slot         on the certificate                      built-in fallback
 *   seal         top right, barangay seal with ribbons   assets/img/seals/<slug>.png
 *   watermark    faint behind the text; also the top-    assets/img/seals/<slug>-watermark.png
 *                right seal when there is no ribbon one
 *   municipal    top left, municipal seal                assets/img/logo.png
 *   bagong       top left, Bagong Pilipinas logo         assets/img/bagong_pilipinas.png
 *
 * Uploads are stored as uploads/seals/b<barangay id>-<slot>.png. They are keyed
 * by id, not name, so renaming a barangay never loses its logos. Every upload
 * is decoded and re-saved as PNG, which both normalises the format and drops
 * anything in the file that is not image data.
 */

if (!function_exists('logo_slots')) {

    /** slot => [label, where it appears, tip for a good file]. */
    function logo_slots(): array
    {
        return [
            'seal' => [
                'label' => 'Barangay seal with ribbon',
                'where' => 'Top right of every certificate.',
                'tip'   => 'The full seal including the ribbons, on a transparent background.',
            ],
            'watermark' => [
                'label' => 'Plain round seal (watermark)',
                'where' => 'Faint watermark behind the text. Also used at the top right when there is no ribbon seal.',
                'tip'   => 'Only the round seal, no ribbons, on a transparent background.',
            ],
            'municipal' => [
                'label' => 'Municipal seal',
                'where' => 'Top left of every certificate.',
                'tip'   => 'The official seal of the municipality.',
            ],
            'bagong' => [
                'label' => 'Bagong Pilipinas logo',
                'where' => 'Top left, beside the municipal seal.',
                'tip'   => 'The Bagong Pilipinas logo, on a transparent background.',
            ],
        ];
    }

    function logo_upload_dir(): string
    {
        return BASE_PATH . '/uploads/seals';
    }

    /** Where barangay $bid's own upload for $slot lives (whether or not it exists yet). */
    function logo_upload_path(int $bid, string $slot): string
    {
        return logo_upload_dir() . '/b' . $bid . '-' . $slot . '.png';
    }

    /** "ABARIONGAN RUAR" -> "abariongan-ruar": the name the built-in seal files use. */
    function logo_slug(string $barangay_name): string
    {
        return trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($barangay_name)), '-');
    }

    /** The built-in file for a slot, relative to assets/img, or null when the slot has none for this barangay. */
    function logo_builtin_rel(string $slug, string $slot): ?string
    {
        switch ($slot) {
            case 'seal':      return $slug !== '' ? 'seals/' . $slug . '.png' : null;
            case 'watermark': return $slug !== '' ? 'seals/' . $slug . '-watermark.png' : null;
            case 'municipal': return 'logo.png';
            case 'bagong':    return 'bagong_pilipinas.png';
        }
        return null;
    }

    /**
     * The image a document should use for $slot:
     *   ['source' => 'uploaded' | 'builtin', 'path' => file on disk, 'url' => link]
     * or null when there is neither an upload nor a built-in file.
     */
    function brgy_logo(int $bid, string $slug, string $slot): ?array
    {
        if (!isset(logo_slots()[$slot])) { return null; }

        $up = logo_upload_path($bid, $slot);
        if ($bid > 0 && is_file($up)) {
            return [
                'source' => 'uploaded',
                'path'   => $up,
                'url'    => url('uploads/seals/' . basename($up)) . '?v=' . filemtime($up),
            ];
        }
        $rel = logo_builtin_rel($slug, $slot);
        if ($rel !== null && is_file(BASE_PATH . '/assets/img/' . $rel)) {
            return [
                'source' => 'builtin',
                'path'   => BASE_PATH . '/assets/img/' . $rel,
                'url'    => asset('img/' . $rel),
            ];
        }
        return null;
    }

    /** The largest upload this server will accept, in bytes (never more than 8 MB). */
    function logo_max_bytes(): int
    {
        $to_bytes = function ($v) {
            $v = trim((string)$v);
            if ($v === '') { return 0; }
            $n = (float)$v;
            switch (strtolower(substr($v, -1))) {
                case 'g': $n *= 1024;
                case 'm': $n *= 1024;
                case 'k': $n *= 1024;
            }
            return (int)$n;
        };
        $limits = array_filter([$to_bytes(ini_get('upload_max_filesize')), $to_bytes(ini_get('post_max_size'))]);
        $limits[] = 8 * 1024 * 1024;
        return (int)min($limits);
    }

    /**
     * Validate an entry of $_FILES and store it as barangay $bid's $slot.
     * Returns null on success or a message to show the user.
     */
    function logo_save_upload(array $file, int $bid, string $slot): ?string
    {
        if ($bid <= 0 || !isset(logo_slots()[$slot])) { return 'Unknown logo.'; }

        $max_mb = rtrim(rtrim(number_format(logo_max_bytes() / 1048576, 1), '0'), '.');
        $err = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($err === UPLOAD_ERR_NO_FILE) { return 'Choose an image file first.'; }
        if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) { return 'That file is too large. The limit is ' . $max_mb . ' MB.'; }
        if ($err !== UPLOAD_ERR_OK) { return 'The upload did not finish. Please try again.'; }

        $tmp = (string)($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) { return 'The upload did not finish. Please try again.'; }
        if ((int)($file['size'] ?? 0) > logo_max_bytes()) { return 'That file is too large. The limit is ' . $max_mb . ' MB.'; }

        // Decide the type from the file's own contents, never from its name.
        $info = @getimagesize($tmp);
        $loaders = [
            IMAGETYPE_PNG  => 'imagecreatefrompng',
            IMAGETYPE_JPEG => 'imagecreatefromjpeg',
            IMAGETYPE_GIF  => 'imagecreatefromgif',
            IMAGETYPE_WEBP => 'imagecreatefromwebp',
        ];
        if (!$info || !isset($loaders[$info[2]])) { return 'That file is not a PNG, JPG, GIF or WebP image.'; }
        [$w, $h] = $info;
        if ($w < 64 || $h < 64) { return 'That image is too small. Use one at least 64 pixels wide and tall.'; }
        if ($w > 8000 || $h > 8000) { return 'That image is too large. Use one no bigger than 8000 pixels on a side.'; }

        $loader = $loaders[$info[2]];
        if (!function_exists($loader) || !function_exists('imagepng')) {
            return 'This server cannot process images (the PHP GD extension is missing).';
        }
        $src = @$loader($tmp);
        if (!$src) { return 'That image could not be read. Try saving it again as a PNG.'; }

        // Re-draw onto a clean transparent canvas, shrinking anything over 1600px.
        $scale = min(1, 1600 / max($w, $h));
        $nw = max(1, (int)round($w * $scale));
        $nh = max(1, (int)round($h * $scale));
        $dst = imagecreatetruecolor($nw, $nh);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imagedestroy($src);

        $dir = logo_upload_dir();
        if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
            imagedestroy($dst);
            return 'The server could not create the folder for logos.';
        }
        $dest = logo_upload_path($bid, $slot);
        $part = $dest . '.part';
        $ok = @imagepng($dst, $part, 6);
        imagedestroy($dst);
        if (!$ok || !@rename($part, $dest)) {
            @unlink($part);
            return 'The server could not save the image. Check that uploads/seals is writable.';
        }
        @unlink(preg_replace('/\.png$/', '.json', $dest));   // measured seal position, re-measured on next use
        clearstatcache(true, $dest);
        return null;
    }

    /** Remove barangay $bid's own upload for $slot, so the built-in file is used again. */
    function logo_remove_upload(int $bid, string $slot): bool
    {
        if ($bid <= 0 || !isset(logo_slots()[$slot])) { return false; }
        $dest = logo_upload_path($bid, $slot);
        @unlink(preg_replace('/\.png$/', '.json', $dest));
        return is_file($dest) ? @unlink($dest) : false;
    }
}
