<?php

namespace App\Http\Controllers;

use App\Models\Cohort;
use App\Models\ConfigurationSetting;
use App\Models\ConfigurationSettingType;
use App\Models\IvaManager;
use App\Models\IvaUser;
use App\Models\IvaUserChangelog;
use App\Models\IvaUserCustomize;
use App\Models\IvaUserLog;
use App\Models\Region;
use App\Models\Task;
use App\Models\TimedoctorV1User;
use App\Models\TimedoctorV2User;
use App\Services\ActivityLogService;
use App\Services\TimeDoctor\TimeDoctorService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class IvaUserController extends Controller
{
    protected $timeDoctorService;

    public function __construct(TimeDoctorService $timeDoctorService)
    {
        $this->timeDoctorService = $timeDoctorService;
    }

    /**
     * Display a listing of IVA users.
     */
    public function index(Request $request)
    {
        $perPage = $request->input('per_page', config('constants.pagination.iva_users_per_page'));
        $perPage = min($perPage, config('constants.pagination.max_per_page'));

        // Check if current user should be filtered by region
        $managerRegionFilter = getManagerRegionFilter($request->user());

        $query = IvaUser::with(['region', 'cohort', 'timedoctorUser']);

        // Apply region filter for managers with view_team_data only
        if ($managerRegionFilter) {
            $query->where('region_id', $managerRegionFilter);
        }

        // Apply filters if provided (only if not already filtered by manager region)
        if (!$managerRegionFilter && $request->has('region_id') && ! empty($request->region_id)) {
            $query->where('region_id', $request->region_id);
        }

        if ($request->has('cohort_id') && ! empty($request->cohort_id)) {
            $query->where('cohort_id', $request->cohort_id);
        }

        if ($request->has('is_active') && $request->is_active !== null) {
            $query->where('is_active', $request->is_active === 'true' || $request->is_active === '1');
        }

        if ($request->has('work_status') && ! empty($request->work_status)) {
            $query->where('work_status', $request->work_status);
        }

        if ($request->has('timedoctor_version') && ! empty($request->timedoctor_version)) {
            $query->where('timedoctor_version', $request->timedoctor_version);
        }

        if ($request->has('search') && ! empty($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'LIKE', "%{$search}%")
                    ->orWhere('email', 'LIKE', "%{$search}%")
                    ->orWhere('job_title', 'LIKE', "%{$search}%");
            });
        }

        // Get all regions and cohorts for filter dropdown
        $regions = Region::where('is_active', true)->orderBy('name')->get();
        $cohorts = Cohort::where('is_active', true)->orderBy('name')->get();

        // Get work status options from configuration
        $workStatusOptions = $this->getWorkStatusOptions();

        // Get TimeDoctor options
        $timedoctorOptions = $this->getTimeDoctorOptions();

        // Add pagination
        $users = $query->orderBy('full_name')->paginate($perPage);

        // Load manager information and TimeDoctor sync status for each user
        $users->each(function ($user) {
            $user->loadManagerInfo();
            $user->setAttribute('timedoctor_sync_status', $this->getTimeDoctorSyncStatus($user));
        });

        // Log the activity
        // ActivityLogService::log(
        //     'view_iva_users_list',
        //     'Viewed IVA users list',
        //     ['total_users' => $users->total()]
        // );

        return response()->json([
            'users' => $users,
            'regions' => $regions,
            'cohorts' => $cohorts,
            'work_status_options' => $workStatusOptions,
            'timedoctor_versions' => $timedoctorOptions,
            'region_filter' => $managerRegionFilter ? [
                'applied' => true,
                'region_id' => $managerRegionFilter,
                'reason' => 'view_team_data_permission'
            ] : ['applied' => false],
        ]);
    }

    /**
     * Store a newly created IVA user.
     */
    public function store(Request $request)
    {
        // Check if user has permission to edit IVA data
        if (! $request->user()->can('edit_iva_data')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'full_name' => 'required|string|max:255',
            'job_title' => 'nullable|string|max:255',
            'email' => 'required|email|unique:iva_user,email',
            'hire_date' => 'nullable|date',
            'region_id' => 'nullable|exists:regions,id',
            'cohort_id' => 'nullable|exists:cohorts,id',
            'work_status' => ['nullable', 'string'],
            'timedoctor_version' => 'required|integer|in:1,2',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Validate work_status against configuration settings
        if ($request->work_status) {
            $validWorkStatuses = $this->getValidWorkStatuses();
            if (! in_array($request->work_status, $validWorkStatuses)) {
                return response()->json([
                    'errors' => ['work_status' => ['Invalid work status selected']],
                ], 422);
            }
        }

        DB::beginTransaction();

        try {
            // Create the user
            $user = IvaUser::create([
                'full_name' => $request->full_name,
                'job_title' => $request->job_title,
                'email' => $request->email,
                'hire_date' => $request->hire_date,
                'region_id' => $request->region_id,
                'cohort_id' => $request->cohort_id,
                'work_status' => $request->work_status,
                'timedoctor_version' => $request->timedoctor_version,
                'is_active' => $request->is_active ?? true,
            ]);

            // Check if email exists in TimeDoctor tables and link
            $this->linkTimeDoctorUser($user);

            // Log the creation
            ActivityLogService::log(
                'create_iva_user',
                'Created new IVA user: '.$user->full_name,
                $user->toArray()
            );

            DB::commit();

            return response()->json([
                'message' => 'User created successfully',
                'user' => $user->load(['region', 'cohort', 'timedoctorUser']),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to create user',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified IVA user with all related information.
     */
    public function show($id)
    {
        $user = IvaUser::with([
            'region',
            'cohort',
            'timedoctorUser',
            'managers.manager',
            'managers.managerType',
            'managers.region',
            'changelogs' => function ($query) {
                $query->orderBy('created_at', 'desc');
            },
            'customizations.setting.settingType',
            'logs' => function ($query) {
                $query->with('creator')->orderBy('created_at', 'desc');
            },
        ])->findOrFail($id);

        // Process changelogs to add formatted display values
        $user->changelogs->each(function ($changelog) {
            $changelog->display_values = $this->formatChangelogDisplayValues($changelog);
        });

        // Get all regions and cohorts for dropdown
        $regions = Region::where('is_active', true)->orderBy('name')->get();
        $cohorts = Cohort::where('is_active', true)->orderBy('name')->get();

        // Get work status options from configuration
        $workStatusOptions = $this->getWorkStatusOptions();

        // Get timedoctor versions
        $timedoctorVersions = $this->getTimeDoctorOptions();

        // Get user customization types
        $customizationTypes = ConfigurationSettingType::where('for_user_customize', true)
            ->where('allow_create', true)
            ->with(['settings' => function ($query) {
                $query->where('is_active', true);
            }])
            ->get();

        // Get all possible manager types
        $managerTypes = $this->getManagerTypes();

        // Get log types for IVA user logs
        $logTypes = $this->getLogTypes();

        // Get TimeDoctor sync status
        $timeDoctorSyncStatus = $this->getTimeDoctorSyncStatus($user);

        // Log the activity
        // ActivityLogService::log(
        //     'view_iva_user',
        //     'Viewed IVA user details: ' . $user->full_name,
        //     ['user_id' => $id]
        // );

        return response()->json([
            'user' => $user,
            'regions' => $regions,
            'cohorts' => $cohorts,
            'timedoctorVersions' => $timedoctorVersions,
            'workStatusOptions' => $workStatusOptions,
            'customizationTypes' => $customizationTypes,
            'managerTypes' => $managerTypes,
            'logTypes' => $logTypes,
            'timeDoctorSyncStatus' => $timeDoctorSyncStatus,
        ]);
    }

    /**
     * Update the specified IVA user.
     */
    public function update(Request $request, $id)
    {
        // Check if user has permission to edit IVA data
        if (! $request->user()->can('edit_iva_data')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $user = IvaUser::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'full_name' => 'required|string|max:255',
            'job_title' => 'nullable|string|max:255',
            'email' => [
                'required',
                'email',
                Rule::unique('iva_user')->ignore($id),
            ],
            'hire_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'region_id' => 'nullable|exists:regions,id',
            'cohort_id' => 'nullable|exists:cohorts,id',
            'work_status' => ['nullable', 'string'],
            'timedoctor_version' => 'required|integer|in:1,2',
            'is_active' => 'boolean',
            'region_change_info.reason' => 'required_if:region_id,!'.$user->region_id.'|string|nullable',
            'region_change_info.effectiveDate' => 'required_if:region_id,!'.$user->region_id.'|date|nullable',
            'cohort_change_info.reason' => 'required_if:cohort_id,!'.$user->cohort_id.'|string|nullable',
            'cohort_change_info.effectiveDate' => 'required_if:cohort_id,!'.$user->cohort_id.'|date|nullable',
            'work_status_change_info.reason' => 'required_if:work_status,!'.$user->work_status.'|string|nullable',
            'work_status_change_info.effectiveDate' => 'required_if:work_status,!'.$user->work_status.'|date|nullable',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Validate work_status against configuration settings
        if ($request->work_status) {
            $validWorkStatuses = $this->getValidWorkStatuses();
            if (! in_array($request->work_status, $validWorkStatuses)) {
                return response()->json([
                    'errors' => ['work_status' => ['Invalid work status selected']],
                ], 422);
            }
        }

        DB::beginTransaction();

        try {
            // Save the old values for logging
            $oldValues = $user->toArray();

            // Update user
            $user->full_name = $request->full_name;
            $user->job_title = $request->job_title;
            $user->email = $request->email;
            $user->hire_date = $request->hire_date;
            $user->end_date = $request->end_date;

            // Track changes to specific fields that need changelog entries
            $changedFields = [];

            // For region changes
            if ($user->region_id != $request->region_id) {
                $changedFields['region'] = [
                    'old' => $user->region_id,
                    'new' => $request->region_id,
                    'reason' => $request->region_change_info['reason'] ?? 'Region updated',
                    'effective_date' => $request->region_change_info['effectiveDate'] ?? now(),
                ];
                $user->region_id = $request->region_id;
            }

            // For cohort changes
            if ($user->cohort_id != $request->cohort_id) {
                $changedFields['cohort'] = [
                    'old' => $user->cohort_id,
                    'new' => $request->cohort_id,
                    'reason' => $request->cohort_change_info['reason'] ?? 'Cohort updated',
                    'effective_date' => $request->cohort_change_info['effectiveDate'] ?? now(),
                ];
                $user->cohort_id = $request->cohort_id;
            }

            // For work status changes
            if ($user->work_status != $request->work_status) {
                $changedFields['work_status'] = [
                    'old' => $user->work_status,
                    'new' => $request->work_status,
                    'reason' => $request->work_status_change_info['reason'] ?? 'Work status updated',
                    'effective_date' => $request->work_status_change_info['effectiveDate'] ?? now(),
                ];
                $user->work_status = $request->work_status;
            }

            $user->timedoctor_version = $request->timedoctor_version;
            $user->is_active = $request->is_active;
            $user->save();

            // Update TimeDoctor user info if exists and re-link if needed
            $this->updateTimeDoctorUserInfo($user);

            // Create changelog entries for tracked fields
            foreach ($changedFields as $field => $values) {
                IvaUserChangelog::create([
                    'iva_user_id' => $user->id,
                    'field_changed' => $field,
                    'old_value' => json_encode($values['old']),
                    'new_value' => json_encode($values['new']),
                    'change_reason' => $values['reason'],
                    'changed_by_name' => request()->user() ? request()->user()->name : 'System',
                    'changed_by_email' => request()->user() ? request()->user()->email : 'system@example.com',
                    'effective_date' => $values['effective_date'],
                ]);
            }

            // Log the activity
            ActivityLogService::log(
                'update_iva_user',
                'Updated IVA user: '.$user->full_name,
                [
                    'old' => $oldValues,
                    'new' => $user->toArray(),
                ]
            );

            DB::commit();

            return response()->json([
                'message' => 'User updated successfully',
                'user' => $user->fresh(['region', 'cohort', 'timedoctorUser', 'managers.manager', 'managers.managerType']),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to update user',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Sync IVA users from external API
     */
    public function syncUsers(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'sync_type' => 'required|in:manager,specific',
            'emails' => 'required_if:sync_type,specific|array',
            'emails.*' => 'required_if:sync_type,specific|email',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $token = encryptSecureHRMSToken();
            if (! $token) {
                return response()->json([
                    'message' => 'Failed to generate secure token',
                ], 500);
            }

            $apiUrl = config('services.hrms.ivas_info_url');
            $payload = ['token' => $token];

            if ($request->sync_type === 'manager') {
                // Get COO email from configuration
                $cooEmailSetting = ConfigurationSetting::join('configuration_settings_type', 'configuration_settings.setting_type_id', '=', 'configuration_settings_type.id')
                    ->where('configuration_settings_type.key', 'tocert_coo_email')
                    ->where('configuration_settings.is_active', true)
                    ->first();

                if (! $cooEmailSetting) {
                    return response()->json([
                        'message' => 'COO email not configured in system settings',
                    ], 422);
                }

                $payload['email'] = $cooEmailSetting->setting_value;
            } else {
                $payload['emails'] = $request->emails;
            }

            // Make API call
            $response = Http::timeout(60)->post($apiUrl, $payload);

            if (! $response->successful()) {
                Log::error('Employee API sync failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return response()->json([
                    'message' => 'Failed to sync users from external API',
                    'error' => $response->body(),
                ], 500);
            }

            $responseData = $response->json();

            if (! $responseData['success'] || empty($responseData['data'])) {
                return response()->json([
                    'message' => 'No users data received from API',
                ], 422);
            }

            $syncResults = $this->processUserSyncData($responseData['data']);

            // TimeDoctor task sync for specific employees only
            $timedoctorResults = [];
            if ($request->sync_type === 'specific') {
                $timedoctorResults = $this->syncTimeDocTasksForSyncedUsers($responseData['data']);
            }

            // Log the activity
            ActivityLogService::log(
                'sync_iva_users',
                'Synced IVA users from external API',
                [
                    'sync_type' => $request->sync_type,
                    'total_processed' => count($responseData['data']),
                    'created' => $syncResults['created'],
                    'updated' => $syncResults['updated'],
                    'work_status_warnings' => $syncResults['work_status_warnings'],
                    'timedoctor_results' => $timedoctorResults,
                ]
            );

            $response = [
                'message' => 'Users sync completed successfully',
                'results' => $syncResults,
            ];

            // Add TimeDoctor results if available
            if (!empty($timedoctorResults)) {
                $response['timedoctor_results'] = $timedoctorResults;
                $response['message'] = 'Users sync and TimeDoctor task sync completed successfully';
            }

            return response()->json($response);

        } catch (\Exception $e) {
            Log::error('User sync error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Failed to sync users',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get COO email for sync page
     */
    public function getCooEmail()
    {
        // dd('get coo email');
        $cooEmailSetting = ConfigurationSetting::join('configuration_settings_type', 'configuration_settings.setting_type_id', '=', 'configuration_settings_type.id')
            ->where('configuration_settings_type.key', 'tocert_coo_email')
            ->where('configuration_settings.is_active', true)
            ->select('configuration_settings.*')
            ->first();

        if (! $cooEmailSetting) {
            return response()->json([
                'message' => 'COO email not configured',
            ], 422);
        }

        return response()->json([
            'email' => $cooEmailSetting->setting_value,
            'name' => $cooEmailSetting->description,
        ]);
    }

    /**
     * Process user sync data from API
     */
    private function processUserSyncData($usersData)
    {
        $created = [];
        $updated = [];
        $workStatusWarnings = [];

        DB::beginTransaction();

        try {
            foreach ($usersData as $userData) {
                $email = $userData['email'];
                $existingUser = IvaUser::where('email', $email)->first();

                // Map type_of_work to our work_status format
                $workStatus = null;
                if (isset($userData['type_of_work'])) {
                    $workType = strtolower($userData['type_of_work']);
                    if (strpos($workType, 'full') !== false) {
                        $workStatus = 'full-time';
                    } elseif (strpos($workType, 'part') !== false) {
                        $workStatus = 'part-time';
                    }
                }

                $syncData = [
                    'full_name' => $userData['full_name'],
                    'job_title' => $userData['job_title'] ?? null,
                    'email' => $email,
                    'hire_date' => $userData['start_date'] ?? null,
                    'end_date' => $userData['leaving_date'] ?? null,
                    'is_active' => $userData['current_status'] ?? true,
                    'timedoctor_version' => 1, // Default to version 1
                ];

                if ($existingUser) {
                    // Update existing user
                    $existingUser->update([
                        'full_name' => $syncData['full_name'],
                        'job_title' => $syncData['job_title'],
                        'hire_date' => $syncData['hire_date'],
                        'end_date' => $syncData['end_date'],
                        'is_active' => $syncData['is_active'],
                        // Note: we don't update work_status automatically
                    ]);

                    // Check if work status needs to be updated manually
                    if ($workStatus && $existingUser->work_status !== $workStatus) {
                        $workStatusWarnings[] = [
                            'user' => $existingUser->full_name,
                            'email' => $existingUser->email,
                            'current_status' => $existingUser->work_status,
                            'api_status' => $workStatus,
                            'message' => "Work status needs manual update from '{$existingUser->work_status}' to '{$workStatus}'",
                        ];
                    }

                    $updated[] = [
                        'id' => $existingUser->id,
                        'full_name' => $existingUser->full_name,
                        'email' => $existingUser->email,
                        'job_title' => $existingUser->job_title,
                        'action' => 'updated',
                    ];
                } else {
                    // Create new user
                    if ($workStatus) {
                        $syncData['work_status'] = $workStatus;
                    }

                    $newUser = IvaUser::create($syncData);

                    // Try to link with TimeDoctor user
                    $this->linkTimeDoctorUser($newUser);

                    $created[] = [
                        'id' => $newUser->id,
                        'full_name' => $newUser->full_name,
                        'email' => $newUser->email,
                        'job_title' => $newUser->job_title,
                        'action' => 'created',
                    ];
                }
            }

            DB::commit();

            return [
                'created' => $created,
                'updated' => $updated,
                'work_status_warnings' => $workStatusWarnings,
                'total_created' => count($created),
                'total_updated' => count($updated),
                'total_warnings' => count($workStatusWarnings),
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    // ... (rest of the methods remain the same, but update any batch references to cohort)

    /**
     * Get cohort display value (name)
     */
    private function getCohortDisplayValue($value)
    {
        if (is_null($value) || $value === '') {
            return 'No Cohort';
        }

        $cohortId = json_decode($value, true) ?? $value;
        $cohort = Cohort::find($cohortId);

        if ($cohort) {
            return $cohort->name;
        }

        return $value;
    }

    /**
     * Format changelog display values for better presentation
     */
    private function formatChangelogDisplayValues($changelog)
    {
        $displayValues = [
            'old_display' => null,
            'new_display' => null,
        ];

        switch ($changelog->field_changed) {
            case 'region':
                $displayValues['old_display'] = $this->getRegionDisplayValue($changelog->old_value);
                $displayValues['new_display'] = $this->getRegionDisplayValue($changelog->new_value);
                break;

            case 'cohort':
                $displayValues['old_display'] = $this->getCohortDisplayValue($changelog->old_value);
                $displayValues['new_display'] = $this->getCohortDisplayValue($changelog->new_value);
                break;

            case 'work_status':
                $displayValues['old_display'] = $this->getWorkStatusDisplayValue($changelog->old_value);
                $displayValues['new_display'] = $this->getWorkStatusDisplayValue($changelog->new_value);
                break;

            case 'is_active':
                $oldValue = json_decode($changelog->old_value, true);
                $newValue = json_decode($changelog->new_value, true);
                $displayValues['old_display'] = $oldValue ? 'Active' : 'Inactive';
                $displayValues['new_display'] = $newValue ? 'Active' : 'Inactive';
                break;

            default:
                // For other fields, try to decode JSON and display as string
                $oldValue = json_decode($changelog->old_value, true);
                $newValue = json_decode($changelog->new_value, true);

                $displayValues['old_display'] = is_null($oldValue) ? $changelog->old_value : (is_array($oldValue) ? json_encode($oldValue) : $oldValue);
                $displayValues['new_display'] = is_null($newValue) ? $changelog->new_value : (is_array($newValue) ? json_encode($newValue) : $newValue);
                break;
        }

        return $displayValues;
    }

    // ... (rest of the existing methods remain the same)

    /**
     * Get region display value (description or name)
     */
    private function getRegionDisplayValue($value)
    {
        if (is_null($value) || $value === '') {
            return 'No Region';
        }

        $regionId = json_decode($value, true) ?? $value;
        $region = Region::find($regionId);

        if ($region) {
            // Use description if available, otherwise use name
            return $region->description ?: $region->name;
        }

        return $value;
    }

    /**
     * Get work status display value (description from configuration)
     */
    private function getWorkStatusDisplayValue($value)
    {
        if (is_null($value) || $value === '') {
            return 'Not Set';
        }

        $workStatus = json_decode($value, true) ?? $value;

        // Get work status configuration setting
        $workStatusType = ConfigurationSettingType::where('key', 'work_status')->first();
        if ($workStatusType) {
            $setting = ConfigurationSetting::where('setting_type_id', $workStatusType->id)
                ->where('setting_value', $workStatus)
                ->first();

            if ($setting && $setting->description) {
                return $setting->description;
            }
        }

        return $workStatus;
    }

    /**
     * Add customizations to a user.
     */
    public function addCustomizations(Request $request, $id)
    {
        // Check if user has permission to edit IVA data
        if (! $request->user()->can('edit_iva_data')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $user = IvaUser::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'customizations' => 'required|array|min:1',
            'customizations.*.setting_id' => 'required|exists:configuration_settings,id',
            'customizations.*.custom_value' => 'required|string',
            'customizations.*.start_date' => 'required|date',
            'customizations.*.end_date' => 'nullable|date|after_or_equal:customizations.*.start_date',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();

        try {
            $addedCustomizations = [];

            foreach ($request->customizations as $customizationData) {
                // Verify the setting allows customization
                $setting = ConfigurationSetting::with('settingType')->find($customizationData['setting_id']);
                if (! $setting || ! $setting->settingType || ! $setting->settingType->for_user_customize) {
                    return response()->json([
                        'message' => 'One or more settings cannot be customized',
                    ], 422);
                }

                // Check for overlapping date ranges for the same setting
                $overlappingCustomization = $this->checkDateOverlap(
                    $user->id,
                    $customizationData['setting_id'],
                    $customizationData['start_date'],
                    $customizationData['end_date'] ?? null
                );

                if ($overlappingCustomization) {
                    return response()->json([
                        'message' => 'Date range overlaps with existing customization for setting: '.$setting->setting_value.
                                   '. Existing period: '.$overlappingCustomization['start_date'].
                                   ' to '.($overlappingCustomization['end_date'] ?? 'ongoing'),
                    ], 422);
                }

                // Create the customization
                $customization = IvaUserCustomize::create([
                    'iva_user_id' => $user->id,
                    'setting_id' => $customizationData['setting_id'],
                    'custom_value' => $customizationData['custom_value'],
                    'start_date' => $customizationData['start_date'] ? Carbon::parse($customizationData['start_date'])->format('Y-m-d') : null,
                    'end_date' => $customizationData['end_date'] ? Carbon::parse($customizationData['end_date'])->format('Y-m-d') : null,
                ]);

                $addedCustomizations[] = $customization->load(['setting.settingType']);

                // Log the activity
                ActivityLogService::log(
                    'add_iva_user_customization',
                    'Added customization for user: '.$user->full_name,
                    [
                        'user_id' => $user->id,
                        'customization_id' => $customization->id,
                        'setting_id' => $customization->setting_id,
                        'setting_name' => $setting->setting_value,
                        'setting_type' => $setting->settingType->name,
                        'custom_value' => $customization->custom_value,
                        'start_date' => $customization->start_date,
                        'end_date' => $customization->end_date,
                    ]
                );
            }

            DB::commit();

            // Get all user customizations
            $allCustomizations = IvaUserCustomize::where('iva_user_id', $user->id)
                ->with(['setting.settingType'])
                ->get();

            return response()->json([
                'message' => 'Customization(s) added successfully',
                'customizations' => $allCustomizations,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to add customization(s)',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove a specific customization.
     */
    public function removeCustomization(Request $request, $userId, $customizationId)
    {
        // Check if user has permission to edit IVA data
        if (! $request->user()->can('edit_iva_data')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $user = IvaUser::findOrFail($userId);
        $customization = IvaUserCustomize::with('setting.settingType')
            ->where('iva_user_id', $userId)
            ->where('id', $customizationId)
            ->firstOrFail();

        DB::beginTransaction();

        try {
            // Log for activity tracking before deletion
            ActivityLogService::log(
                'remove_iva_user_customization',
                'Removed customization for user: '.$user->full_name,
                [
                    'user_id' => $user->id,
                    'customization_id' => $customization->id,
                    'setting_id' => $customization->setting_id,
                    'setting_name' => $customization->setting?->setting_value,
                    'setting_type' => $customization->setting?->settingType?->name,
                    'custom_value' => $customization->custom_value,
                    'start_date' => $customization->start_date,
                    'end_date' => $customization->end_date,
                ]
            );

            // Delete the customization
            $customization->delete();

            DB::commit();

            return response()->json([
                'message' => 'Customization removed successfully',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to remove customization',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get work status options from configuration.
     */
    private function getWorkStatusOptions()
    {
        $workStatusType = ConfigurationSettingType::where('key', 'work_status')->first();

        if (! $workStatusType) {
            return [
                ['value' => 'full-time', 'label' => 'Full Time'],
                ['value' => 'part-time', 'label' => 'Part Time'],
            ];
        }

        return ConfigurationSetting::where('setting_type_id', $workStatusType->id)
            ->where('is_active', true)
            ->orderBy('order')
            ->get()
            ->map(function ($setting) {
                return [
                    'value' => $setting->setting_value,
                    'label' => $setting->description ?: $setting->setting_value,
                ];
            })
            ->toArray();
    }

    private function getTimeDoctorOptions()
    {
        $workStatusType = ConfigurationSettingType::where('key', 'timedoctor_version')->first();

        if (! $workStatusType) {
            return [

            ];
        }

        return ConfigurationSetting::where('setting_type_id', $workStatusType->id)
            ->where('is_active', true)
            ->orderBy('order')
            ->get()
            ->map(function ($setting) {
                return [
                    'value' => $setting->setting_value,
                    'label' => $setting->description ?: $setting->setting_value,
                ];
            })
            ->toArray();
    }

    /**
     * Get valid work status values.
     */
    private function getValidWorkStatuses()
    {
        $workStatusType = ConfigurationSettingType::where('key', 'work_status')->first();

        if (! $workStatusType) {
            return ['full-time', 'part-time'];
        }

        return ConfigurationSetting::where('setting_type_id', $workStatusType->id)
            ->where('is_active', true)
            ->pluck('setting_value')
            ->toArray();
    }

    /**
     * Get manager types from configuration.
     */
    private function getManagerTypes()
    {
        $managerType = ConfigurationSettingType::where('key', 'manager_type')->first();

        if (! $managerType) {
            return [];
        }

        return ConfigurationSetting::where('setting_type_id', $managerType->id)
            ->where('is_active', true)
            ->orderBy('order')
            ->get();
    }

    /**
     * Link TimeDoctor user if email exists in TimeDoctor tables.
     */
    private function linkTimeDoctorUser($user)
    {
        if ($user->timedoctor_version == 1) {
            $tdUser = TimedoctorV1User::where('tm_email', $user->email)
                ->whereNull('iva_user_id')
                ->first();

            if ($tdUser) {
                $tdUser->update(['iva_user_id' => $user->id]);
            }
        } elseif ($user->timedoctor_version == 2) {
            $tdUser = TimedoctorV2User::where('tm_email', $user->email)
                ->whereNull('iva_user_id')
                ->first();

            if ($tdUser) {
                $tdUser->update(['iva_user_id' => $user->id]);
            }
        }
    }

    /**
     * Update TimeDoctor user information.
     */
    private function updateTimeDoctorUserInfo($user)
    {
        if ($user->timedoctor_version == 1 && $user->timedoctorUser) {
            $user->timedoctorUser->update([
                'tm_fullname' => $user->full_name,
                'tm_email' => $user->email,
                'is_active' => $user->is_active,
                'last_synced_at' => now(),
            ]);
        }

        // Re-link if email changed
        $this->linkTimeDoctorUser($user);
    }

    /**
     * Get TimeDoctor sync status for user.
     */
    private function getTimeDoctorSyncStatus($user)
    {
        $status = [
            'is_linked' => false,
            'can_sync' => false,
            'td_version' => $user->timedoctor_version,
            'td_user_id' => null,
            'last_synced' => null,
            'sync_available' => false,
        ];

        if ($user->timedoctor_version == 1) {
            $tdUser = TimedoctorV1User::where('iva_user_id', $user->id)->first();
            if ($tdUser) {
                $status['is_linked'] = true;
                $status['can_sync'] = true;
                $status['td_user_id'] = $tdUser->timedoctor_id;
                $status['last_synced'] = $tdUser->last_synced_at;
                $status['sync_available'] = true;
            }
        } elseif ($user->timedoctor_version == 2) {
            $tdUser = TimedoctorV2User::where('iva_user_id', $user->id)->first();
            if ($tdUser) {
                $status['is_linked'] = true;
                $status['can_sync'] = true;
                $status['td_user_id'] = $tdUser->timedoctor_id;
                $status['last_synced'] = $tdUser->last_synced_at;
                $status['sync_available'] = false;
            }
        }

        return $status;
    }

    /**
     * Get log types from configuration.
     */
    private function getLogTypes()
    {
        $logType = ConfigurationSettingType::where('key', 'iva_logs_type')->first();

        if (! $logType) {
            return [
                ['value' => 'note', 'label' => 'Note'],
                ['value' => 'nad', 'label' => 'NAD'],
                ['value' => 'performance', 'label' => 'Performance'],
            ];
        }

        return ConfigurationSetting::where('setting_type_id', $logType->id)
            ->where('is_active', true)
            ->orderBy('order')
            ->get()
            ->map(function ($setting) {
                return [
                    'value' => $setting->setting_value,
                    'label' => $setting->description ?: $setting->setting_value,
                ];
            })
            ->toArray();
    }

    /**
     * Get valid log type values.
     */
    private function getValidLogTypes()
    {
        $logType = ConfigurationSettingType::where('key', 'iva_logs_type')->first();

        if (! $logType) {
            return ['note', 'nad', 'performance'];
        }

        return ConfigurationSetting::where('setting_type_id', $logType->id)
            ->where('is_active', true)
            ->pluck('setting_value')
            ->toArray();
    }

    /**
     * Update user customizations.
     */
    public function updateCustomization(Request $request, $id, $customizationId)
    {
        // Check if user has permission to edit IVA data
        if (! $request->user()->can('edit_iva_data')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $user = IvaUser::findOrFail($id);
        $customization = IvaUserCustomize::where('iva_user_id', $id)
            ->where('id', $customizationId)
            ->firstOrFail();

        $validator = Validator::make($request->all(), [
            'custom_value' => 'required|string',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Get the setting to verify it allows customization
        $setting = ConfigurationSetting::with('settingType')->find($customization->setting_id);
        if (! $setting || ! $setting->settingType || ! $setting->settingType->for_user_customize) {
            return response()->json([
                'message' => 'This setting cannot be customized',
            ], 422);
        }

        // Check for overlapping date ranges for the same setting (excluding current customization)
        $overlappingCustomization = $this->checkDateOverlap(
            $user->id,
            $customization->setting_id,
            $request->start_date ?? $customization->start_date,
            $request->end_date ?? $customization->end_date,
            $customizationId // Exclude current customization from overlap check
        );

        if ($overlappingCustomization) {
            return response()->json([
                'message' => 'Date range overlaps with existing customization for setting: '.$setting->setting_value.
                           '. Existing period: '.$overlappingCustomization['start_date'].
                           ' to '.($overlappingCustomization['end_date'] ?? 'ongoing'),
            ], 422);
        }

        DB::beginTransaction();

        try {
            $oldValue = $customization->custom_value;
            $oldStartDate = $customization->start_date;
            $oldEndDate = $customization->end_date;

            $customization->update([
                'custom_value' => $request->custom_value,
                'start_date' => $request->start_date ? Carbon::parse($request->start_date)->format('Y-m-d') : null,
                'end_date' => $request->end_date ? Carbon::parse($request->end_date)->format('Y-m-d') : null,
            ]);

            // Log the update
            ActivityLogService::log(
                'update_iva_user_customization',
                'Updated customization for user: '.$user->full_name,
                [
                    'user_id' => $user->id,
                    'customization_id' => $customization->id,
                    'setting_id' => $customization->setting_id,
                    'setting_name' => $setting->setting_value,
                    'old_custom_value' => $oldValue,
                    'new_custom_value' => $request->custom_value,
                    'old_start_date' => $oldStartDate,
                    'new_start_date' => $request->start_date,
                    'old_end_date' => $oldEndDate,
                    'new_end_date' => $request->end_date,
                ]
            );

            DB::commit();

            // Return the updated customization with relationships
            $updatedCustomization = $customization->fresh(['setting.settingType']);

            return response()->json([
                'message' => 'Customization updated successfully',
                'customization' => $updatedCustomization,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to update customization',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Add a log entry for the user
     */
    public function addLog(Request $request, $id)
    {
        // Check if user has permission to edit IVA data
        if (! $request->user()->can('edit_iva_data')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $user = IvaUser::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'log_type' => 'required|string',
            'title' => 'nullable|string|max:255',
            'content' => 'required|string',
            'is_private' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Validate log_type against configuration settings
        $validLogTypes = $this->getValidLogTypes();
        if (! in_array($request->log_type, $validLogTypes)) {
            return response()->json([
                'errors' => ['log_type' => ['Invalid log type selected']],
            ], 422);
        }

        DB::beginTransaction();

        try {
            $log = IvaUserLog::create([
                'iva_user_id' => $user->id,
                'created_by' => request()->user()->id,
                'log_type' => $request->log_type,
                'title' => $request->title,
                'content' => $request->content,
                'is_private' => $request->is_private ?? false,
            ]);

            // Log the activity
            ActivityLogService::log(
                'create_iva_user_log',
                'Added log entry for user: '.$user->full_name,
                [
                    'user_id' => $user->id,
                    'log_id' => $log->id,
                    'log_type' => $request->log_type,
                    'title' => $request->title,
                    'is_private' => $request->is_private ?? false,
                ]
            );

            DB::commit();

            return response()->json([
                'message' => 'Log entry added successfully',
                'log' => $log->load('creator'),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to add log entry',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update a log entry
     */
    public function updateLog(Request $request, $id, $logId)
    {
        // Check if user has permission to edit IVA data
        if (! $request->user()->can('edit_iva_data')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $user = IvaUser::findOrFail($id);
        $log = IvaUserLog::where('iva_user_id', $id)->findOrFail($logId);

        $validator = Validator::make($request->all(), [
            'log_type' => 'required|string',
            'title' => 'nullable|string|max:255',
            'content' => 'required|string',
            'is_private' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Validate log_type against configuration settings
        $validLogTypes = $this->getValidLogTypes();
        if (! in_array($request->log_type, $validLogTypes)) {
            return response()->json([
                'errors' => ['log_type' => ['Invalid log type selected']],
            ], 422);
        }

        DB::beginTransaction();

        try {
            $oldValues = $log->toArray();

            $log->update([
                'log_type' => $request->log_type,
                'title' => $request->title,
                'content' => $request->content,
                'is_private' => $request->is_private ?? false,
            ]);

            // Log the activity
            ActivityLogService::log(
                'update_iva_user_log',
                'Updated log entry for user: '.$user->full_name,
                [
                    'user_id' => $user->id,
                    'log_id' => $log->id,
                    'old_values' => $oldValues,
                    'new_values' => $log->toArray(),
                ]
            );

            DB::commit();

            return response()->json([
                'message' => 'Log entry updated successfully',
                'log' => $log->fresh(['creator']),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to update log entry',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a log entry
     */
    public function deleteLog(Request $request, $id, $logId)
    {
        // Check if user has permission to edit IVA data
        if (! $request->user()->can('edit_iva_data')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $user = IvaUser::findOrFail($id);
        $log = IvaUserLog::where('iva_user_id', $id)->findOrFail($logId);

        DB::beginTransaction();

        try {
            // Log the activity before deletion
            ActivityLogService::log(
                'delete_iva_user_log',
                'Deleted log entry for user: '.$user->full_name,
                [
                    'user_id' => $user->id,
                    'log_id' => $log->id,
                    'log_type' => $log->log_type,
                    'title' => $log->title,
                    'content' => $log->content,
                    'is_private' => $log->is_private,
                    'created_at' => $log->created_at,
                ]
            );

            $log->delete();

            DB::commit();

            return response()->json([
                'message' => 'Log entry deleted successfully',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to delete log entry',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get logs for a user
     */
    public function getLogs($id, Request $request)
    {
        $user = IvaUser::findOrFail($id);

        $query = IvaUserLog::where('iva_user_id', $id)->with('creator');

        // Filter by log type if specified
        if ($request->has('log_type') && ! empty($request->log_type)) {
            $query->where('log_type', $request->log_type);
        }

        // Filter by privacy if specified
        if ($request->has('is_private') && $request->is_private !== null) {
            $query->where('is_private', $request->is_private === 'true' || $request->is_private === '1');
        }

        $logs = $query->orderBy('created_at', 'desc')->paginate(20);

        return response()->json([
            'logs' => $logs,
        ]);
    }

    /**
     * Add a manager to a user
     */
    public function addManager(Request $request, $id)
    {
        // Check if user has permission to edit IVA data
        if (! $request->user()->can('edit_iva_data')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $user = IvaUser::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'manager_id' => 'required|exists:iva_user,id',
            'manager_type_id' => 'required|exists:configuration_settings,id',
            'region_id' => 'required|exists:regions,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Prevent self-management
        if ($user->id == $request->manager_id) {
            return response()->json([
                'message' => 'A user cannot be their own manager',
                'error' => 'Invalid manager assignment',
            ], 422);
        }

        // Check if the user belongs to the specified region
        if ($user->region_id != $request->region_id) {
            return response()->json([
                'message' => 'The user must belong to the same region as the manager assignment',
                'error' => 'Region mismatch',
            ], 422);
        }

        DB::beginTransaction();

        try {
            // Check if this user already has this type of manager in this region
            $existingAssignment = IvaManager::where('iva_id', $user->id)
                ->where('region_id', $request->region_id)
                ->where('manager_type_id', $request->manager_type_id)
                ->first();

            if ($existingAssignment) {
                // Update existing assignment
                $existingAssignment->update([
                    'iva_manager_id' => $request->manager_id,
                ]);
                $message = 'Manager updated successfully';
            } else {
                // Create new assignment
                IvaManager::create([
                    'iva_id' => $user->id,
                    'iva_manager_id' => $request->manager_id,
                    'manager_type_id' => $request->manager_type_id,
                    'region_id' => $request->region_id,
                ]);
                $message = 'Manager assigned successfully';
            }

            // Log the activity
            $manager = IvaUser::find($request->manager_id);
            $managerType = ConfigurationSetting::find($request->manager_type_id);
            $region = Region::find($request->region_id);

            ActivityLogService::log(
                'assign_iva_manager',
                'Assigned '.$manager->full_name.' as '.$managerType->setting_value.' manager to '.$user->full_name.' in region: '.$region->name,
                [
                    'user_id' => $user->id,
                    'manager_id' => $request->manager_id,
                    'manager_type_id' => $request->manager_type_id,
                    'region_id' => $request->region_id,
                ]
            );

            DB::commit();

            // Refresh user with updated managers
            $user = $user->fresh(['managers.manager', 'managers.managerType', 'managers.region']);

            return response()->json([
                'message' => $message,
                'managers' => $user->managers,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to assign manager',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove a manager from a user
     */
    public function removeManager(Request $request, $userId, $managerId)
    {
        // Check if user has permission to edit IVA data
        if (! $request->user()->can('edit_iva_data')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $user = IvaUser::findOrFail($userId);
        $managerAssignment = IvaManager::with(['manager', 'managerType', 'region'])
            ->where('iva_id', $userId)
            ->where('id', $managerId)
            ->firstOrFail();

        DB::beginTransaction();

        try {
            // Get manager details for logging before deletion
            $manager = $managerAssignment->manager;
            $managerType = $managerAssignment->managerType;
            $region = $managerAssignment->region;

            // Log the activity before deletion
            ActivityLogService::log(
                'remove_iva_manager',
                'Removed '.$manager->full_name.' as '.$managerType->setting_value.' manager from '.$user->full_name.' in region: '.$region->name,
                [
                    'user_id' => $user->id,
                    'manager_assignment_id' => $managerAssignment->id,
                    'manager_id' => $manager->id,
                    'manager_name' => $manager->full_name,
                    'manager_email' => $manager->email,
                    'manager_type_id' => $managerType->id,
                    'manager_type' => $managerType->setting_value,
                    'region_id' => $region->id,
                    'region_name' => $region->name,
                ]
            );

            // Delete the assignment
            $managerAssignment->delete();

            DB::commit();

            return response()->json([
                'message' => 'Manager removed successfully',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to remove manager',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get all available users for manager selection excluding the current user
     */
    public function getAvailableManagers($id)
    {
        $availableManagers = IvaUser::where('is_active', true)
            ->where('id', '!=', $id)
            ->get(['id', 'full_name', 'email', 'region_id']);

        return response()->json([
            'managers' => $availableManagers,
        ]);
    }

    /**
     * Check if a date range overlaps with existing customizations for the same setting
     */
    private function checkDateOverlap($userId, $settingId, $startDate, $endDate = null, $excludeCustomizationId = null)
    {
        $query = IvaUserCustomize::where('iva_user_id', $userId)
            ->where('setting_id', $settingId);

        // Exclude current customization when updating
        if ($excludeCustomizationId) {
            $query->where('id', '!=', $excludeCustomizationId);
        }

        $existingCustomizations = $query->get();

        foreach ($existingCustomizations as $existing) {
            $existingStart = $existing->start_date;
            $existingEnd = $existing->end_date;

            // Check for overlap
            if ($this->dateRangesOverlap($startDate, $endDate, $existingStart, $existingEnd)) {
                return [
                    'id' => $existing->id,
                    'start_date' => $existingStart,
                    'end_date' => $existingEnd,
                    'custom_value' => $existing->custom_value,
                ];
            }
        }

        return null;
    }

    /**
     * Check if two date ranges overlap
     */
    private function dateRangesOverlap($start1, $end1, $start2, $end2)
    {
        // Convert to Carbon instances for easier comparison
        $start1 = Carbon::parse($start1);
        $end1 = $end1 ? Carbon::parse($end1) : null;
        $start2 = Carbon::parse($start2);
        $end2 = $end2 ? Carbon::parse($end2) : null;

        // If either range is open-ended (no end date), treat as extending indefinitely
        if (! $end1 && ! $end2) {
            // Both ranges are open-ended, they overlap if start dates are different
            return true;
        }

        if (! $end1) {
            // First range is open-ended, overlaps if start1 <= end2
            return $start1->lte($end2);
        }

        if (! $end2) {
            // Second range is open-ended, overlaps if start2 <= end1
            return $start2->lte($end1);
        }

        // Both ranges have end dates
        // Ranges overlap if: start1 <= end2 AND start2 <= end1
        return $start1->lte($end2) && $start2->lte($end1);
    }

    /**
     * Sync TimeDoctor tasks for all synced users
     */
    private function syncTimeDocTasksForSyncedUsers($syncedUsersData)
    {
        $results = [];

        foreach ($syncedUsersData as $userData) {
            $email = $userData['email'] ?? null;

            if (!$email) {
                continue;
            }

            // Check if user exists in TimeDoctor V1
            $timedoctorUser = TimedoctorV1User::where('tm_email', $email)
                ->whereNotNull('iva_user_id')
                ->where('is_active', true)
                ->first();

            if ($timedoctorUser) {
                // User found in TimeDoctor, sync their tasks
                $taskSyncResult = $this->syncTimeDocSingleUserTasks(
                    $timedoctorUser->timedoctor_id,
                    $email
                );

                $results[] = [
                    'email' => $email,
                    'found_in_timedoctor' => true,
                    'tasks_synced' => $taskSyncResult['tasks_synced'],
                    'sync_success' => $taskSyncResult['success'],
                    'message' => $taskSyncResult['message']
                ];
            } else {
                // User not found in TimeDoctor V1
                $results[] = [
                    'email' => $email,
                    'found_in_timedoctor' => false,
                    'tasks_synced' => 0,
                    'sync_success' => false,
                    'message' => 'User not found in TimeDoctor V1'
                ];
            }
        }

        return $results;
    }

    /**
     * Sync TimeDoctor tasks for a specific user
     */
    private function syncTimeDocSingleUserTasks($timedoctorUserId, $userEmail)
    {
        try {
            // Get company info first
            $companyInfo = $this->timeDoctorService->getCompanyInfo();

            if (!isset($companyInfo['accounts'][0]['company_id'])) {
                return [
                    'success' => false,
                    'message' => 'Could not retrieve company ID from TimeDoctor',
                    'tasks_synced' => 0
                ];
            }

            $companyId = $companyInfo['accounts'][0]['company_id'];

            // Get tasks for this specific user
            $tasksData = $this->timeDoctorService->getTasks($companyId, $timedoctorUserId);

            if (!isset($tasksData['tasks']) || !is_array($tasksData['tasks'])) {
                return [
                    'success' => true,
                    'message' => 'No tasks found for user',
                    'tasks_synced' => 0
                ];
            }

            $syncCount = 0;

            DB::beginTransaction();

            try {
                foreach ($tasksData['tasks'] as $task) {
                    $userListData = [
                        'tId' => $task['task_id'] ?? null,
                        'vId' => 1,
                    ];

                    $existingTask = Task::where('task_name', $task['task_name'])->first();

                    if ($existingTask) {
                        $existingUserList = $existingTask->user_list ?? [];
                        $userExists = false;

                        if (is_array($existingUserList)) {
                            foreach ($existingUserList as $key => $userData) {
                                // Check if this task already exists for this user by comparing tId
                                if (isset($userData['tId']) && $userData['tId'] == ($task['task_id'] ?? null)) {
                                    $existingUserList[$key] = $userListData;
                                    $userExists = true;
                                    break;
                                }
                            }

                            if (!$userExists) {
                                $existingUserList[] = $userListData;
                            }
                        } else {
                            $existingUserList = [$userListData];
                        }

                        $existingTask->update([
                            'is_active' => ($task['status'] === 'Active' || $task['active'] ?? false),
                            'last_synced_at' => now(),
                            'user_list' => $existingUserList,
                        ]);
                    } else {
                        Task::create([
                            'task_name' => $task['task_name'],
                            'user_list' => [$userListData],
                            'is_active' => ($task['status'] === 'Active' || $task['active'] ?? false),
                            'last_synced_at' => now(),
                        ]);
                    }

                    $syncCount++;
                }

                DB::commit();

                return [
                    'success' => true,
                    'message' => "Tasks synced successfully for {$userEmail}",
                    'tasks_synced' => $syncCount
                ];

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (\Exception $e) {
            Log::error('Error syncing TimeDoctor tasks for single user', [
                'user_email' => $userEmail,
                'timedoctor_user_id' => $timedoctorUserId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'Error syncing tasks: ' . $e->getMessage(),
                'tasks_synced' => 0
            ];
        }
    }
}
