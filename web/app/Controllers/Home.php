<?php

namespace App\Controllers;

use CodeIgniter\API\ResponseTrait;

class Home extends BaseController
{
    use ResponseTrait;
    public function index()
    {
        return view('landing');
    }

    public function androidApp()
    {
        return view('android_app');
    }

    public function mlBackend()
    {
        return view('ml_backend');
    }

    public function setup()
    {
        return view('setup');
    }

    public function faq()
    {
        return view('faq');
    }

    public function health()
    {
        $db = \Config\Database::connect();
        $dbStatus = 'ok';
        try {
            $db->initialize();
            $db->simpleQuery('SELECT 1');
        } catch (\Throwable $e) {
            $dbStatus = 'error: ' . $e->getMessage();
        }

        $redisHost = env('REDIS_HOST') ?: getenv('REDIS_HOST');
        $redisPort = (int) (env('REDIS_PORT') ?: getenv('REDIS_PORT') ?: 6379);
        $redisAlive = \Config\Session::isRedisAlive($redisHost ?: '127.0.0.1', $redisPort);

        $sessionConfig = config('Session');
        $cacheConfig   = config('Cache');

        $version = '3.5.0';
        $jsonPath = APPPATH . 'Config/version.json';
        if (file_exists($jsonPath)) {
            $versionData = json_decode(file_get_contents($jsonPath), true);
            $version = $versionData['version'] ?? '3.5.0';
        }

        $storageService = service('storage');
        $storageStatus  = $storageService->testConnection();

        $data = [
            'status'         => $dbStatus === 'ok' ? 'ok' : 'degraded',
            'database'       => $dbStatus,
            'redis_status'   => $redisAlive ? 'connected' : ($redisHost ? 'fallback_active' : 'not_configured'),
            'storage_engine' => config('Storage')->driver,
            'storage_status' => !empty($storageStatus['success']) ? 'connected' : 'degraded',
            'session_engine' => ($sessionConfig->driver ?? '') === \CodeIgniter\Session\Handlers\RedisHandler::class ? 'redis' : 'mysql',
            'cache_engine'   => ($cacheConfig->handler ?? '') === 'redis' ? 'redis' : 'file',
            'resilience'     => [
                'mode'                => $redisAlive ? 'in_memory_primary' : 'persistent_fallback',
                'failover_configured' => true,
                'storage_fallback'    => 'local_filesystem_hybrid',
            ],
            'timestamp'      => date('c'),
            'app'            => 'Smart Finance Platform',
            'version'        => $version,
        ];

        return $this->response->setJSON($data);
    }

    public function systemVersion()
    {
        $jsonPath = APPPATH . 'Config/version.json';
        if (!file_exists($jsonPath)) {
            return $this->response->setJSON([
                'version' => '3.5.0',
                'github_url' => 'https://github.com/Niccher/Smart-Finance-Platform',
                'changelog' => [],
                'releases'  => []
            ]);
        }
        $data = json_decode(file_get_contents($jsonPath), true);
        return $this->response->setJSON($data);
    }

    public function rescan()
    {
        $userId = auth()->user()->id;
        $mlBase = rtrim((string) config('MlBackend')->baseUrl, '/');
        $fastApiUrl = $mlBase . '/process/for-user/' . $userId;

        // Fire-and-forget: PHP returns 'started' immediately.
        // The ML service job runs asynchronously; the client polls /progress.
        $ch = curl_init($fastApiUrl);
        curl_setopt($ch, CURLOPT_POST,           true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
        curl_setopt($ch, CURLOPT_TIMEOUT,        5);
        curl_setopt($ch, CURLOPT_POSTFIELDS,     '{}');
        curl_setopt($ch, CURLOPT_HTTPHEADER,     ['Content-Type: application/json']);
        @curl_exec($ch);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($curlErr && strpos($curlErr, 'timed out') === false) {
            log_message('error', 'Rescan failed: ' . $curlErr);
            return $this->response->setJSON([
                'status'  => 'error',
                'message' => 'Could not reach the analysis service. Please try again later.',
            ]);
        }

        return $this->response->setJSON([
            'status'  => 'started',
            'message' => 'LLM analysis has been queued. Processing will run in the background.',
        ]);
    }



    public function progress()
    {
        $userId = auth()->user()->id;
        $db = \Config\Database::connect();
        $tokenType = \CodeIgniter\Shield\Authentication\Authenticators\AccessTokens::ID_TYPE_ACCESS_TOKEN;

        $rawTokens = [];
        $tokenRows = $db->query("
            SELECT DISTINCT s.sms_owner AS tk FROM tbl_Sms s
            WHERE s.sms_user_id = ? AND s.sms_owner IS NOT NULL AND s.sms_owner != ''
            UNION
            SELECT DISTINCT l.loot_Owner AS tk FROM tbl_Loot l
            WHERE l.loot_user_id = ? AND l.loot_Owner IS NOT NULL AND l.loot_Owner != ''
            UNION
            SELECT DISTINCT s.sms_owner AS tk FROM tbl_Sms s
            INNER JOIN auth_identities i ON i.secret = SHA2(s.sms_owner, 256)
            WHERE i.user_id = ? AND i.type = ?
            UNION
            SELECT DISTINCT l.loot_Owner AS tk FROM tbl_Loot l
            INNER JOIN auth_identities i ON i.secret = SHA2(l.loot_Owner, 256)
            WHERE i.user_id = ? AND i.type = ?
        ", [$userId, $userId, $userId, $tokenType, $userId, $tokenType])->getResult();

        foreach ($tokenRows as $r) {
            if (!empty($r->tk)) $rawTokens[] = $r->tk;
        }

        $total = 0;
        $statuses = [];
        $job = null;
        $llmClassified = 0;

        $totalSenders = 0;
        $financeSenders = 0;
        $financeSms = 0;
        $uniqueSendersCount = 0;

        // Count all SMS belonging to the user either by owner token or user_id
        $smsBase = $db->table('tbl_Sms');
        $smsBase->groupStart();
        if (!empty($rawTokens)) {
            $smsBase->whereIn('sms_owner', $rawTokens)->orWhere('sms_user_id', $userId);
        } else {
            $smsBase->where('sms_user_id', $userId);
        }
        $smsBase->groupEnd();
        $total = $smsBase->countAllResults();

        if ($total > 0 || !empty($rawTokens)) {
            // Total unique senders in the inbox data
            $sendersQuery = $db->table('tbl_Sms')
                ->select('COUNT(DISTINCT sms_number) as cnt');
            $sendersQuery->groupStart();
            if (!empty($rawTokens)) {
                $sendersQuery->whereIn('sms_owner', $rawTokens)->orWhere('sms_user_id', $userId);
            } else {
                $sendersQuery->where('sms_user_id', $userId);
            }
            $sendersQuery->groupEnd();
            $uniqueSendersCount = (int)($sendersQuery->get()->getRow()->cnt ?? 0);

            if ($db->tableExists('tbl_Sender_Profiles') && !empty($rawTokens)) {
                $totalSenders = $db->table('tbl_Sender_Profiles')
                    ->whereIn('sp_owner', $rawTokens)
                    ->countAllResults();

                $financeSenders = $db->table('tbl_Sender_Profiles')
                    ->whereIn('sp_owner', $rawTokens)
                    ->where('sp_is_finance', 1)
                    ->countAllResults();
            }

            if ($db->tableExists('tbl_Sms')) {
                $finQuery = $db->table('tbl_Sms');
                $finQuery->groupStart();
                if (!empty($rawTokens)) {
                    $finQuery->whereIn('sms_owner', $rawTokens)->orWhere('sms_user_id', $userId);
                } else {
                    $finQuery->where('sms_user_id', $userId);
                }
                $finQuery->groupEnd();
                $financeSms = $finQuery->where('sms_is_finance', 1)->countAllResults();
            }

            // Count SMS classified by the LLM (method = 'llm')
            if ($db->tableExists('tbl_Sms_Classification')) {
                $llmQ = $db->table('tbl_Sms_Classification sc')
                    ->select('COUNT(*) as cnt')
                    ->join('tbl_Sms s', 's.id = sc.sms_id');
                $llmQ->groupStart();
                if (!empty($rawTokens)) {
                    $llmQ->whereIn('s.sms_owner', $rawTokens)->orWhere('s.sms_user_id', $userId);
                } else {
                    $llmQ->where('s.sms_user_id', $userId);
                }
                $llmQ->groupEnd();
                $llmRow = $llmQ->where('sc.method', 'llm')->get()->getRow();
                $llmClassified = (int)($llmRow->cnt ?? 0);
            }

            // Count per-SMS processing status (populated by FastAPI on some setups)
            if ($db->tableExists('tbl_Sms_Processing')) {
                $spQ = $db->table('tbl_Sms_Processing sp')
                    ->select('sp.status, COUNT(*) as cnt')
                    ->join('tbl_Sms s', 's.id = sp.sms_id');
                $spQ->groupStart();
                if (!empty($rawTokens)) {
                    $spQ->whereIn('s.sms_owner', $rawTokens)->orWhere('s.sms_user_id', $userId);
                } else {
                    $spQ->where('s.sms_user_id', $userId);
                }
                $spQ->groupEnd();
                $statusRows = $spQ->groupBy('sp.status')->get()->getResult();
                foreach ($statusRows as $r) {
                    $statuses[$r->status] = (int)$r->cnt;
                }
            }
        }

        if ($db->tableExists('tbl_Processing_Jobs')) {
            $job = $db->table('tbl_Processing_Jobs')
                ->where('user_id', (string)$userId)
                ->orderBy('id', 'DESC')
                ->limit(1)
                ->get()
                ->getRowArray();
        }

        // Use the better of the two progress signals, including skipped messages
        $processingDone = ($statuses['done'] ?? 0) + ($statuses['completed'] ?? 0) + ($statuses['error'] ?? 0) + ($statuses['skipped'] ?? 0);
        $processed = max($processingDone, $llmClassified);

        // Determine if a scan is actively running
        $hasProcessingRows = ($statuses['processing'] ?? 0) > 0;
        $activeStatuses    = ['queued', 'processing', 'starting', 'already_running'];
        $terminalStatuses  = ['done', 'error', 'failed', 'cancelled', 'disabled'];
        $jobActive = $job && in_array($job['status'], $activeStatuses, true);

        $running = ($hasProcessingRows || $jobActive) && ($total > 0 && $processed < $total);

        // Derive a clean UI status:
        // - If something is actively running → use the live job status
        // - If the last job finished cleanly → 'done'
        // - If nothing is running and last job was cancelled/failed/errored → 'idle'
        //   (avoids showing a stale terminal failure as the current state)
        // - No job at all → 'idle'
        if ($running) {
            $uiStatus = $job['status'] ?? 'processing';
        } elseif ($job && in_array($job['status'], ['done'], true)) {
            $uiStatus = 'done';
        } elseif ($job && in_array($job['status'], $terminalStatuses, true)) {
            $uiStatus = 'idle';  // stale failure — nothing actively broken right now
        } else {
            $uiStatus = 'idle';
        }

        return $this->response->setJSON([
            'status'            => $uiStatus,
            'total'             => $total,
            'processed'       => $processed,
            'llm_classified'  => $llmClassified,
            'completed'       => (int)($statuses['done'] ?? $statuses['completed'] ?? 0),
            'skipped'         => (int)($statuses['skipped'] ?? 0),
            'errors'          => (int)($statuses['error'] ?? 0) + (int)($job['errors'] ?? 0),
            'pending'         => (int)($statuses['pending'] ?? 0),
            'total_senders'     => $uniqueSendersCount,
            'processed_senders' => $totalSenders,
            'finance_senders'   => $financeSenders,
            'bad_senders'       => $totalSenders - $financeSenders,
            'finance_sms'     => $financeSms,
            'running'         => $running,
            'job'             => $job,
        ]);
    }


    /**
     * Full reprocess: resets all processing flags and re-triggers LLM.
     * This clears tbl_Sms_Processing, tbl_Analyzed_Transactions,
     * tbl_Sms_Classification (llm-method), and tbl_Sender_Profiles
     * for the current user's tokens, so the LLM re-classifies everything from scratch.
     */
    public function rescanAll()
    {
        $userId = auth()->user()->id;
        $db = \Config\Database::connect();
        $tokenType = \CodeIgniter\Shield\Authentication\Authenticators\AccessTokens::ID_TYPE_ACCESS_TOKEN;

        // Discover raw tokens via user ID or SHA2 match
        $rawTokens = [];
        $tokenRows = $db->query("
            SELECT DISTINCT s.sms_owner AS tk FROM tbl_Sms s
            WHERE s.sms_user_id = ? AND s.sms_owner IS NOT NULL AND s.sms_owner != ''
            UNION
            SELECT DISTINCT l.loot_Owner AS tk FROM tbl_Loot l
            WHERE l.loot_user_id = ? AND l.loot_Owner IS NOT NULL AND l.loot_Owner != ''
            UNION
            SELECT DISTINCT s.sms_owner AS tk FROM tbl_Sms s
            INNER JOIN auth_identities i ON i.secret = SHA2(s.sms_owner, 256)
            WHERE i.user_id = ? AND i.type = ?
            UNION
            SELECT DISTINCT l.loot_Owner AS tk FROM tbl_Loot l
            INNER JOIN auth_identities i ON i.secret = SHA2(l.loot_Owner, 256)
            WHERE i.user_id = ? AND i.type = ?
        ", [$userId, $userId, $userId, $tokenType, $userId, $tokenType])->getResult();

        foreach ($tokenRows as $r) {
            if (!empty($r->tk)) $rawTokens[] = $r->tk;
        }

        $smsIds = [];
        $smsQuery = $db->table('tbl_Sms')->select('id');
        $smsQuery->groupStart();
        if (!empty($rawTokens)) {
            $smsQuery->whereIn('sms_owner', $rawTokens)->orWhere('sms_user_id', $userId);
        } else {
            $smsQuery->where('sms_user_id', $userId);
        }
        $smsQuery->groupEnd();
        $smsRows = $smsQuery->get()->getResult();
        foreach ($smsRows as $r) $smsIds[] = $r->id;

        if (empty($smsIds) && empty($rawTokens)) {
            return $this->response->setJSON([
                'status'  => 'error',
                'message' => 'No uploaded data found for this account.',
            ]);
        }

        $db->transStart();

        // Reset processing tracking
        if (!empty($smsIds) && $db->tableExists('tbl_Sms_Processing')) {
            $db->table('tbl_Sms_Processing')->whereIn('sms_id', $smsIds)->delete();
        }

        // Reset LLM classifications (keep upload-method ones as fallback).
        // Classification now lives on tbl_Sms, so reset the LLM-derived columns.
        if (!empty($smsIds)) {
            $db->table('tbl_Sms')
               ->whereIn('id', $smsIds)
               ->where('sms_method', 'llm')
               ->update([
                   'sms_category'    => 'Unclassified',
                   'sms_is_finance'  => 0,
                   'sms_confidence'  => null,
                   'sms_method'      => null,
               ]);
        }

        // Reset analyzed transactions (they are derived from tbl_Sms)
        if (!empty($smsIds) && $db->tableExists('tbl_Sms')) {
            $db->table('tbl_Sms')
                ->whereIn('id', $smsIds)
                ->set('sms_is_transactional', 0)
                ->set('sms_amount', null)
                ->set('sms_balance', null)
                ->set('sms_counterparty', null)
                ->set('sms_transaction_type', null)
                ->set('sms_trans_date', null)
                ->update();
        }

        // Remove sender profiles
        if ($db->tableExists('tbl_Sender_Profiles')) {
            $spQuery = $db->table('tbl_Sender_Profiles');
            if (!empty($rawTokens)) {
                $spQuery->whereIn('sp_owner', $rawTokens)->delete();
            }
        }

        $db->transComplete();

        // Trigger the LLM pipeline — fire-and-forget so PHP returns
        // immediately rather than blocking until the job finishes.
        // The ML service runs the job asynchronously; the client polls
        // /dashboard/rescan/progress to track progress.
        $mlBase = rtrim((string) config('MlBackend')->baseUrl, '/');
        $fastApiUrl = $mlBase . '/process/for-user/' . $userId;

        $ch = curl_init($fastApiUrl);
        curl_setopt($ch, CURLOPT_POST,           true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);   // wait up to 3s to connect
        curl_setopt($ch, CURLOPT_TIMEOUT,        5);   // read up to 5s then give up — job keeps running server-side
        curl_setopt($ch, CURLOPT_POSTFIELDS,     '{}');
        curl_setopt($ch, CURLOPT_HTTPHEADER,     ['Content-Type: application/json']);
        @curl_exec($ch);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($curlErr && strpos($curlErr, 'timed out') === false) {
            // Real connection error (not just a read timeout from async fire-and-forget)
            log_message('error', 'Reprocess LLM trigger failed: ' . $curlErr);
        }

        return $this->response->setJSON([
            'status'  => 'started',
            'message' => 'Full reprocess triggered. Processing will run in the background — check progress below.',
        ]);
    }

}
