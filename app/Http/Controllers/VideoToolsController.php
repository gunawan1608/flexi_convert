<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use App\Models\VideoProcessing;
use Illuminate\Support\Str;
use JsonException;
use Exception;

class VideoToolsController extends Controller
{
    public function process(Request $request)
    {
        try {
            // Force JSON response and disable any output buffering
            $request->headers->set('Accept', 'application/json');
            
            // Clear any previous output that might contaminate JSON
            if (ob_get_level()) {
                ob_clean();
            }
            
            // Enhanced validation with better error messages
            $validated = $request->validate([
                'tool' => 'required|string',
                'files' => 'required|array|min:1',
                'files.*' => 'required|file|max:512000', // 500MB max for videos
                'settings' => 'nullable|array'
            ]);

            $tool = $validated['tool'];
            $files = $validated['files'];
            
            // Enhanced file validation for specific tools
            $this->validateFileTypesForTool($tool, $files);
            
            $settings = $validated['settings'] ?? [];
            
            // Ensure settings is an array
            if (!is_array($settings)) {
                $settings = [];
            }
            
            Log::info('Settings received', [
                'settings' => $settings,
                'tool' => $tool
            ]);

            Log::info("Processing Video tool: {$tool}", [
                'files_count' => count($files),
                'settings' => $settings,
                'user_id' => auth()->id() ?? 'guest',
                'tool_mapping_debug' => "Tool '{$tool}' will be processed"
            ]);

            $results = [];
            foreach ($files as $index => $file) {
                try {
                    $result = $this->processVideoFile($file, $tool, $settings);
                    $results[] = $result;
                } catch (\Exception $fileError) {
                    Log::error("Failed to process file {$index}: " . $fileError->getMessage());
                    $results[] = [
                        'id' => null,
                        'filename' => $file->getClientOriginalName(),
                        'status' => 'failed',
                        'error' => $fileError->getMessage()
                    ];
                }
            }

            // Ensure clean JSON response
            return response()->json([
                'success' => true,
                'message' => 'Pemrosesan video selesai',
                'results' => $results
            ])->header('Content-Type', 'application/json');

        } catch (\Illuminate\Validation\ValidationException $e) {
            // Clear any output buffer before sending JSON
            if (ob_get_level()) {
                ob_clean();
            }
            
            Log::error("Video validation error", [
                'errors' => $e->errors(),
                'input' => $request->except(['files'])
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $e->errors()
            ], 422)->header('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            // Clear any output buffer before sending JSON
            if (ob_get_level()) {
                ob_clean();
            }
            
            Log::error("Video processing critical error", [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan sistem: ' . $e->getMessage(),
                'debug' => config('app.debug') ? [
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ] : null
            ], 500)->header('Content-Type', 'application/json');
        }
    }

    /**
     * Validate file types for specific video conversion tools
     */
    private function validateFileTypesForTool($tool, $files)
    {
        $validationRules = [
            // Format Conversion tools
            'mp4-to-avi' => ['mp4'],
            'avi-to-mp4' => ['avi'],
            'mkv-to-mp4' => ['mkv'],
            'mov-to-mp4' => ['mov'],
            'mp4-to-webm' => ['mp4'],
            'webm-to-mp4' => ['webm'],
            'video-to-gif' => ['mp4', 'avi', 'mkv', 'mov', 'webm'],
            
            // Video Optimization tools
            'compress-video' => ['mp4', 'avi', 'mkv', 'mov', 'webm'],
            'change-resolution' => ['mp4', 'avi', 'mkv', 'mov', 'webm'],
            'change-bitrate' => ['mp4', 'avi', 'mkv', 'mov', 'webm'],
            'change-fps' => ['mp4', 'avi', 'mkv', 'mov', 'webm']
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
            'mp4' => ['video/mp4', 'application/octet-stream'],
            'avi' => ['video/x-msvideo', 'video/avi', 'application/octet-stream'],
            'mkv' => ['video/x-matroska', 'application/octet-stream'],
            'mov' => ['video/quicktime', 'application/octet-stream'],
            'webm' => ['video/webm', 'application/octet-stream'],
            'wmv' => ['video/x-ms-wmv', 'application/octet-stream'],
            'flv' => ['video/x-flv', 'application/octet-stream']
        ];

        $validMimeTypes = [];
        foreach ($extensions as $ext) {
            if (isset($mimeMap[$ext])) {
                $validMimeTypes = array_merge($validMimeTypes, $mimeMap[$ext]);
            }
        }

        return array_unique($validMimeTypes);
    }

    private function processVideoFile($file, $tool, $settings)
    {
        $originalName = $file->getClientOriginalName();
        $extension = $file->getClientOriginalExtension();
        $filename = pathinfo($originalName, PATHINFO_FILENAME);
        $uniqueId = Str::uuid();
        
        Log::info("Starting video processing", [
            'file' => $originalName,
            'tool' => $tool,
            'size' => $file->getSize()
        ]);
        
        // Create database record
        $processing = VideoProcessing::create([
            'user_id' => auth()->id() ?? 1,
            'tool_name' => $tool,
            'original_filename' => $originalName,
            'original_path' => 'video-tools/inputs/' . $uniqueId . '.' . $extension,
            'original_file_size' => $file->getSize(),
            'status' => 'processing',
            'progress' => 0,
            'processing_settings' => json_encode($settings),
            'started_at' => now()
        ]);

        try {
            // Store input file
            $inputPath = 'video-tools/inputs/' . $uniqueId . '.' . $extension;
            Storage::put($inputPath, $file->get());
            Log::info("Input file stored", ['path' => $inputPath]);

            // Process based on tool type
            Log::info("About to process with tool", ['tool' => $tool, 'input_path' => $inputPath]);
            $outputPath = $this->processWithTool($inputPath, $tool, $settings, $uniqueId, $filename);
            $outputFilename = basename($outputPath);
            Log::info("File processed successfully", [
                'tool' => $tool,
                'output_path' => $outputPath,
                'output_filename' => $outputFilename
            ]);

            // Update processing record
            $processing->update([
                'converted_filename' => $outputFilename,
                'converted_path' => $outputPath,
                'converted_file_size' => Storage::size($outputPath),
                'status' => 'completed',
                'progress' => 100,
                'completed_at' => now()
            ]);

            Log::info("Processing completed successfully", ['id' => $processing->id]);

            return [
                'id' => $processing->id,
                'filename' => $originalName,
                'status' => 'completed',
                'download_url' => route('video-tools.download', ['id' => $processing->id]),
                'output_path' => $outputPath,
                'output_filename' => $outputFilename
            ];

        } catch (\Exception $e) {
            Log::error("Processing failed for {$originalName}: " . $e->getMessage(), [
                'tool' => $tool,
                'file' => $originalName,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $processing->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at' => now()
            ]);

            return [
                'id' => $processing->id,
                'filename' => $originalName,
                'status' => 'failed',
                'error' => $e->getMessage()
            ];
        }
    }

    private function processWithTool($inputPath, $tool, $settings, $uniqueId, $filename)
    {
        $tempInputPath = storage_path('app/' . $inputPath);
        
        // Determine output extension based on tool
        $outputExtension = $this->getOutputExtension($tool, $settings);
        
        // Generate output filename with correct extension
        $outputFilename = $uniqueId . '_output.' . $outputExtension;
        $outputPath = 'video-tools/outputs/' . $outputFilename;
        
        Log::info("Output path determined", [
            'tool' => $tool,
            'output_extension' => $outputExtension,
            'output_path' => $outputPath
        ]);
        $tempOutputPath = storage_path('app/' . $outputPath);
        
        // Ensure output directory exists
        $outputDir = dirname($tempOutputPath);
        if (!file_exists($outputDir)) {
            mkdir($outputDir, 0755, true);
        }
        
        // Process video with FFmpeg
        $success = $this->processVideo($tempInputPath, $tempOutputPath, $tool, $settings);
        
        // Verify output file exists and has content
        if (!file_exists($tempOutputPath) || filesize($tempOutputPath) === 0) {
            throw new \Exception("Output file was not created or is empty: {$tempOutputPath}");
        }
        
        Log::info("Video processing completed", [
            'tool' => $tool,
            'input_extension' => pathinfo($tempInputPath, PATHINFO_EXTENSION),
            'output_extension' => $outputExtension,
            'output_size' => filesize($tempOutputPath)
        ]);
        
        return $outputPath;
    }

    private function processVideo($inputPath, $outputPath, $tool, $settings)
    {
        try {
            Log::info('Starting video processing', [
                'tool' => $tool,
                'input_exists' => file_exists($inputPath),
                'input_size' => file_exists($inputPath) ? filesize($inputPath) : 0
            ]);

            // Check if FFmpeg is available
            $ffmpegAvailable = $this->checkFFmpegAvailable();
            
            if (!$ffmpegAvailable) {
                Log::warning('FFmpeg not available, using fallback processing');
                return $this->fallbackVideoProcessing($inputPath, $outputPath);
            }

            // Build FFmpeg command based on tool
            $command = $this->buildFFmpegCommand($inputPath, $outputPath, $tool, $settings);
            
            Log::info('Executing FFmpeg command', ['command' => $command]);
            
            // Execute command
            $output = [];
            $returnCode = 0;
            exec($command . ' 2>&1', $output, $returnCode);
            
            Log::info('FFmpeg execution completed', [
                'return_code' => $returnCode,
                'output' => implode("\n", $output),
                'output_exists' => file_exists($outputPath),
                'output_size' => file_exists($outputPath) ? filesize($outputPath) : 0
            ]);

            return $returnCode === 0 && file_exists($outputPath);

        } catch (\Exception $e) {
            Log::error('Video processing exception', ['error' => $e->getMessage()]);
            return $this->fallbackVideoProcessing($inputPath, $outputPath);
        }
    }

    private function checkFFmpegAvailable()
    {
        $output = [];
        $returnCode = 0;
        exec('ffmpeg -version 2>&1', $output, $returnCode);
        return $returnCode === 0;
    }

    private function buildFFmpegCommand($inputPath, $outputPath, $tool, $settings)
    {
        // Handle special case for video-to-gif first
        if ($tool === 'video-to-gif') {
            return "ffmpeg -i \"$inputPath\" -vf \"fps=10,scale=320:-1:flags=lanczos\" -y \"$outputPath\"";
        }

        // Base command
        $command = "ffmpeg -i \"$inputPath\"";

        // Tool-specific handling
        switch ($tool) {
            case 'compress-video':
                $bitrate = $settings['bitrate'] ?? '2000';
                $resolution = $settings['resolution'] ?? 'original';
                $frameRate = $settings['frameRate'] ?? 'original';
                
                $command .= " -c:v libx264 -preset fast -crf 28";
                
                if ($bitrate !== 'original') {
                    $command .= " -b:v {$bitrate}k";
                }
                
                if ($resolution !== 'original') {
                    $resolutionMap = [
                        '480p' => '854:480',
                        '720p' => '1280:720',
                        '1080p' => '1920:1080',
                        '1440p' => '2560:1440',
                        '2160p' => '3840:2160'
                    ];
                    if (isset($resolutionMap[$resolution])) {
                        $command .= " -vf scale={$resolutionMap[$resolution]}";
                    }
                }
                
                if ($frameRate !== 'original') {
                    $command .= " -r {$frameRate}";
                }
                break;
                
            case 'change-resolution':
                $resolution = $settings['resolution'] ?? '720p';
                $resolutionMap = [
                    '480p' => '854:480',
                    '720p' => '1280:720',
                    '1080p' => '1920:1080',
                    '1440p' => '2560:1440',
                    '2160p' => '3840:2160'
                ];
                
                $command .= " -c:v libx264";
                if ($resolution !== 'original' && isset($resolutionMap[$resolution])) {
                    $command .= " -vf scale={$resolutionMap[$resolution]}";
                }
                break;
                
            case 'change-bitrate':
                $bitrate = $settings['bitrate'] ?? '2000';
                $command .= " -c:v libx264";
                if ($bitrate !== 'original') {
                    $command .= " -b:v {$bitrate}k";
                }
                break;
                
            case 'change-fps':
                $frameRate = $settings['frameRate'] ?? '30';
                $command .= " -c:v libx264";
                if ($frameRate !== 'original') {
                    $command .= " -r {$frameRate}";
                }
                break;
                
            // Format conversion tools
            case 'mp4-to-avi':
            case 'avi-to-mp4':
            case 'mkv-to-mp4':
            case 'mov-to-mp4':
            case 'webm-to-mp4':
            case 'mp4-to-webm':
                $command .= " -c:v libx264 -preset medium -crf 23";
                break;
                
            default:
                $command .= " -c:v libx264 -preset medium -crf 23";
                break;
        }

        // Audio codec for all tools except GIF
        $command .= " -c:a aac -b:a 128k";

        // Output file
        $command .= " -y \"$outputPath\"";

        return $command;
    }

    private function fallbackVideoProcessing($inputPath, $outputPath)
    {
        Log::info('Using fallback video processing (file copy)');
        return copy($inputPath, $outputPath);
    }

    private function getOutputExtension($tool, $settings)
    {
        // Determine output format based on tool
        switch ($tool) {
            case 'mp4-to-avi':
                return 'avi';
            case 'avi-to-mp4':
            case 'mkv-to-mp4':
            case 'mov-to-mp4':
            case 'webm-to-mp4':
                return 'mp4';
            case 'mp4-to-webm':
                return 'webm';
            case 'video-to-gif':
                return 'gif';
            case 'compress-video':
            case 'change-resolution':
            case 'change-bitrate':
            case 'change-fps':
                return 'mp4'; // Keep original format as MP4 for optimization tools
            default:
                return $settings['format'] ?? 'mp4'; // Default to MP4
        }
    }

    public function download($id)
    {
        try {
            Log::info('Video download requested', ['id' => $id]);
            
            $processing = VideoProcessing::findOrFail($id);
            
            if ($processing->status !== 'completed' || !$processing->converted_filename) {
                Log::error('Video file not ready for download', [
                    'id' => $id,
                    'status' => $processing->status,
                    'converted_filename' => $processing->converted_filename
                ]);
                return response()->json(['error' => 'File not ready for download'], 404);
            }

            $filePath = storage_path('app/' . $processing->converted_path);
            
            if (!file_exists($filePath)) {
                Log::error('Video file not found for download', [
                    'id' => $id,
                    'converted_path' => $processing->converted_path,
                    'file_path' => $filePath
                ]);
                return response()->json(['error' => 'File not found'], 404);
            }

            $originalName = $processing->original_filename;
            $extension = pathinfo($processing->converted_filename, PATHINFO_EXTENSION);
            $downloadName = pathinfo($originalName, PATHINFO_FILENAME) . '_processed.' . $extension;

            Log::info('Video download starting', [
                'path' => $filePath,
                'download_name' => $downloadName,
                'file_exists' => file_exists($filePath),
                'file_size' => file_exists($filePath) ? filesize($filePath) : 0
            ]);

            return response()->download($filePath, $downloadName);

        } catch (\Exception $e) {
            Log::error('Video download error', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Download failed'], 500);
        }
    }

}
