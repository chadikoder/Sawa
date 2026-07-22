<?php
declare(strict_types=1);

final class Upload
{
    /** @var array<string, string> */
    private const IMAGE_MIMES = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    /** @var array<string, string> */
    private const DOC_MIMES = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'application/pdf' => 'pdf',
    ];

    /**
     * Store an uploaded file safely.
     *
     * @return string Relative path under UPLOAD_ROOT (e.g. profiles/abc.jpg)
     */
    public static function store(array $file, string $subdir, bool $allowPdf = false): string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('upload_failed');
        }
        if (($file['size'] ?? 0) > MAX_UPLOAD_BYTES) {
            throw new RuntimeException('file_too_large');
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        if (!is_string($mime)) {
            throw new RuntimeException('invalid_file');
        }

        $map = $allowPdf ? array_merge(self::IMAGE_MIMES, ['application/pdf' => 'pdf']) : self::IMAGE_MIMES;
        if (!isset($map[$mime])) {
            throw new RuntimeException('invalid_file_type');
        }

        $ext = $map[$mime];
        $dir = UPLOAD_ROOT . DIRECTORY_SEPARATOR . trim($subdir, '/\\');
        if (!is_dir($dir) && !mkdir($dir, 0750, true)) {
            throw new RuntimeException('upload_dir_failed');
        }

        $name = bin2hex(random_bytes(16)) . '.' . $ext;
        $dest = $dir . DIRECTORY_SEPARATOR . $name;
        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            throw new RuntimeException('move_failed');
        }

        return trim($subdir, '/\\') . '/' . $name;
    }

    /**
     * URL for a stored upload, for use in an <img src>.
     *
     * Host-relative via url(), not absolute via APP_URL. Every caller drops
     * this straight into markup, where a host is unnecessary, and APP_URL is
     * whatever the bundled .env happened to say on the author's machine — so
     * on a handed-over copy served from a different folder every avatar and
     * campaign image resolved to an address that does not exist.
     */
    public static function publicUrl(string $relativePath): string
    {
        return url('storage/uploads/' . ltrim(str_replace('\\', '/', $relativePath), '/'));
    }
}
