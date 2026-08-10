<?php

date_default_timezone_set('Africa/Dar_es_Salaam');

// Configuration (apikey and system_id obtained from mgov)
$apikey = 'xxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxxx';
$system_id = 'XX-XXXXXXXX';
$url = 'https://mgov.gov.go.tz/gateway/sms/v2/send-sms';

// SMS Details
$recipients = '255XXXXXXXXX'; // Replace with the recipient's number
$message = 'TESTING MGOV SMS SENDING......';
$mobileServiceId = 'XXX';
$senderId = '15200';
$messageId = uniqid();

// Generate datetime in required format
$formatted_datetime = date('Y-m-d H:i:s.v'); // with milliseconds
// Uncomment for ISO 8601 if needed: $formatted_datetime = date("Y-m-d\TH:i:s");

$data = [
    'recipients' => $recipients,
    'message' => $message,
    'datetime' => $formatted_datetime,
    'mobileServiceId' => $mobileServiceId,
    'senderId' => $senderId,
    'messageId' => $messageId,
];
echo "Date formated : $formatted_datetime";
$post_data_str = json_encode($data, JSON_UNESCAPED_SLASHES);

function doHash($key, $post_data_str)
{
    $hash = hash_hmac('sha256', $post_data_str, $key, true);

    return base64_encode($hash);
}

$hash_value = doHash($apikey, $post_data_str);

// Log generated hash and payload for debugging
error_log("Generated Hash: $hash_value");
error_log("Request Payload: $post_data_str");

// Prepare headers
$headers = [
    'Content-Type: application/json',
    "hash: $hash_value",
    "sysId: $system_id",
];

// Initialize CURL and configure the request
$curl = curl_init($url);
curl_setopt_array($curl, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $post_data_str,
    CURLOPT_HTTPHEADER => $headers,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => true, // Ensure SSL certificate verification
    CURLOPT_TIMEOUT => 30,         // Set timeout to handle slow responses
]);

// Execute CURL request and handle response
$response = curl_exec($curl);
$curl_error = curl_error($curl);
curl_close($curl);

if ($response === false) {
    error_log("CURL Error: $curl_error");
    echo "Error: Unable to send SMS. CURL error: $curl_error";
} else {
    // Parse the response
    $response_data = json_decode($response, true);

    // Check response structure
    if (is_array($response_data) && isset($response_data['success'])) {
        if ($response_data['success']) {
            echo "SMS sent successfully!\n";
            echo 'Response: '.print_r($response_data, true);
        } else {
            echo "Failed to send SMS.\n";
            echo 'Error Code: '.($response_data['statusCode'] ?? 'Unknown')."\n";
            echo 'Error Message: '.($response_data['statusMessage'] ?? 'No message')."\n";
        }
    } else {
        echo "Failed to parse gateway response.\n";
        echo "Response: $response";
    }
}
