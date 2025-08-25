<?php

require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Conversion;

echo "Fixing stuck conversions...\n\n";

// Find conversions that are stuck in 'processing' but have output files
$stuckConversions = Conversion::where('status', 'processing')
    ->orWhere('status', 'pending')
    ->get();

foreach ($stuckConversions as $conv) {
    echo "Processing conversion ID: {$conv->id}\n";
    echo "File: {$conv->original_filename}\n";
    echo "Current status: {$conv->status}\n";
    
    // Check if output file exists
    if ($conv->converted_file_path) {
        $fullPath = storage_path('app/private/' . $conv->converted_file_path);
        if (file_exists($fullPath)) {
            // File exists, mark as completed
            $conv->update([
                'status' => 'completed',
                'progress' => 100,
                'completed_at' => now()
            ]);
            echo "✅ Updated to completed (file exists)\n";
        } else {
            echo "❌ Output file missing: {$conv->converted_file_path}\n";
        }
    } else {
        // No converted file path, try to find it
        $outputDir = storage_path('app/private/converted/documents/');
        $files = glob($outputDir . '*.docx');
        
        if (count($files) > 0) {
            // Take the latest file
            $latestFile = end($files);
            $relativePath = 'converted/documents/' . basename($latestFile);
            
            $conv->update([
                'status' => 'completed',
                'progress' => 100,
                'converted_file_path' => $relativePath,
                'converted_filename' => basename($latestFile),
                'completed_at' => now()
            ]);
            echo "✅ Updated to completed with file: " . basename($latestFile) . "\n";
        } else {
            echo "❌ No output files found\n";
        }
    }
    
    echo "---\n";
}

echo "\nDone! Check dashboard for updated statuses.\n";
