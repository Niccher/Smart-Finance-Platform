<?php

namespace App\Controllers\Api\V1;

use App\Models\UserSettingModel;
use CodeIgniter\HTTP\ResponseInterface;

class SettingsController extends BaseApiController
{
    public function profile(): ResponseInterface
    {
        $user = $this->getUserFromToken();
        if (!$user) return $this->failUnauthorized('Invalid token');

        return $this->respond([
            'status' => '1',
            'user' => [
                'id' => $user->id,
                'username' => $user->username,
                'email' => $user->email,
                'created_at' => $user->created_at,
            ]
        ]);
    }

    public function updateProfile(): ResponseInterface
    {
        $user = $this->getUserFromToken();
        if (!$user) return $this->failUnauthorized('Invalid token');

        $json = $this->request->getJSON(true) ?? [];
        $username = $this->request->getPost('username') ?? ($json['username'] ?? null);
        $email = $this->request->getPost('email') ?? ($json['email'] ?? null);

        $data = [];
        if (!empty($username) && $username !== $user->username) $data['username'] = $username;
        if (!empty($email) && $email !== $user->email) $data['email'] = $email;

        if (!empty($data)) {
            $user->fill($data);
            $users = model(\CodeIgniter\Shield\Models\UserModel::class);
            $users->save($user);
        }

        return $this->respond(['status' => '1', 'message' => 'Profile updated']);
    }

    public function preferences(): ResponseInterface
    {
        $user = $this->getUserFromToken();
        if (!$user) return $this->failUnauthorized('Invalid token');

        $settings = (new UserSettingModel())->getSettings($user->id);

        return $this->respond([
            'status' => '1',
            'settings' => $settings,
        ]);
    }

    public function savePreferences(): ResponseInterface
    {
        $user = $this->getUserFromToken();
        if (!$user) return $this->failUnauthorized('Invalid token');

        $settingsModel = new UserSettingModel();
        $existing = $settingsModel->getSettings($user->id);

        $fields = ['currency', 'date_format', 'time_format', 'default_budget_period',
                    'notify_email_alerts', 'notify_budget_alerts', 'notify_low_balance',
                    'notify_unusual_activity', 'export_default_format'];

        $json = $this->request->getJSON(true) ?? [];
        $data = [];
        foreach ($fields as $f) {
            $val = $this->request->getPost($f) ?? ($json[$f] ?? null);
            if ($val !== null) $data[$f] = $val;
        }

        if (!empty($data)) {
            $data['dashboard_widgets'] = $existing['dashboard_widgets'] ?? null;
            $settingsModel->saveSettings($user->id, $data);
        }

        return $this->respond(['status' => '1', 'message' => 'Preferences saved']);
    }
}
