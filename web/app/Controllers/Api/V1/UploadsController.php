<?php

namespace App\Controllers\Api\V1;

use App\Models\UploadModel;
use CodeIgniter\HTTP\ResponseInterface;
use App\Controllers\Home;

class UploadsController extends BaseApiController
{
    private $db;

    public function __construct()
    {
        $this->db = \Config\Database::connect();
    }

    public function upload(): ResponseInterface
    {
        helper('text');
        $modUpload = new UploadModel();
        $dated = date('Y-m-d H:i:s');
        $uuid = random_string('alnum', 16);

        if (!$this->request->is('post')) {
            return $this->fail('Method not allowed', 405);
        }

        $token = (string) ($this->request->getPost('varUser') ?: $this->request->getPost('varToken'));
        $devId = (string) ($this->request->getPost('varDev') ?: $this->request->getPost('varDevId'));
        $isContinuation = $this->request->getPost('is_continuation') === 'true' || $this->request->getPost('varBatch') === '1';

        $user = $this->getUserFromToken();
        $userId = $user ? (int)$user->id : null;

        try {
            $file = $this->request->getFile('loot_file') ?? $this->request->getFile('varLoot');
            if ($file === null) {
                throw new \RuntimeException('No file uploaded: expected loot_file or varLoot multipart');
            }
            if (!$file->isValid()) {
                throw new \RuntimeException('Invalid file uploaded: ' . $file->getErrorString() . ' (code ' . $file->getError() . ')');
            }

            $uploadPath = WRITEPATH . 'uploads/payloads/';
            if (!is_dir($uploadPath)) {
                mkdir($uploadPath, 0755, true);
            }

            $newName = 'loot_' . $uuid . '.enc';
            $file->move($uploadPath, $newName);
            $filePath = $uploadPath . $newName;

            // Store in MinIO / S3 object storage with hybrid local fallback
            try {
                $storage = service('storage');
                $storage->putPayload($newName, (string) file_get_contents($filePath));
            } catch (\Throwable $storageEx) {
                log_message('warning', 'Storage payload upload notice: ' . $storageEx->getMessage());
            }

            $data = [
                'loot_Name' => $newName,
                'loot_Uuid' => $uuid,
                'loot_Owner' => $token,
                'loot_Device' => $devId,
                'loot_Created' => $dated,
                'loot_user_id' => $userId,
                'loot_ip' => $this->request->getIPAddress()
            ];

            $this->db->transStart();

            if (!$modUpload->file_upload($data)) {
                throw new \RuntimeException('Database insert failed for loot record');
            }

            $parseResult = $modUpload->loot_parse_sms($uuid, $data['loot_Owner'], $data['loot_Device'], $dated, $userId);

            if (($parseResult['status'] ?? '') !== 'success') {
                throw new \RuntimeException($parseResult['message'] ?? 'SMS parse failed');
            }

            $this->db->transComplete();

            if ($this->db->transStatus() === false) {
                throw new \RuntimeException('Upload transaction failed');
            }

            if ($userId !== null) {
                $this->db->table('tbl_Devices')
                    ->where('device_Uuid', $devId)
                    ->update(['device_user_id' => $userId]);

                $this->triggerMlProcessingWebhook($userId);
            }

            $this->auditApiCall('upload', $token, $devId, (int) ($parseResult['processed'] ?? 0), ['is_continuation' => $isContinuation]);

            return $this->respond([
                'status' => self::STATUS_SUCCESS,
                'time' => $dated,
                'message' => 'File uploaded and processed successfully',
                'uuid' => $uuid,
                'processed' => $parseResult['processed'] ?? 0
            ], 200);

        } catch (\Throwable $e) {
            log_message('error', 'Upload Error: ' . $e->getMessage());
            return $this->respond([
                'status' => self::STATUS_ERROR,
                'time' => $dated,
                'message' => 'File processing failed: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function scanTrigger(): ResponseInterface
    {
        $user = $this->getUserFromToken();
        if (!$user) return $this->failUnauthorized('Invalid token');

        $mlBase = (string) config('MlBackend')->baseUrl;
        $fastApiUrl = $mlBase . '/process/for-user/' . $user->id;
        $client = \Config\Services::curlrequest();

        try {
            $resp = $client->post($fastApiUrl, ['timeout' => 5]);
            $body = json_decode($resp->getBody(), true);
            return $this->respond([
                'status' => 'started',
                'message' => 'Scan triggered',
                'processed' => $body['messages_processed'] ?? 0,
            ]);
        } catch (\Throwable $e) {
            return $this->respond(['status' => 'error', 'message' => 'Service unavailable'], 503);
        }
    }

    public function scanProgress(): ResponseInterface
    {
        $user = $this->getUserFromToken();
        if (!$user) return $this->failUnauthorized('Invalid token');

        $controller = new Home();
        return $controller->progress();
    }

    public function uploadListing(): ResponseInterface
    {
        $modUpload = new UploadModel();
        $dated = date('Y-m-d H:i:s');
        $token = (string) $this->request->getPost('varUser');
        $devId = (string) $this->request->getPost('varDev');

        try {
            $this->auditApiCall('get/my_uploads', $token, $devId);
            $uploads = $modUpload->file_listing($token, $devId);
            $result = [];

            foreach ($uploads as $upload) {
                $summary = $modUpload->loot_summary($upload->loot_Uuid);
                $createdRaw = $upload->loot_Created;
                $summaryCreated = is_numeric($createdRaw) ? date('Y-m-d H:i:s', (int)$createdRaw) : $createdRaw;
                
                $categoryBreakdown = $modUpload->get_upload_category_breakdown($upload->loot_Uuid);
                $financialSummary = $modUpload->get_upload_financial_summary($upload->loot_Uuid);
                $directionBreakdown = $modUpload->get_upload_direction_breakdown($upload->loot_Uuid);
                $transactionTypeBreakdown = $modUpload->get_upload_transaction_type_breakdown($upload->loot_Uuid);
                $topCounterparties = $modUpload->get_upload_top_counterparties($upload->loot_Uuid);
                
                $result[] = [
                    'summary_Loot_Uuid' => $upload->loot_Uuid,
                    'summary_Created' => $summaryCreated,
                    'summary_Count' => (int)($summary[0]->info_All ?? 0),
                    'summary_Received' => (float)($financialSummary['inflow_amount'] ?? 0.0),
                    'summary_Sent' => (float)($financialSummary['outflow_amount'] ?? 0.0),
                    'summary_Unknown' => (int)($directionBreakdown['Unknown'] ?? 0),
                    'finance_senders' => $financialSummary['counterparties'],
                    'total_amount' => $financialSummary['total_amount'],
                    'category_breakdown' => $categoryBreakdown,
                    'direction_breakdown' => $directionBreakdown,
                    'transaction_type_breakdown' => $transactionTypeBreakdown,
                    'top_counterparties' => $topCounterparties,
                ];
            }

            return $this->respond([
                'status' => self::STATUS_SUCCESS,
                'time' => $dated,
                'summarizer' => $result
            ]);
        } catch (\Throwable $e) {
            return $this->respond([
                'status' => self::STATUS_ERROR,
                'time' => $dated,
                'message' => 'Failed to retrieve uploads'
            ]);
        }
    }

    public function lootUploadedCount(): ResponseInterface
    {
        $modUpload = new UploadModel();
        $dated = date('Y-m-d H:i:s');
        $token = (string) $this->request->getPost('varUser');
        $devId = (string) $this->request->getPost('varDev');

        try {
            $uploads = $modUpload->file_listing($token, $devId);
            return $this->respond([
                'msg_status' => self::STATUS_SUCCESS,
                'msg_count' => count($uploads),
                'msg_time' => $dated,
                'msg_state' => empty($uploads) ? 'empty' : 'found'
            ]);
        } catch (\Throwable $e) {
            return $this->respond([
                'msg_status' => self::STATUS_ERROR,
                'msg_time' => $dated,
                'message' => 'Failed to count uploads'
            ]);
        }
    }

    public function uploadSummaryCalculation(): ResponseInterface
    {
        $modUpload = new UploadModel();
        $dated = date('Y-m-d H:i:s');

        if (!$this->request->is('post')) {
            return $this->fail('Method not allowed', 405);
        }

        try {
            $token = (string) $this->request->getPost('varUser');
            $devId = (string) $this->request->getPost('varDev');
            $lootUuid = (string) $this->request->getPost('varLootUuid');

            $user = $this->getUserFromToken();
            if (!$user) {
                return $this->failUnauthorized('Invalid token');
            }

            $this->auditApiCall('get/my_summary_calculations', $token, $devId, 0, ['loot_uuid' => $lootUuid]);
            $summary = $modUpload->loot_summary($lootUuid);

            if (empty($summary) || (isset($summary[0]->user_id) && (int)$summary[0]->user_id !== (int)$user->id)) {
                return $this->respond([
                    'status' => self::STATUS_ERROR,
                    'time' => $dated,
                    'message' => 'No data found for this upload'
                ]);
            }

            $response_data = [
                'status' => self::STATUS_SUCCESS,
                'time' => $dated,
                'loot_summarizer' => $this->formatSummaryData($summary[0], $lootUuid)
            ];

            return $this->respond([
                'loot_summarizer' => $response_data['loot_summarizer']
            ]);

        } catch (\Throwable $e) {
            log_message('error', 'Summary Calculation Error: ' . $e->getMessage());
            return $this->respond([
                'status' => self::STATUS_ERROR,
                'time' => $dated,
                'message' => 'Failed to calculate summary',
                'error' => $e->getMessage()
            ]);
        }
    }

    private function formatSummaryData(object $summary, string $lootUuid = ''): array
    {
        $modUpload = new UploadModel();
        $dir = $lootUuid !== '' ? $modUpload->get_upload_direction_breakdown($lootUuid) : (json_decode($summary->direction_breakdown ?? '{}', true) ?? []);
        $typeBreakdown = $lootUuid !== '' ? $modUpload->get_upload_transaction_type_breakdown($lootUuid) : (json_decode($summary->transaction_type_breakdown ?? '{}', true) ?? []);
        $catBreakdown = $lootUuid !== '' ? $modUpload->get_upload_category_breakdown($lootUuid) : [];
        $financial = $lootUuid !== '' ? $modUpload->get_upload_financial_summary($lootUuid) : ['total_amount' => 0.0, 'counterparties' => 0];
        $topCounterparties = $lootUuid !== '' ? $modUpload->get_upload_top_counterparties($lootUuid) : [];
        return [
            'status'                      => 1,
            'count_All'                   => (int)($summary->info_All ?? 0),
            'count_Unknown'               => (int)($dir['Unknown'] ?? 0),
            'loot_Created'                => $summary->loot_Created ?? '',
            'loot_Uuid'                   => $summary->loot_Uuid ?? '',
            'direction_breakdown'         => $dir,
            'transaction_type_breakdown'  => $typeBreakdown,
            'category_breakdown'          => $catBreakdown,
            'total_amount'                => (float)($financial['total_amount'] ?? 0.0),
            'finance_senders'             => (int)($financial['counterparties'] ?? 0),
            'top_counterparties'          => $topCounterparties,
        ];
    }

    public function deleteAccount(): ResponseInterface
    {
        return $this->performDeletion(true);
    }

    public function deleteData(): ResponseInterface
    {
        return $this->performDeletion(false);
    }

    private function performDeletion(bool $deleteAccount = false): ResponseInterface
    {
        $tokenStr = $this->request->getPost('varToken');
        if (empty($tokenStr)) {
            return $this->respond(["status" => "0", "message" => "varToken is required"]);
        }

        $hashedToken = hash('sha256', $tokenStr);
        $identity = $this->db->table('auth_identities')
            ->where('type', \CodeIgniter\Shield\Authentication\Authenticators\AccessTokens::ID_TYPE_ACCESS_TOKEN)
            ->where('secret', $hashedToken)
            ->get()
            ->getRow();

        if (!$identity) {
            return $this->respond(["status" => "0", "message" => "Invalid Token"]);
        }
        $user_id = $identity->user_id;

        $this->db->transStart();

        $smsIds = [];
        $smsRows = $this->db->table('tbl_Sms')->select('id')->where('sms_owner', $tokenStr)->get()->getResult();
        foreach ($smsRows as $r) $smsIds[] = $r->id;

        if (!empty($smsIds)) {
            if ($this->db->tableExists('tbl_Sms_Processing')) {
                $this->db->table('tbl_Sms_Processing')->whereIn('sms_id', $smsIds)->delete();
            }
            if ($this->db->tableExists('tbl_Sms_Classification')) {
                $this->db->table('tbl_Sms_Classification')->whereIn('sms_id', $smsIds)->delete();
            }
            if ($this->db->tableExists('tbl_Analyzed_Transactions')) {
                $this->db->table('tbl_Analyzed_Transactions')->whereIn('orig_sms_id', $smsIds)->delete();
            }
            $this->db->table('tbl_Sms')->whereIn('id', $smsIds)->delete();
        }

        // Purge stored payload files from MinIO / local storage
        $lootRows = $this->db->table('tbl_Loot')->select('loot_Name')->where('loot_Owner', $tokenStr)->get()->getResult();
        $storage = service('storage');
        foreach ($lootRows as $lRow) {
            try {
                if (!empty($lRow->loot_Name)) {
                    $storage->deletePayload($lRow->loot_Name);
                }
            } catch (\Throwable $delEx) {
                // Ignore storage deletion errors during user purge
            }
        }

        $this->db->table('tbl_Loot')->where('loot_Owner', $tokenStr)->delete();
        $this->db->table('tbl_Devices')->where('device_user_id', $user_id)->delete();
        $this->db->table('tbl_Sender_Profiles')->where('sp_owner', $tokenStr)->delete();

        if ($deleteAccount) {
            $this->db->table('auth_identities')->where('user_id', $user_id)->delete();
            $this->db->table('auth_groups_users')->where('user_id', $user_id)->delete();
            $this->db->table('users')->where('id', $user_id)->delete();
        }

        $this->db->transComplete();

        if ($this->db->transStatus() === false) {
            return $this->respond(["status" => "0", "message" => "Purge failed"]);
        }

        return $this->respond([
            "status" => "1",
            "message" => $deleteAccount ? "Account and data purged successfully" : "Data purged successfully"
        ]);
    }

    public function export(string $format = 'csv')
    {
        $user = $this->getUserFromToken();
        if (!$user) return $this->failUnauthorized('Invalid token');

        $modUploads = new UploadModel();
        $transactions = $modUploads->getAllTransactionsForUser($user->id);

        if ($format === 'json') {
            return $this->response
                ->setHeader('Content-Type', 'application/json')
                ->setHeader('Content-Disposition', 'attachment; filename="mpesa_export.json"')
                ->setBody(json_encode($transactions, JSON_PRETTY_PRINT));
        }

        $filename = 'mpesa_export.csv';
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

    private function triggerMlProcessingWebhook(?int $userId): void
    {
        if (!$userId) return;
        $mlBaseUrl = (string) env('ML_BACKEND_URL', 'http://127.0.0.1:8000');
        $mlSecret = (string) env('ML_INTERNAL_SECRET', '');

        try {
            $client = \Config\Services::curlrequest([
                'base_url' => $mlBaseUrl,
                'timeout'  => 3,
            ]);

            $headers = ['Content-Type' => 'application/json'];
            if (!empty($mlSecret)) {
                $headers['X-Internal-Secret'] = $mlSecret;
            }

            $client->post("/process/for-user/{$userId}", [
                'headers' => $headers,
                'http_errors' => false,
            ]);
        } catch (\Throwable $e) {
            log_message('warning', 'Could not trigger ML webhook: ' . $e->getMessage());
        }
    }
}
