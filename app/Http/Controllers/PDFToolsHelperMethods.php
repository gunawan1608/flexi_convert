<?php

namespace App\Http\Controllers;

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory as WordIOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpPresentation\PhpPresentation;
use PhpOffice\PhpPresentation\Writer\PowerPoint2007;
use PhpOffice\PhpPresentation\Slide\Layout;
use Smalot\PdfParser\Parser as PdfParser;

trait PDFToolsHelperMethods
{
    private function extractWordContent($inputPath)
    {
        try {
            // Try to read DOCX file using PhpWord
            if (class_exists('\PhpOffice\PhpWord\IOFactory')) {
                $phpWord = WordIOFactory::load($inputPath);
                $content = '';
                
                foreach ($phpWord->getSections() as $section) {
                    foreach ($section->getElements() as $element) {
                        if (method_exists($element, 'getText')) {
                            $content .= $element->getText() . "\n";
                        } elseif (method_exists($element, 'getElements')) {
                            foreach ($element->getElements() as $childElement) {
                                if (method_exists($childElement, 'getText')) {
                                    $content .= $childElement->getText() . "\n";
                                }
                            }
                        }
                    }
                }
                
                return $content ?: "Content extracted from Word document: " . basename($inputPath);
            }
        } catch (\Exception $e) {
            \Log::info("Word extraction failed: " . $e->getMessage());
        }
        
        // Fallback
        return "Word document content: " . basename($inputPath) . "\nFile size: " . filesize($inputPath) . " bytes\nConverted on: " . now();
    }
    
    private function extractWordAsHtml($inputPath)
    {
        try {
            if (class_exists('\PhpOffice\PhpWord\IOFactory')) {
                $phpWord = WordIOFactory::load($inputPath);
                
                // Convert to HTML for better PDF rendering
                $htmlWriter = WordIOFactory::createWriter($phpWord, 'HTML');
                $tempHtmlPath = sys_get_temp_dir() . '/' . uniqid() . '.html';
                $htmlWriter->save($tempHtmlPath);
                
                $htmlContent = file_get_contents($tempHtmlPath);
                unlink($tempHtmlPath);
                
                // Clean up HTML for better PDF conversion
                $htmlContent = $this->cleanHtmlForPdf($htmlContent);
                
                return $htmlContent;
            }
        } catch (\Exception $e) {
            \Log::info("Word HTML extraction failed: " . $e->getMessage());
        }
        
        // Fallback to simple text extraction
        $textContent = $this->extractWordContent($inputPath);
        return '<div style="font-family: Arial, sans-serif; line-height: 1.6;">' . nl2br(htmlspecialchars($textContent)) . '</div>';
    }
    
    private function cleanHtmlForPdf($html)
    {
        // Remove problematic elements for PDF conversion
        $html = preg_replace('/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/mi', '', $html);
        $html = preg_replace('/<style\b[^<]*(?:(?!<\/style>)<[^<]*)*<\/style>/mi', '', $html);
        
        // Add basic styling for better PDF appearance
        $css = '<style>
            body { font-family: Arial, sans-serif; font-size: 12pt; line-height: 1.4; }
            h1, h2, h3, h4, h5, h6 { color: #333; margin-top: 1em; margin-bottom: 0.5em; }
            p { margin-bottom: 1em; }
            table { border-collapse: collapse; width: 100%; margin-bottom: 1em; }
            td, th { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #f2f2f2; font-weight: bold; }
        </style>';
        
        // Insert CSS into HTML
        if (strpos($html, '<head>') !== false) {
            $html = str_replace('<head>', '<head>' . $css, $html);
        } else {
            $html = $css . $html;
        }
        
        return $html;
    }
    
    private function extractPDFContent($inputPath)
    {
        try {
            // Try to extract text from PDF using smalot/pdfparser
            if (class_exists('\Smalot\PdfParser\Parser')) {
                $parser = new PdfParser();
                $pdf = $parser->parseFile($inputPath);
                $text = $pdf->getText();
                
                return $text ?: "PDF content extracted from: " . basename($inputPath);
            }
        } catch (\Exception $e) {
            \Log::info("PDF extraction failed: " . $e->getMessage());
        }
        
        // Fallback
        return "PDF document content: " . basename($inputPath) . "\nFile size: " . filesize($inputPath) . " bytes\nConverted on: " . now();
    }
    
    private function createWordDocument($textContent, $outputPath, $originalPath)
    {
        try {
            if (class_exists('\PhpOffice\PhpWord\PhpWord')) {
                $phpWord = new PhpWord();
                $section = $phpWord->addSection();
                
                // Add title
                $section->addTitle('Converted Document', 1);
                $section->addTextBreak();
                
                // Add metadata
                $section->addText('Original file: ' . basename($originalPath), ['bold' => true]);
                $section->addText('Converted on: ' . now());
                $section->addTextBreak(2);
                
                // Add content
                $lines = explode("\n", $textContent);
                foreach ($lines as $line) {
                    if (trim($line)) {
                        $section->addText(trim($line));
                        $section->addTextBreak();
                    }
                }
                
                $writer = WordIOFactory::createWriter($phpWord, 'Word2007');
                $writer->save($outputPath);
                
                return $outputPath;
            }
        } catch (\Exception $e) {
            \Log::error("Word document creation failed: " . $e->getMessage());
        }
        
        // Fallback: create simple text file with .docx extension
        $content = "CONVERTED DOCUMENT\n\n";
        $content .= "Original file: " . basename($originalPath) . "\n";
        $content .= "Converted on: " . now() . "\n\n";
        $content .= "CONTENT:\n" . $textContent;
        
        file_put_contents($outputPath, $content);
        return $outputPath;
    }
    
    private function createExcelDocument($textContent, $outputPath, $originalPath)
    {
        try {
            if (class_exists('\PhpOffice\PhpSpreadsheet\Spreadsheet')) {
                $spreadsheet = new Spreadsheet();
                $sheet = $spreadsheet->getActiveSheet();
                
                // Set headers
                $sheet->setCellValue('A1', 'Converted Document');
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
                
                $sheet->setCellValue('A3', 'Original file:');
                $sheet->setCellValue('B3', basename($originalPath));
                $sheet->setCellValue('A4', 'Converted on:');
                $sheet->setCellValue('B4', now());
                
                // Add content
                $lines = explode("\n", $textContent);
                $row = 6;
                foreach ($lines as $line) {
                    if (trim($line)) {
                        $sheet->setCellValue('A' . $row, trim($line));
                        $row++;
                    }
                }
                
                $writer = new Xlsx($spreadsheet);
                $writer->save($outputPath);
                
                return $outputPath;
            }
        } catch (\Exception $e) {
            \Log::error("Excel document creation failed: " . $e->getMessage());
        }
        
        // Fallback
        $content = "CONVERTED DOCUMENT\n\n";
        $content .= "Original file: " . basename($originalPath) . "\n";
        $content .= "Converted on: " . now() . "\n\n";
        $content .= "CONTENT:\n" . $textContent;
        
        file_put_contents($outputPath, $content);
        return $outputPath;
    }
    
    private function createPowerPointDocument($textContent, $outputPath, $originalPath)
    {
        try {
            if (class_exists('\PhpOffice\PhpPresentation\PhpPresentation')) {
                $presentation = new PhpPresentation();
                $slide = $presentation->getActiveSlide();
                
                // Title slide
                $titleShape = $slide->createRichTextShape();
                $titleShape->setHeight(100)
                          ->setWidth(800)
                          ->setOffsetX(100)
                          ->setOffsetY(100);
                
                $titleParagraph = $titleShape->createParagraph();
                $titleRun = $titleParagraph->createTextRun('Converted Document');
                $titleRun->getFont()->setBold(true)->setSize(24);
                
                // Content
                $contentShape = $slide->createRichTextShape();
                $contentShape->setHeight(400)
                            ->setWidth(800)
                            ->setOffsetX(100)
                            ->setOffsetY(250);
                
                $contentParagraph = $contentShape->createParagraph();
                $contentRun = $contentParagraph->createTextRun($textContent);
                $contentRun->getFont()->setSize(12);
                
                $writer = new PowerPoint2007($presentation);
                $writer->save($outputPath);
                
                return $outputPath;
            }
        } catch (\Exception $e) {
            \Log::error("PowerPoint document creation failed: " . $e->getMessage());
        }
        
        // Fallback
        $content = "CONVERTED DOCUMENT\n\n";
        $content .= "Original file: " . basename($originalPath) . "\n";
        $content .= "Converted on: " . now() . "\n\n";
        $content .= "CONTENT:\n" . $textContent;
        
        file_put_contents($outputPath, $content);
        return $outputPath;
    }
    
    private function findLibreOffice()
    {
        $possiblePaths = [
            'C:\Program Files\LibreOffice\program\soffice.exe',
            'C:\Program Files (x86)\LibreOffice\program\soffice.exe',
            '/usr/bin/libreoffice',
            '/usr/local/bin/libreoffice',
            '/opt/libreoffice/program/soffice',
        ];
        
        foreach ($possiblePaths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }
        
        return null;
    }
}
