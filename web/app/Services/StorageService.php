<?php

namespace App\Services;

use Config\Storage as StorageConfig;
use RuntimeException;

/**
 * StorageService
 *
 * S3-compatible (MinIO / AWS S3) and Local storage service for CodeIgniter 4.
 * Implements native AWS Signature Version 4 (SigV4) for zero-dependency S3 operations
 * with seamless fallback to local disk.
 */
class StorageService
{
    protected StorageConfig $config;

    public function __construct(?StorageConfig $config = null)
    {
        $this->config = $config ?? config('Storage');
    }

    /**
     * Get active storage driver ('minio' or 'local').
     */
    public function getDriver(): string
    {
        return strtolower($this->config->driver);
    }

    /**
     * Whether the MinIO driver is currently enabled.
     */
    public function isMinio(): bool
    {
        return $this->getDriver() === 'minio';
    }

    // ─────────────────────────────────────────────────────────────
    // SMS Payload Operations (Bucket: mpesa-payloads)
    // ─────────────────────────────────────────────────────────────

    /**
     * Store an encrypted SMS payload file.
     */
    public function putPayload(string $filename, string $content): bool
    {
        $filename = basename($filename);

        // Always save to local writable directory for immediate processing and backup safety
        $localPath = WRITEPATH . 'uploads/payloads/' . $filename;
        $this->ensureDirectory(dirname($localPath));
        file_put_contents($localPath, $content);

        // If MinIO is enabled, replicate to MinIO
        if ($this->isMinio()) {
            try {
                $response = $this->s3Request('PUT', $this->config->bucketPayloads, $filename, $content, [
                    'Content-Type' => 'application/octet-stream',
                ]);
                return $response !== null && ($response['statusCode'] >= 200 && $response['statusCode'] < 300);
            } catch (\Throwable $e) {
                log_message('error', 'StorageService::putPayload MinIO upload failed: ' . $e->getMessage());
                // Local copy already exists, so continue gracefully
                return true;
            }
        }

        return true;
    }

    /**
     * Retrieve payload content, checking MinIO first, then local filesystem fallback.
     */
    public function getPayload(string $filename): string
    {
        $filename = basename($filename);
        $localPath = WRITEPATH . 'uploads/payloads/' . $filename;

        // Try MinIO first if enabled
        if ($this->isMinio()) {
            try {
                $response = $this->s3Request('GET', $this->config->bucketPayloads, $filename);
                if ($response !== null && $response['statusCode'] === 200) {
                    // Update/cache local copy if missing
                    if (!file_exists($localPath)) {
                        $this->ensureDirectory(dirname($localPath));
                        file_put_contents($localPath, $response['body']);
                    }
                    return $response['body'];
                }
            } catch (\Throwable $e) {
                log_message('warning', 'StorageService::getPayload MinIO fetch failed for ' . $filename . ': ' . $e->getMessage() . '. Falling back to local disk.');
            }
        }

        // Fallback to local disk
        if (file_exists($localPath)) {
            $content = file_get_contents($localPath);
            if ($content !== false) {
                // If MinIO is enabled but didn't have it, backfill in background/lazily
                if ($this->isMinio()) {
                    try {
                        $this->s3Request('PUT', $this->config->bucketPayloads, $filename, $content, [
                            'Content-Type' => 'application/octet-stream',
                        ]);
                    } catch (\Throwable $e) {
                        // ignore backfill error
                    }
                }
                return $content;
            }
        }

        throw new RuntimeException("Payload file not found: " . $filename);
    }

    /**
     * Check if a payload file exists in MinIO or local storage.
     */
    public function hasPayload(string $filename): bool
    {
        $filename = basename($filename);
        $localPath = WRITEPATH . 'uploads/payloads/' . $filename;

        if (file_exists($localPath)) {
            return true;
        }

        if ($this->isMinio()) {
            try {
                $response = $this->s3Request('HEAD', $this->config->bucketPayloads, $filename);
                return $response !== null && $response['statusCode'] === 200;
            } catch (\Throwable $e) {
                return false;
            }
        }

        return false;
    }

    /**
     * Delete an encrypted payload from storage.
     */
    public function deletePayload(string $filename): bool
    {
        $filename = basename($filename);
        $localPath = WRITEPATH . 'uploads/payloads/' . $filename;

        if (file_exists($localPath)) {
            @unlink($localPath);
        }

        if ($this->isMinio()) {
            try {
                $this->s3Request('DELETE', $this->config->bucketPayloads, $filename);
            } catch (\Throwable $e) {
                log_message('warning', 'StorageService::deletePayload MinIO delete failed: ' . $e->getMessage());
            }
        }

        return true;
    }

    // ─────────────────────────────────────────────────────────────
    // Database Backup Operations (Bucket: mpesa-backups)
    // ─────────────────────────────────────────────────────────────

    /**
     * Store a database backup snapshot.
     */
    public function putBackup(string $filename, string $content): bool
    {
        $filename = basename($filename);

        // Store locally
        $localPath = WRITEPATH . 'backups/' . $filename;
        $this->ensureDirectory(dirname($localPath));
        file_put_contents($localPath, $content);

        if ($this->isMinio()) {
            try {
                $contentType = str_ends_with($filename, '.gz') ? 'application/gzip' : 'application/sql';
                $response = $this->s3Request('PUT', $this->config->bucketBackups, $filename, $content, [
                    'Content-Type' => $contentType,
                ]);
                return $response !== null && ($response['statusCode'] >= 200 && $response['statusCode'] < 300);
            } catch (\Throwable $e) {
                log_message('error', 'StorageService::putBackup MinIO upload failed: ' . $e->getMessage());
                return true;
            }
        }

        return true;
    }

    /**
     * Retrieve backup content, checking MinIO first with local fallback.
     */
    public function getBackup(string $filename): string
    {
        $filename = basename($filename);
        $localPath = WRITEPATH . 'backups/' . $filename;

        if ($this->isMinio()) {
            try {
                $response = $this->s3Request('GET', $this->config->bucketBackups, $filename);
                if ($response !== null && $response['statusCode'] === 200) {
                    return $response['body'];
                }
            } catch (\Throwable $e) {
                log_message('warning', 'StorageService::getBackup MinIO fetch failed for ' . $filename . ': ' . $e->getMessage());
            }
        }

        if (file_exists($localPath)) {
            $content = file_get_contents($localPath);
            if ($content !== false) {
                return $content;
            }
        }

        throw new RuntimeException("Backup file not found: " . $filename);
    }

    /**
     * Delete a backup file from MinIO and local storage.
     */
    public function deleteBackup(string $filename): bool
    {
        $filename = basename($filename);
        $localPath = WRITEPATH . 'backups/' . $filename;

        if (file_exists($localPath)) {
            @unlink($localPath);
        }

        if ($this->isMinio()) {
            try {
                $this->s3Request('DELETE', $this->config->bucketBackups, $filename);
            } catch (\Throwable $e) {
                log_message('warning', 'StorageService::deleteBackup MinIO delete failed: ' . $e->getMessage());
            }
        }

        return true;
    }

    /**
     * Generate a pre-signed download URL for a backup file (valid for $expiresSeconds).
     */
    public function getBackupDownloadUrl(string $filename, int $expiresSeconds = 900): string
    {
        $filename = basename($filename);

        if ($this->isMinio()) {
            return $this->generatePresignedUrl($this->config->bucketBackups, $filename, $expiresSeconds);
        }

        return site_url('admin/system/backup/download-file/' . rawurlencode($filename));
    }

    /**
     * List all database backups across MinIO and local storage.
     *
     * @return array<int, array{name: string, size: int, human_size: string, modified: string, source: string}>
     */
    public function listBackups(): array
    {
        $backups = [];
        $seen = [];

        // 1. MinIO Backups
        if ($this->isMinio()) {
            try {
                $response = $this->s3Request('GET', $this->config->bucketBackups);
                if ($response !== null && $response['statusCode'] === 200 && !empty($response['body'])) {
                    // Strip default namespace to allow uniform xpath/element traversal
                    $cleanXml = preg_replace('/xmlns="[^"]+"/', '', $response['body']);
                    $xml = @simplexml_load_string($cleanXml);
                    if ($xml !== false && isset($xml->Contents)) {
                        foreach ($xml->Contents as $item) {
                            $key = (string) $item->Key;
                            if (preg_match('/\.(sql|sql\.gz|gz)$/i', $key)) {
                                $size = (int) $item->Size;
                                $lastModified = (string) $item->LastModified;
                                $timeStr = date('Y-m-d H:i:s', strtotime($lastModified));
                                $seen[$key] = true;
                                $backups[] = [
                                    'name'       => $key,
                                    'size'       => $size,
                                    'human_size' => $this->humanSize($size),
                                    'modified'   => $timeStr,
                                    'source'     => 'minio',
                                ];
                            }
                        }
                    }
                }
            } catch (\Throwable $e) {
                log_message('warning', 'StorageService::listBackups MinIO list failed: ' . $e->getMessage());
            }
        }

        // 2. Local Disk Backups (for backwards compatibility / hybrid)
        $dir = WRITEPATH . 'backups';
        if (is_dir($dir)) {
            $files = @scandir($dir) ?: [];
            foreach ($files as $file) {
                if ($file === '.' || $file === '..' || $file === '.gitkeep') {
                    continue;
                }
                $filepath = $dir . DIRECTORY_SEPARATOR . $file;
                if (!is_file($filepath) || !preg_match('/\.(sql|sql\.gz|gz)$/i', $file)) {
                    continue;
                }
                if (!isset($seen[$file])) {
                    $size = (int) filesize($filepath);
                    $backups[] = [
                        'name'       => $file,
                        'size'       => $size,
                        'human_size' => $this->humanSize($size),
                        'modified'   => date('Y-m-d H:i:s', filemtime($filepath)),
                        'source'     => 'local',
                    ];
                }
            }
        }

        usort($backups, fn($a, $b) => strcmp($b['modified'], $a['modified']));
        return $backups;
    }

    /**
     * Format bytes to human readable size.
     */
    public function humanSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        for ($i = 0; $bytes >= 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }

    // ─────────────────────────────────────────────────────────────
    // Data Export Operations (Bucket: mpesa-exports)
    // ─────────────────────────────────────────────────────────────

    /**
     * Store an export file in mpesa-exports (automatically expired in 7 days by ILM).
     */
    public function putExport(string $filename, string $content): bool
    {
        $filename = basename($filename);
        if ($this->isMinio()) {
            try {
                $response = $this->s3Request('PUT', $this->config->bucketExports, $filename, $content, [
                    'Content-Type' => 'text/csv; charset=utf-8',
                ]);
                return $response !== null && ($response['statusCode'] >= 200 && $response['statusCode'] < 300);
            } catch (\Throwable $e) {
                log_message('warning', 'StorageService::putExport MinIO upload failed: ' . $e->getMessage());
            }
        }
        return true;
    }

    /**
     * Retrieve an export file from mpesa-exports.
     */
    public function getExport(string $filename): ?string
    {
        $filename = basename($filename);
        if ($this->isMinio()) {
            try {
                $response = $this->s3Request('GET', $this->config->bucketExports, $filename);
                if ($response !== null && $response['statusCode'] === 200) {
                    return $response['body'];
                }
            } catch (\Throwable $e) {
                log_message('warning', 'StorageService::getExport MinIO fetch failed: ' . $e->getMessage());
            }
        }
        return null;
    }

    // ─────────────────────────────────────────────────────────────
    // MinIO / S3 Native SigV4 Engine (Zero External Dependencies)
    // ─────────────────────────────────────────────────────────────

    /**
     * Execute an authenticated AWS SigV4 request against the S3/MinIO API.
     */
    public function s3Request(
        string $method,
        string $bucket,
        string $key = '',
        string $payload = '',
        array $headers = [],
        array $queryParams = []
    ): ?array {
        $endpoint = rtrim($this->config->endpoint, '/');
        $endpointParts = parse_url($endpoint);
        if (!$endpointParts || empty($endpointParts['host'])) {
            throw new RuntimeException("Invalid MinIO endpoint: {$endpoint}");
        }

        $scheme = $endpointParts['scheme'] ?? 'http';
        $hostHeader = $endpointParts['host'] . (isset($endpointParts['port']) ? ':' . $endpointParts['port'] : '');
        $pathPrefix = $endpointParts['path'] ?? '';

        $canonicalUri = rtrim($pathPrefix, '/') . '/' . rawurlencode($bucket);
        if ($key !== '') {
            $keySegments = explode('/', ltrim($key, '/'));
            $canonicalUri .= '/' . implode('/', array_map('rawurlencode', $keySegments));
        }

        // Canonical query parameters
        ksort($queryParams);
        $canonicalQueryPairs = [];
        foreach ($queryParams as $k => $v) {
            $canonicalQueryPairs[] = rawurlencode((string)$k) . '=' . rawurlencode((string)$v);
        }
        $canonicalQueryString = implode('&', $canonicalQueryPairs);

        $region = $this->config->region ?: 'us-east-1';
        $service = 's3';
        $amzDate = gmdate('Ymd\THis\Z');
        $dateStamp = gmdate('Ymd');
        $payloadHash = hash('sha256', $payload);

        // Build standard headers
        $requestHeaders = array_merge([
            'Host'                 => $hostHeader,
            'x-amz-date'           => $amzDate,
            'x-amz-content-sha256' => $payloadHash,
        ], $headers);

        // Canonical headers and signed headers
        $canonicalHeadersArray = [];
        foreach ($requestHeaders as $name => $val) {
            $canonicalHeadersArray[strtolower(trim($name))] = trim((string)$val);
        }
        ksort($canonicalHeadersArray);

        $canonicalHeadersStr = '';
        foreach ($canonicalHeadersArray as $name => $val) {
            $canonicalHeadersStr .= $name . ':' . preg_replace('/\s+/', ' ', $val) . "\n";
        }
        $signedHeadersStr = implode(';', array_keys($canonicalHeadersArray));

        // Canonical Request
        $canonicalRequest = strtoupper($method) . "\n"
            . $canonicalUri . "\n"
            . $canonicalQueryString . "\n"
            . $canonicalHeadersStr . "\n"
            . $signedHeadersStr . "\n"
            . $payloadHash;

        // String to sign
        $credentialScope = "{$dateStamp}/{$region}/{$service}/aws4_request";
        $stringToSign = "AWS4-HMAC-SHA256\n{$amzDate}\n{$credentialScope}\n" . hash('sha256', $canonicalRequest);

        // Signing key calculation
        $kDate    = hash_hmac('sha256', $dateStamp, 'AWS4' . $this->config->secretKey, true);
        $kRegion  = hash_hmac('sha256', $region, $kDate, true);
        $kService = hash_hmac('sha256', $service, $kRegion, true);
        $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
        $signature = hash_hmac('sha256', $stringToSign, $kSigning);

        $authorizationHeader = "AWS4-HMAC-SHA256 Credential={$this->config->accessKey}/{$credentialScope}, SignedHeaders={$signedHeadersStr}, Signature={$signature}";
        $requestHeaders['Authorization'] = $authorizationHeader;

        // Execute via cURL
        $url = "{$scheme}://{$hostHeader}{$canonicalUri}" . ($canonicalQueryString !== '' ? '?' . $canonicalQueryString : '');
        $ch = curl_init($url);

        $curlHeaders = [];
        foreach ($requestHeaders as $k => $v) {
            $curlHeaders[] = "{$k}: {$v}";
        }
        $curlHeaders[] = "Expect:";

        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $curlHeaders);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);

        if (strtoupper($method) === 'PUT') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        } elseif (strtoupper($method) === 'HEAD') {
            curl_setopt($ch, CURLOPT_NOBODY, true);
        }

        $rawResponse = curl_exec($ch);
        if ($rawResponse === false) {
            $err = curl_error($ch);
            curl_close($ch);
            log_message('error', "StorageService cURL failed [{$method} {$url}]: {$err}");
            return null;
        }

        $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        $headerText = substr($rawResponse, 0, $headerSize);
        $body = substr($rawResponse, $headerSize);

        return [
            'statusCode' => $statusCode,
            'body'       => $body,
            'headers'    => $this->parseHeaders($headerText),
        ];
    }

    /**
     * Generate an AWS SigV4 Presigned URL (GET).
     */
    public function generatePresignedUrl(string $bucket, string $key, int $expiresSeconds = 900): string
    {
        $endpoint = !empty($this->config->publicEndpoint) ? $this->config->publicEndpoint : $this->config->endpoint;
        $endpoint = rtrim($endpoint, '/');
        $endpointParts = parse_url($endpoint);
        if (!$endpointParts || empty($endpointParts['host'])) {
            return '';
        }

        $scheme = $endpointParts['scheme'] ?? 'http';
        $hostHeader = $endpointParts['host'] . (isset($endpointParts['port']) ? ':' . $endpointParts['port'] : '');
        $pathPrefix = $endpointParts['path'] ?? '';

        $canonicalUri = rtrim($pathPrefix, '/') . '/' . rawurlencode($bucket);
        if ($key !== '') {
            $keySegments = explode('/', ltrim($key, '/'));
            $canonicalUri .= '/' . implode('/', array_map('rawurlencode', $keySegments));
        }

        $region = $this->config->region ?: 'us-east-1';
        $service = 's3';
        $amzDate = gmdate('Ymd\THis\Z');
        $dateStamp = gmdate('Ymd');
        $credentialScope = "{$dateStamp}/{$region}/{$service}/aws4_request";

        $queryParams = [
            'X-Amz-Algorithm'     => 'AWS4-HMAC-SHA256',
            'X-Amz-Credential'    => "{$this->config->accessKey}/{$credentialScope}",
            'X-Amz-Date'          => $amzDate,
            'X-Amz-Expires'       => (string) $expiresSeconds,
            'X-Amz-SignedHeaders' => 'host',
        ];

        // Sort query params alphabetically
        ksort($queryParams);
        $canonicalQueryPairs = [];
        foreach ($queryParams as $k => $v) {
            $canonicalQueryPairs[] = rawurlencode($k) . '=' . rawurlencode($v);
        }
        $canonicalQueryString = implode('&', $canonicalQueryPairs);

        // Canonical Request for Presigned URL
        $canonicalHeaders = "host:{$hostHeader}\n";
        $signedHeaders = "host";
        $payloadHash = "UNSIGNED-PAYLOAD";

        $canonicalRequest = "GET\n"
            . $canonicalUri . "\n"
            . $canonicalQueryString . "\n"
            . $canonicalHeaders . "\n"
            . $signedHeaders . "\n"
            . $payloadHash;

        // String to sign
        $stringToSign = "AWS4-HMAC-SHA256\n{$amzDate}\n{$credentialScope}\n" . hash('sha256', $canonicalRequest);

        // Signing key calculation
        $kDate    = hash_hmac('sha256', $dateStamp, 'AWS4' . $this->config->secretKey, true);
        $kRegion  = hash_hmac('sha256', $region, $kDate, true);
        $kService = hash_hmac('sha256', $service, $kRegion, true);
        $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
        $signature = hash_hmac('sha256', $stringToSign, $kSigning);

        return "{$scheme}://{$hostHeader}{$canonicalUri}?{$canonicalQueryString}&X-Amz-Signature={$signature}";
    }

    /**
     * Test connectivity to the MinIO cluster.
     */
    public function testConnection(): array
    {
        if (!$this->isMinio()) {
            return [
                'success' => true,
                'driver'  => 'local',
                'message' => 'Local filesystem driver active (' . WRITEPATH . ')',
            ];
        }

        try {
            $resp = $this->s3Request('HEAD', $this->config->bucketPayloads);
            if ($resp !== null && ($resp['statusCode'] === 200 || $resp['statusCode'] === 404)) {
                return [
                    'success'  => true,
                    'driver'   => 'minio',
                    'endpoint' => $this->config->endpoint,
                    'message'  => 'MinIO cluster connection verified (Status: ' . $resp['statusCode'] . ')',
                ];
            }

            return [
                'success'  => false,
                'driver'   => 'minio',
                'endpoint' => $this->config->endpoint,
                'message'  => 'MinIO returned HTTP code ' . ($resp['statusCode'] ?? 'none'),
            ];
        } catch (\Throwable $e) {
            return [
                'success'  => false,
                'driver'   => 'minio',
                'endpoint' => $this->config->endpoint,
                'message'  => 'Connection error: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Ensure local directory exists.
     */
    protected function ensureDirectory(string $path): void
    {
        if (!is_dir($path)) {
            @mkdir($path, 0755, true);
        }
    }

    /**
     * Parse raw HTTP header string into associative array.
     */
    protected function parseHeaders(string $headerText): array
    {
        $headers = [];
        $lines = explode("\r\n", $headerText);
        foreach ($lines as $line) {
            if (strpos($line, ':') !== false) {
                [$key, $val] = explode(':', $line, 2);
                $headers[strtolower(trim($key))] = trim($val);
            }
        }
        return $headers;
    }
}
