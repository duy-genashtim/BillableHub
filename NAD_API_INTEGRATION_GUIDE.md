# NAD API Integration Guide

## Overview

The NAD API (Not-At-Desk API) is integrated into the TimeTrack application to fetch employee availability data. This guide covers the implementation in the `/admin/reports/overall-performance` route, explaining token generation, API usage, and data handling.

---

## Table of Contents

1. [Configuration](#configuration)
2. [Token Generation](#token-generation)
3. [API Communication](#api-communication)
4. [Usage in Overall Performance Report](#usage-in-overall-performance-report)
5. [Sample Data & Responses](#sample-data--responses)
6. [Security Considerations](#security-considerations)
7. [Troubleshooting](#troubleshooting)

---

## Configuration

### Environment Variables

The NAD API requires the following environment variables in your `.env` file:

```bash
NAD_API_URL=https://your-nad-api-endpoint.com/api
API_NAD_SECRET_KEY=your_secret_key_here
NAD_HOUR_RATE=8
```

### Configuration File

Located at `config/services.php`:

```php
'nad' => [
    'url' => env('NAD_API_URL'),
    'secret_key' => env('API_NAD_SECRET_KEY', 'HRMS_SECRET_DATA_KEY'),
    'nad_hour_rate' => [
        'rate' => env('NAD_HOUR_RATE', 8)
    ],
],
```

**Configuration Parameters:**
- **url**: NAD API endpoint URL
- **secret_key**: Shared secret key for encryption/decryption (256-character padded)
- **nad_hour_rate**: Conversion rate from NAD count to hours (default: 8 hours per NAD)

---

## Token Generation

### Encryption Algorithm

The NAD API uses a custom XOR-based encryption with Base64 encoding for token generation.

### `encryptUserData()` Function

**Location**: `app/Helpers/helpers.php:75-97`

**Purpose**: Generates an encrypted token containing user identification data.

**Implementation**:

```php
function encryptUserData($user)
{
    if (!$user) {
        return null;
    }

    $key = config('services.nad.secret_key');
    if (!$key) {
        return null;
    }

    $data = [
        'id' => $user->id,
        'employee_id' => $user->azure_id,
        'email' => $user->email,
        'datetime' => Carbon::now()->toIso8601String(),
        'name_request' => 'iva_biilable',
    ];

    return EncryptData(json_encode($data), $key);
}
```

### Low-Level Encryption: `EncryptData()`

**Location**: `app/Helpers/helpers.php:39-55`

**Algorithm**: XOR cipher with Base64 encoding

```php
function EncryptData($input, $key)
{
    $key = str_pad($key, 256, ' '); // Pad or trim key to 256 characters
    $input = (string) $input;
    $output = '';

    for ($i = 0; $i < strlen($input); $i++) {
        $charCode = ord($input[$i]);
        $keyChar = ord($key[$i % strlen($key)]);
        $encryptedCharCode = $charCode ^ $keyChar;
        $output .= chr($encryptedCharCode);
    }

    return base64_encode($output);
}
```

**Steps:**
1. Pad secret key to 256 characters with spaces
2. XOR each character of input with corresponding key character (cycling through key)
3. Base64 encode the result

### Token Data Structure

**Unencrypted Payload (JSON)**:
```json
{
  "id": 123,
  "employee_id": "azure-ad-object-id",
  "email": "user@example.com",
  "datetime": "2024-12-04T10:30:00+08:00",
  "name_request": "iva_biilable"
}
```

**Encrypted Token (Base64 String)**:
```
VGhpcyBpcyBhIHNhbXBsZSBlbmNyeXB0ZWQgdG9rZW4=
```

---

## API Communication

### `callNADApi()` Function

**Location**: `app/Helpers/helpers.php:180-222`

**Purpose**: Sends requests to the NAD API with encrypted authentication.

**Implementation**:

```php
function callNADApi(string $action, array $data): ?array
{
    try {
        $user = request()->user();

        $formData = [
            ['name' => 'email', 'contents' => $user->email],
            ['name' => 'token', 'contents' => encryptUserData($user)],
            ['name' => 'action', 'contents' => $action],
            ['name' => 'data', 'contents' => json_encode($data)],
        ];

        $response = Http::asMultipart()
            ->post(config('services.nad.url'), $formData);

        if ($response->successful()) {
            return $response->json();
        } else {
            Log::error('NAD API error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return null;
        }
    } catch (\Throwable $e) {
        Log::error('NAD API connection failed', ['error' => $e->getMessage()]);
        return null;
    }
}
```

### Request Format

**Method**: POST
**Content-Type**: multipart/form-data

**Parameters**:
| Field | Type | Description |
|-------|------|-------------|
| `email` | string | User's email address |
| `token` | string | Encrypted authentication token (Base64) |
| `action` | string | API action to perform (e.g., `get_nad_by_date_range`) |
| `data` | string | JSON-encoded payload with action-specific data |

---

## Usage in Overall Performance Report

### Workflow

The overall performance report (`IvaOverallReportController::getOverallPerformanceReport()`) fetches NAD data for all users in the date range.

**Controller Location**: `app/Http/Controllers/IvaOverallReportController.php:16-102`

### `fetchNADDataForUsers()` Function

**Location**: `app/Helpers/helpers.php:303-340`

**Purpose**: Fetch NAD data for all users within a date range.

**Implementation**:

```php
function fetchNADDataForUsers($startDate, $endDate)
{
    $nadHourRate = config('services.nad.nad_hour_rate.rate', 8);

    $nadData = [
        'start_date' => $startDate,
        'end_date' => $endDate,
        'blab_only' => 1,
        'email_list' => [],
    ];

    $nadResponse = callNADApi('get_nad_by_date_range', $nadData);
    $nadUserData = [];
    $nadCount = 0;
    $nadHours = 0;

    if (!empty($nadResponse['status']) && $nadResponse['status'] === true && !empty($nadResponse['data'])) {
        $nadUserData = $nadResponse['data'] ?? [];
        $nadCount = is_array($nadUserData) && isset($nadUserData['nad_count']) ? $nadUserData['nad_count'] : 0;
        $nadHours = $nadCount * $nadHourRate;
    }

    return [
        'nad_data' => $nadUserData,
        'nad_count' => $nadCount,
        'nad_hours' => round($nadHours, 2),
        'nad_hour_rate' => $nadHourRate,
    ];
}
```

### Integration Points

**Controller Method**: `processWeeklySummaryDataOptimized()`, `processMonthlySummaryDataOptimized()`, `processYearlyDataOptimized()`

**Location**: `app/Http/Controllers/IvaOverallReportController.php:277-517`

**Process**:

1. **Fetch NAD Data Once** (optimized for performance):
   ```php
   $nadDataResponse = fetchNADDataForUsers($startDate, $endDate);
   $nadDataByEmail = [];

   if (isset($nadDataResponse['nad_data']) && is_array($nadDataResponse['nad_data'])) {
       foreach ($nadDataResponse['nad_data'] as $nadUser) {
           $nadDataByEmail[$nadUser['email']] = [
               'nad_count' => $nadUser['nad_count'] ?? 0,
               'nad_hours' => ($nadUser['nad_count'] ?? 0) * ($nadDataResponse['nad_hour_rate'] ?? 8),
               'requests' => $nadUser['requests'] ?? 0,
           ];
       }
   }
   ```

2. **Map to Users**:
   ```php
   foreach ($users as $user) {
       $userNadData = $nadDataByEmail[$user->email] ?? [
           'nad_count' => 0,
           'nad_hours' => 0,
           'requests' => 0
       ];

       $userData = [
           // ... other user data
           'nad_count' => $userNadData['nad_count'],
           'nad_hours' => round($userNadData['nad_hours'], 2),
           // ...
       ];
   }
   ```

3. **Calculate Summary Statistics**:
   ```php
   $reportData['summary'] = [
       'full_time' => $this->calculateGroupSummary($allFullTimeUsers),
       'part_time' => $this->calculateGroupSummary($allPartTimeUsers),
       'overall' => $this->calculateGroupSummary($allUsersData),
   ];
   ```

---

## Sample Data & Responses

### Sample Request to NAD API

**Action**: `get_nad_by_date_range`

**Form Data**:
```
email: user@genashtim.com
token: VGhpcyBpcyBhIHNhbXBsZSBlbmNyeXB0ZWQgdG9rZW4=
action: get_nad_by_date_range
data: {
  "start_date": "2024-12-01",
  "end_date": "2024-12-07",
  "blab_only": 1,
  "email_list": []
}
```

**cURL Example**:
```bash
curl -X POST https://nad-api.example.com/api \
  -F "email=user@genashtim.com" \
  -F "token=VGhpcyBpcyBhIHNhbXBsZSBlbmNyeXB0ZWQgdG9rZW4=" \
  -F "action=get_nad_by_date_range" \
  -F 'data={"start_date":"2024-12-01","end_date":"2024-12-07","blab_only":1,"email_list":[]}'
```

### Sample NAD API Response

**Success Response**:
```json
{
  "status": true,
  "message": "NAD data retrieved successfully",
  "data": [
    {
      "email": "john.doe@genashtim.com",
      "nad_count": 5,
      "requests": 12
    },
    {
      "email": "jane.smith@genashtim.com",
      "nad_count": 3,
      "requests": 8
    },
    {
      "email": "bob.wilson@genashtim.com",
      "nad_count": 0,
      "requests": 0
    }
  ]
}
```

**Error Response**:
```json
{
  "status": false,
  "message": "Invalid token or authentication failed",
  "error": "TOKEN_INVALID"
}
```

### Processed Data in Overall Performance Report

**Backend Response** (`/api/reports/overall-performance`):

```json
{
  "success": true,
  "year": 2024,
  "mode": "weekly",
  "date_range": {
    "start": "2024-12-01",
    "end": "2024-12-07"
  },
  "users_data": [
    {
      "id": 123,
      "full_name": "John Doe",
      "email": "john.doe@genashtim.com",
      "job_title": "IVA Specialist",
      "work_status": "full-time",
      "region_id": 1,
      "region_name": "North America",
      "billable_hours": 38.5,
      "non_billable_hours": 2.5,
      "total_hours": 41.0,
      "target_hours": 40.0,
      "nad_count": 5,
      "nad_hours": 40.0,
      "performance": {
        "target_id": 1,
        "work_status": "Full Time",
        "target_hours_per_week": 40,
        "target_total_hours": 40.0,
        "actual_hours": 38.5,
        "percentage": 96.3,
        "status": "BELOW",
        "actual_vs_target": -1.5,
        "period_weeks": 1.0,
        "period_days": 7
      },
      "categories": [
        {
          "category_id": 1,
          "category_name": "Client Work",
          "hours": 30.5
        },
        {
          "category_id": 2,
          "category_name": "Administrative",
          "hours": 8.0
        }
      ]
    }
  ],
  "summary": {
    "full_time": {
      "total_users": 25,
      "total_billable_hours": 962.5,
      "total_non_billable_hours": 62.5,
      "total_hours": 1025.0,
      "total_target_hours": 1000.0,
      "total_nad_count": 125,
      "total_nad_hours": 1000.0,
      "avg_performance": 96.3,
      "performance_breakdown": {
        "exceeded": 8,
        "meet": 10,
        "below": 7
      }
    },
    "part_time": {
      "total_users": 10,
      "total_billable_hours": 192.0,
      "total_non_billable_hours": 8.0,
      "total_hours": 200.0,
      "total_target_hours": 200.0,
      "total_nad_count": 25,
      "total_nad_hours": 200.0,
      "avg_performance": 96.0,
      "performance_breakdown": {
        "exceeded": 2,
        "meet": 5,
        "below": 3
      }
    },
    "overall": {
      "total_users": 35,
      "total_billable_hours": 1154.5,
      "total_non_billable_hours": 70.5,
      "total_hours": 1225.0,
      "total_target_hours": 1200.0,
      "total_nad_count": 150,
      "total_nad_hours": 1200.0,
      "avg_performance": 96.2,
      "performance_breakdown": {
        "exceeded": 10,
        "meet": 15,
        "below": 10
      }
    }
  }
}
```

### Frontend Display

**Component**: `resources/js/pages/iva-reports/OverallPerformanceReport.vue:205-214`

**API Call**:
```javascript
const response = await axios.get('/api/reports/overall-performance', {
  params: {
    year: 2024,
    start_date: '2024-12-01',
    end_date: '2024-12-07',
    mode: 'weekly',
    show_details: true
  }
});

performanceData.value = response.data;
```

---

## Security Considerations

### Token Security

1. **Token Expiration**: The token includes a `datetime` field, but the system doesn't enforce expiration on the Laravel side. The NAD API should validate token age.

2. **Secret Key Management**:
   - Store `API_NAD_SECRET_KEY` in `.env` file
   - Never commit secret keys to version control
   - Use environment-specific keys for development/production

3. **XOR Encryption Limitations**:
   - XOR cipher is not cryptographically secure for sensitive data
   - Consider upgrading to AES-256 or RSA for production
   - Current implementation is suitable for internal APIs only

### API Security Best Practices

1. **HTTPS Required**: Always use HTTPS for NAD API communication
2. **Error Handling**: Sensitive errors are logged server-side, not exposed to frontend
3. **Rate Limiting**: Consider implementing rate limits on NAD API calls
4. **Validation**: NAD API should validate:
   - Token authenticity and expiration
   - User permissions
   - Date range constraints

### Decryption Function (for NAD API server)

**Location**: `app/Helpers/helpers.php:57-73`

```php
function DecryptData($input, $key)
{
    $key = str_pad($key, 256, ' ');
    $input = base64_decode($input);
    $output = '';

    for ($i = 0; $i < strlen($input); $i++) {
        $charCode = ord($input[$i]);
        $keyChar = ord($key[$i % strlen($key)]);
        $decryptedCharCode = $charCode ^ $keyChar;
        $output .= chr($decryptedCharCode);
    }

    return $output;
}
```

---

## Troubleshooting

### Common Issues

#### 1. "NAD API connection failed"

**Symptoms**: Logs show connection timeout or network error

**Solutions**:
- Check `NAD_API_URL` in `.env`
- Verify network connectivity to NAD API server
- Check firewall rules
- Test with cURL from command line

#### 2. "Invalid token or authentication failed"

**Symptoms**: NAD API returns 401/403 status

**Solutions**:
- Verify `API_NAD_SECRET_KEY` matches on both sides
- Check token generation logic
- Ensure datetime is in correct ISO 8601 format
- Verify user data exists (id, email, azure_id)

#### 3. NAD Count is Always Zero

**Symptoms**: All users show `nad_count: 0`

**Possible Causes**:
- NAD API has no data for date range
- Email mismatch between TimeTrack and NAD system
- `blab_only` filter excluding data

**Debug Steps**:
```php
Log::info('NAD API Request', [
    'start_date' => $startDate,
    'end_date' => $endDate,
    'action' => 'get_nad_by_date_range'
]);

Log::info('NAD API Response', [
    'status' => $nadResponse['status'] ?? null,
    'data_count' => count($nadResponse['data'] ?? []),
    'data' => $nadResponse
]);
```

#### 4. Performance Issues with Large Date Ranges

**Symptoms**: Slow response times for yearly reports

**Solutions**:
- NAD data is fetched once per report (already optimized)
- Consider caching NAD responses for frequently accessed date ranges
- Use queue jobs for large reports

**Example Caching**:
```php
$cacheKey = "nad_data_{$startDate}_{$endDate}";
$nadDataResponse = Cache::remember($cacheKey, now()->addMinutes(30), function() use ($startDate, $endDate) {
    return fetchNADDataForUsers($startDate, $endDate);
});
```

---

## API Action Reference

### `get_nad_by_date_range`

**Description**: Retrieve NAD (Not-At-Desk) data for specified date range.

**Request Data Structure**:
```json
{
  "start_date": "YYYY-MM-DD",
  "end_date": "YYYY-MM-DD",
  "blab_only": 1,
  "email_list": []
}
```

**Parameters**:
- `start_date` (string, required): Start date in Y-m-d format
- `end_date` (string, required): End date in Y-m-d format
- `blab_only` (int, optional): Filter for billable NAD only (1 = yes, 0 = no)
- `email_list` (array, optional): List of specific emails to filter (empty array = all users)

**Response Structure**:
```json
{
  "status": true|false,
  "message": "Success/error message",
  "data": [
    {
      "email": "user@example.com",
      "nad_count": 5,
      "requests": 12
    }
  ]
}
```

---

## Code References

| File | Line | Function | Purpose |
|------|------|----------|---------|
| `config/services.php` | 49-53 | NAD Config | Configuration settings |
| `app/Helpers/helpers.php` | 39-55 | `EncryptData()` | XOR encryption |
| `app/Helpers/helpers.php` | 57-73 | `DecryptData()` | XOR decryption |
| `app/Helpers/helpers.php` | 75-97 | `encryptUserData()` | Token generation |
| `app/Helpers/helpers.php` | 180-222 | `callNADApi()` | API communication |
| `app/Helpers/helpers.php` | 303-340 | `fetchNADDataForUsers()` | Bulk NAD data fetch |
| `app/Http/Controllers/IvaOverallReportController.php` | 277-355 | `processWeeklySummaryDataOptimized()` | Weekly report NAD integration |
| `resources/js/pages/iva-reports/OverallPerformanceReport.vue` | 205-214 | `loadPerformanceData()` | Frontend API call |

---

## Related Documentation

- [Overall Performance Report Backend](app/Http/Controllers/IvaOverallReportController.php)
- [Helper Functions](app/Helpers/helpers.php)
- [TimeTrack System Features](SYSTEM_FEATURES_DOCUMENTATION.md)
- [Optimization Guide](OPTIMIZATION_GUIDE.md)

---

**Last Updated**: 2024-12-04
**Version**: 1.0
**Maintainer**: Development Team
