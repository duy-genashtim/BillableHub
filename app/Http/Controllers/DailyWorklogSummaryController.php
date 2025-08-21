<?php
namespace App\Http\Controllers;

use App\Models\IvaUser;
use App\Services\DailyWorklogSummaryService;
use Illuminate\Http\Request;
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
    public function getCalculationOptions()
    {
        try {
            $ivaUsers = IvaUser::where('is_active', true)
                ->with('region')
                ->orderBy('full_name')
                ->get()
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
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load calculation options: ' . $e->getMessage(),
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

        if (!empty($errors)) {
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
            
            // Additional validation
            $errors = $this->summaryService->validateCalculationParams($params);
            if (!empty($errors)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $errors,
                ], 422);
            }

            // Start calculation
            $result = $this->summaryService->calculateSummaries($params);

            return response()->json($result);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Calculation failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get calculation progress (for real-time updates)
     */
    public function getCalculationProgress(Request $request)
    {
        $sessionId = $request->input('session_id');
        
        if (!$sessionId) {
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
                'message' => 'Failed to get progress: ' . $e->getMessage(),
            ], 500);
        }
    }
}