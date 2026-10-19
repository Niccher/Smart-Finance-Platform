<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\Audit;
use CodeIgniter\API\ResponseTrait;

class Backup extends BaseController
{
    use ResponseTrait;

    public function index()
    {
        $dbInfo = $this->getDatabaseInfo();
        $tableStats = $this->getTableStats();
        $backupHistory = $this->getBackupHistory();

        return view('Admin/Backup/index', [
            'bg_color' => '#B1B8ED',
            'db_info' => $dbInfo,
            'table_stats' => $tableStats,
            'backup_history' => $backupHistory,
        ]);
    }

    public function download()
    {
        try {
            $structureOnly = (bool)$this->request->getGet('structure_only');
            $compress = (bool)$this->request->getGet('compress');

            $dump = $this->generateDump($structureOnly, []);
            
            $isGz = false;
            if ($compress && function_exists('gzencode')) {
                $compressedDump = gzencode($dump, 9);
                if ($compressedDump !== false) {
                    $dump = $compressedDump;
                    $isGz = true;
                }
            }

            $filename = $this->getBackupFilename($isGz);
            $filepath = WRITEPATH . 'backups/' . $filename;

            Audit::log('db_backup_download', 'system', 'Downloaded database backup', [
                'structure_only'  => $structureOnly,
                'compress'        => $isGz,
                'excluded_tables' => [],
                'size_bytes'      => strlen($dump),
            ]);

            // Save to backups directory for history and upload to MinIO/S3
            $backupDir = dirname($filepath);
            if (!is_dir($backupDir)) {
                mkdir($backupDir, 0775, true);
            }
            file_put_contents($filepath, $dump);

            try {
                service('storage')->putBackup($filename, $dump);
            } catch (\Throwable $stEx) {
                log_message('warning', 'Could not upload backup to MinIO: ' . $stEx->getMessage());
            }

            // Log to tbl_Backups
            try {
                $model = new \App\Models\BackupModel();
                $model->logBackup([
                    'filename'       => $filename,
                    'filepath'       => 'writable/backups/' . $filename,
                    'file_size'      => strlen($dump),
                    'structure_only' => $structureOnly ? 1 : 0,
                    'compressed'     => $isGz ? 1 : 0,
                    'type'           => 'manual',
                    'created_by'     => auth()->user()->username ?? 'admin',
                ]);
            } catch (\Throwable $e) {
                log_message('warning', 'Could not record backup in tbl_Backups: ' . $e->getMessage());
            }

            // Keep only last 20 backups
            $this->cleanOldBackups(20);

            return $this->response
                ->setHeader('Content-Type', $isGz ? 'application/gzip' : 'application/sql')
                ->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
                ->setHeader('Content-Length', (string)strlen($dump))
                ->setBody($dump);
        } catch (\Throwable $e) {
            log_message('error', 'DB backup failed: ' . $e->getMessage());
            return $this->respond(['status' => 'error', 'message' => 'Backup failed: ' . $e->getMessage()], 500);
        }
    }

    public function scheduled()
    {
        // Endpoint for cron job - saves to file
        $key = $this->request->getGet('key');
        $validKey = getenv('BACKUP_CRON_KEY') ?: 'changeme';

        if ($key !== $validKey) {
            return $this->respond(['status' => 'error', 'message' => 'Invalid key'], 403);
        }

        try {
            $dump = $this->generateDump(false, ['ci_sessions', 'tbl_Audit_Log', 'tbl_Cron_Log', 'tbl_Email_Log']);
            $filename = WRITEPATH . 'backups/backup_' . date('Y-m-d_H-i-s') . '.sql';

            if (!is_dir(dirname($filename))) {
                mkdir(dirname($filename), 0755, true);
            }

            file_put_contents($filename, $dump);

            try {
                service('storage')->putBackup(basename($filename), $dump);
            } catch (\Throwable $stEx) {
                log_message('warning', 'Could not upload scheduled backup to MinIO: ' . $stEx->getMessage());
            }

            // Keep only last 7 backups
            $this->cleanOldBackups(7);

            Audit::log('db_backup_scheduled', 'system', 'Scheduled database backup created', [
                'file' => basename($filename),
                'size_bytes' => strlen($dump),
            ]);

            return $this->respond([
                'status' => 'success',
                'message' => 'Backup saved to ' . basename($filename),
                'file' => basename($filename),
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'Scheduled backup failed: ' . $e->getMessage());
            return $this->respond(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    public function delete()
    {
        $file = $this->request->getPost('file');
        if (empty($file)) {
            return $this->respond(['status' => 'error', 'message' => 'File not specified'], 400);
        }

        $file = basename($file);
        $path = WRITEPATH . 'backups' . DIRECTORY_SEPARATOR . $file;
        
        $storage = service('storage');
        $deleted = false;

        if (is_file($path)) {
            $deleted = @unlink($path);
        }

        try {
            $storage->deleteBackup($file);
            $deleted = true;
        } catch (\Throwable $e) {
            // MinIO deletion failure or not found
        }

        if ($deleted) {
            Audit::log('db_backup_delete', 'system', 'Deleted backup file', ['file' => $file]);
            return $this->respond(['status' => 'success', 'message' => 'Backup deleted']);
        }

        return $this->respond(['status' => 'error', 'message' => 'Failed to delete file'], 500);
    }

    public function downloadFile(string $file = '')
    {
        if ($file === '') {
            return $this->respond(['status' => 'error', 'message' => 'File not specified'], 400);
        }

        $file = basename($file);
        $path = WRITEPATH . 'backups' . DIRECTORY_SEPARATOR . $file;
        $storage = service('storage');
        $content = null;

        if (is_file($path)) {
            $content = file_get_contents($path);
        } else {
            try {
                $content = $storage->getBackup($file);
            } catch (\Throwable $e) {
                return $this->respond(['status' => 'error', 'message' => 'File not found'], 404);
            }
        }

        if ($content === null || $content === false) {
            return $this->respond(['status' => 'error', 'message' => 'File not found'], 404);
        }

        $isGz = str_ends_with(strtolower($file), '.gz');
        $contentType = $isGz ? 'application/gzip' : 'application/sql';

        return $this->response
            ->setHeader('Content-Type', $contentType)
            ->setHeader('Content-Disposition', 'attachment; filename="' . $file . '"')
            ->setHeader('Content-Length', (string)strlen($content))
            ->setBody($content);
    }

    private function generateDump(bool $structureOnly, array $excludeTables): string
    {
        $db = \Config\Database::connect();
        
        // Use PHP-based dump as primary (works without external tools)
        return $this->phpDump($db, $structureOnly, $excludeTables);
    }

    // Kept for reference - external tool dumps (require mysqldump/pg_dump/sqlite3 in PATH)
    private function mysqldump(\CodeIgniter\Database\BaseConnection $db, bool $structureOnly, array $excludeTables): string
    {
        $config = [
            'host' => $db->hostname,
            'user' => $db->username,
            'pass' => $db->password,
            'name' => $db->database,
            'port' => $db->port ?? 3306,
        ];

        $exclude = implode(' ', array_map(fn($t) => '--ignore-table=' . $config['name'] . '.' . $t, $excludeTables));
        $structureFlag = $structureOnly ? '--no-data' : '';

        $cmd = sprintf(
            'mysqldump --single-transaction --quick --skip-lock-tables %s %s -h%s -u%s -p%s -P%d %s',
            $structureFlag,
            $exclude,
            escapeshellarg($config['host']),
            escapeshellarg($config['user']),
            escapeshellarg($config['pass']),
            $config['port'],
            escapeshellarg($config['name'])
        );

        $output = [];
        $returnVar = 0;
        exec($cmd, $output, $returnVar);

        if ($returnVar !== 0) {
            // Fallback to PHP-based dump
            return $this->phpDump($db, $structureOnly, $excludeTables);
        }

        return implode("\n", $output);
    }

    private function pgdump(\CodeIgniter\Database\BaseConnection $db, bool $structureOnly, array $excludeTables): string
    {
        $config = [
            'host' => $db->hostname,
            'user' => $db->username,
            'pass' => $db->password,
            'name' => $db->database,
            'port' => $db->port ?? 5432,
        ];

        $exclude = implode(' ', array_map(fn($t) => '--exclude-table=' . $t, $excludeTables));
        $structureFlag = $structureOnly ? '--schema-only' : '';

        putenv('PGPASSWORD=' . $config['pass']);
        $cmd = sprintf(
            'pg_dump %s %s -h%s -U%s -p%d %s',
            $structureFlag,
            $exclude,
            escapeshellarg($config['host']),
            escapeshellarg($config['user']),
            $config['port'],
            escapeshellarg($config['name'])
        );

        $output = [];
        $returnVar = 0;
        exec($cmd, $output, $returnVar);

        if ($returnVar !== 0) {
            return $this->phpDump($db, $structureOnly, $excludeTables);
        }

        return implode("\n", $output);
    }

    private function sqlitedump(\CodeIgniter\Database\BaseConnection $db, bool $structureOnly, array $excludeTables): string
    {
        $path = $db->database;
        $exclude = implode(' ', array_map(fn($t) => '-x ' . $t, $excludeTables));
        $structureFlag = $structureOnly ? '--schema' : '';

        $cmd = sprintf(
            'sqlite3 %s .dump %s %s',
            escapeshellarg($path),
            $structureFlag,
            $exclude
        );

        $output = [];
        $returnVar = 0;
        exec($cmd, $output, $returnVar);

        if ($returnVar !== 0) {
            return $this->phpDump($db, $structureOnly, $excludeTables);
        }

        return implode("\n", $output);
    }

    private function phpDump(\CodeIgniter\Database\BaseConnection $db, bool $structureOnly, array $excludeTables): string
    {
        $tables = $db->listTables();
        $tables = array_diff($tables, $excludeTables);

        $output = [];
        $output[] = '-- Database backup generated at ' . gmdate('Y-m-d H:i:s') . ' UTC';
        $output[] = '-- Host: ' . $db->hostname;
        $output[] = '-- Database: ' . $db->database;
        $output[] = '';

        foreach ($tables as $table) {
            $create = $db->query("SHOW CREATE TABLE `" . $table . "`");
            if ($create) {
                $row = $create->getRow();
                $output[] = $row->{'Create Table'} . ';';
                $output[] = '';
            }

            if (!$structureOnly) {
                $rows = $db->table($table)->get()->getResultArray();
                if (!empty($rows)) {
                    $columns = array_keys($rows[0]);
                    $colList = '`' . implode('`, `', $columns) . '`';

                    $values = [];
                    foreach ($rows as $row) {
                        $escaped = array_map(fn($v) => $db->escape($v), array_values($row));
                        $values[] = '(' . implode(', ', $escaped) . ')';
                    }

                    $output[] = 'INSERT INTO `' . $table . '` (' . $colList . ') VALUES';
                    $output[] = implode(",\n", $values) . ';';
                    $output[] = '';
                }
            }
        }

        return implode("\n", $output);
    }

    private function getBackupFilename(bool $compress): string
    {
        $date = date('Y-m-d_H-i-s');
        $ext = $compress ? '.sql.gz' : '.sql';
        return 'backup_' . $date . $ext;
    }

    private function cleanOldBackups(int $keep = 20): void
    {
        $dir = WRITEPATH . 'backups';
        if (!is_dir($dir)) return;

        $allFiles = scandir($dir) ?: [];
        $files = [];
        foreach ($allFiles as $f) {
            $path = $dir . DIRECTORY_SEPARATOR . $f;
            if (is_file($path) && preg_match('/\.(sql|sql\.gz|gz)$/i', $f)) {
                $files[] = $path;
            }
        }

        usort($files, fn($a, $b) => filemtime($b) - filemtime($a));

        foreach (array_slice($files, $keep) as $file) {
            @unlink($file);
        }
    }

    private function getDatabaseInfo(): array
    {
        $db = \Config\Database::connect();
        $version = '';
        try {
            $result = $db->query("SELECT VERSION() as ver");
            $row = $result->getRow();
            $version = $row->ver ?? '';
        } catch (\Throwable $e) {
            $version = 'Unknown';
        }

        return [
            'driver' => $db->DBDriver,
            'host' => $db->hostname,
            'database' => $db->database,
            'username' => $db->username,
            'version' => $version,
        ];
    }

    private function getTableStats(): array
    {
        $db = \Config\Database::connect();
        $tables = $db->listTables();
        
        $stats = [
            'total_tables' => count($tables),
            'total_rows' => 0,
            'total_size' => 0,
            'tables' => [],
        ];

        foreach ($tables as $table) {
            try {
                // Get row count
                $countResult = $db->table($table)->countAllResults(false);
                $rowCount = (int)$countResult;

                // Get table size (MySQL specific)
                $sizeResult = $db->query("
                    SELECT 
                        ROUND(((data_length + index_length) / 1024 / 1024), 2) as size_mb
                    FROM information_schema.TABLES 
                    WHERE table_schema = DATABASE() AND table_name = ?
                ", [$table]);
                $sizeRow = $sizeResult->getRow();
                $sizeMb = $sizeRow ? (float)$sizeRow->size_mb : 0;

                $stats['total_rows'] += $rowCount;
                $stats['total_size'] += $sizeMb;

                $stats['tables'][] = [
                    'name' => $table,
                    'rows' => $rowCount,
                    'size_mb' => $sizeMb,
                    'size_human' => $this->humanSize((int)($sizeMb * 1024 * 1024)),
                ];
            } catch (\Throwable $e) {
                $stats['tables'][] = [
                    'name' => $table,
                    'rows' => 0,
                    'size_mb' => 0,
                    'size_human' => 'Unknown',
                ];
            }
        }

        $stats['total_size_human'] = $this->humanSize((int)($stats['total_size'] * 1024 * 1024));
        
        return $stats;
    }

    private function getBackupHistory(): array
    {
        $dir = WRITEPATH . 'backups';
        if (!is_dir($dir)) return [];

        $allFiles = scandir($dir);
        if ($allFiles === false) return [];

        $history = [];
        foreach ($allFiles as $file) {
            if ($file === '.' || $file === '..' || $file === '.gitkeep') {
                continue;
            }

            $filepath = $dir . DIRECTORY_SEPARATOR . $file;
            if (!is_file($filepath)) {
                continue;
            }

            // Match any database backup file (.sql, .sql.gz, or legacy .gz)
            if (!preg_match('/\.(sql|sql\.gz|gz)$/i', $file)) {
                continue;
            }

            $history[] = [
                'name'       => $file,
                'size'       => filesize($filepath),
                'human_size' => $this->humanSize(filesize($filepath)),
                'modified'   => date('Y-m-d H:i:s', filemtime($filepath)),
            ];
        }

        usort($history, fn($a, $b) => strcmp($b['modified'], $a['modified']));
        return $history;
    }

    private function humanSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        for ($i = 0; $bytes >= 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }
}