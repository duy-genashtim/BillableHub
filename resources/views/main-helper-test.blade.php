<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Main Helper Functions Test</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1400px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 3px solid #28a745; padding-bottom: 10px; }
        h2 { color: #28a745; margin-top: 30px; }
        h3 { color: #6c757d; margin-top: 20px; }
        .function-block { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; background: #f9f9f9; }
        .parameters { background: #e9ecef; padding: 10px; margin: 10px 0; border-radius: 3px; }
        .result { background: #f8f9fa; padding: 10px; margin: 10px 0; border-radius: 3px; overflow-x: auto; max-height: 400px; overflow-y: auto; }
        .error { background: #f8d7da; color: #721c24; padding: 10px; border-radius: 3px; margin: 10px 0; }
        .success { background: #d4edda; color: #155724; padding: 10px; border-radius: 3px; margin: 10px 0; }
        .warning { background: #fff3cd; color: #856404; padding: 10px; border-radius: 3px; margin: 10px 0; }
        pre { margin: 0; white-space: pre-wrap; word-wrap: break-word; font-size: 12px; }
        .controls { background: #e8f5e8; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .controls input, .controls select, .controls button { margin: 5px; padding: 8px; border: 1px solid #ddd; border-radius: 3px; }
        .controls button { background: #28a745; color: white; cursor: pointer; }
        .controls button:hover { background: #218838; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin: 10px 0; }
        .summary-box { background: #e3f2fd; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .summary-item { margin: 5px 0; }
        .calculation-summary { background: #f0f8ff; border-left: 4px solid #007bff; padding: 10px; margin: 10px 0; }
        .period-breakdown { background: #f9f9f9; border: 1px solid #dee2e6; border-radius: 3px; margin: 5px 0; padding: 10px; }
        .status-success { color: #28a745; font-weight: bold; }
        .status-error { color: #dc3545; font-weight: bold; }
        .user-info { background: #fff3cd; padding: 10px; border-radius: 3px; margin-bottom: 15px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Main Helper Functions Test Results</h1>
        
        <div class="controls">
            <form method="GET">
                <label>User ID: 
                    <select name="user_id">
                        @foreach($availableUsers as $availableUser)
                            <option value="{{ $availableUser->id }}" {{ $userId == $availableUser->id ? 'selected' : '' }}>
                                {{ $availableUser->id }} - {{ $availableUser->full_name ?? $availableUser->email }}
                            </option>
                        @endforeach
                    </select>
                </label>
                <label>Start Date: <input type="date" name="start_date" value="{{ $startDate }}"></label>
                <label>End Date: <input type="date" name="end_date" value="{{ $endDate }}"></label>
                <label>Week Number: <input type="number" name="week_number" value="{{ $weekNumber }}" min="1" max="52"></label>
                <label>Month Number: <input type="number" name="month_number" value="{{ $monthNumber }}" min="1" max="13"></label>
                <label>Year: <input type="number" name="year" value="{{ $year }}" min="2024"></label>
                <button type="submit">Test Functions</button>
            </form>
        </div>

        @if($user)
            <div class="user-info">
                <strong>Testing with User:</strong> {{ $user->full_name ?? $user->email }} (ID: {{ $user->id }})
            </div>
        @endif

        @if(!empty($errors))
            @foreach($errors as $error)
                <div class="error">
                    <strong>Error:</strong> {{ $error }}
                </div>
            @endforeach
        @endif

        @if(!empty($testResults))
            @foreach($testResults as $key => $test)
                <div class="function-block">
                    <h2>{{ $test['function'] }}()</h2>
                    
                    <div class="parameters">
                        <strong>Parameters:</strong>
                        <pre>{{ json_encode($test['parameters'], JSON_PRETTY_PRINT) }}</pre>
                    </div>

                    @if(isset($test['error']))
                        <div class="error">
                            <strong>Function Error:</strong> {{ $test['error'] }}
                        </div>
                    @elseif(isset($test['result']))
                        @php $result = $test['result']; @endphp
                        
                        @if(is_array($result) && isset($result['success']))
                            <div class="{{ $result['success'] ? 'success' : 'error' }}">
                                <strong>Status:</strong> 
                                <span class="{{ $result['success'] ? 'status-success' : 'status-error' }}">
                                    {{ $result['success'] ? 'SUCCESS' : 'FAILED' }}
                                </span>
                            </div>
                        @endif

                        @if(is_array($result) && isset($result['target_calculations']) && !empty($result['target_calculations']))
                            <h3>Target Calculations Summary</h3>
                            @foreach($result['target_calculations'] as $calc)
                                <div class="calculation-summary">
                                    <div class="grid">
                                        <div>
                                            <strong>Work Status:</strong> {{ $calc['work_status'] }}<br>
                                            <strong>Target Hours/Week:</strong> {{ $calc['target_hours_per_week'] }}<br>
                                            <strong>Total Target Hours:</strong> {{ $calc['target_total_hours'] }}
                                        </div>
                                        <div>
                                            <strong>Period:</strong> {{ $calc['period_weeks'] }} weeks ({{ $calc['period_days'] }} days)<br>
                                            <strong>Target ID:</strong> {{ $calc['target_id'] }}
                                        </div>
                                    </div>
                                    
                                    @if(isset($calc['period_breakdown']) && !empty($calc['period_breakdown']))
                                        <h4>Period Breakdown:</h4>
                                        @foreach($calc['period_breakdown'] as $period)
                                            <div class="period-breakdown">
                                                <div class="grid">
                                                    <div>
                                                        <strong>Period:</strong> {{ $period['period_start'] }} to {{ $period['period_end'] }}<br>
                                                        <strong>Work Status:</strong> {{ $period['work_status_display'] }}<br>
                                                        <strong>Duration:</strong> {{ $period['weeks'] }} weeks ({{ $period['days'] }} days)
                                                    </div>
                                                    <div>
                                                        <strong>Hours/Week:</strong> {{ $period['hours_per_week'] }}<br>
                                                        <strong>Target Hours:</strong> {{ $period['target_hours'] }}<br>
                                                        <strong>Week Range:</strong> {{ $period['week_start'] }} - {{ $period['week_end'] }}
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    @endif
                                </div>
                            @endforeach
                        @endif

                        @if(is_array($result) && isset($result['week_info']))
                            <div class="summary-box">
                                <h3>Week Information</h3>
                                <div class="summary-item"><strong>Week Number:</strong> {{ $result['week_info']['week_number'] }}</div>
                                <div class="summary-item"><strong>Date Range:</strong> {{ $result['week_info']['start_date'] }} to {{ $result['week_info']['end_date'] }}</div>
                                <div class="summary-item"><strong>Label:</strong> {{ $result['week_info']['label'] }}</div>
                                <div class="summary-item"><strong>Year:</strong> {{ $result['week_info']['year'] }}</div>
                            </div>
                        @endif

                        @if(is_array($result) && isset($result['month_info']))
                            <div class="summary-box">
                                <h3>Month Information</h3>
                                <div class="summary-item"><strong>Month Number:</strong> {{ $result['month_info']['value'] }}</div>
                                <div class="summary-item"><strong>Title:</strong> {{ $result['month_info']['title'] }}</div>
                                <div class="summary-item"><strong>Subtitle:</strong> {{ $result['month_info']['subtitle'] }}</div>
                                <div class="summary-item"><strong>Date Range:</strong> {{ $result['month_info']['start_date'] }} to {{ $result['month_info']['end_date'] }}</div>
                            </div>
                        @endif

                        @if(is_array($result) && isset($result['summary']))
                            <div class="summary-box">
                                <h3>Operation Summary</h3>
                                @foreach($result['summary'] as $key => $value)
                                    <div class="summary-item"><strong>{{ ucwords(str_replace('_', ' ', $key)) }}:</strong> {{ $value }}</div>
                                @endforeach
                            </div>
                        @endif

                        <div class="result">
                            <strong>Full Result:</strong>
                            <pre>{{ json_encode($result, JSON_PRETTY_PRINT) }}</pre>
                        </div>
                    @endif
                </div>
            @endforeach
        @endif

        <div style="margin-top: 40px; padding-top: 20px; border-top: 1px solid #ddd; color: #666;">
            <p><strong>Test Info:</strong></p>
            <ul>
                <li>Current Time: {{ now()->format('Y-m-d H:i:s T') }}</li>
                <li>App Timezone: {{ config('app.timezone') }}</li>
                <li>Available Users: {{ count($availableUsers) }}</li>
                <li>Current Week Number: {{ getCurrentWeekNumber() }}</li>
                <li>Current Month Number: {{ getCurrentMonthNumber() }}</li>
            </ul>
        </div>
    </div>
</body>
</html>