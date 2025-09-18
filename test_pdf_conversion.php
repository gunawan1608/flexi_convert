<?php

require_once 'vendor/autoload.php';

use App\Http\Controllers\PDFToolsHelperMethods;
use Illuminate\Support\Facades\Log;

// Test PDF to Word conversion with new enhanced methods

echo "========================================\n";
echo "FlexiConvert - PDF to Word Test\n";
echo "========================================\n\n";

// Test file path (adjust this to your test PDF)
$testPdfPath = 'storage/app/pdf-tools/uploads/test.pdf';
$outputPath = 'storage/app/pdf-tools/outputs/test_conversion_' . uniqid() . '.docx';

if (!file_exists($testPdfPath)) {
    echo "❌ Test PDF file not found: $testPdfPath\n";
    echo "Please upload a PDF file through the web interface first.\n";
    exit(1);
}

echo "📄 Input PDF: $testPdfPath\n";
echo "📝 Output Word: $outputPath\n\n";

echo "Testing PDF to Word conversion methods...\n\n";

try {
    // Test Method 1: LibreOffice conversion
    echo "🔄 Testing LibreOffice conversion...\n";
    try {
        $result = PDFToolsHelperMethods::convertPdfToWordWithLibreOffice($testPdfPath, $outputPath);
        if ($result && file_exists($result)) {
            $fileSize = filesize($result);
            echo "✅ LibreOffice conversion successful!\n";
            echo "   File size: " . formatFileSize($fileSize) . "\n";
            echo "   Location: $result\n\n";
            
            // Test if file can be opened
            if ($fileSize > 15000) {
                echo "✅ File size indicates good quality conversion\n\n";
            } else {
                echo "⚠️  File size is small, may need improvement\n\n";
            }
        }
    } catch (Exception $e) {
        echo "❌ LibreOffice conversion failed: " . $e->getMessage() . "\n\n";
        
        // Test Method 2: Advanced extraction
        echo "🔄 Testing advanced extraction method...\n";
        try {
            $outputPath2 = str_replace('.docx', '_advanced.docx', $outputPath);
            $result = PDFToolsHelperMethods::convertPdfToWordWithAdvancedExtraction($testPdfPath, $outputPath2);
            if ($result && file_exists($result)) {
                $fileSize = filesize($result);
                echo "✅ Advanced extraction successful!\n";
                echo "   File size: " . formatFileSize($fileSize) . "\n";
                echo "   Location: $result\n\n";
            }
        } catch (Exception $e) {
            echo "❌ Advanced extraction failed: " . $e->getMessage() . "\n\n";
            
            // Test Method 3: Simple fallback
            echo "🔄 Testing simple fallback method...\n";
            try {
                $outputPath3 = str_replace('.docx', '_simple.docx', $outputPath);
                $result = PDFToolsHelperMethods::createSimpleWordFromPdf($testPdfPath, $outputPath3);
                if ($result && file_exists($result)) {
                    $fileSize = filesize($result);
                    echo "✅ Simple fallback successful!\n";
                    echo "   File size: " . formatFileSize($fileSize) . "\n";
                    echo "   Location: $result\n\n";
                }
            } catch (Exception $e) {
                echo "❌ All conversion methods failed: " . $e->getMessage() . "\n\n";
            }
        }
    }
    
    echo "========================================\n";
    echo "Conversion Test Complete\n";
    echo "========================================\n\n";
    
    echo "📋 Recommendations:\n";
    echo "1. Install LibreOffice for best quality (run install-libreoffice.bat)\n";
    echo "2. Install Poppler Utils for better text extraction\n";
    echo "3. Check storage/logs/laravel.log for detailed conversion logs\n\n";
    
    echo "🔍 Quality Comparison:\n";
    echo "- LibreOffice: Highest quality, preserves formatting (like iLovePDF)\n";
    echo "- Advanced Extraction: Good quality, structured text with formatting\n";
    echo "- Simple Fallback: Basic quality, plain text conversion\n\n";
    
} catch (Exception $e) {
    echo "❌ Test failed with error: " . $e->getMessage() . "\n";
}

function formatFileSize($bytes) {
    if ($bytes >= 1024 * 1024) {
        return round($bytes / (1024 * 1024), 1) . ' MB';
    } elseif ($bytes >= 1024) {
        return round($bytes / 1024, 1) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}

echo "Test completed. Check the generated files to compare quality.\n";
