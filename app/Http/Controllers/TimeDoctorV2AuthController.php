<?php

namespace App\Http\Controllers;

use App\Models\TimeDoctorToken;
use App\Services\ActivityLogService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TimeDoctorV2AuthController extends Controller
{
    private const API_VERSION = '2';

    private const LOGIN_URL = 'https://api2.timedoctor.com/api/1.0/login';

    public function authenticate()
    {
        try {
            $username = config('services.timedoctor_v2.username');
            $password = config('services.timedoctor_v2.password');
            $companyId = config('services.timedoctor_v2.company_id');

            if (! $username || ! $password || ! $companyId) {
                return response()->json([
                    'success' => false,
                    'message' => 'TimeDoctor V2 credentials not configured. Please check your .env file.',
                ], 400);
            }

            $response = Http::timeout(30)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post(self::LOGIN_URL, [
                    'email' => $username,
                    'password' => $password,
                    'permissions' => 'read',
                ]);

            if (! $response->successful()) {
                Log::error('Failed to authenticate with TimeDoctor V2', [
                    'status' => $response->status(),
                    'response' => $response->json(),
                ]);

                ActivityLogService::log('timedoctor_v2_auth_error', 'Failed to authenticate with TimeDoctor V2', [
                    'module' => 'timedoctor_v2_integration',
                    'status' => $response->status(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Authentication failed: Invalid credentials',
                ], 400);
            }

            $responseData = $response->json();

            if (! isset($responseData['data']['token'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid response from TimeDoctor V2 API',
                ], 400);
            }

            $tokenData = $responseData['data'];
            $expiresAt = isset($tokenData['expires'])
            ? Carbon::parse($tokenData['expires'])
            : Carbon::now()->addMonths(6);

            TimeDoctorToken::updateOrCreate(
                ['version' => self::API_VERSION],
                [
                    'access_token' => $tokenData['token'],
                    'refresh_token' => $tokenData['token'], // V2 uses same token
                    'expires_at' => $expiresAt,
                ]
            );

            // Test the token with company info
            $companyResponse = $this->testTokenWithCompanyInfo($tokenData['token'], $companyId);

            ActivityLogService::log('timedoctor_v2_auth_success', 'Successfully connected to TimeDoctor V2', [
                'module' => 'timedoctor_v2_integration',
                'version' => self::API_VERSION,
                'expires_at' => $expiresAt->toISOString(),
                'company_id' => $companyId,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Successfully connected to TimeDoctor V2',
                'expires_at' => $expiresAt->toISOString(),
                'company_info' => $companyResponse,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to process TimeDoctor V2 authentication', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            ActivityLogService::log('timedoctor_v2_auth_callback_error', 'Failed to process TimeDoctor V2 authentication', [
                'module' => 'timedoctor_v2_integration',
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to process TimeDoctor V2 authentication: '.$e->getMessage(),
            ], 500);
        }
    }

    private function testTokenWithCompanyInfo($token, $companyId)
    {
        try {
            $response = Http::timeout(10)
                ->get("https://api2.timedoctor.com/api/1.0/companies/{$companyId}", [
                    'token' => $token,
                ]);

            if ($response->successful()) {
                return $response->json();
            }

            Log::warning('Token test failed but continuing', [
                'status' => $response->status(),
                'response' => $response->json(),
            ]);

            return null;
        } catch (\Exception $e) {
            Log::warning('Exception during token test', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function checkToken()
    {
        $token = TimeDoctorToken::where('version', self::API_VERSION)->first();

        if (! $token) {
            return response()->json([
                'connected' => false,
                'message' => 'No TimeDoctor V2 connection found',
            ]);
        }

        if (Carbon::parse($token->expires_at)->isPast()) {
            return response()->json([
                'connected' => false,
                'message' => 'TimeDoctor V2 token expired',
                'expired_at' => $token->expires_at,
            ]);
        }

        // Test token by calling company info
        try {
            $companyId = config('services.timedoctor_v2.company_id');
            $response = Http::timeout(10)
                ->get("https://api2.timedoctor.com/api/1.0/companies/{$companyId}", [
                    'token' => $token->access_token,
                ]);

            if (! $response->successful()) {
                return response()->json([
                    'connected' => false,
                    'message' => 'TimeDoctor V2 token is invalid',
                ]);
            }

            return response()->json([
                'connected' => true,
                'message' => 'Connected to TimeDoctor V2',
                'expires_at' => $token->expires_at,
                'company_info' => $response->json(),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to validate TimeDoctor V2 token', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'connected' => false,
                'message' => 'Failed to validate TimeDoctor V2 token',
            ]);
        }
    }

    public function refreshToken()
    {
        $token = TimeDoctorToken::where('version', self::API_VERSION)->first();

        if (! $token) {
            return response()->json([
                'success' => false,
                'message' => 'No TimeDoctor V2 token found',
            ], 404);
        }

        try {
            // For V2, we need to re-authenticate to get a new token
            $username = config('services.timedoctor_v2.username');
            $password = config('services.timedoctor_v2.password');
            $companyId = config('services.timedoctor_v2.company_id');

            $response = Http::timeout(30)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post(self::LOGIN_URL, [
                    'email' => $username,
                    'password' => $password,
                    'permissions' => 'read',
                ]);

            if (! $response->successful()) {
                Log::error('Failed to refresh TimeDoctor V2 token', [
                    'status' => $response->status(),
                    'response' => $response->json(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Failed to refresh TimeDoctor V2 token',
                ], 400);
            }

            $responseData = $response->json();

            if (! isset($responseData['data']['token'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid response from TimeDoctor V2 API',
                ], 400);
            }

            $tokenData = $responseData['data'];
            $expiresAt = isset($tokenData['expires'])
            ? Carbon::parse($tokenData['expires'])
            : Carbon::now()->addMonths(6);

            $token->update([
                'access_token' => $tokenData['token'],
                'refresh_token' => $tokenData['token'],
                'expires_at' => $expiresAt,
            ]);

            ActivityLogService::log('timedoctor_v2_token_refresh', 'TimeDoctor V2 token refreshed successfully', [
                'module' => 'timedoctor_v2_integration',
                'version' => self::API_VERSION,
                'expires_at' => $expiresAt->toISOString(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'TimeDoctor V2 token refreshed successfully',
                'expires_at' => $expiresAt->toISOString(),
            ]);

        } catch (\Exception $e) {
            Log::error('Exception during TimeDoctor V2 token refresh', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Exception during token refresh: '.$e->getMessage(),
            ], 500);
        }
    }

    public function getAccessToken()
    {
        $token = TimeDoctorToken::where('version', self::API_VERSION)->first();

        if (! $token) {
            return null;
        }

        if (Carbon::parse($token->expires_at)->isPast()) {
            return null;
        }

        return $token->access_token;
    }

    public function disconnect()
    {
        TimeDoctorToken::where('version', self::API_VERSION)->delete();

        ActivityLogService::log('timedoctor_v2_disconnect', 'Disconnected from TimeDoctor V2', [
            'module' => 'timedoctor_v2_integration',
            'version' => self::API_VERSION,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Disconnected from TimeDoctor V2',
        ]);
    }

    public function getCompanyInfo()
    {
        $accessToken = $this->getAccessToken();

        if (! $accessToken) {
            return response()->json([
                'success' => false,
                'message' => 'Not connected to TimeDoctor V2 or token expired',
            ], 401);
        }

        try {
            $companyId = config('services.timedoctor_v2.company_id');
            $response = Http::timeout(30)
                ->get("https://api2.timedoctor.com/api/1.0/companies/{$companyId}", [
                    'token' => $accessToken,
                ]);

            if (! $response->successful()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to fetch company data',
                    'data' => $response->json(),
                ], 500);
            }

            return response()->json($response->json());
        } catch (\Exception $e) {
            Log::error('Error fetching TimeDoctor V2 company info', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error fetching company info: '.$e->getMessage(),
            ], 500);
        }
    }
}
