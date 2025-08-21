<?php

// Mock config for testing
if (! function_exists('config')) {
    function config($key, $default = null)
    {
        $configs = [
            'constants.start_year'    => 2024,
            'constants.week_start'    => '2024-01-15',
            'constants.week_per_year' => 52,
            'app.timezone'            => 'Asia/Singapore',
        ];
        return $configs[$key] ?? $default;
    }
}

// Mock Carbon for testing
class Carbon
{
    private $date;

    public function __construct($date = 'now', $timezone = null)
    {
        $this->date = new DateTime($date, $timezone ? new DateTimeZone($timezone) : null);
    }

    public static function parse($date, $timezone = null)
    {
        return new self($date, $timezone);
    }

    public static function now($timezone = null)
    {
        return new self('now', $timezone);
    }

    public function format($format)
    {
        return $this->date->format($format);
    }

    public function copy()
    {
        return clone $this;
    }

    public function addDays($days)
    {
        $this->date->add(new DateInterval("P{$days}D"));
        return $this;
    }

    public function subWeeks($weeks)
    {
        $this->date->sub(new DateInterval("P{$weeks}W"));
        return $this;
    }

    public function startOfDay()
    {
        $this->date->setTime(0, 0, 0);
        return $this;
    }

    public function endOfDay()
    {
        $this->date->setTime(23, 59, 59);
        return $this;
    }

    public function between($start, $end)
    {
        return $this->date >= $start->date && $this->date <= $end->date;
    }

    public function lte($other)
    {
        return $this->date <= $other->date;
    }

    public function gte($other)
    {
        return $this->date >= $other->date;
    }

    public function gt($other)
    {
        return $this->date > $other->date;
    }

    public function __get($property)
    {
        if ($property === 'year') {
            return (int) $this->date->format('Y');
        }
        return null;
    }
}

// Mock Log class for testing
class Log
{
    public static function warning($message)
    {
        // Silent for testing
    }
}
require_once 'helpers.php';
require_once 'date_time_helpers.php';
echo "<!DOCTYPE html>
<html>
<head>
    <title>Date Time Helper Functions Test</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background-color: #f5f5f5; }
        .section { background-color: white; padding: 20px; margin-bottom: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .section h2 { color: #333; border-bottom: 2px solid #007cba; padding-bottom: 10px; }
        .section h3 { color: #555; margin-top: 25px; }
        pre { background-color: #f8f9fa; padding: 15px; border-radius: 5px; overflow-x: auto; }
        .result { margin: 10px 0; }
        .error { color: red; background-color: #ffe6e6; padding: 10px; border-radius: 5px; }
        .success { color: green; background-color: #e6ffe6; padding: 10px; border-radius: 5px; }
    </style>
</head>
<body>";

echo "<div class='section'>";
echo "<h2>Date Time Helper Functions Test Results</h2>";
echo "<p><strong>Test Date:</strong> " . date('Y-m-d H:i:s') . "</p>";
echo "</div>";

// Test Week Functions
echo "<div class='section'>";
echo "<h2>Week Functions</h2>";

try {
    echo "<h3>getWeekListForYear(2024) - First 5 weeks:</h3>";
    $weeks2024 = getWeekListForYear(2024);
    echo "<pre>" . print_r(array_slice($weeks2024, 0, 5), true) . "</pre>";
    echo "<div class='success'>Total weeks for 2024: " . count($weeks2024) . "</div>";

    echo "<h3>getCurrentWeek():</h3>";
    $currentWeek = getCurrentWeek();
    echo "<pre>" . print_r($currentWeek, true) . "</pre>";

    echo "<h3>getCurrentWeekNumber():</h3>";
    $currentWeekNumber = getCurrentWeekNumber();
    echo "<div class='result'>Current Week Number: <strong>{$currentWeekNumber}</strong></div>";

    echo "<h3>getLastWeek():</h3>";
    $lastWeek = getLastWeek();
    echo "<pre>" . print_r($lastWeek, true) . "</pre>";

    echo "<h3>getWeekByNumber(1, 2024):</h3>";
    $weekByNumber = getWeekByNumber(1, 2024);
    echo "<pre>" . print_r($weekByNumber, true) . "</pre>";

} catch (Exception $e) {
    echo "<div class='error'>Error testing week functions: " . $e->getMessage() . "</div>";
}

echo "</div>";

// Test Month Functions
echo "<div class='section'>";
echo "<h2>Month Functions</h2>";

try {
    echo "<h3>getMonthListForYear(2024) - First 3 months:</h3>";
    $months2024 = getMonthListForYear(2024);
    echo "<pre>" . print_r(array_slice($months2024, 0, 3), true) . "</pre>";
    echo "<div class='success'>Total months for 2024: " . count($months2024) . "</div>";

    echo "<h3>getCurrentMonth():</h3>";
    $currentMonth = getCurrentMonth();
    echo "<pre>" . print_r($currentMonth, true) . "</pre>";

    echo "<h3>getCurrentMonthNumber():</h3>";
    $currentMonthNumber = getCurrentMonthNumber();
    echo "<div class='result'>Current Month Number: <strong>{$currentMonthNumber}</strong></div>";

    echo "<h3>getLastMonth():</h3>";
    $lastMonth = getLastMonth();
    echo "<pre>" . print_r($lastMonth, true) . "</pre>";

    echo "<h3>getMonthByNumber(1, 2024):</h3>";
    $monthByNumber = getMonthByNumber(1, 2024);
    echo "<pre>" . print_r($monthByNumber, true) . "</pre>";

} catch (Exception $e) {
    echo "<div class='error'>Error testing month functions: " . $e->getMessage() . "</div>";
}

echo "</div>";

// Test Range Functions
echo "<div class='section'>";
echo "<h2>Date Range Functions</h2>";

try {
    echo "<h3>getDateRangeWeeks('2024-01-15', '2024-02-11', 2024):</h3>";
    $rangeWeeks = getDateRangeWeeks('2024-01-15', '2024-02-11', 2024);
    echo "<pre>" . print_r($rangeWeeks, true) . "</pre>";

    echo "<h3>getDateRangeMonths('2024-01-15', '2024-03-10', 2024):</h3>";
    $rangeMonths = getDateRangeMonths('2024-01-15', '2024-03-10', 2024);
    echo "<pre>" . print_r($rangeMonths, true) . "</pre>";

} catch (Exception $e) {
    echo "<div class='error'>Error testing range functions: " . $e->getMessage() . "</div>";
}

echo "</div>";

// Test Configuration
echo "<div class='section'>";
echo "<h2>Configuration Values</h2>";
echo "<div class='result'>";
echo "<strong>Start Year:</strong> " . config('constants.start_year') . "<br>";
echo "<strong>Week Start:</strong> " . config('constants.week_start') . "<br>";
echo "<strong>Weeks Per Year:</strong> " . config('constants.week_per_year') . "<br>";
echo "<strong>Timezone:</strong> " . config('app.timezone') . "<br>";
echo "</div>";
echo "</div>";

echo "</body></html>";
