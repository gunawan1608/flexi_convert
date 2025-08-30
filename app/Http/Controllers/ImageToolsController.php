<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use App\Models\ImageProcessing;
use Illuminate\Support\Str;
use JsonException;
use Exception;

class ImageToolsController extends Controller
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
                'files.*' => 'required|file|max:51200',
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

            Log::info("Processing Image tool: {$tool}", [
                'files_count' => count($files),
                'settings' => $settings,
                'user_id' => auth()->id() ?? 'guest',
                'tool_mapping_debug' => "Tool '{$tool}' will be processed"
            ]);

            $results = [];
            foreach ($files as $index => $file) {
                try {
                    $result = $this->processImageFile($file, $tool, $settings);
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
                'message' => 'Pemrosesan gambar selesai',
                'results' => $results
            ])->header('Content-Type', 'application/json');

        } catch (\Illuminate\Validation\ValidationException $e) {
            // Clear any output buffer before sending JSON
            if (ob_get_level()) {
                ob_clean();
            }
            
            Log::error("Image validation error", [
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
            
            Log::error("Image processing critical error", [
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
     * Validate file types for specific image conversion tools
     */
    private function validateFileTypesForTool($tool, $files)
    {
        $validationRules = [
            'jpg-to-png' => ['jpg', 'jpeg'],
            'png-to-jpg' => ['png'],
            'webp-to-jpg' => ['webp'],
            'jpg-to-webp' => ['jpg', 'jpeg'],
            'png-to-webp' => ['png'],
            'gif-to-jpg' => ['gif'],
            'bmp-to-jpg' => ['bmp'],
            'tiff-to-jpg' => ['tiff', 'tif'],
            'resize-custom' => ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'],
            'resize-percentage' => ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'],
            'resize-preset' => ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'],
            'rotate' => ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'],
            'crop' => ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'],
            'grayscale' => ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'],
            'blur' => ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'],
            'brightness' => ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'],
            'contrast' => ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'],
            'sepia' => ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'],
            'sharpen' => ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'],
            'emboss' => ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'],
            'edge' => ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'],
            'oil-paint' => ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'],
            'negative' => ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'],
            'watermark' => ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'],
            'optimize' => ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'],
            'thumbnail' => ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp']
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
            'jpg' => ['image/jpeg', 'application/octet-stream'],
            'jpeg' => ['image/jpeg', 'application/octet-stream'],
            'png' => ['image/png', 'application/octet-stream'],
            'gif' => ['image/gif', 'application/octet-stream'],
            'bmp' => ['image/bmp', 'image/x-ms-bmp', 'application/octet-stream'],
            'webp' => ['image/webp', 'application/octet-stream'],
            'tiff' => ['image/tiff', 'application/octet-stream'],
            'tif' => ['image/tiff', 'application/octet-stream']
        ];

        $validMimeTypes = [];
        foreach ($extensions as $ext) {
            if (isset($mimeMap[$ext])) {
                $validMimeTypes = array_merge($validMimeTypes, $mimeMap[$ext]);
            }
        }

        return array_unique($validMimeTypes);
    }

    private function processImageFile($file, $tool, $settings)
    {
        $originalName = $file->getClientOriginalName();
        $extension = $file->getClientOriginalExtension();
        $filename = pathinfo($originalName, PATHINFO_FILENAME);
        $uniqueId = Str::uuid();
        
        Log::info("Starting image processing", [
            'file' => $originalName,
            'tool' => $tool,
            'size' => $file->getSize()
        ]);
        
        // Create database record
        $processing = ImageProcessing::create([
            'user_id' => auth()->id() ?? 1,
            'tool_name' => $tool,
            'original_filename' => $originalName,
            'file_size' => $file->getSize(),
            'status' => 'processing',
            'progress' => 0,
            'settings' => json_encode($settings),
            'created_at' => now(),
            'updated_at' => now()
        ]);

        try {
            // Set resource limits based on file size
            ImageToolsHelperMethods::setResourceLimits($file->getSize());
            
            // Store input file
            $inputPath = 'image-tools/inputs/' . $uniqueId . '.' . $extension;
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
                'processed_filename' => $outputFilename,
                'processed_file_size' => Storage::size($outputPath),
                'status' => 'completed',
                'progress' => 100,
                'completed_at' => now()
            ]);

            Log::info("Processing completed successfully", ['id' => $processing->id]);

            return [
                'id' => $processing->id,
                'filename' => $originalName,
                'status' => 'completed',
                'download_url' => route('image-tools.download', ['id' => $processing->id]),
                'output_path' => $outputPath,
                'output_filename' => $outputFilename
            ];

        } catch (\Exception $e) {
            Log::error("Processing failed for {$originalName}: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            
            $processing->update([
                'status' => 'failed',
                'error_message' => $e->getMessage()
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
        $outputExtension = $this->getOutputExtension($tool);
        
        // If no specific extension (processing tools), preserve original
        if ($outputExtension === null) {
            $outputExtension = pathinfo($inputPath, PATHINFO_EXTENSION);
        }
        
        // Generate output filename with correct extension - force .png for conversion tools
        if (in_array($tool, ['jpg-to-png', 'webp-to-png'])) {
            $outputExtension = 'png'; // Force PNG extension
        }
        
        $outputFilename = $uniqueId . '_output.' . $outputExtension;
        $outputPath = 'image-tools/outputs/' . $outputFilename;
        
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
        
        // Try ImageMagick first, fallback to GD
        $success = $this->processWithImageMagick($tempInputPath, $tempOutputPath, $tool, $settings);
        
        if (!$success) {
            Log::info("ImageMagick processing failed, falling back to GD", ['tool' => $tool]);
            $this->processImage($tempInputPath, $tempOutputPath, $tool, $settings);
        }
        
        // Verify output file exists and has content
        if (!file_exists($tempOutputPath) || filesize($tempOutputPath) === 0) {
            throw new \Exception("Output file was not created or is empty: {$tempOutputPath}");
        }
        
        Log::info("Image processing completed", [
            'tool' => $tool,
            'input_extension' => pathinfo($tempInputPath, PATHINFO_EXTENSION),
            'output_extension' => $outputExtension,
            'output_size' => filesize($tempOutputPath)
        ]);
        
        return $outputPath;
    }

    /**
     * Process image using ImageMagick (preferred method)
     */
    private function processWithImageMagick($inputPath, $outputPath, $tool, $settings)
    {
        try {
            Log::info("ImageMagick processing started", [
                'tool' => $tool,
                'input_path' => $inputPath,
                'output_path' => $outputPath
            ]);
            
            switch ($tool) {
                // Format conversions - Use specific methods for better control
                case 'jpg-to-png':
                    Log::info("Using jpgToPng method");
                    return ImageToolsHelperMethods::jpgToPng($inputPath, $outputPath, $settings);
                case 'png-to-jpg':
                    Log::info("Using pngToJpg method");
                    return ImageToolsHelperMethods::pngToJpg($inputPath, $outputPath, $settings);
                case 'webp-to-jpg':
                    Log::info("Using convertImageFormat for webp-to-jpg");
                    return ImageToolsHelperMethods::convertImageFormat($inputPath, $outputPath, 'jpg', $settings);
                case 'webp-to-png':
                    Log::info("Using webpToPng method");
                    return ImageToolsHelperMethods::webpToPng($inputPath, $outputPath, $settings);
                case 'jpg-to-webp':
                    Log::info("Using convertImageFormat for jpg-to-webp");
                    return ImageToolsHelperMethods::convertImageFormat($inputPath, $outputPath, 'webp', $settings);
                case 'png-to-webp':
                    Log::info("Using pngToWebp method");
                    return ImageToolsHelperMethods::pngToWebp($inputPath, $outputPath, $settings);
                case 'gif-to-jpg':
                    return ImageToolsHelperMethods::convertImageFormat($inputPath, $outputPath, 'jpg', $settings);
                case 'gif-to-png':
                    return ImageToolsHelperMethods::convertImageFormat($inputPath, $outputPath, 'png', $settings);
                case 'bmp-to-jpg':
                    return ImageToolsHelperMethods::convertImageFormat($inputPath, $outputPath, 'jpg', $settings);
                case 'tiff-to-jpg':
                    return ImageToolsHelperMethods::convertImageFormat($inputPath, $outputPath, 'jpg', $settings);
                case 'compress-image':
                case 'optimize-web':
                    return ImageToolsHelperMethods::optimizeImage($inputPath, $outputPath, $settings);

                // Resize operations
                case 'resize-custom':
                    $width = $settings['width'] ?? 800;
                    $height = $settings['height'] ?? 600;
                    return ImageToolsHelperMethods::resizeImage($inputPath, $outputPath, $width, $height, $settings);
                case 'resize-percentage':
                    $info = ImageToolsHelperMethods::getImageInfo($inputPath);
                    $percentage = $settings['percentage'] ?? 100;
                    $newWidth = intval($info['width'] * $percentage / 100);
                    $newHeight = intval($info['height'] * $percentage / 100);
                    return ImageToolsHelperMethods::resizeImage($inputPath, $outputPath, $newWidth, $newHeight, $settings);
                case 'resize-preset':
                    $presets = [
                        'thumbnail' => [150, 150],
                        'small' => [320, 240],
                        'medium' => [640, 480],
                        'large' => [1024, 768],
                        'hd' => [1280, 720],
                        'fullhd' => [1920, 1080]
                    ];
                    $preset = $settings['preset'] ?? 'medium';
                    $dimensions = $presets[$preset] ?? $presets['medium'];
                    return ImageToolsHelperMethods::resizeImage($inputPath, $outputPath, $dimensions[0], $dimensions[1], $settings);

                // Crop operation
                case 'crop':
                    $x = $settings['x'] ?? 0;
                    $y = $settings['y'] ?? 0;
                    $width = $settings['width'] ?? 100;
                    $height = $settings['height'] ?? 100;
                    return ImageToolsHelperMethods::cropImage($inputPath, $outputPath, $x, $y, $width, $height, $settings);

                // Rotation
                case 'rotate':
                case 'rotate-image':
                    $angle = floatval($settings['angle'] ?? 90);
                    
                    Log::info('Rotate settings received', [
                        'angle' => $angle
                    ]);
                    
                    return ImageToolsHelperMethods::rotateImage($inputPath, $outputPath, $angle, $settings);
                
                // Resize operations
                case 'resize-image':
                    $width = intval($settings['width'] ?? 800);
                    $height = intval($settings['height'] ?? 600);
                    $maintainAspectRatio = filter_var($settings['maintainAspectRatio'] ?? true, FILTER_VALIDATE_BOOLEAN);
                    
                    Log::info('Resize settings received', [
                        'width' => $width,
                        'height' => $height,
                        'maintainAspectRatio' => $maintainAspectRatio
                    ]);
                    
                    return ImageToolsHelperMethods::resizeImage($inputPath, $outputPath, $width, $height, array_merge($settings, [
                        'maintainAspectRatio' => $maintainAspectRatio
                    ]));

                // Effects
                case 'grayscale':
                case 'sepia':
                case 'blur':
                case 'sharpen':
                case 'emboss':
                case 'edge':
                case 'oil-paint':
                case 'brightness':
                case 'contrast':
                case 'negative':
                    return ImageToolsHelperMethods::applyImageEffect($inputPath, $outputPath, $tool, $settings);

                // Watermark
                case 'watermark':
                    $watermarkPath = $settings['watermark_path'] ?? null;
                    if (!$watermarkPath || !file_exists($watermarkPath)) {
                        throw new Exception('Watermark file not found or not specified');
                    }
                    return ImageToolsHelperMethods::addWatermark($inputPath, $outputPath, $watermarkPath, $settings);

                // Optimization
                case 'optimize':
                    return ImageToolsHelperMethods::optimizeImage($inputPath, $outputPath, $settings);

                // Thumbnail
                case 'thumbnail':
                    $size = $settings['size'] ?? 150;
                    return ImageToolsHelperMethods::createThumbnail($inputPath, $outputPath, $size, $settings);

                default:
                    // For unknown tools, preserve original format
                    $originalFormat = pathinfo($inputPath, PATHINFO_EXTENSION);
                    return ImageToolsHelperMethods::convertImageFormat($inputPath, $outputPath, $originalFormat, $settings);
            }
        } catch (Exception $e) {
            Log::error("ImageMagick processing failed for tool {$tool}: " . $e->getMessage());
            return false;
        }
    }

    private function processImage($inputPath, $outputPath, $tool, $settings)
    {
        Log::info("Processing image", ['tool' => $tool, 'input' => $inputPath, 'output' => $outputPath]);
        
        // Check if GD extension is available
        if (!extension_loaded('gd')) {
            Log::warning("GD extension not available, using fallback");
            copy($inputPath, $outputPath);
            return;
        }
        
        // Create image resource from input file
        $sourceImage = $this->createImageFromFile($inputPath);
        if (!$sourceImage) {
            throw new \Exception("Failed to create image resource from input file");
        }
        
        // Get original dimensions
        $originalWidth = imagesx($sourceImage);
        $originalHeight = imagesy($sourceImage);
        
        Log::info("Original image dimensions", ['width' => $originalWidth, 'height' => $originalHeight]);
        
        // Process based on tool type
        $processedImage = $this->applyImageProcessing($sourceImage, $tool, $settings, $originalWidth, $originalHeight);
        
        // Save processed image
        $this->saveProcessedImage($processedImage, $outputPath, $tool, $settings);
        
        // Clean up resources
        imagedestroy($sourceImage);
        if ($processedImage !== $sourceImage) {
            imagedestroy($processedImage);
        }
        
        Log::info("Image processing completed successfully");
    }

    private function getOutputExtension($tool)
    {
        $extensionMap = [
            // Format conversions - MUST return target format
            'jpg-to-png' => 'png',
            'png-to-jpg' => 'jpg', 
            'webp-to-jpg' => 'jpg',
            'jpg-to-webp' => 'webp',
            'png-to-webp' => 'webp',
            'gif-to-jpg' => 'jpg',
            'gif-to-png' => 'png',
            'bmp-to-jpg' => 'jpg',
            'tiff-to-jpg' => 'jpg',
            // Processing tools - preserve original format
            'resize-custom' => null,
            'resize-percentage' => null,
            'resize-preset' => null,
            'rotate' => null,
            'crop' => null,
            'grayscale' => null,
            'blur' => null,
            'brightness' => null,
            'contrast' => null,
            'sepia' => null,
            'sharpen' => null,
            'emboss' => null,
            'edge' => null,
            'oil-paint' => null,
            'negative' => null,
            'watermark' => null,
            'optimize' => null,
            'thumbnail' => null,
            'compress-image' => null,
            'optimize-web' => null
        ];
        
        // Return specific target format for conversion tools
        if (isset($extensionMap[$tool]) && $extensionMap[$tool] !== null) {
            return $extensionMap[$tool];
        }
        
        // For processing tools (null value), preserve original format
        return null; // Will be determined from input file
    }

    private function applyImageProcessing($sourceImage, $tool, $settings, $originalWidth, $originalHeight)
    {
        switch ($tool) {
            // Format conversions - handle transparency and proper format conversion
            case 'jpg-to-png':
                return $this->convertJpgToPng($sourceImage, $originalWidth, $originalHeight);
            case 'webp-to-png':
                return $this->convertWebpToPng($sourceImage, $originalWidth, $originalHeight);
            case 'png-to-jpg':
            case 'webp-to-jpg':
            case 'jpg-to-webp':
            case 'png-to-webp':
            case 'gif-to-jpg':
                return $sourceImage;
                
            // Resize operations
            case 'resize-custom':
                return $this->resizeImage($sourceImage, $settings['width'], $settings['height'], $settings['maintainAspectRatio'] ?? true);
            case 'resize-percentage':
                $newWidth = intval($originalWidth * ($settings['percentage'] ?? 100) / 100);
                $newHeight = intval($originalHeight * ($settings['percentage'] ?? 100) / 100);
                return $this->resizeImage($sourceImage, $newWidth, $newHeight, true);
            case 'resize-preset':
                return $this->resizeImagePresetInternal($sourceImage, $settings['preset'] ?? 'medium');
                
            // Rotation
            case 'rotate':
                return $this->rotateImage($sourceImage, $settings['angle'] ?? 90);
                
            // Filters
            case 'grayscale':
                return $this->applyGrayscaleFilter($sourceImage);
            case 'blur':
                return $this->applyBlurFilter($sourceImage, $settings['intensity'] ?? 5);
            case 'brightness':
                return $this->applyBrightnessFilter($sourceImage, $settings['level'] ?? 50);
            case 'contrast':
                return $this->applyContrastFilter($sourceImage, $settings['level'] ?? 0);
                
            default:
                return $sourceImage;
        }
    }

    private function resizeImage($sourceImage, $newWidth, $newHeight, $maintainAspectRatio = true)
    {
        $originalWidth = imagesx($sourceImage);
        $originalHeight = imagesy($sourceImage);
        
        if ($maintainAspectRatio && $newWidth && $newHeight) {
            $aspectRatio = $originalWidth / $originalHeight;
            if ($newWidth / $newHeight > $aspectRatio) {
                $newWidth = $newHeight * $aspectRatio;
            } else {
                $newHeight = $newWidth / $aspectRatio;
            }
        }
        
        $newImage = imagecreatetruecolor($newWidth, $newHeight);
        
        // Preserve transparency for PNG
        imagealphablending($newImage, false);
        imagesavealpha($newImage, true);
        $transparent = imagecolorallocatealpha($newImage, 255, 255, 255, 127);
        imagefill($newImage, 0, 0, $transparent);
        
        imagecopyresampled($newImage, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $originalWidth, $originalHeight);
        
        return $newImage;
    }

    private function resizeImagePresetInternal($sourceImage, $preset)
    {
        $presets = [
            'thumbnail' => [150, 150],
            'small' => [320, 240],
            'medium' => [640, 480],
            'large' => [1024, 768],
            'hd' => [1280, 720],
            'fullhd' => [1920, 1080]
        ];
        
        $dimensions = $presets[$preset] ?? $presets['medium'];
        return $this->resizeImage($sourceImage, $dimensions[0], $dimensions[1], true);
    }

    private function rotateImage($sourceImage, $angle)
    {
        $rotated = imagerotate($sourceImage, -$angle, 0);
        return $rotated ?: $sourceImage;
    }

    private function applyGrayscaleFilter($sourceImage)
    {
        $newImage = imagecreatetruecolor(imagesx($sourceImage), imagesy($sourceImage));
        imagecopy($newImage, $sourceImage, 0, 0, 0, 0, imagesx($sourceImage), imagesy($sourceImage));
        imagefilter($newImage, IMG_FILTER_GRAYSCALE);
        return $newImage;
    }

    private function applyBlurFilter($sourceImage, $intensity)
    {
        $newImage = imagecreatetruecolor(imagesx($sourceImage), imagesy($sourceImage));
        imagecopy($newImage, $sourceImage, 0, 0, 0, 0, imagesx($sourceImage), imagesy($sourceImage));
        
        for ($i = 0; $i < $intensity; $i++) {
            imagefilter($newImage, IMG_FILTER_GAUSSIAN_BLUR);
        }
        
        return $newImage;
    }

    private function applyBrightnessFilter($sourceImage, $level)
    {
        $newImage = imagecreatetruecolor(imagesx($sourceImage), imagesy($sourceImage));
        imagecopy($newImage, $sourceImage, 0, 0, 0, 0, imagesx($sourceImage), imagesy($sourceImage));
        imagefilter($newImage, IMG_FILTER_BRIGHTNESS, $level - 50);
        return $newImage;
    }

    private function applyContrastFilter($sourceImage, $level)
    {
        $newImage = imagecreatetruecolor(imagesx($sourceImage), imagesy($sourceImage));
        imagecopy($newImage, $sourceImage, 0, 0, 0, 0, imagesx($sourceImage), imagesy($sourceImage));
        imagefilter($newImage, IMG_FILTER_CONTRAST, $level);
        return $newImage;
    }

    private function saveProcessedImage($image, $outputPath, $tool, $settings)
    {
        $outputExtension = $this->getOutputExtension($tool);
        $quality = $settings['quality'] ?? 'high';
        
        // Convert quality setting to numeric
        $qualityValue = match($quality) {
            'low' => 60,
            'medium' => 80,
            'high' => 95,
            default => 85
        };
        
        // Check GD function support before using
        switch ($outputExtension) {
            case 'jpg':
            case 'jpeg':
                if (!function_exists('imagejpeg')) {
                    throw new \Exception('JPEG support not available in GD');
                }
                return imagejpeg($image, $outputPath, $qualityValue);
            case 'png':
                if (!function_exists('imagepng')) {
                    throw new \Exception('PNG support not available in GD');
                }
                // PNG compression level (0-9, where 9 is max compression)
                $pngCompression = 9 - intval($qualityValue / 10);
                return imagepng($image, $outputPath, $pngCompression);
            case 'webp':
                if (!function_exists('imagewebp')) {
                    throw new \Exception('WebP support not available in GD');
                }
                return imagewebp($image, $outputPath, $qualityValue);
            case 'gif':
                if (!function_exists('imagegif')) {
                    throw new \Exception('GIF support not available in GD');
                }
                return imagegif($image, $outputPath);
            default:
                if (!function_exists('imagejpeg')) {
                    throw new \Exception('JPEG support not available in GD');
                }
                return imagejpeg($image, $outputPath, $qualityValue);
        }
    }

    /**
     * Convert JPG to PNG with proper transparency handling
     */
    private function convertJpgToPng($sourceImage, $width, $height)
    {
        // Create new PNG image with transparency support
        $pngImage = imagecreatetruecolor($width, $height);
        
        // Enable alpha blending and save alpha channel
        imagealphablending($pngImage, false);
        imagesavealpha($pngImage, true);
        
        // Fill with transparent background
        $transparent = imagecolorallocatealpha($pngImage, 0, 0, 0, 127);
        imagefill($pngImage, 0, 0, $transparent);
        
        // Copy source image to PNG with transparency
        imagealphablending($pngImage, true);
        imagecopy($pngImage, $sourceImage, 0, 0, 0, 0, $width, $height);
        
        // Restore alpha settings for final save
        imagealphablending($pngImage, false);
        imagesavealpha($pngImage, true);
        
        return $pngImage;
    }

    /**
     * Convert WebP to PNG with proper format conversion
     */
    private function convertWebpToPng($sourceImage, $width, $height)
    {
        // Create new PNG image with transparency support
        $pngImage = imagecreatetruecolor($width, $height);
        
        // Enable alpha blending and save alpha channel
        imagealphablending($pngImage, false);
        imagesavealpha($pngImage, true);
        
        // Fill with transparent background
        $transparent = imagecolorallocatealpha($pngImage, 0, 0, 0, 127);
        imagefill($pngImage, 0, 0, $transparent);
        
        // Copy source image to PNG with transparency
        imagealphablending($pngImage, true);
        imagecopy($pngImage, $sourceImage, 0, 0, 0, 0, $width, $height);
        
        // Restore alpha settings for final save
        imagealphablending($pngImage, false);
        imagesavealpha($pngImage, true);
        
        return $pngImage;
    }

    private function createImageFromFile($filePath)
    {
        if (!file_exists($filePath)) {
            Log::error("Image file not found: {$filePath}");
            return false;
        }

        $imageInfo = getimagesize($filePath);
        if (!$imageInfo) {
            Log::error("Invalid image file: {$filePath}");
            return false;
        }

        try {
            switch ($imageInfo[2]) {
                case IMAGETYPE_JPEG:
                    $image = imagecreatefromjpeg($filePath);
                    break;
                case IMAGETYPE_PNG:
                    $image = imagecreatefrompng($filePath);
                    break;
                case IMAGETYPE_GIF:
                    $image = imagecreatefromgif($filePath);
                    break;
                case IMAGETYPE_WEBP:
                    if (function_exists('imagecreatefromwebp')) {
                        $image = imagecreatefromwebp($filePath);
                    } else {
                        throw new \Exception("WebP support not available");
                    }
                    break;
                case IMAGETYPE_BMP:
                    if (function_exists('imagecreatefrombmp')) {
                        $image = imagecreatefrombmp($filePath);
                    } else {
                        throw new \Exception("BMP support not available");
                    }
                    break;
                default:
                    throw new \Exception("Unsupported image type: " . $imageInfo[2]);
            }

            if (!$image) {
                throw new \Exception("Failed to create image resource from file");
            }

            return $image;
        } catch (\Exception $e) {
            Log::error("Error creating image from file {$filePath}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Fallback method when GD extension is not available
     */
    private function createFallbackProcessedFile($inputPath, $outputPath, $tool, $settings)
    {
        // For demonstration, just copy the file with a new name
        $extension = pathinfo($inputPath, PATHINFO_EXTENSION);
        $finalOutputPath = str_replace('_output', '_processed.' . $extension, $outputPath);
        
        // Copy original file as "processed" version
        copy($inputPath, $finalOutputPath);
        
        Log::info("Fallback processing used for tool: {$tool} (GD extension not available)");
        
        return $finalOutputPath;
    }

    /**
     * Download processed file
     */
    public function download($id)
    {
        try {
            $processing = ImageProcessing::find($id);
            
            if (!$processing) {
                Log::error("Processing record not found", ['id' => $id]);
                return response()->json([
                    'success' => false,
                    'message' => 'File tidak ditemukan'
                ], 404);
            }

            Log::info("Download request", [
                'id' => $id,
                'status' => $processing->status,
                'filename' => $processing->processed_filename
            ]);

            if ($processing->status !== 'completed') {
                return response()->json([
                    'success' => false,
                    'message' => 'File belum selesai diproses'
                ], 400);
            }

            $filePath = 'image-tools/outputs/' . $processing->processed_filename;
            
            if (!Storage::exists($filePath)) {
                Log::error("File not found in storage", ['path' => $filePath]);
                return response()->json([
                    'success' => false,
                    'message' => 'File tidak ditemukan di storage'
                ], 404);
            }

            $fileContent = Storage::get($filePath);
            $mimeType = Storage::mimeType($filePath);
            
            // Generate proper download filename based on original filename and tool
            $downloadFilename = $this->generateDownloadFilename($processing);
            
            // Clean filename for safe download
            $safeFilename = str_replace(['"', '\\', '/'], ['_', '_', '_'], $downloadFilename);
            
            // For Content-Disposition, use RFC 5987 encoding
            $encodedFilename = "UTF-8''" . rawurlencode($downloadFilename);
            
            Log::info("Download successful", [
                'original_filename' => $processing->original_filename,
                'tool_name' => $processing->tool_name,
                'processed_filename' => $processing->processed_filename,
                'download_filename' => $downloadFilename
            ]);
            
            return response($fileContent)
                ->header('Content-Type', $mimeType)
                ->header('Content-Disposition', 'attachment; filename="' . $safeFilename . '"; filename*=' . $encodedFilename);

        } catch (\Exception $e) {
            Log::error("Download failed: " . $e->getMessage(), [
                'id' => $id,
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Download gagal: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate proper download filename based on original filename and conversion tool
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
            case 'jpg-to-png':
            case 'jpeg-to-png':
                return $cleanBaseName . '.png';
            
            case 'png-to-jpg':
            case 'png-to-jpeg':
                return $cleanBaseName . '.jpg';
            
            case 'webp-to-jpg':
            case 'webp-to-jpeg':
                return $cleanBaseName . '.jpg';
            
            case 'webp-to-png':
                return $cleanBaseName . '.png';
            
            case 'jpg-to-webp':
            case 'jpeg-to-webp':
            case 'png-to-webp':
                return $cleanBaseName . '.webp';
            
            case 'gif-to-jpg':
            case 'gif-to-jpeg':
                return $cleanBaseName . '.jpg';
            
            case 'gif-to-png':
                return $cleanBaseName . '.png';
            
            case 'bmp-to-jpg':
            case 'bmp-to-jpeg':
                return $cleanBaseName . '.jpg';
            
            case 'bmp-to-png':
                return $cleanBaseName . '.png';
            
            // Size modifications - add suffix
            case 'resize-image':
            case 'resize-custom':
                return $cleanBaseName . '_resized.' . $this->getOriginalExtension($originalFilename);
            
            case 'resize-percentage':
                return $cleanBaseName . '_scaled.' . $this->getOriginalExtension($originalFilename);
            
            case 'resize-preset':
                return $cleanBaseName . '_preset.' . $this->getOriginalExtension($originalFilename);
            
            // Crop operations - add suffix
            case 'crop-square':
                return $cleanBaseName . '_square.' . $this->getOriginalExtension($originalFilename);
            
            case 'crop-rectangle':
                return $cleanBaseName . '_cropped.' . $this->getOriginalExtension($originalFilename);
            
            case 'crop-circle':
                return $cleanBaseName . '_circle.png'; // Always PNG for transparency
            
            case 'crop-custom':
                return $cleanBaseName . '_custom_crop.' . $this->getOriginalExtension($originalFilename);
            
            // Optimization - add suffix
            case 'compress-lossy':
            case 'compress-lossless':
                return $cleanBaseName . '_compressed.' . $this->getOriginalExtension($originalFilename);
            
            case 'reduce-colors':
                return $cleanBaseName . '_optimized.' . $this->getOriginalExtension($originalFilename);
            
            // Effects - add suffix
            case 'rotate-image':
                return $cleanBaseName . '_rotated.' . $this->getOriginalExtension($originalFilename);
            
            case 'flip-horizontal':
                return $cleanBaseName . '_flipped_h.' . $this->getOriginalExtension($originalFilename);
            
            case 'flip-vertical':
                return $cleanBaseName . '_flipped_v.' . $this->getOriginalExtension($originalFilename);
            
            case 'grayscale':
                return $cleanBaseName . '_grayscale.' . $this->getOriginalExtension($originalFilename);
            
            case 'sepia':
                return $cleanBaseName . '_sepia.' . $this->getOriginalExtension($originalFilename);
            
            // Default fallback
            default:
                return $cleanBaseName . '_processed.' . $this->getOriginalExtension($originalFilename);
        }
    }
    
    /**
     * Get original file extension
     */
    private function getOriginalExtension($filename)
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        return $extension ?: 'jpg'; // Default to jpg if no extension
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
        
        return $cleaned ?: 'image';
    }
}
