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
        .status-below { color: #dc3545; font-weight: bold; }
        .status-meet { color: #28a745; font-weight: bold; }
        .status-exceeded { color: #007bff; font-weight: bold; }
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
                <label>Category ID: <input type="number" name="category_id" value="{{ $categoryId }}" min="1"></label>
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
            @php
                // Performance comparison for calculateUserTargetHours functions
                $originalTime = $testResults['calculateUserTargetHours']['execution_time_ms'] ?? null;
                $optimizedTime = $testResults['calculateUserTargetHoursOptimized']['execution_time_ms'] ?? null;
                $improvementPercent = null;
                $speedupFactor = null;
                
                if ($originalTime && $optimizedTime && $originalTime > 0) {
                    $improvementPercent = round((($originalTime - $optimizedTime) / $originalTime) * 100, 1);
                    $speedupFactor = round($originalTime / $optimizedTime, 1);
                }
            @endphp

            @if($originalTime && $optimizedTime)
                <div class="calculation-summary" style="background: #f0f8ff; border-left: 4px solid #28a745;">
                    <h2>⚡ Performance Comparison: calculateUserTargetHours</h2>
                    <div class="grid">
                        <div>
                            <strong>Original Function:</strong> {{ $originalTime }}ms<br>
                            <strong>Optimized Function:</strong> {{ $optimizedTime }}ms<br>
                            @if($improvementPercent !== null)
                                <strong>Performance Improvement:</strong> 
                                <span style="color: #28a745; font-weight: bold;">{{ $improvementPercent }}% faster</span><br>
                                <strong>Speed Factor:</strong> {{ $speedupFactor }}x faster
                            @endif
                        </div>
                        <div>
                            @if($improvementPercent && $improvementPercent > 50)
                                <div style="background: #d4edda; color: #155724; padding: 10px; border-radius: 3px;">
                                    🚀 <strong>Excellent optimization!</strong><br>
                                    The optimized version is significantly faster.
                                </div>
                            @elseif($improvementPercent && $improvementPercent > 20)
                                <div style="background: #fff3cd; color: #856404; padding: 10px; border-radius: 3px;">
                                    ⚡ <strong>Good optimization!</strong><br>
                                    Noticeable performance improvement.
                                </div>
                            @elseif($improvementPercent && $improvementPercent > 0)
                                <div style="background: #cce5ff; color: #004085; padding: 10px; border-radius: 3px;">
                                    📈 <strong>Moderate improvement</strong><br>
                                    Some performance gain achieved.
                                </div>
                            @else
                                <div style="background: #f8d7da; color: #721c24; padding: 10px; border-radius: 3px;">
                                    ⚠️ <strong>No improvement</strong><br>
                                    Optimization may need review.
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @endif

            @foreach($testResults as $key => $test)
                <div class="function-block">
                    <h2>{{ $test['function'] }}() 
                        @if(isset($test['execution_time_ms']))
                            <span style="color: #007bff; font-size: 0.7em; font-weight: normal;">({{ $test['execution_time_ms'] }}ms)</span>
                        @endif
                    </h2>
                    
                    @if(isset($test['execution_time_ms']))
                        <div class="warning" style="background: #e1f5fe; color: #01579b; margin-bottom: 10px;">
                            <strong>⏱️ Execution Time:</strong> {{ $test['execution_time_ms'] }}ms
                        </div>
                    @endif
                    
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
                                            <strong>Work Status:</strong> {{ $calc['work_status'] ?? 'N/A' }}<br>
                                            <strong>Target Hours/Week:</strong> {{ $calc['target_hours_per_week'] ?? 'N/A' }}<br>
                                            <strong>Total Target Hours:</strong> {{ $calc['target_total_hours'] ?? 'N/A' }}
                                        </div>
                                        <div>
                                            <strong>Period:</strong> {{ $calc['period_weeks'] ?? 'N/A' }} weeks ({{ $calc['period_days'] ?? 'N/A' }} days)<br>
                                            <strong>Target ID:</strong> {{ $calc['target_id'] ?? 'N/A' }}
                                        </div>
                                    </div>
                                    
                                    @if(isset($calc['period_breakdown']) && !empty($calc['period_breakdown']))
                                        <h4>Period Breakdown:</h4>
                                        @foreach($calc['period_breakdown'] as $period)
                                            <div class="period-breakdown">
                                                <div class="grid">
                                                    <div>
                                                        <strong>Period:</strong> {{ $period['period_start'] ?? 'N/A' }} to {{ $period['period_end'] ?? 'N/A' }}<br>
                                                        <strong>Work Status:</strong> {{ $period['work_status_display'] ?? 'N/A' }}<br>
                                                        <strong>Duration:</strong> {{ $period['weeks'] ?? 'N/A' }} weeks ({{ $period['days'] ?? 'N/A' }} days)
                                                    </div>
                                                    <div>
                                                        <strong>Hours/Week:</strong> {{ $period['hours_per_week'] ?? 'N/A' }}<br>
                                                        <strong>Target Hours:</strong> {{ $period['target_hours'] ?? 'N/A' }}<br>
                                                        <strong>Week Range:</strong> {{ $period['week_start'] ?? 'N/A' }} - {{ $period['week_end'] ?? 'N/A' }}
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    @endif
                                </div>
                            @endforeach
                        @endif

                        @if(is_array($result) && isset($result['data']) && is_array($result['data']) && !empty($result['data']))
                            <div class="summary-box">
                                <h3>Results Summary</h3>
                                @if(isset($result['data'][0]) && isset($result['data'][0]['date']))
                                    <h4>Daily Breakdown ({{ count($result['data']) }} days)</h4>
                                    <div class="grid">
                                        @foreach(array_slice($result['data'], 0, 5) as $day)
                                            <div class="period-breakdown">
                                                <strong>{{ $day['date'] ?? 'N/A' }} ({{ $day['day_short'] ?? 'N/A' }})</strong><br>
                                                Total Hours: {{ $day['total_hours'] ?? 0 }}<br>
                                                Billable: {{ $day['billable_hours'] ?? 0 }}, Non-billable: {{ $day['non_billable_hours'] ?? 0 }}
                                            </div>
                                        @endforeach
                                    </div>
                                    @if(count($result['data']) > 5)
                                        <p><em>Showing first 5 days of {{ count($result['data']) }} total days...</em></p>
                                    @endif
                                @elseif(isset($result['data'][0]) && isset($result['data'][0]['type']))
                                    <h4>Category Breakdown</h4>
                                    @foreach($result['data'] as $categoryType)
                                        <div class="calculation-summary">
                                            <strong>{{ $categoryType['type'] ?? 'Unknown Type' }}:</strong> {{ $categoryType['total_hours'] ?? 0 }} hours 
                                            ({{ $categoryType['categories_count'] ?? 0 }} categories)
                                            @if(isset($categoryType['categories']) && !empty($categoryType['categories']))
                                                <div class="period-breakdown">
                                                    @foreach(array_slice($categoryType['categories'], 0, 3) as $category)
                                                        <div>{{ $category['category_name'] ?? 'Unknown' }}: {{ $category['total_hours'] ?? 0 }}h ({{ $category['entries_count'] ?? 0 }} entries)</div>
                                                    @endforeach
                                                    @if(count($categoryType['categories']) > 3)
                                                        <p><em>...and {{ count($categoryType['categories']) - 3 }} more categories</em></p>
                                                    @endif
                                                </div>
                                            @endif
                                        </div>
                                    @endforeach
                                @elseif(isset($result['data'][0]) && isset($result['data'][0]['task_name']))
                                    <h4>Tasks by Category ({{ count($result['data']) }} tasks)</h4>
                                    @foreach(array_slice($result['data'], 0, 5) as $task)
                                        <div class="period-breakdown">
                                            <strong>{{ $task['task_name'] ?? 'Unknown Task' }}</strong><br>
                                            Hours: {{ $task['total_hours'] ?? 0 }}, Entries: {{ $task['entries_count'] ?? 0 }}
                                        </div>
                                    @endforeach
                                    @if(count($result['data']) > 5)
                                        <p><em>Showing first 5 tasks of {{ count($result['data']) }} total tasks...</em></p>
                                    @endif
                                @endif
                            </div>
                        @endif

                        @if(is_array($result) && !isset($result['data']) && !isset($result['target_calculations']) && !isset($result['success']))
                            @php $basicMetrics = $result; @endphp
                            @if(isset($basicMetrics['total_hours']))
                                <div class="summary-box">
                                    <h3>Basic Metrics Summary</h3>
                                    <div class="grid">
                                        <div>
                                            <div class="summary-item"><strong>Total Hours:</strong> {{ $basicMetrics['total_hours'] ?? 0 }}</div>
                                            <div class="summary-item"><strong>Billable Hours:</strong> {{ $basicMetrics['billable_hours'] ?? 0 }}</div>
                                            <div class="summary-item"><strong>Non-billable Hours:</strong> {{ $basicMetrics['non_billable_hours'] ?? 0 }}</div>
                                            <div class="summary-item"><strong>Uncategorized Hours:</strong> {{ $basicMetrics['uncategorized_hours'] ?? 0 }}</div>
                                        </div>
                                        <div>
                                            <div class="summary-item"><strong>Total Entries:</strong> {{ $basicMetrics['total_entries'] ?? 0 }}</div>
                                            <div class="summary-item"><strong>Billable Entries:</strong> {{ $basicMetrics['billable_entries'] ?? 0 }}</div>
                                            <div class="summary-item"><strong>Non-billable Entries:</strong> {{ $basicMetrics['non_billable_entries'] ?? 0 }}</div>
                                            <div class="summary-item"><strong>Uncategorized Entries:</strong> {{ $basicMetrics['uncategorized_entries'] ?? 0 }}</div>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        @endif

                        @if(is_array($result) && is_numeric(array_keys($result)[0] ?? null))
                            @php $performances = $result; @endphp
                            <div class="summary-box">
                                <h3>Performance Metrics ({{ count($performances) }} calculations)</h3>
                                @foreach($performances as $performance)
                                    @if(is_array($performance) && isset($performance['work_status']))
                                        <div class="calculation-summary">
                                            <div class="grid">
                                                <div>
                                                    <strong>Work Status:</strong> {{ $performance['work_status'] ?? 'N/A' }}<br>
                                                    <strong>Target Hours/Week:</strong> {{ $performance['target_hours_per_week'] ?? 'N/A' }}<br>
                                                    <strong>Target Total:</strong> {{ $performance['target_total_hours'] ?? 'N/A' }}h
                                                </div>
                                                <div>
                                                    <strong>Actual Hours:</strong> {{ $performance['actual_hours'] ?? 'N/A' }}h<br>
                                                    <strong>Performance:</strong> <span class="status-{{ strtolower($performance['status'] ?? 'unknown') }}">{{ $performance['percentage'] ?? 'N/A' }}% ({{ $performance['status'] ?? 'N/A' }})</span><br>
                                                    <strong>Difference:</strong> {{ $performance['actual_vs_target'] ?? 'N/A' }}h
                                                </div>
                                            </div>
                                        </div>
                                    @endif
                                @endforeach
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
                <li>Test Category ID: {{ $categoryId }}</li>
            </ul>
        </div>
    </div>
</body>
</html>