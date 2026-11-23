<?php

namespace App\Controllers;

use App\Models\UploadModel;

class AnalysisCallbackController extends BaseController
{
    public function index()
    {
        try {
            $limit = $this->request->getGet('limit') ?? 1000;
            $mod_uploads = new UploadModel();
            $db = \Config\Database::connect();

            // 1. Fetch Custom Rules, grouped by user_id
            $categoryRules = [
                'global' => [],
                'users'  => []
            ];
            $rulesQuery = $db->table('tbl_Category_Rules')->get()->getResultArray();
            foreach ($rulesQuery as $r) {
                $ruleItem = [
                    'id'         => (int)$r['id'],
                    'keyword'    => strtolower($r['keyword']),
                    'category'   => $r['correct_category'],
                    'match_type' => $r['match_type'] ?? 'exact',
                ];
                if (empty($r['user_id'])) {
                    $categoryRules['global'][] = $ruleItem;
                } else {
                    $userIdKey = (int)$r['user_id'];
                    if (!isset($categoryRules['users'][$userIdKey])) {
                        $categoryRules['users'][$userIdKey] = [];
                    }
                    $categoryRules['users'][$userIdKey][] = $ruleItem;
                }
            }

            // 2. Fetch SMS not yet analyzed
            $smsList = $db->table('tbl_Sms s')
                ->select('s.*')
                ->join('tbl_Analyzed_Transactions a', 'a.orig_sms_id = s.sms__id', 'left')
                ->where('a.id', null)
                ->orderBy('s.sms_time', 'DESC')
                ->limit($limit)
                ->get()
                ->getResult();

            // 3. Map auth identities secrets to user IDs
            $tokenUserMap = [];
            if ($db->tableExists('auth_identities')) {
                $identities = $db->table('auth_identities')
                    ->select('secret, user_id')
                    ->where('type', \CodeIgniter\Shield\Authentication\Authenticators\AccessTokens::ID_TYPE_ACCESS_TOKEN)
                    ->get()
                    ->getResultArray();
                foreach ($identities as $i) {
                    $tokenUserMap[$i['secret']] = (int)$i['user_id'];
                }
            }

            $processed = 0;
            $results = [];

            foreach ($smsList as $sms) {
                if (empty($sms->sms_body)) continue;
                
                $body = base64_decode($sms->sms_body);
                if ($body === false) continue;

                // Resolve owner's user_id
                $ownerHash = hash('sha256', $sms->sms_owner);
                $smsUserId = $tokenUserMap[$ownerHash] ?? null;

                // Retrieve rules relevant to this user (user rules take precedence over global ones)
                $rulesForThisUser = [];
                if ($smsUserId && isset($categoryRules['users'][$smsUserId])) {
                    $rulesForThisUser = $categoryRules['users'][$smsUserId];
                }
                $rulesForThisUser = array_merge($rulesForThisUser, $categoryRules['global']);

                $parsed = $this->deepParse($body, $sms->sms_category ?? $sms->cl_category ?? '', $rulesForThisUser);
                
                // Normalise the date: handle numeric timestamps
                $transDate = $this->normalizeDate($sms->sms_time);

                $results[] = [
                    'orig_sms_id'     => $sms->sms__id,
                    'amount'          => $parsed['amount'],
                    'counterparty'    => $parsed['counterparty'],
                    'description'     => $parsed['description'],
                    'trans_date'      => $transDate,
                    'matched_rule_id' => $parsed['matched_rule_id'],
                ];
                $processed++;
            }

            if (!empty($results)) {
                // Canonical record: write parsed data onto tbl_Sms
                foreach ($results as $r) {
                    $db->table('tbl_Sms')
                        ->where('sms__id', $r['orig_sms_id'])
                        ->update([
                            'sms_amount'           => $r['amount'],
                            'sms_counterparty'     => $r['counterparty'],
                            'sms_transaction_type' => $r['description'],
                            'sms_is_transactional' => 1,
                            'sms_trans_date'       => $r['trans_date'],
                        ]);

                    // Update Rule match telemetry
                    if (!empty($r['matched_rule_id'])) {
                        $db->table('tbl_Category_Rules')
                            ->where('id', $r['matched_rule_id'])
                            ->increment('hit_count', 1);
                        $db->table('tbl_Category_Rules')
                            ->where('id', $r['matched_rule_id'])
                            ->update(['last_matched_at' => date('Y-m-d H:i:s')]);
                    }
                }
            }

            @session_write_close();
            if (ob_get_length()) ob_clean();

            return $this->response->setJSON([
                'status'  => 'success',
                'message' => "Successfully analyzed $processed transactions.",
                'count'   => $processed
            ]);

        } catch (\Exception $e) {
            if (ob_get_length()) ob_clean();
            return $this->response->setStatusCode(500)->setJSON([
                'status'  => 'error',
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * Normalize any date/timestamp format to Y-m-d H:i:s
     */
    private function normalizeDate($time): string {
        if (empty($time)) return date('Y-m-d H:i:s');
        // Handle JavaScript millisecond timestamps
        if (is_numeric($time) && $time > 1000000000000) {
            return date('Y-m-d H:i:s', (int)($time / 1000));
        }
        $timestamp = is_numeric($time) ? (int)$time : strtotime((string)$time);
        return date('Y-m-d H:i:s', $timestamp ?: time());
    }

    private function deepParse($body, $category, $rules = []): array
    {
        $data = [
            'trans_id'        => '',
            'amount'          => 0,
            'counterparty'    => 'Unknown',
            'description'     => $category,
            'matched_rule_id' => null,
        ];

        // 1. Extract Transaction ID
        if (preg_match('/\b([A-Z0-9]{10})\s+(Confirmed|transferred|sent)/i', $body, $m)) {
            $data['trans_id'] = $m[1];
        }

        // 2. Extract Amount
        if (preg_match('/ksh\.?\s*([\d,]+\.?\d{0,2})/i', $body, $m)) {
            $data['amount'] = (float)str_replace(',', '', $m[1]);
        }

        // 3. Extract Counterparty based on content patterns
        if (preg_match('/sent to\s+([A-Z][^0-9]+?)\s+[\d]/i', $body, $m)) {
            $data['counterparty'] = trim($m[1]);
        } elseif (preg_match('/paid to\s+([^.]+)\./i', $body, $m)) {
            $data['counterparty'] = trim($m[1]);
        } elseif (preg_match('/received\s+ksh.*?\s+from\s+([A-Z][^0-9]+?)\s+[\d]/i', $body, $m)) {
            $data['counterparty'] = trim($m[1]);
        } elseif (preg_match('/from\s+([A-Z][A-Z ]+(?:BANK|LIMITED|GROUP|SACCO|KENYA)?)\s+on/i', $body, $m)) {
            $data['counterparty'] = trim($m[1]);
        } elseif (preg_match('/transferred\s+to\s+([A-Z][^0-9.]+?)[\s.]/i', $body, $m)) {
            $data['counterparty'] = trim($m[1]);
        } elseif (preg_match('/withdrawn\s+from\s+([A-Z][A-Z ]+?)\s+agent/i', $body, $m)) {
            $data['counterparty'] = trim($m[1]);
        }

        // Clean up counterparty
        if ($data['counterparty'] !== 'Unknown') {
            $data['counterparty'] = preg_replace('/\s+(on|via|using|for)$/i', '', $data['counterparty']);
            $data['counterparty'] = trim($data['counterparty'], " .,");
        }

        // Apply Smart Auto-Fix Rules
        $cp = strtolower($data['counterparty']);
        foreach ($rules as $rule) {
            $keyword = $rule['keyword'];
            $matchType = $rule['match_type'];
            
            $isMatch = false;
            if ($matchType === 'contains') {
                $isMatch = str_contains($cp, $keyword);
            } else { // exact
                $isMatch = ($cp === $keyword);
            }

            if ($isMatch) {
                $data['description'] = $rule['category'];
                $data['matched_rule_id'] = $rule['id'];
                break;
            }
        }

        return $data;
    }

    public function saveRule()
    {
        $keyword    = $this->request->getPost('keyword');
        $category   = $this->request->getPost('category');
        $matchType  = $this->request->getPost('match_type') ?? 'exact'; // 'exact' or 'contains'
        $transId    = $this->request->getPost('trans_id'); // Update the specific transaction immediately

        if (empty($keyword) || empty($category)) {
            return $this->response->setJSON(['status' => 'error', 'message' => 'Keyword and Category required.']);
        }

        $db     = \Config\Database::connect();
        $user   = auth()->user();
        $userId = $user?->id;

        // Upsert Rule — scoped to this user
        $builder  = $db->table('tbl_Category_Rules');
        $existing = $builder
            ->where('keyword', $keyword)
            ->where('user_id', $userId)
            ->get()->getRow();

        if ($existing) {
            $builder->where('id', $existing->id)->update([
                'correct_category' => $category,
                'match_type'       => $matchType,
            ]);
        } else {
            $builder->insert([
                'user_id'          => $userId,
                'keyword'          => $keyword,
                'correct_category' => $category,
                'match_type'       => $matchType,
                'rule_source'      => 'user',
                'hit_count'        => 0,
                'created_at'       => date('Y-m-d H:i:s'),
            ]);
        }

        // Apply immediately to the specific transaction that triggered the rule
        if (!empty($transId)) {
            $db->table('tbl_Sms')->where('sms__id', $transId)->update(['sms_transaction_type' => $category]);
        }

        // Retroactively apply to all past transactions owned by this user
        // that share the same counterparty name (exact match or LIKE for 'contains')
        if ($userId) {
            $smsBuilder = $db->table('tbl_Sms')->where('sms_user_id', $userId);
            if ($matchType === 'contains') {
                $smsBuilder->like('sms_counterparty', $keyword, 'both');
            } else {
                $smsBuilder->where('sms_counterparty', $keyword);
            }
            $smsBuilder->update([
                'sms_transaction_type' => $category,
                'sms_category'         => $category,
            ]);
        }

        return $this->response->setJSON([
            'status'  => 'success',
            'message' => "Rule saved! Future and past transactions for '$keyword' will be mapped to '$category'.",
        ]);
    }
}
