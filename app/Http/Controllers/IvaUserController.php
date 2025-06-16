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
use App\Models\TimedoctorV1User;
use App\Models\TimedoctorV2User;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class IvaUserController extends Controller
{
    /**
     * Display a listing of IVA users.
     */
    public function index(Request $request)
    {
        $perPage = $request->input('per_page', config('constants.pagination.iva_users_per_page'));
        $perPage = min($perPage, config('constants.pagination.max_per_page'));

        $query = IvaUser::with(['region', 'cohort', 'timedoctorUser']);

        // Apply filters if provided
        if ($request->has('region_id') && ! empty($request->region_id)) {
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
                    ->orWhere('email', 'LIKE', "%{$search}%");
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
        ActivityLogService::log(
            'view_iva_users_list',
            'Viewed IVA users list',
            ['total_users' => $users->total()]
        );

        return response()->json([
            'users'               => $users,
            'regions'             => $regions,
            'cohorts'             => $cohorts,
            'work_status_options' => $workStatusOptions,
            'timedoctor_versions' => $timedoctorOptions,
        ]);
    }

    /**
     * Store a newly created IVA user.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'full_name'          => 'required|string|max:255',
            'email'              => 'required|email|unique:iva_user,email',
            'hire_date'          => 'nullable|date',
            'region_id'          => 'nullable|exists:regions,id',
            'cohort_id'          => 'nullable|exists:cohorts,id',
            'work_status'        => ['nullable', 'string'],
            'timedoctor_version' => 'required|integer|in:1,2',
            'is_active'          => 'boolean',
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
                'full_name'          => $request->full_name,
                'email'              => $request->email,
                'hire_date'          => $request->hire_date,
                'region_id'          => $request->region_id,
                'cohort_id'          => $request->cohort_id,
                'work_status'        => $request->work_status,
                'timedoctor_version' => $request->timedoctor_version,
                'is_active'          => $request->is_active ?? true,
            ]);

            // Check if email exists in TimeDoctor tables and link
            $this->linkTimeDoctorUser($user);

            // Log the creation
            ActivityLogService::log(
                'create_iva_user',
                'Created new IVA user: ' . $user->full_name,
                $user->toArray()
            );

            DB::commit();

            return response()->json([
                'message' => 'User created successfully',
                'user'    => $user->load(['region', 'cohort', 'timedoctorUser']),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to create user',
                'error'   => $e->getMessage(),
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
            'logs'       => function ($query) {
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
        ActivityLogService::log(
            'view_iva_user',
            'Viewed IVA user details: ' . $user->full_name,
            ['user_id' => $id]
        );

        return response()->json([
            'user'                 => $user,
            'regions'              => $regions,
            'cohorts'              => $cohorts,
            'timedoctorVersions'   => $timedoctorVersions,
            'workStatusOptions'    => $workStatusOptions,
            'customizationTypes'   => $customizationTypes,
            'managerTypes'         => $managerTypes,
            'logTypes'             => $logTypes,
            'timeDoctorSyncStatus' => $timeDoctorSyncStatus,
        ]);
    }

    /**
     * Update the specified IVA user.
     */
    public function update(Request $request, $id)
    {
        $user = IvaUser::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'full_name'                             => 'required|string|max:255',
            'email'                                 => [
                'required',
                'email',
                Rule::unique('iva_user')->ignore($id),
            ],
            'hire_date'                             => 'nullable|date',
            'end_date'                              => 'nullable|date',
            'region_id'                             => 'nullable|exists:regions,id',
            'cohort_id'                             => 'nullable|exists:cohorts,id',
            'work_status'                           => ['nullable', 'string'],
            'timedoctor_version'                    => 'required|integer|in:1,2',
            'is_active'                             => 'boolean',
            'region_change_info.reason'             => 'required_if:region_id,!' . $user->region_id . '|string|nullable',
            'region_change_info.effectiveDate'      => 'required_if:region_id,!' . $user->region_id . '|date|nullable',
            'cohort_change_info.reason'             => 'required_if:cohort_id,!' . $user->cohort_id . '|string|nullable',
            'cohort_change_info.effectiveDate'      => 'required_if:cohort_id,!' . $user->cohort_id . '|date|nullable',
            'work_status_change_info.reason'        => 'required_if:work_status,!' . $user->work_status . '|string|nullable',
            'work_status_change_info.effectiveDate' => 'required_if:work_status,!' . $user->work_status . '|date|nullable',
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
            $user->email     = $request->email;
            $user->hire_date = $request->hire_date;
            $user->end_date  = $request->end_date;

            // Track changes to specific fields that need changelog entries
            $changedFields = [];

            // For region changes
            if ($user->region_id != $request->region_id) {
                $changedFields['region'] = [
                    'old'            => $user->region_id,
                    'new'            => $request->region_id,
                    'reason'         => $request->region_change_info['reason'] ?? 'Region updated',
                    'effective_date' => $request->region_change_info['effectiveDate'] ?? now(),
                ];
                $user->region_id = $request->region_id;
            }

            // For cohort changes
            if ($user->cohort_id != $request->cohort_id) {
                $changedFields['cohort'] = [
                    'old'            => $user->cohort_id,
                    'new'            => $request->cohort_id,
                    'reason'         => $request->cohort_change_info['reason'] ?? 'Cohort updated',
                    'effective_date' => $request->cohort_change_info['effectiveDate'] ?? now(),
                ];
                $user->cohort_id = $request->cohort_id;
            }

            // For work status changes
            if ($user->work_status != $request->work_status) {
                $changedFields['work_status'] = [
                    'old'            => $user->work_status,
                    'new'            => $request->work_status,
                    'reason'         => $request->work_status_change_info['reason'] ?? 'Work status updated',
                    'effective_date' => $request->work_status_change_info['effectiveDate'] ?? now(),
                ];
                $user->work_status = $request->work_status;
            }

            // For active status changes
            if ($user->is_active != $request->is_active) {
                $changedFields['is_active'] = [
                    'old'            => $user->is_active,
                    'new'            => $request->is_active,
                    'reason'         => 'Status updated',
                    'effective_date' => now(),
                ];
                $user->is_active = $request->is_active;
            }

            $user->timedoctor_version = $request->timedoctor_version;
            $user->save();

            // Update TimeDoctor user info if exists and re-link if needed
            $this->updateTimeDoctorUserInfo($user);

            // Create changelog entries for tracked fields
            foreach ($changedFields as $field => $values) {
                IvaUserChangelog::create([
                    'iva_user_id'      => $user->id,
                    'field_changed'    => $field,
                    'old_value'        => json_encode($values['old']),
                    'new_value'        => json_encode($values['new']),
                    'change_reason'    => $values['reason'],
                    'changed_by_name'  => request()->user() ? request()->user()->name : 'System',
                    'changed_by_email' => request()->user() ? request()->user()->email : 'system@example.com',
                    'effective_date'   => $values['effective_date'],
                ]);
            }

            // Log the activity
            ActivityLogService::log(
                'update_iva_user',
                'Updated IVA user: ' . $user->full_name,
                [
                    'old' => $oldValues,
                    'new' => $user->toArray(),
                ]
            );

            DB::commit();

            return response()->json([
                'message' => 'User updated successfully',
                'user'    => $user->fresh(['region', 'cohort', 'timedoctorUser', 'managers.manager', 'managers.managerType']),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to update user',
                'error'   => $e->getMessage(),
            ], 500);
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
        $cohort   = Cohort::find($cohortId);

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
                $oldValue                     = json_decode($changelog->old_value, true);
                $newValue                     = json_decode($changelog->new_value, true);
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
        $region   = Region::find($regionId);

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
     * Remove a specific customization.
     */
    public function removeCustomization($userId, $customizationId)
    {
        $user          = IvaUser::findOrFail($userId);
        $customization = IvaUserCustomize::with('setting.settingType')
            ->where('iva_user_id', $userId)
            ->where('id', $customizationId)
            ->firstOrFail();

        DB::beginTransaction();

        try {
            // Log for activity tracking before deletion
            ActivityLogService::log(
                'remove_iva_user_customization',
                'Removed customization for user: ' . $user->full_name,
                [
                    'user_id'          => $user->id,
                    'customization_id' => $customization->id,
                    'setting_id'       => $customization->setting_id,
                    'setting_name'     => $customization->setting?->setting_value,
                    'setting_type'     => $customization->setting?->settingType?->name,
                    'custom_value'     => $customization->custom_value,
                    'start_date'       => $customization->start_date,
                    'end_date'         => $customization->end_date,
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
                'error'   => $e->getMessage(),
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
                'tm_fullname'    => $user->full_name,
                'tm_email'       => $user->email,
                'is_active'      => $user->is_active,
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
            'is_linked'      => false,
            'can_sync'       => false,
            'td_version'     => $user->timedoctor_version,
            'td_user_id'     => null,
            'last_synced'    => null,
            'sync_available' => false,
        ];

        if ($user->timedoctor_version == 1) {
            $tdUser = TimedoctorV1User::where('iva_user_id', $user->id)->first();
            if ($tdUser) {
                $status['is_linked']      = true;
                $status['can_sync']       = true;
                $status['td_user_id']     = $tdUser->timedoctor_id;
                $status['last_synced']    = $tdUser->last_synced_at;
                $status['sync_available'] = true;
            }
        } elseif ($user->timedoctor_version == 2) {
            $tdUser = TimedoctorV2User::where('iva_user_id', $user->id)->first();
            if ($tdUser) {
                $status['is_linked']      = true;
                $status['can_sync']       = false; // V2 sync on hold
                $status['td_user_id']     = $tdUser->timedoctor_id;
                $status['last_synced']    = $tdUser->last_synced_at;
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
    public function updateCustomizations(Request $request, $id)
    {
        $user = IvaUser::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'customizations'                => 'required|array',
            'customizations.*.setting_id'   => 'required|exists:configuration_settings,id',
            'customizations.*.custom_value' => 'required|string',
            'customizations.*.start_date'   => 'nullable|date',
            'customizations.*.end_date'     => 'nullable|date|after_or_equal:customizations.*.start_date',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();

        try {
            // Process each customization
            foreach ($request->customizations as $customization) {
                // Get the setting to check if customization is allowed
                $setting = ConfigurationSetting::with('settingType')->find($customization['setting_id']);
                if (! $setting || ! $setting->settingType || ! $setting->settingType->for_user_customize) {
                    continue;
                }

                // Check if customization already exists for this specific setting
                $existingCustomization = IvaUserCustomize::where('iva_user_id', $user->id)
                    ->where('setting_id', $customization['setting_id'])
                    ->first();

                if ($existingCustomization) {
                    // Update existing
                    $oldValue = $existingCustomization->custom_value;
                    $existingCustomization->update([
                        'custom_value' => $customization['custom_value'],
                        'start_date'   => $customization['start_date'] ?? null,
                        'end_date'     => $customization['end_date'] ?? null,
                    ]);

                    // Log the update
                    ActivityLogService::log(
                        'update_iva_user_customization',
                        'Updated customization for user: ' . $user->full_name,
                        [
                            'user_id'      => $user->id,
                            'setting_id'   => $customization['setting_id'],
                            'setting_name' => $setting->setting_value,
                            'old_value'    => $oldValue,
                            'new_value'    => $customization['custom_value'],
                            'start_date'   => $customization['start_date'] ?? null,
                            'end_date'     => $customization['end_date'] ?? null,
                        ]
                    );
                } else {
                    // Create new
                    $newCustomization = IvaUserCustomize::create([
                        'iva_user_id'  => $user->id,
                        'setting_id'   => $customization['setting_id'],
                        'custom_value' => $customization['custom_value'],
                        'start_date'   => $customization['start_date'] ?? null,
                        'end_date'     => $customization['end_date'] ?? null,
                    ]);

                    // Log the creation
                    ActivityLogService::log(
                        'create_iva_user_customization',
                        'Added customization for user: ' . $user->full_name,
                        [
                            'user_id'          => $user->id,
                            'customization_id' => $newCustomization->id,
                            'setting_id'       => $customization['setting_id'],
                            'setting_name'     => $setting->setting_value,
                            'custom_value'     => $customization['custom_value'],
                            'start_date'       => $customization['start_date'] ?? null,
                            'end_date'         => $customization['end_date'] ?? null,
                        ]
                    );
                }
            }

            DB::commit();

            // Refresh user with updated customizations
            $user = $user->fresh(['customizations.setting.settingType']);

            return response()->json([
                'message'        => 'User customizations updated successfully',
                'customizations' => $user->customizations,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to update user customizations',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Add a log entry for the user
     */
    public function addLog(Request $request, $id)
    {
        $user = IvaUser::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'log_type'   => 'required|string',
            'title'      => 'nullable|string|max:255',
            'content'    => 'required|string',
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
                'created_by'  => request()->user()->id,
                'log_type'    => $request->log_type,
                'title'       => $request->title,
                'content'     => $request->content,
                'is_private'  => $request->is_private ?? false,
            ]);

            // Log the activity
            ActivityLogService::log(
                'create_iva_user_log',
                'Added log entry for user: ' . $user->full_name,
                [
                    'user_id'    => $user->id,
                    'log_id'     => $log->id,
                    'log_type'   => $request->log_type,
                    'title'      => $request->title,
                    'is_private' => $request->is_private ?? false,
                ]
            );

            DB::commit();

            return response()->json([
                'message' => 'Log entry added successfully',
                'log'     => $log->load('creator'),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to add log entry',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update a log entry
     */
    public function updateLog(Request $request, $id, $logId)
    {
        $user = IvaUser::findOrFail($id);
        $log  = IvaUserLog::where('iva_user_id', $id)->findOrFail($logId);

        $validator = Validator::make($request->all(), [
            'log_type'   => 'required|string',
            'title'      => 'nullable|string|max:255',
            'content'    => 'required|string',
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
                'log_type'   => $request->log_type,
                'title'      => $request->title,
                'content'    => $request->content,
                'is_private' => $request->is_private ?? false,
            ]);

            // Log the activity
            ActivityLogService::log(
                'update_iva_user_log',
                'Updated log entry for user: ' . $user->full_name,
                [
                    'user_id'    => $user->id,
                    'log_id'     => $log->id,
                    'old_values' => $oldValues,
                    'new_values' => $log->toArray(),
                ]
            );

            DB::commit();

            return response()->json([
                'message' => 'Log entry updated successfully',
                'log'     => $log->fresh(['creator']),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to update log entry',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a log entry
     */
    public function deleteLog($id, $logId)
    {
        $user = IvaUser::findOrFail($id);
        $log  = IvaUserLog::where('iva_user_id', $id)->findOrFail($logId);

        DB::beginTransaction();

        try {
            // Log the activity before deletion
            ActivityLogService::log(
                'delete_iva_user_log',
                'Deleted log entry for user: ' . $user->full_name,
                [
                    'user_id'    => $user->id,
                    'log_id'     => $log->id,
                    'log_type'   => $log->log_type,
                    'title'      => $log->title,
                    'content'    => $log->content,
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
                'error'   => $e->getMessage(),
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
        $user = IvaUser::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'manager_id'      => 'required|exists:iva_user,id',
            'manager_type_id' => 'required|exists:configuration_settings,id',
            'region_id'       => 'required|exists:regions,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Prevent self-management
        if ($user->id == $request->manager_id) {
            return response()->json([
                'message' => 'A user cannot be their own manager',
                'error'   => 'Invalid manager assignment',
            ], 422);
        }

        // Check if the user belongs to the specified region
        if ($user->region_id != $request->region_id) {
            return response()->json([
                'message' => 'The user must belong to the same region as the manager assignment',
                'error'   => 'Region mismatch',
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
                    'iva_id'          => $user->id,
                    'iva_manager_id'  => $request->manager_id,
                    'manager_type_id' => $request->manager_type_id,
                    'region_id'       => $request->region_id,
                ]);
                $message = 'Manager assigned successfully';
            }

            // Log the activity
            $manager     = IvaUser::find($request->manager_id);
            $managerType = ConfigurationSetting::find($request->manager_type_id);
            $region      = Region::find($request->region_id);

            ActivityLogService::log(
                'assign_iva_manager',
                'Assigned ' . $manager->full_name . ' as ' . $managerType->setting_value . ' manager to ' . $user->full_name . ' in region: ' . $region->name,
                [
                    'user_id'         => $user->id,
                    'manager_id'      => $request->manager_id,
                    'manager_type_id' => $request->manager_type_id,
                    'region_id'       => $request->region_id,
                ]
            );

            DB::commit();

            // Refresh user with updated managers
            $user = $user->fresh(['managers.manager', 'managers.managerType', 'managers.region']);

            return response()->json([
                'message'  => $message,
                'managers' => $user->managers,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to assign manager',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove a manager from a user
     */
    public function removeManager($userId, $managerId)
    {
        $user              = IvaUser::findOrFail($userId);
        $managerAssignment = IvaManager::with(['manager', 'managerType', 'region'])
            ->where('iva_id', $userId)
            ->where('id', $managerId)
            ->firstOrFail();

        DB::beginTransaction();

        try {
            // Get manager details for logging before deletion
            $manager     = $managerAssignment->manager;
            $managerType = $managerAssignment->managerType;
            $region      = $managerAssignment->region;

            // Log the activity before deletion
            ActivityLogService::log(
                'remove_iva_manager',
                'Removed ' . $manager->full_name . ' as ' . $managerType->setting_value . ' manager from ' . $user->full_name . ' in region: ' . $region->name,
                [
                    'user_id'               => $user->id,
                    'manager_assignment_id' => $managerAssignment->id,
                    'manager_id'            => $manager->id,
                    'manager_name'          => $manager->full_name,
                    'manager_email'         => $manager->email,
                    'manager_type_id'       => $managerType->id,
                    'manager_type'          => $managerType->setting_value,
                    'region_id'             => $region->id,
                    'region_name'           => $region->name,
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
                'error'   => $e->getMessage(),
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
}
