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
        Schema::create('iva_manager', function (Blueprint $table) {
            $table->id();
            $table->foreignId('iva_id')->constrained('iva_user')->onDelete('cascade');
            $table->foreignId('iva_manager_id')->constrained('iva_user')->onDelete('cascade');
            // with a specific setting_type_id (e.g., manager_types)
            $table->foreignId('manager_type_id')->constrained('configuration_settings');
            // Add region relationship
            $table->foreignId('region_id')->constrained('regions');
            $table->timestamps();

            // Add indexes
            $table->index('manager_type_id');
            $table->index('region_id');
        });

        Schema::create('iva_user_changelogs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('iva_user_id')->constrained('iva_user')->onDelete('cascade');

            // Link to configuration_settings table for field_changed types
            $table->enum('field_changed', ['info', 'region', 'work_status', 'other']);

            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->text('change_reason')->nullable();

            // Who made the change (without linking to iva_user)
            $table->string('changed_by_name')->nullable();
            $table->string('changed_by_email')->nullable();

            // When change takes effect (could be different from created_at)
            $table->dateTime('effective_date')->nullable();

            // Standard timestamps
            $table->timestamps();

            // Indexes for frequent queries
            $table->index('iva_user_id');
            $table->index('field_changed');
            $table->index('effective_date');
            $table->index('created_at'); // For chronological filtering
        });

        Schema::create('iva_user_customize', function (Blueprint $table) {
            $table->id();
            $table->foreignId('iva_user_id')->constrained('iva_user')->onDelete('cascade');

            $table->foreignId('setting_id')->constrained('configuration_settings')->onDelete('cascade');
            //  $table->foreignId('setting_type_id')->constrained('configuration_settings_type')->onDelete('cascade');
            $table->text('custom_value');
            $table->timestamps();

            // Prevent duplicate customizations for the same user and setting
            $table->unique(['iva_user_id', 'setting_id']);

            // Add indexes for better query performance
            $table->index('iva_user_id');
            $table->index('setting_id');
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('iva_user_customize');
        Schema::dropIfExists('iva_user_changelogs');
        Schema::dropIfExists('iva_manager');
    }
};
