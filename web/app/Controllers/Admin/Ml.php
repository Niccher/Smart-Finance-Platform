<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use CodeIgniter\API\ResponseTrait;

class Ml extends BaseController
{
    use ResponseTrait;

    private function baseUrl(): string
    {
        return (string) config('MlBackend')->baseUrl;
    }

    private function client()
    {
        return \Config\Services::curlrequest();
    }

    public function index()
    {
        $data = [
            'bg_color' => '#B1B8ED',
            'status' => $this->fetchStatus(),
        ];

        return view('Admin/Ml/index', $data);
    }

    public function models()
    {
        $data = [
            'bg_color' => '#B1B8ED',
            'status' => $this->fetchStatus(),
        ];

        return view('Admin/Ml/models', $data);
    }

    public function config()
    {
        $data = [
            'bg_color' => '#B1B8ED',
            'status' => $this->fetchStatus(),
            'ml_backend_url' => (string) config('MlBackend')->baseUrl,
        ];

        return view('Admin/Ml/config', $data);
    }

    public function test()
    {
        $data = [
            'bg_color' => '#B1B8ED',
            'status' => $this->fetchStatus(),
        ];

        return view('Admin/Ml/test', $data);
    }

    /**
     * ML job history & statistics (time taken, SMS classified, errors, status).
     */
    public function jobs()
    {
        $db = \Config\Database::connect();

        if (!$db->tableExists('tbl_Processing_Jobs')) {
            return view('Admin/Ml/jobs', [
                'bg_color' => '#B1B8ED',
                'status' => $this->fetchStatus(),
                'jobs' => [],
                'totals' => ['jobs' => 0, 'msgs' => 0, 'errors' => 0, 'time' => 0],
                'status_counts' => [],
                'auto' => $this->fetchAutoState(),
            ]);
        }

        // Auto-cleanup zombie jobs that are stuck in active statuses for more than 30 minutes
        $db->table('tbl_Processing_Jobs')
            ->whereIn('status', ['processing', 'starting', 'queued'])
            ->where('created_at < DATE_SUB(NOW(), INTERVAL 30 MINUTE)')
            ->update(['status' => 'failed']);

        $jobs = $db->table('tbl_Processing_Jobs')
            ->orderBy('id', 'DESC')
            ->limit(200)
            ->get()
            ->getResultArray();

        $totalSmsDb = (int) $db->table('tbl_Sms')->countAllResults();

        // Resolve user_id -> username for display.
        $usernames = [];
        $userRows = $db->table('users')->select('id, username')->get()->getResult();
        foreach ($userRows as $r) {
            $usernames[(string) $r->id] = $r->username;
        }

        // Pre-fetch token usages from tbl_LLM_Calls group by job_id to calculate pricing
        $tokenUsages = [];
        if ($db->tableExists('tbl_LLM_Calls')) {
            $calls = $db->table('tbl_LLM_Calls')
                ->select('job_id, SUM(prompt_tokens) as p, SUM(reply_tokens) as r')
                ->groupBy('job_id')
                ->get()
                ->getResult();
            foreach ($calls as $c) {
                $tokenUsages[(int)$c->job_id] = [
                    'prompt' => (int)$c->p,
                    'reply' => (int)$c->r
                ];
            }
        }

        $totals = [
            'jobs' => 0, 'msgs' => 0, 'errors' => 0, 'time' => 0,
            'senders' => 0, 'senders_finance' => 0, 'senders_unwanted' => 0,
            'sms_total' => 0, 'sms_finance' => 0, 'sms_unwanted' => 0, 'sms_skipped' => 0,
            'transactional' => 0, 'cost' => 0.0
        ];
        $statusCounts = [];
        foreach ($jobs as $key => $j) {
            $md = $this->decodeMetadata($j['metadata'] ?? null);

            // Sanitize messages_processed and metadata counts to never exceed database totals
            if ((int)$j['messages_processed'] > $totalSmsDb) {
                $j['messages_processed'] = $totalSmsDb;
                $db->table('tbl_Processing_Jobs')->where('id', $j['id'])->update(['messages_processed' => $totalSmsDb]);
            }
            
            if (!empty($md)) {
                $modifiedMd = false;
                foreach (['messages_processed', 'sms_total', 'sms_finance', 'sms_unwanted', 'sms_skipped', 'senders_total'] as $field) {
                    if (isset($md[$field]) && (int)$md[$field] > $totalSmsDb) {
                        $md[$field] = $totalSmsDb;
                        $modifiedMd = true;
                    }
                }
                if ($modifiedMd) {
                    $db->table('tbl_Processing_Jobs')->where('id', $j['id'])->update(['metadata' => json_encode($md)]);
                }
            }

            // Compute pricing: standard $0.15 per million input tokens, $0.60 per million output tokens
            $jobTokens = $tokenUsages[(int)$j['id']] ?? ['prompt' => 0, 'reply' => 0];
            $cost = (($jobTokens['prompt'] / 1000000) * 0.15) + (($jobTokens['reply'] / 1000000) * 0.60);
            $j['cost'] = $cost;
            $j['tokens'] = $jobTokens;

            $totals['jobs']++;
            $totals['msgs'] += (int) ($md['messages_processed'] ?? $j['messages_processed']);
            $totals['errors'] += (int) ($md['errors'] ?? $j['errors']);
            $totals['time'] += (int) ($md['duration_seconds'] ?? $j['duration_seconds']);
            $totals['senders'] += (int) ($md['senders_total'] ?? 0);
            $totals['senders_finance'] += (int) ($md['senders_finance'] ?? 0);
            $totals['senders_unwanted'] += (int) ($md['senders_unwanted'] ?? 0);
            $totals['sms_total'] += (int) ($md['sms_total'] ?? 0);
            $totals['sms_finance'] += (int) ($md['sms_finance'] ?? 0);
            $totals['sms_unwanted'] += (int) ($md['sms_unwanted'] ?? 0);
            $totals['sms_skipped'] += (int) ($md['sms_skipped'] ?? 0);
            $totals['transactional'] += (int) ($md['transactional_inserted'] ?? 0);
            $totals['cost'] += $cost;
            $statusCounts[$j['status']] = ($statusCounts[$j['status']] ?? 0) + 1;

            $j['metadata'] = $md;
            $j['username'] = $usernames[(string) $j['user_id']] ?? ($j['user_id'] ? substr((string) $j['user_id'], 0, 8) . '…' : '—');
            $jobs[$key] = $j;
        }

        $data = [
            'bg_color' => '#B1B8ED',
            'status' => $this->fetchStatus(),
            'jobs' => $jobs,
            'totals' => $totals,
            'status_counts' => $statusCounts,
            'auto' => $this->fetchAutoState(),
        ];

        return view('Admin/Ml/jobs', $data);
    }

    /**
     * Toggle the ML backend's auto-processing poller on/off.
     */
    public function toggleAuto()
    {
        $enabled = (int) $this->request->getPost('enabled') === 1;

        try {
            $resp = $this->client()->post($this->baseUrl() . '/admin/jobs/auto', [
                'json' => ['enabled' => $enabled],
                'timeout' => 15,
            ]);
            $respBody = json_decode($resp->getBody(), true);

            return $this->response->setJSON([
                'status' => $respBody['status'] ?? 'error',
                'message' => $respBody['message'] ?? 'Unknown response',
                'auto_enabled' => $respBody['auto_enabled'] ?? $enabled,
            ]);
        } catch (\Throwable $e) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'Failed to reach ML backend: ' . $e->getMessage(),
            ]);
        }
    }

    private function decodeMetadata($raw): array
    {
        if (is_array($raw)) {
            return $raw;
        }
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
        return [];
    }

    private function fetchAutoState(): array
    {
        try {
            $resp = $this->client()->get($this->baseUrl() . '/admin/jobs/status', ['timeout' => 8]);
            $body = json_decode($resp->getBody(), true);
            return [
                'reachable' => true,
                'auto_enabled' => (bool) ($body['auto_enabled'] ?? true),
                'totals' => $body['totals'] ?? [],
            ];
        } catch (\Throwable $e) {
            return ['reachable' => false, 'auto_enabled' => null, 'totals' => [], 'error' => $e->getMessage()];
        }
    }

    /**
     * Admin console to view senders and override their finance flag
     * (e.g. mark a sender as non-finance).
     */
    public function senders()
    {
        $data = [
            'bg_color' => '#B1B8ED',
            'status' => $this->fetchStatus(),
            'senders' => $this->loadSenders(),
        ];

        return view('Admin/Ml/senders', $data);
    }

    /**
     * Set a sender's finance flag across all sender profiles and existing
     * SMS classifications. 0 = non-finance override.
     */
    public function setSenderFinance()
    {
        $number = trim((string) $this->request->getPost('sender'));
        $isFinance = (int) $this->request->getPost('is_finance') === 1;

        if ($number === '') {
            return $this->response->setJSON(['status' => 'error', 'message' => 'No sender provided.']);
        }

        $db = \Config\Database::connect();

        $db->transStart();

        $updatedProfiles = 0;
        if ($db->tableExists('tbl_Sender_Profiles')) {
            $updatedProfiles = $db->table('tbl_Sender_Profiles')
                ->where('sp_number', $number)
                ->update([
                    'sp_is_finance' => $isFinance ? 1 : 0,
                    'sp_updated' => date('Y-m-d H:i:s'),
                ]);
        }

        $updatedClassifications = 0;
        if ($db->tableExists('tbl_Sms')) {
            $updatedClassifications = $db->table('tbl_Sms')
                ->where('sms_number', $number)
                ->update(['sms_is_finance' => $isFinance ? 1 : 0]);
        }

        $db->transComplete();

        \App\Libraries\Audit::log('ml_sender_finance', 'config', 'Set sender finance flag', [
            'sender' => $number,
            'is_finance' => $isFinance,
            'profiles_updated' => $updatedProfiles,
            'classifications_updated' => $updatedClassifications,
        ]);

        return $this->response->setJSON([
            'status' => 'success',
            'message' => 'Sender "' . $number . '" marked as ' . ($isFinance ? 'finance' : 'non-finance')
                . " ({$updatedProfiles} profiles, {$updatedClassifications} SMS classifications updated).",
        ]);
    }

    /**
     * List distinct senders with aggregate finance status from sender profiles.
     */
    private function loadSenders(): array
    {
        $db = \Config\Database::connect();

        if (!$db->tableExists('tbl_Sender_Profiles')) {
            return [];
        }

        $rows = $db->query(
            "SELECT sp_number,
                    MAX(sp_name) AS sp_name,
                    MAX(sp_category) AS sp_category,
                    COUNT(*) AS total,
                    COALESCE(SUM(sp_is_finance), 0) AS finance_count
             FROM tbl_Sender_Profiles
             GROUP BY sp_number
             ORDER BY finance_count DESC, sp_number ASC"
        )->getResultArray();

        $senders = [];
        foreach ($rows as $row) {
            $senders[] = [
                'number' => $row['sp_number'],
                'name' => $row['sp_name'] ?? '',
                'category' => $row['sp_category'] ?? '',
                'total' => (int) $row['total'],
                'finance_count' => (int) $row['finance_count'],
                'is_finance' => (int) $row['finance_count'] > 0,
            ];
        }

        return $senders;
    }

    /**
     * Global allowlist of senders always treated as finance-related
     * ("allowed by default"). The ML backend reads this table first and
     * falls back to its hardcoded list when the table is empty.
     */
    public function allowed()
    {
        $data = [
            'bg_color' => '#B1B8ED',
            'status' => $this->fetchStatus(),
            'allowed' => $this->loadAllowed(),
            'fallback_active' => $this->isFallbackActive(),
        ];

        return view('Admin/Ml/allowed', $data);
    }

    /**
     * Add a sender to the global allowed list.
     */
    public function allowedAdd()
    {
        $sender = strtoupper(trim((string) $this->request->getPost('sender')));
        $category = trim((string) $this->request->getPost('category'));

        if ($sender === '') {
            return $this->response->setJSON(['status' => 'error', 'message' => 'No sender provided.']);
        }

        $db = \Config\Database::connect();
        $exists = $db->table('tbl_Allowed_Senders')
            ->where('sender', $sender)
            ->get()
            ->getRow();

        if ($exists) {
            return $this->response->setJSON(['status' => 'error', 'message' => 'Sender "' . $sender . '" is already on the allowed list.']);
        }

        $db->table('tbl_Allowed_Senders')->insert([
            'sender' => $sender,
            'category' => $category ?: null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        \App\Libraries\Audit::log('ml_allowed_add', 'config', 'Added sender to allowed list', [
            'sender' => $sender,
            'category' => $category,
        ]);

        return $this->response->setJSON([
            'status' => 'success',
            'message' => 'Sender "' . $sender . '" added to the allowed list. The ML backend will treat it as finance on its next processing cycle.',
        ]);
    }

    /**
     * Remove a sender from the global allowed list.
     */
    public function allowedRemove()
    {
        $sender = strtoupper(trim((string) $this->request->getPost('sender')));

        if ($sender === '') {
            return $this->response->setJSON(['status' => 'error', 'message' => 'No sender provided.']);
        }

        $db = \Config\Database::connect();
        $db->table('tbl_Allowed_Senders')->where('sender', $sender)->delete();

        \App\Libraries\Audit::log('ml_allowed_remove', 'config', 'Removed sender from allowed list', [
            'sender' => $sender,
        ]);

        return $this->response->setJSON([
            'status' => 'success',
            'message' => 'Sender "' . $sender . '" removed from the allowed list.',
        ]);
    }

    /**
     * Clear the whole allowed list so the ML backend returns to its
     * hardcoded fallback defaults.
     */
    public function allowedReset()
    {
        $db = \Config\Database::connect();
        $db->table('tbl_Allowed_Senders')->truncate();

        \App\Libraries\Audit::log('ml_allowed_reset', 'config', 'Cleared allowed list to use hardcoded fallback');

        return $this->response->setJSON([
            'status' => 'success',
            'message' => 'Allowed list cleared. The ML backend will fall back to its hardcoded defaults.',
        ]);
    }

    public function allowedBulkCategorize()
    {
        $senders = $this->request->getPost('senders');
        $category = trim((string) $this->request->getPost('category'));

        if (empty($senders) || !is_array($senders)) {
            return $this->response->setJSON(['status' => 'error', 'message' => 'No senders selected.']);
        }

        $db = \Config\Database::connect();
        $db->table('tbl_Allowed_Senders')
            ->whereIn('sender', $senders)
            ->update([
                'category' => $category ?: null
            ]);

        \App\Libraries\Audit::log('ml_allowed_bulk_categorize', 'config', 'Bulk categorized allowed senders', [
            'senders' => $senders,
            'category' => $category,
        ]);

        return $this->response->setJSON([
            'status' => 'success',
            'message' => 'Successfully updated category for ' . count($senders) . ' senders.',
        ]);
    }

    public function allowedBulkRemove()
    {
        $senders = $this->request->getPost('senders');

        if (empty($senders) || !is_array($senders)) {
            return $this->response->setJSON(['status' => 'error', 'message' => 'No senders selected.']);
        }

        $db = \Config\Database::connect();
        $db->table('tbl_Allowed_Senders')->whereIn('sender', $senders)->delete();

        \App\Libraries\Audit::log('ml_allowed_bulk_remove', 'config', 'Bulk removed allowed senders', [
            'senders' => $senders,
        ]);

        return $this->response->setJSON([
            'status'  => 'success',
            'message' => 'Removed ' . count($senders) . ' senders.',
        ]);
    }

    public function allowedSeed()
    {
        \CodeIgniter\CLI\CLI::init();
        $seeder = \Config\Database::seeder();
        $seeder->call('AllowedSendersSeeder');

        \App\Libraries\Audit::log('ml_allowed_seed', 'config', 'Re-seeded allowed senders with defaults');

        return $this->response->setJSON([
            'status'  => 'success',
            'message' => 'Default senders have been re-seeded. Existing entries were preserved.',
        ]);
    }

    private function loadAllowed(): array
    {
        $db = \Config\Database::connect();
        if (!$db->tableExists('tbl_Allowed_Senders')) {
            return [];
        }

        return $db->table('tbl_Allowed_Senders')
            ->select('sender, category, created_at')
            ->orderBy('sender', 'ASC')
            ->get()
            ->getResultArray();
    }

    private function isFallbackActive(): bool
    {
        $db = \Config\Database::connect();
        if (!$db->tableExists('tbl_Allowed_Senders')) {
            return true;
        }

        return $db->table('tbl_Allowed_Senders')->countAllResults() === 0;
    }

    public function saveConfig()
    {
        $savedUrlOk = false;
        $mlBackendUrl = trim((string)$this->request->getPost('ml_backend_url'));
        if ($mlBackendUrl !== '') {
            if (!preg_match('#^https?://#i', $mlBackendUrl)) {
                $mlBackendUrl = 'http://' . $mlBackendUrl;
            }
            $mlBackendUrl = rtrim($mlBackendUrl, '/');
            try {
                $db = \Config\Database::connect();
                $db->table('tbl_Settings')->upsert([
                    'key'         => 'ml_backend_url',
                    'value'       => $mlBackendUrl,
                    'type'        => 'string',
                    'description' => 'ML Backend Base URL',
                ]);
                config('MlBackend')->baseUrl = $mlBackendUrl;
                $savedUrlOk = true;
            } catch (\Throwable $e) {
                log_message('error', 'Failed to save ml_backend_url: ' . $e->getMessage());
            }
        }

        $payload = [
            'llm_model' => $this->request->getPost('llm_model') ?: null,
            'llm_max_tokens' => $this->request->getPost('llm_max_tokens') !== '' ? (int)$this->request->getPost('llm_max_tokens') : null,
            'llm_temperature' => $this->request->getPost('llm_temperature') !== '' ? (float)$this->request->getPost('llm_temperature') : null,
            'llm_ctx_size' => $this->request->getPost('llm_ctx_size') !== '' ? (int)$this->request->getPost('llm_ctx_size') : null,
            'llm_batch_size' => $this->request->getPost('llm_batch_size') !== '' ? (int)$this->request->getPost('llm_batch_size') : null,
            'n_gpu_layers' => $this->request->getPost('n_gpu_layers') !== '' ? (int)$this->request->getPost('n_gpu_layers') : null,
            'batch_size' => $this->request->getPost('batch_size') !== '' ? (int)$this->request->getPost('batch_size') : null,
            'max_retries' => $this->request->getPost('max_retries') !== '' ? (int)$this->request->getPost('max_retries') : null,
            'poll_interval' => $this->request->getPost('poll_interval') !== '' ? (int)$this->request->getPost('poll_interval') : null,
            'llm_engine' => $this->request->getPost('llm_engine') !== '' ? $this->request->getPost('llm_engine') : null,
            'llm_provider' => $this->request->getPost('llm_provider') !== '' ? $this->request->getPost('llm_provider') : null,
            'llm_base_url' => $this->request->getPost('llm_base_url') !== '' ? $this->request->getPost('llm_base_url') : null,
            'llm_api_key' => $this->request->getPost('llm_api_key') !== '' ? $this->request->getPost('llm_api_key') : null,
            'llm_external_provider' => $this->request->getPost('llm_external_provider') !== '' ? $this->request->getPost('llm_external_provider') : null,
            'llm_external_base_url' => $this->request->getPost('llm_external_base_url') !== '' ? $this->request->getPost('llm_external_base_url') : null,
            'llm_external_api_key' => $this->request->getPost('llm_external_api_key') !== '' ? $this->request->getPost('llm_external_api_key') : null,
            'llm_external_model' => $this->request->getPost('llm_external_model') !== '' ? $this->request->getPost('llm_external_model') : null,
            'llm_external_max_tokens' => $this->request->getPost('llm_external_max_tokens') !== '' ? (int)$this->request->getPost('llm_external_max_tokens') : null,
            'llm_external_temperature' => $this->request->getPost('llm_external_temperature') !== '' ? (float)$this->request->getPost('llm_external_temperature') : null,
            'external_batch_size' => $this->request->getPost('external_batch_size') !== '' ? (int)$this->request->getPost('external_batch_size') : null,
            'external_max_retries' => $this->request->getPost('external_max_retries') !== '' ? (int)$this->request->getPost('external_max_retries') : null,
            'external_poll_interval' => $this->request->getPost('external_poll_interval') !== '' ? (int)$this->request->getPost('external_poll_interval') : null,
            'llm_fallback_provider' => $this->request->getPost('llm_fallback_provider') !== '' ? $this->request->getPost('llm_fallback_provider') : null,
            'llm_fallback_base_url' => $this->request->getPost('llm_fallback_base_url') !== '' ? $this->request->getPost('llm_fallback_base_url') : null,
            'llm_fallback_api_key' => $this->request->getPost('llm_fallback_api_key') !== '' ? $this->request->getPost('llm_fallback_api_key') : null,
            'llm_fallback_model' => $this->request->getPost('llm_fallback_model') !== '' ? $this->request->getPost('llm_fallback_model') : null,
            'llm_fallback_enabled' => $this->request->getPost('llm_fallback_enabled') !== '' ? in_array(strtolower($this->request->getPost('llm_fallback_enabled')), ['true', '1', 'yes', 'on'], true) : null,
            'llm_gemini_api_key' => $this->request->getPost('llm_gemini_api_key') !== '' ? $this->request->getPost('llm_gemini_api_key') : null,
            'llm_deepseek_api_key' => $this->request->getPost('llm_deepseek_api_key') !== '' ? $this->request->getPost('llm_deepseek_api_key') : null,
            'llm_openai_api_key' => $this->request->getPost('llm_openai_api_key') !== '' ? $this->request->getPost('llm_openai_api_key') : null,
            'llm_groq_api_key' => $this->request->getPost('llm_groq_api_key') !== '' ? $this->request->getPost('llm_groq_api_key') : null,
            'llm_mistral_api_key' => $this->request->getPost('llm_mistral_api_key') !== '' ? $this->request->getPost('llm_mistral_api_key') : null,
            'llm_openrouter_api_key' => $this->request->getPost('llm_openrouter_api_key') !== '' ? $this->request->getPost('llm_openrouter_api_key') : null,
            'llm_cohere_api_key' => $this->request->getPost('llm_cohere_api_key') !== '' ? $this->request->getPost('llm_cohere_api_key') : null,
            'llm_kimi_api_key' => $this->request->getPost('llm_kimi_api_key') !== '' ? $this->request->getPost('llm_kimi_api_key') : null,
            'llm_nemotron_api_key' => $this->request->getPost('llm_nemotron_api_key') !== '' ? $this->request->getPost('llm_nemotron_api_key') : null,
            'llm_xai_api_key' => $this->request->getPost('llm_xai_api_key') !== '' ? $this->request->getPost('llm_xai_api_key') : null,
        ];

        try {
            $resp = $this->client()->post($this->baseUrl() . '/admin/config', [
                'json' => array_filter($payload, fn($v) => $v !== null),
                'timeout' => 10,
            ]);
            $body = json_decode($resp->getBody(), true);

            return $this->response->setJSON([
                'status' => 'success',
                'message' => 'Config saved successfully. ' . ($body['note'] ?? ''),
                'applied' => $body['applied'] ?? [],
            ]);
        } catch (\Throwable $e) {
            if ($savedUrlOk) {
                return $this->response->setJSON([
                    'status' => 'success',
                    'message' => 'ML Backend URL updated to ' . esc($mlBackendUrl) . ' in database settings. (Note: ML microservice is not reachable at this URL).',
                ]);
            }
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'Failed to reach ML backend: ' . $e->getMessage(),
            ]);
        }
    }

    public function testConnection()
    {
        $targetMlUrl = trim((string)$this->request->getPost('ml_backend_url'));
        if ($targetMlUrl !== '') {
            if (!preg_match('#^https?://#i', $targetMlUrl)) {
                $targetMlUrl = 'http://' . $targetMlUrl;
            }
            $targetMlUrl = rtrim($targetMlUrl, '/');
        } else {
            $targetMlUrl = $this->baseUrl();
        }

        $payload = [
            'provider' => $this->request->getPost('provider'),
            'base_url' => $this->request->getPost('base_url'),
            'api_key' => $this->request->getPost('api_key'),
            'model' => $this->request->getPost('model'),
        ];

        try {
            $resp = $this->client()->post($targetMlUrl . '/admin/test_connection', [
                'json' => $payload,
                'timeout' => 30,
            ]);
            $rawBody = (string)$resp->getBody();
            $body = json_decode($rawBody, true);
            if (!is_array($body)) {
                return $this->response->setJSON([
                    'status' => 'error',
                    'tested_url' => $targetMlUrl,
                    'message' => "ML microservice at '{$targetMlUrl}' returned non-JSON response: " . substr($rawBody, 0, 150),
                ]);
            }
            return $this->response->setJSON($body);
        } catch (\Throwable $e) {
            return $this->response->setJSON([
                'status' => 'error',
                'tested_url' => $targetMlUrl,
                'message' => "Failed to reach ML backend at '{$targetMlUrl}': " . $e->getMessage(),
            ]);
        }
    }

    public function testBackendUrl()
    {
        $url = trim((string)$this->request->getPost('url'));
        if (empty($url)) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'Please enter an ML backend URL to test.',
            ]);
        }

        if (!preg_match('#^https?://#i', $url)) {
            $url = 'http://' . $url;
        }
        $url = rtrim($url, '/');

        $parsed = parse_url($url);
        $host = strtolower($parsed['host'] ?? '');
        if (empty($host) || $host === '169.254.169.254' || $host === 'metadata.google.internal' || preg_match('/^169\.254\./', $host)) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'Prohibited or invalid target host for ML microservice.',
            ]);
        }

        $start = microtime(true);

        try {
            $resp = $this->client()->get($url . '/admin/status', ['timeout' => 8]);
            $latency = round((microtime(true) - $start) * 1000);
            $rawBody = (string)$resp->getBody();
            $body = json_decode($rawBody, true);

            if (!is_array($body)) {
                return $this->response->setJSON([
                    'status' => 'error',
                    'tested_url' => $url,
                    'message' => "ML microservice at '{$url}' returned non-JSON response: " . substr($rawBody, 0, 150),
                ]);
            }

            $dbOk = (bool)($body['db_configured'] ?? false);
            $llamaStatus = $body['llama'] ?? 'unknown';

            return $this->response->setJSON([
                'status' => 'success',
                'latency_ms' => $latency,
                'db_configured' => $dbOk,
                'llama_status' => $llamaStatus,
                'app' => $body['app'] ?? [],
                'message' => $dbOk
                    ? "ML microservice reached at {$url} in {$latency}ms. Database connection is healthy!"
                    : "ML microservice reached at {$url} in {$latency}ms, but DB connection failed (db_configured: false). Check ML microservice database credentials.",
            ]);
        } catch (\Throwable $e) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => "Could not reach ML microservice at '{$url}'. Error: " . $e->getMessage(),
            ]);
        }
    }

    public function activateModel()
    {
        $filename = $this->request->getPost('filename');
        $llmModel = $this->request->getPost('llm_model');

        if (empty($filename)) {
            return $this->response->setJSON(['status' => 'error', 'message' => 'No model selected']);
        }

        try {
            $resp = $this->client()->post($this->baseUrl() . '/admin/models/activate', [
                'json' => ['filename' => $filename, 'llm_model' => $llmModel ?: null],
                'timeout' => 30,
            ]);
            $rawBody = (string)$resp->getBody();
            $body = json_decode($rawBody, true);

            return $this->response->setJSON(is_array($body) ? $body : [
                'status' => 'error',
                'message' => 'ML backend returned non-JSON response: ' . substr($rawBody, 0, 150),
            ]);
        } catch (\Throwable $e) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'Failed to reach ML backend: ' . $e->getMessage(),
            ]);
        }
    }

    public function uploadModel()
    {
        $file = $this->request->getFile('model');
        if (!$file || !$file->isValid()) {
            return $this->response->setJSON(['status' => 'error', 'message' => 'No model file selected.']);
        }

        $ext = strtolower(pathinfo($file->getName(), PATHINFO_EXTENSION));
        if (!in_array($ext, ['gguf', 'bin'], true)) {
            return $this->response->setJSON(['status' => 'error', 'message' => 'Only .gguf and .bin model files are allowed.']);
        }

        if ($file->getSize() > 8 * 1024 * 1024 * 1024) {
            return $this->response->setJSON(['status' => 'error', 'message' => 'Model too large (max 8 GB).']);
        }

        try {
            $resp = $this->client()->post($this->baseUrl() . '/admin/models/upload', [
                'multipart' => [
                    [
                        'name'     => 'file',
                        'contents' => fopen($file->getTempName(), 'r'),
                        'filename' => $file->getName(),
                    ],
                ],
                'timeout'        => 0,
                'connect_timeout' => 30,
            ]);
            $body = json_decode($resp->getBody(), true);

            return $this->response->setJSON([
                'status' => $body['status'] ?? 'error',
                'message' => $body['message'] ?? 'Unknown response',
            ]);
        } catch (\Throwable $e) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'Upload failed: ' . $e->getMessage(),
            ]);
        }
    }

    public function modelDownload()
    {
        $url = trim((string)$this->request->getPost('url'));
        $filename = trim((string)$this->request->getPost('filename'));
        $hfToken = trim((string)$this->request->getPost('hf_token'));

        if ($url === '') {
            return $this->response->setJSON(['status' => 'error', 'message' => 'Download URL is required.']);
        }

        try {
            $resp = $this->client()->post($this->baseUrl() . '/admin/models/download', [
                'json' => [
                    'url' => $url,
                    'filename' => $filename !== '' ? $filename : null,
                    'hf_token' => $hfToken !== '' ? $hfToken : null,
                ],
                'timeout' => 15,
            ]);
            $body = json_decode((string)$resp->getBody(), true);
            return $this->response->setJSON(is_array($body) ? $body : ['status' => 'error', 'message' => 'Invalid backend response']);
        } catch (\Throwable $e) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'Failed to initiate download on ML backend: ' . $e->getMessage(),
            ]);
        }
    }

    public function modelDownloadStatus(string $taskId = '')
    {
        $taskId = trim($taskId);
        if ($taskId === '') {
            return $this->response->setJSON(['status' => 'error', 'message' => 'Task ID is required.']);
        }

        try {
            $resp = $this->client()->get($this->baseUrl() . '/admin/models/download/' . urlencode($taskId), [
                'timeout' => 10,
            ]);
            $body = json_decode((string)$resp->getBody(), true);
            return $this->response->setJSON(is_array($body) ? $body : ['status' => 'error', 'message' => 'Invalid backend response']);
        } catch (\Throwable $e) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'Failed to check download status: ' . $e->getMessage(),
            ]);
        }
    }

    public function testLocal()
    {
        $targetMlUrl = trim((string)$this->request->getPost('ml_backend_url'));
        if ($targetMlUrl !== '') {
            if (!preg_match('#^https?://#i', $targetMlUrl)) {
                $targetMlUrl = 'http://' . $targetMlUrl;
            }
            $targetMlUrl = rtrim($targetMlUrl, '/');
        } else {
            $targetMlUrl = $this->baseUrl();
        }

        $tStart = microtime(true);
        try {
            $resp = $this->client()->get($targetMlUrl . '/admin/status', [
                'timeout' => 10,
            ]);
            $latencyMs = (int) round((microtime(true) - $tStart) * 1000);
            $rawBody = (string)$resp->getBody();
            $body = json_decode($rawBody, true);

            if (!is_array($body)) {
                return $this->response->setJSON([
                    'status' => 'error',
                    'latency_ms' => $latencyMs,
                    'tested_url' => $targetMlUrl,
                    'message' => "ML microservice returned non-JSON response: " . substr($rawBody, 0, 150),
                ]);
            }

            return $this->response->setJSON([
                'status' => 'ok',
                'latency_ms' => $latencyMs,
                'tested_url' => $targetMlUrl,
                'active_model' => $body['config']['llm_model'] ?? ($body['status']['llm_model'] ?? 'None'),
                'engine' => $body['config']['llm_engine'] ?? 'local',
                'llama_status' => $body['llama_status'] ?? 'unknown',
                'models' => $body['models'] ?? [],
            ]);
        } catch (\Throwable $e) {
            $latencyMs = (int) round((microtime(true) - $tStart) * 1000);
            return $this->response->setJSON([
                'status' => 'error',
                'latency_ms' => $latencyMs,
                'tested_url' => $targetMlUrl,
                'message' => "Could not connect to local ML backend: " . $e->getMessage(),
            ]);
        }
    }

    public function deleteModel()
    {
        $filename = $this->request->getPost('filename');
        if (empty($filename)) {
            return $this->response->setJSON(['status' => 'error', 'message' => 'No model selected']);
        }

        try {
            $resp = $this->client()->post($this->baseUrl() . '/admin/models/delete', [
                'json' => ['filename' => $filename],
                'timeout' => 10,
            ]);
            $body = json_decode($resp->getBody(), true);

            return $this->response->setJSON([
                'status' => $body['status'] ?? 'error',
                'message' => $body['message'] ?? 'Unknown response',
            ]);
        } catch (\Throwable $e) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'Failed to reach ML backend: ' . $e->getMessage(),
            ]);
        }
    }

    public function runTest()
    {
        $sender = $this->request->getPost('sender') ?: 'MPESA';
        $message = $this->request->getPost('message');

        if (empty($message)) {
            return $this->response->setJSON(['status' => 'error', 'message' => 'Please enter a test message']);
        }

        try {
            $resp = $this->client()->post($this->baseUrl() . '/admin/test-prompt', [
                'json' => ['sender' => $sender, 'messages' => [$message]],
                'timeout' => 120,
            ]);
            $body = json_decode($resp->getBody(), true);

            return $this->response->setJSON([
                'status' => $body['status'] ?? 'error',
                'message' => $body['status'] === 'ok' ? 'LLM responded in ' . ($body['elapsed_ms'] ?? '?') . 'ms' : ($body['message'] ?? 'Error'),
                'result' => $body,
            ]);
        } catch (\Throwable $e) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'LLM request failed: ' . $e->getMessage(),
            ]);
        }
    }

    public function prompts()
    {
        $data = [
            'bg_color' => '#B1B8ED',
            'status' => $this->fetchStatus(),
            'prompts' => $this->fetchPrompts(),
        ];

        return view('Admin/Ml/prompts', $data);
    }

    public function promptSave()
    {
        $key = trim((string) $this->request->getPost('prompt_key'));
        $title = (string) $this->request->getPost('title');
        $body = (string) $this->request->getPost('body');

        if ($key === '' || trim($body) === '') {
            return $this->response->setJSON(['status' => 'error', 'message' => 'Prompt key and body are required.']);
        }

        try {
            $resp = $this->client()->post($this->baseUrl() . '/admin/prompts', [
                'json' => ['prompt_key' => $key, 'body' => $body, 'title' => $title],
                'timeout' => 15,
            ]);
            $respBody = json_decode($resp->getBody(), true);

            return $this->response->setJSON([
                'status' => $respBody['status'] ?? 'error',
                'message' => $respBody['message'] ?? 'Unknown response',
            ]);
        } catch (\Throwable $e) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'Failed to save prompt: ' . $e->getMessage(),
            ]);
        }
    }

    public function promptActivate()
    {
        $id = (int) $this->request->getPost('id');
        if ($id <= 0) {
            return $this->response->setJSON(['status' => 'error', 'message' => 'Invalid prompt id']);
        }

        try {
            $resp = $this->client()->post($this->baseUrl() . "/admin/prompts/{$id}/activate", [
                'timeout' => 15,
            ]);
            $respBody = json_decode($resp->getBody(), true);

            return $this->response->setJSON([
                'status' => $respBody['status'] ?? 'error',
                'message' => $respBody['message'] ?? 'Unknown response',
            ]);
        } catch (\Throwable $e) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'Failed to activate prompt: ' . $e->getMessage(),
            ]);
        }
    }

    public function promptDelete()
    {
        $id = (int) $this->request->getPost('id');
        if ($id <= 0) {
            return $this->response->setJSON(['status' => 'error', 'message' => 'Invalid prompt id']);
        }

        try {
            $resp = $this->client()->post($this->baseUrl() . "/admin/prompts/{$id}/delete", [
                'timeout' => 15,
            ]);
            $respBody = json_decode($resp->getBody(), true);

            return $this->response->setJSON([
                'status' => $respBody['status'] ?? 'error',
                'message' => $respBody['message'] ?? 'Unknown response',
            ]);
        } catch (\Throwable $e) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'Failed to delete prompt: ' . $e->getMessage(),
            ]);
        }
    }

    private function fetchPrompts(): array
    {
        try {
            $resp = $this->client()->get($this->baseUrl() . '/admin/prompts', ['timeout' => 10]);
            $body = json_decode($resp->getBody(), true);
            return is_array($body) ? $body : ['keys' => [], 'prompts' => [], 'resolved' => []];
        } catch (\Throwable $e) {
            return ['keys' => [], 'prompts' => [], 'resolved' => [], 'error' => $e->getMessage()];
        }
    }

    private function fetchStatus(): array
    {
        try {
            $start = microtime(true);
            $resp = $this->client()->get($this->baseUrl() . '/admin/status', ['timeout' => 5]);
            $latency = round((microtime(true) - $start) * 1000);
            $body = json_decode($resp->getBody(), true);

            return [
                'reachable' => true,
                'latency_ms' => $latency,
                'app' => $body['app'] ?? [],
                'llama' => $body['llama'] ?? 'unknown',
                'db_configured' => $body['db_configured'] ?? false,
                'uptime' => $body['uptime'] ?? null,
                'models' => $body['models'] ?? [],
                'error' => null,
            ];
        } catch (\Throwable $e) {
            return [
                'reachable' => false,
                'latency_ms' => 0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Stop/cancel a queued or processing ML job as administrator.
     */
    public function stopJob()
    {
        $jobId = (int)$this->request->getPost('job_id');
        if ($jobId <= 0) {
            return $this->response->setJSON(['status' => 'error', 'message' => 'Invalid Job ID.']);
        }

        $db = \Config\Database::connect();

        $job = $db->table('tbl_Processing_Jobs')
            ->where('id', $jobId)
            ->get()
            ->getRowArray();

        if (!$job) {
            return $this->response->setJSON(['status' => 'error', 'message' => 'Job not found.']);
        }

        if (!in_array($job['status'], ['queued', 'processing', 'starting'])) {
            return $this->response->setJSON(['status' => 'error', 'message' => 'Job is not in a cancellable state.']);
        }

        // Decode metadata to update the error field
        $md = $job['metadata'] ?? null;
        if (is_string($md) && $md !== '') {
            $md = json_decode($md, true);
        }
        $md = is_array($md) ? $md : [];
        $md['error'] = 'Job stopped/cancelled by administrator.';

        // Calculate duration metrics
        $startedAt = $job['started_at'] ?? $job['created_at'] ?? null;
        $duration = null;
        if (!empty($startedAt)) {
            $duration = max(0, time() - strtotime($startedAt . ' UTC'));
        }
        $md['duration_seconds'] = $duration;

        $db->table('tbl_Processing_Jobs')
            ->where('id', $jobId)
            ->update([
                'status' => 'failed',
                'completed_at' => gmdate('Y-m-d H:i:s'),
                'duration_seconds' => $duration,
                'metadata' => json_encode($md)
            ]);

        \App\Libraries\Audit::log('ml_job_stopped', 'maintenance', 'Stopped processing job manually', [
            'job_id' => $jobId,
            'original_status' => $job['status']
        ]);

        return $this->response->setJSON([
            'status' => 'success',
            'message' => 'Job successfully cancelled/stopped.'
        ]);
    }
}
