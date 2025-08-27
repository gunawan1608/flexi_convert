<?php

// Simple test script to verify API endpoint is working
echo "Testing FlexiConvert API endpoint...\n\n";

// Test 1: Check if server is running
$url = 'http://127.0.0.1:8000/api/pdf-tools/process';
echo "1. Testing API endpoint: {$url}\n";

// Create test data
$postData = [
    'tool' => 'word-to-pdf',
    'settings' => '{}',
    '_token' => 'test-token'
];

// Create a simple test file
$testFile = tempnam(sys_get_temp_dir(), 'test_word_');
file_put_contents($testFile, "Test Word document content\nThis is a test file for conversion.");

// Prepare multipart form data
$boundary = '----WebKitFormBoundary' . uniqid();
$data = '';

// Add regular fields
foreach ($postData as $key => $value) {
    $data .= "--{$boundary}\r\n";
    $data .= "Content-Disposition: form-data; name=\"{$key}\"\r\n\r\n";
    $data .= "{$value}\r\n";
}

// Add file
$data .= "--{$boundary}\r\n";
$data .= "Content-Disposition: form-data; name=\"files[]\"; filename=\"test.docx\"\r\n";
$data .= "Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document\r\n\r\n";
$data .= file_get_contents($testFile) . "\r\n";
$data .= "--{$boundary}--\r\n";

// Setup cURL
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: multipart/form-data; boundary={$boundary}",
    "Content-Length: " . strlen($data)
]);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

// Execute request
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

// Clean up
unlink($testFile);

// Analyze response
echo "HTTP Code: {$httpCode}\n";

if ($error) {
    echo "cURL Error: {$error}\n";
} else {
    echo "Response received:\n";
    echo "Length: " . strlen($response) . " bytes\n";
    echo "First 200 characters:\n";
    echo substr($response, 0, 200) . "\n\n";
    
    // Check if it's JSON
    $decoded = json_decode($response, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        echo "✓ Valid JSON response\n";
        echo "Response data:\n";
        print_r($decoded);
    } else {
        echo "✗ Invalid JSON response\n";
        echo "JSON Error: " . json_last_error_msg() . "\n";
        echo "Raw response:\n";
        echo $response . "\n";
    }
}

echo "\nTest completed.\n";
