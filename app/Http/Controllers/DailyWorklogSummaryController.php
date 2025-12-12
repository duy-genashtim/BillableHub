<?php

namespace App\Http\Controllers;

use App\Models\IvaUser;
use App\Services\DailyWorklogSummaryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class DailyWorklogSummaryController extends Controller
{
    protected $summaryService;

    public function __construct(DailyWorklogSummaryService $summaryService)
    {
        $this->summaryService = $summaryService;
    }

    /**
     * Get options for calculation page
     */
    public function getCalculationOptions(Request $request)
    {
        try {
            // Check if current user should be filtered by region
            $managerRegionFilter = getManagerRegionFilter($request->user());

            $query = IvaUser::where('is_active', true)
                ->with('region')
                ->orderBy('full_name');

            // Apply region filter for managers with view_team_data only
            if ($managerRegionFilter) {
                $query->where('region_id', $managerRegionFilter);
            }

            $ivaUsers = $query->get()
                ->map(function ($user) {
                    return [
                        'id' => $user->id,
                        'full_name' => $user->full_name,
                        'region_name' => $user->region->name ?? 'No Region',
                        'email' => $user->email,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => [
                    'iva_users' => $ivaUsers,
                ],
                'region_filter' => $managerRegionFilter ? [
                    'applied' => true,
                    'region_id' => $managerRegionFilter,
                    'reason' => 'view_team_data_permission'
                ] : ['applied' => false],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load calculation options: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Validate calculation parameters
     */
    public function validateCalculation(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'calculate_all' => 'boolean',
            'iva_user_ids' => 'nullable|array',
            'iva_user_ids.*' => 'integer|exists:iva_user,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Additional business logic validation
        $params = $request->only(['start_date', 'end_date', 'calculate_all', 'iva_user_ids']);
        $errors = $this->summaryService->validateCalculationParams($params);

        if (! empty($errors)) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $errors,
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Validation passed',
        ]);
    }

    /**
     * Start calculation process
     */
    public function startCalculation(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'calculate_all' => 'boolean',
            'iva_user_ids' => 'nullable|array',
            'iva_user_ids.*' => 'integer|exists:iva_user,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $params = $request->only(['start_date', 'end_date', 'calculate_all', 'iva_user_ids']);

            // Log incoming parameters for debugging
            Log::info('Daily worklog calculation requested', [
                'params' => $params,
                'user_id' => auth()->id(),
            ]);

            // Additional validation
            $errors = $this->summaryService->validateCalculationParams($params);
            if (! empty($errors)) {
                Log::warning('Calculation validation failed', ['errors' => $errors, 'params' => $params]);
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $errors,
                ], 422);
            }

            // Start calculation
            $result = $this->summaryService->calculateSummaries($params);

            // Log result summary
            Log::info('Calculation completed', [
                'success' => $result['success'],
                'total_ivas' => $result['summary']['total_ivas'] ?? 0,
                'total_processed' => $result['summary']['total_processed'] ?? 0,
                'total_errors' => $result['summary']['total_errors'] ?? 0,
            ]);

            return response()->json($result);

        } catch (\Exception $e) {
            Log::error('Daily worklog calculation error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'params' => $params ?? [],
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Calculation failed: '.$e->getMessage(),
                'debug_info' => [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ],
            ], 500);
        }
    }

    /**
     * Get calculation progress (for real-time updates)
     */
    public function getCalculationProgress(Request $request)
    {
        $sessionId = $request->input('session_id');

        if (! $sessionId) {
            return response()->json([
                'success' => false,
                'message' => 'Session ID is required',
            ], 422);
        }

        try {
            $progress = $this->summaryService->getCalculationProgress($sessionId);

            return response()->json([
                'success' => true,
                'data' => $progress,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get progress: '.$e->getMessage(),
            ], 500);
        }
    }
}
