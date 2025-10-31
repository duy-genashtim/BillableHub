<?php

namespace App\Http\Controllers;

use App\Models\ConfigurationSetting;
use App\Models\ReportCategory;
use App\Models\Task;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ReportCategoryController extends Controller
{
    /**
     * Display a listing of the categories.
     */
    public function index()
    {
        $categories = ReportCategory::with('categoryType')
            ->withCount('taskCategories as tasks_count')
            ->get();

        // Get category types for filter dropdown
        $categoryTypes = ConfigurationSetting::whereHas('settingType', function ($query) {
            $query->where('setting_category', 'report-cat');
        })->where('is_active', true)->get();

        // ActivityLogService::log(
        //     'view_categories_list',
        //     'Viewed report categories list',
        //     [
        //         'total_categories' => $categories->count(),
        //         'module'           => 'task_categories',
        //     ]
        // );

        return response()->json([
            'categories' => $categories,
            'categoryTypes' => $categoryTypes,
        ]);
    }

    /**
     * Store a newly created category in storage.
     */
    public function store(Request $request)
    {
        // Check if user has permission to edit IVA data
        if (! $request->user()->can('edit_iva_data')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'cat_name' => 'required|string|max:255|unique:report_categories',
            'cat_description' => 'nullable|string',
            'is_active' => 'boolean',
            'category_order' => 'nullable|integer|min:1',
            'category_type' => 'required|exists:configuration_settings,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $category = ReportCategory::create($validator->validated());

        // Load the category type relationship
        $category->load('categoryType');

        ActivityLogService::log(
            'create_category',
            'Created new report category: '.$category->cat_name,
            [
                'category_id' => $category->id,
                'category_name' => $category->cat_name,
                'category_type' => $category->categoryType?->setting_value,
                'is_active' => $category->is_active,
                'category_order' => $category->category_order,
                'module' => 'task_categories',
            ]
        );

        return response()->json([
            'message' => 'Category created successfully',
            'category' => $category,
        ], 201);
    }

    /**
     * Display the specified category.
     */
    public function show($id)
    {
        $category = ReportCategory::with(['categoryType'])->findOrFail($id);

        // Get tasks associated with this category
        $taskIds = $category->taskCategories()->pluck('task_id');
        $tasks = Task::whereIn('id', $taskIds)->get();

        $categoryData = $category->toArray();
        $categoryData['tasks'] = $tasks;

        ActivityLogService::log(
            'view_category',
            'Viewed report category: '.$category->cat_name,
            [
                'category_id' => $id,
                'category_name' => $category->cat_name,
                'tasks_count' => $tasks->count(),
                'module' => 'task_categories',
            ]
        );

        return response()->json(['category' => $categoryData]);
    }

    /**
     * Update the specified category in storage.
     */
    public function update(Request $request, $id)
    {
        // Check if user has permission to edit IVA data
        if (! $request->user()->can('edit_iva_data')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $category = ReportCategory::findOrFail($id);
        $oldValues = $category->toArray();

        $validator = Validator::make($request->all(), [
            'cat_name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('report_categories')->ignore($id),
            ],
            'cat_description' => 'nullable|string',
            'is_active' => 'boolean',
            'category_order' => 'nullable|integer|min:1',
            'category_type' => 'required|exists:configuration_settings,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $category->update($validator->validated());

        // Load the category type relationship
        $category->load('categoryType');

        ActivityLogService::log(
            'update_category',
            'Updated report category: '.$category->cat_name,
            [
                'category_id' => $id,
                'category_name' => $category->cat_name,
                'old_values' => $oldValues,
                'new_values' => $category->toArray(),
                'module' => 'task_categories',
            ]
        );

        return response()->json([
            'message' => 'Category updated successfully',
            'category' => $category,
        ]);
    }

    /**
     * Remove the specified category from storage.
     */
    public function destroy(Request $request, $id)
    {
        // Check if user has permission to edit IVA data
        if (! $request->user()->can('edit_iva_data')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $category = ReportCategory::findOrFail($id);

        // Check if category can be deleted (no tasks assigned)
        if (! $category->canBeDeleted()) {
            return response()->json([
                'error' => 'Cannot delete category that has tasks assigned to it',
            ], 422);
        }

        $categoryName = $category->cat_name;
        $categoryData = $category->toArray();

        $category->delete();

        ActivityLogService::log(
            'delete_category',
            'Deleted report category: '.$categoryName,
            [
                'category_id' => $id,
                'category_name' => $categoryName,
                'deleted_data' => $categoryData,
                'module' => 'task_categories',
            ]
        );

        return response()->json([
            'message' => 'Category deleted successfully',
        ]);
    }

    /**
     * Toggle category status (activate/deactivate).
     */
    public function toggleStatus(Request $request, $id)
    {
        // Check if user has permission to edit IVA data
        if (! $request->user()->can('edit_iva_data')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $category = ReportCategory::findOrFail($id);
        $oldStatus = $category->is_active;

        $category->is_active = ! $category->is_active;
        $category->save();

        ActivityLogService::log(
            'toggle_category_status',
            'Toggled status of category: '.$category->cat_name,
            [
                'category_id' => $id,
                'category_name' => $category->cat_name,
                'old_status' => $oldStatus,
                'new_status' => $category->is_active,
                'module' => 'task_categories',
            ]
        );

        return response()->json([
            'message' => 'Category status updated successfully',
            'category' => $category,
        ]);
    }

    /**
     * Get all tasks available for assignment to categories.
     */
    public function getAvailableTasks()
    {
        $tasks = Task::active()->get();

        ActivityLogService::log(
            'view_available_tasks',
            'Viewed available tasks for category assignment',
            [
                'total_tasks' => $tasks->count(),
                'module' => 'task_categories',
            ]
        );

        return response()->json(['tasks' => $tasks]);
    }

    /**
     * Add tasks to a category.
     */
    public function assignTasks(Request $request, $id)
    {
        // Check if user has permission to edit IVA data
        if (! $request->user()->can('edit_iva_data')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'task_ids' => 'required|array',
            'task_ids.*' => 'exists:tasks,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $category = ReportCategory::findOrFail($id);

        // Get existing task IDs for this category
        $existingTaskIds = $category->taskCategories()->pluck('task_id')->toArray();

        // Filter out already assigned tasks
        $newTaskIds = array_diff($request->task_ids, $existingTaskIds);

        if (empty($newTaskIds)) {
            return response()->json([
                'message' => 'All selected tasks are already assigned to this category',
            ]);
        }

        // Create new assignments
        $assignments = [];
        foreach ($newTaskIds as $taskId) {
            $assignments[] = [
                'task_id' => $taskId,
                'cat_id' => $id,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        \DB::table('task_report_categories')->insert($assignments);

        $assignedTasks = Task::whereIn('id', $newTaskIds)->pluck('task_name')->toArray();

        ActivityLogService::log(
            'assign_tasks_to_category',
            'Assigned tasks to category: '.$category->cat_name,
            [
                'category_id' => $id,
                'category_name' => $category->cat_name,
                'task_ids' => $newTaskIds,
                'task_names' => $assignedTasks,
                'task_count' => count($newTaskIds),
                'module' => 'task_categories',
            ]
        );

        return response()->json([
            'message' => 'Tasks assigned to category successfully',
            'assigned_count' => count($newTaskIds),
        ]);
    }

    /**
     * Remove tasks from a category.
     */
    public function removeTasks(Request $request, $id)
    {
        // Check if user has permission to edit IVA data
        if (! $request->user()->can('edit_iva_data')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'task_ids' => 'required|array',
            'task_ids.*' => 'exists:tasks,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $category = ReportCategory::findOrFail($id);

        // Remove the assignments
        $removedCount = $category->taskCategories()
            ->whereIn('task_id', $request->task_ids)
            ->delete();

        $removedTasks = Task::whereIn('id', $request->task_ids)->pluck('task_name')->toArray();

        ActivityLogService::log(
            'remove_tasks_from_category',
            'Removed tasks from category: '.$category->cat_name,
            [
                'category_id' => $id,
                'category_name' => $category->cat_name,
                'task_ids' => $request->task_ids,
                'task_names' => $removedTasks,
                'removed_count' => $removedCount,
                'module' => 'task_categories',
            ]
        );

        return response()->json([
            'message' => 'Tasks removed from category successfully',
            'removed_count' => $removedCount,
        ]);
    }

    /**
     * Get category types for dropdown.
     */
    public function getCategoryTypes()
    {
        $categoryTypes = ConfigurationSetting::whereHas('settingType', function ($query) {
            $query->where('setting_category', 'report-cat');
        })->where('is_active', true)->get();

        return response()->json(['categoryTypes' => $categoryTypes]);
    }
}
