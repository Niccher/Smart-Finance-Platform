<?php

namespace App\Models;

use CodeIgniter\Model;

class BudgetModel extends Model
{
    protected $table      = 'tbl_Budgets';
    protected $primaryKey = 'id';
    protected $allowedFields = [
        'user_id', 'category', 'label', 'amount_limit', 'period', 'rollover', 'created_at'
    ];

    public function getBudgets(?int $userId = null): array
    {
        if ($userId) {
            return $this->where('user_id', $userId)->findAll();
        }
        return $this->findAll();
    }

    public function saveBudget(array $data, int $userId = 0): bool
    {
        if ($userId > 0) {
            $data['user_id'] = $userId;
        }
        $data['created_at'] = date('Y-m-d H:i:s');
        if (!empty($data['id'])) {
            $builder = $this;
            if ($userId > 0) {
                $builder = $builder->where('user_id', $userId);
            }
            return $builder->update($data['id'], $data);
        }
        return $this->insert($data) !== false;
    }

    public function deleteBudget(int $id, int $userId = 0): bool
    {
        $builder = $this->where('id', $id);
        if ($userId > 0) {
            $builder->where('user_id', $userId);
        }
        return (bool)$builder->delete();
    }

    public function getBudgetProgress(array $budgets, int $userId = 0): array
    {
        $results = [];
        $now     = time();
        $builder = $this->db->table('tbl_Sms s')
            ->select('s.*, sc.direction as cl_direction')
            ->join('tbl_Sms_Classification sc', 'sc.sms_id = s.id', 'left');
        if ($userId > 0) {
            $builder->where('s.sms_user_id', $userId);
        }
        $allSms = $builder->get()->getResult();

        foreach ($budgets as $budget) {
            if ($budget['period'] === 'weekly') {
                $windowStart = strtotime('last monday', $now);
                if (date('N', $now) == 1) $windowStart = strtotime('today', $now);
            } else {
                $windowStart = mktime(0, 0, 0, (int)date('m'), 1);
            }

            $carryOver = 0;
            if (!empty($budget['rollover']) && $budget['period'] === 'monthly') {
                $lastMonthStart = mktime(0, 0, 0, (int)date('m') - 1, 1);
                $lastMonthEnd   = mktime(23, 59, 59, (int)date('m'), 0);
                foreach ($allSms as $sms) {
                    $ts = is_numeric($sms->sms_time) && $sms->sms_time > 1000000000000
                        ? (int)($sms->sms_time / 1000)
                        : (is_numeric($sms->sms_time) ? (int)$sms->sms_time : strtotime((string)$sms->sms_time));
                    if ($ts < $lastMonthStart || $ts > $lastMonthEnd) continue;
                    $dir  = strtolower($sms->cl_direction ?? '');
                    $body = strtolower(base64_decode($sms->sms_body));
                    if ($this->matchesCategory($budget['category'], $dir, $body)) {
                        if (preg_match('/ksh\.?\s*([\d,]+\.?\d{0,2})/i', $body, $m)) {
                            $carryOver -= (float)str_replace(',', '', $m[1]);
                        }
                    }
                }
                $carryOver = max(0, (float)$budget['amount_limit'] + $carryOver);
            }

            $spent = 0.0;
            $catLower = strtolower($budget['category']);
            foreach ($allSms as $sms) {
                $ts = is_numeric($sms->sms_time) && $sms->sms_time > 1000000000000
                    ? (int)($sms->sms_time / 1000)
                    : (is_numeric($sms->sms_time) ? (int)$sms->sms_time : strtotime((string)$sms->sms_time));
                if ($ts < $windowStart) continue;
                $dir  = strtolower($sms->cl_direction ?? '');
                $body = strtolower(base64_decode($sms->sms_body));
                if ($this->matchesCategory($budget['category'], $dir, $body)) {
                    if (preg_match('/ksh\.?\s*([\d,]+\.?\d{0,2})/i', $body, $m)) {
                        $spent += (float)str_replace(',', '', $m[1]);
                    }
                }
            }

            $effectiveLimit = (float)$budget['amount_limit'] + $carryOver;
            $percentage = $effectiveLimit > 0 ? min(round(($spent / $effectiveLimit) * 100, 1), 100) : 0;

            $results[] = array_merge($budget, [
                'spent'           => $spent,
                'carry_over'      => $carryOver,
                'effective_limit' => $effectiveLimit,
                'percentage'      => $percentage,
                'remaining'       => max(0, $effectiveLimit - $spent),
                'over_limit'      => $spent > $effectiveLimit,
            ]);
        }
        return $results;
    }

    private function matchesCategory(string $catLower, string $dir, string $body): bool
    {
        $catLower = strtolower($catLower);
        if ($catLower === 'total outflow') return $dir === 'outgoing';
        if ($catLower === 'received') return $dir === 'incoming';
        if ($catLower === 'paybill') return $dir === 'outgoing' && strpos($body, 'paybill') !== false;
        if ($catLower === 'till') return $dir === 'outgoing' && strpos($body, 'till') !== false;
        if ($catLower === 'sent to mobile') return $dir === 'outgoing';
        if ($catLower === 'withdrawal') return $dir === 'outgoing';
        if ($catLower === 'fuliza') return strpos($body, 'fuliza') !== false && strpos($body, 'taken') !== false;
        return $dir === 'outgoing' || $dir === 'incoming';
    }
}
