<?php

namespace App\Controllers\Api\V1;

use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;
use App\Models\UploadModel;
use CodeIgniter\HTTP\ResponseInterface;

class AuthController extends BaseApiController
{
    public function login(): ResponseInterface
    {
        $json = $this->request->getJSON(true) ?? [];
        $credentials = [
            'email' => $this->request->getPost('email') ?? ($json['email'] ?? null),
            'password' => $this->request->getPost('password') ?? ($json['password'] ?? null),
        ];

        if (auth()->loggedIn()){
            auth()->logout();
        }

        $login_attempt = auth()->attempt($credentials);

        if (!$login_attempt->isOK()){
            return $this->respond([
                "status" => "Invalid",
                "message" => $login_attempt->reason()
            ]);
        }

        $userModel = new UserModel();
        $userinfo = $userModel->find(auth()->id());

        $token = $userinfo->generateAccessToken("MobileAppToken");
        $user_auth_token = $token->raw_token;

        $modUploads = new UploadModel();
        $modUploads->linkDevice((int)$userinfo->id, $user_auth_token, 'Android App Device');

        return $this->respond([
            "status" => "Valid",
            "token" => $user_auth_token
        ]);
    }

    public function register(): ResponseInterface
    {
        $users = new UserModel();

        $json = $this->request->getJSON(true) ?? [];
        $username = $this->request->getPost('username') ?? ($json['username'] ?? null);
        $email = $this->request->getPost('email') ?? ($json['email'] ?? null);
        $password = $this->request->getPost('password') ?? ($json['password'] ?? null);

        $newUser = new User([
            'username' => $username,
            'email' => $email,
            'password' => $password
        ]);

        try {
            $register_attempt = $users->save($newUser);

            if (!$register_attempt){
                return $this->respond([
                    "status" => "Invalid", 
                    "error" => "Unexpected error occurred during registration."
                ]);
            }

            $newUser = $users->findById($users->getInsertID());
            $users->addToDefaultGroup($newUser);

            if (\App\Libraries\Notifier::isTriggerEnabled('signup')) {
                \App\Libraries\Notifier::sendTrigger($newUser->getEmail(), 'signup');
            }

            return $this->respond([
                "status" => "Valid", 
                "message" => "New User added."
            ]);
        } catch (\Throwable $ex) {
            return $this->respond([
                "status" => "Invalid", 
                "error" => "Unexpected error: " . $ex->getMessage()
            ]);
        }
    }

    public function verifyToken(): ResponseInterface
    {
        $json = $this->request->getJSON(true) ?? [];
        $tokenStr = $this->request->getPost('token') 
            ?? ($json['token'] ?? null) 
            ?? $this->getRawToken();

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

                return $this->respond([
                    "status" => "1",
                    "message" => "Token Valid",
                    "time" => date('Y-m-d H:i:s'),
                    "userid" => strval($user->id),
                    "uuid" => "device_paired",
                    "user_name" => $user->username,
                    "user_email" => $user->email ?? "null"
                ]);
            }
        }
        
        return $this->respond(["status" => "0", "message" => "Invalid Token"]);
    }

    public function devicePrint(): ResponseInterface
    {
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
            $existingDevice = $modUpload->device_find_by_fingerprint(
                $fingerprint,
                $model,
                $brand,
                (string) $this->request->getPost('device_AndroidId')
            );

            $db = \Config\Database::connect();
            if (empty($existingDevice)) {
                if (!$modUpload->device_make_print($deviceData)) {
                    throw new \RuntimeException('Device registration failed');
                }
                $existingDevice = $modUpload->device_find_by_fingerprint($fingerprint, $model, $brand);
                $message = 'New device registered';
            } else {
                $message = 'Existing device identified';
                $db->table('tbl_Devices')
                    ->where('device_Uuid', $existingDevice[0]->device_Uuid)
                    ->update(['device_ip' => $this->request->getIPAddress()]);
            }

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
}
