<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Models\Conversion;
use App\Services\DocumentConversionService;

class DocumentController extends Controller
{
    public function index()
    {
        return view('tools.documents');
    }

    public function upload(Request $request)
    {
        // Debug: Log all request data
        \Log::info('Upload request data:', [
            'all_data' => $request->all(),
            'files' => $request->hasFile('files') ? 'Files present' : 'No files',
            'files_count' => $request->hasFile('files') ? count($request->file('files')) : 0,
            'all_files' => $request->files->all(),
            'content_type' => $request->header('Content-Type')
        ]);

        // Temporary: Skip validation to test if files are received
        if (!$request->hasFile('files')) {
            return response()->json([
                'success' => false,
                'message' => 'No files received',
                'debug' => [
                    'request_all' => $request->all(),
                    'files_all' => $request->files->all(),
                    'input_all' => $request->input()
                ]
            ], 422);
        }

        // Continue with actual file processing and conversion creation
        $conversions = [];
        $files = $request->file('files');

        foreach ($files as $file) {
            try {
                // Generate unique filename
                $originalName = $file->getClientOriginalName();
                $extension = $file->getClientOriginalExtension();
                $filename = time() . '_' . Str::random(10) . '_' . $originalName;
                $path = $file->storeAs('uploads/documents', $filename, 'private');
                
                // Create conversion record
                $conversion = Conversion::create([
                    'user_id' => auth()->id(),
                    'conversion_type' => 'document',
                    'original_filename' => $originalName,
                    'stored_filename' => $filename,
                    'file_path' => $path,
                    'original_extension' => $extension,
                    'target_format' => $request->target_format,
                    'target_extension' => $request->target_format,
                    'file_size' => $file->getSize(),
                    'status' => 'pending',
                    'progress' => 0,
                    'settings' => [
                        'quality' => $request->quality ?? 'medium',
                        'page_range' => $request->page_range,
                        'preserve_formatting' => (bool) $request->input('preserve_formatting'),
                        'optimize_images' => (bool) $request->input('optimize_images'),
                        'embed_fonts' => (bool) $request->input('embed_fonts')
                    ]
                ]);
                
                // Process conversion synchronously for development
                try {
                    $this->processConversion($conversion);
                } catch (\Exception $conversionError) {
                    \Log::error('Conversion processing error:', [
                        'conversion_id' => $conversion->id,
                        'error' => $conversionError->getMessage(),
                        'trace' => $conversionError->getTraceAsString()
                    ]);
                    
                    $conversion->update([
                        'status' => 'failed',
                        'error_message' => $conversionError->getMessage()
                    ]);
                }
                
                $conversions[] = [
                    'id' => $conversion->id,
                    'filename' => $originalName,
                    'target_format' => $request->target_format,
                    'status' => $conversion->fresh()->status,
                    'progress' => $conversion->fresh()->progress ?? 0,
                    'download_url' => $conversion->fresh()->status === 'completed' ? route('documents.download', $conversion->id) : null,
                    'created_at' => $conversion->created_at->toISOString()
                ];
                
            } catch (\Exception $e) {
                \Log::error('File upload error:', [
                    'file' => $originalName ?? 'unknown',
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to upload file: ' . ($originalName ?? 'unknown'),
                    'error' => $e->getMessage(),
                    'debug' => [
                        'line' => $e->getLine(),
                        'file' => $e->getFile()
                    ]
                ], 500);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Files uploaded and conversion started',
            'conversions' => $conversions
        ]);
    }

    public function convert(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'conversion_ids' => 'required|array',
            'conversion_ids.*' => 'exists:conversions,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $conversions = Conversion::whereIn('id', $request->conversion_ids)
            ->where('user_id', auth()->id())
            ->get();

        foreach ($conversions as $conversion) {
            $conversion->update(['status' => 'processing', 'started_at' => now()]);
            
            // Process conversion synchronously
            $this->processConversion($conversion);
        }

        return response()->json([
            'success' => true,
            'message' => 'Conversion started',
            'conversions' => $conversions->map(function($conversion) {
                return [
                    'id' => $conversion->id,
                    'filename' => $conversion->original_filename,
                    'status' => $conversion->status,
                    'progress' => 0
                ];
            })
        ]);
    }

    public function status($id)
    {
        $conversion = Conversion::where('id', $id)
            ->where('user_id', auth()->id())
            ->first();

        if (!$conversion) {
            return response()->json([
                'success' => false,
                'message' => 'Conversion not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'conversion' => [
                'id' => $conversion->id,
                'filename' => $conversion->original_filename,
                'status' => $conversion->status,
                'progress' => $conversion->progress ?? 0,
                'download_url' => $conversion->status === 'completed' ? route('documents.download', $conversion->id) : null,
                'created_at' => $conversion->created_at->format('Y-m-d H:i:s')
            ]
        ]);
    }

    public function download($id)
    {
        $conversion = Conversion::where('id', $id)
            ->where('user_id', auth()->id())
            ->where('status', 'completed')
            ->first();

        if (!$conversion) {
            abort(404, 'File not found or conversion not completed');
        }

        $filePath = $conversion->converted_file_path;
        
        if (!Storage::disk('private')->exists($filePath)) {
            abort(404, 'Converted file not found');
        }

        $filename = pathinfo($conversion->original_filename, PATHINFO_FILENAME) . '.' . $conversion->target_extension;

        return Storage::disk('private')->download($filePath, $filename);
    }

    public function history()
    {
        $conversions = Conversion::where('user_id', auth()->id())
            ->where('conversion_type', 'document')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'conversions' => $conversions->map(function($conversion) {
                return [
                    'id' => $conversion->id,
                    'original_filename' => $conversion->original_filename,
                    'original_extension' => strtoupper($conversion->original_extension),
                    'target_extension' => strtoupper($conversion->target_extension),
                    'file_size_human' => $this->formatFileSize($conversion->file_size),
                    'status' => $conversion->status,
                    'created_at' => $conversion->created_at->format('M j, Y H:i'),
                    'download_url' => $conversion->status === 'completed' ? route('documents.download', $conversion->id) : null
                ];
            }),
            'pagination' => [
                'current_page' => $conversions->currentPage(),
                'last_page' => $conversions->lastPage(),
                'total' => $conversions->total()
            ]
        ]);
    }

    private function processConversion($conversion)
    {
        try {
            $conversion->update(['status' => 'processing', 'progress' => 10]);
            
            $conversionService = new DocumentConversionService();
            
            // Get file paths
            $inputPath = storage_path('app/private/' . $conversion->file_path);
            $outputDir = storage_path('app/private/converted/documents/');
            
            if (!file_exists($outputDir)) {
                mkdir($outputDir, 0755, true);
            }
            
            $outputFilename = Str::uuid() . '.' . $conversion->target_extension;
            $outputPath = $outputDir . $outputFilename;
            
            $conversion->update(['progress' => 30]);
            
            // Use DocumentConversionService for actual conversion
            $result = $conversionService->convertDocument(
                $inputPath,
                $outputPath,
                $conversion->original_extension,
                $conversion->target_extension,
                $conversion->settings ?? []
            );
            
            $conversion->update(['progress' => 80]);
            
            if ($result['success']) {
                $conversion->update([
                    'status' => 'completed',
                    'progress' => 100,
                    'converted_file_path' => 'converted/documents/' . $outputFilename,
                    'converted_filename' => $outputFilename,
                    'completed_at' => now()
                ]);
            } else {
                $conversion->update([
                    'status' => 'failed',
                    'error_message' => $result['error'] ?? 'Conversion failed'
                ]);
            }
            
        } catch (\Exception $e) {
            $conversion->update([
                'status' => 'failed',
                'error_message' => $e->getMessage()
            ]);
        }
    }

    private function formatFileSize($bytes)
    {
        if ($bytes === 0) return '0 Bytes';
        
        $k = 1024;
        $sizes = ['Bytes', 'KB', 'MB', 'GB'];
        $i = floor(log($bytes) / log($k));
        
        return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
    }
}
