<?php
namespace App\Providers;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Carbon\Carbon;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Duy - fix max key length is 1000 bytes
        // 191 × 4 = 764 < 767
        Schema::defaultStringLength(191);

        // Set date serialization format to avoid timezone conversion issues
        // This ensures date fields serialize as Y-m-d without timezone information
        Carbon::setToStringFormat('Y-m-d');
    }
}