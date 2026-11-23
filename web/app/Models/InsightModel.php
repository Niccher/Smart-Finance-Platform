<?php

namespace App\Models;

use CodeIgniter\Model;

class InsightModel extends Model
{
    private function normalizeTimestamp($smsTime, $transDate = null): int {
        if (!empty($transDate) && strlen($transDate) >= 10) {
            $ts = strtotime($transDate);
            if ($ts > 0) return $ts;
        }
        if (is_numeric($smsTime) && $smsTime > 1000000000000) {
            return (int)($smsTime / 1000);
        }
        return is_numeric($smsTime) ? (int)$smsTime : (strtotime((string)$smsTime) ?: time());
    }

    private function applyScope($builder, ?string $deviceToken = null, ?int $userId = null): bool {
        if ($userId !== null && $userId > 0) {
            $builder->where('sms_user_id', $userId);
            return true;
        }
        if (!empty($deviceToken)) {
            $builder->where('sms_owner', $deviceToken);
            return true;
        }
        if (function_exists('auth') && auth()->loggedIn() && auth()->id()) {
            $builder->where('sms_user_id', (int)auth()->id());
            return true;
        }
        return false;
    }

    /**
     * Spending Trends (This month vs Last month outflow)
     */
    public function getSpendingTrends(?string $deviceToken = null, ?int $userId = null): array {
        $now = time();
        $thisMonthStart = mktime(0, 0, 0, (int)date('m', $now), 1, (int)date('Y', $now));
        $lastMonthStart = mktime(0, 0, 0, (int)date('m', $now) - 1, 1, (int)date('Y', $now));
        
        $thisMonthOutflow = 0.0;
        $lastMonthOutflow = 0.0;
        
        $builder = $this->db->table('tbl_Sms')
            ->select('sms_amount, sms_direction, sms_time, sms_trans_date')
            ->where('sms_is_finance', 1);
        if (!$this->applyScope($builder, $deviceToken, $userId)) {
            return [
                'this_month' => 0.0,
                'last_month' => 0.0,
                'percentage' => 0.0,
                'trend'      => 'flat'
            ];
        }
        $allSms = $builder->get()->getResult();
        
        foreach ($allSms as $sms) {
            $dir = strtolower($sms->sms_direction ?? '');
            if ($dir !== 'outgoing' && $dir !== 'sent' && $dir !== 'money_out') continue;
            
            $ts = $this->normalizeTimestamp($sms->sms_time, $sms->sms_trans_date);
            $amount = (float)($sms->sms_amount ?? 0);
            
            if ($ts >= $thisMonthStart) {
                $thisMonthOutflow += $amount;
            } elseif ($ts >= $lastMonthStart && $ts < $thisMonthStart) {
                $lastMonthOutflow += $amount;
            }
        }
        
        $diff = $thisMonthOutflow - $lastMonthOutflow;
        $percentage = $lastMonthOutflow > 0 ? ($diff / $lastMonthOutflow) * 100 : 0;
        
        return [
            'this_month' => $thisMonthOutflow,
            'last_month' => $lastMonthOutflow,
            'percentage' => round($percentage, 1),
            'trend'      => $diff > 0 ? 'up' : ($diff < 0 ? 'down' : 'flat')
        ];
    }

    /**
     * Recurring Payments Detector
     */
    public function getRecurringPayments(?string $deviceToken = null, ?int $userId = null): array {
        $builder = $this->db->table('tbl_Sms')
            ->select('sms_counterparty as counterparty, sms_amount as amount, COUNT(*) as occurs, MAX(sms_trans_date) as last_paid')
            ->where('sms_is_finance', 1)
            ->where('sms_is_transactional', 1)
            ->where('sms_counterparty IS NOT NULL')
            ->where('sms_counterparty !=', '')
            ->where('sms_counterparty !=', 'Unknown');

        if (!$this->applyScope($builder, $deviceToken, $userId)) {
            return [];
        }

        $builder->groupBy('sms_counterparty, sms_amount')
            ->having('occurs >=', 2)
            ->orderBy('last_paid', 'DESC')
            ->limit(10);

        return $builder->get()->getResult();
    }

    /**
     * Smart Alerts Engine
     */
    public function getSmartAlerts(?string $deviceToken = null, ?int $userId = null): array {
        $alerts = [];
        $builder = $this->db->table('tbl_Sms')
            ->select('sms_amount, sms_balance, sms_direction, sms_transaction_type, sms_time, sms_trans_date, sms_counterparty')
            ->where('sms_is_finance', 1)
            ->orderBy('id', 'DESC');
        if (!$this->applyScope($builder, $deviceToken, $userId)) {
            return $alerts;
        }
        $allSms = $builder->get()->getResult();
        
        if (empty($allSms)) return $alerts;
        
        // 1. Low Balance Warning
        $latestBal = -1.0;
        foreach ($allSms as $sms) {
            $bal = (float)($sms->sms_balance ?? -1.0);
            if ($bal >= 0) {
                $latestBal = $bal;
                break;
            }
        }
        
        if ($latestBal >= 0 && $latestBal < 1000) {
            $alerts[] = [
                'type'    => 'low_balance',
                'level'   => $latestBal < 200 ? 'danger' : 'warning',
                'title'   => 'Low Balance Warning',
                'message' => 'Your current M-Pesa balance is low: Ksh ' . number_format($latestBal, 2)
            ];
        }

        // 2 & 3. Unusual Activity & Fuliza Index
        $outflowAmounts = [];
        $totalOutflow = 0.0;
        $fulizaTaken = 0.0;
        
        $recentTransactions = [];

        foreach ($allSms as $sms) {
            $dir = strtolower($sms->sms_direction ?? '');
            $type = strtolower($sms->sms_transaction_type ?? '');
            $amount = (float)($sms->sms_amount ?? 0);
            
            if ($dir === 'outgoing' || $dir === 'sent' || $dir === 'money_out') {
                $outflowAmounts[] = $amount;
                $totalOutflow += $amount;
                
                if ($amount > 0) {
                    $recentTransactions[] = ['amount' => $amount, 'sms' => $sms];
                }
            }
            
            if ($type === 'loan') {
                $fulizaTaken += $amount;
            }
        }
        
        // Processing Unusual Activity
        if (count($outflowAmounts) > 5) {
            $avgOutflow = array_sum($outflowAmounts) / count($outflowAmounts);
            $anomalyThreshold = $avgOutflow * 3;
            
            foreach (array_slice($recentTransactions, 0, 10) as $rt) {
                if ($rt['amount'] > $anomalyThreshold && $rt['amount'] > 2000) {
                    $cp = !empty($rt['sms']->sms_counterparty) ? $rt['sms']->sms_counterparty : 'recent transaction';
                    $alerts[] = [
                        'type'    => 'unusual_activity',
                        'level'   => 'warning',
                        'title'   => 'High Value Transaction',
                        'message' => 'Transaction of Ksh ' . number_format($rt['amount'], 2) . ' to ' . htmlspecialchars($cp) . ' is above your average spend of Ksh ' . number_format($avgOutflow, 0) . '.'
                    ];
                    break;
                }
            }
        }
        
        // Processing Fuliza Dependency Index
        if ($totalOutflow > 0 && $fulizaTaken > 0) {
            $fulizaRatio = ($fulizaTaken / $totalOutflow) * 100;
            if ($fulizaRatio > 15) {
                $alerts[] = [
                    'type'    => 'fuliza_index',
                    'level'   => $fulizaRatio > 40 ? 'danger' : 'warning',
                    'title'   => 'High Overdraft/Loan Usage',
                    'message' => round($fulizaRatio, 1) . '% of your recorded outflow (Ksh ' . number_format($fulizaTaken, 0) . ') came from loans/overdrafts.'
                ];
            }
        }

        return $alerts;
    }

    /**
     * AI Observations — Real computed insights for the Analytics page
     */
    public function getAIObservations(?string $deviceToken = null): array {
        $observations = [];

        $builder = $this->db->table('tbl_Sms')
            ->select('sms_amount, sms_direction, sms_transaction_type, sms_category, sms_counterparty, sms_time, sms_trans_date')
            ->where('sms_is_finance', 1)
            ->orderBy('id', 'DESC');
        if ($deviceToken) $builder->where('sms_owner', $deviceToken);
        $allSms = $builder->get()->getResult();

        if (empty($allSms)) {
            return [
                ['type' => 'neutral', 'label' => 'NO DATA YET', 'icon' => 'fa-circle-info',
                 'text' => 'No financial transactions recorded yet.'],
            ];
        }

        // --- 1. Top Counterparty Insight ---
        $cpTotals = [];
        foreach ($allSms as $sms) {
            $cp = $sms->sms_counterparty;
            if (empty($cp) || $cp === 'Unknown') continue;
            $amt = (float)($sms->sms_amount ?? 0);
            if (!isset($cpTotals[$cp])) $cpTotals[$cp] = 0;
            $cpTotals[$cp] += $amt;
        }
        if (!empty($cpTotals)) {
            arsort($cpTotals);
            $topCp = array_key_first($cpTotals);
            $topAmt = $cpTotals[$topCp];
            $observations[] = [
                'type'  => 'info',
                'label' => 'TOP RECIPIENT / COUNTERPARTY',
                'icon'  => 'fa-user-tag',
                'text'  => 'Your highest total transaction volume is with <strong>' . htmlspecialchars($topCp) . '</strong> at <strong>Ksh ' . number_format($topAmt, 2) . '</strong>.'
            ];
        }

        // --- 2. Peak Spending Day of Week ---
        $dayTotals = array_fill(0, 7, 0);
        $dayNames  = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

        foreach ($allSms as $sms) {
            $dir = strtolower($sms->sms_direction ?? '');
            if ($dir !== 'outgoing' && $dir !== 'sent' && $dir !== 'money_out') continue;
            $ts = $this->normalizeTimestamp($sms->sms_time, $sms->sms_trans_date);
            $amount = (float)($sms->sms_amount ?? 0);
            $dow = (int) date('w', $ts);
            $dayTotals[$dow] += $amount;
        }

        $peakDayIndex = array_search(max($dayTotals), $dayTotals);
        $peakDayTotal = max($dayTotals);
        if ($peakDayTotal > 0) {
            $observations[] = [
                'type'  => 'warning',
                'label' => 'PEAK SPENDING DAY',
                'icon'  => 'fa-calendar-day',
                'text'  => 'You spend the most on <strong>' . $dayNames[$peakDayIndex] . 's</strong> with a total outflow of <strong>Ksh ' . number_format($peakDayTotal, 2) . '</strong>.'
            ];
        }

        // --- 3. Month-over-Month Spending Trend ---
        $trends = $this->getSpendingTrends($deviceToken);
        if ($trends['last_month'] > 0) {
            $pct = abs($trends['percentage']);
            if ($trends['trend'] === 'down') {
                $observations[] = [
                    'type'  => 'success',
                    'label' => 'SAVINGS OPPORTUNITY',
                    'icon'  => 'fa-arrow-trend-down',
                    'text'  => 'Spending this month is <strong>' . number_format($pct, 1) . '% lower</strong> than last month (Ksh ' . number_format($trends['last_month'], 0) . ' → Ksh ' . number_format($trends['this_month'], 0) . ').'
                ];
            } elseif ($trends['trend'] === 'up') {
                $observations[] = [
                    'type'  => 'danger',
                    'label' => 'SPENDING INCREASE',
                    'icon'  => 'fa-arrow-trend-up',
                    'text'  => 'Spending this month is <strong>' . number_format($pct, 1) . '% higher</strong> than last month (Ksh ' . number_format($trends['last_month'], 0) . ' → Ksh ' . number_format($trends['this_month'], 0) . ').'
                ];
            }
        }

        return $observations;
    }

    /**
     * AI Financial Health Score (0-100)
     */
    public function getFinancialHealthScore(?string $deviceToken = null, ?int $userId = null): array {
        $builder = $this->db->table('tbl_Sms')
            ->select('sms_amount, sms_direction, sms_transaction_type')
            ->where('sms_is_finance', 1);
        if (!$this->applyScope($builder, $deviceToken, $userId)) {
            return [
                'score' => 50,
                'color' => '#FFA502',
                'tips'  => ['Awaiting financial transaction data to calculate accurate score.']
            ];
        }
        $allSms = $builder->get()->getResult();
        
        $score = 100;
        $inflow = 0.0;
        $outflow = 0.0;
        $fuliza = 0.0;

        foreach ($allSms as $sms) {
            $dir    = strtolower($sms->sms_direction ?? '');
            $type   = strtolower($sms->sms_transaction_type ?? '');
            $amount = (float)($sms->sms_amount ?? 0);

            if ($dir === 'outgoing' || $dir === 'sent' || $dir === 'money_out') {
                $outflow += $amount;
            } elseif ($dir === 'incoming' || $dir === 'received' || $dir === 'money_in') {
                $inflow += $amount;
            }
            
            if ($type === 'loan') {
                $fuliza += $amount;
            }
        }

        $tips = [];

        if ($inflow == 0 && $outflow == 0) {
            return [
                'score' => 50,
                'color' => '#FFA502',
                'tips'  => ['Awaiting financial transaction data to calculate accurate score.']
            ];
        }

        // 1. Savings Ratio (40 points)
        if ($outflow > $inflow) {
            $ratio = $inflow == 0 ? 2 : ($outflow / $inflow);
            if ($ratio > 1.5) { $score -= 35; $tips[] = "Spending significantly exceeds income (High Risk)."; }
            elseif ($ratio > 1.1) { $score -= 20; $tips[] = "Spending exceeds income (Moderate Risk)."; }
            elseif ($ratio > 1.0) { $score -= 10; $tips[] = "Spending slightly higher than income."; }
        } else {
            $tips[] = "Healthy cash flow (Inflow >= Outflow).";
        }

        // 2. Loan Dependency (40 points)
        if ($outflow > 0) {
            $fulizaRatio = $fuliza / $outflow;
            if ($fulizaRatio > 0.4) { $score -= 40; $tips[] = "Heavy loan reliance detected (>40% of spend)."; }
            elseif ($fulizaRatio > 0.2) { $score -= 20; $tips[] = "Moderate loan usage (>20% of spend)."; }
            elseif ($fulizaRatio > 0.05) { $score -= 5; $tips[] = "Low loan usage."; }
        } else {
            $tips[] = "Independent spending (No loan reliance).";
        }

        $score = max(0, min(100, $score));
        $color = $score >= 80 ? '#2ED573' : ($score >= 50 ? '#FFA502' : '#FF4757');

        return [
            'score' => $score,
            'color' => $color,
            'tips'  => $tips
        ];
    }

    /**
     * Subscription & Ghost Fee Insights
     */
    public function getSubscriptionInsights(?string $deviceToken = null): array
    {
        $recurring = $this->getRecurringPayments($deviceToken);
        $subscriptions = [];

        foreach ($recurring as $item) {
            $lastPaid = strtotime($item->last_paid);
            $nextDueDate = date('Y-m-d', strtotime('+30 days', $lastPaid));
            $daysLeft = (int)ceil((strtotime($nextDueDate) - time()) / 86400);

            $subscriptions[] = [
                'counterparty'  => $item->counterparty,
                'amount'        => (float)$item->amount,
                'occurrences'   => (int)$item->occurs,
                'last_paid'     => $item->last_paid,
                'next_due_date' => $nextDueDate,
                'days_remaining' => $daysLeft,
                'status'        => $daysLeft <= 3 ? 'due_soon' : 'active',
            ];
        }

        return $subscriptions;
    }

    /**
     * Reversal & Refund Reconciliation Summary
     */
    public function getReconciliationSummary(?string $deviceToken = null): array
    {
        $builder = $this->db->table('tbl_Sms s')
            ->select('
                COUNT(CASE WHEN sc.is_reversal = 1 THEN 1 END) as reversal_count,
                COALESCE(SUM(CASE WHEN sc.is_reversal = 1 THEN sc.amount ELSE 0 END), 0) as total_reversed,
                COALESCE(SUM(CASE WHEN sc.is_reversal = 0 THEN sc.amount ELSE 0 END), 0) as net_amount
            ')
            ->join('tbl_Sms_Classification sc', 'sc.sms_id = s.id', 'left')
            ->where('s.sms_is_finance', 1);

        if ($deviceToken) {
            $builder->where('s.sms_owner', $deviceToken);
        }

        $row = $builder->get()->getRow();

        return [
            'reversal_count' => (int)($row->reversal_count ?? 0),
            'total_reversed' => (float)($row->total_reversed ?? 0.0),
            'net_amount'     => (float)($row->net_amount ?? 0.0),
        ];
    }
}
