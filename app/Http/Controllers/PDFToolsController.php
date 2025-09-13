<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use App\Models\PdfProcessing;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Exception;
use Dompdf\Dompdf;
use Dompdf\Options;
use setasign\Fpdi\Fpdi;
use setasign\Fpdf\Fpdf;
use App\Http\Controllers\PDFToolsHelperMethods;

class PDFToolsController extends Controller
{
    /**
     * Main entry point for all PDF tools processing
     * 
     * Handles file validation, tool dispatching, and performance logging.
     * All complex processing logic is delegated to PDFToolsHelperMethods.
     * 
     * @param Request $request Contains files, tool name, and optional settings
     * @return \Illuminate\Http\JsonResponse Standardized success/error response
     */
    public function process(Request $request)
    {
        $startTime = microtime(true);
        
        try {
            $request->validate([
                'files' => 'required|array|min:1',
                'files.*' => 'file|max:' . (config('pdftools.validation.max_file_size') / 1024),
                'tool' => 'required|string',
                'settings' => 'nullable|string'
            ]);

            $files = $request->file('files');
            $tool = $request->input('tool');
            $settings = json_decode($request->input('settings', '{}'), true) ?? [];
            $userId = auth()->id();

            // Enhanced file validation
            $this->validateFileTypesForTool($tool, $files);

            if (config('pdftools.logging.log_conversions')) {
                Log::info('PDF Tools processing started', [
                    'tool' => $tool,
                    'files_count' => count($files),
                    'user_id' => $userId,
                    'total_size' => array_sum(array_map(fn($f) => $f->getSize(), $files))
                ]);
            }

            // Dispatch to appropriate method
            $result = $this->dispatchTool($tool, $files, $settings);
            
            // Log performance metrics
            $processingTime = microtime(true) - $startTime;
            PDFToolsHelperMethods::logConversionMetrics($tool, 
                array_sum(array_map(fn($f) => $f->getSize(), $files)), 
                0, $processingTime, $userId);
            
            return $result;

        } catch (ValidationException $e) {
            Log::error('PDF Tools validation failed', ['errors' => $e->errors()]);
            return response()->json([
                'success' => false,
                'message' => 'Validation failed: ' . implode(', ', array_flatten($e->errors()))
            ], 422);
        } catch (Exception $e) {
            Log::error('PDF Tools processing failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'tool' => $request->input('tool', 'unknown')
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Processing failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Route processing requests to appropriate tool methods
     * 
     * @param string $tool Tool name (e.g., 'compress-pdf', 'word-to-pdf')
     * @param array $files Uploaded files array
     * @param array $settings Tool-specific settings
     * @return \Illuminate\Http\JsonResponse Processing result
     * @throws Exception If tool is not supported
     */
    private function dispatchTool($tool, $files, $settings)
    {
        switch ($tool) {
            case 'merge-pdf':
                return $this->mergePdfs($files, $settings);
            case 'split-pdf':
                return $this->splitPdf($files[0], $settings);
            case 'compress-pdf':
                return $this->compressPdf($files[0], $settings);
            case 'rotate-pdf':
                return $this->rotatePdf($files[0], $settings);
            case 'add-watermark':
                return $this->addWatermark($files[0], $settings);
            case 'add-page-numbers':
                return $this->addPageNumbers($files[0], $settings);
            case 'jpg-to-pdf':
            case 'image-to-pdf':
            case 'images-to-pdf':
                return $this->imageToPdf($files, $settings);
            case 'pdf-to-jpg':
            case 'pdf-to-image':
                return $this->pdfToImage($files[0], $settings);
            case 'word-to-pdf':
            case 'docx-to-pdf':
                return $this->wordToPdf($files[0], $settings);
            case 'pdf-to-word':
            case 'pdf-to-docx':
                return $this->pdfToWord($files[0], $settings);
            case 'excel-to-pdf':
            case 'xlsx-to-pdf':
                return $this->excelToPdf($files[0], $settings);
            case 'pdf-to-excel':
            case 'pdf-to-xlsx':
                return $this->pdfToExcel($files[0], $settings);
            case 'ppt-to-pdf':
            case 'powerpoint-to-pdf':
                return $this->powerpointToPdf($files[0], $settings);
            case 'pdf-to-ppt':
            case 'pdf-to-powerpoint':
                return $this->pdfToPowerpoint($files[0], $settings);
            case 'html-to-pdf':
                return PDFToolsHelperMethods::convertHtmlToPdf($files[0], $settings);
            default:
                throw new Exception('Unsupported tool: ' . $tool);
        }
    }

    /**
     * Convert Word document to PDF using LibreOffice CLI with PhpOffice fallback
     * 
     * @param \Illuminate\Http\UploadedFile $file Word document file
     * @param array $settings Conversion settings (optional)
     * @return \Illuminate\Http\JsonResponse Success response with download info or error
     */
    private function wordToPdf($file, $settings = [])
    {
        try {
            return PDFToolsHelperMethods::processWordToPdf($file, $settings);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Word to PDF conversion failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Compress PDF using Ghostscript with FPDI fallback
     * 
     * @param \Illuminate\Http\UploadedFile $file PDF file to compress
     * @param array $settings Compression settings (compressionLevel: low|medium|high)
     * @return \Illuminate\Http\JsonResponse Success response with download info or error
     */
    private function compressPdf($file, $settings = [])
    {
        try {
            return PDFToolsHelperMethods::processCompressPdf($file, $settings);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'PDF compression failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Rotate PDF pages
     * 
     * @param \Illuminate\Http\UploadedFile $file PDF file to rotate
     * @param array $settings Rotation settings (rotation: 90|180|270|-90)
     * @return \Illuminate\Http\JsonResponse Success response with download info or error
     */
    private function rotatePdf($file, $settings = [])
    {
        try {
            return PDFToolsHelperMethods::processRotatePdf($file, $settings);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'PDF rotation failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Split PDF into separate files
     * 
     * @param \Illuminate\Http\UploadedFile $file PDF file to split
     * @param array $settings Split settings (splitMode, startPage, endPage, interval)
     * @return \Illuminate\Http\JsonResponse Success response with download info or error
     */
    private function splitPdf($file, $settings = [])
    {
        try {
            return PDFToolsHelperMethods::processSplitPdf($file, $settings);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'PDF split failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Merge multiple PDFs into one file
     * 
     * @param array $files Array of PDF files to merge
     * @param array $settings Merge settings (mergeOrder: upload|name)
     * @return \Illuminate\Http\JsonResponse Success response with download info or error
     */
    private function mergePdfs($files, $settings = [])
    {
        try {
            return PDFToolsHelperMethods::processMergePdfs($files, $settings);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'PDF merge failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Validate file types for specific conversion tools
     */
    private function validateFileTypesForTool($tool, $files)
    {
        $validationRules = [
            'word-to-pdf' => ['doc', 'docx', 'odt'],
            'docx-to-pdf' => ['doc', 'docx', 'odt'],
            'excel-to-pdf' => ['xls', 'xlsx', 'ods'],
            'xlsx-to-pdf' => ['xls', 'xlsx', 'ods'],
            'ppt-to-pdf' => ['ppt', 'pptx', 'odp'],
            'powerpoint-to-pdf' => ['ppt', 'pptx', 'odp'],
            'image-to-pdf' => ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'tiff'],
            'jpg-to-pdf' => ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'tiff'],
            'images-to-pdf' => ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'tiff'],
            'pdf-to-word' => ['pdf'],
            'pdf-to-docx' => ['pdf'],
            'pdf-to-excel' => ['pdf'],
            'pdf-to-xlsx' => ['pdf'],
            'pdf-to-ppt' => ['pdf'],
            'pdf-to-powerpoint' => ['pdf'],
            'pdf-to-jpg' => ['pdf'],
            'pdf-to-image' => ['pdf'],
            'merge-pdf' => ['pdf'],
            'split-pdf' => ['pdf'],
            'compress-pdf' => ['pdf'],
            'rotate-pdf' => ['pdf'],
            'add-watermark' => ['pdf'],
            'add-page-numbers' => ['pdf'],
            'html-to-pdf' => ['html', 'htm']
        ];

        if (!isset($validationRules[$tool])) {
            return; // No validation rules for this tool
        }

        $allowedExtensions = $validationRules[$tool];
        $allowedMimeTypes = $this->getValidMimeTypes($allowedExtensions);

        foreach ($files as $file) {
            $extension = strtolower($file->getClientOriginalExtension());
            $mimeType = $file->getMimeType();

            if (!in_array($extension, $allowedExtensions) || !in_array($mimeType, $allowedMimeTypes)) {
                throw new Exception("File type not supported for this conversion. Expected: " . implode(', ', $allowedExtensions) . ". Got: {$extension} ({$mimeType})");
            }
        }
    }

    /**
     * Get valid MIME types for file extensions
     */
    private function getValidMimeTypes($extensions)
    {
        $mimeMap = [
            'pdf' => ['application/pdf'],
            'doc' => ['application/msword'],
            'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/octet-stream'],
            'odt' => ['application/vnd.oasis.opendocument.text'],
            'xls' => ['application/vnd.ms-excel'],
            'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/octet-stream'],
            'ods' => ['application/vnd.oasis.opendocument.spreadsheet'],
            'ppt' => ['application/vnd.ms-powerpoint'],
            'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/octet-stream'],
            'odp' => ['application/vnd.oasis.opendocument.presentation'],
            'jpg' => ['image/jpeg', 'application/octet-stream'],
            'jpeg' => ['image/jpeg', 'application/octet-stream'],
            'png' => ['image/png', 'application/octet-stream'],
            'gif' => ['image/gif', 'application/octet-stream'],
            'bmp' => ['image/bmp', 'image/x-ms-bmp', 'application/octet-stream'],
            'webp' => ['image/webp', 'application/octet-stream'],
            'tiff' => ['image/tiff', 'application/octet-stream'],
            'tif' => ['image/tiff', 'application/octet-stream'],
            'html' => ['text/html', 'application/octet-stream'],
            'htm' => ['text/html', 'application/octet-stream']
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
     * Find LibreOffice executable path on Windows
     */
    private function findLibreOffice(): ?string
    {
        $possiblePaths = [
            'C:\\Program Files\\LibreOffice\\program\\soffice.exe',
            'C:\\Program Files (x86)\\LibreOffice\\program\\soffice.exe',
            getenv('PROGRAMFILES') . '\\LibreOffice\\program\\soffice.exe',
            getenv('PROGRAMFILES(X86)') . '\\LibreOffice\\program\\soffice.exe'
        ];

        foreach ($possiblePaths as $path) {
            if ($path && file_exists($path)) {
                Log::info('LibreOffice found at: ' . $path);
                return $path;
            }
        }

        // Try to find via registry or PATH
        $command = 'where soffice.exe 2>nul';
        exec($command, $output, $returnCode);
        
        if ($returnCode === 0 && !empty($output[0])) {
            $path = trim($output[0]);
            if (file_exists($path)) {
                Log::info('LibreOffice found via PATH: ' . $path);
                return $path;
            }
        }

        Log::error('LibreOffice not found in any expected locations');
        return null;
    }

    /**
     * Convert images to PDF
     */
    private function imageToPdf($files, $settings = [])
    {
        try {
            $outputFileName = 'images_to_pdf_' . time() . '.pdf';
            $outputPath = 'pdf-tools/outputs/' . $outputFileName;
            
            // Get first file name for display
            $firstFileName = $files[0]->getClientOriginalName();
            $fileCount = count($files);
            $displayName = $fileCount > 1 ? $firstFileName . " (+ " . ($fileCount - 1) . " more)" : $firstFileName;
            
            // Determine the correct tool name based on the actual tool being used
            $toolName = request()->input('tool', 'image-to-pdf');
            
            // Create database record
            $processing = PdfProcessing::create([
                'user_id' => auth()->id() ?? 1,
                'tool_name' => $toolName,
                'original_filename' => $displayName,
                'processed_filename' => $outputFileName,
                'file_size' => array_sum(array_map(fn($f) => $f->getSize(), $files)),
                'status' => 'processing',
                'progress' => 0,
                'settings' => json_encode($settings),
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // Enhanced implementation with settings support
            $pageSize = $settings['pageSize'] ?? 'A4';
            $orientation = $settings['orientation'] ?? 'portrait';
            $margin = intval($settings['margin'] ?? 20);
            $imageSize = $settings['imageSize'] ?? 'fit';
            
            // Build HTML with custom settings
            $html = '<html><head><style>
                body { margin: 0; padding: 0; }
                .page { 
                    page-break-after: always; 
                    padding: ' . $margin . 'mm;
                    text-align: center;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    min-height: calc(100vh - ' . ($margin * 2) . 'mm);
                }
                .image-container {
                    width: 100%;
                    height: 100%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                }
                .image-fit { max-width: 100%; max-height: 100%; }
                .image-fill { width: 100%; height: 100%; object-fit: cover; }
                .image-original { max-width: none; max-height: none; }
                .image-center { max-width: 80%; max-height: 80%; }
            </style></head><body>';
            
            foreach ($files as $index => $file) {
                $imagePath = $file->store('temp');
                $fullPath = Storage::path($imagePath);
                $imageData = base64_encode(file_get_contents($fullPath));
                $mimeType = $file->getMimeType();
                
                $imageClass = 'image-' . $imageSize;
                $isLastImage = ($index === count($files) - 1);
                
                $html .= '<div class="page"' . ($isLastImage ? ' style="page-break-after: avoid;"' : '') . '>';
                $html .= '<div class="image-container">';
                $html .= '<img src="data:' . $mimeType . ';base64,' . $imageData . '" class="' . $imageClass . '">';
                $html .= '</div></div>';
                
                Storage::delete($imagePath);
            }
            $html .= '</body></html>';

            // Convert to PDF using DomPDF with custom settings
            $options = new \Dompdf\Options();
            $options->set('defaultFont', 'Arial');
            $options->set('isRemoteEnabled', true);
            $dompdf = new \Dompdf\Dompdf($options);
            $dompdf->loadHtml($html);
            $dompdf->setPaper($pageSize, $orientation);
            $dompdf->render();

            Storage::put($outputPath, $dompdf->output());

            $processing->update([
                'status' => 'completed',
                'progress' => 100,
                'completed_at' => now()
            ]);

            return PDFToolsHelperMethods::createSuccessResponse('Images berhasil dikonversi ke PDF', $processing->id, $outputFileName, route('pdf-tools.download', $processing->id));

        } catch (\Exception $e) {
            if (isset($processing)) {
                $processing->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
            }
            Log::error("Image to PDF conversion failed: " . $e->getMessage());
            return response()->json(['error' => true, 'message' => 'Konversi gagal: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Convert PDF to image
     */
    private function pdfToImage($file, $settings, $format = 'jpg')
    {
        $processing = null;
        try {
            if (!extension_loaded('imagick')) {
                throw new \Exception('ImageMagick extension tidak tersedia');
            }

            $inputPath = $file->store('pdf-tools/uploads');
            $fullInputPath = Storage::path($inputPath);
            
            $outputFileName = 'pdf_to_' . $format . '_' . Str::uuid() . '.zip';
            $outputPath = 'pdf-tools/outputs/' . $outputFileName;
            
            $processing = PdfProcessing::create([
                'user_id' => auth()->id(),
                'tool_name' => 'pdf-to-' . $format,
                'original_filename' => $file->getClientOriginalName(),
                'processed_filename' => $outputFileName,
                'file_size' => $file->getSize(),
                'status' => 'processing',
                'progress' => 0,
                'settings' => json_encode($settings)
            ]);

            $imagick = new \Imagick();
            $imagick->setResolution(150, 150);
            $imagick->readImage($fullInputPath);
            
            $zip = new \ZipArchive();
            $tempZipPath = sys_get_temp_dir() . '/' . uniqid() . '.zip';
            
            if ($zip->open($tempZipPath, \ZipArchive::CREATE) !== TRUE) {
                throw new \Exception('Tidak dapat membuat file ZIP');
            }

            $imagick = $imagick->coalesceImages();
            
            foreach ($imagick as $index => $page) {
                $page->setImageFormat($format);
                if ($format === 'jpg') {
                    $page->setImageCompressionQuality(90);
                }
                
                $pageFileName = 'page_' . str_pad($index + 1, 3, '0', STR_PAD_LEFT) . '.' . $format;
                $zip->addFromString($pageFileName, $page->getImageBlob());
            }
            
            $zip->close();
            Storage::put($outputPath, file_get_contents($tempZipPath));
            unlink($tempZipPath);
            
            Storage::delete($inputPath);
            $imagick->clear();
            
            $processing->update([
                'status' => 'completed',
                'progress' => 100,
                'completed_at' => now()
            ]);

            return PDFToolsHelperMethods::createSuccessResponse("PDF berhasil dikonversi ke {$format}", $processing->id, $outputFileName, route('pdf-tools.download', $processing->id));

        } catch (\Exception $e) {
            if ($processing) {
                $processing->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
            }
            Log::error("PDF to image conversion failed: " . $e->getMessage());
            return response()->json([
                'error' => true,
                'message' => "Konversi PDF ke gambar gagal: " . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Add watermark to PDF
     */
    private function addWatermark($file, $settings = [])
    {
        $processing = null;
        try {
            $inputPath = $file->store('pdf-tools/uploads');
            $fullInputPath = Storage::path($inputPath);
            
            $outputFileName = 'watermarked_pdf_' . Str::uuid() . '.pdf';
            $outputPath = 'pdf-tools/outputs/' . $outputFileName;
            
            $processing = PdfProcessing::create([
                'user_id' => auth()->id(),
                'tool_name' => 'add-watermark',
                'original_filename' => $file->getClientOriginalName(),
                'processed_filename' => $outputFileName,
                'file_size' => $file->getSize(),
                'status' => 'processing',
                'progress' => 0,
                'settings' => json_encode($settings)
            ]);

            $watermarkText = $settings['watermark_text'] ?? 'WATERMARK';
            
            $fpdi = new Fpdi();
            $pageCount = $fpdi->setSourceFile($fullInputPath);
            
            for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                $templateId = $fpdi->importPage($pageNo);
                $size = $fpdi->getTemplateSize($templateId);
                
                $fpdi->AddPage($size['orientation'], [$size['width'], $size['height']]);
                $fpdi->useTemplate($templateId);
                
                $fpdi->SetFont('Arial', 'B', 50);
                $fpdi->SetTextColor(200, 200, 200);
                $fpdi->SetXY($size['width']/4, $size['height']/2);
                $fpdi->Cell(0, 0, $watermarkText, 0, 0, 'C');
            }
            
            $pdfContent = $fpdi->Output('S');
            Storage::put($outputPath, $pdfContent);
            Storage::delete($inputPath);
            
            $processing->update([
                'status' => 'completed',
                'progress' => 100,
                'completed_at' => now()
            ]);

            return PDFToolsHelperMethods::createSuccessResponse('Watermark berhasil ditambahkan', $processing->id, $outputFileName, route('pdf-tools.download', $processing->id));

        } catch (\Exception $e) {
            if ($processing) {
                $processing->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
            }
            Log::error("Add watermark failed: " . $e->getMessage());
            return response()->json([
                'error' => true,
                'message' => 'Penambahan watermark gagal: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Add page numbers to PDF
     */
    private function addPageNumbers($file, $settings = [])
    {
        $processing = null;
        try {
            $inputPath = $file->store('pdf-tools/uploads');
            $fullInputPath = Storage::path($inputPath);
            
            $outputFileName = 'numbered_pdf_' . Str::uuid() . '.pdf';
            $outputPath = 'pdf-tools/outputs/' . $outputFileName;
            
            $processing = PdfProcessing::create([
                'user_id' => auth()->id(),
                'tool_name' => 'add-page-numbers',
                'original_filename' => $file->getClientOriginalName(),
                'processed_filename' => $outputFileName,
                'file_size' => $file->getSize(),
                'status' => 'processing',
                'progress' => 0,
                'settings' => json_encode($settings)
            ]);

            $fpdi = new Fpdi();
            $pageCount = $fpdi->setSourceFile($fullInputPath);
            
            for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                $templateId = $fpdi->importPage($pageNo);
                $size = $fpdi->getTemplateSize($templateId);
                
                $fpdi->AddPage($size['orientation'], [$size['width'], $size['height']]);
                $fpdi->useTemplate($templateId);
                
                $fpdi->SetFont('Arial', '', 12);
                $fpdi->SetTextColor(0, 0, 0);
                $fpdi->SetXY($size['width'] - 30, $size['height'] - 20);
                $fpdi->Cell(0, 0, $pageNo, 0, 0, 'C');
            }
            
            $pdfContent = $fpdi->Output('S');
            Storage::put($outputPath, $pdfContent);
            Storage::delete($inputPath);
            
            $processing->update([
                'status' => 'completed',
                'progress' => 100,
                'completed_at' => now()
            ]);

            return PDFToolsHelperMethods::createSuccessResponse('Nomor halaman berhasil ditambahkan', $processing->id, $outputFileName, route('pdf-tools.download', $processing->id));

        } catch (\Exception $e) {
            if ($processing) {
                $processing->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
            }
            Log::error("Add page numbers failed: " . $e->getMessage());
            return response()->json([
                'error' => true,
                'message' => 'Penambahan nomor halaman gagal: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Convert PDF to Word
     */
    private function pdfToWord($file, $settings = [])
    {
        $processing = null;
        try {
            $inputPath = $file->store('pdf-tools/uploads');
            $fullInputPath = Storage::path($inputPath);
            
            $outputFileName = 'pdf_to_word_' . Str::uuid() . '.docx';
            $outputPath = 'pdf-tools/outputs/' . $outputFileName;
            
            $processing = PdfProcessing::create([
                'user_id' => auth()->id(),
                'tool_name' => 'pdf-to-word',
                'original_filename' => $file->getClientOriginalName(),
                'processed_filename' => $outputFileName,
                'file_size' => $file->getSize(),
                'status' => 'processing',
                'progress' => 0,
                'settings' => json_encode($settings)
            ]);

            $textContent = PDFToolsHelperMethods::extractTextFromPdf($fullInputPath);
            PDFToolsHelperMethods::createWordDocument($textContent, Storage::path($outputPath), $fullInputPath);
            
            Storage::delete($inputPath);
            
            $processing->update([
                'status' => 'completed',
                'progress' => 100,
                'completed_at' => now()
            ]);

            return PDFToolsHelperMethods::createSuccessResponse('PDF berhasil dikonversi ke Word', $processing->id, $outputFileName, route('pdf-tools.download', $processing->id));

        } catch (\Exception $e) {
            if ($processing) {
                $processing->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
            }
            Log::error("PDF to Word conversion failed: " . $e->getMessage());
            return response()->json([
                'error' => true,
                'message' => 'Konversi PDF ke Word gagal: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Convert Excel to PDF
     */
    private function excelToPdf($file, $settings = [])
    {
        $processing = null;
        try {
            $inputPath = $file->store('pdf-tools/uploads');
            $fullInputPath = Storage::path($inputPath);
            
            $outputFileName = 'excel_to_pdf_' . Str::uuid() . '.pdf';
            $outputPath = 'pdf-tools/outputs/' . $outputFileName;
            
            $processing = PdfProcessing::create([
                'user_id' => auth()->id(),
                'tool_name' => 'excel-to-pdf',
                'original_filename' => $file->getClientOriginalName(),
                'processed_filename' => $outputFileName,
                'file_size' => $file->getSize(),
                'status' => 'processing',
                'progress' => 0,
                'settings' => json_encode($settings)
            ]);

            // Try LibreOffice
            $libreOfficePath = $this->findLibreOffice();
            if (!$libreOfficePath) {
                throw new \Exception('LibreOffice tidak ditemukan. Pastikan LibreOffice sudah terinstall.');
            }
            
            if (!is_executable($libreOfficePath)) {
                throw new \Exception('LibreOffice tidak dapat dieksekusi: ' . $libreOfficePath);
            }

            $outputDir = Storage::path('pdf-tools/outputs');
            if (!is_dir($outputDir)) {
                mkdir($outputDir, 0755, true);
            }

            $command = "\"{$libreOfficePath}\" --headless --convert-to pdf --outdir \"{$outputDir}\" \"{$fullInputPath}\"";
            Log::info('Executing LibreOffice command for Excel: ' . $command);
            
            exec($command, $output, $returnCode);
            
            Log::info('LibreOffice Excel execution result', [
                'return_code' => $returnCode,
                'output' => $output,
                'command' => $command
            ]);
            
            if ($returnCode === 0) {
                // Use glob to find generated PDF files instead of assuming filename
                $pdfFiles = glob($outputDir . DIRECTORY_SEPARATOR . '*.pdf');
                
                Log::info('Searching for generated Excel PDF files', [
                    'output_dir' => $outputDir,
                    'found_files' => $pdfFiles,
                    'file_count' => count($pdfFiles)
                ]);
                
                if (count($pdfFiles) === 0) {
                    throw new \Exception("File PDF hasil konversi Excel tidak ditemukan di {$outputDir}");
                }
                
                // Get the most recently created PDF file (in case there are multiple)
                $generatedPdf = $pdfFiles[0];
                if (count($pdfFiles) > 1) {
                    // Sort by modification time, get the newest
                    usort($pdfFiles, function($a, $b) {
                        return filemtime($b) - filemtime($a);
                    });
                    $generatedPdf = $pdfFiles[0];
                }
                
                Log::info('Using generated Excel PDF: ' . $generatedPdf);
                
                $finalPath = Storage::path($outputPath);
                rename($generatedPdf, $finalPath);
                Storage::delete($inputPath);
                
                $processing->update([
                    'status' => 'completed',
                    'progress' => 100,
                    'processed_file_size' => filesize($finalPath),
                    'completed_at' => now()
                ]);

                return PDFToolsHelperMethods::createSuccessResponse('Excel berhasil dikonversi ke PDF', $processing->id, $outputFileName, route('pdf-tools.download', $processing->id));
            } else {
                throw new \Exception('LibreOffice Excel conversion failed with return code: ' . $returnCode . '. Output: ' . implode("\n", $output));
            }

        } catch (\Exception $e) {
            if ($processing) {
                $processing->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
            }
            Log::error("Excel to PDF conversion failed: " . $e->getMessage());
            return response()->json([
                'error' => true,
                'message' => 'Konversi Excel ke PDF gagal: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Convert PDF to Excel
     */
    private function pdfToExcel($file, $settings = [])
    {
        $processing = null;
        try {
            // Check file size limit for memory safety
            $maxFileSize = 50 * 1024 * 1024; // 50MB limit
            if ($file->getSize() > $maxFileSize) {
                throw new \Exception('File too large for PDF to Excel conversion. Maximum size: 50MB');
            }

            $outputFileName = 'pdf_to_excel_' . Str::uuid() . '.xlsx';
            $outputPath = config('pdftools.storage.outputs_path') . '/' . $outputFileName;

            $processing = PdfProcessing::create([
                'user_id' => auth()->id(),
                'tool_name' => 'pdf-to-excel',
                'original_filename' => $file->getClientOriginalName(),
                'processed_filename' => $outputFileName,
                'file_size' => $file->getSize(),
                'status' => 'processing',
                'progress' => 0,
                'settings' => json_encode($settings)
            ]);

            $inputPath = $file->store(config('pdftools.storage.uploads_path'));
            $fullInputPath = Storage::path($inputPath);
            
            $textContent = PDFToolsHelperMethods::extractTextFromPdf($fullInputPath);
            PDFToolsHelperMethods::createExcelDocument($textContent, Storage::path($outputPath), $fullInputPath);
            
            Storage::delete($inputPath);
            
            $processing->update([
                'status' => 'completed',
                'progress' => 100,
                'completed_at' => now()
            ]);

            return PDFToolsHelperMethods::createSuccessResponse('PDF berhasil dikonversi ke Excel', $processing->id, $outputFileName, route('pdf-tools.download', $processing->id));

        } catch (\Exception $e) {
            if ($processing) {
                $processing->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
            }
            Log::error("PDF to Excel conversion failed: " . $e->getMessage());
            return response()->json([
                'error' => true,
                'message' => 'Konversi PDF ke Excel gagal: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Convert PowerPoint to PDF
     */
    private function powerpointToPdf($file, $settings = [])
    {
        $processing = null;
        try {
            $inputPath = $file->store('pdf-tools/uploads');
            $fullInputPath = Storage::path($inputPath);
            
            $outputFileName = 'powerpoint_to_pdf_' . Str::uuid() . '.pdf';
            $outputPath = 'pdf-tools/outputs/' . $outputFileName;
            
            $processing = PdfProcessing::create([
                'user_id' => auth()->id(),
                'tool_name' => 'powerpoint-to-pdf',
                'original_filename' => $file->getClientOriginalName(),
                'processed_filename' => $outputFileName,
                'file_size' => $file->getSize(),
                'status' => 'processing',
                'progress' => 0,
                'settings' => json_encode($settings)
            ]);

            // Try LibreOffice
            $libreOfficePath = $this->findLibreOffice();
            if (!$libreOfficePath) {
                throw new \Exception('LibreOffice tidak ditemukan. Pastikan LibreOffice sudah terinstall.');
            }
            
            if (!is_executable($libreOfficePath)) {
                throw new \Exception('LibreOffice tidak dapat dieksekusi: ' . $libreOfficePath);
            }

            $outputDir = Storage::path('pdf-tools/outputs');
            if (!is_dir($outputDir)) {
                mkdir($outputDir, 0755, true);
            }

            $command = "\"{$libreOfficePath}\" --headless --convert-to pdf --outdir \"{$outputDir}\" \"{$fullInputPath}\"";
            Log::info('Executing LibreOffice command: ' . $command);
            
            exec($command, $output, $returnCode);
            
            Log::info('LibreOffice execution result', [
                'return_code' => $returnCode,
                'output' => $output,
                'command' => $command
            ]);
            
            if ($returnCode === 0) {
                // Use glob to find generated PDF files instead of assuming filename
                $pdfFiles = glob($outputDir . DIRECTORY_SEPARATOR . '*.pdf');
                
                Log::info('Searching for generated PDF files', [
                    'output_dir' => $outputDir,
                    'found_files' => $pdfFiles,
                    'file_count' => count($pdfFiles)
                ]);
                
                if (count($pdfFiles) === 0) {
                    throw new \Exception("File PDF hasil konversi tidak ditemukan di {$outputDir}");
                }
                
                // Get the most recently created PDF file (in case there are multiple)
                $generatedPdf = $pdfFiles[0];
                if (count($pdfFiles) > 1) {
                    // Sort by modification time, get the newest
                    usort($pdfFiles, function($a, $b) {
                        return filemtime($b) - filemtime($a);
                    });
                    $generatedPdf = $pdfFiles[0];
                }
                
                Log::info('Using generated PDF: ' . $generatedPdf);
                
                $finalPath = Storage::path($outputPath);
                rename($generatedPdf, $finalPath);
                Storage::delete($inputPath);
                
                $processing->update([
                    'status' => 'completed',
                    'progress' => 100,
                    'processed_file_size' => filesize($finalPath),
                    'completed_at' => now()
                ]);

                return PDFToolsHelperMethods::createSuccessResponse('PowerPoint berhasil dikonversi ke PDF', $processing->id, $outputFileName, route('pdf-tools.download', $processing->id));
            } else {
                throw new \Exception('LibreOffice conversion failed with return code: ' . $returnCode . '. Output: ' . implode("\n", $output));
            }

        }  catch (\Exception $e) {
            if ($processing) {
                $processing->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
            }
            Log::error("PowerPoint to PDF conversion failed: " . $e->getMessage());
            return response()->json([
                'error' => true,
                'message' => 'Konversi PowerPoint ke PDF gagal: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Convert PDF to PowerPoint
     */
    private function pdfToPowerpoint($file, $settings = [])
    {
        $processing = null;
        try {
            // Check file size limit for memory safety
            $maxFileSize = 50 * 1024 * 1024; // 50MB limit
            if ($file->getSize() > $maxFileSize) {
                throw new \Exception('File too large for PDF to PowerPoint conversion. Maximum size: 50MB');
            }

            $outputFileName = 'pdf_to_ppt_' . Str::uuid() . '.pptx';
            $outputPath = config('pdftools.storage.outputs_path') . '/' . $outputFileName;

            $processing = PdfProcessing::create([
                'user_id' => auth()->id(),
                'tool_name' => 'pdf-to-powerpoint',
                'original_filename' => $file->getClientOriginalName(),
                'processed_filename' => $outputFileName,
                'file_size' => $file->getSize(),
                'status' => 'processing',
                'progress' => 0,
                'settings' => json_encode($settings)
            ]);

            $inputPath = $file->store(config('pdftools.storage.uploads_path'));
            $fullInputPath = Storage::path($inputPath);
            
            // Check preserve_layout setting (default: true)
            $preserveLayout = $settings['preserve_layout'] ?? true;
            
            if ($preserveLayout) {
                try {
                    // Try image-based conversion first
                    PDFToolsHelperMethods::createPowerPointFromPdfPages($fullInputPath, Storage::path($outputPath), $settings);
                    Log::info("PDF to PowerPoint: Used preserve_layout mode successfully");
                } catch (\Exception $e) {
                    // Fallback to text-only conversion
                    Log::warning("PDF to PowerPoint: preserve_layout failed, falling back to text-only", [
                        'error' => $e->getMessage()
                    ]);
                    PDFToolsHelperMethods::createPowerPointFromPdfPages($fullInputPath, Storage::path($outputPath));
                }
            } else {
                // Use text-only conversion
                $textContent = PDFToolsHelperMethods::extractTextFromPdf($fullInputPath);
                PDFToolsHelperMethods::createPowerPointDocument($textContent, Storage::path($outputPath), $fullInputPath);
            }
            
            Storage::delete($inputPath);
            
            $processing->update([
                'status' => 'completed',
                'progress' => 100,
                'completed_at' => now()
            ]);

            return PDFToolsHelperMethods::createSuccessResponse('PDF berhasil dikonversi ke PowerPoint', $processing->id, $outputFileName, route('pdf-tools.download', $processing->id));

        } catch (\Exception $e) {
            if ($processing) {
                $processing->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
            }
            Log::error("PDF to PowerPoint conversion failed: " . $e->getMessage());
            return response()->json([
                'error' => true,
                'message' => 'Konversi PDF ke PowerPoint gagal: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Download processed file by processing ID
     * 
     * Validates file existence and processing status before serving download.
     * Uses friendly filename for better user experience.
     * 
     * @param int $id Processing record ID
     * @return \Illuminate\Http\Response File download or JSON error response
     */
    public function download($id)
    {
        try {
            $processing = PdfProcessing::find($id);
            
            if (!$processing) {
                return response()->json([
                    'success' => false,
                    'message' => 'File not found'
                ], 404);
            }

            if ($processing->status !== 'completed') {
                return response()->json([
                    'success' => false,
                    'message' => 'File processing not completed yet'
                ], 400);
            }

            $filePath = config('pdftools.storage.outputs_path') . '/' . $processing->processed_filename;
            
            if (!Storage::exists($filePath)) {
                return response()->json([
                    'success' => false,
                    'message' => 'File not found in storage'
                ], 404);
            }

            $fileContent = Storage::get($filePath);
            $mimeType = Storage::mimeType($filePath);
            
            // Generate proper download filename based on original filename and tool
            $downloadFilename = $this->generateDownloadFilename($processing);
            
            // Simple approach: use friendly filename directly with proper escaping
            $safeFilename = str_replace(['"', '\\', '/'], ['_', '_', '_'], $downloadFilename);
            
            // For Content-Disposition, use RFC 5987 encoding
            $encodedFilename = "UTF-8''" . rawurlencode($downloadFilename);
            
            // Debug logging
            Log::info('Download filename debug', [
                'original_filename' => $processing->original_filename,
                'tool_name' => $processing->tool_name,
                'friendly_filename' => $processing->friendly_filename,
                'processed_filename' => $processing->processed_filename,
                'download_filename' => $downloadFilename,
                'safe_filename' => $safeFilename,
                'encoded_filename' => $encodedFilename
            ]);

            return response($fileContent)
                ->header('Content-Type', $mimeType)
                ->header('Content-Disposition', 'attachment; filename="' . $safeFilename . '"; filename*=' . $encodedFilename)
                ->header('Cache-Control', 'no-cache, must-revalidate')
                ->header('Content-Length', strlen($fileContent));

        } catch (Exception $e) {
            Log::error("Download failed: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Download failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate proper download filename based on original filename and conversion tool
     * 
     * @param PdfProcessing $processing Processing record
     * @return string Proper download filename
     */
    private function generateDownloadFilename($processing)
    {
        $originalFilename = $processing->original_filename;
        $toolName = $processing->tool_name;
        
        // Get base filename without extension
        $baseName = pathinfo($originalFilename, PATHINFO_FILENAME);
        
        // Clean filename for safe download
        $cleanBaseName = $this->cleanFilename($baseName);
        
        switch ($toolName) {
            // Single file conversions - keep original name, change extension
            case 'word-to-pdf':
            case 'excel-to-pdf':
            case 'ppt-to-pdf':
            case 'powerpoint-to-pdf':
            case 'html-to-pdf':
            case 'image-to-pdf':
            case 'images-to-pdf':
            case 'jpg-to-pdf':
                return $cleanBaseName . '.pdf';
            
            case 'pdf-to-word':
                return $cleanBaseName . '.docx';
            
            case 'pdf-to-excel':
                return $cleanBaseName . '.xlsx';
            
            case 'pdf-to-ppt':
            case 'pdf-to-powerpoint':
                return $cleanBaseName . '.pptx';
            
            case 'pdf-to-jpg':
            case 'pdf-to-image':
                return $cleanBaseName . '_images.zip';
            
            // Multi-file operations - use descriptive names
            case 'merge-pdf':
            case 'merge-pdfs':
                return 'merged_document.pdf';
            
            case 'split-pdf':
                return $cleanBaseName . '_split.zip';
            
            // Modification operations - add suffix
            case 'compress-pdf':
                return $cleanBaseName . '_compressed.pdf';
            
            case 'rotate-pdf':
                return $cleanBaseName . '_rotated.pdf';
            
            case 'add-watermark':
                return $cleanBaseName . '_watermarked.pdf';
            
            case 'add-page-numbers':
                return $cleanBaseName . '_numbered.pdf';
            
            case 'repair-pdf':
                return $cleanBaseName . '_repaired.pdf';
            
            case 'ocr-pdf':
                return $cleanBaseName . '_ocr.pdf';
            
            case 'organize-pdf':
                return $cleanBaseName . '_organized.pdf';
            
            case 'crop-pdf':
                return $cleanBaseName . '_cropped.pdf';
            
            case 'edit-pdf':
                return $cleanBaseName . '_edited.pdf';
            
            // Default fallback
            default:
                return $cleanBaseName . '_converted.pdf';
        }
    }
    
    /**
     * Clean filename for safe storage and download
     * 
     * @param string $filename Original filename
     * @return string Cleaned filename
     */
    private function cleanFilename($filename)
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
}
