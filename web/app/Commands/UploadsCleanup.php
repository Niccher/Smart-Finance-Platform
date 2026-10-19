<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class UploadsCleanup extends BaseCommand
{
    protected $group       = 'Maintenance';
    protected $name        = 'uploads:cleanup';
    protected $description = 'Remove orphaned upload files and old loot records no longer referenced.';

    public function run(array $params)
    {
        $db = \Config\Database::connect();
        $uploadDir = WRITEPATH . 'uploads/payloads/';

        $orphaned = 0;
        if (is_dir($uploadDir)) {
            $files = array_diff(scandir($uploadDir), ['.', '..']);

            foreach ($files as $file) {
                $row = null;
                if ($db->tableExists('tbl_Loot')) {
                    $row = $db->table('tbl_Loot')->where('loot_Name', $file)->get()->getRow();
                }

                if ($row === null) {
                    $age = time() - filemtime($uploadDir . $file);
                    // Only delete files older than 1 day to avoid racing an active upload
                    if ($age > 86400) {
                        try {
                            service('storage')->deletePayload($file);
                        } catch (\Throwable $e) {}
                        if (is_file($uploadDir . $file)) {
                            @unlink($uploadDir . $file);
                        }
                        CLI::write('Removed orphaned file: ' . $file, 'yellow');
                        $orphaned++;
                    }
                }
            }
        }

        // Remove stale processing jobs older than 30 days
        if ($db->tableExists('tbl_Processing_Jobs')) {
            $cutoff = date('Y-m-d H:i:s', strtotime('-30 days'));
            $removed = $db->table('tbl_Processing_Jobs')->where('created_at <', $cutoff)->delete();
            CLI::write('Removed ' . $removed . ' stale processing jobs.', 'green');
        }

        CLI::write('Upload cleanup complete. Removed ' . $orphaned . ' orphaned files.', 'green');
    }
}
