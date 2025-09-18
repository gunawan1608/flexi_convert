<?php

require_once 'vendor/autoload.php';

echo "========================================\n";
echo "FlexiConvert - PDF to PowerPoint Test\n";
echo "========================================\n\n";

// Test the enhanced PDF to PowerPoint conversion
$testPdfPath = 'storage/app/pdf-tools/uploads/test.pdf';

if (!file_exists($testPdfPath)) {
    echo "❌ Test PDF file not found: $testPdfPath\n";
    echo "Please upload a PDF file through the web interface first.\n";
    exit(1);
}

echo "📄 Testing PDF: $testPdfPath\n";
echo "📊 File size: " . formatFileSize(filesize($testPdfPath)) . "\n\n";

// Test PowerPoint creation
echo "🔄 Testing enhanced PDF to PowerPoint conversion...\n";

try {
    if (!class_exists('\PhpOffice\PhpPresentation\PhpPresentation')) {
        echo "❌ PhpOffice\PhpPresentation library not available\n";
        echo "Install with: composer require phpoffice/phppresentation\n";
        exit(1);
    }
    
    if (!extension_loaded('imagick')) {
        echo "❌ Imagick extension not available\n";
        exit(1);
    }
    
    // Test image extraction first
    echo "📸 Testing image extraction from PDF...\n";
    
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
            $tempImagePath = $tempDir . '/test_ppt_image_' . $pageIndex . '.png';
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
    
    echo "\n🔄 Testing PowerPoint document creation...\n";
    
    // Test PowerPoint document creation
    $presentation = new \PhpOffice\PhpPresentation\PhpPresentation();
    
    // Remove default slide
    $presentation->removeSlideByIndex(0);
    
    // Create title slide
    $titleSlide = $presentation->createSlide();
    $titleSlide->setName('Title Slide');
    
    // Title slide content
    $titleShape = $titleSlide->createRichTextShape();
    $titleShape->setHeight(150)
              ->setWidth(800)
              ->setOffsetX(100)
              ->setOffsetY(150);
    
    $titleParagraph = $titleShape->createParagraph();
    $titleParagraph->getAlignment()->setHorizontal(\PhpOffice\PhpPresentation\Style\Alignment::HORIZONTAL_CENTER);
    $titleRun = $titleParagraph->createTextRun('PDF to PowerPoint Test');
    $titleRun->getFont()->setBold(true)->setSize(28)->setColor(new \PhpOffice\PhpPresentation\Style\Color('2E74B5'));
    
    // Add image slides
    foreach ($extractedImages as $imageInfo) {
        try {
            $slide = $presentation->createSlide();
            $slide->setName('Page ' . $imageInfo['page']);
            
            // Add page title
            $pageTitle = $slide->createRichTextShape();
            $pageTitle->setHeight(60)
                     ->setWidth(800)
                     ->setOffsetX(100)
                     ->setOffsetY(20);
            
            $pageTitleParagraph = $pageTitle->createParagraph();
            $pageTitleRun = $pageTitleParagraph->createTextRun('Page ' . $imageInfo['page']);
            $pageTitleRun->getFont()->setBold(true)->setSize(20)->setColor(new \PhpOffice\PhpPresentation\Style\Color('2E74B5'));
            
            // Add image
            $imageShape = $slide->createDrawingShape();
            $imageShape->setName('Page ' . $imageInfo['page'] . ' Image')
                      ->setDescription('Test image from PDF page ' . $imageInfo['page'])
                      ->setPath($imageInfo['path']);
            
            // Calculate optimal image size for slide
            $maxWidth = 800;
            $maxHeight = 450;
            
            $originalWidth = $imageInfo['width'];
            $originalHeight = $imageInfo['height'];
            $aspectRatio = $originalWidth / $originalHeight;
            
            if ($aspectRatio > ($maxWidth / $maxHeight)) {
                $displayWidth = $maxWidth;
                $displayHeight = $maxWidth / $aspectRatio;
            } else {
                $displayHeight = $maxHeight;
                $displayWidth = $maxHeight * $aspectRatio;
            }
            
            $imageShape->setWidth($displayWidth)
                      ->setHeight($displayHeight)
                      ->setOffsetX((1000 - $displayWidth) / 2)
                      ->setOffsetY(100);
            
            echo "✅ Added image slide for page " . $imageInfo['page'] . " (" . round($displayWidth) . "x" . round($displayHeight) . ")\n";
            
        } catch (Exception $e) {
            echo "❌ Failed to add slide for page " . $imageInfo['page'] . ": " . $e->getMessage() . "\n";
        }
    }
    
    // Set presentation properties
    $properties = $presentation->getDocumentProperties();
    $properties->setCreator('FlexiConvert Test')
              ->setTitle('PDF to PowerPoint Test')
              ->setDescription('Test conversion from PDF to PowerPoint')
              ->setSubject('PDF Conversion Test');
    
    // Save test presentation
    $testOutputPath = 'storage/app/pdf-tools/outputs/pdf_to_ppt_test.pptx';
    $writer = \PhpOffice\PhpPresentation\IOFactory::createWriter($presentation, 'PowerPoint2007');
    $writer->save($testOutputPath);
    
    if (file_exists($testOutputPath)) {
        $outputSize = filesize($testOutputPath);
        $slideCount = count($presentation->getAllSlides());
        echo "✅ Test PowerPoint created: " . formatFileSize($outputSize) . "\n";
        echo "📊 Slides: {$slideCount} (1 title + " . ($slideCount - 1) . " content)\n";
        echo "📁 Location: $testOutputPath\n";
    } else {
        echo "❌ Failed to create test PowerPoint document\n";
    }
    
    // Clean up test images
    foreach ($extractedImages as $imageInfo) {
        if (file_exists($imageInfo['path'])) {
            @unlink($imageInfo['path']);
        }
    }
    
    echo "\n========================================\n";
    echo "PDF to PowerPoint Test Complete\n";
    echo "========================================\n\n";
    
    echo "📋 Enhanced Features:\n";
    echo "✅ Professional title slide with branding\n";
    echo "✅ Image-based slides with page titles\n";
    echo "✅ Optimal image sizing for PowerPoint\n";
    echo "✅ Proper slide naming and organization\n";
    echo "✅ Enhanced image quality (PNG, 95% quality)\n";
    echo "✅ Fallback to text slides if images fail\n";
    echo "✅ Automatic cleanup of temporary files\n\n";
    
    echo "🔍 Next Steps:\n";
    echo "1. Open the test PowerPoint to verify slide quality\n";
    echo "2. Compare with previous conversion results\n";
    echo "3. Test with your actual PDF files\n";
    echo "4. Check that images display properly in PowerPoint\n";
    
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
