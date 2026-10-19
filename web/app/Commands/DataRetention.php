<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Purges uploaded data (SMS files + database rows) whose upload is older
 * than the configured retention window (default 365 days).
 *
 * The retention period is stored in tbl_Settings under the key
 * `data_retention_days` (admin-configurable from the Maintenance page).
 *
 * Usage: php spark data:retention
 */
class DataRetention extends BaseCommand
{
    protected $group       = 'Maintenance';
    protected $name        = 'data:retention';
    protected $description = 'Delete uploaded files and database rows older than the configured retention period (default 365 days).';

    public function run(array $params)
    {
        $db = \Config\Database::connect();

        $days = $this->retentionDays($db);
        if ($days <= 0) {
            CLI::write('Data retention is disabled (data_retention_days <= 0). Nothing to do.', 'yellow');
            return;
        }

        $cutoff = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        CLI::write("Retention: {$days} days (cutoff {$cutoff}).", 'green');

        if (!$db->tableExists('tbl_Loot')) {
            CLI::write('tbl_Loot does not exist. Nothing to purge.', 'yellow');
            return;
        }

        $uploads = $db->table('tbl_Loot')
            ->select('loot_Uuid, loot_Name, loot_Owner, loot_Created')
            ->where('loot_Created <', $cutoff)
            ->where('loot_Created IS NOT NULL')
            ->get()
            ->getResultArray();

        if (empty($uploads)) {
            CLI::write('No uploads older than the retention window found.', 'green');
            return;
        }

        $uploadDir = WRITEPATH . 'uploads/payloads/';
        $stats = [
            'uploads'    => 0,
            'files'      => 0,
            'sms'        => 0,
            'orphan_dirs'=> 0,
        ];
        $purgedOwners = [];

        foreach ($uploads as $upload) {
            $uuid = $upload['loot_Uuid'];
            $name = $upload['loot_Name'];
            $owner = $upload['loot_Owner'];

            $stats['uploads']++;
            if (!empty($owner)) {
                $purgedOwners[$owner] = true;
            }

            // ── Gather SMS ids linked to this upload ────────────
            $smsIds = [];
            $smsRows = $db->table('tbl_Sms')
                ->select('id')
                ->where('sms_loot_source', $uuid)
                ->get()
                ->getResult();
            foreach ($smsRows as $r) {
                $smsIds[] = $r->id;
            }

            $db->transStart();

            if (!empty($smsIds)) {
                if ($db->tableExists('tbl_Sms_Processing')) {
                    $db->table('tbl_Sms_Processing')->whereIn('sms_id', $smsIds)->delete();
                }
                // tbl_Sms_Classification & tbl_Analyzed_Transactions are now
                // views derived from tbl_Sms — deleting the SMS row removes them.
                $db->table('tbl_Sms')->whereIn('id', $smsIds)->delete();
                $stats['sms'] += count($smsIds);
            }

            // ── Upload summary + loot row ───────────────────────
            if ($db->tableExists('tbl_Loot_Summary')) {
                $db->table('tbl_Loot_Summary')->where('loot_Uuid', $uuid)->delete();
            }
            $db->table('tbl_Loot')->where('loot_Uuid', $uuid)->delete();

            $db->transComplete();

            // ── Delete the physical upload file ────────────────
            if (!empty($name)) {
                try {
                    service('storage')->deletePayload($name);
                } catch (\Throwable $e) {}
                if (is_file($uploadDir . $name)) {
                    if (@unlink($uploadDir . $name)) {
                        $stats['files']++;
                    }
                }
            }

            CLI::write("Purged upload {$name} (uuid {$uuid}).", 'green');
        }

        // ── Clean up sender profiles that no longer have any data ──
        $cleanedProfiles = 0;
        if ($db->tableExists('tbl_Sender_Profiles') && !empty($purgedOwners)) {
            $remaining = [];
            $remainingRows = $db->table('tbl_Loot')
                ->select('loot_Owner')
                ->whereIn('loot_Owner', array_keys($purgedOwners))
                ->get()
                ->getResult();
            foreach ($remainingRows as $r) {
                $remaining[$r->loot_Owner] = true;
            }

            foreach (array_keys($purgedOwners) as $owner) {
                if (isset($remaining[$owner])) {
                    continue;
                }
                $cleanedProfiles += $db->table('tbl_Sender_Profiles')
                    ->where('sp_owner', $owner)
                    ->delete();
            }
        }

        CLI::write(sprintf(
            'Retention purge complete. Uploads: %d, files: %d, SMS rows: %d, orphaned sender profiles: %d.',
            $stats['uploads'],
            $stats['files'],
            $stats['sms'],
            $cleanedProfiles
        ), 'green');
    }

    private function retentionDays($db): int
    {
        $row = $db->table('tbl_Settings')
            ->where('`key`', 'data_retention_days')
            ->get()
            ->getRow();

        if (!$row) {
            return 365;
        }

        return (int) $row->value;
    }
}
