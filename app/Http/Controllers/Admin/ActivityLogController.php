<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

class ActivityLogController extends Controller
{
    /**
     * Display a listing of activity logs
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user();

            if (! $user->can('view_activity_logs')) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $query = ActivityLog::with('user');

            // Search by action
            if ($request->has('action') && ! empty($request->action)) {
                $query->byAction($request->action);
            }

            // Search by email
            if ($request->has('email') && ! empty($request->email)) {
                $query->byEmail($request->email);
            }

            // Search by user
            if ($request->has('user_id') && ! empty($request->user_id)) {
                $query->byUser($request->user_id);
            }

            // Filter by date range
            if ($request->has('start_date') && ! empty($request->start_date)) {
                $startDate = $request->start_date;
                $endDate = $request->get('end_date', now()->toDateString());
                $query->byDateRange($startDate, $endDate);
            }

            // Search in description
            if ($request->has('search') && ! empty($request->search)) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('description', 'like', "%{$search}%")
                        ->orWhere('action', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            }

            // Sorting
            $sortBy = $request->get('sort_by', 'created_at');
            $sortDirection = $request->get('sort_direction', 'desc');
            $query->orderBy($sortBy, $sortDirection);

            // Get pagination config
            $perPage = $request->get('per_page', config('constants.pagination.default_per_page', 20));
            $maxPerPage = config('constants.pagination.max_per_page', 100);
            $perPage = min($perPage, $maxPerPage);

            $logs = $query->paginate($perPage);

            $logsData = $logs->getCollection()->map(function ($log) {
                $detailLog = json_decode($log->detail_log, true) ?? [];

                return [
                    'id' => $log->id,
                    'user_id' => $log->user_id,
                    'user_name' => $log->user?->name,
                    'email' => $log->email,
                    'action' => $log->action,
                    'action_label' => config('constants.activity_log_actions.'.$log->action, ucfirst($log->action)),
                    'description' => $log->description,
                    'detail_log' => $detailLog,
                    'ip_address' => $detailLog['ip_address'] ?? null,
                    'user_agent' => $detailLog['user_agent'] ?? null,
                    'module' => $detailLog['details']['module'] ?? null,
                    'created_at' => $log->created_at,
                    'formatted_date' => $log->created_at->format('Y-m-d H:i:s'),
                ];
            });

            return response()->json([
                'logs' => $logsData,
                'pagination' => [
                    'current_page' => $logs->currentPage(),
                    'per_page' => $logs->perPage(),
                    'total' => $logs->total(),
                    'last_page' => $logs->lastPage(),
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch activity logs: '.$e->getMessage()], 500);
        }
    }

    /**
     * Display the specified activity log
     */
    public function show(Request $request, ActivityLog $activityLog)
    {
        try {
            $user = $request->user();

            if (! $user->can('view_activity_logs')) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $detailLog = json_decode($activityLog->detail_log, true) ?? [];

            return response()->json([
                'log' => [
                    'id' => $activityLog->id,
                    'user_id' => $activityLog->user_id,
                    'user_name' => $activityLog->user?->name,
                    'email' => $activityLog->email,
                    'action' => $activityLog->action,
                    'action_label' => config('constants.activity_log_actions.'.$activityLog->action, ucfirst($activityLog->action)),
                    'description' => $activityLog->description,
                    'detail_log' => $detailLog,
                    'created_at' => $activityLog->created_at,
                    'formatted_date' => $activityLog->created_at->format('Y-m-d H:i:s'),
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch activity log: '.$e->getMessage()], 500);
        }
    }

    /**
     * Export activity logs to CSV
     */
    public function export(Request $request)
    {
        try {
            $user = $request->user();

            if (! $user->can('view_activity_logs')) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $query = ActivityLog::with('user');

            // Apply same filters as index method
            if ($request->has('action') && ! empty($request->action)) {
                $query->byAction($request->action);
            }

            if ($request->has('email') && ! empty($request->email)) {
                $query->byEmail($request->email);
            }

            if ($request->has('user_id') && ! empty($request->user_id)) {
                $query->byUser($request->user_id);
            }

            if ($request->has('start_date') && ! empty($request->start_date)) {
                $startDate = $request->start_date;
                $endDate = $request->get('end_date', now()->toDateString());
                $query->byDateRange($startDate, $endDate);
            }

            if ($request->has('search') && ! empty($request->search)) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('description', 'like', "%{$search}%")
                        ->orWhere('action', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            }

            // Apply sorting for export
            $sortBy = $request->get('sort_by', 'created_at');
            $sortDirection = $request->get('sort_direction', 'desc');
            $query->orderBy($sortBy, $sortDirection);

            // Limit to prevent memory issues
            $logs = $query->limit(10000)->get();

            // Generate CSV content
            $csvData = [];
            $csvData[] = [
                'ID',
                'User Name',
                'Email',
                'Action',
                'Action Label',
                'Description',
                'IP Address',
                'Module',
                'Date',
            ];

            foreach ($logs as $log) {
                $detailLog = json_decode($log->detail_log, true) ?? [];

                $csvData[] = [
                    $log->id,
                    $log->user?->name ?? 'N/A',
                    $log->email ?? 'N/A',
                    $log->action,
                    config('constants.activity_log_actions.'.$log->action, ucfirst($log->action)),
                    $log->description,
                    $detailLog['ip_address'] ?? 'N/A',
                    $detailLog['details']['module'] ?? 'N/A',
                    $log->created_at->format('Y-m-d H:i:s'),
                ];
            }

            // Create CSV content
            $csvContent = '';
            foreach ($csvData as $row) {
                $csvContent .= implode(',', array_map(function ($field) {
                    return '"'.str_replace('"', '""', $field).'"';
                }, $row))."\n";
            }

            // Log the export activity
            ActivityLogService::logDataExport('activity_logs', [
                'total_records' => count($logs),
                'filters' => $request->only(['action', 'email', 'user_id', 'start_date', 'end_date', 'search', 'sort_by', 'sort_direction']),
            ]);

            $filename = 'activity_logs_'.now()->format('Y_m_d_H_i_s').'.csv';

            return Response::make($csvContent, 200, [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to export activity logs: '.$e->getMessage()], 500);
        }
    }

    /**
     * Get available filter options
     */
    public function filterOptions(Request $request)
    {
        try {
            $user = $request->user();

            if (! $user->can('view_activity_logs')) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $actions = ActivityLog::distinct()
                ->pluck('action')
                ->filter()
                ->map(function ($action) {
                    return [
                        'title' => config('constants.activity_log_actions.'.$action, ucfirst($action)),
                        'value' => $action,
                    ];
                })
                ->values();

            $users = ActivityLog::whereNotNull('user_id')
                ->with('user')
                ->get()
                ->pluck('user')
                ->filter()
                ->unique('id')
                ->map(function ($user) {
                    return [
                        'title' => "{$user->name} ({$user->email})",
                        'value' => $user->id,
                    ];
                })
                ->values();

            return response()->json([
                'actions' => $actions,
                'users' => $users,
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch filter options: '.$e->getMessage()], 500);
        }
    }
}
