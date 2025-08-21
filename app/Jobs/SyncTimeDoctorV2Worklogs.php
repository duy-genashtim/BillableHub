<?php
namespace App\Jobs;

use App\Http\Controllers\TimeDoctorV2LongOperationController;
use App\Models\IvaUser;
use App\Services\DailyWorklogSummaryService;
use App\Services\TimeDoctor\TimeDoctorV2Service;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncTimeDoctorV2Worklogs implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $startDate;
    protected $endDate;

    public $timeout = 3600; // 1 hour timeout
    public $tries   = 1;

    public function __construct(string $startDate, string $endDate)
    {
        $this->startDate = $startDate;
        $this->endDate   = $endDate;
    }

    public function handle(TimeDoctorV2Service $timeDoctorV2Service, DailyWorklogSummaryService $dailyWorklogSummaryService)
    {
        Log::info('Starting TimeDoctor V2 worklog sync job', [
            'start_date' => $this->startDate,
            'end_date'   => $this->endDate,
        ]);

        $startDate = Carbon::parse($this->startDate);
        $endDate   = Carbon::parse($this->endDate);

        try {
            $users = IvaUser::with('timedoctorV2User')
                ->where('is_active', true)
                ->where('timedoctor_version', 2)
                ->whereHas('timedoctorV2User', function ($query) {
                    $query->where('is_active', true);
                })
                ->get();

            if ($users->isEmpty()) {
                Log::warning('No active TimeDoctor V2 users found for sync job');
                return;
            }

            $controller  = new TimeDoctorV2LongOperationController($timeDoctorV2Service);
            $currentDate = clone $startDate;
            $totalSynced = 0;

            while ($currentDate->lte($endDate)) {
                Log::info("Processing TimeDoctor V2 worklogs for date: {$currentDate->format('Y-m-d')}");

                $dayResult = $controller->processUsersWorklogsForDay($users, $currentDate, function ($message, $type = 'info') {
                    Log::info("[TimeDoctor V2 Sync Job] {$message}");
                });

                $totalSynced += $dayResult['inserted'] + $dayResult['updated'];
                $currentDate->addDay();
            }

            Log::info('TimeDoctor V2 worklog sync job completed', [
                'start_date'   => $this->startDate,
                'end_date'     => $this->endDate,
                'total_synced' => $totalSynced,
            ]);

            // Auto-calculate daily worklog summaries for all affected users
            Log::info('Starting daily worklog summaries calculation after TimeDoctor V2 sync');
            try {
                $userIds = $users->pluck('id')->toArray();
                $params = [
                    'start_date' => $this->startDate,
                    'end_date' => $this->endDate,
                    'calculate_all' => false,
                    'iva_user_ids' => $userIds
                ];

                $summaryResult = $dailyWorklogSummaryService->calculateSummaries($params);
                
                if ($summaryResult['success']) {
                    Log::info('Daily summaries calculated successfully after TimeDoctor V2 sync', [
                        'processed' => $summaryResult['summary']['total_processed'],
                        'errors' => $summaryResult['summary']['total_errors']
                    ]);
                } else {
                    Log::warning('Daily summaries calculation had issues after TimeDoctor V2 sync', [
                        'message' => $summaryResult['message']
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('Failed to calculate daily summaries after TimeDoctor V2 sync', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }

        } catch (\Exception $e) {
            Log::error('TimeDoctor V2 worklog sync job failed', [
                'start_date' => $this->startDate,
                'end_date'   => $this->endDate,
                'error'      => $e->getMessage(),
                'trace'      => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}