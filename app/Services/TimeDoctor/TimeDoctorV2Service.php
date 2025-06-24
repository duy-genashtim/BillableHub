<?php
namespace App\Services\TimeDoctor;

use App\Http\Controllers\TimeDoctorV2AuthController;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TimeDoctorV2Service
{
    private $authController;
    private $accessToken;
    private $baseUrl;
    private $companyId;

    private const MAX_RETRIES     = 3;
    private const DEFAULT_TIMEOUT = 30;
    private const CONNECT_TIMEOUT = 10;

    public function __construct(TimeDoctorV2AuthController $authController)
    {
        $this->authController = $authController;
        $this->baseUrl        = config('services.timedoctor_v2.base_url');
        $this->companyId      = config('services.timedoctor_v2.company_id');
    }

    private function getAccessToken()
    {
        if (! $this->accessToken) {
            $this->accessToken = $this->authController->getAccessToken();

            if (! $this->accessToken) {
                throw new \Exception('Not connected to TimeDoctor V2 or token expired');
            }
        }

        return $this->accessToken;
    }

    private function makeRequest(string $method, string $endpoint, array $params = [], int $maxRetries = self::MAX_RETRIES)
    {
        $token         = $this->getAccessToken();
        $retries       = 0;
        $lastException = null;

        while ($retries < $maxRetries) {
            try {
                $url         = $this->baseUrl . $endpoint;
                $queryParams = array_merge($params, [
                    'token'   => $token,
                    'company' => $this->companyId,
                ]);

                Log::debug("Making TimeDoctor V2 API request", [
                    'method'       => $method,
                    'url'          => $url,
                    'query_params' => array_merge($queryParams, ['token' => 'HIDDEN']), // Hide token in logs
                ]);

                $response = Http::timeout(self::DEFAULT_TIMEOUT)
                    ->connectTimeout(self::CONNECT_TIMEOUT)
                    ->retry(3, 100);

                if (strtoupper($method) === 'GET') {
                    $response = $response->get($url, $queryParams);
                } else {
                    $response = $response->post($url, $queryParams);
                }

                if (! $response->successful()) {
                    if ($response->status() === 429) {
                        $retryAfter = $response->header('Retry-After', 5);
                        Log::warning('TimeDoctor V2 API rate limit hit, waiting before retry', [
                            'endpoint'    => $endpoint,
                            'retry_after' => $retryAfter,
                            'attempt'     => $retries + 1,
                        ]);
                        sleep($retryAfter);
                        $retries++;
                        continue;
                    }

                    Log::error('TimeDoctor V2 API error', [
                        'endpoint'      => $endpoint,
                        'method'        => $method,
                        'status'        => $response->status(),
                        'response_body' => $response->body(),
                        'url'           => $url,
                    ]);

                    throw new \Exception('TimeDoctor V2 API error: ' . $response->status() . ":\n" . $response->body());
                }

                $responseData = $response->json();

                Log::debug("TimeDoctor V2 API response received", [
                    'endpoint'      => $endpoint,
                    'has_data'      => isset($responseData['data']),
                    'data_type'     => isset($responseData['data']) ? gettype($responseData['data']) : 'no data key',
                    'response_keys' => is_array($responseData) ? array_keys($responseData) : 'not array',
                ]);

                return $responseData;

            } catch (\Exception $e) {
                $lastException = $e;
                $retries++;

                if ($retries < $maxRetries) {
                    $backoff = pow(2, $retries);
                    Log::warning("TimeDoctor V2 API request failed, retrying in {$backoff} seconds", [
                        'endpoint' => $endpoint,
                        'attempt'  => $retries,
                        'error'    => $e->getMessage(),
                    ]);
                    sleep($backoff);
                }
            }
        }

        throw $lastException ?? new \Exception("Failed to connect to TimeDoctor V2 API after {$maxRetries} attempts");
    }

    public function getCompanyInfo()
    {
        try {
            $response = $this->makeRequest('GET', "/companies/{$this->companyId}");
            return $response;
        } catch (\Exception $e) {
            Log::error('TimeDoctor V2 getCompanyInfo error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    public function getUsers()
    {
        return $this->makeRequest('GET', "/users");
    }

    public function getProjects()
    {
        return $this->makeRequest('GET', "/projects", [
            'all'              => 'true',
            'show-integration' => 'true',
            'allow-unassigned' => 'true',
            'detail'           => 'basic',
        ]);
    }

    public function getTasks()
    {
        return $this->makeRequest('GET', "/tasks");
    }

    public function getUserWorklogs($userId, Carbon $startDate, Carbon $endDate)
    {
        // Convert dates to TimeDoctor format with proper timezone handling
        $dateRange = getTimeDoctorDateRange(
            $startDate->format('Y-m-d'),
            $endDate->format('Y-m-d'),
            config('app.timezone', 'Asia/Singapore')
        );

        Log::debug("getUserWorklogs V2 request params", [
            'company_id'  => $this->companyId,
            'user_id'     => $userId,
            'from'        => $dateRange['from'],
            'to'          => $dateRange['to'],
            'local_start' => $startDate->format('Y-m-d H:i:s'),
            'local_end'   => $endDate->format('Y-m-d H:i:s'),
        ]);

        return $this->makeRequest('GET', "/activity/worklog", [
            'user'               => $userId,
            'from'               => $dateRange['from'],
            'to'                 => $dateRange['to'],
            'detail'             => 'true',
            'task-project-names' => 'true',
            'category-details'   => 'true',
        ]);
    }

    public function getCompanyWorklogs(Carbon $startDate, Carbon $endDate)
    {
        // Convert dates to TimeDoctor format with proper timezone handling
        $dateRange = getTimeDoctorDateRange(
            $startDate->format('Y-m-d'),
            $endDate->format('Y-m-d'),
            config('app.timezone', 'Asia/Singapore')
        );

        return $this->makeRequest('GET', "/activity/worklog", [
            'from'               => $dateRange['from'],
            'to'                 => $dateRange['to'],
            'detail'             => 'true',
            'task-project-names' => 'true',
            'category-details'   => 'true',
        ]);
    }

    public function getAllWorklogsForDateRange(Carbon $startDate, Carbon $endDate, callable $batchProcessor = null)
    {
        try {
            $response = $this->getCompanyWorklogs($startDate, $endDate);

            Log::debug("TimeDoctor V2 getAllWorklogsForDateRange response", [
                'has_data'      => isset($response['data']),
                'data_count'    => isset($response['data']) ? count($response['data']) : 0,
                'response_keys' => is_array($response) ? array_keys($response) : 'not array',
            ]);

            if (empty($response) || ! isset($response['data']) || empty($response['data'])) {
                return [
                    'total'     => 0,
                    'processed' => 0,
                    'items'     => [],
                ];
            }

            $items = [];
            // TimeDoctor V2 returns nested arrays by day
            foreach ($response['data'] as $dayData) {
                if (is_array($dayData)) {
                    $items = array_merge($items, $dayData);
                }
            }

            $totalProcessed = count($items);

            if ($batchProcessor && is_callable($batchProcessor)) {
                $batchProcessor($items, 1);
            }

            Log::info("Processed V2 worklog batch", [
                'total_processed' => $totalProcessed,
            ]);

            return [
                'total'     => $totalProcessed,
                'processed' => $totalProcessed,
                'items'     => $items,
            ];

        } catch (\Exception $e) {
            Log::error('Error fetching V2 worklogs for date range', [
                'start_date' => $startDate->format('Y-m-d'),
                'end_date'   => $endDate->format('Y-m-d'),
                'error'      => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function getAllUsersWithPagination()
    {
        $response = $this->getUsers();

        if (empty($response) || ! isset($response['data']) || empty($response['data'])) {
            return [];
        }

        return $response['data'];
    }

    public function getAllProjectsWithPagination()
    {
        $response = $this->getProjects();

        if (empty($response) || ! isset($response['data']) || empty($response['data'])) {
            return [];
        }

        return $response['data'];
    }

    public function getAllTasksWithPagination()
    {
        $response = $this->getTasks();

        if (empty($response) || ! isset($response['data']) || empty($response['data'])) {
            return [];
        }

        return $response['data'];
    }
}