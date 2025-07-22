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
        Schema::create('configuration_settings_type', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();                                                                              // Unique identifier (e.g., employee_category, site_title)
            $table->string('name');                                                                                       // Display name
            $table->text('description')->nullable();                                                                      // Help text or tooltip for UI
            $table->enum('setting_category', ['site', 'user', 'report-time', 'report-cat', 'report', 'system', 'other']); // Type of setting
            $table->boolean('for_user_customize')->default(false);                                                        // Allow user customization
            $table->boolean('allow_edit')->default(true);
            $table->boolean('allow_delete')->default(true);
            $table->boolean('allow_create')->default(true);
            $table->timestamps();

            // Add indexes
            $table->index('key');
            $table->index('setting_category');

        });

        // Now create the configuration_settings table that references the type table
        Schema::create('configuration_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('setting_type_id')->constrained('configuration_settings_type');
            $table->string('setting_value');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('added_by', 150)->nullable();
            $table->smallInteger('order')->default(0)->nullable();
            $table->boolean('is_system')->default(false); // Flag to indicate if it's a system setting (non-deletable)
            $table->timestamps();

            // Add indexes for better performance
            $table->index('setting_type_id');
            $table->index('is_active');
            $table->index(['setting_type_id', 'order']);
            $table->index(['setting_value', 'is_active'], 'idx_settings_value_active');
        });

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable();
            $table->string('email')->nullable();
            $table->string('action'); // e.g., 'create', 'update', 'delete'
            $table->text('description')->nullable();
            $table->text('detail_log');
            $table->timestamps();

            // Add indexes for better performance
            $table->index('email');
            $table->index('action');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('configuration_settings');
        Schema::dropIfExists('configuration_settings_type');
    }
};
