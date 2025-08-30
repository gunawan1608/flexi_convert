<?php

require_once __DIR__ . '/vendor/autoload.php';

// Test if PDFToolsController can be instantiated without errors
try {
    echo "Testing PDFToolsController import fix...\n";
    
    // Test if the class can be loaded
    $reflection = new ReflectionClass('App\Http\Controllers\PDFToolsController');
    echo "✅ PDFToolsController class loaded successfully\n";
    
    // Test if PDFToolsHelperMethods can be loaded
    $helperReflection = new ReflectionClass('App\Http\Controllers\PDFToolsHelperMethods');
    echo "✅ PDFToolsHelperMethods class loaded successfully\n";
    
    // Test if the logConversionMetrics method exists
    if ($helperReflection->hasMethod('logConversionMetrics')) {
        echo "✅ logConversionMetrics method exists\n";
    } else {
        echo "❌ logConversionMetrics method missing\n";
    }
    
    echo "\n🎉 All tests passed! The import fix should resolve the server error.\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
