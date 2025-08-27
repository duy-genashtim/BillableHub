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
        .tabs { display: flex; border-bottom: 2px solid #007bff; margin-bottom: 20px; }
        .tab { padding: 10px 20px; background: #f8f9fa; border: 1px solid #ddd; cursor: pointer; margin-right: 5px; border-bottom: none; }
        .tab.active { background: #007bff; color: white; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        .test-form { background: #f8f9fa; padding: 20px; border-radius: 5px; margin-bottom: 20px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-group input, .form-group select { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 3px; }
        .btn { padding: 10px 20px; background: #007bff; color: white; border: none; border-radius: 3px; cursor: pointer; }
        .btn:hover { background: #0056b3; }
        .loading { color: #007bff; font-style: italic; }
        .success { color: #28a745; }
        .error-result { color: #dc3545; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Date Time Helpers Test Results</h1>
        
        <!-- Tab Navigation -->
        <div class="tabs">
            <div class="tab active" onclick="switchTab('datetime-helpers')">Date Time Helpers</div>
            <div class="tab" onclick="switchTab('worklog-summary')">Daily Worklog Summary</div>
            <div class="tab" onclick="switchTab('nad-data')">NAD Data Fetch</div>
        </div>

        <!-- Date Time Helpers Tab -->
        <div id="datetime-helpers" class="tab-content active">
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
        </div>

        <!-- Daily Worklog Summary Tab -->
        <div id="worklog-summary" class="tab-content">
            <div class="test-form">
                <h2>Test DailyWorklogSummaryService::calculateSummaries</h2>
                <form id="worklogSummaryForm">
                    <div class="form-group">
                        <label for="user_id">IVA User ID:</label>
                        <input type="number" id="user_id" name="user_id" placeholder="Enter user ID (leave empty for all users)">
                    </div>
                    <div class="form-group">
                        <label for="start_date">Start Date:</label>
                        <input type="date" id="start_date" name="start_date" value="{{ Carbon\Carbon::now()->subDays(7)->format('Y-m-d') }}">
                    </div>
                    <div class="form-group">
                        <label for="end_date">End Date:</label>
                        <input type="date" id="end_date" name="end_date" value="{{ Carbon\Carbon::now()->format('Y-m-d') }}">
                    </div>
                    <div class="form-group">
                        <label for="calculate_all">
                            <input type="checkbox" id="calculate_all" name="calculate_all" value="1"> Calculate for all users
                        </label>
                    </div>
                    <button type="button" class="btn" onclick="testWorklogSummary()">Run Test</button>
                </form>
            </div>

            <div id="worklogResults" style="display:none;">
                <h3>Test Results</h3>
                <div id="worklogResultsContent"></div>
            </div>
        </div>

        <!-- NAD Data Fetch Tab -->
        <div id="nad-data" class="tab-content">
            <div class="test-form">
                <h2>Test fetchNADDataForUsers Function</h2>
                <form id="nadDataForm">
                    <div class="form-group">
                        <label for="nad_start_date">Start Date:</label>
                        <input type="date" id="nad_start_date" name="start_date" value="{{ Carbon\Carbon::now()->subDays(7)->format('Y-m-d') }}">
                    </div>
                    <div class="form-group">
                        <label for="nad_end_date">End Date:</label>
                        <input type="date" id="nad_end_date" name="end_date" value="{{ Carbon\Carbon::now()->format('Y-m-d') }}">
                    </div>
                    <button type="button" class="btn" onclick="testNADData()">Fetch NAD Data</button>
                </form>
            </div>

            <div id="nadResults" style="display:none;">
                <h3>NAD Data Results</h3>
                <div id="nadResultsContent"></div>
            </div>
        </div>

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

    <script>
        function switchTab(tabId) {
            // Hide all tab contents
            const tabContents = document.querySelectorAll('.tab-content');
            tabContents.forEach(content => content.classList.remove('active'));
            
            // Remove active class from all tabs
            const tabs = document.querySelectorAll('.tab');
            tabs.forEach(tab => tab.classList.remove('active'));
            
            // Show selected tab content
            document.getElementById(tabId).classList.add('active');
            
            // Add active class to clicked tab
            event.target.classList.add('active');
        }

        async function testWorklogSummary() {
            const form = document.getElementById('worklogSummaryForm');
            const formData = new FormData(form);
            
            // Show loading state
            const resultsDiv = document.getElementById('worklogResults');
            const resultsContent = document.getElementById('worklogResultsContent');
            resultsDiv.style.display = 'block';
            resultsContent.innerHTML = '<div class="loading">Running test...</div>';

            try {
                const response = await fetch('{{ route("api.test.daily-worklog-summary") }}', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    }
                });

                const result = await response.json();
                
                let html = '<div class="function-block">';
                html += '<h3>' + result.function + '</h3>';
                
                html += '<div class="parameters">';
                html += '<strong>Parameters:</strong>';
                html += '<pre>' + JSON.stringify(result.parameters, null, 2) + '</pre>';
                html += '</div>';

                if (result.success) {
                    html += '<div class="success">✓ Test completed successfully</div>';
                    html += '<div class="result">';
                    html += '<strong>Result:</strong>';
                    html += '<pre>' + JSON.stringify(result.result, null, 2) + '</pre>';
                    html += '</div>';
                } else {
                    html += '<div class="error-result">✗ Test failed</div>';
                    if (result.error) {
                        html += '<div class="error">';
                        html += '<strong>Error:</strong>';
                        html += '<pre>' + result.error + '</pre>';
                        html += '</div>';
                    }
                }
                
                html += '</div>';
                resultsContent.innerHTML = html;

            } catch (error) {
                resultsContent.innerHTML = '<div class="error">Failed to execute test: ' + error.message + '</div>';
            }
        }

        // Handle calculate_all checkbox
        document.getElementById('calculate_all').addEventListener('change', function() {
            const userIdInput = document.getElementById('user_id');
            if (this.checked) {
                userIdInput.disabled = true;
                userIdInput.value = '';
                userIdInput.placeholder = 'Disabled - calculating for all users';
            } else {
                userIdInput.disabled = false;
                userIdInput.placeholder = 'Enter user ID (leave empty for all users)';
            }
        });

        async function testNADData() {
            const form = document.getElementById('nadDataForm');
            const formData = new FormData(form);
            
            // Show loading state
            const resultsDiv = document.getElementById('nadResults');
            const resultsContent = document.getElementById('nadResultsContent');
            resultsDiv.style.display = 'block';
            resultsContent.innerHTML = '<div class="loading">Fetching NAD data...</div>';

            try {
                const response = await fetch('{{ route("api.test.nad-data") }}', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    }
                });

                const result = await response.json();
                
                let html = '<div class="function-block">';
                html += '<h3>' + result.function + '</h3>';
                
                html += '<div class="parameters">';
                html += '<strong>Parameters:</strong>';
                html += '<pre>' + JSON.stringify(result.parameters, null, 2) + '</pre>';
                html += '</div>';

                if (result.success) {
                    html += '<div class="success">✓ Test completed successfully</div>';
                    
                    // Show execution time if available
                    if (result.execution_time_ms) {
                        html += '<div style="color: #6c757d; font-size: 0.9em; margin: 5px 0;">⏱️ Execution time: ' + result.execution_time_ms + 'ms</div>';
                    }
                    
                    // Show debug summary if available
                    if (result.debug_summary) {
                        html += '<div class="debug-summary" style="background: #e3f2fd; padding: 10px; margin: 10px 0; border-radius: 3px;">';
                        html += '<strong>🔍 Debug Summary:</strong><br>';
                        html += '<small>';
                        html += 'API URL: ' + (result.debug_summary.api_url || 'Not available') + '<br>';
                        html += 'API Status: ' + (result.debug_summary.api_response_status || 'Unknown') + '<br>';
                        html += 'NAD Count: ' + (result.debug_summary.nad_count || 0) + '<br>';
                        html += 'NAD Hours: ' + (result.debug_summary.nad_hours || 0) + '<br>';
                        html += 'Has Data: ' + (result.debug_summary.has_data ? 'Yes' : 'No') + '<br>';
                        if (result.debug_summary.api_response_message && result.debug_summary.api_response_message !== 'no message') {
                            html += 'API Message: ' + result.debug_summary.api_response_message + '<br>';
                        }
                        html += '</small>';
                        html += '</div>';
                    }
                    
                    html += '<div class="result">';
                    html += '<strong>Full Result:</strong>';
                    html += '<pre>' + JSON.stringify(result.result, null, 2) + '</pre>';
                    html += '</div>';
                } else {
                    html += '<div class="error-result">✗ Test failed</div>';
                    
                    // Show execution time even on failure
                    if (result.execution_time_ms) {
                        html += '<div style="color: #6c757d; font-size: 0.9em; margin: 5px 0;">⏱️ Execution time: ' + result.execution_time_ms + 'ms</div>';
                    }
                    
                    // Show warning if it's a partial failure
                    if (result.warning) {
                        html += '<div style="background: #fff3cd; color: #856404; padding: 10px; margin: 10px 0; border-radius: 3px;">';
                        html += '<strong>⚠️ Warning:</strong> ' + result.warning;
                        html += '</div>';
                    }
                    
                    if (result.error) {
                        html += '<div class="error">';
                        html += '<strong>Error:</strong>';
                        html += '<pre>' + result.error + '</pre>';
                        html += '</div>';
                    }
                    
                    // Show debug summary even on failure
                    if (result.debug_summary) {
                        html += '<div class="debug-summary" style="background: #f8d7da; padding: 10px; margin: 10px 0; border-radius: 3px;">';
                        html += '<strong>🔍 Debug Info:</strong><br>';
                        html += '<small>';
                        html += 'API URL: ' + (result.debug_summary.api_url || 'Not available') + '<br>';
                        html += 'API Status: ' + (result.debug_summary.api_response_status || 'Unknown') + '<br>';
                        if (result.debug_summary.api_response_message && result.debug_summary.api_response_message !== 'no message') {
                            html += 'API Message: ' + result.debug_summary.api_response_message + '<br>';
                        }
                        html += '</small>';
                        html += '</div>';
                    }
                }
                
                html += '</div>';
                resultsContent.innerHTML = html;

            } catch (error) {
                resultsContent.innerHTML = '<div class="error">Failed to execute test: ' + error.message + '</div>';
            }
        }
    </script>
</body>
</html>