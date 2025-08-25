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
        Schema::create('daily_worklog_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('iva_id')->constrained('iva_user')->onDelete('cascade');
            $table->foreignId('report_category_id')
                ->nullable() // allow null values
                ->constrained('report_categories')
                ->onDelete('cascade');
            $table->date('report_date');
            $table->integer('total_duration'); // Total duration in seconds
            $table->integer('entries_count');  // Number of worklog entries
            $table->string('category_type');   // billable, non-billable, or other types
            $table->timestamps();

            // Unique constraint to prevent duplicate entries
            $table->unique(['iva_id', 'report_category_id', 'report_date'], 'unique_iva_category_date');

            // Indexes for performance
            $table->index(['iva_id', 'report_date'], 'idx_iva_date');
            $table->index(['report_category_id', 'report_date'], 'idx_category_date');
            $table->index(['category_type', 'report_date'], 'idx_category_type_date');
            $table->index(['iva_id', 'category_type', 'report_date'], 'idx_iva_category_type_date');
            $table->index(['report_date'], 'idx_report_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_worklog_summaries');
    }
};
