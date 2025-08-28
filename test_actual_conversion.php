<?php

// Simple test without Laravel bootstrap
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "Testing Actual Image Conversion\n";
echo "===============================\n\n";

// Create a simple test image using Imagick
try {
    $imagick = new Imagick();
    $imagick->newImage(100, 100, new ImagickPixel('red'));
    $imagick->setImageFormat('JPEG');
    
    // Save test JPG
    $testJpgPath = 'test_input.jpg';
    $imagick->writeImage($testJpgPath);
    echo "✅ Created test JPG image: {$testJpgPath}\n";
    
    // Test JPG to PNG conversion using basic Imagick
    $outputPngPath = 'test_output.png';
    echo "🔄 Testing JPG to PNG conversion...\n";
    
    // Simple conversion test
    $imagick2 = new Imagick($testJpgPath);
    $imagick2->setImageFormat('PNG');
    $result = $imagick2->writeImage($outputPngPath);
    
    if ($result && file_exists($outputPngPath)) {
        $outputSize = filesize($outputPngPath);
        echo "✅ JPG to PNG conversion successful! Output size: {$outputSize} bytes\n";
        
        // Verify it's actually a PNG
        $imageInfo = getimagesize($outputPngPath);
        if ($imageInfo && $imageInfo['mime'] === 'image/png') {
            echo "✅ Output file is verified as PNG format\n";
        } else {
            echo "❌ Output file is NOT a valid PNG\n";
        }
    } else {
        echo "❌ JPG to PNG conversion failed\n";
    }
    
    // Test WebP creation and conversion
    echo "\n🔄 Testing WebP to PNG conversion...\n";
    $testWebpPath = 'test_input.webp';
    $imagick->setImageFormat('WEBP');
    $imagick->writeImage($testWebpPath);
    echo "✅ Created test WebP image: {$testWebpPath}\n";
    
    $outputPng2Path = 'test_output2.png';
    $imagick3 = new Imagick($testWebpPath);
    $imagick3->setImageFormat('PNG');
    $result2 = $imagick3->writeImage($outputPng2Path);
    
    if ($result2 && file_exists($outputPng2Path)) {
        $outputSize2 = filesize($outputPng2Path);
        echo "✅ WebP to PNG conversion successful! Output size: {$outputSize2} bytes\n";
        
        // Verify it's actually a PNG
        $imageInfo2 = getimagesize($outputPng2Path);
        if ($imageInfo2 && $imageInfo2['mime'] === 'image/png') {
            echo "✅ Output file is verified as PNG format\n";
        } else {
            echo "❌ Output file is NOT a valid PNG\n";
        }
    } else {
        echo "❌ WebP to PNG conversion failed\n";
    }
    
    $imagick3->clear();
    $imagick3->destroy();
    
    // Cleanup
    $imagick->clear();
    $imagick->destroy();
    
    // Clean up test files
    if (file_exists($testJpgPath)) unlink($testJpgPath);
    if (file_exists($testWebpPath)) unlink($testWebpPath);
    if (file_exists($outputPngPath)) unlink($outputPngPath);
    if (file_exists($outputPng2Path)) unlink($outputPng2Path);
    
    echo "\n✅ Test completed successfully!\n";
    
} catch (Exception $e) {
    echo "❌ Error during conversion test: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}
