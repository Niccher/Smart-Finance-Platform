<?php

namespace App\Controllers;

use App\Models\UserSettingModel;
use App\Models\UploadModel;
use App\Models\TagModel;
use App\Models\NoteModel;
use App\Models\GoalModel;
use App\Models\RecurringPaymentModel;

class Settings extends BaseController
{
    private UserSettingModel $settingsModel;
    private UploadModel $uploadsModel;

    public function __construct()
    {
        helper('mpesa_date');
        $this->settingsModel = new UserSettingModel();
        $this->uploadsModel  = new UploadModel();
    }

    public function index()
    {
        $userId = auth()->user()->id;
        $settings = $this->settingsModel->getSettings($userId);
        $data = [
            'settings' => $settings,
            'bg_color' => '#B1B8ED',
        ];
        return view('Settings/index', $data);
    }

    public function profile()
    {
        $data = [
            'user'    => auth()->user(),
            'bg_color' => '#B1B8ED',
        ];
        return view('Settings/profile', $data);
    }

    public function saveProfile()
    {
        $rules = [
            'username' => 'permit_empty|alpha_numeric_space|min_length[3]|max_length[30]',
            'email'    => 'permit_empty|valid_email',
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()->with('error', implode(', ', $this->validator->getErrors()));
        }

        $user = auth()->user();
        $data = [];

        $username = $this->request->getPost('username');

        if (!empty($username) && $username !== $user->username) {
            $data['username'] = $username;
        }
        // Email is not editable for now; ignore any submitted value.

        if (!empty($data)) {
            $user->fill($data);
            $user->save();
        }

        $currentPassword = $this->request->getPost('current_password');
        $newPassword = $this->request->getPost('new_password');
        $confirmPassword = $this->request->getPost('confirm_password');

        if (!empty($currentPassword) && !empty($newPassword)) {
            if ($newPassword !== $confirmPassword) {
                return redirect()->back()->with('error', 'New passwords do not match.');
            }
            if (strlen($newPassword) < 8) {
                return redirect()->back()->with('error', 'Password must be at least 8 characters.');
            }
            $users = auth()->getProvider();
            if (!$users->check(['email' => $user->email, 'password' => $currentPassword])) {
                return redirect()->back()->with('error', 'Current password is incorrect.');
            }
            $user->password = $newPassword;
            $users->save($user);
        }

        return redirect()->to('/dashboard/settings/profile')->with('message', 'Profile updated successfully.');
    }

    public function notifications()
    {
        $userId = auth()->user()->id;
        $data = [
            'settings' => $this->settingsModel->getSettings($userId),
            'bg_color' => '#B1B8ED',
        ];
        return view('Settings/notifications', $data);
    }

    public function saveNotifications()
    {
        $userId = auth()->user()->id;
        $data = [
            'notify_email_alerts'     => (bool)$this->request->getPost('notify_email_alerts'),
            'notify_budget_alerts'    => (bool)$this->request->getPost('notify_budget_alerts'),
            'notify_low_balance'      => (bool)$this->request->getPost('notify_low_balance'),
            'notify_unusual_activity' => (bool)$this->request->getPost('notify_unusual_activity'),
        ];
        $this->settingsModel->saveSettings($userId, $data);
        return redirect()->to('/dashboard/settings/notifications')->with('message', 'Notification preferences saved.');
    }

    public function preferences()
    {
        $userId = auth()->user()->id;
        $data = [
            'settings' => $this->settingsModel->getSettings($userId),
            'bg_color' => '#B1B8ED',
        ];
        return view('Settings/preferences', $data);
    }

    public function savePreferences()
    {
        $userId = auth()->user()->id;
        $widgets = $this->request->getPost('dashboard_widgets');
        $data = [
            'currency'               => $this->request->getPost('currency') ?? 'KES',
            'date_format'            => $this->request->getPost('date_format') ?? 'Y-m-d',
            'time_format'            => $this->request->getPost('time_format') ?? 'H:i',
            'default_budget_period'  => $this->request->getPost('default_budget_period') ?? 'monthly',
            'budget_alert_threshold' => (int)($this->request->getPost('budget_alert_threshold') ?? 80),
            'export_default_format'  => $this->request->getPost('export_default_format') ?? 'csv',
            'dashboard_widgets'      => is_array($widgets) ? json_encode($widgets) : null,
        ];
        $this->settingsModel->saveSettings($userId, $data);
        return redirect()->to('/dashboard/settings/preferences')->with('message', 'Preferences saved.');
    }

    public function security()
    {
        $userId = auth()->user()->id;
        $db = \Config\Database::connect();
        $tokens = $db->table('auth_identities')
            ->where('user_id', $userId)
            ->where('type', \CodeIgniter\Shield\Authentication\Authenticators\AccessTokens::ID_TYPE_ACCESS_TOKEN)
            ->get()
            ->getResult();

        $devices = $this->uploadsModel->getLinkedDevices($userId);

        $data = [
            'tokens'  => $tokens,
            'devices' => $devices,
            'bg_color' => '#B1B8ED',
        ];
        return view('Settings/security', $data);
    }

    public function revokeDevice()
    {
        $deviceToken = $this->request->getPost('device_token');
        $userId = auth()->user()->id;

        if (!empty($deviceToken)) {
            $db = \Config\Database::connect();
            $db->table('tbl_User_Devices')
                ->where('user_id', $userId)
                ->where('device_token', $deviceToken)
                ->delete();
        }

        return redirect()->to('/dashboard/settings/security')->with('message', 'Device unlinked.');
    }

    public function data()
    {
        $userId = auth()->user()->id;
        $db = \Config\Database::connect();
        $tokenType = \CodeIgniter\Shield\Authentication\Authenticators\AccessTokens::ID_TYPE_ACCESS_TOKEN;

        $totalUploads = (int) ($db->query("
            SELECT COUNT(*) as cnt FROM tbl_Loot l
            LEFT JOIN auth_identities i ON i.secret = SHA2(l.loot_Owner, 256) AND i.type = ?
            WHERE l.loot_user_id = ? OR i.user_id = ?
        ", [$tokenType, $userId, $userId])->getRow()->cnt ?? 0);

        $oldestUploadRow = $db->query("
            SELECT MIN(COALESCE(CASE WHEN l.loot_Created >= '2000-01-01' THEN l.loot_Created ELSE NULL END, ls.loot_Created, l.loot_Created)) as oldest
            FROM tbl_Loot l
            LEFT JOIN tbl_Loot_Summary ls ON ls.loot_Uuid = l.loot_Uuid
            LEFT JOIN auth_identities i ON i.secret = SHA2(l.loot_Owner, 256) AND i.type = ?
            WHERE l.loot_user_id = ? OR i.user_id = ?
        ", [$tokenType, $userId, $userId])->getRow();
        $oldestUpload = $oldestUploadRow->oldest ?? 'N/A';

        $nonFinance = $this->nonFinanceCount();

        $data = [
            'total_uploads'     => $totalUploads,
            'oldest_upload'     => format_date_display($oldestUpload),
            'non_finance_count' => (int) ($nonFinance['count'] ?? 0),
            'non_finance_senders' => (int) ($nonFinance['senders'] ?? 0),
            'bg_color'          => '#B1B8ED',
        ];
        return view('Settings/data', $data);
    }

    public function exportSettings()
    {
        $userId = auth()->user()->id;

        $settings = $this->settingsModel->getSettings($userId);

        $db = \Config\Database::connect();
        $tokenType = \CodeIgniter\Shield\Authentication\Authenticators\AccessTokens::ID_TYPE_ACCESS_TOKEN;

        $rawTokens = [];
        $tokenRows = $db->query("
            SELECT DISTINCT s.sms_owner AS tk FROM tbl_Sms s
            INNER JOIN auth_identities i ON i.secret = SHA2(s.sms_owner, 256)
            WHERE i.user_id = ? AND i.type = ?
        ", [$userId, $tokenType])->getResult();
        foreach ($tokenRows as $r) {
            if (!empty($r->tk)) $rawTokens[] = $r->tk;
        }

        $budgets = [];
        if ($db->tableExists('tbl_Budgets')) {
            $budgets = $db->table('tbl_Budgets')->get()->getResultArray();
        }

        $rules = [];
        if ($db->tableExists('tbl_Category_Rules')) {
            $rules = $db->table('tbl_Category_Rules')->get()->getResultArray();
        }

        $export = [
            'exported_at' => date('c'),
            'version'     => '1.0',
            'user'        => [
                'username' => auth()->user()->username,
                'email'    => auth()->user()->email,
            ],
            'settings'    => $settings,
            'budgets'     => $budgets,
            'category_rules' => $rules,
        ];

        $filename = 'mpesa_settings_backup_' . date('Y-m-d') . '.json';
        return $this->response
            ->setHeader('Content-Type', 'application/json')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->setBody(json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    public function importSettings()
    {
        $file = $this->request->getFile('settings_file');
        if (!$file || !$file->isValid()) {
            return redirect()->back()->with('error', 'No valid file uploaded.');
        }

        $contents = file_get_contents($file->getTempName());
        $data = json_decode($contents, true);

        if (!$data || !isset($data['settings'])) {
            return redirect()->back()->with('error', 'Invalid backup file format.');
        }

        $userId = auth()->user()->id;
        $settingsData = $data['settings'];
        unset($settingsData['id'], $settingsData['user_id'], $settingsData['created_at'], $settingsData['updated_at']);

        $this->settingsModel->saveSettings($userId, $settingsData);

        if (!empty($data['budgets'])) {
            $db = \Config\Database::connect();
            if ($db->tableExists('tbl_Budgets')) {
                foreach ($data['budgets'] as $budget) {
                    unset($budget['id']);
                    $db->table('tbl_Budgets')->upsert($budget, ['category', 'period']);
                }
            }
        }

        return redirect()->to('/dashboard/settings/data')->with('message', 'Settings imported successfully.');
    }

    public function exportCategoryRules()
    {
        $db = \Config\Database::connect();
        if (!$db->tableExists('tbl_Category_Rules')) {
            return redirect()->back()->with('error', 'No category rules found.');
        }
        $rules = $db->table('tbl_Category_Rules')->get()->getResultArray();

        $filename = 'mpesa_category_rules_' . date('Y-m-d') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $output = fopen('php://output', 'w');
        fputcsv($output, ['keyword', 'correct_category']);
        foreach ($rules as $rule) {
            fputcsv($output, [$rule['keyword'], $rule['correct_category']]);
        }
        fclose($output);
        exit;
    }

    public function importCategoryRules()
    {
        $file = $this->request->getFile('rules_file');
        if (!$file || !$file->isValid()) {
            return redirect()->back()->with('error', 'No valid file uploaded.');
        }

        $db = \Config\Database::connect();

        $handle = fopen($file->getTempName(), 'r');
        $header = fgetcsv($handle);
        $imported = 0;

        if (!$header || strtolower($header[0] ?? '') !== 'keyword') {
            fclose($handle);
            return redirect()->back()->with('error', 'Invalid CSV format. First row should be: keyword,correct_category');
        }

        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) < 2) continue;
            $keyword = trim($row[0]);
            $category = trim($row[1]);
            if (empty($keyword) || empty($category)) continue;

            $existing = $db->table('tbl_Category_Rules')
                ->where('keyword', $keyword)
                ->get()
                ->getRow();

            if ($existing) {
                $db->table('tbl_Category_Rules')
                    ->where('id', $existing->id)
                    ->update(['correct_category' => $category]);
            } else {
                $db->table('tbl_Category_Rules')->insert([
                    'keyword'          => $keyword,
                    'correct_category' => $category,
                    'created_at'       => date('Y-m-d H:i:s'),
                ]);
            }
            $imported++;
        }
        fclose($handle);

        return redirect()->to('/dashboard/settings/data')->with('message', "Imported {$imported} category rules.");
    }

    public function exportCsv()
    {
        $userId = auth()->user()->id;
        $modUploads = new UploadModel();
        $transactions = $modUploads->getAllTransactionsForUser($userId);

        $filename = 'mpesa_transactions_' . date('Y-m-d') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $output = fopen('php://output', 'w');
        fputcsv($output, ['Date', 'Type', 'Amount', 'Counterparty', 'Description', 'Category', 'Direction', 'Balance']);

        foreach ($transactions as $row) {
            fputcsv($output, [
                $row['trans_date'] ?? $row['sms_time'] ?? '',
                $row['sms_transaction_type'] ?? '',
                $row['amount'] ?? $row['sms_amount'] ?? '',
                $row['counterparty'] ?? $row['sms_counterparty'] ?? '',
                $row['description'] ?? '',
                $row['category'] ?? '',
                $row['direction'] ?? $row['sms_direction'] ?? '',
                $row['sms_balance'] ?? '',
            ]);
        }
        fclose($output);
        exit;
    }

    public function exportJson()
    {
        $userId = auth()->user()->id;
        $modUploads = new UploadModel();
        $transactions = $modUploads->getAllTransactionsForUser($userId);

        $filename = 'mpesa_transactions_' . date('Y-m-d') . '.json';
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        echo json_encode($transactions, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function purgeData()
    {
        $months = (int)($this->request->getPost('months') ?? 12);
        $userId = auth()->user()->id;

        $db = \Config\Database::connect();
        $tokenType = \CodeIgniter\Shield\Authentication\Authenticators\AccessTokens::ID_TYPE_ACCESS_TOKEN;

        $rawTokens = [];
        $tokenRows = $db->query("
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

        if (empty($rawTokens)) {
            return redirect()->back()->with('error', 'No data found for this account.');
        }

        $cutoff = date('Y-m-d H:i:s', strtotime("-{$months} months"));

        $db->transStart();

        $smsIds = [];
        $smsRows = $db->table('tbl_Sms')
            ->select('id')
            ->whereIn('sms_owner', $rawTokens)
            ->where('sms_time <', $cutoff)
            ->get()
            ->getResult();
        foreach ($smsRows as $r) $smsIds[] = $r->id;

        if (!empty($smsIds)) {
            if ($db->tableExists('tbl_Sms_Processing')) {
                $db->table('tbl_Sms_Processing')->whereIn('sms_id', $smsIds)->delete();
            }
            // tbl_Sms_Classification & tbl_Analyzed_Transactions are now views
            // derived from tbl_Sms — deleting the SMS row removes their data.
            $db->table('tbl_Sms')->whereIn('id', $smsIds)->delete();
        }

        $db->transComplete();

        return redirect()->to('/dashboard/settings/data')->with('message', "Data older than {$months} months purged (" . count($smsIds) . " SMS records deleted).");
    }

    public function deleteUpload()
    {
        $uuid = $this->request->getPost('uuid');
        if (empty($uuid)) {
            return redirect()->back()->with('error', 'No upload specified.');
        }

        $userId = auth()->user()->id;
        $db = \Config\Database::connect();
        $tokenType = \CodeIgniter\Shield\Authentication\Authenticators\AccessTokens::ID_TYPE_ACCESS_TOKEN;

        $ownerCheck = $db->query("
            SELECT loot_Owner FROM tbl_Loot WHERE loot_Uuid = ?
            AND loot_Owner IN (
                SELECT secret FROM auth_identities
                WHERE user_id = ? AND type = ?
            )
        ", [$uuid, $userId, $tokenType])->getRow();

        if (!$ownerCheck) {
            return redirect()->back()->with('error', 'Upload not found or not yours.');
        }

        $db->transStart();
        $smsIds = [];
        $smsRows = $db->table('tbl_Sms')
            ->select('id')
            ->where('sms_loot_source', $uuid)
            ->get()
            ->getResult();
        foreach ($smsRows as $r) $smsIds[] = $r->id;

        if (!empty($smsIds)) {
            if ($db->tableExists('tbl_Sms_Processing')) {
                $db->table('tbl_Sms_Processing')->whereIn('sms_id', $smsIds)->delete();
            }
            // tbl_Sms_Classification & tbl_Analyzed_Transactions are now views
            // derived from tbl_Sms — deleting the SMS row removes their data.
            $db->table('tbl_Sms')->whereIn('id', $smsIds)->delete();
        }
        $db->table('tbl_Loot_Summary')->where('loot_Uuid', $uuid)->delete();
        $db->table('tbl_Loot')->where('loot_Uuid', $uuid)->delete();
        $db->transComplete();

        return redirect()->to('/dashboard/settings/data')->with('message', 'Upload deleted successfully.');
    }

    /**
     * Resolve the raw access-token strings that own this user's SMS data.
     */
    private function getRawTokens(): array
    {
        $userId = auth()->user()->id;
        $db = \Config\Database::connect();
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

        return array_values(array_filter(array_map(fn($r) => $r->tk ?? '', $tokenRows)));
    }

    /**
     * Count non-finance SMS owned by the current user (for the delete preview).
     */
    public function nonFinanceCount(): array
    {
        $db = \Config\Database::connect();
        $tokens = $this->getRawTokens();
        if (empty($tokens)) {
            return ['status' => 'success', 'count' => 0, 'senders' => 0];
        }

        $row = $db->table('tbl_Sms')
            ->select("COUNT(*) AS sms, COUNT(DISTINCT sms_number) AS senders")
            ->whereIn('sms_owner', $tokens)
            ->where('(sms_is_finance IS NULL OR sms_is_finance = 0)')
            ->get()
            ->getRow();

        return [
            'status'  => 'success',
            'count'   => (int) ($row->sms ?? 0),
            'senders' => (int) ($row->senders ?? 0),
        ];
    }

    /**
     * Permanently delete SMS whose sender is not finance-related.
     * User-initiated, with an explicit confirmation. Finance SMS and all other
     * account data are untouched.
     */
    public function deleteNonFinance()
    {
        $confirm = strtoupper(trim((string) $this->request->getPost('confirm') ?? ''));
        if ($confirm !== 'DELETE') {
            return redirect()->back()->with('error', 'Deletion cancelled — type DELETE to confirm.');
        }

        $db = \Config\Database::connect();
        $tokens = $this->getRawTokens();
        if (empty($tokens)) {
            return redirect()->to('/dashboard/settings/data')->with('error', 'No data found for this account.');
        }

        // Count what will be removed (for the message).
        $counts = $this->nonFinanceCount();
        $toDelete = (int) ($counts['count'] ?? 0);
        if ($toDelete <= 0) {
            return redirect()->to('/dashboard/settings/data')->with('message', 'No non-finance SMS to delete.');
        }

        $smsIds = [];
        $rows = $db->table('tbl_Sms')
            ->select('id')
            ->whereIn('sms_owner', $tokens)
            ->where('(sms_is_finance IS NULL OR sms_is_finance = 0)')
            ->get()
            ->getResult();
        foreach ($rows as $r) {
            $smsIds[] = $r->id;
        }

        $db->transStart();

        if ($db->tableExists('tbl_Sms_Processing')) {
            $db->table('tbl_Sms_Processing')->whereIn('sms_id', $smsIds)->delete();
        }
        // tbl_Sms_Classification & tbl_Analyzed_Transactions are views derived
        // from tbl_Sms — deleting the SMS rows removes their derived data too.
        $db->table('tbl_Sms')->whereIn('id', $smsIds)->delete();

        $db->transComplete();

        \App\Libraries\Audit::log('user_delete_non_finance', 'data', 'User deleted non-finance SMS', [
            'user_id' => auth()->user()->id,
            'sms_deleted' => count($smsIds),
        ]);

        return redirect()->to('/dashboard/settings/data')
            ->with('message', count($smsIds) . " non-finance SMS deleted (" . (int) ($counts['senders'] ?? 0) . " senders).");
    }

    // ─── Tags ─────────────────────────────────────────────────────

    public function tags()
    {
        $userId = auth()->user()->id;
        $modTags = new TagModel();
        $data = [
            'tags'   => $modTags->getForUser($userId),
            'bg_color' => '#B1B8ED',
        ];
        return view('Settings/tags', $data);
    }

    public function saveTag()
    {
        $userId = auth()->user()->id;
        $modTags = new TagModel();
        $modTags->saveTag($userId, [
            'name'  => $this->request->getPost('name'),
            'color' => $this->request->getPost('color') ?? '#5D5FEF',
        ]);
        return redirect()->to('/dashboard/settings/tags')->with('message', 'Tag created.');
    }

    public function deleteTag()
    {
        $userId = auth()->user()->id;
        $id = (int)$this->request->getPost('id');
        if ($id > 0) {
            $modTags = new TagModel();
            $modTags->deleteTag($id, $userId);
        }
        return redirect()->to('/dashboard/settings/tags')->with('message', 'Tag deleted.');
    }

    // ─── Notes ────────────────────────────────────────────────────

    public function saveNote()
    {
        $userId = auth()->user()->id;
        $smsId = (int)$this->request->getPost('sms_id');
        $note  = $this->request->getPost('note');
        if ($smsId > 0) {
            $modNotes = new NoteModel();
            $modNotes->saveNote($userId, $smsId, $note);
        }
        return $this->response->setJSON(['status' => 'ok']);
    }

    public function getNote(int $smsId)
    {
        $userId = (int) auth()->id();
        $modNotes = new NoteModel();
        $note = $modNotes->getForSms($smsId, $userId);
        return $this->response->setJSON($note ?: ['note' => '']);
    }

    // ─── Spending Goals ───────────────────────────────────────────

    public function goals()
    {
        $userId = auth()->user()->id;
        $modGoals = new GoalModel();
        $goals = $modGoals->getForUser($userId);
        $progress = $modGoals->getProgress($goals);

        $categories = ['Total Outflow', 'Received', 'Paybill', 'Till',
            'Sent to Mobile', 'Withdrawal', 'Fuliza',
            'Mobile Money', 'Bank', 'Fintech', 'SACCO', 'Insurance'];

        $data = [
            'goals'      => $progress,
            'categories' => $categories,
            'bg_color'   => '#B1B8ED',
        ];
        return view('Settings/goals', $data);
    }

    public function saveGoal()
    {
        $userId = auth()->user()->id;
        $modGoals = new GoalModel();
        $modGoals->save([
            'user_id'      => $userId,
            'category'     => $this->request->getPost('category'),
            'label'        => $this->request->getPost('label'),
            'target_amount' => (float)$this->request->getPost('target_amount'),
            'period'       => $this->request->getPost('period') ?? 'monthly',
            'rollover'     => (bool)$this->request->getPost('rollover'),
        ]);
        return redirect()->to('/dashboard/settings/goals')->with('message', 'Goal created.');
    }

    public function deleteGoal()
    {
        $userId = (int) auth()->user()->id;
        $id = (int)$this->request->getPost('id');
        if ($id > 0) {
            (new GoalModel())->where('id', $id)->where('user_id', $userId)->delete();
        }
        return redirect()->to('/dashboard/settings/goals')->with('message', 'Goal deleted.');
    }

    // ─── Recurring Transactions ───────────────────────────────────

    public function recurring()
    {
        $userId = auth()->user()->id;
        $modRecurring = new RecurringPaymentModel();
        $recurring = $modRecurring->getForUser($userId);
        $detected = $modRecurring->detectRecurring($userId);

        $data = [
            'recurring' => $recurring,
            'detected'  => $detected,
            'bg_color'  => '#B1B8ED',
        ];
        return view('Settings/recurring', $data);
    }

    public function saveRecurring()
    {
        $userId = auth()->user()->id;
        $modRecurring = new RecurringPaymentModel();

        $day = (int)($this->request->getPost('day_of_period') ?? 1);
        $frequency = $this->request->getPost('frequency') ?? 'monthly';

        $nextExpected = date('Y-m-d', strtotime("+1 {$frequency}"));

        $modRecurring->save([
            'user_id'      => $userId,
            'label'        => $this->request->getPost('label'),
            'counterparty' => $this->request->getPost('counterparty'),
            'amount'       => (float)$this->request->getPost('amount'),
            'frequency'    => $frequency,
            'category'     => $this->request->getPost('category'),
            'direction'    => $this->request->getPost('direction') ?? 'sent',
            'day_of_period' => $day,
            'next_expected' => $nextExpected,
        ]);
        return redirect()->to('/dashboard/settings/recurring')->with('message', 'Recurring transaction saved.');
    }

    public function deleteRecurring()
    {
        $userId = (int) auth()->user()->id;
        $id = (int)$this->request->getPost('id');
        if ($id > 0) {
            (new RecurringPaymentModel())->where('id', $id)->where('user_id', $userId)->delete();
        }
        return redirect()->to('/dashboard/settings/recurring')->with('message', 'Recurring transaction removed.');
    }

    // ─── Report Scheduling ───────────────────────────────────────

    public function reportSchedule()
    {
        $userId = auth()->user()->id;
        $settings = $this->settingsModel->getSettings($userId);
        $data = [
            'settings' => $settings,
            'bg_color' => '#B1B8ED',
        ];
        return view('Settings/report_schedule', $data);
    }

    public function saveReportSchedule()
    {
        $userId = auth()->user()->id;
        $data = [
            'report_schedule_enabled' => (bool)$this->request->getPost('enabled'),
            'report_schedule_frequency' => $this->request->getPost('frequency') ?? 'monthly',
            'report_schedule_email' => $this->request->getPost('email') ?? auth()->user()->email,
            'report_schedule_day' => (int)($this->request->getPost('day') ?? 1),
            'report_schedule_format' => $this->request->getPost('format') ?? 'pdf',
        ];

        $existingSettings = $this->settingsModel->getSettings($userId);
        $merged = array_merge($existingSettings, $data);
        $merged['dashboard_widgets'] = $existingSettings['dashboard_widgets'];
        $this->settingsModel->saveSettings($userId, $merged);

        return redirect()->to('/dashboard/settings/report-schedule')->with('message', 'Report schedule saved.');
    }
}
