<?php

namespace App\Http\Controllers;

use App\Models\ConfigurationSettingType;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ConfigurationSettingTypeController extends Controller
{
    /**
     * Display a listing of the setting types.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $query = ConfigurationSettingType::query();

        // Filter by category if provided
        if ($request->has('category')) {
            $query->where('setting_category', $request->category);
        }

        $types = $query->orderBy('setting_category')->orderBy('name')->get();

        return response()->json([
            'types' => $types,
        ]);
    }

    /**
     * Store a newly created setting type.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'key' => 'required|string|max:255|unique:configuration_settings_type,key',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'setting_category' => ['required', 'string', Rule::in(['site', 'user', 'report', 'report-time', 'report-cat', 'system', 'other'])],
            'for_user_customize' => 'boolean',
            'allow_edit' => 'boolean',
            'allow_delete' => 'boolean',
            'allow_create' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Create the setting type
        $settingType = ConfigurationSettingType::create([
            'key' => $request->key,
            'name' => $request->name,
            'description' => $request->description,
            'setting_category' => $request->setting_category,
            'for_user_customize' => $request->for_user_customize ?? false,
            'allow_edit' => $request->allow_edit ?? true,
            'allow_delete' => $request->allow_delete ?? true,
            'allow_create' => $request->allow_create ?? true,
        ]);

        // Log the activity using ActivityLogService
        ActivityLogService::logConfigurationTypeCreate($settingType);

        return response()->json([
            'message' => 'Setting type created successfully',
            'settingType' => $settingType,
        ], 201);
    }

    /**
     * Display the specified setting type.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        $settingType = ConfigurationSettingType::findOrFail($id);

        return response()->json([
            'settingType' => $settingType,
        ]);
    }

    /**
     * Update the specified setting type.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        $settingType = ConfigurationSettingType::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'key' => ['required', 'string', 'max:255', Rule::unique('configuration_settings_type', 'key')->ignore($id)],
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'setting_category' => ['required', 'string', Rule::in(['site', 'user', 'report', 'report-time', 'report-cat', 'system', 'other'])],
            'for_user_customize' => 'boolean',
            'allow_edit' => 'boolean',
            'allow_delete' => 'boolean',
            'allow_create' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Save old values for logging
        $oldValues = $settingType->toArray();

        // Update the setting type
        $settingType->update([
            'key' => $request->key,
            'name' => $request->name,
            'description' => $request->description,
            'setting_category' => $request->setting_category,
            'for_user_customize' => $request->for_user_customize ?? $settingType->for_user_customize,
            'allow_edit' => $request->allow_edit ?? $settingType->allow_edit,
            'allow_delete' => $request->allow_delete ?? $settingType->allow_delete,
            'allow_create' => $request->allow_create ?? $settingType->allow_create,
        ]);

        // Log the activity using ActivityLogService
        ActivityLogService::logConfigurationTypeUpdate($settingType, $oldValues);

        return response()->json([
            'message' => 'Setting type updated successfully',
            'settingType' => $settingType,
        ]);
    }

    /**
     * Remove the specified setting type.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        $settingType = ConfigurationSettingType::findOrFail($id);

        // Check if there are any settings of this type
        if ($settingType->settings()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete setting type with existing settings',
                'error' => 'Forbidden action',
            ], 403);
        }

        // Log the activity before deletion
        ActivityLogService::logConfigurationTypeDelete($settingType);

        // Delete the setting type
        $settingType->delete();

        return response()->json([
            'message' => 'Setting type deleted successfully',
        ]);
    }
}
