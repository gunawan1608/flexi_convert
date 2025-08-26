<?php

require_once 'vendor/autoload.php';

use Illuminate\Database\Capsule\Manager as Capsule;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Check PdfProcessing record
$record = App\Models\PdfProcessing::find(11);

if ($record) {
    echo "PdfProcessing ID 11 found:\n";
    echo "- User ID: " . $record->user_id . "\n";
    echo "- Tool Name: " . $record->tool_name . "\n";
    echo "- Status: " . $record->status . "\n";
    echo "- Input Path: " . ($record->input_path ?? 'NULL') . "\n";
    echo "- Output Path: " . ($record->output_path ?? 'NULL') . "\n";
    echo "- Output Filename: " . ($record->output_filename ?? 'NULL') . "\n";
    
    // Check if output file exists in storage
    if ($record->output_path) {
        $exists = Storage::exists($record->output_path);
        echo "- File exists in storage: " . ($exists ? 'YES' : 'NO') . "\n";
        if ($exists) {
            $size = Storage::size($record->output_path);
            echo "- File size: " . $size . " bytes\n";
        }
    }
} else {
    echo "PdfProcessing ID 11 not found\n";
}

// List all PdfProcessing records
echo "\nAll PdfProcessing records:\n";
$all = App\Models\PdfProcessing::all();
foreach ($all as $item) {
    echo "ID: {$item->id}, Status: {$item->status}, Tool: {$item->tool_name}, Output: " . ($item->output_path ?? 'NULL') . "\n";
}
