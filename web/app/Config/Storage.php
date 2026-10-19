<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class Storage extends BaseConfig
{
    /**
     * Storage driver: 'minio' or 'local'
     */
    public string $driver = 'minio';

    /**
     * S3 / MinIO API Endpoint (e.g. http://minio:9000)
     */
    public string $endpoint = 'http://minio:9000';

    /**
     * Root User / Access Key
     */
    public string $accessKey = 'admin_mpesa';

    /**
     * Root Password / Secret Key
     */
    public string $secretKey = 'change_this_minio_password_123!';

    /**
     * S3 Region
     */
    public string $region = 'us-east-1';

    /**
     * Bucket Names
     */
    public string $bucketPayloads = 'mpesa-payloads';
    public string $bucketBackups  = 'mpesa-backups';
    public string $bucketModels   = 'mpesa-models';
    public string $bucketExports  = 'mpesa-exports';

    public function __construct()
    {
        parent::__construct();

        $this->driver         = (string) env('STORAGE_DRIVER', $this->driver);
        $this->endpoint       = rtrim((string) env('MINIO_ENDPOINT', $this->endpoint), '/');
        $this->accessKey      = (string) env('MINIO_ROOT_USER', $this->accessKey);
        $this->secretKey      = (string) env('MINIO_ROOT_PASSWORD', $this->secretKey);
        $this->region         = (string) env('MINIO_REGION', $this->region);
        $this->bucketPayloads = (string) env('MINIO_BUCKET_PAYLOADS', $this->bucketPayloads);
        $this->bucketBackups  = (string) env('MINIO_BUCKET_BACKUPS', $this->bucketBackups);
        $this->bucketModels   = (string) env('MINIO_BUCKET_MODELS', $this->bucketModels);
        $this->bucketExports  = (string) env('MINIO_BUCKET_EXPORTS', $this->bucketExports);
    }
}
