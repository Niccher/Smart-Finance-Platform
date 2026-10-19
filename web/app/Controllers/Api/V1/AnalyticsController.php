<?php

namespace App\Controllers\Api\V1;

use App\Models\UploadModel;
use App\Models\InsightModel;
use CodeIgniter\HTTP\ResponseInterface;

class AnalyticsController extends BaseApiController
{
    public function financialOverview(): ResponseInterface
    {
        $user = $this->requireAuthenticatedUser();
        if (!$user) return $this->failUnauthorized('Invalid or expired API token');

        $modUpload = new UploadModel();
        $dated = date('Y-m-d H:i:s');
        $rawToken = (string) ($this->request->getPost('varUser') ?? $this->request->getGet('varUser') ?? '');
        $devId = (string) ($this->request->getPost('varDev') ?? $this->request->getGet('varDev') ?? '');

        if (!empty($rawToken) && !$this->isTokenOwnedByUser($rawToken, $user)) {
            return $this->failForbidden('Token mismatch for authenticated user');
        }

        $token = $this->resolveTokenForUser($user, $rawToken) ?? $rawToken;

        try {
            $this->auditApiCall('get/my_financial_overview', $token, $devId);
            $overview = $modUpload->get_financial_overview($token, $devId);

            return $this->respond([
                'status' => self::STATUS_SUCCESS,
                'time' => $dated,
                'overview' => $overview
            ]);
        } catch (\Throwable $e) {
            return $this->respond([
                'status' => self::STATUS_ERROR,
                'time' => $dated,
                'message' => 'Failed to retrieve financial overview'
            ]);
        }
    }

    public function transactionsByCategory(): ResponseInterface
    {
        $user = $this->requireAuthenticatedUser();
        if (!$user) return $this->failUnauthorized('Invalid or expired API token');

        $modUpload = new UploadModel();
        $dated = date('Y-m-d H:i:s');
        $rawToken = (string) ($this->request->getPost('varUser') ?? $this->request->getGet('varUser') ?? '');
        $devId = (string) ($this->request->getPost('varDev') ?? $this->request->getGet('varDev') ?? '');
        $category = (string) ($this->request->getPost('varCategory') ?? $this->request->getGet('varCategory') ?? '');
        $page = (int) ($this->request->getPost('varPage') ?? $this->request->getGet('varPage') ?: 1);
        $perPage = (int) ($this->request->getPost('varPerPage') ?? $this->request->getGet('varPerPage') ?: 50);

        if (!empty($rawToken) && !$this->isTokenOwnedByUser($rawToken, $user)) {
            return $this->failForbidden('Token mismatch for authenticated user');
        }

        $token = $this->resolveTokenForUser($user, $rawToken) ?? $rawToken;

        try {
            $this->auditApiCall('get/my_transactions_by_category', $token, $devId);
            $result = $modUpload->get_transactions_by_category($token, $devId, $category, $page, $perPage);

            return $this->respond([
                'status' => self::STATUS_SUCCESS,
                'time' => $dated,
                'transactions' => $result['transactions'],
                'total' => $result['total'],
                'page' => $result['page'],
                'per_page' => $result['per_page'],
            ]);
        } catch (\Throwable $e) {
            return $this->respond([
                'status' => self::STATUS_ERROR,
                'time' => $dated,
                'message' => 'Failed to retrieve transactions'
            ]);
        }
    }

    public function senderProfiles(): ResponseInterface
    {
        $user = $this->requireAuthenticatedUser();
        if (!$user) return $this->failUnauthorized('Invalid or expired API token');

        $modUpload = new UploadModel();
        $dated = date('Y-m-d H:i:s');
        $rawToken = (string) ($this->request->getPost('varUser') ?? $this->request->getGet('varUser') ?? '');
        $devId = (string) ($this->request->getPost('varDev') ?? $this->request->getGet('varDev') ?? '');

        if (!empty($rawToken) && !$this->isTokenOwnedByUser($rawToken, $user)) {
            return $this->failForbidden('Token mismatch for authenticated user');
        }

        $token = $this->resolveTokenForUser($user, $rawToken) ?? $rawToken;

        try {
            $this->auditApiCall('get/my_sender_profiles', $token, $devId);
            $profiles = $modUpload->get_sender_profiles($token, $devId);

            return $this->respond([
                'status' => self::STATUS_SUCCESS,
                'time' => $dated,
                'profiles' => $profiles
            ]);
        } catch (\Throwable $e) {
            return $this->respond([
                'status' => self::STATUS_ERROR,
                'time' => $dated,
                'message' => 'Failed to retrieve sender profiles'
            ]);
        }
    }

    public function financialHealth(): ResponseInterface
    {
        $user = $this->requireAuthenticatedUser();
        if (!$user) return $this->failUnauthorized('Invalid or expired API token');

        $rawToken = (string) ($this->request->getPost('varUser') ?? $this->request->getGet('varUser') ?? '');
        if (!empty($rawToken) && !$this->isTokenOwnedByUser($rawToken, $user)) {
            return $this->failForbidden('Token mismatch for authenticated user');
        }

        $token = $this->resolveTokenForUser($user, $rawToken);
        if (empty($token)) return $this->failUnauthorized('User token required');

        try {
            $insights = new InsightModel();
            $scoreData = $insights->getFinancialHealthScore($token);

            return $this->respond([
                'status' => self::STATUS_SUCCESS,
                'score'  => (int)($scoreData['score'] ?? 50),
                'color'  => $scoreData['color'] ?? 'info',
                'tips'   => $scoreData['tips'] ?? []
            ]);
        } catch (\Throwable $e) {
            return $this->respond([
                'status' => self::STATUS_ERROR,
                'message' => 'Failed to calculate financial health score'
            ]);
        }
    }

    public function financialAlerts(): ResponseInterface
    {
        $user = $this->requireAuthenticatedUser();
        if (!$user) return $this->failUnauthorized('Invalid or expired API token');

        $rawToken = (string) ($this->request->getPost('varUser') ?? $this->request->getGet('varUser') ?? '');
        if (!empty($rawToken) && !$this->isTokenOwnedByUser($rawToken, $user)) {
            return $this->failForbidden('Token mismatch for authenticated user');
        }

        $token = $this->resolveTokenForUser($user, $rawToken);
        if (empty($token)) return $this->failUnauthorized('User token required');

        try {
            $insights = new InsightModel();
            $alerts = $insights->getSmartAlerts($token);

            return $this->respond([
                'status' => self::STATUS_SUCCESS,
                'alerts' => $alerts
            ]);
        } catch (\Throwable $e) {
            return $this->respond([
                'status' => self::STATUS_ERROR,
                'message' => 'Failed to retrieve financial alerts'
            ]);
        }
    }

    public function financialRecurring(): ResponseInterface
    {
        $user = $this->requireAuthenticatedUser();
        if (!$user) return $this->failUnauthorized('Invalid or expired API token');

        $rawToken = (string) ($this->request->getPost('varUser') ?? $this->request->getGet('varUser') ?? '');
        if (!empty($rawToken) && !$this->isTokenOwnedByUser($rawToken, $user)) {
            return $this->failForbidden('Token mismatch for authenticated user');
        }

        $token = $this->resolveTokenForUser($user, $rawToken);
        if (empty($token)) return $this->failUnauthorized('User token required');

        try {
            $insights = new InsightModel();
            $payments = $insights->getRecurringPayments($token);

            return $this->respond([
                'status' => self::STATUS_SUCCESS,
                'payments' => $payments
            ]);
        } catch (\Throwable $e) {
            return $this->respond([
                'status' => self::STATUS_ERROR,
                'message' => 'Failed to retrieve recurring payments'
            ]);
        }
    }

    public function financialTrends(): ResponseInterface
    {
        $user = $this->requireAuthenticatedUser();
        if (!$user) return $this->failUnauthorized('Invalid or expired API token');

        $rawToken = (string) ($this->request->getPost('varUser') ?? $this->request->getGet('varUser') ?? '');
        if (!empty($rawToken) && !$this->isTokenOwnedByUser($rawToken, $user)) {
            return $this->failForbidden('Token mismatch for authenticated user');
        }

        $token = $this->resolveTokenForUser($user, $rawToken);
        if (empty($token)) return $this->failUnauthorized('User token required');

        try {
            $insights = new InsightModel();
            $trends = $insights->getSpendingTrends($token);

            return $this->respond([
                'status' => self::STATUS_SUCCESS,
                'trends' => $trends
            ]);
        } catch (\Throwable $e) {
            return $this->respond([
                'status' => self::STATUS_ERROR,
                'message' => 'Failed to retrieve spending trends'
            ]);
        }
    }

    public function financialInsights(): ResponseInterface
    {
        $user = $this->requireAuthenticatedUser();
        if (!$user) return $this->failUnauthorized('Invalid or expired API token');

        $rawToken = (string) ($this->request->getPost('varUser') ?? $this->request->getGet('varUser') ?? '');
        if (!empty($rawToken) && !$this->isTokenOwnedByUser($rawToken, $user)) {
            return $this->failForbidden('Token mismatch for authenticated user');
        }

        $token = $this->resolveTokenForUser($user, $rawToken);
        if (empty($token)) return $this->failUnauthorized('User token required');

        try {
            $insights = new InsightModel();
            $obs = $insights->getAIObservations($token);

            return $this->respond([
                'status' => self::STATUS_SUCCESS,
                'insights' => $obs
            ]);
        } catch (\Throwable $e) {
            return $this->respond([
                'status' => self::STATUS_ERROR,
                'message' => 'Failed to retrieve financial insights'
            ]);
        }
    }
}
