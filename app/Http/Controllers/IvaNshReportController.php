<?php

namespace App\Http\Controllers;

use App\Models\IvaUser;
use App\Models\WorklogsData;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class IvaNshReportController extends Controller
{
    /**
     * Get NSH (Non Stop Hour) tracking report for all active IVA users
     * Shows users sorted by highest total hours to lowest
     */
    public function getNshReport(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'date' => 'nullable|date',
            'search' => 'nullable|string',
            'work_status' => 'nullable|string|in:full-time,part-time',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Validate region access for users with view_team_data permission
        $regionValidation = validateManagerRegionAccess($request->user());
        if ($regionValidation) {
            return response()->json([
                'success' => false,
                'error' => $regionValidation['error'],
                'message' => $regionValidation['message'],
                'region_access_error' => true
            ], 403);
        }

        // Check if current user should be filtered by region
        $managerRegionFilter = getManagerRegionFilter($request->user());

        // Get date (default to yesterday)
        $date = $request->input('date') ? Carbon::parse($request->input('date')) : Carbon::yesterday();
        $startOfDay = $date->copy()->startOfDay();
        $endOfDay = $date->copy()->endOfDay();

        // Build user query with optimized loading
        $usersQuery = IvaUser::select([
            'id',
            'full_name',
            'email',
            'job_title',
            'work_status',
            'region_id',
            'cohort_id',
            'timedoctor_version',
            'hire_date',
            'end_date',
        ])
            ->with(['region:id,name', 'cohort:id,name'])
            ->where('is_active', true);

        // Apply region filter for managers with view_team_data only
        if ($managerRegionFilter) {
            $usersQuery->where('region_id', $managerRegionFilter);
        }

        // Apply search filter
        if ($request->filled('search')) {
            $search = $request->input('search');
            $usersQuery->where(function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Apply work status filter
        if ($request->filled('work_status')) {
            $usersQuery->where('work_status', $request->input('work_status'));
        }

        // Get all users for NSH calculation
        $users = $usersQuery->get();

        // Get highest time records for each user
        $nshData = $this->getHighestTimeRecords(
            $users->pluck('id')->toArray(),
            $startOfDay->format('Y-m-d H:i:s'),
            $endOfDay->format('Y-m-d H:i:s'),
            $users
        );

        // No sorting - display in natural order

        // Apply pagination (50 items per page by default)
        $perPage = $request->input('per_page', 50);
        $page = $request->input('page', 1);
        $total = count($nshData);
        $offset = ($page - 1) * $perPage;
        $paginatedData = array_slice($nshData, $offset, $perPage);

        // Calculate summary statistics
        $summary = [
            'total_users' => $total,
            'total_hours' => collect($nshData)->sum('hours'),
            'average_hours' => $total > 0 ? collect($nshData)->avg('hours') : 0,
            'max_hours' => $total > 0 ? collect($nshData)->max('hours') : 0,
            'users_over_6h' => collect($nshData)->where('hours', '>=', 6)->count(),
            'users_over_10h' => collect($nshData)->where('hours', '>=', 10)->count(),
        ];

        return response()->json([
            'success' => true,
            'date' => $date->format('Y-m-d'),
            'is_yesterday' => $date->isYesterday(),
            'nsh_data' => $paginatedData,
            'summary' => $summary,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => ceil($total / $perPage),
                'from' => $offset + 1,
                'to' => min($offset + $perPage, $total),
            ],
            'region_filter' => $managerRegionFilter ? [
                'applied' => true,
                'region_id' => $managerRegionFilter,
                'reason' => 'view_team_data_permission'
            ] : ['applied' => false],
        ]);
    }

    /**
     * Get highest time records for NSH calculation (MySQL 5.7+ compatible)
     */
    private function getHighestTimeRecords($userIds, $startDateTime, $endDateTime, $users)
    {
        if (empty($userIds)) {
            return [];
        }

        // MySQL 5.7 compatible approach: Use JOIN with MAX() subquery
        $highestRecords = WorklogsData::select([
            'w1.id',
            'w1.iva_id',
            'w1.task_id',
            'w1.project_id',
            'w1.start_time',
            'w1.end_time',
            'w1.duration',
            'w1.comment',
        ])
            ->from('worklogs_data as w1')
            ->join(
                DB::raw('(SELECT iva_id, MAX(duration) as max_duration
                         FROM worklogs_data
                         WHERE iva_id IN ('.implode(',', $userIds).')
                         AND is_active = 1
                         AND start_time BETWEEN "'.$startDateTime.'" AND "'.$endDateTime.'"
                         GROUP BY iva_id) as w2'),
                function ($join) {
                    $join->on('w1.iva_id', '=', 'w2.iva_id')
                        ->on('w1.duration', '=', 'w2.max_duration');
                }
            )
            ->whereIn('w1.iva_id', $userIds)
            ->where('w1.is_active', true)
            ->whereBetween('w1.start_time', [$startDateTime, $endDateTime])
            ->orderBy('w1.duration', 'desc')
            ->with(['task:id,task_name', 'project:id,project_name'])
            ->get();

        // Handle duplicates by taking the first record per user (in case of ties)
        $uniqueRecords = $highestRecords->groupBy('iva_id')->map(function ($userRecords) {
            return $userRecords->first(); // Take the first record if multiple have same max duration
        });

        $nshData = [];
        $userMap = $users->keyBy('id');

        foreach ($uniqueRecords as $record) {
            $user = $userMap->get($record->iva_id);
            if (! $user) {
                continue;
            }

            $nshData[] = [
                'id' => $user->id,
                'full_name' => $user->full_name,
                'email' => $user->email,
                'job_title' => $user->job_title,
                'work_status' => $user->work_status,
                'region' => $user->region ? $user->region->name : 'Unknown Region',
                'hours' => round($record->duration / 3600, 2),
                'task_name' => $record->task ? $record->task->task_name : 'Unknown Task',
                'project_name' => $record->project ? $record->project->project_name : 'Unknown Project',
                'start_time' => $record->start_time,
                'end_time' => $record->end_time,
                'comment' => $record->comment,
            ];
        }

        return $nshData;
    }
}
