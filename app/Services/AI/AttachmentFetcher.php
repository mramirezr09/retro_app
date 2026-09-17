<?php

namespace App\Services\AI;

class AttachmentFetcher
{
    private const MAX_BYTES = 20 * 1024 * 1024;
    private const TIMEOUT = 90;

    private const IMAGE_TYPES = [
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'gif'  => 'image/gif',
        'webp' => 'image/webp',
        'bmp'  => 'image/bmp',
        'svg'  => 'image/svg+xml',
    ];

    private const DOCUMENT_TYPES = [
        'pdf'  => 'application/pdf',
        'txt'  => 'text/plain',
        'csv'  => 'text/csv',
        'md'   => 'text/markdown',
        'json' => 'application/json',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'doc'  => 'application/msword',
        'rtf'  => 'application/rtf',
        'odt'  => 'application/vnd.oasis.opendocument.text',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'ppt'  => 'application/vnd.ms-powerpoint',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'xls'  => 'application/vnd.ms-excel',
    ];

    public function isImage(string $url): bool
    {
        return $this->imageMime($url) !== null;
    }

    public function isDocument(string $url): bool
    {
        return $this->documentMime($url) !== null;
    }

    public function imageMime(string $url): ?string
    {
        return self::IMAGE_TYPES[$this->extension($url)] ?? null;
    }

    public function documentMime(string $url): ?string
    {
        return self::DOCUMENT_TYPES[$this->extension($url)] ?? null;
    }

    public function extension(string $url): string
    {
        $path = (string) parse_url($url, PHP_URL_PATH);
        return strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
    }

    public function filename(string $url): string
    {
        $path = (string) parse_url($url, PHP_URL_PATH);
        $name = basename($path);
        return $name !== '' ? rawurldecode($name) : 'archivo';
    }

    /**
     * @return array{ok: bool, data_url: ?string, mime: ?string, filename: string, error: ?string}
     */
    public function dataUrl(string $url, string $mime): array
    {
        $filename = $this->filename($url);
        $binary = $this->binary($url, $mime);
        if ($binary === null) {
            return ['ok' => false, 'data_url' => null, 'mime' => null, 'filename' => $filename, 'error' => 'No se pudo descargar el archivo.'];
        }

        return ['ok' => true, 'data_url' => 'data:' . $mime . ';base64,' . base64_encode($binary), 'mime' => $mime, 'filename' => $filename, 'error' => null];
    }

    public function binary(string $url, string $mime): ?string
    {
        $cached = $this->cacheLookup($url, $mime);
        if ($cached !== null) {
            return $cached;
        }

        $binary = $this->download($url);
        if ($binary === null) {
            return null;
        }

        $this->cacheStore($url, $mime, $binary);
        return $binary;
    }

    private function isHttpUrl(string $url): bool
    {
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        return ($scheme === 'http' || $scheme === 'https') && (string) parse_url($url, PHP_URL_HOST) !== '';
    }

    private function cachePath(string $url, string $mime): ?string
    {
        $extension = $this->extension($url);
        if ($extension === '') {
            $extension = $mime === 'application/pdf' ? 'pdf' : 'bin';
        }
        $dir = storage_path('attachments');
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return null;
        }
        return $dir . DIRECTORY_SEPARATOR . sha1($url) . '.' . $extension;
    }

    private function cacheLookup(string $url, string $mime): ?string
    {
        $path = $this->cachePath($url, $mime);
        if ($path === null || !is_file($path)) {
            return null;
        }
        $contents = @file_get_contents($path);
        return $contents === false ? null : $contents;
    }

    private function cacheStore(string $url, string $mime, string $binary): void
    {
        $path = $this->cachePath($url, $mime);
        if ($path !== null) {
            @file_put_contents($path, $binary);
        }
    }

    private function download(string $url): ?string
    {
        if (!$this->isHttpUrl($url) || !function_exists('curl_init')) {
            return null;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_TIMEOUT        => self::TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => 20,
            CURLOPT_USERAGENT      => 'RetroApp/1.0',
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $body = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $size = (int) curl_getinfo($ch, CURLINFO_SIZE_DOWNLOAD);
        curl_close($ch);

        if (!is_string($body) || $httpCode >= 400 || ($size > 0 && $size > self::MAX_BYTES) || strlen($body) > self::MAX_BYTES) {
            return null;
        }

        return $body;
    }
}
