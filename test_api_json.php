<?php

// Test script untuk memverifikasi API mengembalikan JSON
echo "Testing API JSON Response...\n\n";

// Test 1: Test endpoint /api/convert
$url = 'http://127.0.0.1:8000/api/convert';
echo "Testing: {$url}\n";

$data = json_encode([
    'file' => 'example.pdf',
    'tool' => 'word-to-pdf'
]);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: {$httpCode}\n";
echo "Response: " . substr($response, 0, 200) . "\n";

// Check if JSON
$json = json_decode($response, true);
if (json_last_error() === JSON_ERROR_NONE) {
    echo "✓ Valid JSON response\n";
    print_r($json);
} else {
    echo "✗ Not JSON: " . json_last_error_msg() . "\n";
}

echo "\n" . str_repeat("-", 50) . "\n\n";

// Test 2: Test endpoint /api/convert-public (tanpa auth)
$url2 = 'http://127.0.0.1:8000/api/convert-public';
echo "Testing: {$url2}\n";

$ch2 = curl_init();
curl_setopt($ch2, CURLOPT_URL, $url2);
curl_setopt($ch2, CURLOPT_POST, true);
curl_setopt($ch2, CURLOPT_POSTFIELDS, $data);
curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch2, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json'
]);

$response2 = curl_exec($ch2);
$httpCode2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
curl_close($ch2);

echo "HTTP Code: {$httpCode2}\n";
echo "Response: " . substr($response2, 0, 200) . "\n";

// Check if JSON
$json2 = json_decode($response2, true);
if (json_last_error() === JSON_ERROR_NONE) {
    echo "✓ Valid JSON response\n";
    print_r($json2);
} else {
    echo "✗ Not JSON: " . json_last_error_msg() . "\n";
}

echo "\nTest completed.\n";
