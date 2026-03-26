<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use App\Models\PdfProcessing;
use App\Services\GotenbergService;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Exception;
use RuntimeException;
use setasign\Fpdi\Fpdi;
use App\Http\Controllers\PDFToolsHelperMethods;
use Symfony\Component\HttpFoundation\Response;

class PDFToolsController extends Controller
{
    private const GOTENBERG_SUPPORTED_TOOLS = [
        'word-to-pdf',
        'docx-to-pdf',
        'excel-to-pdf',
        'xlsx-to-pdf',
        'ppt-to-pdf',
        'powerpoint-to-pdf',
        'jpg-to-pdf',
        'image-to-pdf',
        'images-to-pdf',
        'html-to-pdf',
        'merge-pdf',
        'split-pdf',
    ];

    private const DISABLED_LEGACY_TOOLS = [
        'compress-pdf',
        'rotate-pdf',
        'add-watermark',
        'add-page-numbers',
    ];

    public function __construct(private GotenbergService $gotenberg)
    {
    }

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
            $userId = $this->currentUserId();

            $this->guardToolAvailability($tool);
            $this->ensureGotenbergReadyFor($tool);

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
                'message' => 'Validation failed: ' . implode(', ', Arr::flatten($e->errors()))
            ], 422);
        } catch (RuntimeException $e) {
            $status = $e->getCode();
            if (!is_int($status) || $status < 400 || $status > 599) {
                $status = 500;
            }

            Log::error('PDF Tools runtime failure', [
                'error' => $e->getMessage(),
                'tool' => $request->input('tool', 'unknown'),
                'status' => $status,
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], $status);
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
            case 'jpg-to-pdf':
            case 'image-to-pdf':
            case 'images-to-pdf':
                return $this->imageToPdf($files, $settings);
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
            case 'pdf-to-jpg':
            case 'pdf-to-image':
                return $this->pdfToImage($files[0], $settings);
            case 'html-to-pdf':
                return $this->htmlToPdf($files[0], $settings);
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
            return $this->convertOfficeDocumentToPdf(
                $file,
                'word-to-pdf',
                'word_to_pdf_',
                'Word berhasil dikonversi ke PDF',
                $settings
            );
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Word to PDF conversion failed: ' . $e->getMessage()
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
        $processing = null;

        try {
            $inputPath = $file->store(config('pdftools.storage.uploads_path'));
            $fullInputPath = Storage::path($inputPath);

            $splitOptions = $this->mapSplitSettings($settings);
            $outputExtension = !empty($splitOptions['splitUnify']) ? 'pdf' : 'zip';
            $outputFileName = 'split_pdf_' . Str::uuid() . '.' . $outputExtension;
            $outputPath = config('pdftools.storage.outputs_path') . '/' . $outputFileName;

            $processing = PdfProcessing::create([
                'user_id' => $this->currentUserId(),
                'tool_name' => 'split-pdf',
                'original_filename' => $file->getClientOriginalName(),
                'processed_filename' => $outputFileName,
                'file_size' => $file->getSize(),
                'status' => 'processing',
                'progress' => 0,
                'settings' => json_encode($settings),
            ]);

            $result = $this->gotenberg->splitPdf($fullInputPath, array_merge($splitOptions, [
                'output_filename' => $outputFileName,
            ]));

            $this->storeGotenbergResult($result, $outputPath, [$inputPath]);

            $processing->update([
                'status' => 'completed',
                'progress' => 100,
                'processed_file_size' => Storage::size($outputPath),
                'completed_at' => now(),
            ]);

            return PDFToolsHelperMethods::createSuccessResponse(
                'PDF berhasil dipisahkan',
                $processing->id,
                $outputFileName,
                route('pdf-tools.download', $processing->id)
            );
        } catch (Exception $e) {
            if ($processing) {
                $processing->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
            }

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
        $processing = null;
        $storedInputPaths = [];

        try {
            $outputFileName = 'merged_pdf_' . Str::uuid() . '.pdf';
            $outputPath = config('pdftools.storage.outputs_path') . '/' . $outputFileName;

            $processing = PdfProcessing::create([
                'user_id' => $this->currentUserId(),
                'tool_name' => 'merge-pdf',
                'original_filename' => count($files) . ' PDF files',
                'processed_filename' => $outputFileName,
                'file_size' => array_sum(array_map(fn($uploadedFile) => $uploadedFile->getSize(), $files)),
                'status' => 'processing',
                'progress' => 0,
                'settings' => json_encode($settings),
            ]);

            $gotenbergFiles = $this->prepareFilesForOrderedMerge($files, $storedInputPaths, $settings['mergeOrder'] ?? 'upload');
            $result = $this->gotenberg->mergePdfs($gotenbergFiles, [
                'output_filename' => $outputFileName,
            ]);

            $this->storeGotenbergResult($result, $outputPath, $storedInputPaths);

            $processing->update([
                'status' => 'completed',
                'progress' => 100,
                'processed_file_size' => Storage::size($outputPath),
                'completed_at' => now(),
            ]);

            return PDFToolsHelperMethods::createSuccessResponse(
                'PDF berhasil digabungkan',
                $processing->id,
                $outputFileName,
                route('pdf-tools.download', $processing->id)
            );
        } catch (Exception $e) {
            foreach ($storedInputPaths as $storedInputPath) {
                Storage::delete($storedInputPath);
            }

            if ($processing) {
                $processing->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
            }

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
            'doc' => ['application/msword', 'application/octet-stream'],
            'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/octet-stream'],
            'odt' => ['application/vnd.oasis.opendocument.text'],
            'xls' => ['application/vnd.ms-excel', 'text/html', 'application/octet-stream', 'application/x-ole-storage'],
            'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/octet-stream', 'application/zip'],
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
     * Convert images to PDF
     */
    private function imageToPdf($files, $settings = [])
    {
        $processing = null;
        $storedInputPaths = [];

        try {
            $outputFileName = 'images_to_pdf_' . time() . '.pdf';
            $outputPath = config('pdftools.storage.outputs_path') . '/' . $outputFileName;

            $firstFileName = $files[0]->getClientOriginalName();
            $fileCount = count($files);
            $displayName = $fileCount > 1 ? $firstFileName . ' (+ ' . ($fileCount - 1) . ' more)' : $firstFileName;
            $toolName = request()->input('tool', 'image-to-pdf');

            $processing = PdfProcessing::create([
                'user_id' => $this->currentUserId(),
                'tool_name' => $toolName,
                'original_filename' => $displayName,
                'processed_filename' => $outputFileName,
                'file_size' => array_sum(array_map(fn($uploadedFile) => $uploadedFile->getSize(), $files)),
                'status' => 'processing',
                'progress' => 0,
                'settings' => json_encode($settings),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $gotenbergFiles = [];
            foreach ($files as $index => $uploadedFile) {
                $storedPath = $uploadedFile->store(config('pdftools.storage.uploads_path'));
                $storedInputPaths[] = $storedPath;
                $gotenbergFiles[] = [
                    'path' => Storage::path($storedPath),
                    'filename' => sprintf('%03d_%s', $index + 1, $uploadedFile->getClientOriginalName()),
                ];
            }

            $result = $this->gotenberg->imagesToPdf($gotenbergFiles, array_merge($settings, [
                'output_filename' => $outputFileName,
            ]));

            $this->storeGotenbergResult($result, $outputPath, $storedInputPaths);

            $processing->update([
                'status' => 'completed',
                'progress' => 100,
                'processed_file_size' => Storage::size($outputPath),
                'completed_at' => now(),
            ]);

            return PDFToolsHelperMethods::createSuccessResponse(
                'Images berhasil dikonversi ke PDF',
                $processing->id,
                $outputFileName,
                route('pdf-tools.download', $processing->id)
            );
        } catch (\Exception $e) {
            foreach ($storedInputPaths as $storedInputPath) {
                Storage::delete($storedInputPath);
            }

            if ($processing) {
                $processing->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
            }

            Log::error('Image to PDF conversion failed: ' . $e->getMessage());

            return response()->json(['error' => true, 'message' => 'Konversi gagal: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Convert HTML to PDF with Gotenberg Chromium.
     */
    private function htmlToPdf($file, $settings = [])
    {
        $processing = null;

        try {
            $inputPath = $file->store(config('pdftools.storage.uploads_path'));
            $fullInputPath = Storage::path($inputPath);

            $outputFileName = 'html_to_pdf_' . Str::uuid() . '.pdf';
            $outputPath = config('pdftools.storage.outputs_path') . '/' . $outputFileName;

            $processing = PdfProcessing::create([
                'user_id' => $this->currentUserId(),
                'tool_name' => 'html-to-pdf',
                'original_filename' => $file->getClientOriginalName(),
                'processed_filename' => $outputFileName,
                'file_size' => $file->getSize(),
                'status' => 'processing',
                'progress' => 0,
                'settings' => json_encode($settings),
            ]);

            $result = $this->gotenberg->htmlFileToPdf($fullInputPath, array_merge($settings, [
                'printBackground' => true,
                'output_filename' => $outputFileName,
            ]));

            $this->storeGotenbergResult($result, $outputPath, [$inputPath]);

            $processing->update([
                'status' => 'completed',
                'progress' => 100,
                'processed_file_size' => Storage::size($outputPath),
                'completed_at' => now(),
            ]);

            return PDFToolsHelperMethods::createSuccessResponse(
                'HTML berhasil dikonversi ke PDF',
                $processing->id,
                $outputFileName,
                route('pdf-tools.download', $processing->id)
            );
        } catch (\Exception $e) {
            if ($processing) {
                $processing->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
            }

            Log::error('HTML to PDF conversion failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'HTML to PDF conversion failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function health()
    {
        try {
            $health = $this->gotenberg->health();

            return response()->json([
                'success' => true,
                'status' => 'up',
                'engine' => 'gotenberg',
                'details' => $health['details'] ?? [],
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'status' => 'down',
                'engine' => 'gotenberg',
                'message' => $e->getMessage(),
            ], Response::HTTP_SERVICE_UNAVAILABLE);
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
                'user_id' => $this->currentUserId(),
                'tool_name' => request()->input('tool', 'pdf-to-' . $format),
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
                'user_id' => $this->currentUserId(),
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
                'user_id' => $this->currentUserId(),
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
            // Set generous time limits for large PDFs
            $originalTimeLimit = ini_get('max_execution_time');
            ini_set('max_execution_time', 600); // 10 minutes for large PDFs
            ini_set('memory_limit', '2G');
            
            $inputPath = $file->store('pdf-tools/uploads');
            $fullInputPath = Storage::path($inputPath);
            
            $outputFileName = 'pdf_to_word_' . Str::uuid() . '.docx';
            $outputPath = 'pdf-tools/outputs/' . $outputFileName;
            
            $processing = PdfProcessing::create([
                'user_id' => $this->currentUserId(),
                'tool_name' => request()->input('tool', 'pdf-to-word'),
                'original_filename' => $file->getClientOriginalName(),
                'processed_filename' => $outputFileName,
                'file_size' => $file->getSize(),
                'status' => 'processing',
                'progress' => 0,
                'settings' => json_encode($settings)
            ]);

            // Try LibreOffice first, fallback to manual method if it fails
            $outputDir = Storage::path('pdf-tools/outputs');
            if (!is_dir($outputDir)) {
                mkdir($outputDir, 0755, true);
            }

            // Use the new enhanced multi-method conversion approach
            $finalPath = Storage::path($outputPath);
            $createdFilePath = PDFToolsHelperMethods::createEnhancedWordFromPdf($fullInputPath, $finalPath);
            
            // Check if the created file has a different extension (RTF)
            if (pathinfo($createdFilePath, PATHINFO_EXTENSION) === 'rtf') {
                // Update the processing record to reflect RTF format
                $rtfFileName = str_replace('.docx', '.rtf', $outputFileName);
                $processing->update([
                    'processed_filename' => $rtfFileName
                ]);
                
                // Update output path for final processing
                $outputPath = 'pdf-tools/outputs/' . $rtfFileName;
                $outputFileName = $rtfFileName;
                
                Log::info('Updated processing record for RTF format', [
                    'new_filename' => $rtfFileName
                ]);
            }
            
            Log::info('PDF to Word conversion completed using enhanced multi-method approach');
            
            Storage::delete($inputPath);
            
            $processing->update([
                'status' => 'completed',
                'progress' => 100,
                'completed_at' => now()
            ]);

            // Restore original time limit
            ini_set('max_execution_time', $originalTimeLimit);

            return PDFToolsHelperMethods::createSuccessResponse('PDF berhasil dikonversi ke Word', $processing->id, $outputFileName, route('pdf-tools.download', $processing->id));

        } catch (\Exception $e) {
            // Restore original time limit
            if (isset($originalTimeLimit)) {
                ini_set('max_execution_time', $originalTimeLimit);
            }
            
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
        try {
            return $this->convertOfficeDocumentToPdf(
                $file,
                'excel-to-pdf',
                'excel_to_pdf_',
                'Excel berhasil dikonversi ke PDF',
                $settings
            );
        } catch (\Exception $e) {
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
                'user_id' => $this->currentUserId(),
                'tool_name' => request()->input('tool', 'pdf-to-excel'),
                'original_filename' => $file->getClientOriginalName(),
                'processed_filename' => $outputFileName,
                'file_size' => $file->getSize(),
                'status' => 'processing',
                'progress' => 0,
                'settings' => json_encode($settings)
            ]);

            $inputPath = $file->store(config('pdftools.storage.uploads_path'));
            $fullInputPath = Storage::path($inputPath);
            
            // Try LibreOffice first, fallback to manual method if it fails
            $outputDir = Storage::path('pdf-tools/outputs');
            if (!is_dir($outputDir)) {
                mkdir($outputDir, 0755, true);
            }

            try {
                $convertedXlsxPath = PDFToolsHelperMethods::convertPdfToOfficeWithLibreOffice($fullInputPath, $outputDir, 'xlsx');
                
                $finalPath = Storage::path($outputPath);
                rename($convertedXlsxPath, $finalPath);
                
                Log::info('PDF to Excel conversion successful using LibreOffice');
            } catch (\Exception $e) {
                if (strpos($e->getMessage(), 'LibreOffice_PDF_Conversion_Failed') !== false) {
                    Log::info('LibreOffice failed, falling back to manual PDF to Excel conversion');
                    
                    // Fallback to manual conversion
                    $textContent = PDFToolsHelperMethods::extractTextFromPdf($fullInputPath);
                    PDFToolsHelperMethods::createExcelDocument($textContent, Storage::path($outputPath), $fullInputPath);
                    
                    Log::info('PDF to Excel conversion completed using manual fallback method');
                } else {
                    throw $e;
                }
            }
            
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
        try {
            return $this->convertOfficeDocumentToPdf(
                $file,
                'powerpoint-to-pdf',
                'powerpoint_to_pdf_',
                'PowerPoint berhasil dikonversi ke PDF',
                $settings
            );
        } catch (\Exception $e) {
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
                'user_id' => $this->currentUserId(),
                'tool_name' => request()->input('tool', 'pdf-to-powerpoint'),
                'original_filename' => $file->getClientOriginalName(),
                'processed_filename' => $outputFileName,
                'file_size' => $file->getSize(),
                'status' => 'processing',
                'progress' => 0,
                'settings' => json_encode($settings)
            ]);

            $inputPath = $file->store(config('pdftools.storage.uploads_path'));
            $fullInputPath = Storage::path($inputPath);
            
            // Try LibreOffice first, fallback to manual method if it fails
            $outputDir = Storage::path('pdf-tools/outputs');
            if (!is_dir($outputDir)) {
                mkdir($outputDir, 0755, true);
            }

            try {
                $convertedPptxPath = PDFToolsHelperMethods::convertPdfToOfficeWithLibreOffice($fullInputPath, $outputDir, 'pptx');
                
                $finalPath = Storage::path($outputPath);
                rename($convertedPptxPath, $finalPath);
                
                Log::info('PDF to PowerPoint conversion successful using LibreOffice');
            } catch (\Exception $e) {
                if (strpos($e->getMessage(), 'LibreOffice_PDF_Conversion_Failed') !== false) {
                    Log::info('LibreOffice failed, falling back to manual PDF to PowerPoint conversion');
                    
                    // Fallback to manual conversion
                    $textContent = PDFToolsHelperMethods::extractTextFromPdf($fullInputPath);
                    PDFToolsHelperMethods::createPowerPointDocument($textContent, Storage::path($outputPath), $fullInputPath);
                    
                    Log::info('PDF to PowerPoint conversion completed using manual fallback method');
                } else {
                    throw $e;
                }
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

    private function convertOfficeDocumentToPdf($file, string $toolName, string $outputPrefix, string $successMessage, array $settings = [])
    {
        $processing = null;

        try {
            $inputPath = $file->store(config('pdftools.storage.uploads_path'));
            $fullInputPath = Storage::path($inputPath);

            $outputFileName = $outputPrefix . Str::uuid() . '.pdf';
            $outputPath = config('pdftools.storage.outputs_path') . '/' . $outputFileName;

            $processing = PdfProcessing::create([
                'user_id' => $this->currentUserId(),
                'tool_name' => $toolName,
                'original_filename' => $file->getClientOriginalName(),
                'processed_filename' => $outputFileName,
                'file_size' => $file->getSize(),
                'status' => 'processing',
                'progress' => 0,
                'settings' => json_encode($settings),
            ]);

            $result = $this->gotenberg->officeToPdf([[
                'path' => $fullInputPath,
                'filename' => $file->getClientOriginalName(),
            ]], array_merge($settings, [
                'output_filename' => $outputFileName,
            ]));

            $this->storeGotenbergResult($result, $outputPath, [$inputPath]);

            $processing->update([
                'status' => 'completed',
                'progress' => 100,
                'processed_file_size' => Storage::size($outputPath),
                'completed_at' => now(),
            ]);

            return PDFToolsHelperMethods::createSuccessResponse(
                $successMessage,
                $processing->id,
                $outputFileName,
                route('pdf-tools.download', $processing->id)
            );
        } catch (\Exception $e) {
            if ($processing) {
                $processing->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
            }

            Log::error($toolName . ' conversion failed: ' . $e->getMessage());

            throw $e;
        }
    }

    private function storeGotenbergResult(array $result, string $storageOutputPath, array $cleanupStoragePaths = []): void
    {
        try {
            Storage::put($storageOutputPath, file_get_contents($result['path']));
        } finally {
            if (!empty($result['path']) && file_exists($result['path'])) {
                @unlink($result['path']);
            }

            foreach ($cleanupStoragePaths as $cleanupStoragePath) {
                Storage::delete($cleanupStoragePath);
            }
        }
    }

    private function prepareFilesForOrderedMerge(array $files, array &$storedInputPaths, string $mergeOrder = 'upload'): array
    {
        $items = [];

        foreach ($files as $index => $file) {
            $storedPath = $file->store(config('pdftools.storage.uploads_path'));
            $storedInputPaths[] = $storedPath;

            $items[] = [
                'path' => Storage::path($storedPath),
                'original_filename' => $file->getClientOriginalName(),
                'sort_index' => $index + 1,
            ];
        }

        if ($mergeOrder === 'name') {
            usort($items, fn($left, $right) => strcasecmp($left['original_filename'], $right['original_filename']));
        }

        foreach ($items as $index => &$item) {
            $item['filename'] = sprintf('%03d_%s', $index + 1, $item['original_filename']);
            unset($item['original_filename'], $item['sort_index']);
        }
        unset($item);

        return $items;
    }

    private function mapSplitSettings(array $settings): array
    {
        $mode = $settings['splitMode'] ?? null;

        if ($mode === 'range') {
            $startPage = (int) ($settings['startPage'] ?? 0);
            $endPage = (int) ($settings['endPage'] ?? 0);

            if ($startPage < 1 || $endPage < $startPage) {
                throw new Exception('Rentang halaman untuk split PDF tidak valid.');
            }

            return [
                'splitMode' => 'pages',
                'splitSpan' => $startPage . '-' . $endPage,
                'splitUnify' => true,
            ];
        }

        if ($mode === 'interval') {
            $interval = (int) ($settings['interval'] ?? 0);

            if ($interval < 1) {
                throw new Exception('Interval split PDF harus lebih besar dari 0.');
            }

            return [
                'splitMode' => 'intervals',
                'splitSpan' => (string) $interval,
                'splitUnify' => false,
            ];
        }

        throw new Exception('Mode split PDF belum dipilih.');
    }

    private function guardToolAvailability(string $tool): void
    {
        if (in_array($tool, self::DISABLED_LEGACY_TOOLS, true)) {
            throw ValidationException::withMessages([
                    'tool' => [
                    'Tool "' . $tool . '" saat ini belum diaktifkan. Tool yang masih dinonaktifkan: compress, rotate, watermark, dan page numbers.',
                ],
            ]);
        }
    }

    private function ensureGotenbergReadyFor(string $tool): void
    {
        if (!in_array($tool, self::GOTENBERG_SUPPORTED_TOOLS, true)) {
            return;
        }

        $this->gotenberg->health();
    }

    private function currentUserId(): ?int
    {
        return auth('web')->id() ?? auth()->id();
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
            case 'pdf-to-docx':
                return $cleanBaseName . '.docx';
            
            case 'pdf-to-excel':
            case 'pdf-to-xlsx':
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
                return $cleanBaseName . '_split.' . pathinfo($processing->processed_filename, PATHINFO_EXTENSION);
            
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
