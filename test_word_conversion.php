<?php

require_once 'vendor/autoload.php';

use App\Http\Controllers\PDFToolsHelperMethods;
use Illuminate\Support\Facades\Log;

// Test LibreOffice detection
echo "Testing LibreOffice detection...\n";

try {
    $reflection = new ReflectionClass(PDFToolsHelperMethods::class);
    $method = $reflection->getMethod('findLibreOffice');
    $method->setAccessible(true);
    
    $libreOfficePath = $method->invoke(null);
    echo "LibreOffice path: " . ($libreOfficePath ?: 'NOT FOUND') . "\n";
    
    if ($libreOfficePath) {
        // Test LibreOffice version
        $process = new Symfony\Component\Process\Process([$libreOfficePath, '--version']);
        $process->run();
        
        echo "LibreOffice version test:\n";
        echo "Exit code: " . $process->getExitCode() . "\n";
        echo "Output: " . $process->getOutput() . "\n";
        echo "Error: " . $process->getErrorOutput() . "\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}
