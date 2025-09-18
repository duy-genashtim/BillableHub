<?php

namespace App\Exports;

use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromArray;
// use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Conditional;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class PerformanceReportExport implements FromArray, WithEvents, WithTitle
{
    protected $reportData;

    protected $billableCategories;

    protected $categoryColumnMap;

    protected $regionGroups;

    protected $ftTableColumns;

    protected $ptTableColumns;

    protected $totalColumns;

    protected $ftRowData;

    protected $ptRowData;

    public function __construct($reportData)
    {
        $this->reportData = $reportData;
        $this->loadBillableCategories();
        $this->createCategoryColumnMap();
        $this->prepareUserData();
        $this->calculateTableColumns();
    }

    /**
     * Load billable categories using main_helper function
     */
    private function loadBillableCategories()
    {
        $result = getReportCategories('billable');

        if ($result['success']) {
            $this->billableCategories = collect($result['data'])->map(function ($category) {
                return (object) [
                    'id' => $category['id'],
                    'cat_name' => $category['name'],
                    'category_order' => $category['order'],
                ];
            });
        } else {
            $this->billableCategories = collect([]);
        }
    }

    /**
     * Create dynamic category to column mapping
     */
    private function createCategoryColumnMap()
    {
        $this->categoryColumnMap = [];
        foreach ($this->billableCategories as $index => $category) {
            $this->categoryColumnMap[$category->id] = [
                'name' => $category->cat_name,
                'ft_col_index' => 3 + $index, // C=2 (non-billable), then D=3, E=4, etc.
                'pt_col_index' => 3 + $index, // Same for PT table (separate table structure)
            ];
        }
    }

    /**
     * Calculate table column counts
     */
    private function calculateTableColumns()
    {
        $categoryCount = count($this->billableCategories);

        // Full-Time table: A=NO, B=Name, C=Non-Billable, D-X=Categories, Y=Actual Billable, Z-AD=Performance, AE-AF=NAD
        $this->ftTableColumns = 3 + $categoryCount + 1 + 5 + 2 - 1; // Base + Categories + Billable + Performance + NAD

        // Part-Time table: Same structure as FT
        $this->ptTableColumns = 3 + $categoryCount + 1 + 3 + 2 - 1; // Base + Categories + Billable + Performance + NAD (less performance columns)

        // Total columns: FT table + 1 separator + PT table
        $this->totalColumns = $this->ftTableColumns + 1 + $this->ptTableColumns;
    }

    /**
     * Prepare user data grouped by region
     */
    private function prepareUserData()
    {
        $this->regionGroups = [];

        if ($this->reportData['report_type'] === 'overall') {
            // Group by region from regions_data[]
            foreach ($this->reportData['regions_data'] as $region) {
                if (isset($region['users_data']) && ! empty($region['users_data'])) {
                    $regionName = $region['region']['name'];
                    $ftUsers = array_values(array_filter($region['users_data'], fn ($u) => $u['work_status'] === 'full-time'));
                    $ptUsers = array_values(array_filter($region['users_data'], fn ($u) => $u['work_status'] === 'part-time'));

                    $this->regionGroups[$regionName] = [
                        'ft_users' => $ftUsers,
                        'pt_users' => $ptUsers,
                        'region_data' => $region,
                    ];
                }
            }
        } else {
            // Single region report
            $regionName = $this->reportData['region']['name'];
            $ftUsers = $this->reportData['full_time_users'] ?? [];
            $ptUsers = $this->reportData['part_time_users'] ?? [];

            $this->regionGroups[$regionName] = [
                'ft_users' => $ftUsers,
                'pt_users' => $ptUsers,
                'region_data' => ['region' => $this->reportData['region']],
            ];
        }
    }

    /**
     * Return array data for Excel
     */
    public function array(): array
    {
        $data = [];

        // Row 1: Date range headers (merged)
        $data[] = $this->buildDateRangeHeaders();

        // Row 2: Empty row
        $data[] = array_fill(0, $this->totalColumns, '');

        // Row 3: Column headers
        $data[] = $this->buildColumnHeaders();

        // Track FT and PT rows separately for styling
        $this->ftRowData = [];
        $this->ptRowData = [];

        // Process FT table completely independently
        $this->processFTTable($data);

        // Process PT table completely independently
        $this->processPTTable($data);

        return $data;
    }

    /**
     * Process Full-Time table independently
     */
    private function processFTTable(&$data)
    {
        foreach ($this->regionGroups as $regionName => $regionData) {
            $ftUsers = $regionData['ft_users'];

            // Skip if no FT users in this region
            if (empty($ftUsers)) {
                continue;
            }

            // Add FT region header row
            $regionHeaderRow = $this->buildRegionHeaderRow($regionName);
            $data[] = $regionHeaderRow;
            $this->ftRowData[] = ['row' => count($data), 'type' => 'region_header'];

            // Add FT users
            for ($i = 0; $i < count($ftUsers); $i++) {
                $row = array_fill(0, $this->totalColumns, '');

                // Add FT user data
                $ftRow = $this->buildFTUserRow($ftUsers[$i], $i + 1);
                for ($j = 0; $j < $this->ftTableColumns; $j++) {
                    $row[$j] = $ftRow[$j];
                }

                $data[] = $row;
                $this->ftRowData[] = ['row' => count($data), 'type' => 'user'];
            }

            // Add FT region total immediately after FT users
            $row = array_fill(0, $this->totalColumns, '');
            $ftTotalRow = $this->buildFTRegionTotalRow($regionData, $regionName);
            for ($j = 0; $j < $this->ftTableColumns; $j++) {
                $row[$j] = $ftTotalRow[$j];
            }
            $data[] = $row;
            $this->ftRowData[] = ['row' => count($data), 'type' => 'region_total'];

            // Add empty separator row after FT region
            $data[] = array_fill(0, $this->totalColumns, '');
        }

        // Add empty row before FT overall summary
        // $data[] = array_fill(0, $this->totalColumns, '');

        // Add FT overall summary row
        $row = array_fill(0, $this->totalColumns, '');
        $ftOverallSummaryRow = $this->buildFTOverallSummaryRow();
        for ($j = 0; $j < $this->ftTableColumns; $j++) {
            $row[$j] = $ftOverallSummaryRow[$j];
        }
        $data[] = $row;
        $this->ftRowData[] = ['row' => count($data), 'type' => 'overall_summary'];
    }

    /**
     * Process Part-Time table independently (side-by-side with FT)
     */
    private function processPTTable(&$data)
    {
        $ptCurrentRow = 3; // Start right after column headers (0-based index)

        foreach ($this->regionGroups as $regionName => $regionData) {
            $ptUsers = $regionData['pt_users'];

            // Skip if no PT users in this region
            if (empty($ptUsers)) {
                continue;
            }

            // Ensure we have enough rows for PT region header
            while (count($data) <= $ptCurrentRow) {
                $data[] = array_fill(0, $this->totalColumns, '');
            }

            // Add PT region header data to existing row
            $ptStartCol = $this->ftTableColumns + 1;
            $data[$ptCurrentRow][$ptStartCol + 1] = $regionName; // PT Name column
            $this->ptRowData[] = ['row' => $ptCurrentRow + 1, 'type' => 'region_header'];
            $ptCurrentRow++;

            // Add PT users
            for ($i = 0; $i < count($ptUsers); $i++) {
                // Ensure we have enough rows
                while (count($data) <= $ptCurrentRow) {
                    $data[] = array_fill(0, $this->totalColumns, '');
                }

                // Add PT user data
                $ptRow = $this->buildPTUserRow($ptUsers[$i], $i + 1);
                for ($j = 0; $j < $this->ptTableColumns; $j++) {
                    $data[$ptCurrentRow][$ptStartCol + $j] = $ptRow[$ptStartCol + $j];
                }

                $this->ptRowData[] = ['row' => $ptCurrentRow + 1, 'type' => 'user'];
                $ptCurrentRow++;
            }

            // Add PT region total
            while (count($data) <= $ptCurrentRow) {
                $data[] = array_fill(0, $this->totalColumns, '');
            }

            $ptTotalRow = $this->buildPTRegionTotalRow($regionData, $regionName);
            for ($j = 0; $j < $this->ptTableColumns; $j++) {
                $data[$ptCurrentRow][$ptStartCol + $j] = $ptTotalRow[$ptStartCol + $j];
            }

            $this->ptRowData[] = ['row' => $ptCurrentRow + 1, 'type' => 'region_total'];
            $ptCurrentRow++;

            // Add empty separator row
            while (count($data) <= $ptCurrentRow) {
                $data[] = array_fill(0, $this->totalColumns, '');
            }
            $ptCurrentRow++;
        }

        // Add empty row before PT overall summary
        while (count($data) <= $ptCurrentRow) {
            $data[] = array_fill(0, $this->totalColumns, '');
        }
        // $ptCurrentRow++;

        // // Add PT overall summary row
        // while (count($data) <= $ptCurrentRow) {
        //     $data[] = array_fill(0, $this->totalColumns, '');
        // }

        $ptOverallSummaryRow = $this->buildPTOverallSummaryRow();
        $ptStartCol = $this->ftTableColumns + 1;
        for ($j = 0; $j < $this->ptTableColumns; $j++) {
            $data[$ptCurrentRow][$ptStartCol + $j] = $ptOverallSummaryRow[$ptStartCol + $j];
        }

        $this->ptRowData[] = ['row' => $ptCurrentRow + 1, 'type' => 'overall_summary'];
    }

    /**
     * Build date range headers (Row 1)
     */
    private function buildDateRangeHeaders()
    {
        $row = array_fill(0, $this->totalColumns, '');

        $dateRange = $this->getDateRangeLabel();

        // Full-Time header (B1 to end of FT table)
        $row[0] = "Date Covered: {$dateRange} (Full Time)";

        // Part-Time header (starts after FT table + separator)
        $ptStartCol = $this->ftTableColumns + 1;                // FT table + separator column
        $row[$ptStartCol] = "Date Covered: {$dateRange} (Part Time)"; // +1 for the Name column

        return $row;
    }

    /**
     * Build column headers (Row 3)
     */
    private function buildColumnHeaders()
    {
        $row = array_fill(0, $this->totalColumns, '');

        // Full-Time table headers
        $row[0] = 'NO.';                       // A
        $row[1] = 'Name';                      // B
        $row[2] = 'Actual Non-Billable Hours'; // C

        // Dynamic billable categories for FT
        foreach ($this->categoryColumnMap as $mapping) {
            $row[$mapping['ft_col_index']] = $mapping['name'];
        }

        $categoryCount = count($this->billableCategories);
        $billableCol = 3 + $categoryCount;
        $row[$billableCol] = 'Actual Billable Hours';
        $row[$billableCol + 1] = 'Target Billable Hours';
        $row[$billableCol + 2] = 'Actuals vs Committed';
        $row[$billableCol + 3] = 'Target Billable Hours (40)';
        $row[$billableCol + 4] = 'Actuals vs Committed (40)';
        $row[$billableCol + 5] = 'NAD Data – In days';
        $row[$billableCol + 6] = 'NAD Data – In hours';

        // Separator column is empty

        // Part-Time table headers (starts after FT table + separator)
        $ptStartCol = $this->ftTableColumns + 1;
        $row[$ptStartCol] = 'NO.';
        $row[$ptStartCol + 1] = 'Name';
        $row[$ptStartCol + 2] = 'Actual Non-Billable Hours';

        // Dynamic billable categories for PT
        foreach ($this->categoryColumnMap as $mapping) {
            $row[$ptStartCol + $mapping['pt_col_index']] = $mapping['name'];
        }

        $ptBillableCol = $ptStartCol + 3 + $categoryCount;
        $row[$ptBillableCol] = 'Actual Billable Hours';
        $row[$ptBillableCol + 1] = 'Target Billable Hours';
        $row[$ptBillableCol + 2] = 'Actuals vs Committed';
        $row[$ptBillableCol + 3] = 'NAD Data – In days';
        $row[$ptBillableCol + 4] = 'NAD Data – In hours';

        return $row;
    }

    /**
     * Build region header row
     */
    private function buildRegionHeaderRow($regionName)
    {
        $row = array_fill(0, $this->totalColumns, '');

        // Display region name in both FT and PT name columns
        $row[1] = $regionName; // B: FT Name column

        // $ptStartCol           = $this->ftTableColumns + 1;
        // $row[$ptStartCol + 1] = $regionName; // PT Name column

        return $row;
    }

    /**
     * Build Full-Time user row
     */
    private function buildFTUserRow($ftUser, $rowNumber)
    {
        $row = array_fill(0, $this->totalColumns, '');

        // Full-Time user data
        $row[0] = $rowNumber;                                                                         // A: NO.
        $row[1] = ucwords(strtolower($ftUser['full_name']));                                          // B: Name (first letter cap only)
        $row[2] = $ftUser['non_billable_hours'] === 0 ? '0' : ($ftUser['non_billable_hours'] ?: '0'); // C: Non-Billable Hours

        // Dynamic billable categories
        foreach ($this->categoryColumnMap as $categoryId => $mapping) {
            $hours = $this->getCategoryHours($ftUser, $categoryId);
            $row[$mapping['ft_col_index']] = $hours === 0 ? '0' : ($hours ?: '0'); // Explicitly show 0
        }
        // foreach ($this->categoryColumnMap as $categoryId => $mapping) {
        //     $row[$mapping['ft_col_index']] = $ftUser['categories'][$categoryId] ?? 0;
        // }

        $categoryCount = count($this->billableCategories);
        $billableCol = 3 + $categoryCount;
        $targetHours = $ftUser['target_hours'] ?? 35;

        $billableHours = $ftUser['billable_hours'] === 0 ? '0' : ($ftUser['billable_hours'] ?: '0');
        $row[$billableCol] = $billableHours === 0 ? '0' : ($billableHours ?: '0');             // Actual Billable Hours
        $row[$billableCol + 1] = $targetHours === 0 ? '0' : ($targetHours ?: '0');                 // Target Hours
        $row[$billableCol + 2] = $billableHours - $targetHours;                                    // Actual vs Target
        $row[$billableCol + 3] = 40;                                                               // 40 Hour Target
        $row[$billableCol + 4] = $billableHours - 40;                                              // Actual vs 40
        $row[$billableCol + 5] = $ftUser['nad_count'] === 0 ? '0' : ($ftUser['nad_count'] ?: '0'); // NAD Days
        $row[$billableCol + 6] = $ftUser['nad_hours'] === 0 ? '0' : ($ftUser['nad_hours'] ?: '0'); // NAD Hours

        return $row;
    }

    /**
     * Build Part-Time user row
     */
    private function buildPTUserRow($ptUser, $rowNumber)
    {
        $row = array_fill(0, $this->totalColumns, '');

        // Part-Time user data (starts after FT table + separator)
        $ptStartCol = $this->ftTableColumns + 1;

        $row[$ptStartCol] = $rowNumber;                                                                         // NO.
        $row[$ptStartCol + 1] = ucwords(strtolower($ptUser['full_name']));                                          // Name (first letter cap only)
        $row[$ptStartCol + 2] = $ptUser['non_billable_hours'] === 0 ? '0' : ($ptUser['non_billable_hours'] ?: '0'); // Non-Billable Hours

        // Dynamic billable categories
        foreach ($this->categoryColumnMap as $categoryId => $mapping) {
            $hours = $this->getCategoryHours($ptUser, $categoryId);
            $row[$ptStartCol + $mapping['pt_col_index']] = $hours === 0 ? '0' : ($hours ?: '0'); // Explicitly show 0
        }

        $categoryCount = count($this->billableCategories);
        $ptBillableCol = $ptStartCol + 3 + $categoryCount;
        $targetHours = $ptUser['target_hours'] ?? 20;

        $ptBillableHours = $ptUser['billable_hours'] == 0 ? '0' : ($ptUser['billable_hours'] ?: '0');
        $row[$ptBillableCol] = $ptBillableHours === 0 ? '0' : ($ptBillableHours ?: '0');         // Actual Billable Hours
        $row[$ptBillableCol + 1] = $targetHours === 0 ? '0' : ($targetHours ?: '0');                 // Target Hours
        $row[$ptBillableCol + 2] = $ptBillableHours - $targetHours;                                  // Actual vs Target
        $row[$ptBillableCol + 3] = $ptUser['nad_count'] === 0 ? '0' : ($ptUser['nad_count'] ?: '0'); // NAD Days
        $row[$ptBillableCol + 4] = $ptUser['nad_hours'] === 0 ? '0' : ($ptUser['nad_hours'] ?: '0'); // NAD Hours

        return $row;
    }

    /**
     * Build Full-Time region total row
     */
    private function buildFTRegionTotalRow($regionData, $regionName)
    {
        $row = array_fill(0, $this->totalColumns, '');

        // Full-Time totals for this region
        $ftTotals = $this->calculateTotals($regionData['ft_users']);
        $row[1] = $regionName.' Total';                                                 // B: Label
        $row[2] = $ftTotals['non_billable'] === 0 ? 0 : ($ftTotals['non_billable'] ?: 0); // C: Total Non-Billable

        // FT category totals
        foreach ($this->categoryColumnMap as $categoryId => $mapping) {
            $categoryTotal = $ftTotals['categories'][$categoryId] ?? 0;
            $row[$mapping['ft_col_index']] = $categoryTotal === 0 ? 0 : ($categoryTotal ?: 0);
        }

        $categoryCount = count($this->billableCategories);
        $billableCol = 3 + $categoryCount;

        $totalBillable = $ftTotals['billable'] === 0 ? 0 : ($ftTotals['billable'] ?: 0);
        $totalTarget = $ftTotals['target_hours'] === 0 ? 0 : ($ftTotals['target_hours'] ?: 0);

        $row[$billableCol] = $totalBillable;                                                   // Total Billable
        $row[$billableCol + 1] = $totalTarget;                                                     // Total Target Hours
        $row[$billableCol + 2] = $totalBillable - $totalTarget;                                    // Total Actuals vs Committed
        $row[$billableCol + 3] = count($regionData['ft_users']) * 40;                              // Total 40 Hour Target (40 * user count)
        $row[$billableCol + 4] = $totalBillable - (count($regionData['ft_users']) * 40);           // Total Actuals vs 40
        $row[$billableCol + 5] = $ftTotals['nad_count'] === 0 ? 0 : ($ftTotals['nad_count'] ?: 0); // Total NAD Days
        $row[$billableCol + 6] = $ftTotals['nad_hours'] === 0 ? 0 : ($ftTotals['nad_hours'] ?: 0); // Total NAD Hours

        return $row;
    }

    /**
     * Build Part-Time region total row
     */
    private function buildPTRegionTotalRow($regionData, $regionName)
    {
        $row = array_fill(0, $this->totalColumns, '');

        // Part-Time totals for this region
        $ptStartCol = $this->ftTableColumns + 1;
        $ptTotals = $this->calculateTotals($regionData['pt_users']);

        $row[$ptStartCol + 1] = $regionName.' Total';                                                 // Name column: Label
        $row[$ptStartCol + 2] = $ptTotals['non_billable'] === 0 ? 0 : ($ptTotals['non_billable'] ?: 0); // Total Non-Billable

        // PT category totals
        foreach ($this->categoryColumnMap as $categoryId => $mapping) {
            $categoryTotal = $ptTotals['categories'][$categoryId] ?? 0;
            $row[$ptStartCol + $mapping['pt_col_index']] = $categoryTotal === 0 ? 0 : ($categoryTotal ?: 0);
        }

        $categoryCount = count($this->billableCategories);
        $ptBillableCol = $ptStartCol + 3 + $categoryCount;

        $totalBillable = $ptTotals['billable'] === 0 ? 0 : ($ptTotals['billable'] ?: 0);
        $totalTarget = $ptTotals['target_hours'] === 0 ? 0 : ($ptTotals['target_hours'] ?: 0);

        $row[$ptBillableCol] = $totalBillable;                                                   // Total Billable
        $row[$ptBillableCol + 1] = $totalTarget;                                                     // Total Target Hours
        $row[$ptBillableCol + 2] = $totalBillable - $totalTarget;                                    // Total Actuals vs Committed
        $row[$ptBillableCol + 3] = $ptTotals['nad_count'] === 0 ? 0 : ($ptTotals['nad_count'] ?: 0); // Total NAD Days
        $row[$ptBillableCol + 4] = $ptTotals['nad_hours'] === 0 ? 0 : ($ptTotals['nad_hours'] ?: 0); // Total NAD Hours

        return $row;
    }

    /**
     * Build Full-Time overall summary row
     */
    private function buildFTOverallSummaryRow()
    {
        $row = array_fill(0, $this->totalColumns, '');

        // Collect all FT users from all regions
        $allFtUsers = [];
        foreach ($this->regionGroups as $regionData) {
            $allFtUsers = array_merge($allFtUsers, $regionData['ft_users']);
        }

        // Full-Time overall totals
        $ftTotals = $this->calculateTotals($allFtUsers);
        $row[1] = 'Overall Summary';                                                      // B: Label
        $row[2] = $ftTotals['non_billable'] === 0 ? 0 : ($ftTotals['non_billable'] ?: 0); // C: Total Non-Billable

        // FT category totals
        foreach ($this->categoryColumnMap as $categoryId => $mapping) {
            $categoryTotal = $ftTotals['categories'][$categoryId] ?? 0;
            $row[$mapping['ft_col_index']] = $categoryTotal === 0 ? 0 : ($categoryTotal ?: 0);
        }

        $categoryCount = count($this->billableCategories);
        $billableCol = 3 + $categoryCount;

        $totalBillable = $ftTotals['billable'] === 0 ? 0 : ($ftTotals['billable'] ?: 0);
        $totalTarget = $ftTotals['target_hours'] === 0 ? 0 : ($ftTotals['target_hours'] ?: 0);

        $row[$billableCol] = $totalBillable;                                                   // Total Billable
        $row[$billableCol + 1] = $totalTarget;                                                     // Total Target Hours
        $row[$billableCol + 2] = $totalBillable - $totalTarget;                                    // Total Actuals vs Committed
        $row[$billableCol + 3] = count($allFtUsers) * 40;                                          // Total 40 Hour Target (40 * user count)
        $row[$billableCol + 4] = $totalBillable - (count($allFtUsers) * 40);                       // Total Actuals vs 40
        $row[$billableCol + 5] = $ftTotals['nad_count'] === 0 ? 0 : ($ftTotals['nad_count'] ?: 0); // Total NAD Days
        $row[$billableCol + 6] = $ftTotals['nad_hours'] === 0 ? 0 : ($ftTotals['nad_hours'] ?: 0); // Total NAD Hours

        return $row;
    }

    /**
     * Build Part-Time overall summary row
     */
    private function buildPTOverallSummaryRow()
    {
        $row = array_fill(0, $this->totalColumns, '');

        // Collect all PT users from all regions
        $allPtUsers = [];
        foreach ($this->regionGroups as $regionData) {
            $allPtUsers = array_merge($allPtUsers, $regionData['pt_users']);
        }

        // Part-Time overall totals
        $ptStartCol = $this->ftTableColumns + 1;
        $ptTotals = $this->calculateTotals($allPtUsers);

        $row[$ptStartCol + 1] = 'Overall Summary';                                                      // PT Name column: Label
        $row[$ptStartCol + 2] = $ptTotals['non_billable'] === 0 ? 0 : ($ptTotals['non_billable'] ?: 0); // Total Non-Billable

        // PT category totals
        foreach ($this->categoryColumnMap as $categoryId => $mapping) {
            $categoryTotal = $ptTotals['categories'][$categoryId] ?? 0;
            $row[$ptStartCol + $mapping['pt_col_index']] = $categoryTotal === 0 ? 0 : ($categoryTotal ?: 0);
        }

        $categoryCount = count($this->billableCategories);
        $ptBillableCol = $ptStartCol + 3 + $categoryCount;

        $totalBillablePT = $ptTotals['billable'] === 0 ? 0 : ($ptTotals['billable'] ?: 0);
        $totalTargetPT = $ptTotals['target_hours'] === 0 ? 0 : ($ptTotals['target_hours'] ?: 0);

        $row[$ptBillableCol] = $totalBillablePT;                                                 // Total Billable
        $row[$ptBillableCol + 1] = $totalTargetPT;                                                   // Total Target Hours
        $row[$ptBillableCol + 2] = $totalBillablePT - $totalTargetPT;                                // Total Actuals vs Committed
        $row[$ptBillableCol + 3] = $ptTotals['nad_count'] === 0 ? 0 : ($ptTotals['nad_count'] ?: 0); // Total NAD Days
        $row[$ptBillableCol + 4] = $ptTotals['nad_hours'] === 0 ? 0 : ($ptTotals['nad_hours'] ?: 0); // Total NAD Hours

        return $row;
    }

    /**
     * Get category hours for a user by category ID
     */
    private function getCategoryHours($user, $categoryId)
    {
        if (isset($user['categories'])) {
            foreach ($user['categories'] as $category) {
                if (($category['category_id'] ?? null) == $categoryId) {
                    return $category['hours'] ?? 0;
                }
            }
        }

        return 0;
    }

    /**
     * Calculate totals for a group of users
     */
    private function calculateTotals($users)
    {
        $totals = [
            'billable' => 0,
            'non_billable' => 0,
            'target_hours' => 0,
            'nad_count' => 0,
            'nad_hours' => 0,
            'categories' => [],
        ];

        foreach ($users as $user) {
            $totals['billable'] += $user['billable_hours'] ?? 0;
            $totals['non_billable'] += $user['non_billable_hours'] ?? 0;
            $totals['target_hours'] += $user['target_hours'] ?? 0;
            $totals['nad_count'] += $user['nad_count'] ?? 0;
            $totals['nad_hours'] += $user['nad_hours'] ?? 0;

            if (isset($user['categories'])) {
                foreach ($user['categories'] as $category) {
                    $categoryId = $category['category_id'] ?? null;
                    if ($categoryId) {
                        $hours = $category['hours'] ?? 0;
                        $totals['categories'][$categoryId] = ($totals['categories'][$categoryId] ?? 0) + $hours;
                    }
                }
            }
        }

        return $totals;
    }

    /**
     * Get formatted date range label
     */
    private function getDateRangeLabel()
    {
        $startDate = Carbon::parse($this->reportData['date_range']['start']);
        $endDate = Carbon::parse($this->reportData['date_range']['end']);

        return $startDate->format('F d').' to '.$endDate->format('F d, Y');
    }

    /**
     * Get worksheet title
     */
    public function title(): string
    {
        $startDate = Carbon::parse($this->reportData['date_range']['start']);
        $endDate = Carbon::parse($this->reportData['date_range']['end']);

        return $startDate->format('M d').' to '.$endDate->format('M d, Y');
    }

    /**
     * Configure sheet events for styling
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $this->applyExcelStyling($sheet);
            },
        ];
    }

    /**
     * Apply Excel styling according to specifications
     */
    private function applyExcelStyling($sheet)
    {
        // Get actual data row count for dynamic styling
        $dataRowCount = $sheet->getHighestRow();

        // Set font
        $sheet->getParent()->getDefaultStyle()->getFont()
            ->setName('Calibri')
            ->setSize(10);

        // Apply header styling
        $this->applyHeaderStyling($sheet);

        // Apply column formatting
        $this->applyColumnFormatting($sheet, $dataRowCount);

        // Apply conditional formatting
        $this->applyConditionalFormatting($sheet, $dataRowCount);

        // Apply borders
        $this->applyBorders($sheet, $dataRowCount);

        // Apply separate styling for FT and PT region headers and totals
        $this->applyFTRegionStyling($sheet);
        $this->applyPTRegionStyling($sheet);

        // Freeze panes at C4
        // $sheet->freezePane('C4');
    }

    /**
     * Apply header row styling
     */
    private function applyHeaderStyling($sheet)
    {
        // Row 1: Date range headers (bold, CENTER align)
        $ftEndCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($this->ftTableColumns);

        // Full Time header (B1 to end of FT table) with light blue background
        $sheet->mergeCells('A1:'.$ftEndCol.'1');
        $sheet->getStyle('A1:'.$ftEndCol.'1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => '000000']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'B4C7E7'], // Light blue for FT
            ],
            'borders' => ['outline' => ['borderStyle' => Border::BORDER_MEDIUM]],
        ]);

        // Part Time header with dark blue background and white text
        $ptStartCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($this->ftTableColumns + 2);
        $ptEndCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($this->totalColumns);

        $sheet->mergeCells($ptStartCol.'1:'.$ptEndCol.'1');
        $sheet->getStyle($ptStartCol.'1:'.$ptEndCol.'1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => 'FFFFFF']], // White text
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1F4E79'], // Dark blue for PT
            ],
            'borders' => ['outline' => ['borderStyle' => Border::BORDER_MEDIUM]],
        ]);

        // Row 3: Column headers
        $sheet->getRowDimension('3')->setRowHeight(24);
        $totalColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($this->totalColumns);

        // FT table headers (light blue)
        $sheet->getStyle('A3:'.$ftEndCol.'3')->applyFromArray([
            'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => '000000']],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'B4C7E7'], // Light blue for FT headers
            ],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ]);

        // PT table headers (dark blue with white text)
        $sheet->getStyle($ptStartCol.'3:'.$ptEndCol.'3')->applyFromArray([
            'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => 'FFFFFF']], // White text
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1F4E79'], // Dark blue for PT headers
            ],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ]);
    }

    /**
     * Apply column formatting
     */
    private function applyColumnFormatting($sheet, $dataRowCount)
    {
        // Set minimum row height and make it auto with min 30
        for ($row = 1; $row <= $dataRowCount; $row++) {
            $sheet->getRowDimension($row)->setRowHeight(-1); // Auto height
            if ($row == 3) {
                // Explicitly set row 3 height = 30
                $sheet->getRowDimension($row)->setRowHeight(44);
            }
        }

        // Set column widths dynamically
        $sheet->getColumnDimension('A')->setWidth(4);  // FT ID column
        $sheet->getColumnDimension('B')->setWidth(28); // FT Name column

        // PT columns
        $ptStartCol = $this->ftTableColumns + 1;
        $ptIdCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($ptStartCol + 1);
        $ptNameCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($ptStartCol + 2);

        $sheet->getColumnDimension($ptIdCol)->setWidth(4);    // PT ID column
        $sheet->getColumnDimension($ptNameCol)->setWidth(28); // PT Name column

        // Separator column (smaller width, no background color)
        $separatorCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($this->ftTableColumns + 1);
        $sheet->getColumnDimension($separatorCol)->setWidth(3);

        // Set column widths based on requirements
        $categoryCount = count($this->billableCategories);

        // FT Non-Billable Hours column (C) - width 12
        $sheet->getColumnDimension('C')->setWidth(12);

        // FT Billable category columns - width 12
        for ($i = 1; $i <= $categoryCount; $i++) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(3 + $i);
            $sheet->getColumnDimension($colLetter)->setWidth(12);
        }

        // FT Actual Billable Hours - width 12
        $ftBillableCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(3 + $categoryCount + 1);
        $sheet->getColumnDimension($ftBillableCol)->setWidth(12);

        // FT Performance columns - width 8
        for ($i = 1; $i <= 5; $i++) { // Target Hours, Actual vs Target, 40 Hour Target, Actual vs 40, NAD Days
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(3 + $categoryCount + 1 + $i);
            $sheet->getColumnDimension($colLetter)->setWidth(12);
        }

        // FT NAD Hours - width 12
        $ftNadHoursCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(3 + $categoryCount + 1 + 5 + 1);
        $sheet->getColumnDimension($ftNadHoursCol)->setWidth(12);

        // PT columns (starting after separator)
        $ptDataStart = $this->ftTableColumns + 2; // After separator column

        // PT Non-Billable Hours - width 12
        $ptNonBillableCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($ptDataStart + 2);
        $sheet->getColumnDimension($ptNonBillableCol)->setWidth(12);

        // PT Billable category columns - width 12
        for ($i = 1; $i <= $categoryCount; $i++) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($ptDataStart + 2 + $i);
            $sheet->getColumnDimension($colLetter)->setWidth(12);
        }

        // PT Actual Billable Hours - width 12
        $ptBillableCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($ptDataStart + 2 + $categoryCount + 1);
        $sheet->getColumnDimension($ptBillableCol)->setWidth(12);

        // PT Performance columns - width 8
        for ($i = 1; $i <= 3; $i++) { // Target Hours, Actual vs Target, NAD Days
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($ptDataStart + 2 + $categoryCount + 1 + $i);
            $sheet->getColumnDimension($colLetter)->setWidth(8);
        }

        // PT NAD Hours - width 12
        $ptNadHoursCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($this->totalColumns);
        $sheet->getColumnDimension($ptNadHoursCol)->setWidth(12);

        // Number format for hours: #,##0.00 (dynamic based on table structure)
        $categoryCount = count($this->billableCategories);

        // FT table hours columns
        for ($i = 3; $i < 3 + $categoryCount + 1; $i++) { // Non-billable + categories + billable
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i);
            $sheet->getStyle($colLetter.'4:'.$colLetter.$dataRowCount)
                ->getNumberFormat()
                ->setFormatCode(NumberFormat::FORMAT_NUMBER_00);
        }

        // PT table hours columns
        $ptDataStart = $this->ftTableColumns + 2;
        for ($i = $ptDataStart + 2; $i < $ptDataStart + 2 + $categoryCount + 1; $i++) { // Non-billable + categories + billable
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i);
            $sheet->getStyle($colLetter.'4:'.$colLetter.$dataRowCount)
                ->getNumberFormat()
                ->setFormatCode(NumberFormat::FORMAT_NUMBER_00);
        }

        // NAD hours columns (last columns in each table)
        $ftNadHoursCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(3 + $categoryCount + 1 + 5 + 1); // Last FT column
        $ptNadHoursCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($this->totalColumns);            // Last PT column

        $sheet->getStyle($ftNadHoursCol.'4:'.$ftNadHoursCol.$dataRowCount)
            ->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_00);
        $sheet->getStyle($ptNadHoursCol.'4:'.$ptNadHoursCol.$dataRowCount)
            ->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_00);

        // Number format for NAD days: 0
        $ftNadDaysCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(3 + $categoryCount + 1 + 5); // Second to last FT column
        $ptNadDaysCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($this->totalColumns - 1);    // Second to last PT column

        $sheet->getStyle($ftNadDaysCol.'4:'.$ftNadDaysCol.$dataRowCount)
            ->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_GENERAL);
        $sheet->getStyle($ptNadDaysCol.'4:'.$ptNadDaysCol.$dataRowCount)
            ->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_GENERAL);

        // Number format for deltas: +#,##0.00;-#,##0.00;0.00
        $ftDelta1Col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(3 + $categoryCount + 1 + 1);                // FT Actual vs Target
        $ftDelta2Col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(3 + $categoryCount + 1 + 3);                // FT Actual vs 40
        $ptDeltaCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($ptDataStart + 2 + $categoryCount + 1 + 1); // PT Actual vs Target

        foreach ([$ftDelta1Col, $ftDelta2Col, $ptDeltaCol] as $col) {
            $sheet->getStyle($col.'4:'.$col.$dataRowCount)
                ->getNumberFormat()
                ->setFormatCode(NumberFormat::FORMAT_NUMBER_00);
        }
    }

    /**
     * Apply conditional formatting for performance deltas
     */
    private function applyConditionalFormatting($sheet, $dataRowCount)
    {
        $categoryCount = count($this->billableCategories);

        // Dynamic delta columns (only Actuals vs Committed, not Target columns)
        $ftDelta1Col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(3 + $categoryCount + 1 + 2);                             // FT Actual vs Target
        $ftDelta2Col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(3 + $categoryCount + 1 + 4);                             // FT Actual vs 40
        $ptDeltaCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($this->ftTableColumns + 2 + 2 + $categoryCount + 1 + 2); // PT Actual vs Target

        $deltaColumns = [$ftDelta1Col, $ftDelta2Col, $ptDeltaCol];

        foreach ($deltaColumns as $col) {
            $range = $col.'4:'.$col.$dataRowCount; // Dynamic range based on actual data

            // Red fill if < 0
            $conditionalStyles = [];
            $condition1 = new Conditional;
            $condition1->setConditionType(Conditional::CONDITION_CELLIS)
                ->setOperatorType(Conditional::OPERATOR_LESSTHAN)
                ->addCondition('0')
                ->getStyle()->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('FFCDD2'); // Light red
            $conditionalStyles[] = $condition1;

            // Green fill if > 0
            $condition2 = new Conditional;
            $condition2->setConditionType(Conditional::CONDITION_CELLIS)
                ->setOperatorType(Conditional::OPERATOR_GREATERTHAN)
                ->addCondition('0')
                ->getStyle()->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('C8E6C9'); // Light green
            $conditionalStyles[] = $condition2;

            // Remove yellow fill for = 0 (no conditional formatting for zero values)
            // This allows zero values to display without background color

            $sheet->getStyle($range)->setConditionalStyles($conditionalStyles);
        }

    }

    /**
     * Apply borders to the table
     */
    private function applyBorders($sheet, $dataRowCount)
    {
        // Calculate separate end rows for FT and PT tables
        $ftEndRow = $this->calculateFTEndRow($dataRowCount);
        $ptEndRow = $this->calculatePTEndRow($dataRowCount);

        // Separate borders for FT table (with dynamic end row)
        $ftEndCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($this->ftTableColumns);
        if ($ftEndRow > 3) {
            $sheet->getStyle('A3:'.$ftEndCol.$ftEndRow)->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => '000000'],
                    ],
                    'outline' => [
                        'borderStyle' => Border::BORDER_MEDIUM,
                        'color' => ['rgb' => '000000'],
                    ],
                ],
            ]);
        }

        // Separate borders for PT table (with dynamic end row)
        $ptStartCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($this->ftTableColumns + 2);
        $ptEndCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($this->totalColumns);
        if ($ptEndRow > 3) {
            $sheet->getStyle($ptStartCol.'3:'.$ptEndCol.$ptEndRow)->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => '000000'],
                    ],
                    'outline' => [
                        'borderStyle' => Border::BORDER_MEDIUM,
                        'color' => ['rgb' => '000000'],
                    ],
                ],
            ]);
        }

        // Remove any background from separator column
        $separatorColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($this->ftTableColumns + 1);
        $sheet->getStyle($separatorColLetter.'1:'.$separatorColLetter.$dataRowCount)->applyFromArray([
            'fill' => [
                'fillType' => Fill::FILL_NONE,
            ],
        ]);
    }

    /**
     * Calculate the last row for FT table
     */
    private function calculateFTEndRow($dataRowCount)
    {
        $ftEndRow = 3; // Start from headers

        // Find the highest row number in FT data (excluding overall summary)
        foreach ($this->ftRowData as $ftRow) {
            if ($ftRow['row'] < $dataRowCount) { // Exclude overall summary row
                $ftEndRow = max($ftEndRow, $ftRow['row']);
            }
        }

        return $ftEndRow;
    }

    /**
     * Calculate the last row for PT table
     */
    private function calculatePTEndRow($dataRowCount)
    {
        $ptEndRow = 3; // Start from headers

        // Find the highest row number in PT data (excluding overall summary)
        foreach ($this->ptRowData as $ptRow) {
            if ($ptRow['row'] < $dataRowCount) { // Exclude overall summary row
                $ptEndRow = max($ptEndRow, $ptRow['row']);
            }
        }

        return $ptEndRow;
    }

    /**
     * Apply styling for Full-Time region headers and total rows
     */
    private function applyFTRegionStyling($sheet)
    {
        $ftEndCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($this->ftTableColumns);

        foreach ($this->ftRowData as $ftRow) {
            $rowNum = $ftRow['row'];
            $type = $ftRow['type'];

            if ($type === 'region_header') {
                // Style FT region header - Light blue background, bold
                $sheet->getStyle('A'.$rowNum.':'.$ftEndCol.$rowNum)->applyFromArray([
                    'font' => ['bold' => true, 'size' => 11],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'D9E2F3'], // Light blue background for FT headers
                    ],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
                ]);
            } elseif ($type === 'region_total') {
                // Style FT region total - Darker blue background, bold, white text
                $sheet->getStyle('A'.$rowNum.':'.$ftEndCol.$rowNum)->applyFromArray([
                    'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => 'FFFFFF']], // White text
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '4472C4'], // Darker blue background for FT totals
                    ],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
                ]);
            } elseif ($type === 'overall_summary') {
                // Style FT overall summary - Very dark blue background, bold, white text
                $sheet->getStyle('A'.$rowNum.':'.$ftEndCol.$rowNum)->applyFromArray([
                    'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => 'FFFFFF']], // White text, larger font
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '1F3864'], // Very dark blue for FT overall summary
                    ],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
                    'borders' => [
                        'outline' => [
                            'borderStyle' => Border::BORDER_THICK,
                            'color' => ['rgb' => '000000'],
                        ],
                    ],
                ]);
            }
        }
    }

    /**
     * Apply styling for Part-Time region headers and total rows
     */
    private function applyPTRegionStyling($sheet)
    {
        $ptStartCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($this->ftTableColumns + 2);
        $ptEndCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($this->totalColumns);

        foreach ($this->ptRowData as $ptRow) {
            $rowNum = $ptRow['row'];
            $type = $ptRow['type'];

            if ($type === 'region_header') {
                // Style PT region header - Light green background, bold
                $sheet->getStyle($ptStartCol.$rowNum.':'.$ptEndCol.$rowNum)->applyFromArray([
                    'font' => ['bold' => true, 'size' => 11],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'E2EFDA'], // Light green background for PT headers
                    ],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
                ]);
            } elseif ($type === 'region_total') {
                // Style PT region total - Darker green background, bold, white text
                $sheet->getStyle($ptStartCol.$rowNum.':'.$ptEndCol.$rowNum)->applyFromArray([
                    'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => 'FFFFFF']], // White text
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '70AD47'], // Darker green background for PT totals
                    ],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
                ]);
            } elseif ($type === 'overall_summary') {
                // Style PT overall summary - Very dark green background, bold, white text
                $sheet->getStyle($ptStartCol.$rowNum.':'.$ptEndCol.$rowNum)->applyFromArray([
                    'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => 'FFFFFF']], // White text, larger font
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '375623'], // Very dark green for PT overall summary
                    ],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
                    'borders' => [
                        'outline' => [
                            'borderStyle' => Border::BORDER_THICK,
                            'color' => ['rgb' => '000000'],
                        ],
                    ],
                ]);
            }
        }
    }
}
