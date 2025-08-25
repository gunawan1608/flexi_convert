<?php

require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Conversion;

echo "Checking conversion statuses...\n\n";

$conversions = Conversion::latest()->take(10)->get();

foreach ($conversions as $conv) {
    echo "ID: {$conv->id}\n";
    echo "File: {$conv->original_filename}\n";
    echo "Status: {$conv->status}\n";
    echo "Progress: {$conv->progress}%\n";
    echo "Created: {$conv->created_at}\n";
    echo "Updated: {$conv->updated_at}\n";
    
    if ($conv->converted_file_path) {
        $fullPath = storage_path('app/private/' . $conv->converted_file_path);
        echo "Output file: {$conv->converted_file_path}\n";
        echo "File exists: " . (file_exists($fullPath) ? 'YES' : 'NO') . "\n";
        if (file_exists($fullPath)) {
            echo "File size: " . filesize($fullPath) . " bytes\n";
        }
    }
    
    echo "---\n";
}
