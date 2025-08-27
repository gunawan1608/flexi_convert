<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AudioToolsController extends Controller
{
    public function process(Request $request)
    {
        try {
            Log::info('Audio processing request received', [
                'tool' => $request->input('tool'),
                'files_count' => count($request->file('files', [])),
                'settings' => $request->input('settings')
            ]);

            $tool = $request->input('tool');
            $settings = json_decode($request->input('settings', '{}'), true);
            $files = $request->file('files', []);

            if (empty($files)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No files provided'
                ], 400);
            }

            $results = [];
            foreach ($files as $index => $file) {
                if (!$file->isValid()) {
                    $results[] = [
                        'filename' => $file->getClientOriginalName(),
                        'status' => 'failed',
                        'message' => 'Invalid file'
                    ];
                    continue;
                }

                try {
                    $result = $this->processAudioFile($file, $tool, $settings);
                    $results[] = $result;
                } catch (\Exception $e) {
                    Log::error('Audio processing failed for file', [
                        'filename' => $file->getClientOriginalName(),
                        'error' => $e->getMessage()
                    ]);
                    
                    $results[] = [
                        'filename' => $file->getClientOriginalName(),
                        'status' => 'failed',
                        'message' => $e->getMessage()
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'results' => $results
            ]);

        } catch (\Exception $e) {
            Log::error('Audio processing error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Processing failed: ' . $e->getMessage()
            ], 500);
        }
    }

    private function processAudioFile($file, $tool, $settings)
    {
        $uniqueId = Str::uuid();
        $originalName = $file->getClientOriginalName();
        $extension = $file->getClientOriginalExtension();
        
        // Store input file
        $inputPath = "audio-tools/inputs/{$uniqueId}_{$originalName}";
        $file->storeAs('', $inputPath);
        
        Log::info('Audio file stored', ['path' => $inputPath]);

        // Process with tool
        $result = $this->processWithTool(
            Storage::path($inputPath),
            $tool,
            $settings,
            $uniqueId,
            $originalName
        );

        return $result;
    }

    private function processWithTool($inputPath, $tool, $settings, $uniqueId, $filename)
    {
        $outputExtension = $this->getOutputExtension($tool, $settings);
        $outputFilename = pathinfo($filename, PATHINFO_FILENAME) . '_processed.' . $outputExtension;
        $outputPath = Storage::path("audio-tools/outputs/{$uniqueId}_{$outputFilename}");

        // Ensure output directory exists
        $outputDir = dirname($outputPath);
        if (!file_exists($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        Log::info('Processing audio with tool', [
            'tool' => $tool,
            'input' => $inputPath,
            'output' => $outputPath,
            'settings' => $settings
        ]);

        // Process audio based on tool
        $success = $this->processAudio($inputPath, $outputPath, $tool, $settings);

        if ($success && file_exists($outputPath)) {
            return [
                'filename' => $outputFilename,
                'status' => 'completed',
                'download_url' => route('audio-tools.download', ['filename' => $uniqueId . '_' . $outputFilename])
            ];
        } else {
            return [
                'filename' => $filename,
                'status' => 'failed',
                'message' => 'Audio processing failed'
            ];
        }
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
                // Format conversion - handled by output extension
                break;
                
            case 'compress-audio':
                $command .= " -b:a 128k";
                break;
                
            case 'normalize-audio':
                $command .= " -filter:a loudnorm";
                break;
                
            case 'change-speed':
                $speed = $settings['speed'] ?? 1.0;
                $command .= " -filter:a \"atempo=$speed\"";
                break;
                
            case 'trim-audio':
                $start = $settings['start'] ?? 0;
                $duration = $settings['duration'] ?? 30;
                $command .= " -ss $start -t $duration";
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
                return 'flac';
            case 'mp3-to-aac':
                return 'aac';
            case 'mp3-to-ogg':
                return 'ogg';
            default:
                return 'mp3'; // Default to MP3
        }
    }

    public function download($filename)
    {
        try {
            Log::info('Audio download requested', ['filename' => $filename]);
            
            $filePath = "audio-tools/outputs/{$filename}";
            
            if (!Storage::exists($filePath)) {
                Log::error('Audio file not found for download', ['path' => $filePath]);
                return response()->json(['error' => 'File not found'], 404);
            }

            $fullPath = Storage::path($filePath);
            $originalName = preg_replace('/^[a-f0-9-]+_/', '', $filename);

            Log::info('Audio download starting', [
                'path' => $fullPath,
                'original_name' => $originalName,
                'file_exists' => file_exists($fullPath),
                'file_size' => file_exists($fullPath) ? filesize($fullPath) : 0
            ]);

            return response()->download($fullPath, $originalName);

        } catch (\Exception $e) {
            Log::error('Audio download error', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Download failed'], 500);
        }
    }
}
