<?php

namespace App\Controllers\Api\V1;

use App\Controllers\BaseController;
use CodeIgniter\API\ResponseTrait;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

class BaseApiController extends BaseController
{
    use ResponseTrait;

    protected const STATUS_SUCCESS = 1;
    protected const STATUS_ERROR = 0;

    /**
     * Initializer for API Controllers. Enforces standard JSON Content-Type and Cache-Control headers.
     */
    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);

        $this->response->setHeader('Content-Type', 'application/json; charset=UTF-8');
        $this->response->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate');
        $this->response->setHeader('Pragma', 'no-cache');
    }

    /**
     * Extracts raw token string from request (POST, GET, Headers)
     */
    protected function getRawToken(): ?string
    {
        $tokenStr = $this->request->getPost('varToken') 
            ?? $this->request->getPost('varUser') 
            ?? $this->request->getGet('varToken') 
            ?? $this->request->getGet('token') 
            ?? $this->request->getGet('varUser') 
            ?? $this->request->getHeaderLine('X-API-Token');
            
        if (empty($tokenStr)) {
            // Also check Authorization header
            $authHeader = $this->request->getHeaderLine('Authorization');
            if (!empty($authHeader) && preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
                $tokenStr = $matches[1];
            }
        }

        return !empty($tokenStr) ? $tokenStr : null;
    }

    /**
     * Resolves an effective user token string from explicit parameter, request token, or user device link.
     */
    protected function resolveTokenForUser(User $user, ?string $token = null): ?string
    {
        if (!empty($token) && $this->isTokenOwnedByUser($token, $user)) {
            return $token;
        }

        $rawToken = $this->getRawToken();
        if (!empty($rawToken) && $this->isTokenOwnedByUser($rawToken, $user)) {
            return $rawToken;
        }

        $deviceModel = new \App\Models\DeviceModel();
        $ownedTokens = $deviceModel->getUserRawTokens((int)$user->id);
        return !empty($ownedTokens) ? $ownedTokens[0] : null;
    }

    /**
     * Helper to authenticate user using authorization token
     */
    protected function getUserFromToken(): ?User
    {
        $tokenStr = $this->getRawToken();
        if (empty($tokenStr)) return null;

        $hashed = hash('sha256', $tokenStr);
        $db = \Config\Database::connect();
        $identity = $db->table('auth_identities')
            ->where('type', \CodeIgniter\Shield\Authentication\Authenticators\AccessTokens::ID_TYPE_ACCESS_TOKEN)
            ->where('secret', $hashed)
            ->get()
            ->getRow();

        if (!$identity) return null;

        \App\Libraries\Audit::log(
            'api_call',
            'api',
            'API call: ' . $this->request->getUri()->getPath(),
            [
                'token_hash'  => $hashed,
                'endpoint'    => $this->request->getUri()->getPath(),
                'method'      => $this->request->getMethod(),
                'app_version' => $this->request->getHeaderLine('X-App-Version') ?: null,
                'device_time' => $this->request->getHeaderLine('X-Device-Time') ?: null,
            ],
            (int) $identity->user_id
        );

        return model(UserModel::class)->findById($identity->user_id);
    }

    /**
     * Mandatory authentication helper that requires a valid active user.
     */
    protected function requireAuthenticatedUser(): ?User
    {
        return $this->getUserFromToken();
    }

    /**
     * Verify if a raw token belongs to the given authenticated user.
     */
    protected function isTokenOwnedByUser(string $rawToken, User $user): bool
    {
        if (empty($rawToken)) {
            return false;
        }

        // 1. Direct Shield auth_identities check (matches hash of raw token to user identity)
        $hashed = hash('sha256', $rawToken);
        $db = \Config\Database::connect();
        $identity = $db->table('auth_identities')
            ->where('type', \CodeIgniter\Shield\Authentication\Authenticators\AccessTokens::ID_TYPE_ACCESS_TOKEN)
            ->where('secret', $hashed)
            ->where('user_id', $user->id)
            ->get()
            ->getRow();

        if ($identity) {
            return true;
        }

        // 2. Fallback to linked devices & historical upload ownership in DeviceModel
        $deviceModel = new \App\Models\DeviceModel();
        $ownedTokens = $deviceModel->getUserRawTokens((int)$user->id);
        return in_array($rawToken, $ownedTokens, true);
    }

    /**
     * Audit logger helper
     */
    protected function auditApiCall(string $endpoint, string $token, string $deviceUuid, int $processed = 0, array $extra = [])
    {
        $user = $this->getUserFromToken();
        $userId = $user ? (int)$user->id : null;
        
        \App\Libraries\Audit::log(
            'api_call',
            'data',
            "Mobile API: $endpoint",
            array_merge([
                'token_hash'  => hash('sha256', $token),
                'device_uuid' => $deviceUuid,
                'processed'   => $processed,
            ], $extra),
            $userId
        );
    }

    protected function parseIntSafe($val): int
    {
        return is_numeric($val) ? (int)$val : 0;
    }

    protected function parseFloatSafe($val): float
    {
        return is_numeric($val) ? (float)$val : 0.0;
    }

    protected function parseLongInt($val): int
    {
        return is_numeric($val) ? (int)$val : 0;
    }
}
