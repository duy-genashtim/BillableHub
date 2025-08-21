<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Date Time Helpers Test</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 3px solid #007bff; padding-bottom: 10px; }
        h2 { color: #007bff; margin-top: 30px; }
        .function-block { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; background: #f9f9f9; }
        .parameters { background: #e9ecef; padding: 10px; margin: 10px 0; border-radius: 3px; }
        .result { background: #f8f9fa; padding: 10px; margin: 10px 0; border-radius: 3px; overflow-x: auto; }
        pre { margin: 0; white-space: pre-wrap; word-wrap: break-word; }
        .error { background: #f8d7da; color: #721c24; padding: 10px; border-radius: 3px; margin: 10px 0; }
        .controls { background: #e3f2fd; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .controls input, .controls button { margin: 5px; padding: 8px; border: 1px solid #ddd; border-radius: 3px; }
        .controls button { background: #007bff; color: white; cursor: pointer; }
        .controls button:hover { background: #0056b3; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #f2f2f2; }
        .count { color: #28a745; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Date Time Helpers Test Results</h1>
        
        <div class="controls">
            <form method="GET">
                <label>Year: <input type="number" name="year" value="{{ $year }}" min="2024"></label>
                <label>Week Number: <input type="number" name="week_number" value="{{ $weekNumber }}" min="1" max="52"></label>
                <label>Month Number: <input type="number" name="month_number" value="{{ $monthNumber }}" min="1" max="13"></label>
                <label>Start Date: <input type="date" name="start_date" value="{{ $startDate }}"></label>
                <label>End Date: <input type="date" name="end_date" value="{{ $endDate }}"></label>
                <button type="submit">Test Functions</button>
            </form>
        </div>

        @if(isset($testResults['error']))
            <div class="error">
                <h3>Error:</h3>
                <pre>{{ $testResults['error']['message'] }}</pre>
            </div>
        @else
            @foreach($testResults as $key => $test)
                <div class="function-block">
                    <h2>{{ $test['function'] }}()</h2>
                    
                    <div class="parameters">
                        <strong>Parameters:</strong>
                        <pre>{{ json_encode($test['parameters'], JSON_PRETTY_PRINT) }}</pre>
                    </div>

                    @if(isset($test['count']))
                        <div class="count">Count: {{ $test['count'] }} items</div>
                    @endif

                    <div class="result">
                        <strong>Result:</strong>
                        @if(in_array($test['function'], ['getWeekListForYear', 'getMonthListForYear', 'getDateRangeWeeks', 'getDateRangeMonths']) && is_array($test['result']))
                            <table>
                                <thead>
                                    <tr>
                                        @if($test['function'] === 'getWeekListForYear' || $test['function'] === 'getDateRangeWeeks')
                                            <th>Week #</th>
                                            <th>Start Date</th>
                                            <th>End Date</th>
                                            <th>Year</th>
                                            <th>Label</th>
                                        @else
                                            <th>Month #</th>
                                            <th>Title</th>
                                            <th>Subtitle</th>
                                            <th>Start Date</th>
                                            <th>End Date</th>
                                        @endif
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach(array_slice($test['result'], 0, 10) as $item)
                                        <tr>
                                            @if($test['function'] === 'getWeekListForYear' || $test['function'] === 'getDateRangeWeeks')
                                                <td>{{ $item['week_number'] }}</td>
                                                <td>{{ $item['start_date'] }}</td>
                                                <td>{{ $item['end_date'] }}</td>
                                                <td>{{ $item['year'] }}</td>
                                                <td>{{ $item['label'] }}</td>
                                            @else
                                                <td>{{ $item['value'] ?? 'N/A' }}</td>
                                                <td>{{ $item['title'] ?? 'N/A' }}</td>
                                                <td>{{ $item['subtitle'] ?? 'N/A' }}</td>
                                                <td>{{ $item['start_date'] ?? 'N/A' }}</td>
                                                <td>{{ $item['end_date'] ?? 'N/A' }}</td>
                                            @endif
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            @if(count($test['result']) > 10)
                                <p><em>Showing first 10 items of {{ count($test['result']) }} total</em></p>
                            @endif
                        @else
                            <pre>{{ json_encode($test['result'], JSON_PRETTY_PRINT) }}</pre>
                        @endif
                    </div>
                </div>
            @endforeach
        @endif

        <div style="margin-top: 40px; padding-top: 20px; border-top: 1px solid #ddd; color: #666;">
            <p><strong>Test Info:</strong></p>
            <ul>
                <li>Current Time: {{ now()->format('Y-m-d H:i:s T') }}</li>
                <li>App Timezone: {{ config('app.timezone') }}</li>
                <li>Start Year: {{ config('constants.start_year') }}</li>
                <li>Week Start: {{ config('constants.week_start') }}</li>
                <li>Weeks Per Year: {{ config('constants.week_per_year') }}</li>
            </ul>
        </div>
    </div>
</body>
</html>