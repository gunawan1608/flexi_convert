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
                'settings' => 'nullable|string'
            ]);

            $tool = $validated['tool'];
            $files = $validated['files'];
            
            // Enhanced file validation for specific tools
            $this->validateFileTypesForTool($tool, $files);
            
            $settingsJson = $validated['settings'] ?? '{}';
            
            // Enhanced JSON parsing with error handling
            $settings = [];
            if (!empty($settingsJson) && $settingsJson !== '{}') {
                try {
                    $decoded = json_decode($settingsJson, true, 512, JSON_THROW_ON_ERROR);
                    $settings = is_array($decoded) ? $decoded : [];
                } catch (JsonException $e) {
                    Log::warning("Invalid JSON in settings: " . $e->getMessage(), [
                        'settings_raw' => $settingsJson,
                        'tool' => $tool
                    ]);
                    $settings = [];
                }
            }

            Log::info("Processing Image tool: {$tool}", [
                'files_count' => count($files),
                'settings' => $settings,
                'user_id' => auth()->id() ?? 'guest'
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
            $outputPath = $this->processWithTool($inputPath, $tool, $settings, $uniqueId, $filename);
            $outputFilename = basename($outputPath);
            Log::info("File processed successfully", ['output_path' => $outputPath]);

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
        $outputFilename = $uniqueId . '_processed.' . $outputExtension;
        $outputPath = 'image-tools/outputs/' . $outputFilename;
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
        
        return $outputPath;
    }

    /**
     * Process image using ImageMagick (preferred method)
     */
    private function processWithImageMagick($inputPath, $outputPath, $tool, $settings)
    {
        try {
            switch ($tool) {
                // Format conversions
                case 'jpg-to-png':
                    return ImageToolsHelperMethods::convertImageFormat($inputPath, $outputPath, 'png', $settings);
                case 'png-to-jpg':
                    return ImageToolsHelperMethods::convertImageFormat($inputPath, $outputPath, 'jpg', $settings);
                case 'webp-to-jpg':
                    return ImageToolsHelperMethods::convertImageFormat($inputPath, $outputPath, 'jpg', $settings);
                case 'jpg-to-webp':
                    return ImageToolsHelperMethods::convertImageFormat($inputPath, $outputPath, 'webp', $settings);
                case 'png-to-webp':
                    return ImageToolsHelperMethods::convertImageFormat($inputPath, $outputPath, 'webp', $settings);
                case 'gif-to-jpg':
                    return ImageToolsHelperMethods::convertImageFormat($inputPath, $outputPath, 'jpg', $settings);
                case 'bmp-to-jpg':
                    return ImageToolsHelperMethods::convertImageFormat($inputPath, $outputPath, 'jpg', $settings);
                case 'tiff-to-jpg':
                    return ImageToolsHelperMethods::convertImageFormat($inputPath, $outputPath, 'jpg', $settings);

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
                    $angle = $settings['angle'] ?? 90;
                    return ImageToolsHelperMethods::rotateImage($inputPath, $outputPath, $angle, $settings);

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
                    // For unknown tools, just copy with format conversion if needed
                    $outputFormat = $this->getOutputExtension($tool);
                    return ImageToolsHelperMethods::convertImageFormat($inputPath, $outputPath, $outputFormat, $settings);
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
            'jpg-to-png' => 'png',
            'png-to-jpg' => 'jpg',
            'webp-to-jpg' => 'jpg',
            'jpg-to-webp' => 'webp',
            'png-to-webp' => 'webp',
            'gif-to-jpg' => 'jpg',
            'bmp-to-jpg' => 'jpg',
            'tiff-to-jpg' => 'jpg',
        ];
        
        // For tools that don't change format, keep original
        if (isset($extensionMap[$tool])) {
            return $extensionMap[$tool];
        }
        
        // Default to jpg for processing tools
        return 'jpg';
    }

    private function applyImageProcessing($sourceImage, $tool, $settings, $originalWidth, $originalHeight)
    {
        switch ($tool) {
            // Format conversions - return source image as-is, format change happens in save
            case 'jpg-to-png':
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
        
        switch ($outputExtension) {
            case 'jpg':
            case 'jpeg':
                return imagejpeg($image, $outputPath, $qualityValue);
            case 'png':
                // PNG compression level (0-9, where 9 is max compression)
                $pngCompression = 9 - intval($qualityValue / 10);
                return imagepng($image, $outputPath, $pngCompression);
            case 'webp':
                return imagewebp($image, $outputPath, $qualityValue);
            case 'gif':
                return imagegif($image, $outputPath);
            default:
                return imagejpeg($image, $outputPath, $qualityValue);
        }
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
            
            Log::info("Download successful", ['file' => $processing->processed_filename]);
            
            return response($fileContent)
                ->header('Content-Type', $mimeType)
                ->header('Content-Disposition', 'attachment; filename="' . $processing->processed_filename . '"');

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
}
