<?php

use Carbon\Carbon;

if (! function_exists('emailToFileName')) {
    function emailToFileName($email)
    {
        return preg_replace('/[^a-zA-Z0-9]/', '_', $email);
    }
}

if (! function_exists('isGenashtimEmail')) {
    function isGenashtimEmail($email)
    {
        // Check if the string is a valid email
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            // Extract the domain part of the email
            $domain = substr(strrchr($email, "@"), 1);

            // Define the list of allowed domains
            $allowedDomains = explode(",", env('SITE_CONFIG_ALLOWED_DOMAINS', 'genashtim.com'));

            // Check if the domain is in the list of allowed domains
            if (in_array($domain, $allowedDomains)) {
                return true; // Email is valid and from an allowed domain
            }
        }
        return false; // Email is not valid or not from an allowed domain
    }
}

if (! function_exists('EncryptData')) {
    function EncryptData($input, $key)
    {
        $key    = str_pad($key, 256, ' '); // Pad or trim key to 256 characters
        $input  = (string) $input;
        $output = '';

        for ($i = 0; $i < strlen($input); $i++) {
            $charCode          = ord($input[$i]);
            $keyChar           = ord($key[$i % strlen($key)]);
            $encryptedCharCode = $charCode ^ $keyChar;
            $output .= chr($encryptedCharCode);
        }

        return base64_encode($output);
    }
}

if (! function_exists('DecryptData')) {
    function DecryptData($input, $key)
    {
        $key    = str_pad($key, 256, ' ');
        $input  = base64_decode($input);
        $output = '';

        for ($i = 0; $i < strlen($input); $i++) {
            $charCode          = ord($input[$i]);
            $keyChar           = ord($key[$i % strlen($key)]);
            $decryptedCharCode = $charCode ^ $keyChar;
            $output .= chr($decryptedCharCode);
        }

        return $output;
    }
}

if (! function_exists('encryptUserData')) {
    function encryptUserData($user)
    {
        if (! $user) {
            return null;
        }

        $key = env('API_NAD_SECRET_KEY');
        if (! $key) {
            return null;
        }

        $data = [
            'id'           => $user->id,
            'employee_id'  => $user->employee_id,
            'email'        => $user->email,
            'datetime'     => Carbon::now()->toIso8601String(),
            'name_request' => 'hrms',
        ];

        return EncryptData(json_encode($data), $key);
    }
}

if (! function_exists('decryptUserToken')) {
    function decryptUserToken($token)
    {
        if (! $token) {
            return null;
        }

        $key = env('API_NAD_SECRET_KEY');
        if (! $key) {
            return null;
        }

        $decrypted = DecryptData($token, $key);
        return json_decode($decrypted, true);
    }
}

// Functions for handling IVA User BillAble reports
if (! function_exists('ivaAdjustStartDate')) {

/**
 * Adjust start date based on user hire date
 *
 * @param object $user - The IvaUser model instance
 * @param string $startDate - The original start date
 * @param string $endDate - The end date for validation
 * @return array - Returns adjusted start date and validation status
 */
    function ivaAdjustStartDate($user, $startDate, $endDate)
    {
        $originalStartDate = Carbon::parse($startDate);
        $parsedEndDate     = Carbon::parse($endDate);

        // Check if user hire_date is null or before start date
        if (is_null($user->hire_date) || Carbon::parse($user->hire_date)->lt($originalStartDate)) {
            $adjustedStartDate = $originalStartDate;
        } else {
            // Get Monday of the week that hire date falls in
            $hireDate          = Carbon::parse($user->hire_date);
            $adjustedStartDate = $hireDate->startOfWeek(Carbon::MONDAY);
        }

        // Check if adjusted start date is at least 1 week before end date
        $isValidWeekRange = $adjustedStartDate->diffInDays($parsedEndDate) >= 7;

        return [
            'adjusted_start_date' => $adjustedStartDate->format('Y-m-d'),
            'original_start_date' => $originalStartDate->format('Y-m-d'),
            'is_valid_week_range' => $isValidWeekRange,
            'days_difference'     => $adjustedStartDate->diffInDays($parsedEndDate),
            'hire_date_used'      => ! is_null($user->hire_date) && Carbon::parse($user->hire_date)->gte($originalStartDate),
        ];
    }
}