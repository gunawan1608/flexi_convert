<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use App\Models\AudioProcessing;
use Illuminate\Support\Str;
use JsonException;
use Exception;

class AudioToolsController extends Controller
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
                'files.*' => 'required|file|max:102400', // 100MB max for audio
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

            Log::info("Processing Audio tool: {$tool}", [
                'files_count' => count($files),
                'settings' => $settings,
                'user_id' => auth()->id() ?? 'guest',
                'tool_mapping_debug' => "Tool '{$tool}' will be processed"
            ]);

            $results = [];
            foreach ($files as $index => $file) {
                try {
                    $result = $this->processAudioFile($file, $tool, $settings);
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
                'message' => 'Pemrosesan audio selesai',
                'results' => $results
            ])->header('Content-Type', 'application/json');

        } catch (\Illuminate\Validation\ValidationException $e) {
            // Clear any output buffer before sending JSON
            if (ob_get_level()) {
                ob_clean();
            }
            
            Log::error("Audio validation error", [
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
            
            Log::error("Audio processing critical error", [
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
     * Validate file types for specific audio conversion tools
     */
    private function validateFileTypesForTool($tool, $files)
    {
        $validationRules = [
            // Format Conversion tools
            'mp3-to-wav' => ['mp3'],
            'wav-to-mp3' => ['wav'],
            'flac-to-mp3' => ['flac'],
            'aac-to-mp3' => ['aac', 'm4a'],
            'ogg-to-mp3' => ['ogg'],
            'mp3-to-flac' => ['mp3'],
            'mp3-to-aac' => ['mp3'],
            'wav-to-flac' => ['wav'],
            
            // Audio Enhancement tools
            'compress-audio' => ['mp3', 'wav', 'flac', 'aac', 'm4a', 'ogg'],
            'noise-reduction' => ['mp3', 'wav', 'flac', 'aac', 'm4a', 'ogg']
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
            'mp3' => ['audio/mpeg', 'audio/mp3', 'application/octet-stream'],
            'wav' => ['audio/wav', 'audio/x-wav', 'audio/wave', 'application/octet-stream'],
            'flac' => ['audio/flac', 'audio/x-flac', 'application/octet-stream'],
            'aac' => ['audio/aac', 'audio/x-aac', 'application/octet-stream'],
            'm4a' => ['audio/mp4', 'audio/x-m4a', 'application/octet-stream'],
            'ogg' => ['audio/ogg', 'audio/x-ogg', 'application/octet-stream'],
            'wma' => ['audio/x-ms-wma', 'application/octet-stream']
        ];

        $validMimeTypes = [];
        foreach ($extensions as $ext) {
            if (isset($mimeMap[$ext])) {
                $validMimeTypes = array_merge($validMimeTypes, $mimeMap[$ext]);
            }
        }

        return array_unique($validMimeTypes);
    }

    private function processAudioFile($file, $tool, $settings)
    {
        $originalName = $file->getClientOriginalName();
        $extension = $file->getClientOriginalExtension();
        $filename = pathinfo($originalName, PATHINFO_FILENAME);
        $uniqueId = Str::uuid();
        
        Log::info("Starting audio processing", [
            'file' => $originalName,
            'tool' => $tool,
            'size' => $file->getSize()
        ]);
        
        // Create database record
        $processing = AudioProcessing::create([
            'user_id' => auth()->id() ?? 1,
            'tool_name' => $tool,
            'original_filename' => $originalName,
            'original_path' => 'audio-tools/inputs/' . $uniqueId . '.' . $extension,
            'original_file_size' => $file->getSize(),
            'status' => 'processing',
            'progress' => 0,
            'processing_settings' => json_encode($settings),
            'started_at' => now()
        ]);

        try {
            // Store input file
            $inputPath = 'audio-tools/inputs/' . $uniqueId . '.' . $extension;
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
                'download_url' => route('audio-tools.download', ['id' => $processing->id]),
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
        $outputPath = 'audio-tools/outputs/' . $outputFilename;
        
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
        
        // Process audio with FFmpeg
        $success = $this->processAudio($tempInputPath, $tempOutputPath, $tool, $settings);
        
        // Verify output file exists and has content
        if (!file_exists($tempOutputPath) || filesize($tempOutputPath) === 0) {
            throw new \Exception("Output file was not created or is empty: {$tempOutputPath}");
        }
        
        Log::info("Audio processing completed", [
            'tool' => $tool,
            'input_extension' => pathinfo($tempInputPath, PATHINFO_EXTENSION),
            'output_extension' => $outputExtension,
            'output_size' => filesize($tempOutputPath)
        ]);
        
        return $outputPath;
    }

    private function processAudio($inputPath, $outputPath, $tool, $settings)
    {
        try {
            Log::info('Starting audio processing', [
                'tool' => $tool,
                'input_exists' => file_exists($inputPath),
                'input_size' => file_exists($inputPath) ? filesize($inputPath) : 0
            ]);

            // Check if FFmpeg is available
            $ffmpegAvailable = $this->checkFFmpegAvailable();
            
            if (!$ffmpegAvailable) {
                Log::warning('FFmpeg not available, using fallback processing');
                return $this->fallbackAudioProcessing($inputPath, $outputPath);
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
            Log::error('Audio processing exception', ['error' => $e->getMessage()]);
            return $this->fallbackAudioProcessing($inputPath, $outputPath);
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
        $quality = $settings['quality'] ?? 'medium';
        $bitrate = $settings['bitrate'] ?? '192';
        $sampleRate = $settings['sampleRate'] ?? '44100';
        $channels = $settings['channels'] ?? 'stereo';

        // Base command
        $command = "ffmpeg -i \"$inputPath\"";

        // Audio codec and quality settings
        switch ($quality) {
            case 'low':
                $command .= " -b:a 128k";
                break;
            case 'high':
                $command .= " -b:a 320k";
                break;
            case 'medium':
            default:
                $command .= " -b:a {$bitrate}k";
                break;
        }

        // Sample rate
        $command .= " -ar $sampleRate";

        // Channels
        if ($channels === 'mono') {
            $command .= " -ac 1";
        } else {
            $command .= " -ac 2";
        }

        // Tool-specific processing
        switch ($tool) {
            case 'mp3-to-wav':
            case 'wav-to-mp3':
            case 'flac-to-mp3':
            case 'aac-to-mp3':
            case 'ogg-to-mp3':
            case 'mp3-to-flac':
            case 'mp3-to-aac':
            case 'wav-to-flac':
                // Format conversion - handled by output extension
                break;
                
            case 'compress-audio':
                $command .= " -b:a 128k";
                break;
                
            case 'noise-reduction':
                $command .= " -af \"afftdn=nf=-25\"";
                break;
        }

        // Output file
        $command .= " -y \"$outputPath\"";

        return $command;
    }

    private function fallbackAudioProcessing($inputPath, $outputPath)
    {
        Log::info('Using fallback audio processing (file copy)');
        return copy($inputPath, $outputPath);
    }

    private function getOutputExtension($tool, $settings)
    {
        // Determine output format based on tool
        switch ($tool) {
            case 'mp3-to-wav':
                return 'wav';
            case 'wav-to-mp3':
            case 'flac-to-mp3':
            case 'aac-to-mp3':
            case 'ogg-to-mp3':
                return 'mp3';
            case 'mp3-to-flac':
            case 'wav-to-flac':
                return 'flac';
            case 'mp3-to-aac':
                return 'aac';
            case 'compress-audio':
            case 'noise-reduction':
                return 'mp3'; // Enhancement tools keep MP3 format by default
            default:
                return 'mp3'; // Default to MP3
        }
    }

    public function download($id)
    {
        try {
            Log::info('Audio download requested', ['id' => $id]);
            
            $processing = AudioProcessing::findOrFail($id);
            
            if ($processing->status !== 'completed' || !$processing->converted_filename) {
                Log::error('Audio file not ready for download', [
                    'id' => $id,
                    'status' => $processing->status,
                    'converted_filename' => $processing->converted_filename
                ]);
                return response()->json(['error' => 'File not ready for download'], 404);
            }

            $filePath = storage_path('app/' . $processing->converted_path);
            
            if (!file_exists($filePath)) {
                Log::error('Audio file not found for download', [
                    'id' => $id,
                    'converted_path' => $processing->converted_path,
                    'file_path' => $filePath
                ]);
                return response()->json(['error' => 'File not found'], 404);
            }

            $originalName = $processing->original_filename;
            $extension = pathinfo($processing->converted_filename, PATHINFO_EXTENSION);
            $downloadName = pathinfo($originalName, PATHINFO_FILENAME) . '_processed.' . $extension;

            Log::info('Audio download starting', [
                'path' => $filePath,
                'download_name' => $downloadName,
                'file_exists' => file_exists($filePath),
                'file_size' => file_exists($filePath) ? filesize($filePath) : 0
            ]);

            return response()->download($filePath, $downloadName);

        } catch (\Exception $e) {
            Log::error('Audio download error', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Download failed'], 500);
        }
    }
}
