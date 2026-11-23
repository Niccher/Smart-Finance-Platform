<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\Notifier;
use CodeIgniter\API\ResponseTrait;

class Notifications extends BaseController
{
    use ResponseTrait;

    public function index()
    {
        $config = Notifier::config();
        $triggers = Notifier::triggers();

        $shieldSettings = [];
        foreach (['Email.fromEmail', 'Email.fromName', 'Email.protocol', 'Email.SMTPHost', 'Email.SMTPUser', 'Email.SMTPPass', 'Email.SMTPPort', 'Email.SMTPCrypto', 'Email.mailType'] as $key) {
            $shieldSettings[$key] = setting($key);
        }

        return view('Admin/Notifications/index', [
            'bg_color'                  => '#B1B8ED',
            'config'                    => $config,
            'triggers'                  => $triggers,
            'trigger_meta'              => Notifier::triggerMeta(),
            'custom_triggers'           => Notifier::customTriggers(),
            'shield'                    => $shieldSettings,
            'email_log'                 => Notifier::logEntries(50),
            'email_log_count'           => Notifier::logCount(),
            'email_queue'               => Notifier::queueEntries(50),
            'email_queue_count'         => Notifier::queueCount(),
            'email_queue_pending_count' => Notifier::queueCount('pending'),
        ]);
    }

    public function saveConfig()
    {
        $fromEmail = trim((string)$this->request->getPost('from_email'));
        $smtpUser  = trim((string)$this->request->getPost('smtp_user'));

        // If from_email is omitted or default example.com, and smtp_user is a valid email, auto-match them
        if (($fromEmail === '' || $fromEmail === 'noreply@example.com' || strpos($fromEmail, 'example.com') !== false) && filter_var($smtpUser, FILTER_VALIDATE_EMAIL)) {
            $fromEmail = $smtpUser;
        }

        $config = [
            'smtp_host'   => trim((string)$this->request->getPost('smtp_host')),
            'smtp_port'   => (int)$this->request->getPost('smtp_port') ?: 587,
            'smtp_user'   => $smtpUser,
            'smtp_pass'   => (string)$this->request->getPost('smtp_pass'),
            'smtp_crypto' => in_array($this->request->getPost('smtp_crypto'), ['tls', 'ssl', 'none'], true) ? $this->request->getPost('smtp_crypto') : 'tls',
            'from_email'  => $fromEmail,
            'from_name'   => trim((string)$this->request->getPost('from_name')),
            'enabled'     => (bool)$this->request->getPost('enabled'),
        ];

        if ($config['from_email'] !== '' && !filter_var($config['from_email'], FILTER_VALIDATE_EMAIL)) {
            return $this->response->setJSON(['status' => 'error', 'message' => 'Invalid "from" email address.']);
        }

        $db = \Config\Database::connect();
        $updates = [
            ['key' => 'email_smtp_host', 'value' => $config['smtp_host'], 'type' => 'string', 'description' => 'SMTP server host'],
            ['key' => 'email_smtp_port', 'value' => $config['smtp_port'], 'type' => 'integer', 'description' => 'SMTP server port'],
            ['key' => 'email_smtp_user', 'value' => $config['smtp_user'], 'type' => 'string', 'description' => 'SMTP username'],
            ['key' => 'email_smtp_crypto', 'value' => $config['smtp_crypto'], 'type' => 'string', 'description' => 'SMTP encryption (tls/ssl/none)'],
            ['key' => 'email_from_email', 'value' => $config['from_email'], 'type' => 'string', 'description' => 'From email address'],
            ['key' => 'email_from_name', 'value' => $config['from_name'], 'type' => 'string', 'description' => 'From display name'],
            ['key' => 'email_enabled', 'value' => $config['enabled'] ? '1' : '0', 'type' => 'boolean', 'description' => 'Enable email notifications'],
        ];

        if ($config['smtp_pass'] !== '') {
            $updates[] = ['key' => 'email_smtp_pass', 'value' => $config['smtp_pass'], 'type' => 'string', 'description' => 'SMTP password'];
        }

        foreach ($updates as $u) {
            $db->table('tbl_Settings')->upsert($u);
        }

        $this->syncShieldEmailConfig($config);

        return $this->response->setJSON([
            'status' => 'success',
            'message' => 'Email/SMTP configuration saved.',
        ]);
    }

    public function sendTestEmail()
    {
        $to = trim((string)$this->request->getPost('test_email'));
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return $this->response->setJSON(['status' => 'error', 'message' => 'Enter a valid test email address.']);
        }

        $result = Notifier::sendTestEmail($to);

        return $this->response->setJSON($result);
    }

    public function saveTriggers()
    {
        $posted = $this->request->getPost('triggers');
        $triggers = [];

        // Save toggle state for every known trigger (built-in + custom).
        foreach (Notifier::triggerMeta() as $meta) {
            $triggers[$meta['key']] = !empty($posted[$meta['key']]);
        }

        $db = \Config\Database::connect();
        $db->table('tbl_Settings')->upsert([
            'key' => 'email_triggers',
            'value' => json_encode($triggers),
            'type' => 'json',
            'description' => 'Email notification triggers',
        ]);

        return $this->response->setJSON([
            'status' => 'success',
            'message' => 'Email trigger settings saved.',
        ]);
    }



    /**
     * Mirror SMTP/from config into Shield's settings table so magic-link
     * (password reset) and Shield-sent emails use the same SMTP server.
     */
    private function syncShieldEmailConfig(array $config): void
    {
        $map = [
            'Email.fromEmail'  => $config['from_email'],
            'Email.fromName'   => $config['from_name'],
            'Email.protocol'   => 'smtp',
            'Email.SMTPHost'   => $config['smtp_host'],
            'Email.SMTPUser'   => $config['smtp_user'],
            'Email.SMTPPass'   => $config['smtp_pass'],
            'Email.SMTPPort'   => $config['smtp_port'],
            'Email.SMTPCrypto' => $config['smtp_crypto'] === 'none' ? '' : $config['smtp_crypto'],
            'Email.mailType'   => 'text',
        ];

        foreach ($map as $key => $value) {
            if ($key === 'Email.SMTPPort') {
                $value = (int)$value;
            }
            setting($key, $value);
        }
    }
}
