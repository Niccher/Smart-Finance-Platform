<?php

namespace App\Models;

use CodeIgniter\Model;
use App\Libraries\CryptoHelper;
use ZipArchive;

class UploadModel extends Model
{
    protected $table = "tbl_Loot";
    protected $primaryKey = "loot_Id";
    protected $allowedFields = [
        'loot_Name', 'loot_Device', 'loot_Owner', 'loot_Uuid', 'loot_Created'
    ];

    /**
     * Normalizes a date value (numeric or string) to Y-m-d H:i:s
     */
    private function normalizeDate($time): string {
        if (empty($time)) return date('Y-m-d H:i:s');
        if (is_numeric($time) && $time > 1000000000000) {
            $timestamp = (int)($time / 1000);
        } else {
            $timestamp = is_numeric($time) ? (int)$time : strtotime((string)$time);
        }
        return date('Y-m-d H:i:s', $timestamp ?: time());
    }

    // ============ PUBLIC METHODS ============ //

    public function file_upload(array $data): bool {
        return $this->insert($data) ? true : false;
    }

    public function loot_zip_extract(string $zipFilePath): bool {
        $this->validate_zip_file($zipFilePath);

        $extractPath = WRITEPATH . 'uploads/payloads/';
        $this->ensure_directory_exists($extractPath);

        $zip = new ZipArchive();
        if ($zip->open($zipFilePath) !== TRUE) {
            throw new \RuntimeException("Failed to open ZIP file");
        }

        $zip->extractTo($extractPath);
        $zip->close();

        return true;
    }

    public function loot_last_uploaded(): ?string {
        $result = $this->db->table($this->table)
            ->selectMax('loot_Uuid')
            ->get();

        return $result->getResult()[0]->loot_Uuid ?? null;
    }

    public function loot_info(string $loot_uuid): array {
        return $this->db->table($this->table)
            ->select('loot_Name, loot_Device, loot_Owner, loot_Uuid')
            ->where('loot_Uuid', $loot_uuid)
            ->get()
            ->getResult();
    }

    public function loot_info_all(string $loot_uuid): array {
        return $this->db->table($this->table)
            ->where('loot_Uuid', $loot_uuid)
            ->get()
            ->getResult();
    }

    public function loot_summary(string $loot_uuid): array {
        return $this->db->table('tbl_Loot_Summary')
            ->where('loot_Uuid', $loot_uuid)
            ->get()
            ->getResult();
    }

    public function loot_parse_sms(
        string $loot_uuid,
        string $loot_owner,
        string $loot_device,
        string $dated,
        ?int $userId = null
    ): array {
        try {
            $this->db->transBegin();

            // 1. Validate and get loot file
            $lootFile = $this->validate_and_get_loot_file($loot_uuid);

            // 2. Load and decrypt content
            $decrypted = $this->decrypt_loot_file($lootFile['path'], $lootFile['name']);

            // 3. Parse JSON data
            $smsData = $this->parse_sms_json($decrypted);

            // 4. Process messages
            $processed = $this->process_sms_messages(
                $smsData,
                $loot_uuid,
                $loot_owner,
                $loot_device,
                $userId
            );

            // 5. Save summary (throws on failure)
            $this->save_summary_data(
                $processed['counters'],
                $loot_uuid,
                $dated,
                $userId
            );

            // Hook for budget evaluation
            if ($userId !== null) {
                $this->evaluate_budgets_for_user($userId);
            }

            if ($this->db->transStatus() === false) {
                throw new \RuntimeException('SMS processing transaction failed');
            }

            $this->db->transCommit();

            // Send notification email
            if ($userId !== null && \App\Libraries\Notifier::isTriggerEnabled('loot_uploaded')) {
                try {
                    $users = model(\CodeIgniter\Shield\Models\UserModel::class);
                    $user = $users->findById($userId);
                    $userEmail = $user ? $user->email : '';
                    if (!empty($userEmail)) {
                        $financial = $this->get_upload_financial_summary($loot_uuid);
                        \App\Libraries\Notifier::sendTrigger($userEmail, 'loot_uploaded', [
                            'totalMessages'    => $processed['inserted_count'],
                            'financialSenders' => (int)($financial['counterparties'] ?? 0),
                            'inflowAmount'     => (float)($financial['inflow_amount'] ?? 0.0),
                            'outflowAmount'    => (float)($financial['outflow_amount'] ?? 0.0),
                        ]);
                    }
                } catch (\Throwable $ex) {
                    log_message('error', 'Loot Uploaded Email Alert Failed: ' . $ex->getMessage());
                }
            }

            return [
                'status' => 'success',
                'processed' => $processed['inserted_count'],
                'summary' => $processed['counters']
            ];

        } catch (\Throwable $e) {
            $this->db->transRollback();
            log_message('error', 'SMS Processing Error: ' . $e->getMessage());
            // Re-throw so Upload::upload can return a proper error JSON to the client
            throw $e;
        }
    }

    public function file_listing(string $loot_owner, string $loot_device): array {
        return $this->db->table($this->table)
            ->where('loot_Device', $loot_device)
            ->where('loot_Owner', $loot_owner)
            ->orderBy('loot_Id', 'DESC')
            ->get()
            ->getResult();
    }

    public function device_check_print(array $print_dump): array {
        return $this->db->table('tbl_Devices')
            ->select('device_Uuid')
            ->where($print_dump)
            ->get()
            ->getResult();
    }

    /**
     * Find an existing device by stable identity fields (not the per-request UUID).
     * Matches on the composite device_Fingerprint first, then falls back to the
     * AndroidId (covers devices registered before the composite fingerprint), and
     * finally to model + brand for legacy/custom-ROM devices with no fingerprint.
     */
    public function device_find_by_fingerprint(
        string $fingerprint,
        string $model = '',
        string $brand = '',
        string $androidId = ''
    ): array {
        $builder = $this->db->table('tbl_Devices')->select('device_Uuid');

        if ($fingerprint !== '') {
            $builder->groupStart()
                ->where('device_Fingerprint', $fingerprint);
            if ($androidId !== '') {
                $builder->orWhere('device_AndroidId', $androidId);
            }
            $builder->groupEnd();
        } elseif ($androidId !== '') {
            $builder->where('device_AndroidId', $androidId);
        } else {
            // Fallback when fingerprint is empty (rare / custom ROMs)
            $builder->where('device_Model', $model)
                ->where('device_Brand', $brand);
        }

        return $builder->limit(1)->get()->getResult();
    }

    public function get_loot_uuid(string $loot_name): string {
        $result = $this->db->table($this->table)
            ->select('loot_Uuid')
            ->where('loot_Name', $loot_name)
            ->get()
            ->getResult();

        return $result[0]->loot_Uuid ?? '';
    }

    public function get_loot_summary_from_uuids(array $loot_array_uuids): array {
        return $this->db->table('tbl_Loot_Summary')
            ->select('*')
            ->whereIn('loot_Uuid', $loot_array_uuids)
            ->get()
            ->getResult();
    }

    public function device_make_print(array $print_dump): bool {
        return $this->db->table('tbl_Devices')->insert($print_dump);
    }

    public function getLinkedDevices(int $userId): array
    {
        return $this->db->table('tbl_User_Devices')->where('user_id', $userId)->get()->getResult();
    }

    public function linkDevice(int $userId, string $deviceToken, string $deviceName): bool
    {
        // Check if already linked
        $exists = $this->db->table('tbl_User_Devices')
            ->where(['user_id' => $userId, 'device_token' => $deviceToken])
            ->countAllResults();
            
        if ($exists > 0) return true;
        
        return $this->db->table('tbl_User_Devices')->insert([
            'user_id' => $userId,
            'device_token' => $deviceToken,
            'device_name' => $deviceName,
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Resolve every raw access-token string that owns data for the given user.
     *
     * tbl_Sms.sms_owner and tbl_Loot.loot_Owner store the RAW token, while
     * auth_identities.secret stores its SHA-256 hash, so the match is:
     *   auth_identities.secret = SHA2(raw_token, 256)
     */
    public function getOwnedRawTokens(int $userId): array
    {
        $tokenType = \CodeIgniter\Shield\Authentication\Authenticators\AccessTokens::ID_TYPE_ACCESS_TOKEN;

        $rawTokens = [];
        $tokenRows = $this->db->query("
            SELECT DISTINCT s.sms_owner AS tk FROM tbl_Sms s
            INNER JOIN auth_identities i ON i.secret = SHA2(s.sms_owner, 256)
            WHERE i.user_id = ? AND i.type = ?
            UNION
            SELECT DISTINCT l.loot_Owner AS tk FROM tbl_Loot l
            INNER JOIN auth_identities i ON i.secret = SHA2(l.loot_Owner, 256)
            WHERE i.user_id = ? AND i.type = ?
        ", [$userId, $tokenType, $userId, $tokenType])->getResult();

        foreach ($tokenRows as $r) {
            if (!empty($r->tk)) $rawTokens[] = $r->tk;
        }

        return $rawTokens;
    }

    /**
     * Count the upload batches (tbl_Loot rows) owned by a user.
     */
    public function countUploadsForUser(int $userId): int
    {
        $tokens = $this->getOwnedRawTokens($userId);
        if (empty($tokens)) return 0;

        return $this->db->table('tbl_Loot')->whereIn('loot_Owner', $tokens)->countAllResults();
    }

    /**
     * Constrain a builder to the SMS rows owned by the given user.
     * When $userId is null no constraint is applied (global read).
     */
    protected function applyOwnerScope(object $builder, ?int $userId, string $ownerCol = 's.sms_owner'): void
    {
        if ($userId === null) return;

        $tokens = $this->getOwnedRawTokens($userId);
        if (empty($tokens)) {
            $builder->where('1 = 0');
        } else {
            $builder->whereIn($ownerCol, $tokens);
        }
    }

    /**
     * Finds the date of the absolute last upload
     */
    public function getLastUploadDate(): ?string {
        $result = $this->db->table($this->table)
            ->selectMax('loot_Created')
            ->get()
            ->getRow();
        return $result ? $this->normalizeDate($result->loot_Created) : null;
    }

    /**
     * Get the date of the very last transaction in the database
     */
    public function getLatestTransactionDate(): ?string {
        $result = $this->db->table('tbl_Sms')
            ->selectMax('sms_time')
            ->get()
            ->getRow();
        return $result ? $this->normalizeDate($result->sms_time) : null;
    }

    /**
     * Get aggregated metrics for the dashboard within 30 days of last upload
     */
    public function getDashboardMetrics30Days(string $lastDate, ?string $deviceToken = null): array {
        $stats = [
            'current_balance' => 0,
            'fuliza_balance' => 0,
            'total_sent_30' => 0,
            'total_received_30' => 0,
            'withdrawn_30' => 0,
            'fuliza_taken_30' => 0,
            'daily_avg_spend' => 0,
            'max_transaction' => 0,
            'is_analyzed' => true
        ];

        $builder = $this->db->table('tbl_Sms')
            ->select('sms_direction, sms_amount, sms_balance, sms_transaction_type, sms_is_finance, sms_is_transactional');
        
        if ($deviceToken) {
            $builder->where('sms_owner', $deviceToken);
        }

        $rows = $builder->where('sms_is_finance', 1)->get()->getResult();

        foreach ($rows as $row) {
            $amt = (float)($row->sms_amount ?? 0);
            $dir = strtolower($row->sms_direction ?? '');
            $type = strtolower($row->sms_transaction_type ?? '');

            if ($dir === 'incoming' || $dir === 'received' || $dir === 'money_in') {
                $stats['total_received_30'] += $amt;
            } elseif ($dir === 'outgoing' || $dir === 'sent' || $dir === 'money_out') {
                $stats['total_sent_30'] += $amt;
                if ($amt > $stats['max_transaction']) {
                    $stats['max_transaction'] = $amt;
                }
            }

            if ($type === 'withdrawal') {
                $stats['withdrawn_30'] += $amt;
            } elseif ($type === 'loan') {
                $stats['fuliza_taken_30'] += $amt;
            }
        }

        $stats['daily_avg_spend'] = $stats['total_sent_30'] / 30;

        $balRow = $this->db->table('tbl_Sms')
            ->select('sms_balance')
            ->where('sms_balance >', 0);
        if ($deviceToken) $balRow->where('sms_owner', $deviceToken);
        $latestBal = $balRow->orderBy('id', 'DESC')->limit(1)->get()->getRow();

        if ($latestBal) {
            $stats['current_balance'] = (float)$latestBal->sms_balance;
        }

        return $stats;
    }

    /**
     * Detailed Sent Summary (30 days)
     */
    public function getSentSummary30Days(string $lastDate, ?string $deviceToken = null): array {
        $data = [
            'paybill' => 0,
            'till' => 0,
            'sent_mobile' => 0,
            'fuliza_deductions' => 0
        ];

        $builder = $this->db->table('tbl_Sms')
            ->select('sms_amount, sms_transaction_type, sms_category, sms_body')
            ->where('sms_is_finance', 1)
            ->whereIn('sms_direction', ['outgoing', 'sent', 'money_out']);

        if ($deviceToken) {
            $builder->where('sms_owner', $deviceToken);
        }

        $rows = $builder->get()->getResult();

        foreach ($rows as $row) {
            $amt = (float)($row->sms_amount ?? 0);
            $type = strtolower($row->sms_transaction_type ?? '');
            $cat = strtolower($row->sms_category ?? '');
            $rawBody = $row->sms_body ?? '';
            $body = strtolower($rawBody . ' ' . base64_decode($rawBody));

            if ($type === 'payment' || strpos($cat, 'payment') !== false || strpos($body, 'paybill') !== false) {
                if (strpos($body, 'till') !== false) {
                    $data['till'] += $amt;
                } else {
                    $data['paybill'] += $amt;
                }
            } elseif ($type === 'repayment' || strpos($body, 'fuliza') !== false) {
                $data['fuliza_deductions'] += $amt;
            } else {
                $data['sent_mobile'] += $amt;
            }
        }

        return $data;
    }

    /**
     * Detailed Received Summary (30 days)
     */
    public function getReceivedSummary30Days(string $lastDate, ?string $deviceToken = null): array {
        $data = [
            'total' => 0,
            'banks' => 0,
            'mshwari_kcb' => 0
        ];

        $builder = $this->db->table('tbl_Sms')
            ->select('sms_amount, sms_category, sms_transaction_type, sms_body')
            ->where('sms_is_finance', 1)
            ->whereIn('sms_direction', ['incoming', 'received', 'money_in']);

        if ($deviceToken) {
            $builder->where('sms_owner', $deviceToken);
        }

        $rows = $builder->get()->getResult();

        foreach ($rows as $row) {
            $amt = (float)($row->sms_amount ?? 0);
            $cat = strtolower($row->sms_category ?? '');
            $rawBody = $row->sms_body ?? '';
            $body = strtolower($rawBody . ' ' . base64_decode($rawBody));

            $data['total'] += $amt;
            if ($cat === 'bank' || strpos($body, 'bank') !== false) {
                $data['banks'] += $amt;
            }
            if (strpos($body, 'mshwari') !== false || strpos($body, 'kcb') !== false) {
                $data['mshwari_kcb'] += $amt;
            }
        }

        return $data;
    }

    /**
     * Fetch recent transactions
     */
    public function getRecentTransactions(int $limit = 10, ?string $deviceToken = null): array {
        $builder = $this->db->table('tbl_Sms')
            ->select('id, sms__id, sms_owner, sms_number, sms_body, sms_time, sms_trans_date, sms_amount, sms_direction, sms_counterparty, sms_transaction_type, sms_category, sms_is_finance, sms_is_transactional')
            ->where('sms_is_finance', 1)
            ->orderBy('id', 'DESC')
            ->limit($limit);

        if ($deviceToken) {
            $builder->where('sms_owner', $deviceToken);
        }

        $rows = $builder->get()->getResult();
        foreach ($rows as $r) {
            $r->analyzed_id = $r->id;
            $r->analyzed_counterparty = $r->sms_counterparty ?: $r->sms_number;
            $r->analyzed_amount = $r->sms_amount;
        }

        return $rows;
    }

    /**
     * Fetch top counterparties by volume
     */
    public function getTopCounterparties(int $limit = 5, ?string $deviceToken = null): array {
        $builder = $this->db->table('tbl_Sms')
            ->select('sms_counterparty as counterparty, sms_counterparty, sms_direction, SUM(sms_amount) as total_amount, COUNT(*) as trans_count, COUNT(*) as tx_count')
            ->where('sms_is_finance', 1)
            ->where('sms_is_transactional', 1)
            ->where('sms_counterparty IS NOT NULL')
            ->where('sms_counterparty !=', '')
            ->groupBy('sms_counterparty, sms_direction')
            ->orderBy('total_amount', 'DESC')
            ->limit($limit);

        if ($deviceToken) {
            $builder->where('sms_owner', $deviceToken);
        }

        return $builder->get()->getResult();
    }

    /**
     * Apply category filter for LLM-based categories (finances, money_in, money_out, notifications)
     */
    private function applyCategoryFilter($builder, string $category): void
    {
        switch ($category) {
            case 'finances':
                $builder->where('sms_is_finance', 1);
                break;
            case 'money_in':
                $builder->where('sms_direction', 'incoming');
                break;
            case 'money_out':
                $builder->where('sms_direction', 'outgoing');
                break;
            case 'notifications':
                $builder->where('sms_is_finance', 0);
                break;
            default:
                if (!empty($category)) {
                    $builder->groupStart()
                        ->where('sms_category', $category)
                        ->orWhere('sms_transaction_type', $category)
                        ->groupEnd();
                }
                break;
        }
    }

    /**
     * Count filtered transactions for pagination
     */
    public function countFilteredTransactions(array $f): int {
        $builder = $this->db->table('tbl_Sms');

        if (!empty($f['category'])) {
            $this->applyCategoryFilter($builder, $f['category']);
        }

        if (!empty($f['direction'])) {
            $builder->where('sms_direction', $f['direction']);
        }

        if (!empty($f['transaction_type'])) {
            $builder->where('sms_transaction_type', $f['transaction_type']);
        }

        if (!empty($f['sender'])) {
            $builder->where('sms_number', $f['sender']);
        }

        if (!empty($f['search'])) {
            $builder->groupStart()
                ->like('sms_number', $f['search'])
                ->orLike('sms_counterparty', $f['search'])
                ->orLike('sms_transaction_type', $f['search'])
                ->groupEnd();
        }

        if (!empty($f['date_from'])) {
            $builder->where('sms_trans_date >=', $f['date_from'] . ' 00:00:00');
        }

        if (!empty($f['date_to'])) {
            $builder->where('sms_trans_date <=', $f['date_to'] . ' 23:59:59');
        }

        if (!empty($f['min_amount'])) {
            $builder->where('sms_amount >=', (float)$f['min_amount']);
        }

        if (!empty($f['max_amount'])) {
            $builder->where('sms_amount <=', (float)$f['max_amount']);
        }

        return $builder->countAllResults();
    }

    /**
     * Advanced Filtering — queries tbl_Sms canonical fields directly
     */
    public function getFilteredTransactions(array $f, int $limit = 500, int $offset = 0): array {
        $builder = $this->db->table('tbl_Sms')
            ->select('id, sms__id, sms_owner, sms_number, sms_body, sms_time, sms_trans_date, sms_amount, sms_direction, sms_counterparty, sms_transaction_type, sms_category, sms_is_finance, sms_is_transactional, sms_fee, sms_is_reversal, sms_is_loan');

        if (!empty($f['category'])) {
            $this->applyCategoryFilter($builder, $f['category']);
        }

        if (!empty($f['direction'])) {
            $builder->where('sms_direction', $f['direction']);
        }

        if (!empty($f['transaction_type'])) {
            $builder->where('sms_transaction_type', $f['transaction_type']);
        }

        if (!empty($f['sender'])) {
            $builder->where('sms_number', $f['sender']);
        }

        if (!empty($f['search'])) {
            $builder->groupStart()
                ->like('sms_number', $f['search'])
                ->orLike('sms_counterparty', $f['search'])
                ->orLike('sms_transaction_type', $f['search'])
                ->groupEnd();
        }

        if (!empty($f['date_from'])) {
            $builder->where('sms_trans_date >=', $f['date_from'] . ' 00:00:00');
        }

        if (!empty($f['date_to'])) {
            $builder->where('sms_trans_date <=', $f['date_to'] . ' 23:59:59');
        }

        if (!empty($f['min_amount'])) {
            $builder->where('sms_amount >=', (float)$f['min_amount']);
        }

        if (!empty($f['max_amount'])) {
            $builder->where('sms_amount <=', (float)$f['max_amount']);
        }

        $builder->limit($limit, $offset);
        $results = $builder->orderBy('id', 'DESC')->get()->getResult();

        foreach ($results as $sms) {
            $sms->analyzed_id = $sms->id;
            $sms->analyzed_counterparty = $sms->sms_counterparty ?: $sms->sms_number;
            $sms->analyzed_amount = $sms->sms_amount;
            $sms->cl_category = $sms->sms_category;
            $sms->cl_direction = $sms->sms_direction;
        }

        return $results;
    }

    /**
     * Get data for charts (30 day window)
     */
    public function getAnalyticsData30Days(string $lastDate, ?string $deviceToken = null): array {
        $labels    = [];
        $spending  = [];
        $receiving = [];

        $builder = $this->db->table('tbl_Sms')
            ->select('sms_amount, sms_direction, sms_trans_date, sms_time, sms_category, sms_is_finance')
            ->where('sms_is_finance', 1)
            ->orderBy('id', 'ASC');

        if ($deviceToken) {
            $builder->where('sms_owner', $deviceToken);
        }

        $allSms = $builder->get()->getResult();

        $byDate = [];
        foreach ($allSms as $sms) {
            $rawTime = !empty($sms->sms_trans_date) ? $sms->sms_trans_date : $this->normalizeDate($sms->sms_time);
            $dateKey = substr($rawTime, 0, 10);
            if ($dateKey && strlen($dateKey) === 10) {
                $byDate[$dateKey][] = $sms;
            }
        }

        if (empty($byDate)) {
            $effectiveLastDate = date('Y-m-d');
        } else {
            $allDates = array_keys($byDate);
            sort($allDates);
            $latestSmsDate = end($allDates);
            $effectiveLastDate = (!empty($lastDate) && strlen($lastDate) >= 10) ? substr($lastDate, 0, 10) : $latestSmsDate;
        }

        for ($i = 29; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime($effectiveLastDate . " -$i days"));
            $labels[] = date('d M', strtotime($date));

            $daySpending  = 0;
            $dayReceiving = 0;

            foreach ($byDate[$date] ?? [] as $sms) {
                $amt = (float)($sms->sms_amount ?? 0);
                $dir = strtolower($sms->sms_direction ?? '');
                if (in_array($dir, ['incoming', 'received', 'money_in', 'in'])) {
                    $dayReceiving += $amt;
                } elseif (in_array($dir, ['outgoing', 'sent', 'money_out', 'out'])) {
                    $daySpending += $amt;
                }
            }

            $spending[]  = $daySpending;
            $receiving[] = $dayReceiving;
        }

        if (array_sum($spending) == 0 && array_sum($receiving) == 0 && !empty($byDate)) {
            $labels = [];
            $spending = [];
            $receiving = [];
            $allDates = array_keys($byDate);
            sort($allDates);
            $sliceDates = array_slice($allDates, -30);
            foreach ($sliceDates as $date) {
                $labels[] = date('d M Y', strtotime($date));
                $daySpending = 0;
                $dayReceiving = 0;
                foreach ($byDate[$date] as $sms) {
                    $amt = (float)($sms->sms_amount ?? 0);
                    $dir = strtolower($sms->sms_direction ?? '');
                    if (in_array($dir, ['incoming', 'received', 'money_in', 'in'])) {
                        $dayReceiving += $amt;
                    } elseif (in_array($dir, ['outgoing', 'sent', 'money_out', 'out'])) {
                        $daySpending += $amt;
                    }
                }
                $spending[] = $daySpending;
                $receiving[] = $dayReceiving;
            }
        }

        $categories = [];
        foreach ($allSms as $sms) {
            $amt = (float)($sms->sms_amount ?? 0);
            if ($amt <= 0) continue;
            $cat = !empty($sms->sms_category) ? $sms->sms_category : 'Mobile Money';
            if (!isset($categories[$cat])) $categories[$cat] = 0;
            $categories[$cat] += $amt;
        }
        arsort($categories);

        $fulizaTaken = array_fill(0, count($labels), 0);
        $fulizaPaid  = array_fill(0, count($labels), 0);

        return [
            'labels'       => $labels,
            'spending'     => $spending,
            'receiving'    => $receiving,
            'categories'   => $categories,
            'fuliza_taken' => $fulizaTaken,
            'fuliza_paid'  => $fulizaPaid,
        ];
    }

    /**
     * Helper to extract amount from SMS body
     */
    private function extractAmount(string $body): float {
        // Match "Ksh 1,200.00" or "Ksh. 1200"
        if (preg_match('/ksh\s*([0-9,]+\.[0-9]{2}|[0-9,]+)/i', $body, $matches)) {
            return (float) str_replace(',', '', $matches[1]);
        }
        return 0;
    }

    /**
     * Helper to extract balance from SMS body
     */
    private function extractBalance(string $body): float {
        if (preg_match('/balance is ksh\s*([0-9,]+\.[0-9]{2}|[0-9,]+)/i', $body, $matches)) {
            return (float) str_replace(',', '', $matches[1]);
        }
        return 0;
    }

    // ============ PROTECTED METHODS ============ //

    protected function clean_sms_by_returning_column(array $smsMessages, string $columnName): array {
        $columnData = [];
        $property = $columnName === 'Thread Id' ? 'Thread Id' : $columnName;

        foreach ($smsMessages as $sms) {
            $columnData[] = $sms->$property ?? '';
        }

        return $columnData;
    }

    protected function validate_zip_file(string $path): void {
        if (!file_exists($path)) {
            throw new \RuntimeException("ZIP file not found");
        }

        if (mime_content_type($path) !== 'application/zip') {
            throw new \RuntimeException("Invalid file type");
        }
    }

    protected function ensure_directory_exists(string $path): void {
        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }
    }

    protected function validate_and_get_loot_file(string $uuid): array {
        $query = $this->db->table($this->table)
            ->select('loot_Name')
            ->where('loot_Uuid', $uuid)
            ->get();

        if ($query->getNumRows() === 0) {
            throw new \RuntimeException("Loot record not found");
        }

        $fileName = $query->getRow()->loot_Name;
        $filePath = WRITEPATH . "uploads/payloads/" . $fileName;

        $storage = service('storage');
        if (!$storage->hasPayload($fileName) && !file_exists($filePath)) {
            throw new \RuntimeException("Loot file not found");
        }

        return [
            'name' => $fileName,
            'path' => $filePath
        ];
    }

    protected function decrypt_loot_file(string $path, ?string $fileName = null): string {
        $cryptoHelper = new CryptoHelper();
        $storage = service('storage');
        $name = $fileName ?: basename($path);

        try {
            $content = $storage->getPayload($name);
        } catch (\Throwable $e) {
            if (file_exists($path)) {
                $content = file_get_contents($path);
            } else {
                throw new \RuntimeException("Failed to load payload for decryption: " . $e->getMessage());
            }
        }
        
        log_message('debug', 'File path: ' . $path . ' (name: ' . $name . ')');
        log_message('debug', 'File size: ' . strlen($content));
        log_message('debug', 'File content first 32 bytes: ' . bin2hex(substr($content, 0, 32)));
        
        $decrypted = $cryptoHelper->decode_content($content);

        if (empty($decrypted)) {
            throw new \RuntimeException("Failed to decrypt file");
        }

        log_message('debug', 'Decrypted content length: ' . strlen($decrypted));
        log_message('debug', 'Decrypted content first 200 chars: ' . substr($decrypted, 0, 200));

        return $decrypted;
    }

    protected function parse_sms_json(string $data): array {
        $transformations = [
            '-------(//)--------' => '',
            "\n" => '****',
            '****"' => '"'
        ];

        $normalized = str_replace(
            array_keys($transformations),
            array_values($transformations),
            $data
        );

        $json = json_decode('[' . substr($normalized, 0, -2) . '}]');

        if (json_last_error() !== JSON_ERROR_NONE) {
            log_message('error', 'JSON parse error: ' . json_last_error_msg());
            log_message('debug', 'Normalized data first 500 chars: ' . substr($normalized, 0, 500));
            throw new \RuntimeException("JSON parse error: " . json_last_error_msg());
        }

        return $json;
    }

    protected function process_sms_messages(
        array $smsData,
        string $uuid,
        string $owner,
        string $device,
        ?int $userId = null
    ): array {
        $inserted = 0;
        $total = count($smsData);

        foreach (array_chunk($smsData, 100) as $chunk) {
            $batch = [];
            foreach ($chunk as $sms) {
                $number = isset($sms->Number) ? strtoupper(trim((string) $sms->Number)) : '';
                $smsBody = isset($sms->Body) ? trim((string) $sms->Body) : '';

                $batch[] = [
                    'sms_type' => $sms->Type ?? '',
                    'sms_number' => $number,
                    'sms_thread_id' => $sms->{'Thread Id'} ?? '',
                    'sms_time' => $sms->Date ?? '',
                    'sms_seen' => $sms->Seen ?? '',
                    'sms__id' => $sms->ID ?? '',
                    'sms_body' => $smsBody,
                    'sms_loot_source' => $uuid,
                    'sms_owner' => $owner,
                    'sms_device' => $device,
                    'sms_user_id' => $userId,
                    'sms_category' => 'Unclassified',
                    'sms_direction' => 'none',
                    'sms_is_finance' => 0,
                    'sms_method' => 'upload',
                    'sms_confidence' => 0.5000,
                ];
            }

            $this->db->table('tbl_Sms')->ignore(true)->insertBatch($batch);
            $inserted += count($chunk);
        }

        return [
            'inserted_count' => $inserted,
            'counters' => ['info_All' => $total],
        ];
    }

    /**
     * Infer direction from a human-readable category string.
     */
    protected function infer_direction(string $category): string
    {
        $lower = strtolower($category);
        $incomingPatterns = ['received', 'from ', 'reversal', 'loan taken', 'loan limit', 'fuliza loan taken', 'balance'];
        $outgoingPatterns = ['sent', 'paid to', 'withdraw', 'sent cancel', 'fuliza loan paid'];

        foreach ($incomingPatterns as $p) {
            if (str_contains($lower, $p)) return 'incoming';
        }
        foreach ($outgoingPatterns as $p) {
            if (str_contains($lower, $p)) return 'outgoing';
        }
        return 'none';
    }

    /**
     * Save upload summary — total SMS count + LLM direction/transaction_type breakdowns.
     */
    protected function save_summary_data(array $counters, string $uuid, int|string $date, ?int $userId = null): bool {
        $directionBreakdown = $this->get_direction_breakdown_for_loot($uuid);
        $transactionTypeBreakdown = $this->get_transaction_type_breakdown_for_loot($uuid);

        $summary = [
            'info_All'                => (int) ($counters['info_All'] ?? 0),
            'loot_Uuid'               => $uuid,
            'loot_Created'            => is_numeric($date) ? date('Y-m-d H:i:s', (int)$date) : $date,
            'direction_breakdown'     => json_encode($directionBreakdown),
            'transaction_type_breakdown' => json_encode($transactionTypeBreakdown),
            'user_id'                 => $userId,
        ];

        $ok = $this->db->table('tbl_Loot_Summary')->insert($summary);
        if (!$ok) {
            $error = $this->db->error();
            throw new \RuntimeException(
                'Failed to save loot summary: ' . ($error['message'] ?? 'unknown DB error')
            );
        }

        return true;
    }

    /**
     * Get direction breakdown for a specific loot (incoming/outgoing/none)
     */
    protected function get_direction_breakdown_for_loot(string $lootUuid): array {
        $rows = $this->db->table('tbl_Sms s')
            ->select('sc.direction')
            ->join('tbl_Sms_Classification sc', 'sc.sms_id = s.id', 'left')
            ->where('s.sms_loot_source', $lootUuid)
            ->get()
            ->getResult();

        $breakdown = ['Money In' => 0, 'Money Out' => 0, 'Unknown' => 0, 'Balance Inquiry' => 0];
        foreach ($rows as $row) {
            $dir = strtolower($row->direction ?? 'none');
            if ($dir === 'incoming' || $dir === 'received') {
                $breakdown['Money In']++;
            } elseif ($dir === 'outgoing' || $dir === 'sent') {
                $breakdown['Money Out']++;
            } elseif ($dir === 'balance' || $dir === 'balance_inquiry') {
                $breakdown['Balance Inquiry']++;
            } else {
                $breakdown['Unknown']++;
            }
        }
        return $breakdown;
    }

    /**
     * Get transaction type breakdown for a specific loot
     */
    protected function get_transaction_type_breakdown_for_loot(string $lootUuid): array {
        $rows = $this->db->table('tbl_Sms')
            ->select('sms_transaction_type')
            ->where('sms_loot_source', $lootUuid)
            ->get()
            ->getResult();

        $breakdown = [];
        foreach ($rows as $row) {
            $type = strtolower($row->sms_transaction_type ?? 'unknown');
            if (!isset($breakdown[$type])) $breakdown[$type] = 0;
            $breakdown[$type]++;
        }
        return $breakdown;
    }

    /**
     * Get aggregated financial overview for a user/device
     */
    public function get_financial_overview(string $ownerUuid, string $deviceId): array {
        $default = [
            'total_transactions' => 0,
            'total_senders' => 0,
            'finance_senders' => 0,
            'category_breakdown' => [],
            'total_amount_sent' => 0.0,
            'total_amount_received' => 0.0,
            'period_start' => date('Y-m-d', strtotime('-30 days')),
            'period_end' => date('Y-m-d'),
        ];

        $rows = $this->db->table('tbl_Sms')
            ->select('id, sms_category, sms_direction, sms_is_finance, sms_amount, sms_number, sms_time, sms_trans_date')
            ->where('sms_owner', $ownerUuid)
            ->get()
            ->getResult();
        if (empty($rows)) return $default;

        $total = count($rows);
        $senders = [];
        $financeSenders = [];
        $categoryBreakdown = [];
        $totalSent = 0.0;
        $totalReceived = 0.0;

        foreach ($rows as $row) {
            $cat = $row->sms_category ?? 'Unclassified';
            if (!isset($categoryBreakdown[$cat])) $categoryBreakdown[$cat] = 0;
            $categoryBreakdown[$cat]++;

            if (!in_array($cat, $senders)) {
                $senders[] = $cat;
            }

            $dir = strtolower($row->sms_direction ?? 'none');
            $amt = (float)($row->sms_amount ?? 0);
            if ($dir === 'incoming' || $dir === 'received') {
                $totalReceived += $amt;
            } elseif ($dir === 'outgoing' || $dir === 'sent') {
                $totalSent += $amt;
            }

            if (!empty($row->sms_is_finance)) {
                $financeSenders[] = $cat;
            }
        }

        // Find earliest and latest SMS time
        $timeBuilder = $this->db->table('tbl_Sms s')
            ->select('MIN(s.sms_time) as first, MAX(s.sms_time) as last')
            ->where('s.sms_owner', $ownerUuid);
        $timeRow = $timeBuilder->get()->getRow();

        $periodStart = $timeRow->first ? $this->normalizeDate($timeRow->first) : $default['period_start'];
        $periodEnd = $timeRow->last ? $this->normalizeDate($timeRow->last) : $default['period_end'];

        return [
            'total_transactions' => $total,
            'total_senders' => count(array_unique($senders)),
            'finance_senders' => count(array_unique($financeSenders)),
            'category_breakdown' => $categoryBreakdown,
            'total_amount_sent' => round($totalSent, 2),
            'total_amount_received' => round($totalReceived, 2),
            'period_start' => substr($periodStart, 0, 10),
            'period_end' => substr($periodEnd, 0, 10),
        ];
    }

    /**
     * Get paginated transactions by financial category
     */
    public function get_transactions_by_category(
        string $ownerUuid,
        string $deviceId,
        string $category = '',
        int $page = 1,
        int $perPage = 50
    ): array {
        $builder = $this->db->table('tbl_Sms s')
            ->select('
                s.id, s.sms_body, s.sms_number, s.sms_time,
                a.amount, a.counterparty, a.description as transaction_type,
                a.trans_date, a.orig_sms_int_id,
                sc.category, sc.direction, sc.sender as sender_number,
                sp.sp_name as sender_name, sp.sp_confidence
            ')
            ->join('tbl_Sms_Classification sc', 'sc.sms_id = s.id', 'left')
            ->join('tbl_Analyzed_Transactions a', 'a.orig_sms_id = s.id', 'left')
            ->join('tbl_Sender_Profiles sp', 'sp.sp_number = sc.sender AND sp.sp_owner = s.sms_owner', 'left')
            ->where('s.sms_owner', $ownerUuid);

        if (!empty($category) && $category !== 'all') {
            $builder->where('sc.category', $category);
        }

        // Get total count
        $countBuilder = clone $builder;
        $total = $countBuilder->countAllResults();

        // Get paginated results
        $offset = ($page - 1) * $perPage;
        $rows = $builder->orderBy('s.sms_time', 'DESC')
            ->limit($perPage, $offset)
            ->get()
            ->getResult();

        $transactions = [];
        foreach ($rows as $row) {
            $body = '';
            try {
                $body = base64_decode($row->sms_body);
                if (mb_detect_encoding($body, 'UTF-8', true) === false) {
                    $body = $row->sms_body;
                }
            } catch (\Exception $e) {
                $body = $row->sms_body ?? '';
            }

            $transactions[] = [
                'id' => (int)$row->id,
                'sms_body' => mb_substr($body, 0, 500),
                'amount' => $row->amount ? (float)$row->amount : null,
                'direction' => $row->direction ?? 'none',
                'counterparty' => $row->counterparty,
                'category' => $row->category ?? 'Unclassified',
                'sender_name' => $row->sender_name ?? $row->sender_number ?? 'Unknown',
                'sender_number' => $row->sender_number ?? '',
                'transaction_type' => $row->transaction_type ?? 'unknown',
                'trans_date' => $row->trans_date,
                'balance' => null,
            ];
        }

        return [
            'transactions' => $transactions,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
        ];
    }

    /**
     * Get sender profiles with transaction counts
     */
    public function get_sender_profiles(string $ownerUuid, string $deviceId): array {
        $builder = $this->db->table('tbl_Sender_Profiles sp')
            ->select('
                sp.sp_number as number,
                MAX(sp.sp_name) as name,
                MAX(sp.sp_category) as category,
                MAX(sp.sp_is_finance) as is_finance,
                MAX(sp.sp_confidence) as confidence,
                COUNT(s.id) as transaction_count,
                SUM(a.amount) as total_amount
            ')
            ->join('tbl_Sms s', 's.sms_number = sp.sp_number AND s.sms_owner = sp.sp_owner', 'left')
            ->join('tbl_Analyzed_Transactions a', 'a.orig_sms_id = s.id', 'left')
            ->where('sp.sp_owner', $ownerUuid)
            ->groupBy('sp.sp_number')
            ->orderBy('transaction_count', 'DESC');

        $rows = $builder->get()->getResult();
        $profiles = [];

        foreach ($rows as $row) {
            $profiles[] = [
                'number' => $row->number ?? '',
                'name' => $row->name ?? '',
                'category' => $row->category ?? 'Unclassified',
            ];
        }

        return $profiles;
    }

    /**
     * Get full report data for a given month/year using tbl_Sms canonical LLM fields
     */
    public function getReportData(int $year, int $month, array $tokens = []): array {
        $monthStr     = sprintf('%04d-%02d', $year, $month);

        if (empty($tokens)) {
            return [
                'year'       => $year,
                'month'      => $month,
                'month_name' => date('F', mktime(0, 0, 0, $month, 1, $year)),
                'labels'     => [],
                'inflow'     => [],
                'outflow'    => [],
                'total_in'   => 0,
                'total_out'  => 0,
                'net'        => 0,
                'categories' => ['Received' => 0, 'Sent' => 0, 'Withdraw' => 0, 'Fuliza' => 0, 'Other' => 0],
                'top_counterparties' => [],
                'tx_count'   => 0,
            ];
        }

        $escapedTokens = array_map(fn($t) => $this->db->escape($t), $tokens);
        $inClause = implode(',', $escapedTokens);

        $allSms = $this->db->query("
            SELECT sms_amount, sms_direction, sms_trans_date, sms_time, sms_category, sms_counterparty, sms_transaction_type
            FROM tbl_Sms
            WHERE sms_is_finance = 1
              AND sms_owner IN ($inClause)
        ")->getResult();

        $byDate = [];
        $availableMonths = [];
        foreach ($allSms as $sms) {
            $rawTime = !empty($sms->sms_trans_date) ? $sms->sms_trans_date : $this->normalizeDate($sms->sms_time);
            $dateKey = substr($rawTime, 0, 10);
            if ($dateKey && strlen($dateKey) === 10) {
                $mKey = substr($dateKey, 0, 7);
                $availableMonths[$mKey] = true;
                if ($mKey === $monthStr) {
                    $byDate[$dateKey][] = $sms;
                }
            }
        }

        if (empty($byDate) && !empty($availableMonths)) {
            $months = array_keys($availableMonths);
            sort($months);
            $monthStr = end($months);
            $parts = explode('-', $monthStr);
            $year = (int)$parts[0];
            $month = (int)$parts[1];

            foreach ($allSms as $sms) {
                $rawTime = !empty($sms->sms_trans_date) ? $sms->sms_trans_date : $this->normalizeDate($sms->sms_time);
                $dateKey = substr($rawTime, 0, 10);
                if (substr($dateKey, 0, 7) === $monthStr) {
                    $byDate[$dateKey][] = $sms;
                }
            }
        }

        $daysInMonth  = date('t', mktime(0, 0, 0, $month, 1, $year));
        $labels   = [];
        $inflow   = [];
        $outflow  = [];

        for ($d = 1; $d <= $daysInMonth; $d++) {
            $dateKey    = sprintf('%04d-%02d-%02d', $year, $month, $d);
            $labels[]   = date('d', strtotime($dateKey));
            $dayIn      = 0;
            $dayOut     = 0;

            foreach ($byDate[$dateKey] ?? [] as $sms) {
                $amount = (float)($sms->sms_amount ?? 0);
                $dir    = strtolower($sms->sms_direction ?? '');
                if (in_array($dir, ['incoming', 'received', 'money_in', 'in'])) {
                    $dayIn += $amount;
                } elseif (in_array($dir, ['outgoing', 'sent', 'money_out', 'out'])) {
                    $dayOut += $amount;
                }
            }

            $inflow[]  = $dayIn;
            $outflow[] = $dayOut;
        }

        $totalIn  = array_sum($inflow);
        $totalOut = array_sum($outflow);
        $net      = $totalIn - $totalOut;

        $categories = [];
        $totalFees = 0.0;
        $totalLoans = 0.0;

        foreach ($byDate as $rows) {
            foreach ($rows as $sms) {
                $amount = (float)($sms->sms_amount ?? 0);
                $fee = (float)($sms->sms_fee ?? 0);
                $totalFees += $fee;

                $type = strtolower($sms->sms_transaction_type ?? '');
                if ($type === 'loan') {
                    $totalLoans += $amount;
                }

                $cat = $sms->sms_category ?? 'Unclassified';
                if (!isset($categories[$cat])) {
                    $categories[$cat] = 0.0;
                }
                $categories[$cat] += $amount;
            }
        }
        arsort($categories);

        $topRows = $this->db->query("
            SELECT sms_counterparty as counterparty, SUM(sms_amount) as total_amount, COUNT(*) as trans_count
            FROM tbl_Sms
            WHERE sms_is_finance = 1
              AND sms_is_transactional = 1
              AND sms_owner IN ($inClause)
              AND sms_counterparty IS NOT NULL
              AND sms_counterparty != ''
              AND (
                  (sms_trans_date IS NOT NULL AND YEAR(sms_trans_date) = ? AND MONTH(sms_trans_date) = ?)
                  OR 
                  (sms_trans_date IS NULL AND sms_time IS NOT NULL AND YEAR(sms_time) = ? AND MONTH(sms_time) = ?)
              )
            GROUP BY sms_counterparty
            ORDER BY total_amount DESC
            LIMIT 5
        ", [$year, $month, $year, $month])->getResult();

        $txCount = 0;
        foreach ($byDate as $rows) $txCount += count($rows);

        return [
            'year'       => $year,
            'month'      => $month,
            'month_name' => date('F', mktime(0, 0, 0, $month, 1, $year)),
            'labels'     => $labels,
            'inflow'     => $inflow,
            'outflow'    => $outflow,
            'total_in'   => $totalIn,
            'total_out'  => $totalOut,
            'net'        => $net,
            'categories' => $categories,
            'top_counterparties' => $topRows,
            'tx_count'   => $txCount,
            'total_fees' => $totalFees,
            'total_loans'=> $totalLoans,
        ];
    }

    /**
     * Get category breakdown for a specific upload batch
     * Returns counts per financial category from classifications
     */
    public function get_upload_category_breakdown(string $lootUuid): array {
        $rows = $this->db->query("
            SELECT COALESCE(latest.cat, 'Unclassified') as category, COUNT(*) as count
            FROM tbl_Sms s
            LEFT JOIN (
                SELECT sc1.sms_id, sc1.category as cat
                FROM tbl_Sms_Classification sc1
                INNER JOIN (
                    SELECT sms_id, MAX(created_at) as max_created
                    FROM tbl_Sms_Classification
                    GROUP BY sms_id
                ) sc2 ON sc1.sms_id = sc2.sms_id AND sc1.created_at = sc2.max_created
            ) latest ON latest.sms_id = s.id
            WHERE s.sms_loot_source = ?
            GROUP BY category
            ORDER BY count DESC
        ", [$lootUuid])->getResult();

        $breakdown = [];
        foreach ($rows as $row) {
            $breakdown[$row->category] = (int)$row->count;
        }
        return $breakdown;
    }

    /**
     * Get financial summary for a specific upload batch
     */
    public function get_upload_financial_summary(string $lootUuid): array {
        $row = $this->db->query("
            SELECT
                COALESCE(SUM(s.sms_amount), 0) as total_amount,
                COALESCE(SUM(CASE WHEN LOWER(s.sms_direction) IN ('incoming', 'received', 'money in') THEN s.sms_amount ELSE 0 END), 0) as inflow_amount,
                COALESCE(SUM(CASE WHEN LOWER(s.sms_direction) IN ('outgoing', 'sent', 'money out') THEN s.sms_amount ELSE 0 END), 0) as outflow_amount,
                COUNT(DISTINCT s.sms_counterparty) as counterparties,
                COUNT(s.id) as analyzed_count
            FROM tbl_Sms s
            WHERE s.sms_loot_source = ? AND s.sms_is_transactional = 1 AND s.sms_amount IS NOT NULL
        ", [$lootUuid])->getRow();

        return [
            'total_amount' => (float)($row->total_amount ?? 0),
            'inflow_amount' => (float)($row->inflow_amount ?? 0),
            'outflow_amount' => (float)($row->outflow_amount ?? 0),
            'counterparties' => (int)($row->counterparties ?? 0),
            'analyzed_count' => (int)($row->analyzed_count ?? 0),
        ];
    }

    /**
     * Get direction breakdown for a specific upload batch
     * Returns counts by LLM direction: incoming (Money In), outgoing (Money Out), none (Unknown)
     */
    public function get_upload_direction_breakdown(string $lootUuid): array {
        $rows = $this->db->query("
            SELECT 
                CASE 
                    WHEN LOWER(COALESCE(latest.direction, 'none')) IN ('incoming', 'received') THEN 'Money In'
                    WHEN LOWER(COALESCE(latest.direction, 'none')) IN ('outgoing', 'sent') THEN 'Money Out'
                    WHEN LOWER(COALESCE(latest.direction, 'none')) IN ('balance', 'balance_inquiry') THEN 'Balance Inquiry'
                    ELSE 'Unknown'
                END as direction_label,
                COUNT(*) as count
            FROM tbl_Sms s
            LEFT JOIN (
                SELECT sc1.sms_id, sc1.direction
                FROM tbl_Sms_Classification sc1
                INNER JOIN (
                    SELECT sms_id, MAX(created_at) as max_created
                    FROM tbl_Sms_Classification
                    GROUP BY sms_id
                ) sc2 ON sc1.sms_id = sc2.sms_id AND sc1.created_at = sc2.max_created
            ) latest ON latest.sms_id = s.id
            WHERE s.sms_loot_source = ?
            GROUP BY direction_label
            ORDER BY count DESC
        ", [$lootUuid])->getResult();

        $breakdown = [];
        foreach ($rows as $row) {
            $breakdown[$row->direction_label] = (int)$row->count;
        }
        return $breakdown;
    }

    /**
     * Get transaction type breakdown for a specific upload batch
     * Returns counts by LLM transaction_type from analyzed transactions
     */
    public function get_upload_transaction_type_breakdown(string $lootUuid): array {
        $rows = $this->db->query("
            SELECT COALESCE(a.description, 'unknown') as transaction_type, COUNT(*) as count
            FROM tbl_Analyzed_Transactions a
            INNER JOIN tbl_Sms s ON s.id = a.orig_sms_int_id OR s.sms__id = a.orig_sms_id
            WHERE s.sms_loot_source = ?
            GROUP BY transaction_type
            ORDER BY count DESC
        ", [$lootUuid])->getResult();

        $breakdown = [];
        foreach ($rows as $row) {
            $breakdown[$row->transaction_type] = (int)$row->count;
        }
        return $breakdown;
    }

    /**
     * Get top counterparties by volume for a specific upload batch
     */
    public function get_upload_top_counterparties(string $lootUuid): array {
        $rows = $this->db->query("
            SELECT a.counterparty, SUM(a.amount) as total_amount, COUNT(*) as trans_count
            FROM tbl_Analyzed_Transactions a
            INNER JOIN tbl_Sms s ON s.id = a.orig_sms_int_id OR s.sms__id = a.orig_sms_id
            WHERE s.sms_loot_source = ?
              AND a.counterparty IS NOT NULL
              AND a.counterparty != ''
              AND a.counterparty != 'Unknown'
            GROUP BY a.counterparty
            ORDER BY total_amount DESC
            LIMIT 5
        ", [$lootUuid])->getResultArray();

        return $rows;
    }

    public function getAllTransactionsForUser(int $userId): array
    {
        $tokenType = \CodeIgniter\Shield\Authentication\Authenticators\AccessTokens::ID_TYPE_ACCESS_TOKEN;

        $rawTokens = [];
        $tokenRows = $this->db->query("
            SELECT DISTINCT s.sms_owner AS tk FROM tbl_Sms s
            INNER JOIN auth_identities i ON i.secret = SHA2(s.sms_owner, 256)
            WHERE i.user_id = ? AND i.type = ?
            UNION
            SELECT DISTINCT l.loot_Owner AS tk FROM tbl_Loot l
            INNER JOIN auth_identities i ON i.secret = SHA2(l.loot_Owner, 256)
            WHERE i.user_id = ? AND i.type = ?
        ", [$userId, $tokenType, $userId, $tokenType])->getResult();

        foreach ($tokenRows as $r) {
            if (!empty($r->tk)) $rawTokens[] = $r->tk;
        }

        if (empty($rawTokens)) return [];

        $rows = $this->db->table('tbl_Sms s')
            ->select('
                s.sms_time, s.sms_body, s.sms_number,
                s.sms_amount, s.sms_balance, s.sms_counterparty,
                s.sms_direction, s.sms_transaction_type,
                sc.category, sc.direction as classified_direction,
                a.amount, a.counterparty as analyzed_counterparty,
                a.description, a.trans_date
            ')
            ->join('tbl_Sms_Classification sc', 'sc.sms_id = s.id', 'left')
            ->join('tbl_Analyzed_Transactions a', 'a.orig_sms_id = s.id', 'left')
            ->whereIn('s.sms_owner', $rawTokens)
            ->orderBy('s.sms_time', 'DESC')
            ->get()
            ->getResultArray();

        return $rows;
    }

    public function evaluate_budgets_for_user(int $userId): void {
        $budgets = $this->db->table('tbl_Budgets')
            ->where('user_id', $userId)
            ->get()
            ->getResult();

        foreach ($budgets as $budget) {
            $category = $budget->category;
            $limit = (float) $budget->amount_limit;
            if ($limit <= 0) continue;

            if ($budget->period === 'weekly') {
                $startDate = date('Y-m-d H:i:s', strtotime('-7 days'));
            } else {
                $startDate = date('Y-m-01 00:00:00');
            }

            $spentQuery = $this->db->table('tbl_Analyzed_Transactions a')
                ->selectSum('a.amount', 'total')
                ->join('tbl_Sms s', 's.sms__id = a.orig_sms_id')
                ->join('tbl_Sms_Classification sc', 'sc.sms_id = s.id')
                ->where('s.sms_user_id', $userId)
                ->where('sc.category', $category)
                ->where('s.sms_time >=', strtotime($startDate) * 1000)
                ->get()
                ->getRow();

            $spent = (float) ($spentQuery->total ?? 0.0);

            $updates = [];
            if ($spent >= $limit) {
                if (!$budget->threshold_alert_100) {
                    $updates['threshold_alert_100'] = 1;
                    $updates['threshold_alert_80'] = 1;
                    $updates['alert_sent_at'] = date('Y-m-d H:i:s');
                    log_message('warning', "Budget Alert 100% breached for user {$userId} in category {$category}: Spent {$spent} of {$limit}");
                }
            } elseif ($spent >= 0.8 * $limit) {
                if (!$budget->threshold_alert_80) {
                    $updates['threshold_alert_80'] = 1;
                    $updates['alert_sent_at'] = date('Y-m-d H:i:s');
                    log_message('warning', "Budget Alert 80% breached for user {$userId} in category {$category}: Spent {$spent} of {$limit}");
                }
            } else {
                if ($budget->threshold_alert_80 || $budget->threshold_alert_100) {
                    $updates['threshold_alert_80'] = 0;
                    $updates['threshold_alert_100'] = 0;
                    $updates['alert_sent_at'] = null;
                }
            }

            if (!empty($updates)) {
                $this->db->table('tbl_Budgets')
                    ->where('id', $budget->id)
                    ->update($updates);
            }
        }
    }
}
