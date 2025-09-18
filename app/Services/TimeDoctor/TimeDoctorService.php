<?php

namespace App\Services\TimeDoctor;

use App\Http\Controllers\TimeDoctorAuthController;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TimeDoctorService
{
    private $authController;

    private $accessToken;

    private $baseUrl;

    private $companyId = null;

    private const API_VERSION = 'v1.1';

    private const MAX_RETRIES = 3;

    private const DEFAULT_TIMEOUT = 30;

    private const CONNECT_TIMEOUT = 10;

    private const PAGINATION_LIMIT = 250;

    public function __construct(TimeDoctorAuthController $authController)
    {
        $this->authController = $authController;
        $this->baseUrl = 'https://webapi.timedoctor.com/'.self::API_VERSION;
    }

    private function getAccessToken()
    {
        if (! $this->accessToken) {
            $this->accessToken = $this->authController->getAccessToken();

            if (! $this->accessToken) {
                throw new \Exception('Not connected to TimeDoctor or token expired');
            }
        }

        return $this->accessToken;
    }

    private function get(string $endpoint, array $params = [], int $maxRetries = self::MAX_RETRIES)
    {
        $token = $this->getAccessToken();
        $retries = 0;
        $lastException = null;
        $tokenRefreshed = false;

        while ($retries < $maxRetries) {
            try {
                $response = Http::withToken($token)
                    ->timeout(self::DEFAULT_TIMEOUT)
                    ->connectTimeout(self::CONNECT_TIMEOUT)
                    ->retry(3, 100)
                    ->get($this->baseUrl.$endpoint, $params);

                if (! $response->successful()) {
                    if ($response->status() === 429) {
                        $retryAfter = $response->header('Retry-After', 5);
                        Log::warning('TimeDoctor API rate limit hit, waiting before retry', [
                            'endpoint' => $endpoint,
                            'retry_after' => $retryAfter,
                            'attempt' => $retries + 1,
                        ]);
                        sleep($retryAfter);
                        $retries++;

                        continue;
                    }

                    // Handle 401 authentication errors - attempt token refresh
                    if ($response->status() === 401 && ! $tokenRefreshed) {
                        $responseBody = $response->json();

                        // Check if it's an expired token error
                        if (isset($responseBody['error']) && $responseBody['error'] === 'invalid_grant') {
                            Log::info('TimeDoctor access token expired, attempting refresh', [
                                'endpoint' => $endpoint,
                                'attempt' => $retries + 1,
                            ]);

                            try {
                                // Attempt to refresh the token
                                $refreshResult = $this->authController->refreshToken();

                                if ($refreshResult && is_object($refreshResult) && method_exists($refreshResult, 'getData')) {
                                    $refreshData = $refreshResult->getData(true);

                                    if (isset($refreshData['success']) && $refreshData['success']) {
                                        // Token refresh successful, get the new token and retry
                                        $this->accessToken = null; // Clear cached token
                                        $token = $this->getAccessToken();
                                        $tokenRefreshed = true;

                                        Log::info('TimeDoctor token refreshed successfully, retrying request', [
                                            'endpoint' => $endpoint,
                                        ]);

                                        continue; // Retry with new token without incrementing retries
                                    }
                                }

                                Log::error('TimeDoctor token refresh failed', [
                                    'endpoint' => $endpoint,
                                    'refresh_result' => $refreshResult,
                                ]);

                            } catch (\Exception $refreshException) {
                                Log::error('Exception during TimeDoctor token refresh', [
                                    'endpoint' => $endpoint,
                                    'error' => $refreshException->getMessage(),
                                ]);
                            }
                        }
                    }

                    Log::error('TimeDoctor API error', [
                        'endpoint' => $endpoint,
                        'params' => $params,
                        'status' => $response->status(),
                        'response' => $response->json(),
                    ]);

                    throw new \Exception('TimeDoctor API error: '.$response->status().":\n".$response->body());
                }

                return $response->json();

            } catch (\Exception $e) {
                $lastException = $e;
                $retries++;

                if ($retries < $maxRetries) {
                    $backoff = pow(2, $retries);
                    Log::warning("TimeDoctor API request failed, retrying in {$backoff} seconds", [
                        'endpoint' => $endpoint,
                        'attempt' => $retries,
                        'error' => $e->getMessage(),
                    ]);
                    sleep($backoff);
                }
            }
        }

        throw $lastException ?? new \Exception("Failed to connect to TimeDoctor API after {$maxRetries} attempts");
    }

    public function getCompanyInfo()
    {
        try {
            $response = $this->get('/companies');

            return $response;
        } catch (\Exception $e) {
            Log::error('TimeDoctor API error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    public function getCompanyId()
    {
        try {
            $companyInfo = $this->getCompanyInfo();

            if (isset($companyInfo['accounts'][0]['company_id'])) {
                return $companyInfo['accounts'][0]['company_id'];
            }

            return null;
        } catch (\Exception $e) {
            Log::error('Error getting company ID from TimeDoctor service', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
        // if ($this->companyId === null) {
        //     try {
        //         $companyInfo = $this->getCompanyInfo();

        //         if (is_array($companyInfo) && isset($companyInfo['accounts']) && ! empty($companyInfo['accounts'])) {
        //             $this->companyId = $companyInfo['accounts'][0]['company_id'] ?? null;
        //         } else {
        //             Log::error('Invalid company info format', ['info' => $companyInfo]);
        //             throw new \Exception('Invalid company info format or empty accounts array');
        //         }

        //         if (! $this->companyId) {
        //             throw new \Exception('No company ID found in TimeDoctor account');
        //         }
        //     } catch (\Exception $e) {
        //         Log::error('Failed to get TimeDoctor company info', [
        //             'error' => $e->getMessage(),
        //             'trace' => $e->getTraceAsString(),
        //         ]);
        //         throw $e;
        //     }
        // }

        // return $this->companyId;
    }

    public function getUsers($companyId)
    {
        return $this->get("/companies/{$companyId}/users");
    }

    public function getProjects()
    {
        return $this->get('/companies/projects', ['all' => 1]);
    }

    public function getTasks($companyId, $userId)
    {
        return $this->get("/companies/{$companyId}/users/{$userId}/tasks", ['status' => 'active']);
    }

    public function getUserWorklogs($companyId, $userId, Carbon $startDate, Carbon $endDate, $offset = 1, $limit = self::PAGINATION_LIMIT)
    {
        $formattedStartDate = $startDate->format('Y-m-d');
        $formattedEndDate = $endDate->format('Y-m-d');

        // Log::debug("getUserWorklogs request params", [
        //     'company_id' => $companyId,
        //     'user_id'    => $userId,
        //     'start_date' => $formattedStartDate,
        //     'end_date'   => $formattedEndDate,
        //     'offset'     => $offset,
        //     'limit'      => $limit,
        // ]);

        return $this->get("/companies/{$companyId}/worklogs", [
            'start_date' => $formattedStartDate,
            'end_date' => $formattedEndDate,
            'offset' => $offset,
            'limit' => $limit,
            'user_ids' => $userId,
            'consolidated' => 0,
            'breaks_only' => 0,
        ]);
    }

    public function getCompanyWorklogs($companyId, Carbon $startDate, Carbon $endDate, $offset = 1, $limit = self::PAGINATION_LIMIT)
    {
        $formattedStartDate = $startDate->format('Y-m-d');
        $formattedEndDate = $endDate->format('Y-m-d');

        return $this->get("/companies/{$companyId}/worklogs", [
            'start_date' => $formattedStartDate,
            'end_date' => $formattedEndDate,
            'offset' => $offset,
            'limit' => $limit,
            'consolidated' => 0,
            'breaks_only' => 0,
        ]);
    }

    public function getAllWorklogsForDateRange($companyId, Carbon $startDate, Carbon $endDate, ?callable $batchProcessor = null)
    {
        $offset = 1;
        $limit = self::PAGINATION_LIMIT;
        $allItems = [];
        $totalProcessed = 0;
        $totalAvailable = null;

        do {
            Log::info('Fetching worklogs batch', [
                'company_id' => $companyId,
                'offset' => $offset,
                'limit' => $limit,
                'date_range' => $startDate->format('Y-m-d').' to '.$endDate->format('Y-m-d'),
            ]);

            $response = $this->getCompanyWorklogs($companyId, $startDate, $endDate, $offset, $limit);

            Log::debug('TimeDoctor worklogs API response', [
                'response_keys' => is_array($response) ? array_keys($response) : 'Response is not an array',
                'raw_response' => $response,
            ]);

            if (empty($response) || empty($response['worklogs']) || (is_array($response['worklogs']) && empty($response['worklogs']))) {
                break;
            }

            if (isset($response['worklogs']['items'])) {
                $items = $response['worklogs']['items'];
            } elseif (is_array($response['worklogs'])) {
                $items = $response['worklogs'];
            } else {
                Log::warning('Unexpected worklogs response format', ['response' => $response]);
                break;
            }

            $currentBatchCount = count($items);
            $totalProcessed += $currentBatchCount;

            if ($offset === 1 && isset($response['total'])) {
                $totalAvailable = (int) $response['total'];
                Log::info("Total worklogs available: {$totalAvailable}");
            }

            if ($batchProcessor && is_callable($batchProcessor)) {
                $batchProcessor($items, $offset);
            } else {
                $allItems = array_merge($allItems, $items);
            }

            Log::info('Processed worklog batch', [
                'offset' => $offset,
                'batch_size' => $currentBatchCount,
                'total_processed' => $totalProcessed,
            ]);

            if ($currentBatchCount < $limit) {
                break;
            }

            $offset += $limit;
            usleep(200000);

        } while (true);

        return [
            'total' => $totalAvailable,
            'processed' => $totalProcessed,
            'items' => $allItems,
        ];
    }

    public function getAllWorklogsForDate(Carbon $date, ?callable $progressCallback = null)
    {
        try {
            $companyId = $this->getCompanyId();
            $results = [];

            $usersData = $this->getUsers($companyId);

            if (! isset($usersData['users']) || ! is_array($usersData['users'])) {
                throw new \Exception('Invalid user data received from TimeDoctor');
            }

            $users = $usersData['users'];
            $totalUsers = count($users);
            $processedUsers = 0;

            foreach ($users as $user) {
                $userId = $user['user_id'];
                $userName = $user['full_name'];

                if ($progressCallback) {
                    $progressCallback("Processing user {$userName} ({$processedUsers}/{$totalUsers})", round(($processedUsers / $totalUsers) * 100));
                }

                $userWorklogs = $this->getAllWorklogsForUser(
                    $companyId,
                    $userId,
                    $date->copy()->startOfDay(),
                    $date->copy()->endOfDay()
                );

                if (! empty($userWorklogs['items'])) {
                    $results[$userId] = $userWorklogs['items'];

                    if ($progressCallback) {
                        $progressCallback("Found {$userWorklogs['processed']} worklogs for user {$userName}", round(($processedUsers / $totalUsers) * 100));
                    }
                }

                $processedUsers++;
            }

            return $results;

        } catch (\Exception $e) {
            Log::error('Error getting all worklogs for date', [
                'date' => $date->format('Y-m-d'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    private function getAllWorklogsForUser($companyId, $userId, Carbon $startDate, Carbon $endDate)
    {
        $offset = 1;
        $limit = self::PAGINATION_LIMIT;
        $allItems = [];
        $totalProcessed = 0;

        do {
            $response = $this->getUserWorklogs($companyId, $userId, $startDate, $endDate, $offset, $limit);

            Log::debug('TimeDoctor user worklogs API response', [
                'user_id' => $userId,
                'response_keys' => is_array($response) ? array_keys($response) : 'Response is not an array',
                'worklogs_exists' => isset($response['worklogs']),
                'worklogs_type' => isset($response['worklogs']) ? gettype($response['worklogs']) : 'not set',
            ]);

            if (empty($response) || empty($response['worklogs'])) {
                break;
            }

            if (isset($response['worklogs']['items'])) {
                $items = $response['worklogs']['items'];
            } elseif (is_array($response['worklogs'])) {
                $items = $response['worklogs'];
            } else {
                Log::warning('Unexpected user worklogs response format', ['response' => $response]);
                break;
            }

            $currentBatchCount = count($items);

            if ($currentBatchCount === 0) {
                break;
            }

            $allItems = array_merge($allItems, $items);
            $totalProcessed += $currentBatchCount;

            if ($currentBatchCount < $limit) {
                break;
            }

            $offset += $limit;

        } while (true);

        return [
            'processed' => $totalProcessed,
            'items' => $allItems,
        ];
    }
}
