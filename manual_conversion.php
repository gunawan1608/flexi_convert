<?php

require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Conversion;
use App\Services\DocumentConversionService;
use Illuminate\Support\Str;

// Get the latest conversion
$conversion = Conversion::latest()->first();

if (!$conversion) {
    echo "No conversions found\n";
    exit;
}

echo "Processing conversion:\n";
echo "ID: {$conversion->id}\n";
echo "File: {$conversion->original_filename}\n";
echo "Status: {$conversion->status}\n";
echo "Target format: {$conversion->target_format}\n";

// Update status to processing
$conversion->update(['status' => 'processing', 'progress' => 10]);

try {
    $conversionService = new DocumentConversionService();
    
    // Get file paths
    $inputPath = storage_path('app/private/' . $conversion->file_path);
    $outputDir = storage_path('app/private/converted/documents/');
    
    if (!file_exists($outputDir)) {
        mkdir($outputDir, 0755, true);
        echo "Created output directory: {$outputDir}\n";
    }
    
    $outputFilename = Str::uuid() . '.' . $conversion->target_extension;
    $outputPath = $outputDir . $outputFilename;
    
    echo "Input path: {$inputPath}\n";
    echo "Output path: {$outputPath}\n";
    
    $conversion->update(['progress' => 30]);
    
    // Use DocumentConversionService for actual conversion
    $result = $conversionService->convertDocument(
        $inputPath,
        $outputPath,
        $conversion->original_extension,
        $conversion->target_extension,
        $conversion->settings ?? []
    );
    
    $conversion->update(['progress' => 80]);
    
    if ($result['success']) {
        $conversion->update([
            'status' => 'completed',
            'progress' => 100,
            'converted_file_path' => 'converted/documents/' . $outputFilename,
            'converted_filename' => $outputFilename,
            'completed_at' => now()
        ]);
        
        echo "Conversion completed successfully!\n";
        echo "Output file: {$outputFilename}\n";
        echo "Full path: {$outputPath}\n";
        
        if (file_exists($outputPath)) {
            echo "File size: " . filesize($outputPath) . " bytes\n";
        }
    } else {
        $conversion->update([
            'status' => 'failed',
            'error_message' => $result['error'] ?? 'Conversion failed'
        ]);
        
        echo "Conversion failed: " . ($result['error'] ?? 'Unknown error') . "\n";
    }
    
} catch (Exception $e) {
    $conversion->update([
        'status' => 'failed',
        'error_message' => $e->getMessage()
    ]);
    
    echo "Exception occurred: " . $e->getMessage() . "\n";
}

echo "Final status: " . $conversion->fresh()->status . "\n";
