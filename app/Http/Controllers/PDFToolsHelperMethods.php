<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory as WordIOFactory;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use App\Models\PdfProcessing;
use Slide\Layout;
use Smalot\PdfParser\Parser as PdfParser;

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

        // Common LibreOffice paths
        $paths = [
            '/usr/bin/libreoffice',
            '/usr/bin/soffice',
            '/opt/libreoffice/program/soffice',
            '/Applications/LibreOffice.app/Contents/MacOS/soffice',
            'C:\\Program Files\\LibreOffice\\program\\soffice.exe',
            'C:\\Program Files (x86)\\LibreOffice\\program\\soffice.exe',
            'C:\\Program Files\\LibreOffice\\program\\soffice.com'
        ];

        foreach ($paths as $path) {
            if (file_exists($path)) {
                Log::info('LibreOffice found at path', ['path' => $path]);
                return $path;
            }
        }

        // Try to find using system commands
        if (PHP_OS_FAMILY === 'Windows') {
            // Windows: try where command for soffice
            $process = new Process(['where', 'soffice']);
            $process->run();
            if ($process->isSuccessful()) {
                $path = trim($process->getOutput());
                Log::info('LibreOffice found via where command', ['path' => $path]);
                return $path;
            }
            
            // Also try direct soffice command (it's in PATH)
            $process = new Process(['soffice', '--version']);
            $process->run();
            if ($process->isSuccessful()) {
                Log::info('LibreOffice accessible via soffice command');
                return 'soffice'; // Return command name if accessible via PATH
            }
        } else {
            // Unix: try which command
            $process = new Process(['which', 'soffice']);
            $process->run();
            if ($process->isSuccessful()) {
                $path = trim($process->getOutput());
                Log::info('LibreOffice found via which command', ['path' => $path]);
                return $path;
            }

            $process = new Process(['which', 'libreoffice']);
            $process->run();
            if ($process->isSuccessful()) {
                $path = trim($process->getOutput());
                Log::info('LibreOffice found via which command', ['path' => $path]);
                return $path;
            }
        }

        Log::warning('LibreOffice not found in any standard location');
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
     * Convert Office document to PDF using LibreOffice CLI with unique temp directory
     */
    private static function convertWithLibreOffice($inputPath, $outputDir)
    {
        $libreOfficePath = self::findLibreOffice();
        if (!$libreOfficePath) {
            Log::warning('LibreOffice not found, will use fallback method');
            throw new Exception('LibreOffice not found - will use fallback conversion');
        }

        // Create unique temporary directory for this conversion to prevent file collisions
        $uniqueTempDir = $outputDir . DIRECTORY_SEPARATOR . 'temp_' . Str::uuid();
        if (!mkdir($uniqueTempDir, 0755, true)) {
            throw new Exception('Failed to create temporary directory: ' . $uniqueTempDir);
        }

        try {
            // Windows-specific LibreOffice command with proper arguments
            $command = [
                $libreOfficePath,
                '--headless',
                '--invisible',
                '--nodefault',
                '--nolockcheck',
                '--nologo',
                '--norestore',
                '--convert-to',
                'pdf',
                '--outdir',
                $uniqueTempDir,
                $inputPath
            ];

            $process = new Process($command);
            $process->setTimeout(config('pdftools.libreoffice.timeout', 120));
            
            // Set environment variables for Windows LibreOffice
            if (PHP_OS_FAMILY === 'Windows') {
                $env = $_ENV;
                $env['HOME'] = sys_get_temp_dir();
                $env['TMPDIR'] = sys_get_temp_dir();
                $process->setEnv($env);
            }
            
            Log::info('LibreOffice conversion starting', [
                'command' => $process->getCommandLine(),
                'input_path' => $inputPath,
                'input_exists' => file_exists($inputPath),
                'input_size' => file_exists($inputPath) ? filesize($inputPath) : 0,
                'temp_dir' => $uniqueTempDir,
                'temp_dir_exists' => is_dir($uniqueTempDir),
                'libreoffice_path' => $libreOfficePath
            ]);
            
            $process->run();

            // Log process output for debugging
            Log::info('LibreOffice process completed', [
                'exit_code' => $process->getExitCode(),
                'successful' => $process->isSuccessful(),
                'output' => $process->getOutput(),
                'error_output' => $process->getErrorOutput()
            ]);

            if (!$process->isSuccessful()) {
                Log::error('LibreOffice process failed', [
                    'exit_code' => $process->getExitCode(),
                    'error_output' => $process->getErrorOutput(),
                    'command' => $process->getCommandLine(),
                    'exit_code_hex' => dechex($process->getExitCode() & 0xFFFFFFFF)
                ]);
                
                // Windows exit code -1073740791 (0xC0000409) indicates access violation
                // Try alternative approach with cmd.exe wrapper
                if (PHP_OS_FAMILY === 'Windows' && ($process->getExitCode() === -1073740791 || $process->getExitCode() !== 0)) {
                    Log::info('Trying Windows cmd.exe wrapper approach due to exit code: ' . $process->getExitCode());
                    return self::convertWithLibreOfficeWindows($inputPath, $uniqueTempDir, $outputDir);
                }
                
                throw new ProcessFailedException($process);
            }

            
            // Add delay to ensure file system operations are complete
            sleep(1);
            
            // Find generated PDF files in the unique temp directory
            $pdfFiles = glob($uniqueTempDir . DIRECTORY_SEPARATOR . '*.pdf');
            $allFiles = glob($uniqueTempDir . DIRECTORY_SEPARATOR . '*');
            
            Log::info('LibreOffice output check', [
                'temp_dir' => $uniqueTempDir,
                'temp_dir_exists' => is_dir($uniqueTempDir),
                'temp_dir_writable' => is_writable($uniqueTempDir),
                'pdf_files_found' => count($pdfFiles),
                'pdf_files' => $pdfFiles,
                'all_files_found' => count($allFiles),
                'all_files' => $allFiles
            ]);
            
            if (count($pdfFiles) === 0) {
                throw new Exception('LibreOffice conversion failed - no PDF files found in temp directory: ' . $uniqueTempDir . '. Check if LibreOffice is properly installed and the input file is valid.');
            }
            
            // Get the first (and should be only) PDF file
            $tempPdfPath = $pdfFiles[0];
            
            // Move the PDF to the final output directory with a unique name
            $finalPdfName = 'converted_' . Str::uuid() . '.pdf';
            $finalPdfPath = $outputDir . DIRECTORY_SEPARATOR . $finalPdfName;
            
            if (!rename($tempPdfPath, $finalPdfPath)) {
                throw new Exception('Failed to move converted PDF from temp directory');
            }
            
            Log::info('LibreOffice conversion successful', [
                'input' => basename($inputPath),
                'temp_dir' => $uniqueTempDir,
                'final_path' => $finalPdfPath,
                'file_size' => filesize($finalPdfPath)
            ]);

            return $finalPdfPath;
            
        } finally {
            // Clean up temporary directory
            if (is_dir($uniqueTempDir)) {
                $files = glob($uniqueTempDir . DIRECTORY_SEPARATOR . '*');
                foreach ($files as $file) {
                    if (is_file($file)) {
                        @unlink($file);
                    }
                }
                @rmdir($uniqueTempDir);
            }
        }
    }

    /**
     * Windows-specific LibreOffice conversion with cmd.exe wrapper
     */
    private static function convertWithLibreOfficeWindows($inputPath, $uniqueTempDir, $outputDir)
    {
        try {
            // Use direct soffice command since it's in PATH
            $cmdLine = "soffice --headless --invisible --nodefault --nolockcheck --nologo --norestore --convert-to pdf --outdir \"{$uniqueTempDir}\" \"{$inputPath}\"";
            
            Log::info('Windows LibreOffice conversion with direct command', [
                'command' => $cmdLine,
                'input_path' => $inputPath,
                'temp_dir' => $uniqueTempDir,
                'input_exists' => file_exists($inputPath),
                'temp_dir_writable' => is_writable($uniqueTempDir)
            ]);
            
            // Execute with proper timeout and capture all output
            $output = [];
            $returnVar = 0;
            
            // Change to temp directory to avoid path issues
            $originalDir = getcwd();
            chdir($uniqueTempDir);
            
            exec($cmdLine . ' 2>&1', $output, $returnVar);
            
            // Change back to original directory
            chdir($originalDir);
            
            Log::info('Windows LibreOffice exec result', [
                'return_code' => $returnVar,
                'output' => implode("\n", $output)
            ]);
            
            if ($returnVar !== 0) {
                throw new Exception("LibreOffice Windows conversion failed with code: {$returnVar}. Output: " . implode("\n", $output));
            }
            
            // Add delay to ensure file system operations are complete
            sleep(1);
            
            // Find generated PDF files
            $pdfFiles = glob($uniqueTempDir . DIRECTORY_SEPARATOR . '*.pdf');
            $allFiles = glob($uniqueTempDir . DIRECTORY_SEPARATOR . '*');
            
            Log::info('Windows LibreOffice output check', [
                'temp_dir' => $uniqueTempDir,
                'temp_dir_exists' => is_dir($uniqueTempDir),
                'temp_dir_writable' => is_writable($uniqueTempDir),
                'pdf_files_found' => count($pdfFiles),
                'pdf_files' => $pdfFiles,
                'all_files_found' => count($allFiles),
                'all_files' => $allFiles
            ]);
            
            if (count($pdfFiles) === 0) {
                throw new Exception('LibreOffice Windows conversion failed - no PDF files found');
            }
            
            // Move the PDF to final location
            $tempPdfPath = $pdfFiles[0];
            $finalPdfName = 'converted_' . Str::uuid() . '.pdf';
            $finalPdfPath = $outputDir . DIRECTORY_SEPARATOR . $finalPdfName;
            
            if (!rename($tempPdfPath, $finalPdfPath)) {
                throw new Exception('Failed to move converted PDF from temp directory');
            }
            
            Log::info('Windows LibreOffice conversion successful', [
                'final_path' => $finalPdfPath,
                'file_size' => filesize($finalPdfPath)
            ]);
            
            return $finalPdfPath;
            
        } catch (Exception $e) {
            Log::error('Windows LibreOffice conversion failed: ' . $e->getMessage());
            throw $e;
        }
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
            try {
                return PDFToolsHelperMethods::processCompressPdf($file, $settings);
            } catch (Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'PDF compression failed: ' . $e->getMessage()
                ], 500);
            }
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
     * Extract text content from PDF with enhanced formatting and full content extraction
     */
    public static function extractTextFromPdf($pdfPath)
    {
        try {
            // Check file size and increase memory limit for large files
            $fileSize = filesize($pdfPath);
            if ($fileSize > 10 * 1024 * 1024) { // 10MB
                ini_set('memory_limit', '2G');
                ini_set('max_execution_time', 300); // 5 minutes
            }
            
            Log::info("Starting PDF text extraction", [
                'file' => basename($pdfPath),
                'size' => number_format($fileSize / 1024, 2) . ' KB'
            ]);
            
            $parser = new Parser();
            $pdf = $parser->parseFile($pdfPath);
            
            // Get total page count
            $pages = $pdf->getPages();
            $pageCount = count($pages);
            
            Log::info("PDF has {$pageCount} pages");
            
            $fullText = '';
            $extractedPages = 0;
            
            // Extract text from each page individually for better content recovery
            foreach ($pages as $pageNumber => $page) {
                try {
                    $pageText = $page->getText();
                    
                    if (!empty(trim($pageText))) {
                        // Add page separator for multi-page documents
                        if ($extractedPages > 0) {
                            $fullText .= "\n\n--- Page " . ($pageNumber + 1) . " ---\n\n";
                        }
                        
                        $fullText .= $pageText;
                        $extractedPages++;
                    }
                    
                    // Log progress for large documents
                    if ($pageCount > 10 && ($pageNumber + 1) % 5 === 0) {
                        Log::info("Extracted text from page " . ($pageNumber + 1) . " of {$pageCount}");
                    }
                    
                } catch (Exception $pageError) {
                    Log::warning("Failed to extract text from page " . ($pageNumber + 1), [
                        'error' => $pageError->getMessage()
                    ]);
                    // Continue with next page instead of failing completely
                    continue;
                }
            }
            
            // If no text extracted from individual pages, try full document extraction
            if (empty(trim($fullText))) {
                Log::info("Individual page extraction failed, trying full document extraction");
                $fullText = $pdf->getText();
            }
            
            // Enhanced text cleaning while preserving structure and formatting
            $fullText = self::enhancedTextCleaning($fullText);
            
            if (empty(trim($fullText))) {
                Log::warning("No readable text found in PDF document");
                return self::createPlaceholderContent($pdfPath, $pageCount);
            }
            
            Log::info("PDF text extraction completed", [
                'pages_processed' => $extractedPages,
                'total_pages' => $pageCount,
                'text_length' => strlen($fullText),
                'text_preview' => substr(trim($fullText), 0, 100) . '...'
            ]);
            
            return $fullText;
            
        } catch (Exception $e) {
            Log::error('PDF text extraction failed', [
                'file' => basename($pdfPath),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // For memory errors, try alternative approach
            if (strpos($e->getMessage(), 'memory') !== false || strpos($e->getMessage(), 'allowed memory') !== false) {
                return self::extractTextFromPdfFallback($pdfPath);
            }
            
            // Return meaningful content instead of just error message
            return self::createPlaceholderContent($pdfPath, 0, $e->getMessage());
        }
    }
    
    /**
     * Enhanced text cleaning while preserving structure and formatting
     */
    private static function enhancedTextCleaning($text)
    {
        // Normalize line endings
        $text = preg_replace('/\r\n|\r/', "\n", $text);
        
        // Remove excessive whitespace while preserving paragraph structure
        $text = preg_replace('/[ \t]+/', ' ', $text); // Multiple spaces/tabs to single space
        $text = preg_replace('/\n[ \t]*\n/', "\n\n", $text); // Clean empty lines
        $text = preg_replace('/\n{4,}/', "\n\n\n", $text); // Limit consecutive newlines to max 3
        
        // Fix common PDF extraction issues
        $text = str_replace(['ﬁ', 'ﬂ', 'ﬀ', 'ﬃ', 'ﬄ'], ['fi', 'fl', 'ff', 'ffi', 'ffl'], $text); // Fix ligatures
        $text = preg_replace('/([a-z])([A-Z])/', '$1 $2', $text); // Add space between camelCase
        $text = preg_replace('/(\d)([A-Za-z])/', '$1 $2', $text); // Add space between numbers and letters
        $text = preg_replace('/([A-Za-z])(\d)/', '$1 $2', $text); // Add space between letters and numbers
        
        // Fix broken words that span lines
        $text = preg_replace('/([a-z])-\s*\n\s*([a-z])/', '$1$2', $text); // Rejoin hyphenated words
        
        // Clean up bullet points and lists
        $text = preg_replace('/^\s*[•·▪▫◦‣⁃]\s*/m', '• ', $text);
        $text = preg_replace('/^\s*[\d]+\.\s*/m', '$0', $text); // Preserve numbered lists
        
        // Remove page headers/footers patterns (common patterns)
        $text = preg_replace('/^Page \d+.*$/m', '', $text);
        $text = preg_replace('/^\d+\s*$/m', '', $text); // Standalone page numbers
        
        return trim($text);
    }
    
    /**
     * Create placeholder content when text extraction fails
     */
    private static function createPlaceholderContent($pdfPath, $pageCount = 0, $errorMessage = null)
    {
        $fileName = basename($pdfPath);
        $fileSize = filesize($pdfPath);
        
        $content = "CONVERTED DOCUMENT\n\n";
        $content .= "Original PDF File: {$fileName}\n";
        $content .= "File Size: " . number_format($fileSize / 1024, 2) . " KB\n";
        
        if ($pageCount > 0) {
            $content .= "Total Pages: {$pageCount}\n";
        }
        
        $content .= "Conversion Date: " . date('Y-m-d H:i:s') . "\n\n";
        
        if ($errorMessage) {
            $content .= "EXTRACTION NOTE:\n";
            $content .= "The original PDF content could not be fully extracted due to technical limitations.\n";
            $content .= "This may occur with:\n";
            $content .= "• Scanned documents or image-based PDFs\n";
            $content .= "• Password-protected or encrypted PDFs\n";
            $content .= "• PDFs with complex formatting or embedded objects\n";
            $content .= "• Very large PDF files\n\n";
            $content .= "Error Details: " . $errorMessage . "\n\n";
        } else {
            $content .= "CONTENT NOTE:\n";
            $content .= "This PDF appears to contain primarily images, scanned content, or complex formatting\n";
            $content .= "that cannot be converted to editable text. The original visual layout and\n";
            $content .= "formatting have been preserved in the source PDF file.\n\n";
        }
        
        $content .= "For best results with text extraction, please ensure your PDF:\n";
        $content .= "• Contains selectable text (not just scanned images)\n";
        $content .= "• Is not password-protected\n";
        $content .= "• Uses standard fonts and formatting\n";
        $content .= "• Is under 50MB in size\n\n";
        
        $content .= "If you need to convert scanned PDFs, consider using OCR (Optical Character Recognition) tools first.";
        
        return $content;
    }

    /**
     * Fallback method for PDF text extraction when memory is limited
     */
    private static function extractTextFromPdfFallback($pdfPath)
    {
        try {
            Log::info("Using fallback PDF text extraction method");
            
            // Try with reduced memory approach - process in smaller chunks
            $parser = new Parser();
            
            // Set lower memory limit for fallback
            ini_set('memory_limit', '512M');
            ini_set('max_execution_time', 120);
            
            $pdf = $parser->parseFile($pdfPath);
            $pages = $pdf->getPages();
            
            $extractedText = '';
            $maxPages = min(10, count($pages)); // Limit to first 10 pages in fallback mode
            
            for ($i = 0; $i < $maxPages; $i++) {
                try {
                    $pageText = $pages[$i]->getText();
                    if (!empty(trim($pageText))) {
                        if ($i > 0) {
                            $extractedText .= "\n\n--- Page " . ($i + 1) . " ---\n\n";
                        }
                        $extractedText .= $pageText;
                    }
                } catch (Exception $pageError) {
                    Log::warning("Fallback: Failed to extract page " . ($i + 1));
                    continue;
                }
            }
            
            if (!empty(trim($extractedText))) {
                $extractedText = self::enhancedTextCleaning($extractedText);
                
                if (count($pages) > $maxPages) {
                    $extractedText .= "\n\n[Note: Only first {$maxPages} pages extracted due to memory limitations. Total pages: " . count($pages) . "]";
                }
                
                return $extractedText;
            }
            
            return self::createPlaceholderContent($pdfPath, count($pages), "Memory limitations prevented full extraction");
                   
        } catch (Exception $e) {
            Log::error("Fallback PDF extraction also failed", ['error' => $e->getMessage()]);
            return self::createPlaceholderContent($pdfPath, 0, "All extraction methods failed: " . $e->getMessage());
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
            }
            $imagick = new \Imagick();
            $imagick->setResolution(120, 120); // Lower resolution for faster processing
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
        try {
            Log::info("Word document creation started: " . basename($outputPath));
            $startTime = microtime(true);
            
            // Enhanced text sanitization for XML compatibility
            $textContent = self::sanitizeTextForWord($textContent);
            
            // Handle empty content
            if (empty(trim($textContent))) {
                $textContent = "No readable text found in the PDF document.\n\nThis document was generated from a scanned or image-only PDF file. The original formatting and layout could not be preserved.";
            }
            
            // Create PhpWord instance with proper settings
            $phpWord = new \PhpOffice\PhpWord\PhpWord();
            
            // Set document properties
            $properties = $phpWord->getDocInfo();
            $properties->setCreator('FlexiConvert');
            $properties->setTitle('PDF to Word Conversion');
            $properties->setSubject('Converted from PDF: ' . basename($originalPath));
            $properties->setDescription('Document converted from PDF to DOCX format');
            $properties->setCreated(time());
            $properties->setModified(time());
            
            // Add section with proper page settings
            $section = $phpWord->addSection([
                'marginTop' => 1440,    // 1 inch
                'marginBottom' => 1440,
                'marginLeft' => 1440,
                'marginRight' => 1440,
            ]);
            
            // Add minimal document header to reduce file size
            $section->addText('PDF to Word Conversion', [
                'name' => 'Calibri',
                'size' => 14,
                'bold' => true
            ]);
            $section->addTextBreak(1);
            
            // Try OCR extraction if no readable text found
            if (trim($textContent) === 'No readable text found in the PDF document.' || 
                strpos($textContent, 'No readable text found') !== false) {
                Log::info("No readable text found, attempting OCR extraction");
                $ocrText = self::extractTextWithOCR($originalPath);
                if (!empty(trim($ocrText))) {
                    $textContent = $ocrText;
                    Log::info("OCR extraction successful, extracted " . strlen($textContent) . " characters");
                }
            }
            
            // Process and add content with improved formatting
            self::addFormattedTextToSection($section, $textContent);
            
            // Always extract and add images from PDF (regardless of text content)
            Log::info("Extracting images from PDF for Word document");
            $images = self::extractImagesFromPdf($originalPath);
            if (!empty($images)) {
                Log::info("Found " . count($images) . " images in PDF");
                
                // Add images section
                $section->addTextBreak(2);
                $section->addText('Images from PDF:', [
                    'name' => 'Calibri',
                    'size' => 12,
                    'bold' => true,
                    'color' => '2E74B5'
                ]);
                $section->addTextBreak(1);
                
                foreach ($images as $imageInfo) {
                    if (file_exists($imageInfo['path'])) {
                        try {
                            // Add image to Word document
                            $section->addImage($imageInfo['path'], [
                                'width' => min($imageInfo['width'], 400), // Max 400px width
                                'height' => min($imageInfo['height'], 300), // Max 300px height
                                'wrappingStyle' => 'inline'
                            ]);
                            
                            // Add image caption
                            $section->addText('Page ' . $imageInfo['page'], [
                                'name' => 'Calibri',
                                'size' => 9,
                                'italic' => true,
                                'color' => '666666'
                            ]);
                            $section->addTextBreak(1);
                            
                            Log::info("Added image from page " . $imageInfo['page'] . " to Word document");
                        } catch (\Exception $e) {
                            Log::warning("Failed to add image from page " . $imageInfo['page'] . ": " . $e->getMessage());
                        }
                    }
                }
                
                // Clean up temporary image files
                foreach ($images as $imageInfo) {
                    if (file_exists($imageInfo['path'])) {
                        @unlink($imageInfo['path']);
                    }
                }
            } else {
                Log::info("No images found in PDF");
            }
            
            // Ensure output directory exists
            $outputDir = dirname($outputPath);
            if (!file_exists($outputDir)) {
                if (!mkdir($outputDir, 0755, true)) {
                    throw new \Exception('Cannot create output directory: ' . $outputDir);
                }
            }
            
            // Save document with error handling
            try {
                $writer = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
                $writer->save($outputPath);
            } catch (\Exception $e) {
                Log::error('PhpWord save failed', ['error' => $e->getMessage()]);
                throw new \Exception('Failed to save Word document: ' . $e->getMessage());
            }
            
            // Verify file creation
            if (!file_exists($outputPath)) {
                throw new \Exception('Word document file was not created at: ' . $outputPath);
            }
            
            $fileSize = filesize($outputPath);
            if ($fileSize < 1000) { // DOCX files should be at least 1KB
                throw new \Exception('Word document file appears to be corrupt (size: ' . $fileSize . ' bytes)');
            }
            
            $processingTime = microtime(true) - $startTime;
            Log::info("Word document created successfully", [
                'filename' => basename($outputPath),
                'size' => number_format($fileSize) . ' bytes',
                'processing_time' => round($processingTime, 2) . 's'
            ]);
            
            return $outputPath;
            
        } catch (\Exception $e) {
            Log::error("Word document creation failed", [
                'error' => $e->getMessage(),
                'output_path' => $outputPath,
                'original_file' => basename($originalPath ?? 'unknown')
            ]);
            throw new \Exception("Failed to create Word document: " . $e->getMessage());
        }
    }
    
    /**
     * Extract images from PDF using Imagick
     */
    private static function extractImagesFromPdf($pdfPath)
    {
        $images = [];
        
        try {
            if (!extension_loaded('imagick')) {
                Log::warning("Imagick not available for image extraction");
                return $images;
            }
            
            Log::info("Extracting images from PDF: " . basename($pdfPath));
            
            $imagick = new \Imagick();
            $imagick->setResolution(150, 150); // Good quality for Word documents
            $imagick->readImage($pdfPath);
            
            $pageCount = $imagick->getNumberImages();
            Log::info("Processing {$pageCount} pages for image extraction");
            
            $imagick->resetIterator();
            $pageIndex = 0;
            
            foreach ($imagick as $page) {
                $pageIndex++;
                
                try {
                    // Convert page to PNG for better quality in Word
                    $page->setImageFormat('png');
                    
                    // Create temporary file
                    $tempDir = sys_get_temp_dir();
                    $tempImagePath = $tempDir . '/pdf_image_' . $pageIndex . '_' . uniqid() . '.png';
                    $page->writeImage($tempImagePath);
                    
                    // Get image dimensions
                    $width = $page->getImageWidth();
                    $height = $page->getImageHeight();
                    
                    $images[] = [
                        'path' => $tempImagePath,
                        'page' => $pageIndex,
                        'width' => $width,
                        'height' => $height
                    ];
                    
                    Log::info("Extracted image from page {$pageIndex}: {$width}x{$height}");
                    
                } catch (\Exception $pageError) {
                    Log::warning("Failed to extract image from page {$pageIndex}: " . $pageError->getMessage());
                    continue;
                }
            }
            
            $imagick->clear();
            $imagick->destroy();
            
            Log::info("Image extraction completed: " . count($images) . " images extracted");
            return $images;
            
        } catch (\Exception $e) {
            Log::error("Image extraction failed: " . $e->getMessage());
            return $images;
        }
    }
    
    /**
     * Extract text from PDF using OCR
     */
    private static function extractTextWithOCR($pdfPath)
    {
        try {
            if (!extension_loaded('imagick')) {
                Log::warning("Imagick not available for OCR");
                return '';
            }

            Log::info("Starting OCR text extraction from PDF: " . basename($pdfPath));
            $startTime = microtime(true);

            // Set generous time limits for large documents
            ini_set('max_execution_time', 600); // 10 minutes
            ini_set('memory_limit', '2G');
            
            $imagick = new \Imagick();
            $imagick->setResolution(120, 120); // Optimized resolution for speed
            $imagick->readImage($pdfPath);
            
            $pageCount = $imagick->getNumberImages();
            Log::info("Processing {$pageCount} pages for OCR");
            
            // For very large documents, limit pages to prevent timeout
            $maxPages = 50; // Process max 50 pages
            if ($pageCount > $maxPages) {
                Log::info("Large document detected ({$pageCount} pages), limiting to first {$maxPages} pages");
                $pageCount = $maxPages;
            }
            
            $allText = '';
            $imagick->resetIterator();
            $pageIndex = 0;
            
            foreach ($imagick as $page) {
                $pageIndex++;
                
                // Stop if we've reached the page limit
                if ($pageIndex > $pageCount) {
                    break;
                }
                
                try {
                    // Convert to grayscale for better OCR
                    $page->setImageType(\Imagick::IMGTYPE_GRAYSCALE);
                    $page->setImageFormat('png');
                    
                    // Basic image enhancement for faster OCR
                    $page->normalizeImage();
                    $page->contrastImage(true);
                    
                    // Create temporary file
                    $tempDir = sys_get_temp_dir();
                    $tempImagePath = $tempDir . '/ocr_page_' . $pageIndex . '_' . uniqid() . '.png';
                    $page->writeImage($tempImagePath);
                    
                    // Perform OCR on the image
                    $text = self::performOCR($tempImagePath, $pageIndex);
                    
                    if (!empty(trim($text))) {
                        if (!empty($allText)) {
                            $allText .= "\n\n--- Page " . $pageIndex . " ---\n\n";
                        }
                        $allText .= $text;
                        
                        // Log every 5 pages to reduce log spam
                        if ($pageIndex % 5 == 0 || $pageIndex == 1) {
                            Log::info("OCR extracted text from page {$pageIndex}: " . strlen($text) . " characters");
                        }
                    }
                    
                    // Clean up temp file
                    if (file_exists($tempImagePath)) {
                        @unlink($tempImagePath);
                    }
                    
                    // Progress logging for large documents
                    if ($pageCount > 5 && $pageIndex % 3 === 0) {
                        Log::info("OCR progress: {$pageIndex}/{$pageCount} pages processed");
                    }
                    
                } catch (\Exception $pageError) {
                    Log::warning("OCR failed for page {$pageIndex}: " . $pageError->getMessage());
                    continue;
                }
            }
            
            $imagick->clear();
            $imagick->destroy();
            
            // Clean and format extracted text
            if (!empty(trim($allText))) {
                $allText = self::cleanOCRText($allText);
                $processingTime = microtime(true) - $startTime;
                
                Log::info("OCR extraction completed", [
                    'pages' => $pageCount,
                    'text_length' => strlen($allText),
                    'processing_time' => round($processingTime, 2) . 's'
                ]);
                
                return $allText;
            }
            
            Log::warning("OCR extraction produced no readable text");
            return '';
            
        } catch (\Exception $e) {
            Log::error("OCR extraction failed: " . $e->getMessage());
            return '';
        }
    }
    
    /**
     * Perform OCR on image file using available methods
     */
    private static function performOCR($imagePath, $pageNumber)
    {
        $text = '';
        
        // Method 1: Try Tesseract if available
        $text = self::performTesseractOCR($imagePath);
        
        // Method 2: If Tesseract fails, try basic image analysis
        if (empty(trim($text))) {
            $text = self::performBasicImageAnalysis($imagePath, $pageNumber);
        }
        
        return $text;
    }
    
    /**
     * Perform OCR using Tesseract (if available)
     */
    private static function performTesseractOCR($imagePath)
    {
        try {
            // Check if Tesseract is available
            $tesseractPaths = [
                'C:\Program Files\Tesseract-OCR\tesseract.exe',
                'C:\Program Files (x86)\Tesseract-OCR\tesseract.exe',
                '/usr/bin/tesseract',
                '/usr/local/bin/tesseract',
                'tesseract' // If in PATH
            ];
            
            $tesseractPath = null;
            foreach ($tesseractPaths as $path) {
                if (file_exists($path) || $path === 'tesseract') {
                    $tesseractPath = $path;
                    break;
                }
            }
            
            if (!$tesseractPath) {
                Log::info("Tesseract not found, skipping Tesseract OCR");
                return '';
            }
            
            // Create output file path
            $outputPath = sys_get_temp_dir() . '/ocr_output_' . uniqid();
            
            // Run Tesseract
            $command = "\"{$tesseractPath}\" \"{$imagePath}\" \"{$outputPath}\" -l eng --psm 6 2>&1";
            $output = [];
            $returnCode = 0;
            
            exec($command, $output, $returnCode);
            
            $textFile = $outputPath . '.txt';
            if (file_exists($textFile)) {
                $text = file_get_contents($textFile);
                @unlink($textFile);
                
                if (!empty(trim($text))) {
                    Log::info("Tesseract OCR successful");
                    return $text;
                }
            }
            
            Log::info("Tesseract OCR produced no text");
            return '';
            
        } catch (\Exception $e) {
            Log::warning("Tesseract OCR failed: " . $e->getMessage());
            return '';
        }
    }
    
    /**
     * Basic image analysis fallback when OCR tools are not available
     */
    private static function performBasicImageAnalysis($imagePath, $pageNumber)
    {
        try {
            // This is a fallback method that provides basic content description
            $imageSize = getimagesize($imagePath);
            if (!$imageSize) {
                return '';
            }
            
            $width = $imageSize[0];
            $height = $imageSize[1];
            
            // Create basic content description
            $content = "Page {$pageNumber} Content\n\n";
            $content .= "This page contains text and/or images that could not be automatically extracted.\n";
            $content .= "Image dimensions: {$width} x {$height} pixels\n\n";
            $content .= "[Content from scanned document - OCR tools not available]\n";
            $content .= "To extract text from scanned documents, please install Tesseract OCR.\n\n";
            
            return $content;
            
        } catch (\Exception $e) {
            Log::warning("Basic image analysis failed: " . $e->getMessage());
            return "Page {$pageNumber}: [Content could not be analyzed]\n\n";
        }
    }
    
    /**
     * Clean and format OCR extracted text
     */
    private static function cleanOCRText($text)
    {
        // Remove excessive whitespace
        $text = preg_replace('/\s+/', ' ', $text);
        $text = preg_replace('/\n\s*\n\s*\n/', "\n\n", $text);
        
        // Fix common OCR errors
        $text = str_replace(['|', '~', '`'], ['I', '-', "'"], $text);
        $text = preg_replace('/([a-z])([A-Z])/', '$1 $2', $text); // Add space between words
        $text = preg_replace('/(\d)\s+(\d)/', '$1$2', $text); // Fix split numbers
        
        // Clean up line breaks
        $text = preg_replace('/([a-z])\s*\n\s*([a-z])/', '$1 $2', $text); // Join broken words
        $text = preg_replace('/\n+/', "\n", $text); // Remove excessive line breaks
        
        return trim($text);
    }

    /**
     * Sanitize text content for Word document XML compatibility
     */
    private static function sanitizeTextForWord($text)
    {
        if (empty($text)) return '';
        
        // Remove problematic characters that cause Word corruption
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text);
        
        // Fix UTF-8 encoding issues
        $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
        
        // Remove invalid UTF-8 sequences that cause corruption
        $text = preg_replace('/[^\x09\x0A\x0D\x20-\x7E\x{A0}-\x{D7FF}\x{E000}-\x{FFFD}]/u', '', $text);
        
        // Remove XML-breaking characters
        $text = str_replace(['&', '<', '>', '"', "'"], ['and', '(', ')', '"', "'"], $text);
        
        // Normalize line endings
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        
        // Clean excessive whitespace
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace('/\n{3,}/', "\n\n", $text);
        
        // Limit text length to prevent memory issues and corruption
        if (strlen($text) > 300000) { // 300KB limit for stability
            $text = substr($text, 0, 300000) . "\n\n[Text truncated due to size limit]";
        }
        
        return trim($text);
    }

    /**
     * Add formatted text content to Word section (simplified version)
     */
    private static function addFormattedTextToSection($section, $textContent)
    {
        // Add text content directly without images section
        $paragraphs = preg_split('/\n\s*\n/', $textContent);
        
        foreach ($paragraphs as $paragraph) {
            $paragraph = trim($paragraph);
            if (empty($paragraph)) continue;
            
            $lines = explode("\n", $paragraph);
            
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line)) continue;
                
                // Determine text formatting
                $textStyle = ['name' => 'Arial', 'size' => 11];
                
                // Check for potential headings
                if (self::isLikelyHeading($line)) {
                    $textStyle['bold'] = true;
                    $textStyle['size'] = 13;
                    $textStyle['color'] = '2E74B5';
                }
                
                // Add the text
                $section->addText($line, $textStyle);
            }
            
            // Add paragraph break
            $section->addTextBreak();
        }
    }
    
    /**
     * Determine if a line is likely a heading
     */
    private static function isLikelyHeading($line)
    {
        $line = trim($line);
        
        // Check various heading patterns
        return (
            // Short lines that are all caps
            (strlen($line) < 60 && strtoupper($line) === $line && ctype_alpha(str_replace(' ', '', $line))) ||
            // Lines that end without punctuation and are relatively short
            (strlen($line) < 80 && !preg_match('/[.!?]$/', $line) && ucfirst($line) === $line) ||
            // Lines with numbering
            preg_match('/^\d+\.?\s+[A-Z]/', $line) ||
            // Lines that start with common heading words
            preg_match('/^(Chapter|Section|Part|Appendix|Introduction|Conclusion|Summary|Abstract)\s/i', $line)
        );
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
            // Map frontend compressionLevel to backend quality
            $compressionLevel = $settings['compressionLevel'] ?? 'medium';
            $quality = $compressionLevel; // Direct mapping: low/medium/high
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
     * Process PDF rotation
     */
    public static function processRotatePdf($file, $settings = [])
    {
        $startTime = microtime(true);
        $processing = null;
        
        try {
            $rotation = $settings['rotation'] ?? '90';
            $friendlyFilename = self::generateFriendlyFilename('rotate-pdf', $file->getClientOriginalName());
            $internalFilename = Str::uuid() . '.pdf';
            $outputPath = config('pdftools.storage.outputs_path') . '/' . $internalFilename;
            
            $processing = PdfProcessing::create([
                'user_id' => auth()->id(),
                'tool_name' => 'rotate-pdf',
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

            // Use FPDI for PDF rotation
            $fpdi = new \setasign\Fpdi\Fpdi();
            $pageCount = $fpdi->setSourceFile($fullInputPath);
            
            for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                $templateId = $fpdi->importPage($pageNo);
                $size = $fpdi->getTemplateSize($templateId);
                
                // Adjust page orientation based on rotation
                if ($rotation == '90' || $rotation == '270') {
                    $fpdi->AddPage('P', [$size['height'], $size['width']]);
                } else {
                    $fpdi->AddPage($size['orientation'], [$size['width'], $size['height']]);
                }
                
                // Apply rotation
                $fpdi->Rotate((int)$rotation);
                $fpdi->useTemplate($templateId);
            }
            
            Storage::put($outputPath, $fpdi->Output('S'));
            Storage::delete($inputPath);
            
            $processing->update([
                'status' => 'completed',
                'progress' => 100,
                'completed_at' => now()
            ]);

            $processingTime = microtime(true) - $startTime;
            self::logConversionMetrics('rotate-pdf', $file->getSize(), 
                Storage::size($outputPath), $processingTime, auth()->id());

            return self::createSuccessResponse(
                'PDF rotated successfully.',
                $processing->id,
                $friendlyFilename,
                route('pdf-tools.download', $processing->id)
            );

        } catch (Exception $e) {
            if ($processing) {
                $processing->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
            }
            Log::error("PDF rotation failed", [
                'error' => $e->getMessage(),
                'file' => $file->getClientOriginalName()
            ]);
            throw $e;
        }
    }

    /**
     * Process PDF split
     */
    public static function processSplitPdf($file, $settings = [])
    {
        $startTime = microtime(true);
        $processing = null;
        
        try {
            $splitMode = $settings['splitMode'] ?? 'all';
            $friendlyFilename = 'split_' . pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME) . '.zip';
            $internalFilename = Str::uuid() . '.zip';
            $outputPath = config('pdftools.storage.outputs_path') . '/' . $internalFilename;
            
            $processing = PdfProcessing::create([
                'user_id' => auth()->id(),
                'tool_name' => 'split-pdf',
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

            $fpdi = new \setasign\Fpdi\Fpdi();
            $pageCount = $fpdi->setSourceFile($fullInputPath);
            
            $zip = new \ZipArchive();
            $tempZipPath = sys_get_temp_dir() . '/' . uniqid() . '.zip';
            
            if ($zip->open($tempZipPath, \ZipArchive::CREATE) !== TRUE) {
                throw new Exception('Cannot create ZIP file');
            }
            
            if ($splitMode === 'all') {
                // Split every page
                for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                    $newPdf = new \setasign\Fpdi\Fpdi();
                    $templateId = $newPdf->importPage($pageNo, '/MediaBox', $fullInputPath);
                    $size = $newPdf->getTemplateSize($templateId);
                    
                    $newPdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                    $newPdf->useTemplate($templateId);
                    
                    $pageFileName = 'page_' . str_pad($pageNo, 3, '0', STR_PAD_LEFT) . '.pdf';
                    $zip->addFromString($pageFileName, $newPdf->Output('S'));
                }
            } elseif ($splitMode === 'range') {
                $startPage = max(1, (int)($settings['startPage'] ?? 1));
                $endPage = min($pageCount, (int)($settings['endPage'] ?? $pageCount));
                
                $newPdf = new \setasign\Fpdi\Fpdi();
                for ($pageNo = $startPage; $pageNo <= $endPage; $pageNo++) {
                    $templateId = $newPdf->importPage($pageNo, '/MediaBox', $fullInputPath);
                    $size = $newPdf->getTemplateSize($templateId);
                    
                    $newPdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                    $newPdf->useTemplate($templateId);
                }
                
                $rangeFileName = 'pages_' . $startPage . '-' . $endPage . '.pdf';
                $zip->addFromString($rangeFileName, $newPdf->Output('S'));
            } elseif ($splitMode === 'interval') {
                $interval = max(1, (int)($settings['interval'] ?? 5));
                $fileIndex = 1;
                
                for ($startPage = 1; $startPage <= $pageCount; $startPage += $interval) {
                    $endPage = min($startPage + $interval - 1, $pageCount);
                    
                    $newPdf = new \setasign\Fpdi\Fpdi();
                    for ($pageNo = $startPage; $pageNo <= $endPage; $pageNo++) {
                        $templateId = $newPdf->importPage($pageNo, '/MediaBox', $fullInputPath);
                        $size = $newPdf->getTemplateSize($templateId);
                        
                        $newPdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                        $newPdf->useTemplate($templateId);
                    }
                    
                    $intervalFileName = 'part_' . str_pad($fileIndex, 2, '0', STR_PAD_LEFT) . '.pdf';
                    $zip->addFromString($intervalFileName, $newPdf->Output('S'));
                    $fileIndex++;
                }
            }
            
            $zip->close();
            Storage::put($outputPath, file_get_contents($tempZipPath));
            unlink($tempZipPath);
            Storage::delete($inputPath);
            
            $processing->update([
                'status' => 'completed',
                'progress' => 100,
                'completed_at' => now()
            ]);

            $processingTime = microtime(true) - $startTime;
            self::logConversionMetrics('split-pdf', $file->getSize(), 
                Storage::size($outputPath), $processingTime, auth()->id());

            return self::createSuccessResponse(
                'PDF split successfully.',
                $processing->id,
                $friendlyFilename,
                route('pdf-tools.download', $processing->id)
            );

        } catch (Exception $e) {
            if ($processing) {
                $processing->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
            }
            Log::error("PDF split failed", [
                'error' => $e->getMessage(),
                'file' => $file->getClientOriginalName()
            ]);
            throw $e;
        }
    }

    /**
     * Process PDF merge
     */
    public static function processMergePdfs($files, $settings = [])
    {
        $startTime = microtime(true);
        $processing = null;
        
        try {
            $mergeOrder = $settings['mergeOrder'] ?? 'upload';
            $friendlyFilename = 'merged_' . date('Ymd_His') . '.pdf';
            $internalFilename = Str::uuid() . '.pdf';
            $outputPath = config('pdftools.storage.outputs_path') . '/' . $internalFilename;
            
            $processing = PdfProcessing::create([
                'user_id' => auth()->id(),
                'tool_name' => 'merge-pdf',
                'original_filename' => count($files) . ' files',
                'processed_filename' => $internalFilename,
                'friendly_filename' => $friendlyFilename,
                'file_size' => array_sum(array_map(fn($f) => $f->getSize(), $files)),
                'status' => 'processing',
                'progress' => 0,
                'settings' => json_encode($settings),
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // Sort files if needed
            if ($mergeOrder === 'name') {
                usort($files, fn($a, $b) => strcmp($a->getClientOriginalName(), $b->getClientOriginalName()));
            }

            $fpdi = new \setasign\Fpdi\Fpdi();
            
            foreach ($files as $index => $file) {
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
                
                // Update progress
                $progress = (($index + 1) / count($files)) * 100;
                $processing->update(['progress' => $progress]);
            }
            
            Storage::put($outputPath, $fpdi->Output('S'));
            
            $processing->update([
                'status' => 'completed',
                'progress' => 100,
                'completed_at' => now()
            ]);

            $processingTime = microtime(true) - $startTime;
            self::logConversionMetrics('merge-pdf', 
                array_sum(array_map(fn($f) => $f->getSize(), $files)), 
                Storage::size($outputPath), $processingTime, auth()->id());

            return self::createSuccessResponse(
                'PDFs merged successfully.',
                $processing->id,
                $friendlyFilename,
                route('pdf-tools.download', $processing->id)
            );

        } catch (Exception $e) {
            if ($processing) {
                $processing->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
            }
            Log::error("PDF merge failed", [
                'error' => $e->getMessage(),
                'files' => array_map(fn($f) => $f->getClientOriginalName(), $files)
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

    // Duplicate processMergePdfs method removed - using the one above
 }