<?php

require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\DocumentConversionService;

echo "Testing new DOCX generation...\n";

$service = new DocumentConversionService();

// Test text content
$testText = "# Converted from PDF: Test Document

This is a demonstration conversion from PDF to DOCX.

Original file: test.pdf
Conversion date: " . date('Y-m-d H:i:s') . "

In a production environment, this would contain the actual extracted text from your PDF document.

The PDF file has been successfully processed and converted to the requested format.

This is a multi-paragraph document to test proper formatting and structure.";

$outputPath = storage_path('app/private/converted/documents/test-output.docx');

try {
    // Create output directory if it doesn't exist
    $outputDir = dirname($outputPath);
    if (!file_exists($outputDir)) {
        mkdir($outputDir, 0755, true);
    }
    
    // Test the createDocxFromText method directly
    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('createDocxFromText');
    $method->setAccessible(true);
    $method->invoke($service, $testText, $outputPath);
    
    if (file_exists($outputPath)) {
        $fileSize = filesize($outputPath);
        echo "SUCCESS: DOCX file created!\n";
        echo "File path: {$outputPath}\n";
        echo "File size: {$fileSize} bytes\n";
        
        if ($fileSize > 1000) {
            echo "✅ File size looks good (not corrupted)\n";
        } else {
            echo "❌ File size too small, might be corrupted\n";
        }
    } else {
        echo "❌ File was not created\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
