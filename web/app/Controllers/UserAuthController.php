<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;
use CodeIgniter\Shield\Result;
use CodeIgniter\API\ResponseTrait;

class UserAuthController extends BaseController{

    use ResponseTrait;

    public function user_login(){
        $credentials = [
            'email' => $this->request->getPost('email'),
            'password' => $this->request->getPost('password'),
        ];

        if (auth()->loggedIn()){
            auth()->logout();
        }

        $login_attempt = auth()->attempt($credentials);

        if (!$login_attempt->isOK()){
            $reason = $login_attempt->reason();
            //return $this->fail($reason, 400);
            return $this->respond(["status" => "Invalid","message" => $reason]);
        }else{
            $user = new UserModel();
            $userinfo = $user->find(auth()->id());

            //print(auth()->user()->id, auth()->user()->email);

            $token = $userinfo->generateAccessToken("TokenName");
            $user_auth_token = $token->raw_token;

            // Persist the raw token → user link so uploaded data stays
            // attributable even after this Shield token is revoked.
            $modUploads = new \App\Models\UploadModel();
            $modUploads->linkDevice((int)$userinfo->id, $user_auth_token, 'Android App Device');

            return $this->respond(["status" => "Valid","token" => $user_auth_token]);
        }
    }

    public function user_register(){
        $users = new UserModel();
        //$users = auth()->getProvider();

        //$this->request->getVar('username') $this->request->getPost('username')
        $creds['username'] = $this->request->getPost('username');
        $creds['email'] = $this->request->getPost('email');
        $creds['password'] = $this->request->getPost('password');

        $newUser = new User([
            'username' => $this->request->getPost('username'),
            'email' => $this->request->getPost('email'),
            'password' => $this->request->getPost('password')
        ]);
        //$newUser = new User($creds);

        //return $this->respond(["status" => "Invalid", "postData" => $_POST, "newUser" => $newUser,"userCred" => $creds, "error" => "Unexpected error"]);

        try {
            $register_attempt = $users->save($newUser);

            $newUser = $users->findById($users->getInsertID());
            $users->addToDefaultGroup($newUser);

            if (!$register_attempt){
                return $this->respond(["status" => "Invalid", "error" => "Unexpected error occurred.0"]);
            }else{
                if (\App\Libraries\Notifier::isTriggerEnabled('signup')) {
                    \App\Libraries\Notifier::sendTrigger($newUser->getEmail(), 'signup');
                }
                return $this->respond(["status" => "Valid", "message" => "New User added."]);
            }
        }catch (Exception $ex) {
            return $this->respond(["status" => "Invalid", "error" => "Unexpected error occurred.1"]);
        }

    }

    public function verify_token(){
        $tokenStr = $this->request->getPost('token');
        if (empty($tokenStr)) {
            return $this->respond(["status" => "0", "message" => "Token is required"]);
        }

        $hashedToken = hash('sha256', $tokenStr);
        $db = \Config\Database::connect();
        
        $identity = $db->table('auth_identities')
            ->where('type', \CodeIgniter\Shield\Authentication\Authenticators\AccessTokens::ID_TYPE_ACCESS_TOKEN)
            ->where('secret', $hashedToken)
            ->get()
            ->getRow();

        if ($identity) {
            $userModel = new UserModel();
            $user = $userModel->find($identity->user_id);
            if ($user) {
                \App\Libraries\Audit::log(
                    'verify_token',
                    'auth',
                    'Token verified',
                    [
                        'token_hash'  => $hashedToken,
                        'app_version' => $this->request->getHeaderLine('X-App-Version') ?: null,
                        'device_time' => $this->request->getHeaderLine('X-Device-Time') ?: null,
                    ],
                    (int) $identity->user_id
                );

                // Return payload matching the expected Android format
                return $this->respond([
                    "status" => "1",
                    "message" => "Token Valid",
                    "time" => date('Y-m-d H:i:s'),
                    "userid" => strval($user->id),
                    "uuid" => "device_paired", // Place-holder for UUID
                    "user_name" => $user->username,
                    "user_email" => $user->email ?? "null"
                ]);
            }
        }
        
        return $this->respond(["status" => "0", "message" => "Invalid Token"]);
    }

    public function delete_account(){
        return $this->perform_deletion(true);
    }

    public function delete_data(){
        return $this->perform_deletion(false);
    }

    private function perform_deletion(bool $deleteAccount = false) {
        $db = \Config\Database::connect();
        $isApi = $this->request->getPost('varToken') ? true : false;
        $user_id = null;
        $rawTokens = [];

        if ($isApi) {
            $tokenStr = $this->request->getPost('varToken');
            $hashedToken = hash('sha256', $tokenStr);
            $identity = $db->table('auth_identities')
                ->where('type', \CodeIgniter\Shield\Authentication\Authenticators\AccessTokens::ID_TYPE_ACCESS_TOKEN)
                ->where('secret', $hashedToken)
                ->get()
                ->getRow();

            if (!$identity) {
                return $this->respond(["status" => "0", "message" => "Invalid Token"]);
            }
            $user_id = $identity->user_id;
            $rawTokens[] = $tokenStr;
        } else {
            if (!auth()->loggedIn()) {
                return redirect()->to('login');
            }
            $user_id = auth()->id();

            // Find all raw tokens belonging to this user by matching SHA-256(sms_owner)
            // against the stored hashed tokens in auth_identities
            if ($db->tableExists('tbl_Sms') && $db->tableExists('auth_identities')) {
                $ownerRows = $db->query("
                    SELECT DISTINCT s.sms_owner
                    FROM tbl_Sms s
                    WHERE SHA2(s.sms_owner, 256) IN (
                        SELECT secret FROM auth_identities
                        WHERE user_id = ? AND type = ?
                    )
                ", [$user_id, \CodeIgniter\Shield\Authentication\Authenticators\AccessTokens::ID_TYPE_ACCESS_TOKEN])->getResult();
                foreach ($ownerRows as $row) {
                    if (!empty($row->sms_owner)) {
                        $rawTokens[] = $row->sms_owner;
                    }
                }
            }

            // Also read from tbl_Loot as a fallback
            if ($db->tableExists('tbl_Loot') && $db->tableExists('auth_identities')) {
                $lootOwnerRows = $db->query("
                    SELECT DISTINCT l.loot_Owner
                    FROM tbl_Loot l
                    WHERE SHA2(l.loot_Owner, 256) IN (
                        SELECT secret FROM auth_identities
                        WHERE user_id = ? AND type = ?
                    )
                ", [$user_id, \CodeIgniter\Shield\Authentication\Authenticators\AccessTokens::ID_TYPE_ACCESS_TOKEN])->getResult();
                foreach ($lootOwnerRows as $row) {
                    if (!empty($row->loot_Owner)) {
                        $rawTokens[] = $row->loot_Owner;
                    }
                }
            }
        }

        // Also pick up any manually-linked device tokens from tbl_User_Devices
        if ($db->tableExists('tbl_User_Devices') && $user_id) {
            $userDevices = $db->table('tbl_User_Devices')->where('user_id', $user_id)->get()->getResult();
            $dbTokens = array_column($userDevices, 'device_token');
            if (!empty($dbTokens)) {
                $rawTokens = array_unique(array_merge($rawTokens, $dbTokens));
            }
        }

        // Capture the user's email (needed for the confirmation email after deletion).
        $userEmail = '';
        if ($user_id && $db->tableExists('auth_identities')) {
            $emailRow = $db->table('auth_identities')
                ->where('user_id', $user_id)
                ->where('type', 'email_password')
                ->get()
                ->getRow();
            $userEmail = $emailRow->secret ?? '';
        }

        // Count what will be removed so the confirmation email can report it.
        $smsDeleted = 0;
        $transactionsDeleted = 0;
        $filesDeleted = 0;
        if (!empty($rawTokens)) {
            if ($db->tableExists('tbl_Sms')) {
                $smsDeleted = (int)$db->table('tbl_Sms')->whereIn('sms_owner', $rawTokens)->countAllResults();
            }
            if ($db->tableExists('tbl_Loot')) {
                $filesDeleted = (int)$db->table('tbl_Loot')->whereIn('loot_Owner', $rawTokens)->countAllResults();
            }
        }
        if ($db->tableExists('tbl_Analyzed_Transactions') && $db->tableExists('tbl_Sms') && $db->tableExists('auth_identities')) {
            $tokenType = \CodeIgniter\Shield\Authentication\Authenticators\AccessTokens::ID_TYPE_ACCESS_TOKEN;
            $row = $db->query("
                SELECT COUNT(DISTINCT a.id) AS cnt
                FROM tbl_Analyzed_Transactions a
                INNER JOIN tbl_Sms s ON s.id = a.orig_sms_int_id OR s.sms__id = a.orig_sms_id
                INNER JOIN auth_identities i ON i.secret = SHA2(s.sms_owner, 256)
                WHERE i.user_id = ? AND i.type = ?
            ", [$user_id, $tokenType])->getRow();
            $transactionsDeleted = (int)($row->cnt ?? 0);
        }

        $db->transStart();

        if (!empty($rawTokens)) {
            // Processing tracking, jobs, and sender profiles are real tables.
            // tbl_Sms_Classification & tbl_Analyzed_Transactions are now views
            // derived from tbl_Sms — the raw SMS delete below removes them.
            if ($db->tableExists('tbl_Sms_Processing') && $db->tableExists('tbl_Sms')) {
                $db->table('tbl_Sms_Processing')
                   ->whereIn('sms_id', function(\CodeIgniter\Database\BaseBuilder $builder) use ($rawTokens) {
                       return $builder->select('id')->from('tbl_Sms')->whereIn('sms_owner', $rawTokens);
                   })->delete();
            }
            if ($db->tableExists('tbl_Processing_Jobs')) {
                $db->table('tbl_Processing_Jobs')
                   ->where('user_id', $user_id)
                   ->delete();
            }
            if ($db->tableExists('tbl_Sender_Profiles') && $db->tableExists('tbl_Sms')) {
                $db->table('tbl_Sender_Profiles')
                   ->whereIn('sp_owner', $rawTokens)
                   ->delete();
            }

            // Find all loot entries for these tokens to delete physical files
            if ($db->tableExists('tbl_Loot')) {
                $loots = $db->table('tbl_Loot')->whereIn('loot_Owner', $rawTokens)->get()->getResult();
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

            // Delete raw SMS (also removes derived classification/transactions views)
            if ($db->tableExists('tbl_Sms')) {
                $db->table('tbl_Sms')->whereIn('sms_owner', $rawTokens)->delete();
            }

            // Delete Loot Summary
            if ($db->tableExists('tbl_Loot_Summary') && $db->tableExists('tbl_Loot')) {
                $db->table('tbl_Loot_Summary')->whereIn('loot_Uuid', function(\CodeIgniter\Database\BaseBuilder $builder) use ($rawTokens) {
                    return $builder->select('loot_Uuid')->from('tbl_Loot')->whereIn('loot_Owner', $rawTokens);
                })->delete();
            }

            // Delete Loot
            if ($db->tableExists('tbl_Loot')) {
                $db->table('tbl_Loot')->whereIn('loot_Owner', $rawTokens)->delete();
            }
        }

        // Safety net (web flow only): remove ORPHANED data whose owner no longer
        // matches any registered token or linked device. This happens when a token
        // was revoked before deletion — the auth_identities row is gone, so the
        // token-based lookup above can't attribute the data. Without this,
        // "Delete My Data" would silently do nothing for orphaned uploads.
        $orphanSmsDeleted = 0;
        if (!$isApi && $db->tableExists('auth_identities')) {
            $registeredSub = "SELECT secret FROM auth_identities WHERE type = 'access_token'";
            if ($db->tableExists('tbl_User_Devices')) {
                $registeredSub .= " UNION SELECT SHA2(device_token, 256) FROM tbl_User_Devices";
            }

            if ($db->tableExists('tbl_Sms_Processing') && $db->tableExists('tbl_Sms')) {
                $db->query("DELETE FROM tbl_Sms_Processing WHERE sms_id IN (
                    SELECT id FROM tbl_Sms WHERE SHA2(sms_owner, 256) NOT IN ($registeredSub)
                )");
            }
            if ($db->tableExists('tbl_Sender_Profiles')) {
                $db->query("DELETE FROM tbl_Sender_Profiles WHERE SHA2(sp_owner, 256) NOT IN ($registeredSub)");
            }
            if ($db->tableExists('tbl_Sms')) {
                // Derived views (classification & analyzed transactions) are
                // removed together with the orphaned SMS rows.
                $orphanSmsDeleted = (int)$db->query("SELECT COUNT(*) AS c FROM tbl_Sms WHERE SHA2(sms_owner, 256) NOT IN ($registeredSub)")->getRow()->c;
                $db->query("DELETE FROM tbl_Sms WHERE SHA2(sms_owner, 256) NOT IN ($registeredSub)");
            }
            if ($db->tableExists('tbl_Loot_Summary') && $db->tableExists('tbl_Loot')) {
                $db->query("DELETE FROM tbl_Loot_Summary WHERE loot_Uuid IN (
                    SELECT loot_Uuid FROM tbl_Loot WHERE SHA2(loot_Owner, 256) NOT IN ($registeredSub)
                )");
            }
            if ($db->tableExists('tbl_Loot')) {
                $orphanLoots = $db->query("SELECT loot_Name FROM tbl_Loot WHERE SHA2(loot_Owner, 256) NOT IN ($registeredSub)")->getResult();
                foreach ($orphanLoots as $loot) {
                    try {
                        service('storage')->deletePayload($loot->loot_Name);
                    } catch (\Throwable $e) {}
                    $filePathTxt = WRITEPATH . 'uploads/payloads/' . ltrim($loot->loot_Name, '/');
                    $filePathJson = str_replace('.txt', '.json', $filePathTxt);
                    if (file_exists($filePathTxt)) @unlink($filePathTxt);
                    if (file_exists($filePathJson)) @unlink($filePathJson);
                }
                $db->query("DELETE FROM tbl_Loot WHERE SHA2(loot_Owner, 256) NOT IN ($registeredSub)");
            }
        }

        // Reset per-user data & settings so the DB looks like a freshly created account.
        if ($user_id) {
            foreach (['tbl_Transaction_Tags', 'tbl_Transaction_Notes', 'tbl_Spending_Goals', 'tbl_Budgets', 'tbl_Recurring_Transactions', 'tbl_User_Settings'] as $t) {
                if ($db->tableExists($t)) {
                    $db->table($t)->where('user_id', $user_id)->delete();
                }
            }
            // Transaction tag maps are cascade-deleted with their tags, but clean them explicitly too.
            if ($db->tableExists('tbl_Transaction_Tag_Map') && $db->tableExists('tbl_Transaction_Tags')) {
                $db->table('tbl_Transaction_Tag_Map')
                    ->whereIn('tag_id', function (\CodeIgniter\Database\BaseBuilder $b) use ($user_id) {
                        return $b->select('id')->from('tbl_Transaction_Tags')->where('user_id', $user_id);
                    })->delete();
            }
            // Unpair devices so the account is in a fresh state.
            if ($db->tableExists('tbl_User_Devices')) {
                $db->table('tbl_User_Devices')->where('user_id', $user_id)->delete();
            }
        }

        if ($deleteAccount) {
            // Delete Shield User completely
            $db->table('auth_identities')->where('user_id', $user_id)->delete();
            $db->table('auth_logins')->where('user_id', $user_id)->delete();
            $db->table('auth_groups_users')->where('user_id', $user_id)->delete();
            $db->table('auth_permissions_users')->where('user_id', $user_id)->delete();
            $db->table('users')->where('id', $user_id)->delete();
        }

        $db->transComplete();

        if ($db->transStatus() === false) {
            if ($isApi) {
                return $this->respond(["status" => "0", "message" => "Failed to perform operation. Database error."]);
            }
            return redirect()->back()->with('error', 'Failed to perform operation. Database error.');
        }

        // Send a confirmation email (HTML styled, includes an Email ID).
        if (!empty($userEmail)) {
            $notifyData = [
                'smsDeleted'          => $smsDeleted,
                'transactionsDeleted' => $transactionsDeleted,
                'filesDeleted'        => $filesDeleted,
                'orphanSmsDeleted'    => $orphanSmsDeleted,
                'deletedAt'           => date('Y-m-d H:i:s T'),
            ];
            if ($deleteAccount) {
                if (\App\Libraries\Notifier::isTriggerEnabled('user_account_deleted')) {
                    \App\Libraries\Notifier::sendTrigger($userEmail, 'user_account_deleted', $notifyData);
                }
            } elseif (\App\Libraries\Notifier::isTriggerEnabled('user_deleted_data')) {
                \App\Libraries\Notifier::sendTrigger($userEmail, 'user_deleted_data', $notifyData);
            }
        }

        if ($isApi) {
            return $this->respond(["status" => "1", "message" => "Operation successfully completed."]);
        }

        if ($deleteAccount) {
            auth()->logout();
            return redirect()->to('login')->with('message', 'Your account has been deleted permanently.');
        } else {
            if (($smsDeleted + $transactionsDeleted + $filesDeleted + $orphanSmsDeleted) === 0) {
                return redirect()->back()->with('info', 'No data was found to delete. Your account was preserved.');
            }
            return redirect()->back()->with('message', 'Your data has been successfully deleted. Your account was preserved.');
        }
    }
}
