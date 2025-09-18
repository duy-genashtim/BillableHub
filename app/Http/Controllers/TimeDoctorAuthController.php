<?php

namespace App\Http\Controllers;

use App\Models\TimeDoctorToken;
use App\Services\ActivityLogService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;

class TimeDoctorAuthController extends Controller
{
    private const API_VERSION = '1';

    private const AUTH_URL = 'https://webapi.timedoctor.com/oauth/v2/auth';

    private const TOKEN_URL = 'https://webapi.timedoctor.com/oauth/v2/token';

    public function redirect()
    {
        $query = http_build_query([
            'response_type' => 'code',
            'client_id' => config('services.timedoctor_v1.client_id'),
            'redirect_uri' => config('services.timedoctor_v1.redirect_uri'),
        ]);

        ActivityLogService::log('timedoctor_auth_redirect', 'Redirecting to TimeDoctor OAuth authorization', [
            'module' => 'timedoctor_integration',
            'version' => self::API_VERSION,
        ]);

        return Redirect::to(self::AUTH_URL.'?'.$query);
    }

    public function callback(Request $request)
    {
        if ($request->has('error')) {
            ActivityLogService::log('timedoctor_auth_error', 'TimeDoctor OAuth authorization failed', [
                'module' => 'timedoctor_integration',
                'error' => $request->input('error_description', 'Unknown error'),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Authorization failed: '.$request->input('error_description', 'Unknown error'),
            ], 400);
        }

        if (! $request->has('code')) {
            return response()->json([
                'success' => false,
                'message' => 'Authorization code is missing',
            ], 400);
        }

        try {
            $response = Http::post(self::TOKEN_URL, [
                'client_id' => config('services.timedoctor_v1.client_id'),
                'client_secret' => config('services.timedoctor_v1.client_secret'),
                'grant_type' => 'authorization_code',
                'code' => $request->input('code'),
                'redirect_uri' => config('services.timedoctor_v1.redirect_uri'),
            ]);

            if (! $response->successful()) {
                Log::error('Failed to exchange authorization code for access token', [
                    'status' => $response->status(),
                    'response' => $response->json(),
                ]);

                ActivityLogService::log('timedoctor_auth_token_error', 'Failed to exchange authorization code for access token', [
                    'module' => 'timedoctor_integration',
                    'status' => $response->status(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Failed to exchange authorization code for access token',
                ], 400);
            }

            $tokenData = $response->json();

            TimeDoctorToken::updateOrCreate(
                ['version' => self::API_VERSION],
                [
                    'access_token' => $tokenData['access_token'],
                    'refresh_token' => $tokenData['refresh_token'],
                    'expires_at' => Carbon::now()->addSeconds($tokenData['expires_in']),
                ]
            );

            ActivityLogService::log('timedoctor_auth_success', 'Successfully connected to TimeDoctor', [
                'module' => 'timedoctor_integration',
                'version' => self::API_VERSION,
                'expires_at' => Carbon::now()->addSeconds($tokenData['expires_in'])->toISOString(),
            ]);

            return redirect('/admin/timedoctor')->with('success', 'Successfully connected to TimeDoctor!');

        } catch (\Exception $e) {
            Log::error('Failed to process TimeDoctor callback', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            ActivityLogService::log('timedoctor_auth_callback_error', 'Failed to process TimeDoctor callback', [
                'module' => 'timedoctor_integration',
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to process TimeDoctor callback: '.$e->getMessage(),
            ], 500);
        }
    }

    public function checkToken()
    {
        $token = TimeDoctorToken::where('version', self::API_VERSION)->first();

        if (! $token) {
            return response()->json([
                'connected' => false,
                'message' => 'No TimeDoctor connection found',
            ]);
        }

        if (Carbon::parse($token->expires_at)->isPast()) {
            try {
                $refreshed = $this->refreshToken();

                if (! $refreshed) {
                    return response()->json([
                        'connected' => false,
                        'message' => 'Token expired and refresh failed',
                        'expires_at' => $token->expires_at,
                    ]);
                }

                return response()->json([
                    'connected' => true,
                    'message' => 'Connected to TimeDoctor (token refreshed)',
                    'expires_at' => $token->fresh()->expires_at,
                ]);
            } catch (\Exception $e) {
                Log::error('Token refresh failed', [
                    'error' => $e->getMessage(),
                ]);

                return response()->json([
                    'connected' => false,
                    'message' => 'Token expired and refresh failed',
                ]);
            }
        }

        return response()->json([
            'connected' => true,
            'message' => 'Connected to TimeDoctor',
            'expires_at' => $token->expires_at,
        ]);
    }

    public function refreshToken()
    {
        $token = TimeDoctorToken::where('version', self::API_VERSION)->first();

        if (! $token) {
            return response()->json([
                'success' => false,
                'message' => 'No TimeDoctor token found',
            ], 404);
        }

        try {
            $response = Http::post(self::TOKEN_URL, [
                'client_id' => config('services.timedoctor_v1.client_id'),
                'client_secret' => config('services.timedoctor_v1.client_secret'),
                'grant_type' => 'refresh_token',
                'refresh_token' => $token->refresh_token,
            ]);

            if (! $response->successful()) {
                Log::error('Failed to refresh TimeDoctor token', [
                    'status' => $response->status(),
                    'response' => $response->json(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Failed to refresh TimeDoctor token',
                ], 400);
            }

            $newTokenData = $response->json();

            $token->update([
                'access_token' => $newTokenData['access_token'],
                'refresh_token' => $newTokenData['refresh_token'],
                'expires_at' => Carbon::now()->addSeconds($newTokenData['expires_in']),
            ]);

            ActivityLogService::log('timedoctor_token_refresh', 'TimeDoctor token refreshed successfully', [
                'module' => 'timedoctor_integration',
                'version' => self::API_VERSION,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'TimeDoctor token refreshed successfully',
                'expires_at' => $token->expires_at,
            ]);

        } catch (\Exception $e) {
            Log::error('Exception during TimeDoctor token refresh', [
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
            $refreshed = $this->refreshToken();

            if (! $refreshed) {
                return null;
            }

            $token = $token->fresh();
        }

        return $token->access_token;
    }

    public function disconnect()
    {
        TimeDoctorToken::where('version', self::API_VERSION)->delete();

        ActivityLogService::log('timedoctor_disconnect', 'Disconnected from TimeDoctor', [
            'module' => 'timedoctor_integration',
            'version' => self::API_VERSION,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Disconnected from TimeDoctor',
        ]);
    }

    public function getCompanyInfo()
    {
        $accessToken = $this->getAccessToken();

        if (! $accessToken) {
            return response()->json([
                'success' => false,
                'message' => 'Not connected to TimeDoctor or token expired',
            ], 401);
        }

        try {
            $response = Http::withToken($accessToken)
                ->get('https://webapi.timedoctor.com/v1.1/companies');

            if (! $response->successful()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to fetch company data',
                    'data' => $response->json(),
                ], 500);
            }

            return response()->json($response->json());
        } catch (\Exception $e) {
            Log::error('Error fetching TimeDoctor company info', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error fetching company info: '.$e->getMessage(),
            ], 500);
        }
    }
}
