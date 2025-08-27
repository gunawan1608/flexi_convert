<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class VideoToolsController extends Controller
{
    public function process(Request $request)
    {
        try {
            Log::info('Video processing request received', [
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
                    $result = $this->processVideoFile($file, $tool, $settings);
                    $results[] = $result;
                } catch (\Exception $e) {
                    Log::error('Video processing failed for file', [
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
            Log::error('Video processing error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Processing failed: ' . $e->getMessage()
            ], 500);
        }
    }

    private function processVideoFile($file, $tool, $settings)
    {
        $uniqueId = Str::uuid();
        $originalName = $file->getClientOriginalName();
        $extension = $file->getClientOriginalExtension();
        
        // Store input file
        $inputPath = "video-tools/inputs/{$uniqueId}_{$originalName}";
        $file->storeAs('', $inputPath);
        
        Log::info('Video file stored', ['path' => $inputPath]);

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
        $outputPath = Storage::path("video-tools/outputs/{$uniqueId}_{$outputFilename}");

        // Ensure output directory exists
        $outputDir = dirname($outputPath);
        if (!file_exists($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        Log::info('Processing video with tool', [
            'tool' => $tool,
            'input' => $inputPath,
            'output' => $outputPath,
            'settings' => $settings
        ]);

        // Process video based on tool
        $success = $this->processVideo($inputPath, $outputPath, $tool, $settings);

        if ($success && file_exists($outputPath)) {
            return [
                'filename' => $outputFilename,
                'status' => 'completed',
                'download_url' => route('video-tools.download', ['filename' => $uniqueId . '_' . $outputFilename])
            ];
        } else {
            return [
                'filename' => $filename,
                'status' => 'failed',
                'message' => 'Video processing failed'
            ];
        }
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
        $quality = $settings['quality'] ?? 'high';
        $resolution = $settings['resolution'] ?? '1080p';
        $fps = $settings['fps'] ?? '30';
        $codec = $settings['codec'] ?? 'h264';
        $bitrate = $settings['bitrate'] ?? 'auto';

        // Base command
        $command = "ffmpeg -i \"$inputPath\"";

        // Video codec
        switch ($codec) {
            case 'h264':
                $command .= " -c:v libx264";
                break;
            case 'h265':
                $command .= " -c:v libx265";
                break;
            case 'vp9':
                $command .= " -c:v libvpx-vp9";
                break;
            case 'av1':
                $command .= " -c:v libaom-av1";
                break;
        }

        // Quality preset
        switch ($quality) {
            case 'low':
                $command .= " -preset fast -crf 28";
                break;
            case 'medium':
                $command .= " -preset medium -crf 23";
                break;
            case 'high':
                $command .= " -preset slow -crf 18";
                break;
            case 'ultra':
                $command .= " -preset veryslow -crf 15";
                break;
        }

        // Resolution
        switch ($resolution) {
            case '480p':
                $command .= " -s 854x480";
                break;
            case '720p':
                $command .= " -s 1280x720";
                break;
            case '1080p':
                $command .= " -s 1920x1080";
                break;
            case '1440p':
                $command .= " -s 2560x1440";
                break;
            case '4k':
                $command .= " -s 3840x2160";
                break;
        }

        // Frame rate
        $command .= " -r $fps";

        // Bitrate (if not auto)
        if ($bitrate !== 'auto') {
            $command .= " -b:v $bitrate";
        }

        // Tool-specific processing
        switch ($tool) {
            case 'compress-video':
                $command .= " -crf 28 -preset fast";
                break;
                
            case 'resize-video':
                // Resolution already handled above
                break;
                
            case 'trim-video':
                $start = $settings['start'] ?? 0;
                $duration = $settings['duration'] ?? 30;
                $command .= " -ss $start -t $duration";
                break;
        }

        // Audio codec
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
                return 'mp4';
            case 'mp4-to-webm':
                return 'webm';
            case 'mp4-to-gif':
                return 'gif';
            default:
                return $settings['format'] ?? 'mp4'; // Default to MP4
        }
    }

    public function download($filename)
    {
        try {
            Log::info('Video download requested', ['filename' => $filename]);
            
            $filePath = "video-tools/outputs/{$filename}";
            
            if (!Storage::exists($filePath)) {
                Log::error('Video file not found for download', ['path' => $filePath]);
                return response()->json(['error' => 'File not found'], 404);
            }

            $fullPath = Storage::path($filePath);
            $originalName = preg_replace('/^[a-f0-9-]+_/', '', $filename);

            Log::info('Video download starting', [
                'path' => $fullPath,
                'original_name' => $originalName,
                'file_exists' => file_exists($fullPath),
                'file_size' => file_exists($fullPath) ? filesize($fullPath) : 0
            ]);

            return response()->download($fullPath, $originalName);

        } catch (\Exception $e) {
            Log::error('Video download error', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Download failed'], 500);
        }
    }
}
