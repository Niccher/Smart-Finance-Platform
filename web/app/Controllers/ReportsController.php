<?php

namespace App\Controllers;

use App\Models\UploadModel;
use App\Models\InsightModel;

class ReportsController extends BaseController
{
    public function index()
    {
        helper('mpesa_date');
        $db = \Config\Database::connect();
        $mod = new UploadModel();
        $modInsights = new InsightModel();

        $year  = (int)($this->request->getGet('year')  ?? date('Y'));
        $month = (int)($this->request->getGet('month') ?? date('m'));
        $month = max(1, min(12, $month));

        // Get user-scoped tokens
        $userId = auth()->user()->id;
        $tokenType = \CodeIgniter\Shield\Authentication\Authenticators\AccessTokens::ID_TYPE_ACCESS_TOKEN;
        $tokenRows = $db->query("
            SELECT DISTINCT s.sms_owner AS tk FROM tbl_Sms s
            INNER JOIN auth_identities i ON i.secret = SHA2(s.sms_owner, 256)
            WHERE i.user_id = ? AND i.type = ?
            UNION
            SELECT DISTINCT l.loot_Owner AS tk FROM tbl_Loot l
            INNER JOIN auth_identities i ON i.secret = SHA2(l.loot_Owner, 256)
            WHERE i.user_id = ? AND i.type = ?
        ", [$userId, $tokenType, $userId, $tokenType])->getResult();
        $rawTokens = array_values(array_filter(array_map(fn($r) => $r->tk ?? '', $tokenRows)));

        $report = $mod->getReportData($year, $month, $rawTokens);
        $trends = $modInsights->getSpendingTrends(null, (int)$userId);
        $recurring = $modInsights->getRecurringPayments(null, (int)$userId);

        // Build available months for selector
        $months = [];
        for ($i = 0; $i < 12; $i++) {
            $ts = strtotime("-$i months");
            $months[] = [
                'value' => date('Y-m', $ts),
                'label' => date('F Y', $ts),
                'y'     => (int)date('Y', $ts),
                'm'     => (int)date('m', $ts),
            ];
        }

        $data = [
            'report'      => $report,
            'trends'      => $trends,
            'recurring'   => $recurring,
            'months'      => $months,
            'selectedKey' => sprintf('%04d-%02d', $year, $month),
            'bg_color'    => '#B1B8ED',
        ];

        return view('Reports/index', $data);
    }

    public function printView()
    {
        helper('mpesa_date');
        $db = \Config\Database::connect();
        $mod = new UploadModel();

        $year  = (int)($this->request->getGet('year')  ?? date('Y'));
        $month = (int)($this->request->getGet('month') ?? date('m'));
        $month = max(1, min(12, $month));

        // Get user-scoped tokens
        $userId = auth()->user()->id;
        $tokenType = \CodeIgniter\Shield\Authentication\Authenticators\AccessTokens::ID_TYPE_ACCESS_TOKEN;
        $tokenRows = $db->query("
            SELECT DISTINCT s.sms_owner AS tk FROM tbl_Sms s
            INNER JOIN auth_identities i ON i.secret = SHA2(s.sms_owner, 256)
            WHERE i.user_id = ? AND i.type = ?
            UNION
            SELECT DISTINCT l.loot_Owner AS tk FROM tbl_Loot l
            INNER JOIN auth_identities i ON i.secret = SHA2(l.loot_Owner, 256)
            WHERE i.user_id = ? AND i.type = ?
        ", [$userId, $tokenType, $userId, $tokenType])->getResult();
        $rawTokens = array_values(array_filter(array_map(fn($r) => $r->tk ?? '', $tokenRows)));

        $report = $mod->getReportData($year, $month, $rawTokens);

        return view('Reports/print', ['report' => $report]);
    }
}
