<?php
namespace App\Providers;

use App\Http\Controllers\TimeDoctorAuthController;
use App\Services\TimeDoctor\TimeDoctorService;
use Illuminate\Support\ServiceProvider;

class TimeDoctorServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TimeDoctorService::class, function ($app) {
            return new TimeDoctorService(
                $app->make(TimeDoctorAuthController::class)
            );
        });
    }

    public function boot(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../../config/services.php', 'services'
        );
    }
}