<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use CodeIgniter\API\ResponseTrait;

class Users extends BaseController
{
    use ResponseTrait;

    public function index()
    {
        $db = \Config\Database::connect();

        // Search and pagination
        $search = trim((string)$this->request->getGet('q'));
        $page = max(1, (int)$this->request->getGet('page'));
        $perPage = 25;
        $offset = ($page - 1) * $perPage;

        $builder = $db->table('users')
            ->select("users.id, users.username, MAX(ai.secret) as email, users.active, users.created_at, users.updated_at")
            ->select('GROUP_CONCAT(DISTINCT agu.group) as user_groups')
            ->join('auth_identities ai', "ai.user_id = users.id AND ai.type = 'email_password'", 'left')
            ->join('auth_groups_users agu', 'agu.user_id = users.id', 'left')
            ->groupBy(['users.id', 'users.username', 'users.active', 'users.created_at', 'users.updated_at'])
            ->orderBy('users.id', 'DESC');

        if ($search !== '') {
            $builder->groupStart()
                ->like('users.username', $search)
                ->orLike('ai.secret', $search)
                ->groupEnd();
        }

        $total = $db->table('users')
            ->select('COUNT(DISTINCT users.id)')
            ->join('auth_identities ai', "ai.user_id = users.id AND ai.type = 'email_password'", 'left')
            ->when($search !== '', function ($q) use ($search) {
                $q->groupStart()
                    ->like('users.username', $search)
                    ->orLike('ai.secret', $search)
                    ->groupEnd();
            })
            ->get()->getRow()->{'COUNT(DISTINCT users.id)'};

        $users = $builder->limit($perPage, $offset)->get()->getResultArray();

        // Calculate storage per user (sms bytes + loot count estimate)
        $userIds = array_column($users, 'id');
        $storage = [];
        if (!empty($userIds)) {
            $placeholders = implode(',', array_fill(0, count($userIds), '?'));
            // SMS body bytes
            $rows = $db->query("
                SELECT i.user_id, SUM(LENGTH(s.sms_body)) as total_bytes, COUNT(*) as cnt
                FROM tbl_Sms s
                INNER JOIN auth_identities i ON i.secret = SHA2(s.sms_owner, 256)
                WHERE i.user_id IN ($placeholders)
                GROUP BY i.user_id
            ", $userIds)->getResultArray();
            foreach ($rows as $r) $storage[$r['user_id']] = ($storage[$r['user_id']] ?? 0) + (int)$r['total_bytes'];

            // Loot rows (no file size stored, estimate ~1KB per upload)
            $rows = $db->query("
                SELECT i.user_id, COUNT(*) as cnt
                FROM tbl_Loot l
                INNER JOIN auth_identities i ON i.secret = SHA2(l.loot_Owner, 256)
                WHERE i.user_id IN ($placeholders)
                GROUP BY i.user_id
            ", $userIds)->getResultArray();
            foreach ($rows as $r) $storage[$r['user_id']] = ($storage[$r['user_id']] ?? 0) + (int)$r['cnt'] * 1024;
        }

        foreach ($users as &$u) {
            $u['groups'] = $u['user_groups'] ?: '—';
            $u['storage_bytes'] = $storage[$u['id']] ?? 0;
            $u['storage_human'] = $this->humanSize($u['storage_bytes']);
        }

        $data = [
            'bg_color' => '#B1B8ED',
            'users' => $users,
            'search' => $search,
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => ceil($total / $perPage),
        ];

        return view('Admin/Users/index', $data);
    }

    public function toggle()
    {
        $id = (int)$this->request->getPost('user_id');
        $active = (bool)$this->request->getPost('active');

        $db = \Config\Database::connect();
        $user = $db->table('users')->where('id', $id)->get()->getRow();
        if (!$user) {
            return $this->response->setJSON(['status' => 'error', 'message' => 'User not found']);
        }

        // Prevent deactivating yourself
        if ($id === auth()->id()) {
            return $this->response->setJSON(['status' => 'error', 'message' => 'Cannot change your own status']);
        }

        $db->table('users')->where('id', $id)->update(['active' => $active ? 1 : 0]);

        return $this->response->setJSON([
            'status' => 'success',
            'message' => $active ? 'User activated' : 'User deactivated',
        ]);
    }

    public function changeGroup()
    {
        $id = (int)$this->request->getPost('user_id');
        $group = trim((string)$this->request->getPost('group')); // 'superadmin' or 'default' or 'admin'

        $allowedGroups = ['user', 'admin', 'superadmin'];
        if (!in_array($group, $allowedGroups, true)) {
            return $this->response->setJSON(['status' => 'error', 'message' => 'Invalid group']);
        }

        $db = \Config\Database::connect();
        $user = $db->table('users')->where('id', $id)->get()->getRow();
        if (!$user) {
            return $this->response->setJSON(['status' => 'error', 'message' => 'User not found']);
        }

        if ($id === auth()->id()) {
            return $this->response->setJSON(['status' => 'error', 'message' => 'Cannot change your own group']);
        }

        // Remove from all groups, add to new group (Shield stores group name in `group` column)
        $db->table('auth_groups_users')->where('user_id', $id)->delete();
        $db->table('auth_groups_users')->insert([
            'user_id' => $id,
            'group' => $group,
        ]);

        return $this->response->setJSON([
            'status' => 'success',
            'message' => "User group changed to $group",
        ]);
    }

    public function delete()
    {
        $id = (int)$this->request->getPost('user_id');

        if ($id === auth()->id()) {
            return $this->response->setJSON(['status' => 'error', 'message' => 'Cannot delete yourself']);
        }

        $db = \Config\Database::connect();
        $user = $db->table('users')->where('id', $id)->get()->getRow();
        if (!$user) {
            return $this->response->setJSON(['status' => 'error', 'message' => 'User not found']);
        }

        // Capture the user's email + username for the confirmation email (sent after deletion).
        $userEmail = '';
        $emailRow = $db->table('auth_identities')
            ->where('user_id', $id)
            ->where('type', 'email_password')
            ->get()
            ->getRow();
        $userEmail = $emailRow->secret ?? '';
        $userName = $user->username ?? '';

        // Collect the user's auth identity secrets (SHA2-hashed device tokens
        // stored as the "owner" identifier in the SMS/Loot tables).
        $secrets = $db->table('auth_identities')
            ->select('secret')
            ->where('user_id', $id)
            ->get()
            ->getResultArray();
        $secretList = array_column($secrets, 'secret');

        // Count data that will be removed so the confirmation email can report it.
        $smsDeleted = 0;
        $transactionsDeleted = 0;
        $filesDeleted = 0;
        if (!empty($secretList)) {
            $sub = "SELECT secret FROM auth_identities WHERE user_id = " . (int)$id;
            if ($db->tableExists('tbl_Sms')) {
                $smsDeleted = (int)$db->query("SELECT COUNT(*) AS cnt FROM tbl_Sms WHERE SHA2(sms_owner, 256) IN ($sub)")->getRow()->cnt;
            }
            if ($db->tableExists('tbl_Loot')) {
                $filesDeleted = (int)$db->query("SELECT COUNT(*) AS cnt FROM tbl_Loot WHERE SHA2(loot_Owner, 256) IN ($sub)")->getRow()->cnt;
            }
            if ($db->tableExists('tbl_Analyzed_Transactions') && $db->tableExists('tbl_Sms')) {
                $q = "SELECT COUNT(DISTINCT a.id) AS cnt
                      FROM tbl_Analyzed_Transactions a
                      INNER JOIN tbl_Sms s ON s.id = a.orig_sms_int_id OR s.sms__id = a.orig_sms_id
                      WHERE SHA2(s.sms_owner, 256) IN ($sub)";
                $transactionsDeleted = (int)$db->query($q)->getRow()->cnt;
            }
        }

        $db->transStart();

        $failedQuery = null;

        if (!empty($secretList)) {
            $sub = "SELECT secret FROM auth_identities WHERE user_id = " . (int)$id;

            // Child tables that reference tbl_Sms (deleted first).
            // tbl_Analyzed_Transactions & tbl_Sms_Classification are now views
            // derived from tbl_Sms — the raw SMS delete below removes them.
            if ($db->tableExists('tbl_Sms_Processing') && $db->tableExists('tbl_Sms')) {
                $q = "DELETE FROM tbl_Sms_Processing WHERE sms_id IN (SELECT id FROM tbl_Sms WHERE SHA2(sms_owner, 256) IN ($sub))";
                if ($db->query($q) === false) $failedQuery = $q . ' → ' . json_encode($db->error());
            }

            // Parent owner rows.
            // Delete physical uploaded files before removing the Loot rows.
            if ($db->tableExists('tbl_Loot')) {
                $loots = $db->query("SELECT loot_Name FROM tbl_Loot WHERE SHA2(loot_Owner, 256) IN ($sub)")->getResult();
                foreach ($loots as $loot) {
                    try {
                        service('storage')->deletePayload($loot->loot_Name);
                    } catch (\Throwable $e) {}
                    $filePathTxt = WRITEPATH . 'uploads/payloads/' . ltrim($loot->loot_Name, '/');
                    $filePathJson = str_replace('.txt', '.json', $filePathTxt);
                    if (file_exists($filePathTxt)) unlink($filePathTxt);
                    if (file_exists($filePathJson)) unlink($filePathJson);
                }
            }
            if ($db->tableExists('tbl_Loot')) {
                $q = "DELETE FROM tbl_Loot WHERE SHA2(loot_Owner, 256) IN ($sub)";
                if ($db->query($q) === false) $failedQuery = $q . ' → ' . json_encode($db->error());
            }
            if ($db->tableExists('tbl_Loot_Summary') && $db->tableExists('tbl_Loot')) {
                $q = "DELETE FROM tbl_Loot_Summary WHERE loot_Uuid IN (SELECT loot_Uuid FROM tbl_Loot WHERE SHA2(loot_Owner, 256) IN ($sub))";
                if ($db->query($q) === false) $failedQuery = $q . ' → ' . json_encode($db->error());
            }
            if ($db->tableExists('tbl_Sms')) {
                $q = "DELETE FROM tbl_Sms WHERE SHA2(sms_owner, 256) IN ($sub)";
                if ($db->query($q) === false) $failedQuery = $q . ' → ' . json_encode($db->error());
            }
            if ($db->tableExists('tbl_Sender_Profiles')) {
                $q = "DELETE FROM tbl_Sender_Profiles WHERE SHA2(sp_owner, 256) IN ($sub)";
                if ($db->query($q) === false) $failedQuery = $q . ' → ' . json_encode($db->error());
            }
        }

        foreach (['tbl_Processing_Jobs', 'tbl_User_Settings', 'tbl_User_Devices'] as $t) {
            if ($db->tableExists($t) && $db->table($t)->where('user_id', (string)$id)->delete() === false) {
                $failedQuery = "$t WHERE user_id=$id → " . json_encode($db->error());
            }
        }

        // Feature tables (FK cascades handle these when the users row is deleted,
        // but delete explicitly so no orphaned rows survive if FKs are not enforced).
        foreach (['tbl_Transaction_Tags', 'tbl_Transaction_Notes', 'tbl_Spending_Goals', 'tbl_Budgets', 'tbl_Recurring_Transactions'] as $t) {
            if ($db->tableExists($t) && $db->table($t)->where('user_id', (string)$id)->delete() === false) {
                $failedQuery = "$t WHERE user_id=$id → " . json_encode($db->error());
            }
        }
        if ($db->tableExists('tbl_Transaction_Tag_Map') && $db->tableExists('tbl_Transaction_Tags')) {
            if ($db->table('tbl_Transaction_Tag_Map')
                ->whereIn('tag_id', function (\CodeIgniter\Database\BaseBuilder $b) use ($id) {
                    return $b->select('id')->from('tbl_Transaction_Tags')->where('user_id', (string)$id);
                })->delete() === false) {
                $failedQuery = 'tbl_Transaction_Tag_Map → ' . json_encode($db->error());
            }
        }

        $db->table('auth_groups_users')->where('user_id', $id)->delete();
        $db->table('auth_identities')->where('user_id', $id)->delete();
        $db->table('auth_permissions_users')->where('user_id', $id)->delete();
        $db->table('auth_logins')->where('user_id', $id)->delete();
        $db->table('auth_remember_tokens')->where('user_id', $id)->delete();
        $db->table('auth_token_logins')->where('user_id', $id)->delete();

        $db->table('users')->where('id', $id)->delete();

        $db->transComplete();

        if ($db->transStatus() === false) {
            return $this->response->setJSON(['status' => 'error', 'message' => 'Delete failed: ' . ($failedQuery ?? json_encode($db->error()))]);
        }

        // Send a confirmation email (HTML styled, includes an Email ID).
        if (!empty($userEmail) && \App\Libraries\Notifier::isTriggerEnabled('user_account_deleted')) {
            \App\Libraries\Notifier::sendTrigger($userEmail, 'user_account_deleted', [
                'username'            => $userName,
                'smsDeleted'          => $smsDeleted,
                'transactionsDeleted' => $transactionsDeleted,
                'filesDeleted'        => $filesDeleted,
                'deletedAt'           => date('Y-m-d H:i:s T'),
            ]);
        }

        return $this->response->setJSON([
            'status' => 'success',
            'message' => 'User deleted',
        ]);
    }

    private function humanSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        for ($i = 0; $bytes >= 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }
}