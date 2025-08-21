<?php
namespace App\Console\Commands;

use App\Models\IvaUser;
use App\Services\ActivityLogService;
use App\Services\DailyWorklogSummaryService;
use App\Services\TimeDoctor\TimeDoctorService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncTimeDoctorWorklogs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'timedoctor:sync-worklogs
                            {--start-date= : Start date for sync (Y-m-d format)}
                            {--end-date= : End date for sync (Y-m-d format)}
                            {--dry-run : Run without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync TimeDoctor v1 worklog data for all IVA users within a date range';

    protected $timeDoctorService;
    protected $dailyWorklogSummaryService;

    const BATCH_SIZE       = 100;
    const MAX_RETRIES      = 3;
    const PAGINATION_LIMIT = 250;

    public function __construct(
        TimeDoctorService $timeDoctorService,
        DailyWorklogSummaryService $dailyWorklogSummaryService
    ) {
        parent::__construct();
        $this->timeDoctorService = $timeDoctorService;
        $this->dailyWorklogSummaryService = $dailyWorklogSummaryService;
        set_time_limit(0); // Allow script to run indefinitely
        ini_set('memory_limit', '1024M');
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        try {
            $this->info('=== TimeDoctor Worklog Sync Command ===');

            // Parse and validate date range
            $dateRange = $this->parseDateRange();
            $startDate = $dateRange['start'];
            $endDate   = $dateRange['end'];
            $isDryRun  = $this->option('dry-run');

            if ($isDryRun) {
                $this->warn('DRY RUN MODE - No data will be modified');
            }

            $this->info("Sync Date Range: {$startDate->format('Y-m-d')} to {$endDate->format('Y-m-d')}");
            $this->info("Total Days: " . ($startDate->diffInDays($endDate) + 1));

            // Get TimeDoctor company info
            $this->info('Connecting to TimeDoctor API...');
            $companyInfo = $this->timeDoctorService->getCompanyInfo();

            if (! isset($companyInfo['accounts'][0]['company_id'])) {
                throw new \Exception('Could not retrieve company ID from TimeDoctor');
            }

            $companyId = $companyInfo['accounts'][0]['company_id'];
            $this->info("Using TimeDoctor Company ID: {$companyId}");

            // Get active IVA users with TimeDoctor mapping
            $users = IvaUser::with('timedoctorUser')
                ->where('is_active', true)
                ->whereHas('timedoctorUser', function ($query) {
                    $query->where('is_active', true);
                })
                ->get();

            if ($users->isEmpty()) {
                throw new \Exception('No active TimeDoctor users found. Please sync users first.');
            }

            $this->info("Found {$users->count()} active IVA users with TimeDoctor mapping");

            // Process sync
            $results = $this->syncWorklogsForDateRange($companyId, $users, $startDate, $endDate, $isDryRun);

            // Display results
            $this->displayResults($results, $startDate, $endDate, $isDryRun);

            // Log activity
            if (! $isDryRun) {
                ActivityLogService::log('sync_timedoctor_data', 'TimeDoctor worklog sync completed via Artisan command', [
                    'module'          => 'timedoctor_integration',
                    'start_date'      => $startDate->format('Y-m-d'),
                    'end_date'        => $endDate->format('Y-m-d'),
                    'total_synced'    => $results['total_inserted'] + $results['total_updated'],
                    'total_days'      => $startDate->diffInDays($endDate) + 1,
                    'users_processed' => $users->count(),
                    'command'         => 'artisan',
                ]);

                // Auto-calculate daily worklog summaries for all affected users
                $this->info('📊 Calculating daily worklog summaries...');
                try {
                    $userIds = $users->pluck('id')->toArray();
                    $params = [
                        'start_date' => $startDate->format('Y-m-d'),
                        'end_date' => $endDate->format('Y-m-d'),
                        'calculate_all' => false,
                        'iva_user_ids' => $userIds
                    ];

                    $summaryResult = $this->dailyWorklogSummaryService->calculateSummaries($params);
                    
                    if ($summaryResult['success']) {
                        $this->info('✅ Daily summaries calculated successfully! ' . 
                            'Processed: ' . $summaryResult['summary']['total_processed'] . 
                            ', Errors: ' . $summaryResult['summary']['total_errors']);
                    } else {
                        $this->warn('⚠️  Daily summaries calculation had issues: ' . $summaryResult['message']);
                    }
                } catch (\Exception $e) {
                    $this->error('❌ Failed to calculate daily summaries: ' . $e->getMessage());
                }
            }

            $this->info('✅ Sync completed successfully!');
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("❌ Sync failed: {$e->getMessage()}");

            // Log::error('TimeDoctor worklog sync command failed', [
            //     'error' => $e->getMessage(),
            //     'trace' => $e->getTraceAsString(),
            //     'command' => $this->signature,
            // ]);

            ActivityLogService::log('sync_timedoctor_data', 'TimeDoctor worklog sync failed via Artisan command', [
                'module'  => 'timedoctor_integration',
                'error'   => $e->getMessage(),
                'command' => 'artisan',
            ]);

            return Command::FAILURE;
        }
    }

    /**
     * Parse and validate date range from command options
     */
    private function parseDateRange(): array
    {
        $startDateInput = $this->option('start-date');
        $endDateInput   = $this->option('end-date');

        // Default to yesterday if no dates provided
        if (! $startDateInput && ! $endDateInput) {
            $yesterday = Carbon::yesterday();
            $startDate = $yesterday->copy();
            $endDate   = $yesterday->copy();
            $this->info("No date range specified, defaulting to yesterday: {$yesterday->format('Y-m-d')}");
        } else {
            // Validate start date
            if (! $startDateInput) {
                throw new \InvalidArgumentException('Start date is required when end date is provided');
            }

            if (! $endDateInput) {
                throw new \InvalidArgumentException('End date is required when start date is provided');
            }

            try {
                $startDate = Carbon::createFromFormat('Y-m-d', $startDateInput);
                $endDate   = Carbon::createFromFormat('Y-m-d', $endDateInput);
            } catch (\Exception $e) {
                throw new \InvalidArgumentException('Invalid date format. Use Y-m-d format (e.g., 2024-01-15)');
            }

            // Validate date range
            if ($startDate->gt($endDate)) {
                throw new \InvalidArgumentException('Start date must be before or equal to end date');
            }

            if ($startDate->diffInDays($endDate) > 30) {
                throw new \InvalidArgumentException('Date range cannot exceed 31 days');
            }
        }

        return [
            'start' => $startDate,
            'end'   => $endDate,
        ];
    }

    /**
     * Sync worklogs for the specified date range
     */
    private function syncWorklogsForDateRange($companyId, $users, Carbon $startDate, Carbon $endDate, bool $isDryRun): array
    {
        $totalDays     = $startDate->diffInDays($endDate) + 1;
        $processedDays = 0;
        $totalInserted = 0;
        $totalUpdated  = 0;
        $totalErrors   = 0;

        $progressBar = $this->output->createProgressBar($totalDays);
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s% -- %message%');
        $progressBar->setMessage('Starting sync...');
        $progressBar->start();

        $currentDate = $startDate->copy();

        while ($currentDate->lte($endDate)) {
            $dayStr = $currentDate->format('Y-m-d');
            $progressBar->setMessage("Processing: {$dayStr}");

            try {
                $dayResult = $this->processUsersWorklogsForDay($companyId, $users, $currentDate, $isDryRun);

                $totalInserted += $dayResult['inserted'];
                $totalUpdated += $dayResult['updated'];
                $totalErrors += $dayResult['errors'];

                $this->newLine();
                $this->info("✓ Day {$dayStr}: Added {$dayResult['inserted']}, Updated {$dayResult['updated']}, Errors {$dayResult['errors']}");

            } catch (\Exception $e) {
                $totalErrors++;
                $this->newLine();
                $this->error("✗ Day {$dayStr}: {$e->getMessage()}");

                // Log::error("Error processing day {$dayStr}", [
                //     'date' => $dayStr,
                //     'error' => $e->getMessage(),
                // ]);
            }

            $processedDays++;
            $currentDate->addDay();
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        return [
            'total_inserted' => $totalInserted,
            'total_updated'  => $totalUpdated,
            'total_errors'   => $totalErrors,
            'processed_days' => $processedDays,
        ];
    }

    /**
     * Process worklogs for all users for a specific day
     * Reused logic from TimeDoctorLongOperationController
     */
    private function processUsersWorklogsForDay($companyId, $users, Carbon $date, bool $isDryRun): array
    {
        $totalUsers     = $users->count();
        $processedUsers = 0;
        $totalInserted  = 0;
        $totalUpdated   = 0;
        $totalErrors    = 0;

        foreach ($users as $user) {
            if (! $user->timedoctorUser) {
                $processedUsers++;
                continue;
            }

            $userName = $user->timedoctorUser->tm_fullname;

            try {
                $offset       = 1;
                $hasMoreData  = true;
                $batchNumber  = 1;
                $userInserted = 0;
                $userUpdated  = 0;

                while ($hasMoreData) {
                    $worklogData = $this->timeDoctorService->getUserWorklogs(
                        $companyId,
                        $user->timedoctorUser->timedoctor_id,
                        $date->copy()->startOfDay(),
                        $date->copy()->endOfDay(),
                        $offset,
                        self::PAGINATION_LIMIT
                    );

                    if (! is_array($worklogData)) {
                        $totalErrors++;
                        break;
                    }

                    if (! isset($worklogData['worklogs']) || empty($worklogData['worklogs'])) {
                        break;
                    }

                    if (isset($worklogData['worklogs']['items'])) {
                        $worklogItems = $worklogData['worklogs']['items'];
                    } else if (is_array($worklogData['worklogs'])) {
                        $worklogItems = $worklogData['worklogs'];
                    } else {
                        $totalErrors++;
                        break;
                    }

                    $worklogCount = count($worklogItems);

                    if ($worklogCount === 0) {
                        break;
                    }

                    // Process worklog batch using existing controller logic
                    if (! $isDryRun) {
                        $result = $this->processWorklogBatch($worklogItems, $user);

                        if (is_array($result)) {
                            $userInserted += $result['inserted'];
                            $userUpdated += $result['updated'];
                            $totalErrors += $result['errors'];
                        }
                    } else {
                        // In dry run mode, just count what would be processed
                        $userInserted += $worklogCount;
                    }

                    $hasMoreData = $worklogCount >= self::PAGINATION_LIMIT;
                    $offset += self::PAGINATION_LIMIT;
                    $batchNumber++;

                    usleep(200000); // Rate limiting
                }

                $totalInserted += $userInserted;
                $totalUpdated += $userUpdated;
                $processedUsers++;

            } catch (\Exception $e) {
                // Log::error("Error processing worklogs for user {$userName}", [
                //     'user_id' => $user->id,
                //     'date' => $date->format('Y-m-d'),
                //     'error' => $e->getMessage(),
                // ]);

                $totalErrors++;
                $processedUsers++;
            }
        }

        return [
            'inserted' => $totalInserted,
            'updated'  => $totalUpdated,
            'errors'   => $totalErrors,
        ];
    }

    /**
     * Process a batch of worklog items for a user
     * Reused logic from TimeDoctorLongOperationController
     */
    private function processWorklogBatch(array $worklogItems, $user): array
    {
        if (empty($worklogItems)) {
            return ['inserted' => 0, 'updated' => 0, 'errors' => 0];
        }

        // Import the controller class to reuse its logic
        $controller = app(\App\Http\Controllers\TimeDoctorLongOperationController::class);

        // Use reflection to call the private method
        $reflection = new \ReflectionClass($controller);
        $method     = $reflection->getMethod('processWorklogBatch');
        $method->setAccessible(true);

        return $method->invokeArgs($controller, [$worklogItems, $user]);
    }

    /**
     * Display sync results
     */
    private function displayResults(array $results, Carbon $startDate, Carbon $endDate, bool $isDryRun): void
    {
        $this->newLine();
        $this->info('=== SYNC RESULTS ===');

        if ($isDryRun) {
            $this->warn('DRY RUN RESULTS (No actual changes made):');
        }

        $this->info("Date Range: {$startDate->format('Y-m-d')} to {$endDate->format('Y-m-d')}");
        $this->info("Days Processed: {$results['processed_days']}");
        $this->info("Records Inserted: {$results['total_inserted']}");
        $this->info("Records Updated: {$results['total_updated']}");
        $this->info("Total Records Synced: " . ($results['total_inserted'] + $results['total_updated']));

        if ($results['total_errors'] > 0) {
            $this->warn("Errors Encountered: {$results['total_errors']}");
        } else {
            $this->info("Errors: 0");
        }

        $this->newLine();
    }
}
