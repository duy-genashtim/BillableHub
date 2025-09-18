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

    protected $regionCountTableRows;

    protected $billableTasksTableRows;

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

        // Row 2: General Tasks header (FT + PT)
        $data[] = $this->buildGeneralTasksHeader();

        // Row 3: Grouped headers (FT + PT)
        $data[] = $this->buildGroupedHeaders();

        // Row 4: Column headers
        $data[] = $this->buildColumnHeaders();

        // Track FT and PT rows separately for styling
        $this->ftRowData = [];
        $this->ptRowData = [];
        $this->regionCountTableRows = [];
        $this->billableTasksTableRows = [];

        // Process FT table completely independently
        $this->processFTTable($data);

        // Process PT table completely independently
        $this->processPTTable($data);

        // Add additional summary tables for overall reports
        if ($this->reportData['report_type'] === 'overall') {
            // Add 2 empty rows before Region Count Table
            $data[] = array_fill(0, $this->totalColumns, '');
            $data[] = array_fill(0, $this->totalColumns, '');

            // Add Region Count Table
            $regionCountRows = $this->buildRegionCountTable();
            $regionCountStartRow = count($data) + 1; // +1 for 1-based Excel row indexing
            foreach ($regionCountRows as $index => $row) {
                $data[] = $row;
                $this->regionCountTableRows[] = [
                    'row' => count($data),
                    'type' => $index === 0 ? 'header' : ($index === count($regionCountRows) - 1 ? 'grand_total' : 'data'),
                ];
            }

            // Add 2 empty rows before Billable Tasks Table
            $data[] = array_fill(0, $this->totalColumns, '');
            $data[] = array_fill(0, $this->totalColumns, '');

            // Add Billable Tasks Table
            $billableTasksRows = $this->buildBillableTasksTable();
            $billableTasksStartRow = count($data) + 1; // +1 for 1-based Excel row indexing
            foreach ($billableTasksRows as $index => $row) {
                $data[] = $row;
                $this->billableTasksTableRows[] = [
                    'row' => count($data),
                    'type' => $index === 0 ? 'header' : ($index === count($billableTasksRows) - 1 ? 'grand_total' : 'data'),
                ];
            }
        }

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
        $ptCurrentRow = 4; // Start right after column headers (0-based index)

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
     * Build General Tasks header (Row 2) for both FT and PT tables
     */
    private function buildGeneralTasksHeader()
    {
        $row = array_fill(0, $this->totalColumns, '');

        $categoryCount = count($this->billableCategories);

        // Only add "General Tasks" if there are billable categories
        if ($categoryCount > 0) {
            // FT Table: Place "General Tasks" in first billable category column (index 3 = column D)
            $row[3] = 'General Tasks';

            // PT Table: Calculate PT start position and place "General Tasks"
            $ptStartCol = $this->ftTableColumns + 1; // After FT table + separator
            $ptFirstCategoryCol = $ptStartCol + 3;           // PT: NO + Name + Non-Billable + first category
            $row[$ptFirstCategoryCol] = 'General Tasks';
        }

        return $row;
    }

    /**
     * Build grouped headers (Row 3) for both FT and PT tables
     */
    private function buildGroupedHeaders()
    {
        $row = array_fill(0, $this->totalColumns, '');

        $categoryCount = count($this->billableCategories);

        // === MOVE COLUMN HEADERS FROM NO. TO ACTUAL BILLABLE HOURS TO ROW 3 ===

        // FT Table: NO. to Actual Billable Hours
        $row[0] = 'NO.';                       // A
        $row[1] = 'Name';                      // B
        $row[2] = 'Actual Non-Billable Hours'; // C

        // FT Dynamic billable categories
        foreach ($this->billableCategories as $index => $category) {
            $row[3 + $index] = $category->cat_name;
        }

        $ftActualBillableCol = 3 + $categoryCount;
        $row[$ftActualBillableCol] = 'Actual Billable Hours';

        // PT Table: NO. to Actual Billable Hours (starts after FT table + separator)
        $ptStartCol = $this->ftTableColumns + 1;
        $row[$ptStartCol] = 'NO.';
        $row[$ptStartCol + 1] = 'Name';
        $row[$ptStartCol + 2] = 'Actual Non-Billable Hours';

        // PT Dynamic billable categories
        foreach ($this->billableCategories as $index => $category) {
            $row[$ptStartCol + 3 + $index] = $category->cat_name;
        }

        $ptActualBillableCol = $ptStartCol + 3 + $categoryCount;
        $row[$ptActualBillableCol] = 'Actual Billable Hours';

        // === KEEP GROUPED PERFORMANCE HEADERS ===

        // FT: "35 Workweek Hours" - spans Target Billable Hours + Actuals vs Committed columns
        $ftTarget35Col = $ftActualBillableCol + 1; // Target Billable Hours column
        $row[$ftTarget35Col] = '35 Workweek Hours';

        // FT: "40 Workweek Hours" - spans Target (40) + Actuals vs Committed (40) columns
        $ftTarget40Col = $ftActualBillableCol + 3; // Target Billable Hours (40) column
        $row[$ftTarget40Col] = '40 Workweek Hours';

        // FT: "NAD Data" - spans NAD Days + NAD Hours columns
        $ftNadCol = $ftActualBillableCol + 5; // NAD Data – In days column
        $row[$ftNadCol] = 'NAD Data';

        // PT: "20 Workweek Hours" - spans Target Billable Hours + Actuals vs Committed columns
        $ptTarget20Col = $ptActualBillableCol + 1; // PT Target Billable Hours column
        $row[$ptTarget20Col] = '20 Workweek Hours';

        // PT: "NAD Data" - spans NAD Days + NAD Hours columns
        $ptNadCol = $ptActualBillableCol + 3; // PT NAD Data – In days column
        $row[$ptNadCol] = 'NAD Data';

        return $row;
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
     * Build column headers (Row 4) - Only performance columns
     */
    private function buildColumnHeaders()
    {
        $row = array_fill(0, $this->totalColumns, '');

        $categoryCount = count($this->billableCategories);

        // === FT PERFORMANCE HEADERS ONLY (Target Billable Hours to NAD Data) ===
        $ftActualBillableCol = 3 + $categoryCount; // After NO + Name + Non-Billable + Categories + Actual Billable
        $row[$ftActualBillableCol + 1] = 'Target Billable Hours';
        $row[$ftActualBillableCol + 2] = 'Actuals vs Committed';
        $row[$ftActualBillableCol + 3] = 'Target Billable Hours';
        $row[$ftActualBillableCol + 4] = 'Actuals vs Committed';
        $row[$ftActualBillableCol + 5] = 'In days';
        $row[$ftActualBillableCol + 6] = 'In hours';

        // === PT PERFORMANCE HEADERS ONLY (Target Billable Hours to NAD Data) ===
        $ptStartCol = $this->ftTableColumns + 1;
        $ptActualBillableCol = $ptStartCol + 3 + $categoryCount; // PT: NO + Name + Non-Billable + Categories + Actual Billable
        $row[$ptActualBillableCol + 1] = 'Target Billable Hours';
        $row[$ptActualBillableCol + 2] = 'Actuals vs Committed';
        $row[$ptActualBillableCol + 3] = 'In days';
        $row[$ptActualBillableCol + 4] = 'In hours';

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
        $billableHours40 = $ftUser['performance']['target_hours_per_week'] == 35 ? 40 : $ftUser['performance']['target_hours_per_week'];
        $weekNumber = $ftUser['performance']['period_weeks'] ?? 1;
        $totalBillableHours40 = $billableHours40 == 40 ? $billableHours40 * $weekNumber : $targetHours;
        $row[$billableCol] = $billableHours === 0 ? '0' : ($billableHours ?: '0');             // Actual Billable Hours
        $row[$billableCol + 1] = $targetHours === 0 ? '0' : ($targetHours ?: '0');                 // Target Hours
        $row[$billableCol + 2] = $billableHours - $targetHours;                                    // Actual vs Target
        $row[$billableCol + 3] = $totalBillableHours40;                                            // 40 Hour Target
        $row[$billableCol + 4] = $billableHours - $totalBillableHours40;                           // Actual vs 40
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
        $row[1] = $regionName.' Total';                                                     // B: Label
        $row[2] = $ftTotals['non_billable'] === 0 ? '0' : ($ftTotals['non_billable'] ?: '0'); // C: Total Non-Billable

        // FT category totals
        foreach ($this->categoryColumnMap as $categoryId => $mapping) {
            $categoryTotal = $ftTotals['categories'][$categoryId] ?? 0;
            $row[$mapping['ft_col_index']] = $categoryTotal === 0 ? '0' : ($categoryTotal ?: '0');
        }

        $categoryCount = count($this->billableCategories);
        $billableCol = 3 + $categoryCount;

        $totalBillable = $ftTotals['billable'] === 0 ? '0' : ($ftTotals['billable'] ?: '0');
        $totalTarget = $ftTotals['target_hours'] === 0 ? '0' : ($ftTotals['target_hours'] ?: '0');

        $row[$billableCol] = $totalBillable;                // Total Billable
        $row[$billableCol + 1] = $totalTarget;                  // Total Target Hours
        $row[$billableCol + 2] = $totalBillable - $totalTarget; // Total Actuals vs Committed

        // Sum per-user "40-hr target" logic across the region
        $sum40Target = 0;
        foreach ($regionData['ft_users'] as $u) {
            // mirror the per-user logic used in buildFTUserRow
            $billableHours40 = (($u['performance']['target_hours_per_week'] ?? 35) == 35)
            ? 40
            : ($u['performance']['target_hours_per_week'] ?? 35);

            $weekNumber = $u['performance']['period_weeks'] ?? 1;
            $targetHoursUser = $u['target_hours'] ?? 35;

            $sum40Target += ($billableHours40 == 40)
            ? ($billableHours40 * $weekNumber)
            : $targetHoursUser;
        }

        $row[$billableCol + 3] = $sum40Target;                  // 40 Hour Target (summed)
        $row[$billableCol + 4] = $totalBillable - $sum40Target; // Actual vs 40

        // $row[$billableCol + 3] = count($regionData['ft_users']) * 40;                                  // Total 40 Hour Target (40 * user count)
        // $row[$billableCol + 4] = $totalBillable - (count($regionData['ft_users']) * 40);               // Total Actuals vs 40
        $row[$billableCol + 5] = $ftTotals['nad_count'] === 0 ? '0' : ($ftTotals['nad_count'] ?: '0'); // Total NAD Days
        $row[$billableCol + 6] = $ftTotals['nad_hours'] === 0 ? '0' : ($ftTotals['nad_hours'] ?: '0'); // Total NAD Hours

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

        $row[$ptStartCol + 1] = $regionName.' Total';                                                     // Name column: Label
        $row[$ptStartCol + 2] = $ptTotals['non_billable'] === 0 ? '0' : ($ptTotals['non_billable'] ?: '0'); // Total Non-Billable

        // PT category totals
        foreach ($this->categoryColumnMap as $categoryId => $mapping) {
            $categoryTotal = $ptTotals['categories'][$categoryId] ?? 0;
            $row[$ptStartCol + $mapping['pt_col_index']] = $categoryTotal === 0 ? '0' : ($categoryTotal ?: '0');
        }

        $categoryCount = count($this->billableCategories);
        $ptBillableCol = $ptStartCol + 3 + $categoryCount;

        $totalBillable = $ptTotals['billable'] === 0 ? '0' : ($ptTotals['billable'] ?: '0');
        $totalTarget = $ptTotals['target_hours'] === 0 ? '0' : ($ptTotals['target_hours'] ?: '0');

        $row[$ptBillableCol] = $totalBillable;                                                       // Total Billable
        $row[$ptBillableCol + 1] = $totalTarget;                                                         // Total Target Hours
        $row[$ptBillableCol + 2] = $totalBillable - $totalTarget;                                        // Total Actuals vs Committed
        $row[$ptBillableCol + 3] = $ptTotals['nad_count'] === 0 ? '0' : ($ptTotals['nad_count'] ?: '0'); // Total NAD Days
        $row[$ptBillableCol + 4] = $ptTotals['nad_hours'] === 0 ? '0' : ($ptTotals['nad_hours'] ?: '0'); // Total NAD Hours

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
        $row[1] = 'Full-Time Overall Summary';                                                // B: Label
        $row[2] = $ftTotals['non_billable'] === 0 ? '0' : ($ftTotals['non_billable'] ?: '0'); // C: Total Non-Billable

        // FT category totals
        foreach ($this->categoryColumnMap as $categoryId => $mapping) {
            $categoryTotal = $ftTotals['categories'][$categoryId] ?? 0;
            $row[$mapping['ft_col_index']] = $categoryTotal === 0 ? '0' : ($categoryTotal ?: '0');
        }

        $categoryCount = count($this->billableCategories);
        $billableCol = 3 + $categoryCount;

        $totalBillable = $ftTotals['billable'] === 0 ? '0' : ($ftTotals['billable'] ?: '0');
        $totalTarget = $ftTotals['target_hours'] === 0 ? '0' : ($ftTotals['target_hours'] ?: '0');

        $row[$billableCol] = $totalBillable;                // Total Billable
        $row[$billableCol + 1] = $totalTarget;                  // Total Target Hours
        $row[$billableCol + 2] = $totalBillable - $totalTarget; // Total Actuals vs Committed

        $sum40TargetAll = 0;
        foreach ($allFtUsers as $u) {
            $billableHours40 = (($u['performance']['target_hours_per_week'] ?? 35) == 35)
            ? 40
            : ($u['performance']['target_hours_per_week'] ?? 35);

            $weekNumber = $u['performance']['period_weeks'] ?? 1;
            $targetHoursUser = $u['target_hours'] ?? 35;

            $sum40TargetAll += ($billableHours40 == 40)
            ? ($billableHours40 * $weekNumber)
            : $targetHoursUser;
        }

        $row[$billableCol + 3] = $sum40TargetAll;                                                      // 40 Hour Target (summed)
        $row[$billableCol + 4] = $totalBillable - $sum40TargetAll;                                     // Actual vs 40
        // $row[$billableCol + 3] = count($allFtUsers) * 40;                                              // Total 40 Hour Target (40 * user count)
        // $row[$billableCol + 4] = $totalBillable - (count($allFtUsers) * 40);                           // Total Actuals vs 40
        $row[$billableCol + 5] = $ftTotals['nad_count'] === 0 ? '0' : ($ftTotals['nad_count'] ?: '0'); // Total NAD Days
        $row[$billableCol + 6] = $ftTotals['nad_hours'] === 0 ? '0' : ($ftTotals['nad_hours'] ?: '0'); // Total NAD Hours

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

        $row[$ptStartCol + 1] = 'Part-Time Overall Summary';                                            // PT Name column: Label
        $row[$ptStartCol + 2] = $ptTotals['non_billable'] === 0 ? 0 : ($ptTotals['non_billable'] ?: 0); // Total Non-Billable

        // PT category totals
        foreach ($this->categoryColumnMap as $categoryId => $mapping) {
            $categoryTotal = $ptTotals['categories'][$categoryId] ?? 0;
            $row[$ptStartCol + $mapping['pt_col_index']] = $categoryTotal === 0 ? '0' : ($categoryTotal ?: '0');
        }

        $categoryCount = count($this->billableCategories);
        $ptBillableCol = $ptStartCol + 3 + $categoryCount;

        $totalBillablePT = $ptTotals['billable'] === 0 ? '0' : ($ptTotals['billable'] ?: '0');
        $totalTargetPT = $ptTotals['target_hours'] === 0 ? '0' : ($ptTotals['target_hours'] ?: '0');

        $row[$ptBillableCol] = $totalBillablePT;                                                     // Total Billable
        $row[$ptBillableCol + 1] = $totalTargetPT;                                                       // Total Target Hours
        $row[$ptBillableCol + 2] = $totalBillablePT - $totalTargetPT;                                    // Total Actuals vs Committed
        $row[$ptBillableCol + 3] = $ptTotals['nad_count'] === 0 ? '0' : ($ptTotals['nad_count'] ?: '0'); // Total NAD Days
        $row[$ptBillableCol + 4] = $ptTotals['nad_hours'] === 0 ? '0' : ($ptTotals['nad_hours'] ?: '0'); // Total NAD Hours

        return $row;
    }

    /**
     * Build Region Count Table for Overall reports
     */
    private function buildRegionCountTable()
    {
        $tableRows = [];

        // Header row (starting from column B)
        $headerRow = array_fill(0, $this->totalColumns, '');
        $headerRow[1] = 'Region';
        $headerRow[2] = 'Full-Time';
        $headerRow[3] = 'Part-Time';
        $headerRow[4] = 'Total';
        $tableRows[] = $headerRow;

        $ftGrandTotal = 0;
        $ptGrandTotal = 0;

        // Data rows for each region (starting from column B)
        foreach ($this->regionGroups as $regionName => $regionData) {
            $ftCount = count($regionData['ft_users']);
            $ptCount = count($regionData['pt_users']);
            $totalCount = $ftCount + $ptCount;

            $ftGrandTotal += $ftCount;
            $ptGrandTotal += $ptCount;

            $dataRow = array_fill(0, $this->totalColumns, '');
            $dataRow[1] = $regionName;
            $dataRow[2] = $ftCount;
            $dataRow[3] = $ptCount;
            $dataRow[4] = $totalCount;
            $tableRows[] = $dataRow;
        }

        // Grand Total row (starting from column B)
        $totalRow = array_fill(0, $this->totalColumns, '');
        $totalRow[1] = 'Grand Total';
        $totalRow[2] = $ftGrandTotal;
        $totalRow[3] = $ptGrandTotal;
        $totalRow[4] = $ftGrandTotal + $ptGrandTotal;
        $tableRows[] = $totalRow;

        return $tableRows;
    }

    /**
     * Build Billable Tasks Table for Overall reports (FT+PT combined)
     */
    private function buildBillableTasksTable()
    {
        $tableRows = [];

        // Header row (starting from column B)
        $headerRow = array_fill(0, $this->totalColumns, '');
        $headerRow[1] = 'Billable Tasks Category';
        $headerRow[2] = 'Total Hours';
        $tableRows[] = $headerRow;

        // Collect all users from all regions (FT + PT combined)
        $allUsers = [];
        foreach ($this->regionGroups as $regionData) {
            $allUsers = array_merge($allUsers, $regionData['ft_users'], $regionData['pt_users']);
        }

        $categoryTotals = [];
        $grandTotal = 0;

        // Calculate totals for each category (starting from column B)
        foreach ($this->billableCategories as $category) {
            $categoryId = $category->id;
            $categoryTotal = 0;

            foreach ($allUsers as $user) {
                $categoryHours = $this->getCategoryHours($user, $categoryId);
                $categoryTotal += $categoryHours;
            }

            $categoryTotals[$categoryId] = $categoryTotal;
            $grandTotal += $categoryTotal;

            // Add category row
            $dataRow = array_fill(0, $this->totalColumns, '');
            $dataRow[1] = $category->cat_name;
            $dataRow[2] = $categoryTotal === 0 ? '0' : ($categoryTotal ?: '0');
            $tableRows[] = $dataRow;
        }

        // Grand Total row (starting from column B)
        $totalRow = array_fill(0, $this->totalColumns, '');
        $totalRow[1] = 'Grand Total';
        $totalRow[2] = $grandTotal === 0 ? '0' : ($grandTotal ?: '0');
        $tableRows[] = $totalRow;

        return $tableRows;
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

        // Apply styling for summary tables (only for overall reports)
        if ($this->reportData['report_type'] === 'overall') {
            $this->applyRegionCountTableStyling($sheet);
            $this->applyBillableTasksTableStyling($sheet);
        }

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
        $sheet->getStyle('A1:'.$ftEndCol.'4')->applyFromArray([
            'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => 'FFFFFF']], // White text
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                // 'startColor' => ['rgb' => 'B4C7E7'], // Light blue for FT
                'startColor' => ['rgb' => '1F4E79'], // Dark blue for PT
            ],
            'borders' => ['outline' => ['borderStyle' => Border::BORDER_MEDIUM]],
        ]);

        // Part Time header with dark blue background and white text
        $ptStartCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($this->ftTableColumns + 2);
        $ptEndCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($this->totalColumns);

        $sheet->mergeCells($ptStartCol.'1:'.$ptEndCol.'1');
        $sheet->getStyle($ptStartCol.'1:'.$ptEndCol.'4')->applyFromArray([
            'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => 'FFFFFF']], // White text
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                // 'startColor' => ['rgb' => '1F4E79'], // Dark blue for PT
                'startColor' => ['rgb' => '006400'], // Dark green for PT
            ],
            'borders' => ['outline' => ['borderStyle' => Border::BORDER_MEDIUM]],
        ]);

        // Row 2: General Tasks header styling (for both FT and PT)
        $sheet->getRowDimension('2')->setRowHeight(20);

        // Row 3: Grouped headers styling (for both FT and PT)
        $sheet->getRowDimension('3')->setRowHeight(20);

        // Row 4: Column headers
        $sheet->getRowDimension('4')->setRowHeight(24);
        $totalColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($this->totalColumns);

        // FT table headers (light blue)
        $sheet->getStyle('A4:'.$ftEndCol.'4')->applyFromArray([
            'font' => ['bold' => true, 'size' => 11],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
            // 'fill'      => [
            //     'fillType'   => Fill::FILL_SOLID,
            //     'startColor' => ['rgb' => 'B4C7E7'], // Light blue for FT headers
            // ],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ]);

        // PT table headers (dark blue with white text)
        $sheet->getStyle($ptStartCol.'4:'.$ptEndCol.'4')->applyFromArray([
            'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => 'FFFFFF']], // White text
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
            // 'fill'      => [
            //     'fillType'   => Fill::FILL_SOLID,
            //     'startColor' => ['rgb' => '1F4E79'], // Dark blue for PT headers
            // ],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ]);

        $categoryCount = count($this->billableCategories);

        if ($categoryCount > 0) {
            // === ROW 2: GENERAL TASKS MERGING (FT + PT) ===

            // FT General Tasks: Merge billable categories + Actual Billable Hours (D2 to column after categories)
            $ftGeneralStart = 'D';                    // First billable category column
            $ftGeneralEndIndex = 3 + $categoryCount + 1; // Categories + Actual Billable Hours
            $ftGeneralEnd = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($ftGeneralEndIndex);
            $sheet->mergeCells($ftGeneralStart.'2:'.$ftGeneralEnd.'2');
            $sheet->getStyle('A2:'.$ftEndCol.'2')->applyFromArray([
                'font' => ['bold' => true, 'size' => 11],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                // 'fill'      => [
                //     'fillType'   => Fill::FILL_SOLID,
                //     'startColor' => ['rgb' => 'E8F4FD'], // Light blue for FT
                // ],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
            ]);

            // PT General Tasks: Same structure
            $ptStartColIndex = $this->ftTableColumns + 1;
            $ptGeneralStartIndex = $ptStartColIndex + 3 + 1;                  // PT start + NO + Name + Non-Billable + first category
            $ptGeneralEndIndex = $ptStartColIndex + 3 + $categoryCount + 1; // PT Categories + Actual Billable Hours
            $ptGeneralStart = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($ptGeneralStartIndex);
            $ptGeneralEnd = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($ptGeneralEndIndex);
            $sheet->mergeCells($ptGeneralStart.'2:'.$ptGeneralEnd.'2');
            $sheet->getStyle($ptStartCol.'2:'.$ptEndCol.'2')->applyFromArray([
                'font' => ['bold' => true, 'size' => 11],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                // 'fill'      => [
                //     'fillType'   => Fill::FILL_SOLID,
                //     'startColor' => ['rgb' => 'F0F8F0'], // Light green for PT
                // ],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
            ]);
        }

        // FT "35 Workweek Hours" - Target Billable Hours + Actuals vs Committed
        $ftActualBillableCol = 3 + $categoryCount;
        $ft35Start = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($ftActualBillableCol + 1 + 1);
        $ft35End = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($ftActualBillableCol + 2 + 1);
        $sheet->mergeCells($ft35Start.'2:'.$ft35End.'2');
        $sheet->mergeCells($ft35Start.'3:'.$ft35End.'3');
        $sheet->getStyle($ft35Start.'3:'.$ft35End.'3')->applyFromArray([
            'font' => ['bold' => true, 'size' => 11],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            // 'fill'      => [
            //     'fillType'   => Fill::FILL_SOLID,
            //     'startColor' => ['rgb' => 'D9E2F3'], // Medium blue for FT
            // ],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ]);

        // FT "40 Workweek Hours" - Target (40) + Actuals vs Committed (40)
        $ft40Start = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($ftActualBillableCol + 3 + 1);
        $ft40End = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($ftActualBillableCol + 4 + 1);
        $sheet->mergeCells($ft40Start.'2:'.$ft40End.'2');
        $sheet->mergeCells($ft40Start.'3:'.$ft40End.'3');
        $sheet->getStyle($ft40Start.'3:'.$ft40End.'3')->applyFromArray([
            'font' => ['bold' => true, 'size' => 11],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            // 'fill'      => [
            //     'fillType'   => Fill::FILL_SOLID,
            //     'startColor' => ['rgb' => 'D9E2F3'], // Medium blue for FT
            // ],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ]);

        // FT "NAD Data" - NAD Days + NAD Hours
        $ftNADStart = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($ftActualBillableCol + 5 + 1);
        $ftNADEnd = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($ftActualBillableCol + 6 + 1);
        $sheet->mergeCells($ftNADStart.'2:'.$ftNADEnd.'2');
        $sheet->mergeCells($ftNADStart.'3:'.$ftNADEnd.'3');
        $sheet->getStyle($ftNADStart.'3:'.$ftNADEnd.'3')->applyFromArray([
            'font' => ['bold' => true, 'size' => 11],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            // 'fill'      => [
            //     'fillType'   => Fill::FILL_SOLID,
            //     'startColor' => ['rgb' => 'D9E2F3'], // Medium blue for FT
            // ],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ]);

        // PT "20 Workweek Hours" - Target Billable Hours + Actuals vs Committed
        $ptStartColIndex = $this->ftTableColumns + 1;
        $ptActualBillableCol = $ptStartColIndex + 3 + $categoryCount + 1;
        $pt20Start = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($ptActualBillableCol + 1);
        $pt20End = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($ptActualBillableCol + 2);
        $sheet->mergeCells($pt20Start.'2:'.$pt20End.'2');
        $sheet->mergeCells($pt20Start.'3:'.$pt20End.'3');
        $sheet->getStyle($pt20Start.'3:'.$pt20End.'3')->applyFromArray([
            'font' => ['bold' => true, 'size' => 11],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'wrapText' => true],
            // 'fill'      => [
            //     'fillType'   => Fill::FILL_SOLID,
            //     'startColor' => ['rgb' => 'E2EFDA'], // Medium green for PT
            // ],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ]);

        // PT "NAD Data" - NAD Days + NAD Hours
        $ptNADStart = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($ptActualBillableCol + 3);
        $ptNADEnd = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($ptActualBillableCol + 4);
        $sheet->mergeCells($ptNADStart.'2:'.$ptNADEnd.'2');
        $sheet->mergeCells($ptNADStart.'3:'.$ptNADEnd.'3');
        $sheet->getStyle($ptNADStart.'3:'.$ptNADEnd.'3')->applyFromArray([
            'font' => ['bold' => true, 'size' => 11],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            // 'fill'      => [
            //     'fillType'   => Fill::FILL_SOLID,
            //     'startColor' => ['rgb' => 'E2EFDA'], // Medium green for PT
            // ],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ]);

        // Loop from column index 1 (A) up to one before $ft35Start
        for ($col = 1; $col < \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($ft35Start); $col++) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);

            $range = $colLetter.'3:'.$colLetter.'4';

            $sheet->mergeCells($range);
            $sheet->getStyle($range)->applyFromArray([
                'font' => ['bold' => true, 'size' => 11],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                    'wrapText' => true,
                ],
                // 'fill'      => [
                //     'fillType'   => Fill::FILL_SOLID,
                //     'startColor' => ['rgb' => 'B4C7E7'],
                // ],
                'borders' => [
                    'allBorders' => ['borderStyle' => Border::BORDER_THIN],
                ],
            ]);
        }

        // Loop PT columns from $ptStartCol until just before $pt20Start
        for (
            $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($ptStartCol);
            $col < \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($pt20Start);
            $col++
        ) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
            $range = $colLetter.'3:'.$colLetter.'4';

            $sheet->mergeCells($range);
            $sheet->getStyle($range)->applyFromArray([
                'font' => ['bold' => true, 'size' => 11],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                    'wrapText' => true, // enable wrap
                ],
                // 'fill'      => [
                //     'fillType'   => Fill::FILL_SOLID,
                //     'startColor' => ['rgb' => 'B4C7E7'],
                // ],
                'borders' => [
                    'allBorders' => ['borderStyle' => Border::BORDER_THIN],
                ],
            ]);
        }

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
                $sheet->getRowDimension($row)->setRowHeight(22);
            }
            if ($row == 4) {
                // Explicitly set row 3 height = 30
                $sheet->getRowDimension($row)->setRowHeight(26);
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
        $sheet->getStyle($ftBillableCol.':'.$ftBillableCol)
            ->getNumberFormat()
            ->setFormatCode(NumberFormat::FORMAT_NUMBER_00);

        // FT Performance columns - width 8
        for ($i = 1; $i <= 5; $i++) { // Target Hours, Actual vs Target, 40 Hour Target, Actual vs 40, NAD Days
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(3 + $categoryCount + 1 + $i);
            $sheet->getColumnDimension($colLetter)->setWidth(10);
            $sheet->getStyle($colLetter.':'.$colLetter)
                ->getNumberFormat()
                ->setFormatCode('#,##0');
        }

        // FT NAD Hours - width 12
        $ftNadHoursCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(3 + $categoryCount + 1 + 5 + 1);
        $sheet->getColumnDimension($ftNadHoursCol)->setWidth(10);
        $sheet->getStyle($ftNadHoursCol.':'.$ftNadHoursCol)
            ->getNumberFormat()
            ->setFormatCode('#,##0');

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
        $sheet->getStyle($ptNadHoursCol.':'.$ptNadHoursCol)
            ->getNumberFormat()
            ->setFormatCode('#,##0'); // or NumberFormat::FORMAT_NUMBER (both show no decimals)

        // Number format for hours: #,##0.00 (dynamic based on table structure)
        $categoryCount = count($this->billableCategories);

        // FT table hours columns
        for ($i = 3; $i < 3 + $categoryCount + 1; $i++) { // Non-billable + categories + billable
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i);
            $sheet->getStyle($colLetter.'5:'.$colLetter.$dataRowCount)
                ->getNumberFormat()
                ->setFormatCode(NumberFormat::FORMAT_NUMBER_00);
        }

        // PT table hours columns
        $ptDataStart = $this->ftTableColumns + 2;
        for ($i = $ptDataStart + 2; $i < $ptDataStart + 2 + $categoryCount + 1; $i++) { // Non-billable + categories + billable
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i);
            $sheet->getStyle($colLetter.'5:'.$colLetter.$dataRowCount)
                ->getNumberFormat()
                ->setFormatCode(NumberFormat::FORMAT_NUMBER_00);
        }

        // NAD hours columns (last columns in each table)
        $ftNadHoursCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(3 + $categoryCount + 1 + 5 + 1); // Last FT column
        $ptNadHoursCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($this->totalColumns);            // Last PT column

        $sheet->getStyle($ftNadHoursCol.'5:'.$ftNadHoursCol.$dataRowCount)
            ->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER);
        $sheet->getStyle($ptNadHoursCol.'5:'.$ptNadHoursCol.$dataRowCount)
            ->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER);

        // Number format for NAD days: 0
        $ftNadDaysCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(3 + $categoryCount + 1 + 5); // Second to last FT column
        $ptNadDaysCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($this->totalColumns - 1);    // Second to last PT column

        $sheet->getStyle($ftNadDaysCol.'5:'.$ftNadDaysCol.$dataRowCount)
            ->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER);
        $sheet->getStyle($ptNadDaysCol.'5:'.$ptNadDaysCol.$dataRowCount)
            ->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER);

        // Number format for deltas: +#,##0.00;-#,##0.00;0.00
        $ftDelta1Col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(3 + $categoryCount + 1 + 1);                // FT Actual vs Target
        $ftDelta2Col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(3 + $categoryCount + 1 + 3);                // FT Actual vs 40
        $ptDeltaCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($ptDataStart + 2 + $categoryCount + 1 + 1); // PT Actual vs Target

        foreach ([$ftDelta1Col, $ftDelta2Col, $ptDeltaCol] as $col) {
            $sheet->getStyle($col.'5:'.$col.$dataRowCount)
                ->getNumberFormat()
                ->setFormatCode(NumberFormat::FORMAT_NUMBER);
        }
    }

    /**
     * Apply conditional formatting for performance deltas (excluding region styling rows)
     */
    private function applyConditionalFormatting($sheet, $dataRowCount)
    {
        $categoryCount = count($this->billableCategories);

        // Dynamic delta columns (only Actuals vs Committed, not Target columns)
        $ftDelta1Col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(3 + $categoryCount + 1 + 2);                             // FT Actual vs Target
        $ftDelta2Col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(3 + $categoryCount + 1 + 4);                             // FT Actual vs 40
        $ptDeltaCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($this->ftTableColumns + 2 + 2 + $categoryCount + 1 + 2); // PT Actual vs Target

        $deltaColumns = [$ftDelta1Col, $ftDelta2Col, $ptDeltaCol];

        // Get all rows that should be excluded from conditional formatting (region headers, totals, overall summary)
        $excludedRows = [];
        foreach ($this->ftRowData as $ftRow) {
            if (in_array($ftRow['type'], ['region_header', 'region_total', 'overall_summary'])) {
                $excludedRows[] = $ftRow['row'];
            }
        }
        foreach ($this->ptRowData as $ptRow) {
            if (in_array($ptRow['type'], ['region_header', 'region_total', 'overall_summary'])) {
                $excludedRows[] = $ptRow['row'];
            }
        }

        foreach ($deltaColumns as $col) {
            // Apply conditional formatting to each individual row, skipping excluded rows
            for ($row = 5; $row <= $dataRowCount; $row++) {
                // Skip rows with region styling
                if (in_array($row, $excludedRows)) {
                    continue;
                }

                $cellRange = $col.$row;

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

                $sheet->getStyle($cellRange)->setConditionalStyles($conditionalStyles);
            }
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
        if ($ftEndRow > 4) {
            $sheet->getStyle('A4:'.$ftEndCol.$ftEndRow)->applyFromArray([
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
        if ($ptEndRow > 4) {
            $sheet->getStyle($ptStartCol.'4:'.$ptEndCol.$ptEndRow)->applyFromArray([
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
        $ftEndRow = 4; // Start from headers

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
        $ptEndRow = 4; // Start from headers

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
                            'borderStyle' => Border::BORDER_THIN,
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
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => '000000'],
                        ],
                    ],
                ]);
            }
        }
    }

    /**
     * Apply styling for Region Count Table (Dark Orange theme)
     */
    private function applyRegionCountTableStyling($sheet)
    {
        foreach ($this->regionCountTableRows as $row) {
            $rowNum = $row['row'];
            $type = $row['type'];

            if ($type === 'header' || $type === 'grand_total') {
                // Header and Grand Total - Dark orange background with white text
                $sheet->getStyle('B'.$rowNum.':E'.$rowNum)->applyFromArray([
                    'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => 'FFFFFF']], // White text
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'C65911'], // Dark orange
                    ],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => '000000'],
                        ],
                    ],
                ]);
            } elseif ($type === 'data') {
                // Data rows - Light orange background with bold region names
                $sheet->getStyle('B'.$rowNum.':E'.$rowNum)->applyFromArray([
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'F4B183'], // Light orange
                    ],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => '000000'],
                        ],
                    ],
                ]);

                // Make region name bold (column B)
                $sheet->getStyle('B'.$rowNum)->applyFromArray([
                    'font' => ['bold' => true],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
                ]);
            }
        }
    }

    /**
     * Apply styling for Billable Tasks Table (Dark Purple theme)
     */
    private function applyBillableTasksTableStyling($sheet)
    {
        foreach ($this->billableTasksTableRows as $row) {
            $rowNum = $row['row'];
            $type = $row['type'];

            if ($type === 'header' || $type === 'grand_total') {
                // Header and Grand Total - Dark purple background with white text
                $sheet->getStyle('B'.$rowNum.':C'.$rowNum)->applyFromArray([
                    'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => 'FFFFFF']], // White text
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '5B2C6F'], // Dark purple
                    ],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => '000000'],
                        ],
                    ],
                ]);
            } elseif ($type === 'data') {
                // Data rows - Light purple background with bold category names
                $sheet->getStyle('B'.$rowNum.':C'.$rowNum)->applyFromArray([
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'D5A6BD'], // Light purple
                    ],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => '000000'],
                        ],
                    ],
                ]);

                // Make category name bold (column B)
                $sheet->getStyle('B'.$rowNum)->applyFromArray([
                    'font' => ['bold' => true],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
                ]);
            }
        }
    }
}
