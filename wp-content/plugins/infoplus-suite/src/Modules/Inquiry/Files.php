<?php
/**
 * File storage for inquiry uploads.
 *
 * Files never go to the media library. They live in
 * wp-content/uploads/infoplus-inquiries/, which gets an .htaccess that
 * denies every web request (Apache 2.2 and 2.4 syntax, LiteSpeed reads it)
 * and an empty index.php. Stored names are random with a whitelisted
 * extension, so nothing uploaded can be reached by URL or executed. Admins
 * download through Admin::downloadFile() (capability + nonce).
 *
 * nginx ignores .htaccess: on such a host add a "deny all" location for the
 * folder (unverified for production, the live host is LiteSpeed).
 *
 * Flow: the browser uploads each file on its own (per-file progress) to
 * tmp/, the server remembers it in a transient bound to the form token, and
 * the final submit moves the claimed files into {year}/{month}/{random}/.
 * Unclaimed temp files are removed by the daily cleanup.
 *
 * @package Infoplus\Suite
 */

namespace Infoplus\Suite\Modules\Inquiry;

if (!defined('ABSPATH')) exit;

final class Files
{
    public const DIR = 'infoplus-inquiries';

    /**
     * Allowed extensions and the real MIME types each may have. A file passes
     * when wp_check_filetype_and_ext() accepts it for one of the candidates.
     * AI files are PDF-compatible (application/pdf) or, older ones, PostScript.
     */
    public const TYPES = [
        'pdf'  => ['application/pdf'],
        'ai'   => ['application/pdf', 'application/postscript', 'application/illustrator'],
        'eps'  => ['application/postscript', 'image/x-eps', 'application/eps'],
        'svg'  => ['image/svg+xml', 'text/xml', 'application/xml', 'text/plain'],
        'png'  => ['image/png'],
        'jpg'  => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'webp' => ['image/webp'],
    ];

    private const TEMP_TTL = DAY_IN_SECONDS;

    /** @return string[] Lower case extensions. */
    public static function extensions(): array
    {
        return array_keys(self::TYPES);
    }

    /** Absolute base folder, created and protected on first use. */
    public static function baseDir(): string
    {
        $up = wp_upload_dir(null, false);
        $dir = trailingslashit($up['basedir']) . self::DIR;
        self::protect($dir);
        return $dir;
    }

    private static function protect(string $dir): void
    {
        if (!is_dir($dir)) {
            wp_mkdir_p($dir);
        }
        $ht = $dir . '/.htaccess';
        if (!file_exists($ht)) {
            // Both syntaxes: Apache 2.4 (Require) and 2.2 (Deny), LiteSpeed understands both.
            $rules = "# Infoplus Suite: inquiry uploads are private.\n"
                . "<IfModule mod_authz_core.c>\n\tRequire all denied\n</IfModule>\n"
                . "<IfModule !mod_authz_core.c>\n\tOrder allow,deny\n\tDeny from all\n</IfModule>\n"
                . "Options -Indexes\n"
                . "<IfModule mod_php.c>\n\tphp_flag engine off\n</IfModule>\n";
            file_put_contents($ht, $rules);
        }
        if (!file_exists($dir . '/index.php')) {
            file_put_contents($dir . '/index.php', "<?php\n// Silence is golden.\n");
        }
    }

    private static function ensureDir(string $dir): void
    {
        if (!is_dir($dir)) {
            wp_mkdir_p($dir);
        }
        if (!file_exists($dir . '/index.php')) {
            file_put_contents($dir . '/index.php', "<?php\n// Silence is golden.\n");
        }
    }

    /**
     * Check one uploaded file: extension whitelist, real content type via
     * wp_check_filetype_and_ext(), size, and for SVG an <svg> root.
     *
     * @param array{name:string,tmp_name:string,size:int,error:int} $file
     * @return array{ext:string,mime:string}|\WP_Error
     */
    public static function check(array $file)
    {
        $name = (string) ($file['name'] ?? '');
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string) $file['tmp_name'])) {
            $code = (int) ($file['error'] ?? 0);
            if ($code === UPLOAD_ERR_INI_SIZE || $code === UPLOAD_ERR_FORM_SIZE) {
                return new \WP_Error('file_size', self::msgTooBig($name));
            }
            return new \WP_Error('file_upload', __('Die Datei konnte nicht hochgeladen werden. Bitte versuchen Sie es erneut.', 'infoplus-suite'));
        }

        $size = (int) filesize($file['tmp_name']);
        if ($size <= 0) {
            return new \WP_Error('file_empty', sprintf(__('„%s“ ist leer.', 'infoplus-suite'), $name));
        }
        if ($size > Settings::maxFileBytes()) {
            return new \WP_Error('file_size', self::msgTooBig($name));
        }

        $ext = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));
        if (!isset(self::TYPES[$ext])) {
            return new \WP_Error('file_type', self::msgType($name));
        }

        // wp_check_filetype_and_ext() also requires the real MIME type to be in
        // get_allowed_mime_types(). SVG and PostScript are not by default, so
        // they are allowed for the length of this check only (not for the media library).
        $allow = static function ($mimes) use ($ext) {
            foreach (self::TYPES[$ext] as $i => $m) {
                $mimes['ip_inquiry_' . $ext . '_' . $i] = $m;
            }
            return $mimes;
        };
        add_filter('upload_mimes', $allow, 999);
        try {
            return self::matchType($file, $name, $ext);
        } finally {
            remove_filter('upload_mimes', $allow, 999);
        }
    }

    /** @return array{ext:string,mime:string}|\WP_Error */
    private static function matchType(array $file, string $name, string $ext)
    {
        $images = ['jpg|jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
        foreach (self::TYPES[$ext] as $mime) {
            $map = $images;
            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                $map[$ext] = $mime;
            }
            $checked = wp_check_filetype_and_ext($file['tmp_name'], $name, $map);
            if (!empty($checked['ext']) && isset(self::TYPES[$checked['ext']])) {
                $real = (string) $checked['ext'];
                if ($real === 'svg' && !self::looksLikeSvg($file['tmp_name'])) {
                    continue;
                }
                return ['ext' => $real === 'jpeg' ? 'jpg' : $real, 'mime' => (string) $checked['type']];
            }
        }
        return new \WP_Error('file_type', self::msgType($name));
    }

    private static function looksLikeSvg(string $path): bool
    {
        $head = (string) file_get_contents($path, false, null, 0, 4096);
        return stripos($head, '<svg') !== false;
    }

    public static function msgType(string $name): string
    {
        return sprintf(
            /* translators: %s: file name */
            __('„%s“: Dieser Dateityp wird nicht unterstützt. Erlaubt sind PDF, AI, EPS, SVG, PNG, JPG und WEBP.', 'infoplus-suite'),
            $name
        );
    }

    public static function msgTooBig(string $name): string
    {
        return sprintf(
            /* translators: 1: file name, 2: size limit */
            __('„%1$s“ ist zu groß. Insgesamt sind höchstens %2$s möglich.', 'infoplus-suite'),
            $name,
            size_format(Settings::maxUploadBytes())
        );
    }

    /**
     * Store a checked upload in tmp/ and remember it for this form token.
     *
     * @param array{name:string,tmp_name:string,size:int} $file
     * @param array{ext:string,mime:string}               $type
     * @return array{id:string,name:string,size:int}|\WP_Error
     */
    public static function storeTemp(array $file, array $type, string $token)
    {
        $tkey = Token::key($token);
        $list = self::tempList($tkey);

        if (count($list) >= Settings::maxFiles()) {
            return new \WP_Error('file_count', sprintf(__('Bitte laden Sie höchstens %d Dateien hoch.', 'infoplus-suite'), Settings::maxFiles()));
        }
        $size = (int) filesize($file['tmp_name']);
        $total = $size;
        foreach ($list as $item) {
            $total += (int) $item['size'];
        }
        if ($total > Settings::maxUploadBytes()) {
            return new \WP_Error('file_size', self::msgTooBig((string) $file['name']));
        }

        $dir = self::baseDir() . '/tmp';
        self::ensureDir($dir);
        $id = bin2hex(random_bytes(16));
        $path = $dir . '/' . $id . '.' . $type['ext'];
        if (!@move_uploaded_file($file['tmp_name'], $path)) {
            return new \WP_Error('file_upload', __('Die Datei konnte nicht gespeichert werden.', 'infoplus-suite'));
        }
        @chmod($path, 0640);

        $list[$id] = [
            'id'   => $id,
            'name' => self::cleanName((string) $file['name'], $type['ext']),
            'size' => $size,
            'ext'  => $type['ext'],
            'mime' => $type['mime'],
            'path' => $path,
        ];
        set_transient('infoplus_inq_up_' . $tkey, $list, self::TEMP_TTL);

        return ['id' => $id, 'name' => $list[$id]['name'], 'size' => $size];
    }

    /** @return array<string, array<string, mixed>> */
    public static function tempList(string $tkey): array
    {
        $list = get_transient('infoplus_inq_up_' . $tkey);
        return is_array($list) ? $list : [];
    }

    public static function deleteTemp(string $token, string $id): bool
    {
        $tkey = Token::key($token);
        $list = self::tempList($tkey);
        if (!isset($list[$id])) {
            return false;
        }
        self::unlinkInside((string) $list[$id]['path']);
        unset($list[$id]);
        set_transient('infoplus_inq_up_' . $tkey, $list, self::TEMP_TTL);
        return true;
    }

    /**
     * Move the files of this token whose ids the submit names into the final
     * folder. Unknown ids are ignored (never trusted).
     *
     * @param string[] $ids
     * @return array<int, array{name:string,file:string,size:int,ext:string,mime:string}>
     */
    public static function claim(string $token, array $ids): array
    {
        $tkey = Token::key($token);
        $list = self::tempList($tkey);
        if (!$list) {
            return [];
        }
        $dest = self::baseDir() . '/' . gmdate('Y/m') . '/' . bin2hex(random_bytes(8));
        self::ensureDir(dirname($dest));
        self::ensureDir($dest);

        $out = [];
        foreach (array_unique($ids) as $id) {
            if (!isset($list[$id]) || !is_file((string) $list[$id]['path'])) {
                continue;
            }
            $item = $list[$id];
            $target = $dest . '/' . bin2hex(random_bytes(12)) . '.' . $item['ext'];
            if (@rename((string) $item['path'], $target)) {
                $out[] = [
                    'name' => (string) $item['name'],
                    'file' => ltrim(str_replace(self::baseDir(), '', $target), '/'),
                    'size' => (int) $item['size'],
                    'ext'  => (string) $item['ext'],
                    'mime' => (string) $item['mime'],
                ];
            }
            unset($list[$id]);
        }
        // Files uploaded but removed again before the submit go now.
        foreach ($list as $left) {
            self::unlinkInside((string) $left['path']);
        }
        delete_transient('infoplus_inq_up_' . $tkey);
        return $out;
    }

    /** Absolute path of a stored file (relative path from post meta), or '' if outside the folder. */
    public static function absolute(string $relative): string
    {
        $base = realpath(self::baseDir());
        $path = realpath(self::baseDir() . '/' . ltrim($relative, '/'));
        if (!$base || !$path || strpos($path, $base . DIRECTORY_SEPARATOR) !== 0 || !is_file($path)) {
            return '';
        }
        return $path;
    }

    /** Delete the stored files of one inquiry (permanent delete in wp-admin). */
    public static function deleteStored(array $files): void
    {
        $dirs = [];
        foreach ($files as $f) {
            $abs = self::absolute((string) ($f['file'] ?? ''));
            if ($abs !== '') {
                @unlink($abs);
                $dirs[dirname($abs)] = true;
            }
        }
        foreach (array_keys($dirs) as $dir) {
            @unlink($dir . '/index.php');
            @rmdir($dir);
        }
    }

    /** Daily: remove temp files older than a day (uploads of forms never sent). */
    public static function cleanup(): void
    {
        $dir = self::baseDir() . '/tmp';
        if (!is_dir($dir)) {
            return;
        }
        foreach ((array) glob($dir . '/*.*') as $path) {
            if (basename((string) $path) === 'index.php') {
                continue;
            }
            if (is_file((string) $path) && filemtime((string) $path) < time() - self::TEMP_TTL) {
                @unlink((string) $path);
            }
        }
    }

    private static function unlinkInside(string $path): void
    {
        $base = realpath(self::baseDir());
        $real = realpath($path);
        if ($base && $real && strpos($real, $base . DIRECTORY_SEPARATOR) === 0 && is_file($real)) {
            @unlink($real);
        }
    }

    /** Display name: the original name, cleaned, with the checked extension. */
    private static function cleanName(string $name, string $ext): string
    {
        $base = sanitize_file_name((string) pathinfo($name, PATHINFO_FILENAME));
        $base = mb_substr($base !== '' ? $base : 'datei', 0, 80);
        return $base . '.' . $ext;
    }
}
