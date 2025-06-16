<?php
namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\ConfigurationSetting;
use App\Models\ConfigurationSettingType;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class ConfigurationSettingsController extends Controller
{
    /**
     * Display a listing of the settings, optionally filtered by type.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $query = ConfigurationSetting::with('settingType');
        // dd($query->toSql(), $query->getBindings(), $query);
        // Filter by type if provided
        if ($request->has('type_id') && ! empty($request->type_id)) {
            $query->where('setting_type_id', $request->type_id);
        }
        if ($request->has('type_key') && ! empty($request->type_key)) {
            $query->whereHas('settingType', function ($q) use ($request) {
                $q->where('key', $request->type_key);
            });
        }

        // Get settings and apply sorting if requested
        if ($request->has('sort')) {
            $sortField = $request->input('sort.field', 'order');
            $sortOrder = $request->input('sort.order', 'asc');
            $query->orderBy($sortField, $sortOrder);
        } else {
            $query->orderBy('setting_type_id')->orderBy('order');
        }

        // Get all available types
        $types = ConfigurationSettingType::all()->keyBy('id')->map(function ($type) {
            return $type->name;
        });

        // Get settings
        $settings = $query->get();

        // Group settings by type for the response
        $settingsByType = $settings->groupBy('setting_type_id');

        return response()->json([
            'settings'       => $settings,
            'settingsByType' => $settingsByType,
            'types'          => $types,
        ]);
    }

    /**
     * Store a newly created setting.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'setting_type_id' => 'required|exists:configuration_settings_type,id',
            'setting_value'   => 'required|string|max:255',
            'description'     => 'nullable|string',
            'order'           => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Check if the setting type allows creation
        $settingType = ConfigurationSettingType::find($request->setting_type_id);
        if (! $settingType->allow_create) {
            return response()->json([
                'error' => 'Creating settings of this type is not allowed',
            ], 403);
        }

        // Create the setting
        $setting = ConfigurationSetting::create([
            'setting_type_id' => $request->setting_type_id,
            'setting_value'   => $request->setting_value,
            'description'     => $request->description,
            'is_active'       => true,
            'added_by'        => Auth::user() ? Auth::user()->email : 'System',
            'order'           => $request->order ?? 0,
            'is_system'       => false, // User-created settings can be deleted
        ]);

        // Load the relationship for logging
        $setting->load('settingType');

        // Log the activity using ActivityLogService
        ActivityLogService::logConfigurationCreate($setting);

        return response()->json([
            'message' => 'Setting created successfully',
            'setting' => $setting,
        ], 201);
    }

    /**
     * Display the specified setting.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        $setting = ConfigurationSetting::with('settingType')->findOrFail($id);

        return response()->json([
            'setting' => $setting,
        ]);
    }

    /**
     * Update the specified setting.
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        $setting = ConfigurationSetting::with('settingType')->findOrFail($id);

        // Check if editing is allowed for this type
        if (! $setting->settingType->allow_edit) {
            return response()->json([
                'error' => 'Editing settings of this type is not allowed',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'setting_value' => 'required|string|max:255',
            'description'   => 'nullable|string',
            'order'         => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Save old values for logging
        $oldValues = $setting->toArray();

        // Update the setting
        $setting->update([
            'setting_value' => $request->setting_value,
            'description'   => $request->description,
            'order'         => $request->order ?? $setting->order,
        ]);

        // Log the activity using ActivityLogService
        ActivityLogService::logConfigurationUpdate($setting, $oldValues);

        return response()->json([
            'message' => 'Setting updated successfully',
            'setting' => $setting,
        ]);
    }

    /**
     * Toggle the active status of the setting.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function toggleStatus($id)
    {
        $setting = ConfigurationSetting::with('settingType')->findOrFail($id);

        // Save old status for logging
        $oldStatus = $setting->is_active;

        // Toggle the status
        $setting->is_active = ! $setting->is_active;
        $setting->save();

        // Log the activity using ActivityLogService
        ActivityLogService::logConfigurationStatusToggle($setting, $oldStatus);

        return response()->json([
            'message' => 'Setting status updated successfully',
            'setting' => $setting,
        ]);
    }

    /**
     * Remove the specified setting.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        $setting = ConfigurationSetting::with('settingType')->findOrFail($id);

        // Check if it's a system setting
        if ($setting->is_system) {
            return response()->json([
                'message' => 'System settings cannot be deleted',
                'error'   => 'Forbidden action',
            ], 403);
        }

        // Check if the setting type allows deletion
        if (! $setting->settingType->allow_delete) {
            return response()->json([
                'message' => 'Deleting settings of this type is not allowed',
                'error'   => 'Forbidden action',
            ], 403);
        }

        // Log the activity before deletion
        ActivityLogService::logConfigurationDelete($setting);

        // Delete the setting
        $setting->delete();

        return response()->json([
            'message' => 'Setting deleted successfully',
        ]);
    }

    /**
     * Get all settings types.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getTypes()
    {
        $types = ConfigurationSettingType::orderBy('setting_category')->orderBy('name')->get();

        return response()->json([
            'types' => $types,
        ]);
    }

    /**
     * Get activity logs related to settings
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getActivityLogs(Request $request)
    {
        $query = ActivityLog::query();

        // Filter by configuration-related actions
        $configActions = ['create_config', 'update_config', 'delete_config', 'toggle_status', 'create_config_type', 'update_config_type', 'delete_config_type'];
        $query->whereIn('action', $configActions);

        // Add pagination
        $perPage      = $request->input('per_page', 10);
        $activityLogs = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'logs' => $activityLogs,
        ]);
    }
}
