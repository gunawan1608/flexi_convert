<?php

require_once 'vendor/autoload.php';

echo "========================================\n";
echo "FlexiConvert - Image Quality Test\n";
echo "========================================\n\n";

// Test the image extraction and quality improvements
$testPdfPath = 'storage/app/pdf-tools/uploads/test.pdf';

if (!file_exists($testPdfPath)) {
    echo "❌ Test PDF file not found: $testPdfPath\n";
    echo "Please upload a PDF file through the web interface first.\n";
    exit(1);
}

echo "📄 Testing PDF: $testPdfPath\n";
echo "📊 File size: " . formatFileSize(filesize($testPdfPath)) . "\n\n";

// Test image extraction improvements
echo "🔄 Testing enhanced image extraction...\n";

try {
    if (!extension_loaded('imagick')) {
        echo "❌ Imagick extension not available\n";
        exit(1);
    }
    
    $imagick = new \Imagick();
    $imagick->setResolution(150, 150);
    $imagick->readImage($testPdfPath);
    
    $pageCount = $imagick->getNumberImages();
    echo "✅ PDF has {$pageCount} pages\n";
    
    $imagick->resetIterator();
    $pageIndex = 0;
    $extractedImages = [];
    
    foreach ($imagick as $page) {
        $pageIndex++;
        
        if ($pageIndex > 3) break; // Test only first 3 pages
        
        try {
            // Use PNG for better quality
            $page->setImageFormat('png');
            $page->setImageCompressionQuality(95);
            
            // Get dimensions
            $width = $page->getImageWidth();
            $height = $page->getImageHeight();
            
            // Enhance image
            $page->normalizeImage();
            $page->enhanceImage();
            
            // Create test image
            $tempDir = sys_get_temp_dir();
            $tempImagePath = $tempDir . '/test_image_' . $pageIndex . '.png';
            $page->writeImage($tempImagePath);
            
            if (file_exists($tempImagePath)) {
                $fileSize = filesize($tempImagePath);
                echo "✅ Page {$pageIndex}: {$width}x{$height} pixels, " . formatFileSize($fileSize) . "\n";
                
                $extractedImages[] = [
                    'page' => $pageIndex,
                    'path' => $tempImagePath,
                    'width' => $width,
                    'height' => $height,
                    'size' => $fileSize
                ];
            } else {
                echo "❌ Page {$pageIndex}: Image extraction failed\n";
            }
            
        } catch (Exception $e) {
            echo "❌ Page {$pageIndex}: Error - " . $e->getMessage() . "\n";
        }
    }
    
    $imagick->clear();
    $imagick->destroy();
    
    echo "\n🔄 Testing Word document creation with images...\n";
    
    // Test Word document creation
    $phpWord = new \PhpOffice\PhpWord\PhpWord();
    $section = $phpWord->addSection();
    
    $section->addTitle('Image Quality Test', 1);
    $section->addTextBreak();
    
    foreach ($extractedImages as $imageInfo) {
        try {
            $section->addTitle('Page ' . $imageInfo['page'], 2);
            
            // Calculate optimal dimensions
            $maxWidth = 550;
            $maxHeight = 700;
            $aspectRatio = $imageInfo['width'] / $imageInfo['height'];
            
            if ($aspectRatio > 1) {
                $displayWidth = min($maxWidth, $imageInfo['width'] * 0.8);
                $displayHeight = $displayWidth / $aspectRatio;
            } else {
                $displayHeight = min($maxHeight, $imageInfo['height'] * 0.8);
                $displayWidth = $displayHeight * $aspectRatio;
            }
            
            $section->addImage($imageInfo['path'], [
                'width' => $displayWidth,
                'height' => $displayHeight,
                'wrappingStyle' => 'inline'
            ]);
            
            $section->addTextBreak();
            $section->addText('Original: ' . $imageInfo['width'] . 'x' . $imageInfo['height'] . ' pixels, ' . formatFileSize($imageInfo['size']), [
                'size' => 9,
                'italic' => true
            ]);
            $section->addTextBreak(2);
            
            echo "✅ Added image from page " . $imageInfo['page'] . " to Word document\n";
            
        } catch (Exception $e) {
            echo "❌ Failed to add image from page " . $imageInfo['page'] . ": " . $e->getMessage() . "\n";
        }
    }
    
    // Save test document
    $testOutputPath = 'storage/app/pdf-tools/outputs/image_quality_test.docx';
    $writer = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
    $writer->save($testOutputPath);
    
    if (file_exists($testOutputPath)) {
        $outputSize = filesize($testOutputPath);
        echo "✅ Test Word document created: " . formatFileSize($outputSize) . "\n";
        echo "📁 Location: $testOutputPath\n";
    } else {
        echo "❌ Failed to create test Word document\n";
    }
    
    // Clean up test images
    foreach ($extractedImages as $imageInfo) {
        if (file_exists($imageInfo['path'])) {
            @unlink($imageInfo['path']);
        }
    }
    
    echo "\n========================================\n";
    echo "Image Quality Test Complete\n";
    echo "========================================\n\n";
    
    echo "📋 Quality Improvements:\n";
    echo "✅ PNG format for better quality\n";
    echo "✅ 95% compression quality\n";
    echo "✅ Image enhancement (normalize + enhance)\n";
    echo "✅ Optimal sizing for Word documents\n";
    echo "✅ Better aspect ratio handling\n\n";
    
    echo "🔍 Next Steps:\n";
    echo "1. Open the test Word document to verify image quality\n";
    echo "2. Compare with previous conversion results\n";
    echo "3. Test with your actual PDF files\n";
    
} catch (Exception $e) {
    echo "❌ Test failed: " . $e->getMessage() . "\n";
}

function formatFileSize($bytes) {
    if ($bytes >= 1024 * 1024) {
        return round($bytes / (1024 * 1024), 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return round($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}

echo "\nTest completed.\n";
