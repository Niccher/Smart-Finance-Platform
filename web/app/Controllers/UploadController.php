<?php

namespace App\Controllers;

use App\Models\UploadModel;
use App\Models\AppUserModel;
use App\Libraries\CryptoHelper;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Files\File;
use CodeIgniter\API\ResponseTrait;
use CodeIgniter\Controller;
use CodeIgniter\Shield\Authentication\Authenticators\AccessTokens;

class UploadController extends BaseController
{
    use ResponseTrait;

    // Constants for response statuses
    private const STATUS_SUCCESS = 1;
    private const STATUS_ERROR = 0;
    private const STATUS_INVALID_REQUEST = 2;

    protected $db;

    public function __construct() {
        // Load the database service (CI4 way)
        $this->db = \Config\Database::connect();
    }

    /**
     * Upload and process SMS data file
     */
    private const UPLOAD_COOLDOWN_SECONDS = 30;

    public function upload(): ResponseInterface {
        helper('text');
        $modUpload = new UploadModel();
        $dated = time();
        $uuid = random_string('alnum', 16);
        $token = (string) $this->request->getPost('varToken');
        $devId = (string) $this->request->getPost('varDevId');
        $isContinuation = $this->request->getPost('varBatch') === '1';

        // Resolve the token to its numeric user once at ingest; stable across token rotation
        $userId = null;
        $identity = $this->db->table('auth_identities')
            ->select('user_id')
            ->where('type', AccessTokens::ID_TYPE_ACCESS_TOKEN)
            ->where('secret', hash('sha256', $token))
            ->get()
            ->getRow();
        $userId = $identity->user_id ?? null;

        log_message('info', "=== UPLOAD REQUEST START ===");
        log_message('info', 'Upload request: token=' . substr($token, 0, 8) . '... devId=' . $devId);
        log_message('debug', 'Request method: ' . $this->request->getMethod());
        log_message('debug', 'POST keys: ' . json_encode(array_keys($this->request->getPost())));
        log_message('debug', 'FILES keys: ' . json_encode(array_keys($_FILES ?? [])));

        if (!$this->request->is('post')) {
            log_message('warning', 'Upload: Method not allowed (not POST)');
            return $this->respond([
                'status' => self::STATUS_INVALID_REQUEST,
                'time' => $dated,
                'message' => 'Method not allowed'
            ], 405);
        }

        $validation = $this->validate([
            'varToken' => 'required|string|min_length[8]|max_length[64]',
            'varDevId' => 'required|string|min_length[8]|max_length[64]',
            'varLoot' => 'uploaded[varLoot]|max_size[varLoot,51200]',
        ]);

        if (!$validation) {
            $errors = $this->validator->getErrors();
            log_message('error', 'Upload validation failed: ' . json_encode($errors));
            return $this->respond([
                'status' => self::STATUS_INVALID_REQUEST,
                'time' => $dated,
                'message' => 'Validation failed: ' . implode('; ', $errors),
                'errors' => $errors
            ], 422);
        }

        if ($devId === 'nullable' || $token === 'nullable') {
            log_message('error', 'Upload rejected: token or device not registered (nullable)');
            return $this->respond([
                'status' => self::STATUS_INVALID_REQUEST,
                'time' => $dated,
                'message' => 'Device not registered. Re-link the app with your token (device fingerprint required).'
            ], 422);
        }

        // Rate limiting: reject if last upload from this token was within cooldown.
        // Continuation batches of the same sync session (varBatch=1) bypass the cooldown.
        if (!$isContinuation) {
            $lastUpload = $this->db->table('tbl_Loot')
                ->select('MAX(loot_Created) as last')
                ->where('loot_Owner', $token)
                ->get()
                ->getRow();
            if ($lastUpload && $lastUpload->last) {
                $lastTime = strtotime($lastUpload->last);
                if ($lastTime && (time() - $lastTime) < self::UPLOAD_COOLDOWN_SECONDS) {
                    $wait = self::UPLOAD_COOLDOWN_SECONDS - (time() - $lastTime);
                    log_message('info', "Upload rate-limited for token {$token}: wait {$wait}s");
                    return $this->respond([
                        'status' => self::STATUS_ERROR,
                        'time' => $dated,
                        'message' => "Please wait {$wait} seconds before uploading again."
                     ], 429);
                }
            }
        }

        try {
            $file = $this->request->getFile('varLoot');
            
            if (!$file->isValid()) {
                $fileErrors = $file->getErrorString();
                log_message('error', 'Upload file invalid: ' . $fileErrors);
                throw new \RuntimeException('Invalid file upload: ' . $fileErrors);
            }
            
            log_message('info', 'Upload file received: name=' . $file->getClientName() . ' size=' . $file->getSize() . ' type=' . $file->getClientMimeType());
            
            $newName = 'loot_' . $uuid . '.enc';
            $uploadPath = WRITEPATH . 'uploads/payloads/';

            if (!is_dir($uploadPath)) {
                if (!mkdir($uploadPath, 0755, true)) {
                    log_message('error', 'Failed to create upload directory: ' . $uploadPath);
                    throw new \RuntimeException('Failed to create upload directory');
                }
                log_message('info', 'Created upload directory: ' . $uploadPath);
            }

            if (!$file->move($uploadPath, $newName)) {
                log_message('error', 'File move failed to ' . $uploadPath . $newName);
                throw new \RuntimeException('File move failed');
            }
            
            log_message('info', 'File moved successfully to: ' . $uploadPath . $newName);

            // Replicate to MinIO / S3 object storage with hybrid fallback
            try {
                $storage = service('storage');
                $storage->putPayload($newName, (string) file_get_contents($uploadPath . $newName));
            } catch (\Throwable $storageEx) {
                log_message('warning', 'Storage payload upload notice: ' . $storageEx->getMessage());
            }

            $data = [
                'loot_Name' => $newName,
                'loot_Owner' => $token,
                'loot_Uuid' => $uuid,
                'loot_Device' => $devId,
                'loot_Created' => $dated,
                'loot_user_id' => $userId,
                'loot_ip' => $this->request->getIPAddress()
            ];

            $this->db->transStart();

            if (!$modUpload->file_upload($data)) {
                log_message('error', 'Database insert failed for loot record');
                throw new \RuntimeException('Database insert failed for loot record');
            }

            log_message('info', 'Loot record inserted, starting SMS parse for uuid=' . $uuid);

            // Throws on decrypt/parse/summary failure — do not ignore result
            $parseResult = $modUpload->loot_parse_sms($uuid, $data['loot_Owner'], $data['loot_Device'], $dated, $userId);

            if (($parseResult['status'] ?? '') !== 'success') {
                log_message('error', 'SMS parse failed: ' . ($parseResult['message'] ?? 'unknown'));
                throw new \RuntimeException($parseResult['message'] ?? 'SMS parse failed');
            }

            $this->db->transComplete();

            if ($this->db->transStatus() === false) {
                log_message('error', 'Upload transaction failed');
                throw new \RuntimeException('Upload transaction failed');
            }

            // Keep device registration linked to the owning user (id is stable across rotations)
            if ($userId !== null) {
                $this->db->table('tbl_Devices')
                    ->where('device_Uuid', $devId)
                    ->update(['device_user_id' => $userId]);
            }

            $this->auditApiCall(
                'upload',
                $token,
                $devId,
                (int) ($parseResult['processed'] ?? 0),
                ['is_continuation' => $isContinuation]
            );

            log_message('info', 'Upload success uuid=' . $uuid . ' processed=' . ($parseResult['processed'] ?? 0));

            return $this->respond([
                'status' => self::STATUS_SUCCESS,
                'time' => $dated,
                'message' => 'File uploaded and processed successfully',
                'uuid' => $uuid,
                'processed' => $parseResult['processed'] ?? 0
            ], 200);

        } catch (\Throwable $e) {
            log_message('error', 'Upload Error: ' . $e->getMessage() . ' | Trace: ' . $e->getTraceAsString());

            \App\Libraries\Audit::log(
                'upload',
                'data',
                'Upload failed: ' . $e->getMessage(),
                [
                    'token_hash'  => hash('sha256', $token),
                    'device_uuid' => $devId,
                    'is_error'    => true,
                ],
                $userId
            );

            return $this->respond([
                'status' => self::STATUS_ERROR,
                'time' => $dated,
                'message' => 'File processing failed: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Register or identify a device
     */
    public function device_print(): ResponseInterface {
        helper('text');
        $modUpload = new UploadModel();
        $dated = date('Y-m-d H:i:s');
        $uuid = random_string('alnum', 16);

        if (!$this->request->is('post')) {
            return $this->fail('Method not allowed', 405);
        }

        $fingerprint = (string) $this->request->getPost('device_Fingerprint');
        $model = (string) $this->request->getPost('device_Model');
        $brand = (string) $this->request->getPost('device_Brand');

        $deviceData = [
            'device_Device' => (string) $this->request->getPost('device_Device'),
            'device_Uuid' => $uuid,
            'device_Created_At' => $dated,
            'device_Product' => (string) $this->request->getPost('device_Product'),
            'device_Bootloader' => (string) $this->request->getPost('device_Bootloader'),
            'device_Type' => (string) $this->request->getPost('device_Type'),
            'device_Tags' => (string) $this->request->getPost('device_Tags'),
            'device_Host' => (string) $this->request->getPost('device_Host'),
            'device_Display' => (string) $this->request->getPost('device_Display'),
            'device_Hardware' => (string) $this->request->getPost('device_Hardware'),
            'device_Fingerprint' => $fingerprint,
            'device_Manufacturer' => (string) $this->request->getPost('device_Manufacturer'),
            'device_Brand' => $brand,
            'device_Board' => (string) $this->request->getPost('device_Board'),
            'device_User' => (string) $this->request->getPost('device_User'),
            'device_Model' => $model,
            'device_Time' => $this->parseLongInt($this->request->getPost('device_Time')),
            'device_Serial' => (string) $this->request->getPost('device_Serial'),
            // Permission-free signals from the app (see DeviceFingerprint.kt)
            'device_AndroidId' => (string) $this->request->getPost('device_AndroidId'),
            'device_AppCertHash' => (string) $this->request->getPost('device_AppCertHash'),
            'device_AppVersion' => (string) $this->request->getPost('device_AppVersion'),
            'device_FirstInstallTime' => (string) $this->request->getPost('device_FirstInstallTime'),
            'device_LastUpdateTime' => (string) $this->request->getPost('device_LastUpdateTime'),
            'device_Sensors' => (string) $this->request->getPost('device_Sensors'),
            'device_ScreenWidth' => $this->parseIntSafe($this->request->getPost('device_ScreenWidth')),
            'device_ScreenHeight' => $this->parseIntSafe($this->request->getPost('device_ScreenHeight')),
            'device_DensityDpi' => $this->parseIntSafe($this->request->getPost('device_DensityDpi')),
            'device_Xdpi' => $this->parseFloatSafe($this->request->getPost('device_Xdpi')),
            'device_Ydpi' => $this->parseFloatSafe($this->request->getPost('device_Ydpi')),
            'device_Locale' => (string) $this->request->getPost('device_Locale'),
            'device_Timezone' => (string) $this->request->getPost('device_Timezone'),
            'device_CpuCount' => $this->parseIntSafe($this->request->getPost('device_CpuCount')),
            'device_Abis' => (string) $this->request->getPost('device_Abis'),
            'device_StorageTotal' => $this->parseLongInt($this->request->getPost('device_StorageTotal')),
            'device_StorageAvailable' => $this->parseLongInt($this->request->getPost('device_StorageAvailable')),
            'device_BatteryCapacity' => $this->parseIntSafe($this->request->getPost('device_BatteryCapacity')),
            'device_ip' => $this->request->getIPAddress()
        ];

        try {
            // Match on stable identity fields only (never include the freshly generated UUID)
            $existingDevice = $modUpload->device_find_by_fingerprint(
                $fingerprint,
                $model,
                $brand,
                (string) $this->request->getPost('device_AndroidId')
            );

            if (empty($existingDevice)) {
                if (!$modUpload->device_make_print($deviceData)) {
                    throw new \RuntimeException('Device registration failed');
                }
                $existingDevice = $modUpload->device_find_by_fingerprint($fingerprint, $model, $brand);
                $message = 'New device registered';
                log_message('info', 'New device registered print_id=' . ($existingDevice[0]->device_Uuid ?? ''));
            } else {
                $message = 'Existing device identified';
                log_message('info', 'Existing device print_id=' . $existingDevice[0]->device_Uuid);
                // Refresh the last-seen IP for known devices
                $this->db->table('tbl_Devices')
                    ->where('device_Uuid', $existingDevice[0]->device_Uuid)
                    ->update(['device_ip' => $this->request->getIPAddress()]);
            }

            if (empty($existingDevice)) {
                throw new \RuntimeException('Device registration succeeded but could not re-read print_id');
            }

            \App\Libraries\Audit::log(
                'device_print',
                'auth',
                $message,
                [
                    'device_uuid' => $existingDevice[0]->device_Uuid,
                    'model'       => $model,
                    'brand'       => $brand,
                    'app_version' => $this->request->getHeaderLine('X-App-Version') ?: null,
                ],
                null
            );

            return $this->respond([
                'status' => self::STATUS_SUCCESS,
                'message' => $message,
                'print_id' => $existingDevice[0]->device_Uuid,
                'time' => $dated,
            ]);

        } catch (\Throwable $e) {
            log_message('error', 'Device Print Error: ' . $e->getMessage());
            return $this->respond([
                'status' => self::STATUS_ERROR,
                'time' => $dated,
                'message' => 'Device processing failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get list of uploads for a user/device
     */
    public function upload_listing(): ResponseInterface {
        $modUpload = new UploadModel();
        $dated = date('Y-m-d H:i:s');

        if (!$this->request->is('post')) {
            return $this->fail('Method not allowed', 405);
        }

        try {
            $ownerUuid = (string) $this->request->getPost('varUser');
            $deviceId = (string) $this->request->getPost('varDev');

            $this->auditApiCall('get/my_uploads', $ownerUuid, $deviceId);

            $uploads = $modUpload->file_listing($ownerUuid, $deviceId);
            $result = [];

            foreach ($uploads as $upload) {
                $summary = $modUpload->loot_summary($upload->loot_Uuid);
                
                // Convert loot_Created (BIGINT Unix timestamp) to formatted date string for Android
                $createdRaw = $upload->loot_Created;
                $summaryCreated = is_numeric($createdRaw) 
                    ? date('Y-m-d H:i:s', (int)$createdRaw) 
                    : $createdRaw;
                
                $categoryBreakdown = $modUpload->get_upload_category_breakdown($upload->loot_Uuid);
                $financialSummary = $modUpload->get_upload_financial_summary($upload->loot_Uuid);
                
                // Get LLM classification breakdowns
                $directionBreakdown = $modUpload->get_upload_direction_breakdown($upload->loot_Uuid);
                $transactionTypeBreakdown = $modUpload->get_upload_transaction_type_breakdown($upload->loot_Uuid);
                
                $result[] = [
                    'summary_Loot_Uuid' => $upload->loot_Uuid,
                    'summary_Created' => $summaryCreated,
                    'summary_Count' => $summary[0]->info_All ?? 0,
                    'summary_Received' => $summary[0]->info_Get_from_MPESA ?? 0,
                    'summary_Sent' => $summary[0]->info_Sent_to_MPESA ?? 0,
                    'summary_Unknown' => $summary[0]->info_Unknown ?? 0,
                    'finance_senders' => $financialSummary['counterparties'],
                    'total_amount' => $financialSummary['total_amount'],
                    'category_breakdown' => $categoryBreakdown,
                    'direction_breakdown' => $directionBreakdown,
                    'transaction_type_breakdown' => $transactionTypeBreakdown,
                ];
            }

            return $this->respond([
                'status' => self::STATUS_SUCCESS,
                'time' => $dated,
                'summarizer' => $result
            ]);

        } catch (\Throwable $e) {
            log_message('error', 'Upload Listing Error: ' . $e->getMessage());
            return $this->respond([
                'status' => self::STATUS_ERROR,
                'time' => $dated,
                'message' => 'Failed to retrieve upload list',
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get aggregated financial overview for the Android dashboard
     */
    public function financial_overview(): ResponseInterface {
        $modUpload = new UploadModel();
        $dated = date('Y-m-d H:i:s');

        if (!$this->request->is('post')) {
            return $this->fail('Method not allowed', 405);
        }

        try {
            $ownerUuid = (string) $this->request->getPost('varUser');
            $deviceId = (string) $this->request->getPost('varDev');

            $this->auditApiCall('get/my_financial_overview', $ownerUuid, $deviceId);

            $overview = $modUpload->get_financial_overview($ownerUuid, $deviceId);

            return $this->respond([
                'status' => self::STATUS_SUCCESS,
                'time' => $dated,
                'overview' => $overview
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'Financial Overview Error: ' . $e->getMessage());
            return $this->respond([
                'status' => self::STATUS_ERROR,
                'time' => $dated,
                'message' => 'Failed to retrieve financial overview'
            ]);
        }
    }

    /**
     * Get paginated transactions by financial category
     */
    public function transactions_by_category(): ResponseInterface {
        $modUpload = new UploadModel();
        $dated = date('Y-m-d H:i:s');

        if (!$this->request->is('post')) {
            return $this->fail('Method not allowed', 405);
        }

        try {
            $ownerUuid = (string) $this->request->getPost('varUser');
            $deviceId = (string) $this->request->getPost('varDev');
            $category = (string) $this->request->getPost('varCategory');
            $page = (int) ($this->request->getPost('varPage') ?: 1);
            $perPage = (int) ($this->request->getPost('varPerPage') ?: 50);

            $this->auditApiCall('get/my_transactions_by_category', $ownerUuid, $deviceId);

            $result = $modUpload->get_transactions_by_category($ownerUuid, $deviceId, $category, $page, $perPage);

            return $this->respond([
                'status' => self::STATUS_SUCCESS,
                'time' => $dated,
                'transactions' => $result['transactions'],
                'total' => $result['total'],
                'page' => $result['page'],
                'per_page' => $result['per_page'],
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'Transactions By Category Error: ' . $e->getMessage());
            return $this->respond([
                'status' => self::STATUS_ERROR,
                'time' => $dated,
                'message' => 'Failed to retrieve transactions'
            ]);
        }
    }

    /**
     * Get sender profiles with transaction counts
     */
    public function sender_profiles(): ResponseInterface {
        $modUpload = new UploadModel();
        $dated = date('Y-m-d H:i:s');

        if (!$this->request->is('post')) {
            return $this->fail('Method not allowed', 405);
        }

        try {
            $ownerUuid = (string) $this->request->getPost('varUser');
            $deviceId = (string) $this->request->getPost('varDev');

            $this->auditApiCall('get/my_sender_profiles', $ownerUuid, $deviceId);

            $profiles = $modUpload->get_sender_profiles($ownerUuid, $deviceId);

            return $this->respond([
                'status' => self::STATUS_SUCCESS,
                'time' => $dated,
                'profiles' => $profiles
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'Sender Profiles Error: ' . $e->getMessage());
            return $this->respond([
                'status' => self::STATUS_ERROR,
                'time' => $dated,
                'message' => 'Failed to retrieve sender profiles'
            ]);
        }
    }

    /**
     * Get detailed SMS statistics last 3 uploads
     */
    public function upload_list_graph(){
        $mod_upload = new UploadModel();
        $mod_cryption = new CryptoHelper();
        $mod_user = new AppUserModel();

        $session = session();

        if (empty($session->get('user_name'))){
            #echo "Not Logged In";
        }

        if ($this->request->getPost()){
            $summ_owner_uuid = $this->request->getVar('varUser');
            $summ_device = $this->request->getVar('varDev');
            $summ_owner = $mod_user->user_get_id($summ_owner_uuid);

            $loot_array = array();
            $loot_list = $mod_upload->file_listing($summ_owner_uuid, $summ_device);
            echo '{ "summarizer":[';
            $counter = 0;
            $loot_size = count($loot_list);
            foreach ($loot_list as $loot_info) {
                $counter +=1;
                $loot_meta = $mod_upload->loot_summary($loot_info->loot_Uuid); //tbl_Loot_Summary
                $data = [
                    'summary_Loot_Uuid'  => $loot_info->loot_Uuid,
                    'summary_Created'  => $loot_info->loot_Created,
                    'summary_Count'  => $loot_meta[0]->info_All,
                    'summary_Received'  => $loot_meta[0]->info_Get_from_MPESA,
                    'summary_Sent'  => $loot_meta[0]->info_Sent_to_MPESA,
                    'summary_Unknown' => $loot_meta[0]->info_Unknown
                ];
                array_push($loot_array,$data);

                if ($counter == 4) {
                    print json_encode($data);
                    break; // Exit the loop after the third item
                } else {
                    print json_encode($data).",";
                }
            }
            echo "]}";
        }
    }

    /**
     * Get detailed SMS statistics for a specific upload
     */
    public function upload_summary_calculation(): ResponseInterface {
        $modUpload = new UploadModel();
        $dated = date('Y-m-d H:i:s');

        if (!$this->request->is('post')) {
            return $this->fail('Method not allowed', 405);
        }

        try {
            $lootUuid = (string) $this->request->getPost('varLootUuid');
            $this->auditApiCall('get/my_summary_calculations', '', '', 0, ['loot_uuid' => $lootUuid]);
            $summary = $modUpload->loot_summary($lootUuid);

            if (empty($summary)) {
                return $this->respond([
                    'status' => self::STATUS_ERROR,
                    'time' => $dated,
                    'message' => 'No data found for this upload'
                ]);
            }

            $response_data = ['status' => self::STATUS_SUCCESS,
                'time' => $dated,
                'loot_summarizer' => $this->formatSummaryData($summary[0])];

            $data = json_encode($response_data, JSON_PRETTY_PRINT);

            // Check if the 'loot_summarizer' key exists
            if (isset($data['loot_summarizer'])) {
                // Assign the 'loot_summarizer' subarray to be the new data
                // Encode the transformed array back into a JSON string
                $transformedJson = json_encode($data['loot_summarizer'], JSON_PRETTY_PRINT);

                print_r($transformedJson);
                echo gettype($transformedJson);
            }

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

    /**
     * Count uploads for a user/device
     */
    public function loot_uploaded_count(): ResponseInterface {
        $modUpload = new UploadModel();
        $dated = date('Y-m-d H:i:s');

        if (!$this->request->is('post')) {
            return $this->fail('Method not allowed', 405);
        }

        try {
            $ownerUuid = (string) $this->request->getPost('varUser');
            $deviceId = (string) $this->request->getPost('varDev');
            $this->auditApiCall('get/my_uploads_count', $ownerUuid, $deviceId);
            $uploads = $modUpload->file_listing($ownerUuid, $deviceId);

            return $this->respond([
                'msg_status' => self::STATUS_SUCCESS,
                'msg_count' => count($uploads),
                'msg_time' => $dated,
                'msg_state' => empty($uploads) ? 'empty' : 'found'
            ]);

        } catch (\Throwable $e) {
            log_message('error', 'Upload Count Error: ' . $e->getMessage());
            return $this->respond([
                'msg_status' => self::STATUS_ERROR,
                'msg_time' => $dated,
                'message' => 'Failed to count uploads',
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get aggregated category counts across uploads
     */
    public function loot_uploaded_category_count(): ResponseInterface {
        $modUpload = new UploadModel();
        $dated = date('Y-m-d H:i:s');

        if (!$this->request->is('post')) {
            return $this->fail('Method not allowed', 405);
        }

        try {
            $ownerUuid = (string) $this->request->getPost('varUser');
            $deviceId = (string) $this->request->getPost('varDev');

            $this->auditApiCall('get/my_uploads_category_count', $ownerUuid, $deviceId);

            $uploads = $modUpload->file_listing($ownerUuid, $deviceId);
            $uuids = array_column($uploads, 'loot_Uuid');

            $summaries = $modUpload->get_loot_summary_from_uuids($uuids);
            $result = [];

            foreach ($summaries as $summary) {
                $result[] = $this->formatSummaryData($summary);
            }

            return $this->respond([
                'status' => self::STATUS_SUCCESS,
                'time' => $dated,
                'loot_summarizer' => ['loot_summarizer' => $result]
            ]);

        } catch (\Throwable $e) {
            log_message('error', 'Category Count Error: ' . $e->getMessage());
            return $this->respond([
                'status' => self::STATUS_ERROR,
                'time' => $dated,
                'message' => 'Failed to retrieve category counts',
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * List all SMS in a given category
     */
    public function list_all_sms_in_category(): ResponseInterface {
        $modUpload = new UploadModel();
        $dated = date('Y-m-d H:i:s');

        if (!$this->request->is('post')) {
            return $this->fail('Method not allowed', 405);
        }

        try {
            $ownerUuid = (string) $this->request->getPost('varUser');
            $deviceId = (string) $this->request->getPost('varDev');
            $category = (string) $this->request->getPost('varCategory');
            $page = (int) ($this->request->getPost('varPage') ?: 1);
            $perPage = (int) ($this->request->getPost('varPerPage') ?: 100);

            $this->auditApiCall('get/list_all_sms_in_category', $ownerUuid, $deviceId);

            $result = $modUpload->get_transactions_by_category($ownerUuid, $deviceId, $category, $page, $perPage);

            return $this->respond([
                'status' => self::STATUS_SUCCESS,
                'time' => $dated,
                'transactions' => $result['transactions'],
                'total' => $result['total'],
                'page' => $result['page'],
                'per_page' => $result['per_page'],
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'List SMS By Category Error: ' . $e->getMessage());
            return $this->respond([
                'status' => self::STATUS_ERROR,
                'time' => $dated,
                'message' => 'Failed to list SMS in category'
            ]);
        }
    }

    /**
     * Delete upload by UUID
     */
    public function loot_delete_by_uuid(): ResponseInterface {
        $modUpload = new UploadModel();
        $dated = date('Y-m-d H:i:s');

        if (!$this->request->is('post')) {
            return $this->fail('Method not allowed', 405);
        }

        try {
            $lootUuid = (string) $this->request->getPost('varLootUuid');

            return $this->respond([
                'status' => self::STATUS_SUCCESS,
                'time' => $dated,
                'message' => 'Deletion endpoint not yet implemented'
            ]);

        } catch (\Throwable $e) {
            log_message('error', 'Delete Error: ' . $e->getMessage());
            return $this->respond([
                'status' => self::STATUS_ERROR,
                'time' => $dated,
                'message' => 'Deletion failed',
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Resolve the numeric user for a raw access token (if any) and write an
     * audit row for an Android-facing API call. IP + user-agent are captured
     * automatically by the Audit library from the current request.
     */
    private function auditApiCall(
        string $action,
        string $rawToken = '',
        string $deviceId = '',
        int $smsCount = 0,
        array $extra = []
    ): void {
        $userId = null;
        if ($rawToken !== '') {
            $identity = $this->db->table('auth_identities')
                ->select('user_id')
                ->where('type', AccessTokens::ID_TYPE_ACCESS_TOKEN)
                ->where('secret', hash('sha256', $rawToken))
                ->get()
                ->getRow();
            $userId = $identity->user_id ?? null;
        }

        $metadata = $extra;
        if ($rawToken !== '') {
            $metadata['token_hash'] = hash('sha256', $rawToken);
        }
        if ($deviceId !== '') {
            $metadata['device_uuid'] = $deviceId;
        }
        if ($smsCount > 0) {
            $metadata['sms_count'] = $smsCount;
        }
        if ($session = $this->request->getHeaderLine('X-Sync-Session')) {
            $metadata['sync_session'] = $session;
        }
        if ($version = $this->request->getHeaderLine('X-App-Version')) {
            $metadata['app_version'] = $version;
        }
        if ($retry = $this->request->getHeaderLine('X-Retry-Attempt')) {
            $metadata['retry_attempt'] = $retry;
        }
        if (($offset = $this->deviceClockOffsetMs()) !== null) {
            $metadata['clock_offset_ms'] = $offset;
        }

        \App\Libraries\Audit::log(
            $action,
            'data',
            $smsCount > 0 ? $action . " ($smsCount messages)" : $action,
            $metadata,
            $userId
        );
    }

    /**
     * Server-now minus device-reported epoch ms (positive = server ahead).
     */
    private function deviceClockOffsetMs(): ?int
    {
        $deviceTime = $this->request->getHeaderLine('X-Device-Time');
        if ($deviceTime === '' || !is_numeric($deviceTime)) {
            return null;
        }
        return (int)(round(microtime(true) * 1000) - (float) $deviceTime);
    }

    /**
     * Format summary data consistently — LLM-based breakdowns only
     */
    private function formatSummaryData(object $summary): array {
        return [
            'status' => 1,
            'count_All' => $summary->info_All,
            'count_Unknown' => $summary->info_Unknown,
            'loot_Created' => $summary->loot_Created,
            'loot_Uuid' => $summary->loot_Uuid,
            'direction_breakdown' => json_decode($summary->direction_breakdown ?? '{}', true) ?? [],
            'transaction_type_breakdown' => json_decode($summary->transaction_type_breakdown ?? '{}', true) ?? [],
        ];
    }

    /**
     * Safely parse long integer from input
     */
    private function parseLongInt($value): int {
        $clean = preg_replace('/[^0-9]/', '', (string) $value);
        return is_numeric($clean) ? (int) $clean : time() * 1000;
    }

    /**
     * Safely parse integer from input (0 when missing/invalid)
     */
    private function parseIntSafe($value): int {
        $clean = preg_replace('/[^0-9\-]/', '', (string) $value);
        return is_numeric($clean) ? (int) $clean : 0;
    }

    /**
     * Safely parse float from input (0.0 when missing/invalid)
     */
    private function parseFloatSafe($value): float {
        $clean = (string) $value;
        if (!is_numeric($clean)) {
            $clean = preg_replace('/[^0-9\-.]/', '', $clean);
        }
        return is_numeric($clean) ? (float) $clean : 0.0;
    }
}
