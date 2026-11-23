<?php

namespace App\Models;

use CodeIgniter\Model;

class GoalModel extends Model
{
    protected $table      = 'tbl_Spending_Goals';
    protected $primaryKey = 'id';
    protected $allowedFields = [
        'user_id', 'category', 'label', 'target_amount',
        'period', 'rollover', 'active', 'created_at', 'updated_at'
    ];
    protected $useTimestamps = true;

    public function getForUser(int $userId): array
    {
        return $this->where('user_id', $userId)->where('active', 1)->findAll();
    }

    public function getProgress(array $goals): array
    {
        $db = \Config\Database::connect();
        $results = [];
        $now = time();

        foreach ($goals as $goal) {
            if ($goal['period'] === 'weekly') {
                $windowStart = strtotime('last monday', $now);
                if (date('N', $now) == 1) $windowStart = strtotime('today', $now);
            } else {
                $windowStart = mktime(0, 0, 0, (int)date('m'), 1);
            }

            $spent = 0.0;
            $rows = $db->table('tbl_Sms s')
                ->select('s.sms_time, s.sms_body, sc.direction')
                ->join('tbl_Sms_Classification sc', 'sc.sms_id = s.id', 'left')
                ->where('s.sms_user_id', $userId)
                ->where('sc.category', $goal['category'])
                ->get()->getResult();

            foreach ($rows as $row) {
                $ts = is_numeric($row->sms_time) && $row->sms_time > 1000000000000
                    ? (int)($row->sms_time / 1000)
                    : (is_numeric($row->sms_time) ? (int)$row->sms_time : strtotime((string)$row->sms_time));
                if ($ts < $windowStart) continue;
                if (preg_match('/ksh\.?\s*([\d,]+\.?\d{0,2})/i', base64_decode($row->sms_body), $m)) {
                    $spent += (float)str_replace(',', '', $m[1]);
                }
            }

            $target = (float)$goal['target_amount'];
            $results[] = array_merge($goal, [
                'spent'      => $spent,
                'percentage' => $target > 0 ? min(100, round(($spent / $target) * 100, 1)) : 0,
                'remaining'  => max(0, $target - $spent),
            ]);
        }
        return $results;
    }
}
