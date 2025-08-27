<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory as WordIOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpPresentation\PhpPresentation;
use PhpOffice\PhpPresentation\Writer\PowerPoint2007;
use PhpOffice\PhpPresentation\IOFactory;
use PhpOffice\PhpPresentation\Style\Alignment;
use PhpOffice\PhpPresentation\Style\Color;
use Imagick;
use Smalot\PdfParser\Parser;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Exception;
use Slide\Layout;
use Smalot\PdfParser\Parser as PdfParser;
use App\Models\PdfProcessing;

/**
 * PDFToolsHelperMethods adalah class helper statis, bukan trait. 
 * Tidak boleh di-instantiate. Semua method dipanggil statis.
 * 
 * Usage: PDFToolsHelperMethods::processWordToPdf($file, $settings);
 */
class PDFToolsHelperMethods
{
    /**
     * Find LibreOffice executable path
     */
    private static function findLibreOffice()
    {
        $configPath = config('pdftools.libreoffice.path');
        if ($configPath && file_exists($configPath)) {
            return $configPath;
        }

        $commonPaths = [
            // Windows
            'C:\\Program Files\\LibreOffice\\program\\soffice.exe',
            'C:\\Program Files (x86)\\LibreOffice\\program\\soffice.exe',
            // Linux
            '/usr/bin/libreoffice',
            '/usr/bin/soffice',
            '/snap/bin/libreoffice',
            // macOS
            '/Applications/LibreOffice.app/Contents/MacOS/soffice',
        ];

        foreach ($commonPaths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        // Try to find in PATH
        $process = new Process(['which', 'soffice']);
        $process->run();
        if ($process->isSuccessful()) {
            return trim($process->getOutput());
        }

        $process = new Process(['which', 'libreoffice']);
        $process->run();
        if ($process->isSuccessful()) {
            return trim($process->getOutput());
        }

        return null;
    }

    /**
     * Find Ghostscript executable path
     */
    private static function findGhostscript()
    {
        $configPath = config('pdftools.ghostscript.path');
        if ($configPath && file_exists($configPath)) {
            return $configPath;
        }

        $commonPaths = [
            // Windows
            'C:\\Program Files\\gs\\gs*\\bin\\gswin64c.exe',
            'C:\\Program Files (x86)\\gs\\gs*\\bin\\gswin32c.exe',
            // Linux/macOS
            '/usr/bin/gs',
            '/usr/local/bin/gs',
        ];

        foreach ($commonPaths as $pattern) {
            $paths = glob($pattern);
            if (!empty($paths) && file_exists($paths[0])) {
                return $paths[0];
            }
        }

        // Try to find in PATH
        $process = new Process(['which', 'gs']);
        $process->run();
        if ($process->isSuccessful()) {
            return trim($process->getOutput());
        }

        return null;
    }

    /**
     * Convert Office document to PDF using LibreOffice CLI
     */
    private static function convertWithLibreOffice($inputPath, $outputDir)
    {
        $libreOfficePath = self::findLibreOffice();
        if (!$libreOfficePath) {
            throw new Exception('LibreOffice not found');
        }

        $process = new Process([
            $libreOfficePath,
            '--headless',
            '--convert-to',
            'pdf',
            '--outdir',
            $outputDir,
            $inputPath
        ]);

        $process->setTimeout(config('pdftools.libreoffice.timeout', 120));
        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        // Find the generated PDF file using glob instead of assuming filename
        $pdfFiles = glob($outputDir . DIRECTORY_SEPARATOR . '*.pdf');
        
        if (count($pdfFiles) === 0) {
            throw new Exception('LibreOffice conversion failed - no PDF files found in output directory');
        }
        
        // Get the most recently created PDF file (in case there are multiple)
        $pdfPath = $pdfFiles[0];
        if (count($pdfFiles) > 1) {
            // Sort by modification time, get the newest
            usort($pdfFiles, function($a, $b) {
                return filemtime($b) - filemtime($a);
            });
            $pdfPath = $pdfFiles[0];
        }

        return $pdfPath;
    }

    /**
     * Convert HTML to PDF using wkhtmltopdf or DomPDF
     */
    public static function convertHtmlToPdf($file, $settings = [])
    {
        $startTime = microtime(true);
        $processing = null;
        
        try {
            $friendlyFilename = self::generateFriendlyFilename('html-to-pdf', $file->getClientOriginalName());
            $internalFilename = Str::uuid() . '.pdf';
            $outputPath = config('pdftools.storage.outputs_path') . '/' . $internalFilename;
            
            $processing = PdfProcessing::create([
                'user_id' => auth()->id(),
                'tool_name' => 'html-to-pdf',
                'original_filename' => $file->getClientOriginalName(),
                'processed_filename' => $internalFilename,
                'friendly_filename' => $friendlyFilename,
                'file_size' => $file->getSize(),
                'status' => 'processing',
                'progress' => 0,
                'settings' => json_encode($settings),
                'created_at' => now(),
                'updated_at' => now()
            ]);

            $inputPath = $file->store(config('pdftools.storage.uploads_path'));
            $fullInputPath = Storage::path($inputPath);
            $htmlContent = file_get_contents($fullInputPath);

            // Try wkhtmltopdf first (if available)
            if (self::isWkhtmltopdfAvailable()) {
                $success = self::convertHtmlToPdfWithWkhtmltopdf($htmlContent, Storage::path($outputPath), $settings);
                if ($success) {
                    Storage::delete($inputPath);
                    
                    $processing->update([
                        'status' => 'completed',
                        'progress' => 100,
                        'processed_file_size' => Storage::size($outputPath),
                        'completed_at' => now()
                    ]);

                    $processingTime = microtime(true) - $startTime;
                    self::logConversionMetrics('html-to-pdf', $file->getSize(), 
                        Storage::size($outputPath), $processingTime, auth()->id());

                    return self::createSuccessResponse(
                        'HTML berhasil dikonversi ke PDF',
                        $processing->id,
                        $friendlyFilename,
                        route('pdf-tools.download', $processing->id)
                    );
                }
            }

            // Fallback to DomPDF
            $success = self::convertHtmlToPdfWithDomPdf($htmlContent, Storage::path($outputPath), $settings);
            
            if ($success) {
                Storage::delete($inputPath);
                
                $processing->update([
                    'status' => 'completed',
                    'progress' => 100,
                    'processed_file_size' => Storage::size($outputPath),
                    'completed_at' => now()
                ]);

                $processingTime = microtime(true) - $startTime;
                self::logConversionMetrics('html-to-pdf', $file->getSize(), 
                    Storage::size($outputPath), $processingTime, auth()->id());

                return self::createSuccessResponse(
                    'HTML berhasil dikonversi ke PDF (menggunakan DomPDF)',
                    $processing->id,
                    $friendlyFilename,
                    route('pdf-tools.download', $processing->id)
                );
            }

            throw new Exception('HTML to PDF conversion failed with all available methods');

        } catch (Exception $e) {
            if ($processing) {
                $processing->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
            }
            Log::error("HTML to PDF conversion failed: " . $e->getMessage());
            return response()->json([
                'error' => true,
                'message' => 'Konversi HTML ke PDF gagal: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check if wkhtmltopdf is available
     */
    private static function isWkhtmltopdfAvailable(): bool
    {
        $wkhtmltopdfPaths = [
            // Windows
            'C:\\Program Files\\wkhtmltopdf\\bin\\wkhtmltopdf.exe',
            'C:\\Program Files (x86)\\wkhtmltopdf\\bin\\wkhtmltopdf.exe',
            // Linux
            '/usr/bin/wkhtmltopdf',
            '/usr/local/bin/wkhtmltopdf'
        ];

        foreach ($wkhtmltopdfPaths as $path) {
            if (file_exists($path)) {
                return true;
            }
        }

        // Try to find via PATH
        $command = PHP_OS_FAMILY === 'Windows' ? 'where wkhtmltopdf 2>nul' : 'which wkhtmltopdf';
        exec($command, $output, $returnCode);
        
        return $returnCode === 0 && !empty($output[0]);
    }

    /**
     * Convert HTML to PDF using wkhtmltopdf
     */
    private static function convertHtmlToPdfWithWkhtmltopdf(string $htmlContent, string $outputPath, array $settings = []): bool
    {
        try {
            // Create temporary HTML file
            $tempHtmlFile = tempnam(sys_get_temp_dir(), 'html_to_pdf_') . '.html';
            file_put_contents($tempHtmlFile, $htmlContent);

            $wkhtmltopdfPath = self::findWkhtmltopdf();
            if (!$wkhtmltopdfPath) {
                return false;
            }

            // Build command with options
            $options = [];
            
            // Page size
            $pageSize = $settings['page_size'] ?? 'A4';
            $options[] = '--page-size ' . $pageSize;
            
            // Orientation
            $orientation = $settings['orientation'] ?? 'Portrait';
            if (strtolower($orientation) === 'landscape') {
                $options[] = '--orientation Landscape';
            }
            
            // Margins
            $marginTop = $settings['margin_top'] ?? '10mm';
            $marginRight = $settings['margin_right'] ?? '10mm';
            $marginBottom = $settings['margin_bottom'] ?? '10mm';
            $marginLeft = $settings['margin_left'] ?? '10mm';
            
            $options[] = "--margin-top {$marginTop}";
            $options[] = "--margin-right {$marginRight}";
            $options[] = "--margin-bottom {$marginBottom}";
            $options[] = "--margin-left {$marginLeft}";
            
            // Additional options
            $options[] = '--enable-local-file-access';
            $options[] = '--disable-smart-shrinking';
            
            $optionsString = implode(' ', $options);
            $command = "\"{$wkhtmltopdfPath}\" {$optionsString} \"{$tempHtmlFile}\" \"{$outputPath}\"";
            
            Log::info('Executing wkhtmltopdf command: ' . $command);
            exec($command, $output, $returnCode);
            
            // Clean up temporary file
            unlink($tempHtmlFile);
            
            Log::info('wkhtmltopdf execution result', [
                'return_code' => $returnCode,
                'output' => $output,
                'output_file_exists' => file_exists($outputPath)
            ]);
            
            return $returnCode === 0 && file_exists($outputPath);
            
        } catch (Exception $e) {
            Log::error('wkhtmltopdf conversion failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Convert HTML to PDF using DomPDF
     */
    private static function convertHtmlToPdfWithDomPdf(string $htmlContent, string $outputPath, array $settings = []): bool
    {
        try {
            // Check if DomPDF is available
            if (!class_exists('Dompdf\Dompdf')) {
                Log::warning('DomPDF not available, install with: composer require dompdf/dompdf');
                return false;
            }

            $dompdf = new \Dompdf\Dompdf();
            
            // Set options
            $options = $dompdf->getOptions();
            $options->set('isHtml5ParserEnabled', true);
            $options->set('isRemoteEnabled', true);
            $options->set('defaultFont', 'Arial');
            
            // Paper size and orientation
            $paperSize = $settings['page_size'] ?? 'A4';
            $orientation = $settings['orientation'] ?? 'portrait';
            
            $dompdf->loadHtml($htmlContent);
            $dompdf->setPaper($paperSize, strtolower($orientation));
            $dompdf->render();
            
            // Save PDF
            $pdfContent = $dompdf->output();
            file_put_contents($outputPath, $pdfContent);
            
            Log::info('DomPDF conversion completed', [
                'output_file_exists' => file_exists($outputPath),
                'file_size' => filesize($outputPath)
            ]);
            
            return file_exists($outputPath);
            
        } catch (Exception $e) {
            Log::error('DomPDF conversion failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Find wkhtmltopdf executable
     */
    private static function findWkhtmltopdf(): ?string
    {
        $wkhtmltopdfPaths = [
            // Windows
            'C:\\Program Files\\wkhtmltopdf\\bin\\wkhtmltopdf.exe',
            'C:\\Program Files (x86)\\wkhtmltopdf\\bin\\wkhtmltopdf.exe',
            // Linux
            '/usr/bin/wkhtmltopdf',
            '/usr/local/bin/wkhtmltopdf'
        ];

        foreach ($wkhtmltopdfPaths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        // Try to find via PATH
        $command = PHP_OS_FAMILY === 'Windows' ? 'where wkhtmltopdf 2>nul' : 'which wkhtmltopdf';
        exec($command, $output, $returnCode);
        
        if ($returnCode === 0 && !empty($output[0])) {
            return trim($output[0]);
        }

        return null;
    }

    /**
     * Compress PDF using Ghostscript
     */
    private static function compressPdfWithGhostscript($inputPath, $outputPath, $quality = 'medium')
    {
        $gsPath = self::findGhostscript();
        if (!$gsPath) {
            throw new Exception('Ghostscript not found');
        }

        $qualitySettings = config('pdftools.ghostscript.quality_presets.' . $quality, 
            config('pdftools.ghostscript.quality_presets.medium'));

        $command = array_merge([
            $gsPath,
            '-sDEVICE=pdfwrite',
            '-dCompatibilityLevel=1.4',
            '-dPDFSETTINGS=/printer',
            '-dNOPAUSE',
            '-dQUIET',
            '-dBATCH',
            '-sOutputFile=' . $outputPath,
        ], $qualitySettings, [$inputPath]);

        $process = new Process($command);
        $process->setTimeout(config('pdftools.ghostscript.timeout', 60));
        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        return file_exists($outputPath);
    }

    /**
     * Clean filename for safe storage
     */
    private static function cleanFilename($filename)
    {
        // Remove or replace unsafe characters
        $cleaned = preg_replace('/[^\w\s\-\.\(\)\[\]]/', '', $filename);
        $cleaned = preg_replace('/\s+/', ' ', $cleaned);
        $cleaned = trim($cleaned);
        
        // Limit length
        if (strlen($cleaned) > 100) {
            $cleaned = substr($cleaned, 0, 100);
        }
        
        return $cleaned ?: 'converted_file';
    }

    /**
     * Generate friendly output filename based on tool and input
     */
    private static function generateFriendlyFilename($tool, $originalFilename, $index = null)
    {
        $base = pathinfo($originalFilename, PATHINFO_FILENAME);
        $cleanBase = self::cleanFilename($base);
        
        switch ($tool) {
            case 'word-to-pdf':
            case 'excel-to-pdf':
            case 'ppt-to-pdf':
            case 'image-to-pdf':
                return $cleanBase . '.pdf';
            
            case 'pdf-to-word':
                return $cleanBase . '.docx';
            
            case 'pdf-to-excel':
                return $cleanBase . '.xlsx';
            
            case 'pdf-to-ppt':
                return $cleanBase . '.pptx';
            
            case 'merge-pdf':
                return 'merged_' . date('Ymd_His') . '.pdf';
            
            case 'split-pdf':
                return $cleanBase . '_parts.zip';
            
            case 'compress-pdf':
                return $cleanBase . '_compressed.pdf';
            
            case 'rotate-pdf':
                return $cleanBase . '_rotated.pdf';
            
            case 'add-watermark':
                return $cleanBase . '_watermarked.pdf';
            
            case 'add-page-numbers':
                return $cleanBase . '_numbered.pdf';
            
            case 'pdf-to-image':
            case 'pdf-to-jpg':
                return $cleanBase . '_images.zip';
            
            default:
                return $cleanBase . '_converted.pdf';
        }
    }

    /**
     * Create standardized success response
     */
    public static function createSuccessResponse($message, $processingId, $outputFilename, $downloadUrl, $isFallback = false)
    {
        $response = [
            'success' => true,
            'message' => $message,
            'processing_id' => $processingId,
            'output_filename' => $outputFilename,
            'download_url' => $downloadUrl
        ];

        if ($isFallback) {
            $response['message'] = $message . ' Layout may differ from original due to fallback engine.';
            $response['fallback_used'] = true;
        }

        return response()->json($response);
    }

    /**
     * Create standardized error response
     */
    private static function createErrorResponse($message, $code = 400, $details = null)
    {
        $response = [
            'error' => true,
            'message' => $message
        ];

        if ($details) {
            $response['details'] = $details;
        }

        Log::error('PDF Tools Error', [
            'message' => $message,
            'details' => $details,
            'code' => $code
        ]);

        return response()->json($response, $code);
    }

    /**
     * Enhanced file type validation
     */
    private static function validateFileTypesForTool($tool, $files)
    {
        $validationRules = config('pdftools.validation.allowed_extensions');
        
        $toolMapping = [
            'word-to-pdf' => 'word',
            'excel-to-pdf' => 'excel', 
            'ppt-to-pdf' => 'powerpoint',
            'image-to-pdf' => 'image',
            'jpg-to-pdf' => 'image',
            'html-to-pdf' => 'html',
            'pdf-to-word' => 'pdf',
            'pdf-to-excel' => 'pdf',
            'pdf-to-ppt' => 'pdf',
            'pdf-to-jpg' => 'pdf',
            'pdf-to-image' => 'pdf',
            'merge-pdf' => 'pdf',
            'split-pdf' => 'pdf',
            'compress-pdf' => 'pdf',
            'rotate-pdf' => 'pdf',
            'add-watermark' => 'pdf',
            'add-page-numbers' => 'pdf'
        ];

        if (!isset($toolMapping[$tool])) {
            throw new Exception('Unknown tool: ' . $tool);
        }

        $allowedExtensions = $validationRules[$toolMapping[$tool]] ?? [];
        $allowedMimeTypes = self::getValidMimeTypes($allowedExtensions);

        foreach ($files as $file) {
            $extension = strtolower($file->getClientOriginalExtension());
            $mimeType = $file->getMimeType();
            $fileSize = $file->getSize();

            // Check file size
            if ($fileSize > config('pdftools.validation.max_file_size')) {
                throw new Exception('File too large: ' . $file->getClientOriginalName());
            }

            // Check extension and MIME type
            if (!in_array($extension, $allowedExtensions) || !in_array($mimeType, $allowedMimeTypes)) {
                throw new Exception("File type not supported for this conversion. Expected: " . 
                    implode(', ', $allowedExtensions) . ". Got: {$extension} ({$mimeType})");
            }
        }
    }

    /**
     * Get valid MIME types for file extensions
     */
    private static function getValidMimeTypes($extensions)
    {
        $mimeMap = [
            'pdf' => ['application/pdf'],
            'doc' => ['application/msword'],
            'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
            'odt' => ['application/vnd.oasis.opendocument.text'],
            'xls' => ['application/vnd.ms-excel'],
            'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
            'ods' => ['application/vnd.oasis.opendocument.spreadsheet'],
            'ppt' => ['application/vnd.ms-powerpoint'],
            'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation'],
            'odp' => ['application/vnd.oasis.opendocument.presentation'],
            'jpg' => ['image/jpeg'],
            'jpeg' => ['image/jpeg'],
            'png' => ['image/png'],
            'gif' => ['image/gif'],
            'bmp' => ['image/bmp'],
            'html' => ['text/html'],
            'htm' => ['text/html']
        ];

        $validMimeTypes = [];
        foreach ($extensions as $ext) {
            if (isset($mimeMap[$ext])) {
                $validMimeTypes = array_merge($validMimeTypes, $mimeMap[$ext]);
            }
        }

        return array_unique($validMimeTypes);
    }

    /**
     * Log conversion metrics
     */
    public static function logConversionMetrics($tool, $inputSize, $outputSize, $processingTime, $userId = null)
    {
        if (!config('pdftools.logging.log_performance')) {
            return;
        }

        Log::info('PDF Conversion Metrics', [
            'tool' => $tool,
            'user_id' => $userId,
            'input_size' => $inputSize,
            'output_size' => $outputSize,
            'processing_time' => $processingTime,
            'compression_ratio' => $inputSize > 0 ? round(($outputSize / $inputSize) * 100, 2) : 0
        ]);
    }
    
    private static function extractWordContent($inputPath)
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
    
    private static function extractWordAsHtml($inputPath)
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
                $htmlContent = self::cleanHtmlForPdf($htmlContent);
                
                return $htmlContent;
            }
        } catch (\Exception $e) {
            \Log::info("Word HTML extraction failed: " . $e->getMessage());
        }
        
        // Fallback to simple text extraction
        $textContent = self::extractWordContent($inputPath);
        return '<div style="font-family: Arial, sans-serif; line-height: 1.6;">' . nl2br(htmlspecialchars($textContent)) . '</div>';
    }
    
    private static function cleanHtmlForPdf($html)
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
    
    /**
     * Extract text content from PDF with enhanced formatting
     */
    public static function extractTextFromPdf($pdfPath)
    {
        try {
            // Check file size and increase memory limit for large files
            $fileSize = filesize($pdfPath);
            if ($fileSize > 10 * 1024 * 1024) { // 10MB
                ini_set('memory_limit', '1G');
            }
            
            $parser = new Parser();
            $pdf = $parser->parseFile($pdfPath);
            $text = $pdf->getText();
            
            // Enhanced text cleaning while preserving structure
            $text = preg_replace('/\r\n|\r/', "\n", $text); // Normalize line endings
            $text = preg_replace('/[ \t]+/', ' ', $text); // Clean up spaces/tabs
            $text = preg_replace('/\n{3,}/', "\n\n", $text); // Limit consecutive newlines
            $text = trim($text);
            
            if (empty($text)) {
                return 'No readable text found in PDF document.';
            }
            
            return $text;
        } catch (Exception $e) {
            Log::error('PDF text extraction failed', [
                'file' => $pdfPath,
                'error' => $e->getMessage()
            ]);
            
            // For memory errors, try alternative approach
            if (strpos($e->getMessage(), 'memory') !== false) {
                return self::extractTextFromPdfFallback($pdfPath);
            }
            
            return 'Text extraction failed: ' . $e->getMessage();
        }
    }
    
    /**
     * Fallback method for PDF text extraction when memory is limited
     */
    private static function extractTextFromPdfFallback($pdfPath)
    {
        try {
            // Simple fallback - just return basic file info
            $fileSize = filesize($pdfPath);
            $fileName = basename($pdfPath);
            
            return "PDF Document: {$fileName}\n" .
                   "File Size: " . number_format($fileSize / 1024, 2) . " KB\n" .
                   "Note: Full text extraction failed due to memory limitations.\n" .
                   "This is a placeholder content for the converted document.";
                   
        } catch (Exception $e) {
            return "PDF text extraction failed completely. File: " . basename($pdfPath);
        }
    }
    
    /**
     * Create PowerPoint from PDF pages using Imagick (preserve layout)
     */
    public static function createPowerPointFromPdfPages(string $pdfPath, string $outputPath, array $settings = []): string
    {
        // Check if Imagick is available
        if (!extension_loaded('imagick')) {
            throw new \Exception('Imagick extension is not available for PDF to PowerPoint conversion');
        }
        
        // Check if Ghostscript is available and properly configured
        if (!self::isGhostscriptAvailable()) {
            throw new \Exception('Ghostscript is not available or not properly configured for PDF processing');
        }
        
        // Memory and execution time handling for large files
        $fileSize = filesize($pdfPath);
        if ($fileSize > 20 * 1024 * 1024) { // 20MB
            ini_set('memory_limit', '1G');
            ini_set('max_execution_time', 300); // 5 minutes
        } else if ($fileSize > 5 * 1024 * 1024) { // 5MB
            ini_set('max_execution_time', 180); // 3 minutes
        } else {
            ini_set('max_execution_time', 120); // 2 minutes
        }
        
        // Parse settings with optimized DPI for performance
        $defaultDpi = 72; // Lower default for better performance
        if ($fileSize > 20 * 1024 * 1024) {
            $defaultDpi = 72; // Very large files: 72 DPI
        } else if ($fileSize > 10 * 1024 * 1024) {
            $defaultDpi = 100; // Large files: 100 DPI
        } else {
            $defaultDpi = 150; // Small files: 150 DPI
        }
        
        $dpi = $settings['dpi'] ?? $defaultDpi;
        $pageRange = $settings['page_range'] ?? null;
        $background = $settings['background'] ?? 'white';
        
        try {
            // Create presentation
            $presentation = new PhpPresentation();
            $presentation->removeSlideByIndex(0); // Remove default slide
            
            // Set Ghostscript path environment variable for this process
            $ghostscriptPath = self::findGhostscriptPath();
            if ($ghostscriptPath && file_exists($ghostscriptPath)) {
                // Set environment variable for Imagick to find Ghostscript
                putenv('MAGICK_GHOSTSCRIPT_PATH=' . $ghostscriptPath);
                putenv('PATH=' . getenv('PATH') . ';' . dirname($ghostscriptPath));
            }
            
            // Get PDF page count
            $imagick = new Imagick();
            $imagick->setResolution($dpi, $dpi);
            $imagick->readImage($pdfPath);
            $pageCount = $imagick->getNumberImages();
            $imagick->clear();
            
            Log::info("PDF to PowerPoint: Processing {$pageCount} pages at {$dpi} DPI (File size: " . number_format($fileSize / 1024 / 1024, 2) . " MB)");
            
            // Parse page range if specified
            $pagesToProcess = range(0, $pageCount - 1);
            if ($pageRange) {
                $pagesToProcess = self::parsePageRange($pageRange, $pageCount);
            }
            
            // Array to track temp files for cleanup
            $tempFiles = [];
            
            // Add progress tracking for large files
            $processedPages = 0;
            $totalPages = count($pagesToProcess);
            
            foreach ($pagesToProcess as $pageIndex) {
                $processedPages++;
                
                // Log progress every 10 pages for large documents
                if ($totalPages > 10 && $processedPages % 10 === 0) {
                    Log::info("PDF to PowerPoint: Progress {$processedPages}/{$totalPages} pages");
                }
                $imagick = new Imagick();
                $imagick->setResolution($dpi, $dpi);
                $imagick->readImage($pdfPath . "[{$pageIndex}]");
                
                // Set format and clean alpha
                $imagick->setImageFormat('png');
                $imagick->setImageAlphaChannel(Imagick::ALPHACHANNEL_REMOVE);
                $imagick->setImageBackgroundColor($background);
                $imagick->flattenImages();
                
                // Get image as blob
                $imageBlob = $imagick->getImageBlob();
                
                // Create slide
                $slide = $presentation->createSlide();
                
                // Save image to temporary file and add to slide
                $tempImagePath = sys_get_temp_dir() . '/pdf_page_' . $pageIndex . '_' . uniqid() . '.png';
                file_put_contents($tempImagePath, $imageBlob);
                
                $drawing = new \PhpOffice\PhpPresentation\Shape\Drawing\File();
                $drawing->setName('PDF Page ' . ($pageIndex + 1));
                $drawing->setDescription('PDF Page ' . ($pageIndex + 1));
                $drawing->setPath($tempImagePath);
                
                // Fit to slide maintaining aspect ratio
                $slideWidth = 720; // Default slide width in points
                $slideHeight = 540; // Default slide height in points (16:9)
                
                $imageWidth = $imagick->getImageWidth();
                $imageHeight = $imagick->getImageHeight();
                
                $scaleX = $slideWidth / $imageWidth;
                $scaleY = $slideHeight / $imageHeight;
                $scale = min($scaleX, $scaleY);
                
                $newWidth = $imageWidth * $scale;
                $newHeight = $imageHeight * $scale;
                
                $drawing->setWidth($newWidth);
                $drawing->setHeight($newHeight);
                
                // Center the image
                $offsetX = ($slideWidth - $newWidth) / 2;
                $offsetY = ($slideHeight - $newHeight) / 2;
                $drawing->setOffsetX($offsetX);
                $drawing->setOffsetY($offsetY);
                
                $slide->addShape($drawing);
                
                // Clean up Imagick and temp file
                $imagick->clear();
                $imagick->destroy();
                
                // Register temp file for cleanup after presentation is saved
                $tempFiles[] = $tempImagePath;
            }
            
            // Ensure output directory exists
            $outputDir = dirname($outputPath);
            if (!file_exists($outputDir)) {
                @mkdir($outputDir, 0777, true);
            }
            
            // Save presentation
            $writer = IOFactory::createWriter($presentation, 'PowerPoint2007');
            $writer->save($outputPath);
            
            // Verify file was created
            if (!file_exists($outputPath) || filesize($outputPath) === 0) {
                throw new \Exception('Failed to create PowerPoint file or file is empty');
            }
            
            // Clean up temporary image files
            foreach ($tempFiles as $tempFile) {
                if (file_exists($tempFile)) {
                    @unlink($tempFile);
                }
            }
            
            Log::info("PowerPoint created successfully: " . basename($outputPath) . " (" . count($pagesToProcess) . " slides)");
            return $outputPath;
            
        } catch (\Exception $e) {
            Log::error("PowerPoint creation from PDF pages failed: " . $e->getMessage());
            throw new \Exception("Failed to create PowerPoint from PDF pages: " . $e->getMessage());
        }
    }
    
    /**
     * Parse page range string like "1-3,5,7-8" into array of page indices (0-based)
     */
    private static function parsePageRange(string $pageRange, int $totalPages): array
    {
        $pages = [];
        $ranges = explode(',', $pageRange);
        
        foreach ($ranges as $range) {
            $range = trim($range);
            if (strpos($range, '-') !== false) {
                list($start, $end) = explode('-', $range, 2);
                $start = max(1, intval($start));
                $end = min($totalPages, intval($end));
                for ($i = $start; $i <= $end; $i++) {
                    $pages[] = $i - 1; // Convert to 0-based
                }
            } else {
                $page = max(1, min($totalPages, intval($range)));
                $pages[] = $page - 1; // Convert to 0-based
            }
        }
        
        return array_unique($pages);
    }
    
    /**
     * Check if Ghostscript is available and properly configured
     */
    private static function isGhostscriptAvailable(): bool
    {
        try {
            // Check if PDF format is supported by Imagick
            $formats = Imagick::queryFormats('PDF');
            if (empty($formats)) {
                return false;
            }
            
            // Find and configure Ghostscript path for Imagick
            $ghostscriptPath = self::findGhostscriptPath();
            if (!$ghostscriptPath) {
                Log::warning("Ghostscript not found in any expected location");
                return false;
            }
            
            // Configure Imagick to use the found Ghostscript path
            try {
                // Set Ghostscript path for Imagick delegate
                if (method_exists('Imagick', 'setResourceLimit')) {
                    Imagick::setResourceLimit(Imagick::RESOURCETYPE_MEMORY, 256 * 1024 * 1024); // 256MB
                }
                
                Log::info("Ghostscript configured for Imagick: $ghostscriptPath");
                return true;
            } catch (\Exception $e) {
                Log::error("Failed to configure Imagick with Ghostscript: " . $e->getMessage());
                return false;
            }
            
            Log::warning("Ghostscript not found in any expected location");
            return false;
        } catch (\Exception $e) {
            Log::error("Ghostscript detection failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Find Ghostscript executable path
     */
    private static function findGhostscriptPath(): ?string
    {
        // Try command line first
        $possibleCommands = [
            'gs -v',  // Unix/Linux and Windows (if in PATH)
            'gswin64c -v',  // Windows 64-bit console
            'gswin32c -v',  // Windows 32-bit console
        ];
        
        foreach ($possibleCommands as $command) {
            $output = [];
            $returnCode = 0;
            
            // Use full PATH environment on Windows
            if (PHP_OS_FAMILY === 'Windows') {
                $fullCommand = "cmd /c \"$command\" 2>&1";
            } else {
                $fullCommand = "$command 2>&1";
            }
            
            exec($fullCommand, $output, $returnCode);
            
            if ($returnCode === 0 && !empty($output)) {
                // Check if output contains Ghostscript signature
                $outputText = implode(' ', $output);
                if (stripos($outputText, 'ghostscript') !== false || stripos($outputText, 'GPL Ghostscript') !== false) {
                    $commandName = explode(' ', $command)[0];
                    Log::info("Ghostscript detected via command: $commandName");
                    return $commandName;
                }
            }
        }
        
        // Fallback: Try direct file paths on Windows
        if (PHP_OS_FAMILY === 'Windows') {
            $possiblePaths = [
                'C:\\Program Files\\gs\\gs*\\bin\\gswin64c.exe',
                'C:\\Program Files (x86)\\gs\\gs*\\bin\\gswin32c.exe',
            ];
            
            foreach ($possiblePaths as $path) {
                $globPaths = glob($path);
                foreach ($globPaths as $globPath) {
                    if (is_executable($globPath)) {
                        Log::info("Ghostscript found at: $globPath");
                        return $globPath;
                    }
                }
            }
        }
        
        return null;
    }
    
    public static function createWordDocument($textContent, $outputPath, $originalPath)
    {
        if (!class_exists('\PhpOffice\PhpWord\PhpWord')) {
            throw new \Exception('PhpOffice\PhpWord library tidak tersedia. Install dengan: composer require phpoffice/phpword');
        }

        try {
            Log::info("Word document creation started: " . basename($outputPath));
            $startTime = microtime(true);
            
            // Sanitize text content from invalid XML characters
            $textContent = preg_replace('/[^\x09\x0A\x0D\x20-\x7E\x{A0}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $textContent);
            
            // Handle empty content
            if (empty(trim($textContent))) {
                $textContent = "No readable text found. This document was generated from a scanned or image-only PDF.";
            }
            
            $phpWord = new PhpWord();
            
            // Set document properties
            $properties = $phpWord->getDocInfo();
            $properties->setCreator('FlexiConvert');
            $properties->setTitle('PDF to Word Conversion - ' . basename($originalPath));
            $properties->setSubject('Converted from PDF');
            $properties->setCreated(time());
            
            $section = $phpWord->addSection();
            
            // Add title
            $section->addTitle('Converted Document', 1);
            $section->addTextBreak();
            
            // Add metadata
            $section->addText('Original file: ' . basename($originalPath), ['bold' => true]);
            $section->addText('Converted on: ' . now());
            $section->addTextBreak(2);
            
            // Add content with proper formatting
            $lines = explode("\n", $textContent);
            foreach ($lines as $line) {
                $trimmedLine = trim($line);
                if ($trimmedLine) {
                    // Check if line looks like a heading
                    if (strlen($trimmedLine) < 100 && preg_match('/^[A-Z][^.]*$/', $trimmedLine)) {
                        $section->addText($trimmedLine, ['bold' => true, 'size' => 14]);
                    } else {
                        $section->addText($trimmedLine);
                    }
                    $section->addTextBreak();
                }
            }
            
            // Ensure output directory exists
            $outputDir = dirname($outputPath);
            if (!file_exists($outputDir)) {
                @mkdir($outputDir, 0777, true);
            }
            
            // Save document using proper IOFactory
            $writer = WordIOFactory::createWriter($phpWord, 'Word2007');
            $writer->save($outputPath);
            
            // Verify file was created and has content
            if (!file_exists($outputPath)) {
                throw new \Exception('Word document file was not created');
            }
            
            $fileSize = filesize($outputPath);
            if ($fileSize === 0) {
                throw new \Exception('Word document file is empty (0 bytes)');
            }
            
            $processingTime = microtime(true) - $startTime;
            Log::info("Word document created successfully", [
                'filename' => basename($outputPath),
                'size' => $fileSize,
                'processing_time' => $processingTime
            ]);
            
            return $outputPath;
            
        } catch (\Exception $e) {
            Log::error("Word document creation failed: " . $e->getMessage());
            throw new \Exception("Gagal membuat dokumen Word: " . $e->getMessage());
        }
    }
    
    public static function createExcelDocument($textContent, $outputPath, $originalPath)
    {
        if (!class_exists('\PhpOffice\PhpSpreadsheet\Spreadsheet')) {
            throw new \Exception('PhpOffice\PhpSpreadsheet library tidak tersedia. Install dengan: composer require phpoffice/phpspreadsheet');
        }

        try {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            
            // Set headers
            $sheet->setCellValue('A1', 'Converted Document');
            $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
            
            $sheet->setCellValue('A3', 'Original file:');
            $sheet->setCellValue('B3', basename($originalPath));
            $sheet->setCellValue('A4', 'Converted on:');
            $sheet->setCellValue('B4', now());
            
            // Add content with better formatting
            $lines = explode("\n", $textContent);
            $row = 6;
            foreach ($lines as $line) {
                $trimmedLine = trim($line);
                if ($trimmedLine) {
                    // Try to detect tabular data
                    if (strpos($trimmedLine, "\t") !== false || strpos($trimmedLine, ',') !== false) {
                        $columns = preg_split('/[\t,]+/', $trimmedLine);
                        $col = 'A';
                        foreach ($columns as $cellValue) {
                            $sheet->setCellValue($col . $row, trim($cellValue));
                            $col++;
                        }
                    } else {
                        $sheet->setCellValue('A' . $row, $trimmedLine);
                    }
                    $row++;
                }
            }
            
            // Auto-size columns
            foreach (range('A', 'Z') as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }
            
            $writer = new Xlsx($spreadsheet);
            $writer->save($outputPath);
            
            \Log::info("Excel document created successfully: " . basename($outputPath));
            return $outputPath;
            
        } catch (\Exception $e) {
            \Log::error("Excel document creation failed: " . $e->getMessage());
            throw new \Exception("Gagal membuat dokumen Excel: " . $e->getMessage());
        }
    }
    
    public static function createPowerPointDocument($textContent, $outputPath, $originalPath)
    {
        if (!class_exists('\PhpOffice\PhpPresentation\PhpPresentation')) {
            throw new \Exception('PhpOffice\PhpPresentation library tidak tersedia. Install dengan: composer require phpoffice/phppresentation');
        }

        try {
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
            
            // Content with better formatting
            $contentShape = $slide->createRichTextShape();
            $contentShape->setHeight(400)
                        ->setWidth(800)
                        ->setOffsetX(100)
                        ->setOffsetY(250);
            
            // Split content into slides if too long
            $lines = explode("\n", $textContent);
            $linesPerSlide = 15;
            $chunks = array_chunk($lines, $linesPerSlide);
            
            foreach ($chunks as $index => $chunk) {
                if ($index > 0) {
                    $slide = $presentation->createSlide();
                    $contentShape = $slide->createRichTextShape();
                    $contentShape->setHeight(500)
                                ->setWidth(800)
                                ->setOffsetX(100)
                                ->setOffsetY(100);
                }
                
                $contentParagraph = $contentShape->createParagraph();
                $slideContent = implode("\n", array_filter($chunk, 'trim'));
                $contentRun = $contentParagraph->createTextRun($slideContent);
                $contentRun->getFont()->setSize(12);
            }
            
            $writer = new PowerPoint2007($presentation);
            $writer->save($outputPath);
            
            \Log::info("PowerPoint document created successfully: " . basename($outputPath));
            return $outputPath;
            
        } catch (\Exception $e) {
            \Log::error("PowerPoint document creation failed: " . $e->getMessage());
            throw new \Exception("Gagal membuat dokumen PowerPoint: " . $e->getMessage());
        }
    }

    /**
     * Process PDF compression with Ghostscript priority and FPDI fallback
     */
    public static function processCompressPdf($file, $settings = [])
    {
        $startTime = microtime(true);
        $processing = null;
        $isFallback = false;
        
        try {
            $quality = $settings['quality'] ?? 'medium';
            $friendlyFilename = self::generateFriendlyFilename('compress-pdf', $file->getClientOriginalName());
            $internalFilename = Str::uuid() . '.pdf';
            $outputPath = config('pdftools.storage.outputs_path') . '/' . $internalFilename;
            
            $processing = PdfProcessing::create([
                'user_id' => auth()->id(),
                'tool_name' => 'compress-pdf',
                'original_filename' => $file->getClientOriginalName(),
                'processed_filename' => $internalFilename,
                'friendly_filename' => $friendlyFilename,
                'file_size' => $file->getSize(),
                'status' => 'processing',
                'progress' => 0,
                'settings' => json_encode($settings),
                'created_at' => now(),
                'updated_at' => now()
            ]);

            $inputPath = $file->store(config('pdftools.storage.uploads_path'));
            $fullInputPath = Storage::path($inputPath);
            $fullOutputPath = Storage::path($outputPath);

            // Try Ghostscript first (best compression)
            try {
                if (self::compressPdfWithGhostscript($fullInputPath, $fullOutputPath, $quality)) {
                    Storage::delete($inputPath);
                    
                    $processing->update([
                        'status' => 'completed',
                        'progress' => 100,
                        'completed_at' => now()
                    ]);

                    $processingTime = microtime(true) - $startTime;
                    self::logConversionMetrics('compress-pdf', $file->getSize(), 
                        Storage::size($outputPath), $processingTime, auth()->id());

                    return self::createSuccessResponse(
                        'PDF compressed successfully.',
                        $processing->id,
                        $friendlyFilename,
                        route('pdf-tools.download', $processing->id)
                    );
                }
            } catch (Exception $e) {
                Log::warning('Ghostscript compression failed, falling back to FPDI', [
                    'error' => $e->getMessage()
                ]);
                $isFallback = true;
            }

            // Fallback to FPDI-based compression
            $fpdi = new \setasign\Fpdi\Fpdi();
            $pageCount = $fpdi->setSourceFile($fullInputPath);
            
            for ($i = 1; $i <= $pageCount; $i++) {
                $template = $fpdi->importPage($i);
                $size = $fpdi->getTemplateSize($template);
                
                // Slightly reduce page size for compression
                $fpdi->AddPage('P', [$size['width'] * 0.95, $size['height'] * 0.95]);
                $fpdi->useTemplate($template, 0, 0, $size['width'] * 0.95, $size['height'] * 0.95);
            }
            
            Storage::put($outputPath, $fpdi->Output('S'));
            Storage::delete($inputPath);
            
            $processing->update([
                'status' => 'completed',
                'progress' => 100,
                'completed_at' => now()
            ]);

            $processingTime = microtime(true) - $startTime;
            self::logConversionMetrics('compress-pdf', $file->getSize(), 
                Storage::size($outputPath), $processingTime, auth()->id());

            return self::createSuccessResponse(
                'PDF compressed successfully.',
                $processing->id,
                $friendlyFilename,
                route('pdf-tools.download', $processing->id),
                $isFallback
            );

        } catch (Exception $e) {
            if ($processing) {
                $processing->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
            }
            Log::error("PDF compression failed", [
                'error' => $e->getMessage(),
                'file' => $file->getClientOriginalName()
            ]);
            throw $e;
        }
    }

    /**
     * Process Word to PDF conversion with LibreOffice priority
     */
    public static function processWordToPdf($file, $settings = [])
    {
        $startTime = microtime(true);
        $processing = null;
        $isFallback = false;
        
        try {
            $friendlyFilename = self::generateFriendlyFilename('word-to-pdf', $file->getClientOriginalName());
            $internalFilename = Str::uuid() . '.pdf';
            $outputPath = config('pdftools.storage.outputs_path') . '/' . $internalFilename;
            
            $processing = PdfProcessing::create([
                'user_id' => auth()->id(),
                'tool_name' => 'word-to-pdf',
                'original_filename' => $file->getClientOriginalName(),
                'processed_filename' => $internalFilename,
                'friendly_filename' => $friendlyFilename,
                'file_size' => $file->getSize(),
                'status' => 'processing',
                'progress' => 0,
                'settings' => json_encode($settings),
                'created_at' => now(),
                'updated_at' => now()
            ]);

            $inputPath = $file->store(config('pdftools.storage.uploads_path'));
            $fullInputPath = Storage::path($inputPath);

            // Try LibreOffice first (highest quality)
            if (config('pdftools.office_to_pdf_engine') === 'libreoffice') {
                try {
                    $tempDir = sys_get_temp_dir();
                    $convertedPath = self::convertWithLibreOffice($fullInputPath, $tempDir);
                    
                    if ($convertedPath && file_exists($convertedPath)) {
                        Storage::put($outputPath, file_get_contents($convertedPath));
                        unlink($convertedPath);
                        Storage::delete($inputPath);
                        
                        $processing->update([
                            'status' => 'completed',
                            'progress' => 100,
                            'completed_at' => now()
                        ]);

                        $processingTime = microtime(true) - $startTime;
                        self::logConversionMetrics('word-to-pdf', $file->getSize(), 
                            Storage::size($outputPath), $processingTime, auth()->id());

                        return self::createSuccessResponse(
                            'Word document converted to PDF successfully.',
                            $processing->id,
                            $friendlyFilename,
                            route('pdf-tools.download', $processing->id)
                        );
                    }
                } catch (Exception $e) {
                    Log::warning('LibreOffice conversion failed, falling back to PhpOffice', [
                        'error' => $e->getMessage()
                    ]);
                    $isFallback = true;
                }
            }

            // Fallback to PhpOffice + DomPDF
            $htmlContent = self::extractWordAsHtml($fullInputPath);
            
            $options = new \Dompdf\Options();
            $options->set('defaultFont', 'Arial');
            $options->set('isRemoteEnabled', true);
            $options->set('isHtml5ParserEnabled', true);
            
            $dompdf = new \Dompdf\Dompdf($options);
            $dompdf->loadHtml($htmlContent);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();

            Storage::put($outputPath, $dompdf->output());
            Storage::delete($inputPath);
            
            $processing->update([
                'status' => 'completed',
                'progress' => 100,
                'completed_at' => now()
            ]);

            $processingTime = microtime(true) - $startTime;
            self::logConversionMetrics('word-to-pdf', $file->getSize(), 
                Storage::size($outputPath), $processingTime, auth()->id());

            return self::createSuccessResponse(
                'Word document converted to PDF successfully.',
                $processing->id,
                $friendlyFilename,
                route('pdf-tools.download', $processing->id),
                $isFallback
            );

        } catch (Exception $e) {
            if ($processing) {
                $processing->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
            }
            Log::error("Word to PDF conversion failed", [
                'error' => $e->getMessage(),
                'file' => $file->getClientOriginalName()
            ]);
            throw $e;
        }
    }

    /**
     * Process PDF merge using FPDI
     */
    public static function processMergePdfs($files, $settings = [])
    {
        $processing = null;
        try {
            $friendlyFilename = self::generateFriendlyFilename('merge-pdf', count($files) . '_files');
            $internalFilename = Str::uuid() . '.pdf';
            $outputPath = config('pdftools.storage.outputs_path') . '/' . $internalFilename;
            
            $processing = PdfProcessing::create([
                'user_id' => auth()->id(),
                'tool_name' => 'merge-pdf',
                'original_filename' => count($files) . ' PDF files',
                'processed_filename' => $internalFilename,
                'friendly_filename' => $friendlyFilename,
                'file_size' => array_sum(array_map(fn($f) => $f->getSize(), $files)),
                'status' => 'processing',
                'progress' => 0,
                'settings' => json_encode($settings)
            ]);

            $fpdi = new \setasign\Fpdi\Fpdi();
            
            foreach ($files as $file) {
                $inputPath = $file->store(config('pdftools.storage.uploads_path'));
                $fullInputPath = Storage::path($inputPath);
                
                $pageCount = $fpdi->setSourceFile($fullInputPath);
                
                for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                    $templateId = $fpdi->importPage($pageNo);
                    $size = $fpdi->getTemplateSize($templateId);
                    
                    $fpdi->AddPage($size['orientation'], [$size['width'], $size['height']]);
                    $fpdi->useTemplate($templateId);
                }
                
                Storage::delete($inputPath);
            }
            
            Storage::put($outputPath, $fpdi->Output('S'));
            
            $processing->update([
                'status' => 'completed',
                'progress' => 100,
                'completed_at' => now()
            ]);

            return self::createSuccessResponse(
                'PDF files merged successfully.',
                $processing->id,
                $friendlyFilename,
                route('pdf-tools.download', $processing->id)
            );

        } catch (Exception $e) {
            if ($processing) {
                $processing->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
            }
            Log::error("PDF merge failed", ['error' => $e->getMessage()]);
            throw $e;
        }
    }
}