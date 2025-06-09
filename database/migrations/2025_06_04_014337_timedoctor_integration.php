<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. regions table - this needs to be first due to foreign key constraints
        Schema::create('regions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('region_order')->default(10);
            $table->timestamps();

            // Add index for frequently queried columns
            $table->index('name');
            $table->index('is_active');
            $table->index('region_order');
        });

        // 2. iva_user table - moved up for dependency order
        Schema::create('iva_user', function (Blueprint $table) {
            $table->id();
            $table->string('full_name');
            $table->string('email')->unique();
            $table->date('hire_date')->nullable();
            $table->date('end_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('region_id')->nullable()->constrained('regions');
            $table->string('work_status')->nullable();
            $table->smallInteger('timedoctor_version');

            $table->timestamps();

            // Add indexes for frequently queried columns
            $table->index('hire_date');
            $table->index('end_date');
            $table->index('is_active');
            $table->index('region_id');
        });

        // 3. timedoctor_v1_user table
        Schema::create('timedoctor_v1_user', function (Blueprint $table) {
            $table->id();
            $table->string('timedoctor_id');
            $table->string('tm_fullname');
            $table->string('tm_email');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamp('last_login')->nullable();
            $table->foreignId('iva_user_id')->nullable()->constrained('iva_user')->onDelete('cascade');
            $table->timestamps();

            // Add indexes for better performance
            $table->index('timedoctor_id');
            $table->index('tm_email');
            $table->index('is_active');
            $table->index('last_synced_at');
            $table->index('iva_user_id');
        });

        Schema::create('timedoctor_v2_user', function (Blueprint $table) {
            $table->id();

            // Core user identification (keeping from v1)
            $table->string('timedoctor_id')->unique();
            $table->string('tm_fullname');
            $table->string('tm_email');

            // Enhanced user profile fields from API
            $table->string('timezone')->nullable();
            $table->string('profile_timezone')->nullable();
            $table->enum('role', ['owner', 'admin', 'manager', 'user'])->default('user');

            // Project and tag associations (stored as JSON arrays)
            $table->json('only_project_ids')->nullable();
            $table->json('manager_ids')->nullable();
            $table->json('tag_ids')->nullable();
            $table->json('silent_info')->nullable();

                                                         // Sync and status tracking
            $table->boolean('is_active')->default(true); // Keep for backwards compatibility
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamp('last_login')->nullable();
            $table->foreignId('iva_user_id')->nullable()->constrained('iva_user')->onDelete('cascade');
            $table->timestamps();

            // Add indexes for better performance
            $table->index('timedoctor_id');
            $table->index('tm_email');
            $table->index('is_active');
            $table->index('role');
            $table->index('last_synced_at');
            $table->index('iva_user_id');
        });

        // 4. projects table
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->smallInteger('timedoctor_version');
            $table->string('timedoctor_id')->nullable();
            $table->string('project_name');
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            // Add indexes
            $table->index('timedoctor_id');
            $table->index('project_name');
            $table->index('is_active');
            $table->index('last_synced_at');
        });

        // 5. tasks table - UPDATED
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->smallInteger('timedoctor_version');
            $table->string('task_name');           // Made unique
            $table->string('slug');                // Added slug field
            $table->text('user_list')->nullable(); // Changed from timedoctor_id to user_list as JSON
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            // $table->index('user_list', 'tasks_user_list_index', 'hash'); // Using hash index for text field
            $table->index('task_name');
            $table->index('is_active');
        });

        // 6. report_categories table - moved up for dependency
        Schema::create('report_categories', function (Blueprint $table) {
            $table->id();
            $table->string('cat_name');
            $table->text('cat_description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('category_order')->default(20);
            $table->foreignId('category_type')->constrained('configuration_settings')->onDelete('cascade');
            $table->timestamps();

            // Add index
            $table->index('cat_name');
            $table->index('is_active');
        });

        // 7. task_report_categories table
        Schema::create('task_report_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks')->onDelete('cascade');
            $table->foreignId('cat_id')->constrained('report_categories')->onDelete('cascade');
            $table->timestamps();

            // Add composite index for this junction table
            $table->index(['task_id', 'cat_id']);
        });

        // 8. worklogs_data table - UPDATED
        Schema::create('worklogs_data', function (Blueprint $table) {
            $table->id();
            $table->foreignId('iva_id')->constrained('iva_user')->onDelete('cascade');
            $table->string('timedoctor_project_id')->nullable();
            $table->string('timedoctor_task_id')->nullable();
            $table->foreignId('project_id')->nullable()->constrained('projects')->onDelete('set null');
            $table->foreignId('task_id')->nullable()->constrained('tasks')->onDelete('set null');
            $table->string('work_mode');
            $table->dateTime('start_time');
            $table->dateTime('end_time')->nullable();
            $table->integer('duration');
            $table->string('device_id')->nullable();
            $table->text('comment')->nullable();
            $table->string('api_type'); // timedoctor, manual or other
            $table->string('timedoctor_worklog_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('update_comment')->nullable();
            $table->smallInteger('timedoctor_version');
            $table->string('tm_user_id');
            $table->timestamps();

            // Add indexes for better query performance
            $table->index('work_mode');
            $table->index('start_time');
            $table->index('end_time');
            $table->index('is_active');
            $table->index('api_type');
            $table->index('tm_user_id');
            $table->index('timedoctor_worklog_id');
            $table->index('timedoctor_project_id');
            $table->index('timedoctor_task_id');
        });

        // 10. timedoctor_sync_status table
        Schema::create('timedoctor_sync_status', function (Blueprint $table) {
            $table->id();
            $table->string('entity_type'); // users, projects, tasks, worklogs
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamp('last_sync_started_at')->nullable();
            $table->string('status')->default('idle'); // idle, in_progress, completed, failed
            $table->text('sync_details')->nullable();  // Additional details like date ranges, counts, etc.
            $table->text('error_message')->nullable();
            $table->timestamps();

            // Add indexes
            $table->index('entity_type');
            $table->index('status');
            $table->index('last_synced_at');
        });

        // 11. timedoctor_worklog_sync_metadata table
        Schema::create('timedoctor_worklog_sync_metadata', function (Blueprint $table) {
            $table->id();
            $table->date('sync_date')->unique();
            $table->string('status')->default('pending'); // pending, in_progress, completed, failed
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->boolean('is_synced')->default(false);
            $table->integer('total_records')->nullable();
            $table->integer('synced_records')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            // Add index for faster lookups
            $table->index('sync_date');
            $table->index('status');
            $table->index('is_synced');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop tables in reverse order to respect foreign key constraints
        Schema::dropIfExists('timedoctor_worklog_sync_metadata');
        Schema::dropIfExists('timedoctor_sync_status');
        Schema::dropIfExists('worklogs_data');
        Schema::dropIfExists('task_report_categories');
        Schema::dropIfExists('report_categories');
        Schema::dropIfExists('tasks');
        Schema::dropIfExists('projects');
        Schema::dropIfExists('timedoctor_v1_user');
        Schema::dropIfExists('timedoctor_v2_user');
        Schema::dropIfExists('iva_user');
        Schema::dropIfExists('regions');
    }
};
