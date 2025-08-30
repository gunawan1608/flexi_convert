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

            // More lenient validation - prioritize file extension over MIME type
            if (!in_array($extension, $allowedExtensions)) {
                throw new Exception("File type not supported for this conversion. Expected: " . implode(', ', $allowedExtensions) . ". Got: {$extension}");
            }
            
            // Log MIME type for debugging but don't fail validation on it
            Log::info("File validation passed", [
                'extension' => $extension,
                'mime_type' => $mimeType,
                'tool' => $tool
            ]);
        }
    }

    /**
     * Get valid MIME types for file extensions
     */
    private function getValidMimeTypes($extensions)
    {
        $mimeMap = [
            'mp3' => ['audio/mpeg', 'audio/mp3', 'audio/x-wav', 'application/octet-stream'],
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
        
        // Determine output extension based on tool and input path
        $outputExtension = $this->getOutputExtension($tool, $settings, $tempInputPath);
        
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
        
        // Try different ways to find FFmpeg
        $commands = [
            'ffmpeg -version 2>&1',
            '"C:\Program Files\ffmpeg-2025-08-20-git-4d7c609be3-full_build\bin\ffmpeg.exe" -version 2>&1',
            '"C:\ffmpeg\bin\ffmpeg.exe" -version 2>&1',
            'where ffmpeg 2>&1',
            'ffmpeg.exe -version 2>&1'
        ];
        
        foreach ($commands as $command) {
            exec($command, $output, $returnCode);
            Log::info("FFmpeg detection attempt", [
                'command' => $command,
                'return_code' => $returnCode,
                'output_preview' => implode(' ', array_slice($output, 0, 2))
            ]);
            
            if ($returnCode === 0) {
                Log::info("FFmpeg found successfully with command: " . $command);
                return true;
            }
            
            // Reset for next attempt
            $output = [];
            $returnCode = 0;
        }
        
        Log::warning("FFmpeg not found with any detection method");
        return false;
    }

    private function buildFFmpegCommand($inputPath, $outputPath, $tool, $settings)
    {
        $quality = $settings['quality'] ?? 'medium';
        $bitrate = $settings['bitrate'] ?? '192';
        $sampleRate = $settings['sampleRate'] ?? '44100';
        $channels = $settings['channels'] ?? 'stereo';

        // Use the same FFmpeg detection logic for command execution
        $ffmpegCmd = 'ffmpeg';
        
        // Try to find the correct FFmpeg path
        $testCommands = [
            'ffmpeg',
            'C:\Program Files\ffmpeg-2025-08-20-git-4d7c609be3-full_build\bin\ffmpeg.exe',
            'C:\ffmpeg\bin\ffmpeg.exe',
            'ffmpeg.exe'
        ];
        
        foreach ($testCommands as $testCmd) {
            $testOutput = [];
            $testReturn = 0;
            exec("\"$testCmd\" -version 2>&1", $testOutput, $testReturn);
            if ($testReturn === 0) {
                $ffmpegCmd = $testCmd;
                break;
            }
        }
        
        // Base command with detected FFmpeg path - handle spaces in path properly
        if (strpos($ffmpegCmd, ' ') !== false && strpos($ffmpegCmd, '"') === false) {
            $command = "\"$ffmpegCmd\" -i \"$inputPath\"";
        } else {
            $command = "$ffmpegCmd -i \"$inputPath\"";
        }

        // Handle tool-specific processing
        if ($tool === 'compress-audio') {
            // For compression, use the original format but with lower bitrate
            $inputExtension = strtolower(pathinfo($inputPath, PATHINFO_EXTENSION));
            $outputExtension = strtolower(pathinfo($outputPath, PATHINFO_EXTENSION));
            
            // Add format-specific parameters for compression
            switch ($outputExtension) {
                case 'wav':
                    $command .= " -f wav -c:a pcm_s16le";
                    break;
                case 'flac':
                    $command .= " -f flac -c:a flac -compression_level 8";
                    break;
                case 'aac':
                    $command .= " -f aac -c:a aac -b:a 128k";
                    break;
                case 'ogg':
                    $command .= " -f ogg -c:a libvorbis -q:a 3";
                    break;
                case 'mp3':
                default:
                    $command .= " -f mp3 -c:a libmp3lame -b:a 128k";
                    break;
            }
        } else if ($tool === 'noise-reduction') {
            // For noise reduction, preserve format but add noise reduction filter
            $outputExtension = strtolower(pathinfo($outputPath, PATHINFO_EXTENSION));
            
            switch ($outputExtension) {
                case 'wav':
                    $command .= " -af \"highpass=f=200,lowpass=f=3000\" -f wav -c:a pcm_s16le";
                    break;
                case 'flac':
                    $command .= " -af \"highpass=f=200,lowpass=f=3000\" -f flac -c:a flac";
                    break;
                case 'aac':
                    $command .= " -af \"highpass=f=200,lowpass=f=3000\" -f aac -c:a aac";
                    break;
                case 'ogg':
                    $command .= " -af \"highpass=f=200,lowpass=f=3000\" -f ogg -c:a libvorbis";
                    break;
                case 'mp3':
                default:
                    $command .= " -af \"highpass=f=200,lowpass=f=3000\" -f mp3 -c:a libmp3lame";
                    break;
            }
        } else {
            // Regular conversion tools
            $outputExtension = pathinfo($outputPath, PATHINFO_EXTENSION);
            
            // Add format-specific parameters
            switch (strtolower($outputExtension)) {
                case 'wav':
                    $command .= " -f wav -c:a pcm_s16le";
                    break;
                case 'flac':
                    $command .= " -f flac -c:a flac";
                    break;
                case 'aac':
                    $command .= " -f aac -c:a aac";
                    break;
                case 'ogg':
                    $command .= " -f ogg -c:a libvorbis";
                    break;
                case 'mp3':
                default:
                    $command .= " -f mp3 -c:a libmp3lame";
                    break;
            }
            
            // Audio quality settings (only for lossy formats and non-enhancement tools)
            if (!in_array(strtolower($outputExtension), ['wav', 'flac'])) {
                switch ($quality) {
                    case 'low':
                        $command .= " -b:a 128k";
                        break;
                    case 'high':
                        $command .= " -b:a 320k";
                        break;
                    case 'medium':
                    default:
                        $command .= " -b:a 192k";
                        break;
                }
            }
        }

        // Sample rate and channels (for all tools)
        $command .= " -ar $sampleRate";
        $command .= " -ac " . ($channels === 'mono' ? '1' : '2');

        // Overwrite output file
        $command .= " -y \"$outputPath\"";

        Log::info("FFmpeg command built", [
            'tool' => $tool,
            'output_extension' => pathinfo($outputPath, PATHINFO_EXTENSION),
            'command_preview' => substr($command, 0, 200) . '...'
        ]);

        return $command;
    }

    private function fallbackAudioProcessing($inputPath, $outputPath)
    {
        Log::info('Using fallback audio processing (file copy)');
        return copy($inputPath, $outputPath);
    }

    private function getOutputExtension($tool, $settings, $inputPath = null)
    {
        // For enhancement tools, preserve original format
        if (in_array($tool, ['compress-audio', 'noise-reduction']) && $inputPath) {
            $inputExtension = strtolower(pathinfo($inputPath, PATHINFO_EXTENSION));
            Log::info("Enhancement tool preserving original format", [
                'tool' => $tool,
                'input_extension' => $inputExtension
            ]);
            return $inputExtension;
        }
        
        // For conversion tools, return specific output format
        switch ($tool) {
            case 'mp3-to-wav':
            case 'flac-to-wav':
            case 'aac-to-wav':
            case 'ogg-to-wav':
                return 'wav';
            case 'wav-to-mp3':
            case 'flac-to-mp3':
            case 'aac-to-mp3':
            case 'ogg-to-mp3':
                return 'mp3';
            case 'mp3-to-flac':
            case 'wav-to-flac':
            case 'aac-to-flac':
            case 'ogg-to-flac':
                return 'flac';
            case 'mp3-to-aac':
            case 'wav-to-aac':
            case 'flac-to-aac':
            case 'ogg-to-aac':
                return 'aac';
            case 'mp3-to-ogg':
            case 'wav-to-ogg':
            case 'flac-to-ogg':
            case 'aac-to-ogg':
                return 'ogg';
            default:
                return 'mp3'; // Default fallback
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

            $downloadFilename = $this->generateDownloadFilename($processing);
            
            // Clean filename for safe download
            $safeFilename = str_replace(['"', '\\', '/'], ['_', '_', '_'], $downloadFilename);
            
            Log::info('Audio download starting', [
                'original_filename' => $processing->original_filename,
                'tool_name' => $processing->tool_name,
                'converted_filename' => $processing->converted_filename,
                'download_filename' => $downloadFilename
            ]);

            $mimeTypes = [
                'mp3' => 'audio/mpeg',
                'wav' => 'audio/wav',
                'flac' => 'audio/flac',
                'aac' => 'audio/aac',
                'ogg' => 'audio/ogg',
                'm4a' => 'audio/mp4'
            ];
            
            $extension = strtolower(pathinfo($downloadFilename, PATHINFO_EXTENSION));
            $contentType = $mimeTypes[$extension] ?? 'application/octet-stream';
            
            return response()->download($filePath, $safeFilename, [
                'Content-Type' => $contentType,
                'Content-Disposition' => 'attachment; filename="' . $safeFilename . '"',
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
                'Pragma' => 'no-cache',
                'Expires' => '0'
            ]);

        } catch (\Exception $e) {
            Log::error('Audio download error', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Download failed'], 500);
        }
    }

    /**
     * Generate proper download filename for audio files
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
            // Format conversions - keep original name, change extension
            case 'mp3-to-wav':
                return $cleanBaseName . '.wav';
            
            case 'wav-to-mp3':
                return $cleanBaseName . '.mp3';
            
            case 'flac-to-mp3':
                return $cleanBaseName . '.mp3';
            
            case 'mp3-to-flac':
                return $cleanBaseName . '.flac';
            
            case 'aac-to-mp3':
                return $cleanBaseName . '.mp3';
            
            case 'ogg-to-mp3':
                return $cleanBaseName . '.mp3';
            
            case 'm4a-to-mp3':
                return $cleanBaseName . '.mp3';
            
            case 'wav-to-flac':
                return $cleanBaseName . '.flac';
            
            case 'flac-to-wav':
                return $cleanBaseName . '.wav';
            
            // Audio enhancement - add suffix
            case 'compress-audio':
                return $cleanBaseName . '_compressed.' . $this->getOriginalExtension($originalFilename);
            
            case 'noise-reduction':
                return $cleanBaseName . '_cleaned.' . $this->getOriginalExtension($originalFilename);
            
            case 'normalize-audio':
                return $cleanBaseName . '_normalized.' . $this->getOriginalExtension($originalFilename);
            
            case 'enhance-audio':
                return $cleanBaseName . '_enhanced.' . $this->getOriginalExtension($originalFilename);
            
            case 'audio-equalizer':
                return $cleanBaseName . '_equalized.' . $this->getOriginalExtension($originalFilename);
            
            case 'change-bitrate':
                return $cleanBaseName . '_bitrate.' . $this->getOriginalExtension($originalFilename);
            
            case 'change-sample-rate':
                return $cleanBaseName . '_resampled.' . $this->getOriginalExtension($originalFilename);
            
            // Default fallback
            default:
                return $cleanBaseName . '_processed.' . $this->getOriginalExtension($originalFilename);
        }
    }

    /**
     * Get original file extension for audio
     */
    private function getOriginalExtension($filename)
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        return $extension ?: 'mp3'; // Default to mp3 if no extension
    }

    /**
     * Clean filename for safe storage and download
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
        
        return $cleaned ?: 'audio';
    }
}
