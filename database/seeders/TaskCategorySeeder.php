<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TaskCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Check if tasks table has data
        $taskCount = DB::table('tasks')->count();

        if ($taskCount === 0) {
            $this->command->info('No tasks found in tasks table. Seeder will not run.');
            return;
        }

        $this->command->info("Found {$taskCount} tasks. Starting category seeding...");

        // Get category type IDs for billable and non-billable
        $billableTypeId = DB::table('configuration_settings')
            ->where('setting_value', 'billable')
            ->value('id');

        $nonBillableTypeId = DB::table('configuration_settings')
            ->where('setting_value', 'non-billable')
            ->value('id');

        if (! $billableTypeId || ! $nonBillableTypeId) {
            $this->command->error('Could not find billable/non-billable configuration settings. Please ensure they exist.');
            return;
        }

        // Define categories and their tasks
        $categories = [
            [
                'name'    => 'Actual Non-Billable Hours',
                'type'    => 'non-billable',
                'type_id' => $nonBillableTypeId,
                'tasks'   => [
                    'On Department Call',
                    'Genashtim Training',
                    'G-Tribe Meeting',
                    'Touchbase Meetings',
                    'ESG Consultancy',
                ],
            ],
            [
                'name'    => 'Administrative work',
                'type'    => 'billable',
                'type_id' => $billableTypeId,
                'tasks'   => [
                    '[Administrative work] Administrative activities (Slack, Office Hours, 1-on-1 Sessions with Partnership Managers and Regional Team Leads, Pipeline Management, Meetings with B Lab Global)',
                ],
            ],
            [
                'name'    => 'Evaluation work',
                'type'    => 'billable',
                'type_id' => $billableTypeId,
                'tasks'   => [
                    '[Evaluation work] Evaluation Standard Approach',
                ],
            ],
            [
                'name'    => 'Support services',
                'type'    => 'billable',
                'type_id' => $billableTypeId,
                'tasks'   => [
                    '[Support services] Risk Review Support',
                    '[Support services] Background Check (BGC) and Transparent Documents Support',
                ],
            ],
            [
                'name'    => 'Training',
                'type'    => 'billable',
                'type_id' => $billableTypeId,
                'tasks'   => [
                    '[Training] B Lab Training (Thinkific, live Q&A sessions, B Lab required webinars)',
                ],
            ],
            [
                'name'    => 'Verification work - Others',
                'type'    => 'billable',
                'type_id' => $billableTypeId,
                'tasks'   => [
                    '[Verification work] Medium Enterprise Approach (MEA) & Recert 4 (R4)',
                    '[Verification work] Small Enterprise Approach (SEA)',
                    '[Verification work] Small-Medium Enterprise Approach (SMEA) & Recert 3 (R3)',
                    '[Verification work] Recert 2 (R2)',
                    '[Verification work] Recert 1 (R1)',
                    '[Verification work]: Pending B Corp',
                    '[Verification work] Accelerated Review (SEA)',
                    '[Verification work] Accelerated Review (SMEA)',
                ],
            ],
            [
                'name'    => 'Verification work - Large',
                'type'    => 'billable',
                'type_id' => $billableTypeId,
                'tasks'   => [
                    '[Verification work] Large Enterprise Approach & Multinational Companies (LE/MNC)',
                ],
            ],
            [
                'name'    => 'BLAB RTLs, aRTLs and QET',
                'type'    => 'billable',
                'type_id' => $billableTypeId,
                'tasks'   => [
                    '[Support services] Mentorship activities',
                    '[Management] Support to IVAs (Touchbase Meetings, Office Hours, 1-on-1 Sessions, Performance Monitoring)',
                    '[Management] Quality Enhancement (Data Analysis, Quality Management, Performance Tracking)',
                    '[Management] Quality Assurance (Assessment Checks)',
                    '[Management] Team Planning (Company Assignments)',
                    '[Management] Performance Improvement Plans (Planning and Sessions related to PIP)',
                ],
            ],
        ];

        DB::beginTransaction();

        try {
            foreach ($categories as $categoryData) {
                $this->command->info("Processing category: {$categoryData['name']}");

                // Create or get category
                $categoryId = DB::table('report_categories')
                    ->where('cat_name', $categoryData['name'])
                    ->value('id');

                if (! $categoryId) {
                    $categoryId = DB::table('report_categories')->insertGetId([
                        'cat_name'        => $categoryData['name'],
                        'cat_description' => "Auto-generated category for {$categoryData['type']} tasks",
                        'is_active'       => true,
                        'category_order'  => 20,
                        'category_type'   => $categoryData['type_id'],
                        'created_at'      => now(),
                        'updated_at'      => now(),
                    ]);

                    $this->command->info("  ✓ Created category: {$categoryData['name']} (ID: {$categoryId})");
                } else {
                    $this->command->info("  → Category already exists: {$categoryData['name']} (ID: {$categoryId})");
                }

                // Process tasks for this category
                $tasksFound      = 0;
                $tasksAssociated = 0;

                foreach ($categoryData['tasks'] as $taskName) {
                    // Try to find task by exact name first
                    $task = DB::table('tasks')
                        ->where('task_name', $taskName)
                        ->where('is_active', true)
                        ->first();

                    // If not found, try partial match (useful for tasks with brackets)
                    if (! $task) {
                        $task = DB::table('tasks')
                            ->where('task_name', 'LIKE', '%' . trim($taskName, '[]') . '%')
                            ->where('is_active', true)
                            ->first();
                    }

                    if ($task) {
                        $tasksFound++;

                        // Check if association already exists
                        $existingAssociation = DB::table('task_report_categories')
                            ->where('task_id', $task->id)
                            ->where('cat_id', $categoryId)
                            ->exists();

                        if (! $existingAssociation) {
                            DB::table('task_report_categories')->insert([
                                'task_id'    => $task->id,
                                'cat_id'     => $categoryId,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                            $tasksAssociated++;
                            $this->command->info("    ✓ Associated task: {$task->task_name}");
                        } else {
                            $this->command->info("    → Task already associated: {$task->task_name}");
                        }
                    } else {
                        $this->command->warn("    ✗ Task not found: {$taskName}");
                    }
                }

                $this->command->info("  Summary: {$tasksFound} tasks found, {$tasksAssociated} new associations created");
                $this->command->line('');
            }

            DB::commit();
            $this->command->info('✅ Task category seeding completed successfully!');

            // Show summary
            $this->showSummary();

        } catch (\Exception $e) {
            DB::rollBack();
            $this->command->error('❌ Error occurred during seeding: ' . $e->getMessage());
            Log::error('TaskCategorySeeder failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Show summary of created categories and associations
     */
    private function showSummary(): void
    {
        $this->command->line('');
        $this->command->info('=== SEEDING SUMMARY ===');

        $totalCategories   = DB::table('report_categories')->count();
        $totalAssociations = DB::table('task_report_categories')->count();
        $totalTasks        = DB::table('tasks')->count();

        $this->command->info("Total Categories: {$totalCategories}");
        $this->command->info("Total Task-Category Associations: {$totalAssociations}");
        $this->command->info("Total Tasks: {$totalTasks}");

        // Show categories with task counts
        $this->command->line('');
        $this->command->info('Categories with task counts:');

        $categoriesWithCounts = DB::table('report_categories as rc')
            ->leftJoin('task_report_categories as trc', 'rc.id', '=', 'trc.cat_id')
            ->leftJoin('configuration_settings as cs', 'rc.category_type', '=', 'cs.id')
            ->select('rc.cat_name', 'cs.setting_value as type', DB::raw('COUNT(trc.task_id) as task_count'))
            ->groupBy('rc.id', 'rc.cat_name', 'cs.setting_value')
            ->orderBy('rc.cat_name')
            ->get();

        foreach ($categoriesWithCounts as $category) {
            $type = strtoupper($category->type ?? 'UNKNOWN');
            $this->command->info("  • {$category->cat_name} ({$type}): {$category->task_count} tasks");
        }

        $this->command->line('');
    }
}