<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\UploadModel;
use App\Models\BudgetModel;
use App\Models\InsightModel;

class DashboardController extends BaseController
{
    public function index()
    {
        $mod_uploads = new UploadModel();
        helper('mpesa_date');
        
        $userId = auth()->user()->id;
        $linkedDevices = $mod_uploads->getLinkedDevices($userId);
        
        $deviceToken = $this->request->getGet('device');
        if (empty($deviceToken)) {
            $deviceToken = null;
        }

        $lastDate = $mod_uploads->getLatestTransactionDate() ?? date('Y-m-d H:i:s');
        
        $metrics = $mod_uploads->getDashboardMetrics30Days($lastDate, $deviceToken);
        $sent_summary = $mod_uploads->getSentSummary30Days($lastDate, $deviceToken);
        $received_summary = $mod_uploads->getReceivedSummary30Days($lastDate, $deviceToken);
        $recent_transactions = $mod_uploads->getRecentTransactions(10, $deviceToken);
        
        $modBudget   = new BudgetModel();
        $modInsights = new InsightModel();

        // Refresh budget threshold values
        $mod_uploads->evaluate_budgets_for_user($userId);

        $userBudgets = $modBudget->getBudgets($userId);

        $data = [
            'device_filter'       => $deviceToken,
            'linked_devices'      => $linkedDevices,
            'total_uploads'       => $mod_uploads->countAll(),
            'metrics'             => $metrics,
            'sent_summary'        => $sent_summary,
            'received_summary'    => $received_summary,
            'recent_transactions' => $recent_transactions,
            'top_counterparties'  => $mod_uploads->getTopCounterparties(5, $deviceToken),
            'budget_alerts'       => $modBudget->getBudgetProgress($userBudgets, $userId),
            'budgets'             => $userBudgets,
            'smart_alerts'        => $modInsights->getSmartAlerts($deviceToken, $userId),
            'trends'              => $modInsights->getSpendingTrends($deviceToken, $userId),
            'health_score'        => $modInsights->getFinancialHealthScore($deviceToken, $userId),
            'bg_color'            => '#B1B8ED'
        ];

        return view('Dash/index', $data);
    }

    public function linkDevice()
    {
        $userId = auth()->user()->id;
        $deviceToken = $this->request->getPost('device_token');
        $deviceName = $this->request->getPost('device_name');
        if (empty($deviceName)) $deviceName = 'My Device';
        
        if (!empty($deviceToken)) {
            $mod_uploads = new UploadModel();
            $mod_uploads->linkDevice($userId, $deviceToken, $deviceName);
        }
        return redirect()->to(base_url('dashboard'))->with('message', 'Device linked successfully');
    }
}
