<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use CodeIgniter\API\ResponseTrait;

class Migrate extends BaseController
{
    use ResponseTrait;

    public function index()
    {
        // Simple auth check - only allow with valid secret key
        $key = (string) $this->request->getGet('key');
        $validKey = (string) getenv('MIGRATE_KEY');

        if (empty($validKey) || !hash_equals($validKey, $key)) {
            return $this->respond(['status' => 'error', 'message' => 'Unauthorized or migration key not configured.'], 403);
        }

        try {
            $migrations = \Config\Services::migrations();
            
            // Get current status
            $current = $migrations->current();
            
            // Run migrations
            $result = $migrations->latest();
            
            $new = $migrations->current();
            
            return $this->respond([
                'status' => 'success',
                'message' => 'Migrations completed',
                'before' => $current,
                'after' => $new,
                'migrated' => $result,
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'Admin Migrate error: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            return $this->respond([
                'status' => 'error',
                'message' => 'Migration failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function status()
    {
        $key = (string) $this->request->getGet('key');
        $validKey = (string) getenv('MIGRATE_KEY');

        if (empty($validKey) || !hash_equals($validKey, $key)) {
            return $this->respond(['status' => 'error', 'message' => 'Unauthorized or migration key not configured.'], 403);
        }

        try {
            $migrations = \Config\Services::migrations();
            $current = $migrations->current();
            $files = $migrations->findMigrations();
            
            return $this->respond([
                'status' => 'success',
                'current_version' => $current,
                'available_migrations' => array_keys($files),
            ]);
        } catch (\Throwable $e) {
            return $this->respond([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}