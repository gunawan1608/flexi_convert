<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Imagick;
use ImagickException;
use Exception;

class ImageToolsHelperMethods
{
    /**
     * Check if ImageMagick is available and properly configured
     */
    public static function isImageMagickAvailable(): bool
    {
        try {
            if (!extension_loaded('imagick')) {
                Log::warning('ImageMagick extension not loaded');
                return false;
            }

            $imagick = new Imagick();
            $formats = $imagick->queryFormats();
            
            Log::info('ImageMagick available with formats', ['count' => count($formats)]);
            return true;
        } catch (Exception $e) {
            Log::error('ImageMagick availability check failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Convert image format using ImageMagick
     */
    public static function convertImageFormat(string $inputPath, string $outputPath, string $outputFormat, array $settings = []): bool
    {
        try {
            if (!self::isImageMagickAvailable()) {
                throw new Exception('ImageMagick is not available');
            }

            $imagick = new Imagick($inputPath);
            
            // Check and fix CMYK colorspace BEFORE any processing
            $currentColorspace = $imagick->getImageColorspace();
            Log::info('Image colorspace detected', [
                'colorspace' => $currentColorspace,
                'input' => basename($inputPath)
            ]);
            
            if ($currentColorspace === Imagick::COLORSPACE_CMYK) {
                Log::info('Converting CMYK to sRGB colorspace');
                $imagick->transformImageColorspace(Imagick::COLORSPACE_SRGB);
            }
            
            // Normalize output format
            $outputFormat = strtolower($outputFormat);
            if ($outputFormat === 'jpg') {
                $outputFormat = 'jpeg';
            }
            
            // FORCE set output format - this is critical for format conversion
            $imagick->setImageFormat(strtoupper($outputFormat));
            
            // Apply HIGH quality settings (80-100 range)
            $quality = self::getQualityValue($settings['quality'] ?? 'high');
            // Ensure minimum quality of 80 for conversions
            $quality = max(80, $quality);
            $imagick->setImageCompressionQuality($quality);

            // Apply compression and format-specific optimizations
            switch ($outputFormat) {
                case 'jpeg':
                    $imagick->setImageCompression(Imagick::COMPRESSION_JPEG);
                    // Remove alpha channel for JPEG
                    $imagick->setImageAlphaChannel(Imagick::ALPHACHANNEL_REMOVE);
                    // Set white background for transparency
                    $imagick->setImageBackgroundColor('white');
                    $imagick = $imagick->mergeImageLayers(Imagick::LAYERMETHOD_FLATTEN);
                    break;
                    
                case 'png':
                    $imagick->setImageCompression(Imagick::COMPRESSION_ZIP);
                    // Preserve alpha channel for PNG
                    $imagick->setImageAlphaChannel(Imagick::ALPHACHANNEL_ACTIVATE);
                    // Set PNG compression level for high quality
                    $imagick->setOption('png:compression-level', '1'); // 0-9, lower = better quality
                    break;
                    
                case 'webp':
                    // WebP supports both lossy and lossless
                    $imagick->setOption('webp:lossless', 'false');
                    $imagick->setOption('webp:alpha-quality', '100');
                    $imagick->setOption('webp:method', '6'); // Best quality method
                    break;
                    
                case 'gif':
                    // For GIF, use palette optimization
                    $imagick->quantizeImage(256, Imagick::COLORSPACE_RGB, 0, false, false);
                    break;
            }
            
            // Ensure output directory exists
            $outputDir = dirname($outputPath);
            if (!is_dir($outputDir)) {
                mkdir($outputDir, 0755, true);
            }

            // Write the image with FORCED format
            $success = $imagick->writeImage($outputPath);
            
            if (!$success) {
                throw new Exception('Failed to write converted image');
            }
            
            // Verify the output file was created and has correct format
            if (!file_exists($outputPath)) {
                throw new Exception('Output file was not created');
            }
            
            // Verify actual format conversion occurred
            $verifyImagick = new Imagick($outputPath);
            $actualFormat = strtolower($verifyImagick->getImageFormat());
            $verifyImagick->clear();
            $verifyImagick->destroy();
            
            if ($actualFormat !== $outputFormat && !($outputFormat === 'jpeg' && $actualFormat === 'jpg')) {
                Log::warning('Format conversion may not have occurred properly', [
                    'expected' => $outputFormat,
                    'actual' => $actualFormat
                ]);
            }
            
            $imagick->clear();
            $imagick->destroy();

            Log::info('Image format conversion completed', [
                'input' => basename($inputPath),
                'output' => basename($outputPath),
                'format' => $outputFormat,
                'actual_format' => $actualFormat,
                'quality' => $quality,
                'output_size' => filesize($outputPath)
            ]);

            return true;
        } catch (Exception $e) {
            Log::error('Image format conversion failed: ' . $e->getMessage(), [
                'input' => $inputPath,
                'output' => $outputPath,
                'format' => $outputFormat,
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Resize image using ImageMagick
     */
    public static function resizeImage(string $inputPath, string $outputPath, int $width, int $height, array $settings = []): bool
    {
        try {
            if (!self::isImageMagickAvailable()) {
                throw new Exception('ImageMagick is not available');
            }

            $imagick = new Imagick($inputPath);
            
            $maintainAspectRatio = $settings['maintainAspectRatio'] ?? true;
            $bestFit = $settings['bestFit'] ?? true;

            if ($maintainAspectRatio) {
                $imagick->resizeImage($width, $height, Imagick::FILTER_LANCZOS, 1, $bestFit);
            } else {
                $imagick->scaleImage($width, $height);
            }

            // Apply quality settings
            if (isset($settings['quality'])) {
                $quality = self::getQualityValue($settings['quality']);
                $imagick->setImageCompressionQuality($quality);
            }

            $imagick->writeImage($outputPath);
            $imagick->clear();
            $imagick->destroy();

            Log::info('Image resize completed', [
                'input' => basename($inputPath),
                'output' => basename($outputPath),
                'dimensions' => "{$width}x{$height}"
            ]);

            return true;
        } catch (Exception $e) {
            Log::error('Image resize failed: ' . $e->getMessage(), [
                'input' => $inputPath,
                'output' => $outputPath,
                'dimensions' => "{$width}x{$height}"
            ]);
            return false;
        }
    }

    /**
     * Crop image using ImageMagick
     */
    public static function cropImage(string $inputPath, string $outputPath, int $x, int $y, int $width, int $height, array $settings = []): bool
    {
        try {
            if (!self::isImageMagickAvailable()) {
                throw new Exception('ImageMagick is not available');
            }

            $imagick = new Imagick($inputPath);
            $imagick->cropImage($width, $height, $x, $y);

            // Apply quality settings
            if (isset($settings['quality'])) {
                $quality = self::getQualityValue($settings['quality']);
                $imagick->setImageCompressionQuality($quality);
            }

            $imagick->writeImage($outputPath);
            $imagick->clear();
            $imagick->destroy();

            Log::info('Image crop completed', [
                'input' => basename($inputPath),
                'output' => basename($outputPath),
                'crop' => "{$width}x{$height}+{$x}+{$y}"
            ]);

            return true;
        } catch (Exception $e) {
            Log::error('Image crop failed: ' . $e->getMessage(), [
                'input' => $inputPath,
                'output' => $outputPath,
                'crop' => "{$width}x{$height}+{$x}+{$y}"
            ]);
            return false;
        }
    }

    /**
     * Rotate image using ImageMagick
     */
    public static function rotateImage(string $inputPath, string $outputPath, float $angle, array $settings = []): bool
    {
        try {
            if (!self::isImageMagickAvailable()) {
                throw new Exception('ImageMagick is not available');
            }

            $imagick = new Imagick($inputPath);
            
            $backgroundColor = $settings['backgroundColor'] ?? 'transparent';
            $imagick->rotateImage($backgroundColor, $angle);

            // Apply quality settings
            if (isset($settings['quality'])) {
                $quality = self::getQualityValue($settings['quality']);
                $imagick->setImageCompressionQuality($quality);
            }

            $imagick->writeImage($outputPath);
            $imagick->clear();
            $imagick->destroy();

            Log::info('Image rotation completed', [
                'input' => basename($inputPath),
                'output' => basename($outputPath),
                'angle' => $angle
            ]);

            return true;
        } catch (Exception $e) {
            Log::error('Image rotation failed: ' . $e->getMessage(), [
                'input' => $inputPath,
                'output' => $outputPath,
                'angle' => $angle
            ]);
            return false;
        }
    }

    /**
     * Apply watermark to image using ImageMagick
     */
    public static function addWatermark(string $inputPath, string $outputPath, string $watermarkPath, array $settings = []): bool
    {
        try {
            if (!self::isImageMagickAvailable()) {
                throw new Exception('ImageMagick is not available');
            }

            $imagick = new Imagick($inputPath);
            $watermark = new Imagick($watermarkPath);

            // Set watermark opacity
            $opacity = $settings['opacity'] ?? 0.5;
            $watermark->evaluateImage(Imagick::EVALUATE_MULTIPLY, $opacity, Imagick::CHANNEL_ALPHA);

            // Position watermark
            $position = $settings['position'] ?? 'bottom-right';
            $margin = $settings['margin'] ?? 10;
            
            list($x, $y) = self::calculateWatermarkPosition(
                $imagick->getImageWidth(),
                $imagick->getImageHeight(),
                $watermark->getImageWidth(),
                $watermark->getImageHeight(),
                $position,
                $margin
            );

            $imagick->compositeImage($watermark, Imagick::COMPOSITE_OVER, $x, $y);

            // Apply quality settings
            if (isset($settings['quality'])) {
                $quality = self::getQualityValue($settings['quality']);
                $imagick->setImageCompressionQuality($quality);
            }

            $imagick->writeImage($outputPath);
            $imagick->clear();
            $imagick->destroy();
            $watermark->clear();
            $watermark->destroy();

            Log::info('Watermark applied successfully', [
                'input' => basename($inputPath),
                'output' => basename($outputPath),
                'watermark' => basename($watermarkPath),
                'position' => $position
            ]);

            return true;
        } catch (Exception $e) {
            Log::error('Watermark application failed: ' . $e->getMessage(), [
                'input' => $inputPath,
                'output' => $outputPath,
                'watermark' => $watermarkPath
            ]);
            return false;
        }
    }

    /**
     * Apply image effects using ImageMagick
     */
    public static function applyImageEffect(string $inputPath, string $outputPath, string $effect, array $settings = []): bool
    {
        try {
            if (!self::isImageMagickAvailable()) {
                throw new Exception('ImageMagick is not available');
            }

            $imagick = new Imagick($inputPath);
            
            // Get original format to preserve it
            $originalFormat = strtolower($imagick->getImageFormat());

            switch ($effect) {
                case 'grayscale':
                    $imagick->modulateImage(100, 0, 100);
                    break;

                case 'sepia':
                    $imagick->sepiaToneImage(80);
                    break;

                case 'blur':
                    $radius = $settings['radius'] ?? 5;
                    $sigma = $settings['sigma'] ?? 3;
                    $imagick->blurImage($radius, $sigma);
                    break;

                case 'sharpen':
                    $radius = $settings['radius'] ?? 0;
                    $sigma = $settings['sigma'] ?? 1;
                    $imagick->sharpenImage($radius, $sigma);
                    break;

                case 'emboss':
                    $radius = $settings['radius'] ?? 0;
                    $sigma = $settings['sigma'] ?? 1;
                    $imagick->embossImage($radius, $sigma);
                    break;

                case 'edge':
                    $radius = $settings['radius'] ?? 0;
                    $imagick->edgeImage($radius);
                    break;

                case 'oil-paint':
                    $radius = $settings['radius'] ?? 3;
                    $imagick->oilPaintImage($radius);
                    break;

                case 'brightness':
                    $brightness = $settings['brightness'] ?? 100;
                    $imagick->modulateImage($brightness, 100, 100);
                    break;

                case 'contrast':
                    $contrast = $settings['contrast'] ?? 100;
                    if ($contrast != 100) {
                        $imagick->contrastImage($contrast > 100);
                    }
                    break;

                case 'negative':
                    $imagick->negateImage(false);
                    break;

                default:
                    throw new Exception("Unsupported effect: {$effect}");
            }
            
            // Preserve original format after applying effects
            $imagick->setImageFormat(strtoupper($originalFormat));

            // Apply quality settings
            $quality = self::getQualityValue($settings['quality'] ?? 'high');
            $imagick->setImageCompressionQuality($quality);
            
            // Ensure output directory exists
            $outputDir = dirname($outputPath);
            if (!is_dir($outputDir)) {
                mkdir($outputDir, 0755, true);
            }

            $success = $imagick->writeImage($outputPath);
            
            if (!$success) {
                throw new Exception('Failed to write processed image');
            }
            
            $imagick->clear();
            $imagick->destroy();

            Log::info('Image effect applied successfully', [
                'input' => basename($inputPath),
                'output' => basename($outputPath),
                'effect' => $effect,
                'format_preserved' => $originalFormat
            ]);

            return true;
        } catch (Exception $e) {
            Log::error('Image effect application failed: ' . $e->getMessage(), [
                'input' => $inputPath,
                'output' => $outputPath,
                'effect' => $effect,
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Optimize image file size using ImageMagick
     */
    public static function optimizeImage(string $inputPath, string $outputPath, array $settings = []): bool
    {
        try {
            if (!self::isImageMagickAvailable()) {
                throw new Exception('ImageMagick is not available');
            }

            $imagick = new Imagick($inputPath);

            // Strip metadata to reduce file size
            $imagick->stripImage();

            // Set compression quality
            $quality = self::getQualityValue($settings['quality'] ?? 'medium');
            $imagick->setImageCompressionQuality($quality);

            // Apply additional optimizations based on format
            $format = strtolower($imagick->getImageFormat());
            
            if (in_array($format, ['jpg', 'jpeg'])) {
                $imagick->setImageCompression(Imagick::COMPRESSION_JPEG);
                $imagick->setInterlaceScheme(Imagick::INTERLACE_JPEG);
            } elseif ($format === 'png') {
                $imagick->setImageCompression(Imagick::COMPRESSION_ZIP);
            }

            // Resize if too large (optional optimization)
            if (isset($settings['maxWidth']) || isset($settings['maxHeight'])) {
                $maxWidth = $settings['maxWidth'] ?? 1920;
                $maxHeight = $settings['maxHeight'] ?? 1080;
                
                if ($imagick->getImageWidth() > $maxWidth || $imagick->getImageHeight() > $maxHeight) {
                    $imagick->resizeImage($maxWidth, $maxHeight, Imagick::FILTER_LANCZOS, 1, true);
                }
            }

            $imagick->writeImage($outputPath);
            $imagick->clear();
            $imagick->destroy();

            Log::info('Image optimization completed', [
                'input' => basename($inputPath),
                'output' => basename($outputPath),
                'quality' => $quality
            ]);

            return true;
        } catch (Exception $e) {
            Log::error('Image optimization failed: ' . $e->getMessage(), [
                'input' => $inputPath,
                'output' => $outputPath
            ]);
            return false;
        }
    }

    /**
     * Create image thumbnail using ImageMagick
     */
    public static function createThumbnail(string $inputPath, string $outputPath, int $size = 150, array $settings = []): bool
    {
        try {
            if (!self::isImageMagickAvailable()) {
                throw new Exception('ImageMagick is not available');
            }

            $imagick = new Imagick($inputPath);
            
            // Create square thumbnail by default
            $cropToSquare = $settings['cropToSquare'] ?? true;
            
            if ($cropToSquare) {
                $imagick->cropThumbnailImage($size, $size);
            } else {
                $imagick->resizeImage($size, $size, Imagick::FILTER_LANCZOS, 1, true);
            }

            // Apply quality settings
            $quality = self::getQualityValue($settings['quality'] ?? 'high');
            $imagick->setImageCompressionQuality($quality);

            $imagick->writeImage($outputPath);
            $imagick->clear();
            $imagick->destroy();

            Log::info('Thumbnail created successfully', [
                'input' => basename($inputPath),
                'output' => basename($outputPath),
                'size' => $size
            ]);

            return true;
        } catch (Exception $e) {
            Log::error('Thumbnail creation failed: ' . $e->getMessage(), [
                'input' => $inputPath,
                'output' => $outputPath,
                'size' => $size
            ]);
            return false;
        }
    }

    /**
     * Get image information using ImageMagick
     */
    public static function getImageInfo(string $imagePath): array
    {
        try {
            if (!self::isImageMagickAvailable()) {
                throw new Exception('ImageMagick is not available');
            }

            $imagick = new Imagick($imagePath);
            
            $info = [
                'width' => $imagick->getImageWidth(),
                'height' => $imagick->getImageHeight(),
                'format' => $imagick->getImageFormat(),
                'colorspace' => $imagick->getImageColorspace(),
                'compression' => $imagick->getImageCompression(),
                'quality' => $imagick->getImageCompressionQuality(),
                'filesize' => filesize($imagePath),
                'resolution' => $imagick->getImageResolution()
            ];

            $imagick->clear();
            $imagick->destroy();

            return $info;
        } catch (Exception $e) {
            Log::error('Failed to get image info: ' . $e->getMessage(), [
                'path' => $imagePath
            ]);
            return [];
        }
    }

    /**
     * Calculate watermark position
     */
    private static function calculateWatermarkPosition(int $imageWidth, int $imageHeight, int $watermarkWidth, int $watermarkHeight, string $position, int $margin): array
    {
        switch ($position) {
            case 'top-left':
                return [$margin, $margin];
            case 'top-right':
                return [$imageWidth - $watermarkWidth - $margin, $margin];
            case 'bottom-left':
                return [$margin, $imageHeight - $watermarkHeight - $margin];
            case 'bottom-right':
                return [$imageWidth - $watermarkWidth - $margin, $imageHeight - $watermarkHeight - $margin];
            case 'center':
                return [
                    ($imageWidth - $watermarkWidth) / 2,
                    ($imageHeight - $watermarkHeight) / 2
                ];
            default:
                return [$imageWidth - $watermarkWidth - $margin, $imageHeight - $watermarkHeight - $margin];
        }
    }

    /**
     * Get colorspace name for debugging
     */
    private static function getColorspaceName($colorspace): string
    {
        $colorspaces = [];
        
        // Only add constants that are defined in this ImageMagick installation
        if (defined('Imagick::COLORSPACE_UNDEFINED')) $colorspaces[Imagick::COLORSPACE_UNDEFINED] = 'UNDEFINED';
        if (defined('Imagick::COLORSPACE_RGB')) $colorspaces[Imagick::COLORSPACE_RGB] = 'RGB';
        if (defined('Imagick::COLORSPACE_GRAY')) $colorspaces[Imagick::COLORSPACE_GRAY] = 'GRAY';
        if (defined('Imagick::COLORSPACE_TRANSPARENT')) $colorspaces[Imagick::COLORSPACE_TRANSPARENT] = 'TRANSPARENT';
        if (defined('Imagick::COLORSPACE_OHTA')) $colorspaces[Imagick::COLORSPACE_OHTA] = 'OHTA';
        if (defined('Imagick::COLORSPACE_LAB')) $colorspaces[Imagick::COLORSPACE_LAB] = 'LAB';
        if (defined('Imagick::COLORSPACE_XYZ')) $colorspaces[Imagick::COLORSPACE_XYZ] = 'XYZ';
        if (defined('Imagick::COLORSPACE_YCBCR')) $colorspaces[Imagick::COLORSPACE_YCBCR] = 'YCBCR';
        if (defined('Imagick::COLORSPACE_YCC')) $colorspaces[Imagick::COLORSPACE_YCC] = 'YCC';
        if (defined('Imagick::COLORSPACE_YIQ')) $colorspaces[Imagick::COLORSPACE_YIQ] = 'YIQ';
        if (defined('Imagick::COLORSPACE_YPBPR')) $colorspaces[Imagick::COLORSPACE_YPBPR] = 'YPBPR';
        if (defined('Imagick::COLORSPACE_YUV')) $colorspaces[Imagick::COLORSPACE_YUV] = 'YUV';
        if (defined('Imagick::COLORSPACE_CMYK')) $colorspaces[Imagick::COLORSPACE_CMYK] = 'CMYK';
        if (defined('Imagick::COLORSPACE_SRGB')) $colorspaces[Imagick::COLORSPACE_SRGB] = 'SRGB';
        if (defined('Imagick::COLORSPACE_HSB')) $colorspaces[Imagick::COLORSPACE_HSB] = 'HSB';
        if (defined('Imagick::COLORSPACE_HSL')) $colorspaces[Imagick::COLORSPACE_HSL] = 'HSL';
        if (defined('Imagick::COLORSPACE_HWB')) $colorspaces[Imagick::COLORSPACE_HWB] = 'HWB';
        
        return $colorspaces[$colorspace] ?? "UNKNOWN($colorspace)";
    }

    /**
     * Convert quality setting to numeric value (optimized for high quality)
     */
    private static function getQualityValue($quality): int
    {
        if (is_numeric($quality)) {
            return max(1, min(100, (int)$quality));
        }

        return match(strtolower($quality)) {
            'low' => 80,      // Increased from 60 to 80
            'medium' => 90,   // Increased from 80 to 90
            'high' => 95,     // Keep at 95
            'maximum' => 100, // Keep at 100
            default => 90     // Increased from 85 to 90
        };
    }

    /**
     * Set memory and execution limits based on image size
     */
    public static function setResourceLimits(int $fileSize): void
    {
        // Calculate memory needed (rough estimate)
        $memoryNeeded = max(256, $fileSize * 3 / 1024 / 1024); // MB
        $memoryLimit = $memoryNeeded . 'M';
        
        // Set execution time based on file size
        $executionTime = max(60, min(300, $fileSize / 1024 / 1024 * 10)); // seconds
        
        ini_set('memory_limit', $memoryLimit);
        ini_set('max_execution_time', $executionTime);
        
        Log::info('Resource limits set for image processing', [
            'memory_limit' => $memoryLimit,
            'execution_time' => $executionTime,
            'file_size_mb' => round($fileSize / 1024 / 1024, 2)
        ]);
    }

    /**
     * Specific JPG to PNG conversion with CMYK handling and GD fallback
     */
    public static function jpgToPng(string $inputPath, string $outputPath, array $settings = []): bool
    {
        // Force output path to end with .png
        $outputPath = preg_replace('/\.[^.]+$/', '.png', $outputPath);

        try {
            if (!self::isImageMagickAvailable()) {
                throw new Exception('ImageMagick is not available');
            }

            $imagick = new Imagick($inputPath);
            
            // Critical: Handle CMYK colorspace properly
            $currentColorspace = $imagick->getImageColorspace();
            Log::info('Original image colorspace detected', [
                'colorspace_id' => $currentColorspace,
                'colorspace_name' => self::getColorspaceName($currentColorspace)
            ]);
            
            // Force fallback to GD for better JPG to PNG conversion
            Log::info('Forcing GD fallback for better JPG to PNG conversion');
            throw new Exception('Force GD fallback for JPG to PNG');
            
            return true;
        } catch (Exception $e) {
            Log::error('ImageMagick JPG to PNG conversion failed: ' . $e->getMessage());
            Log::info('Imagick gagal, fallback ke GD untuk JPG to PNG conversion');
            
            // Fallback to GD
            return self::jpgToPngGD($inputPath, $outputPath, $settings);
        }
    }

    /**
     * GD fallback for JPG to PNG conversion
     */
    private static function jpgToPngGD(string $inputPath, string $outputPath, array $settings = []): bool
    {
        try {
            if (!function_exists('imagecreatefromjpeg')) {
                throw new Exception('GD gagal membaca JPEG');
            }
            if (!function_exists('imagepng')) {
                throw new Exception('GD gagal menulis PNG');
            }

            $img = imagecreatefromjpeg($inputPath);
            if (!$img) {
                throw new Exception('GD gagal membaca JPEG');
            }

            // GD automatically handles sRGB colorspace
            $result = imagepng($img, $outputPath);
            imagedestroy($img);

            if (!$result) {
                throw new Exception('GD gagal menyimpan PNG');
            }

            Log::info('Imagick gagal, fallback ke GD berhasil', [
                'input' => basename($inputPath),
                'output' => basename($outputPath),
                'method' => 'GD JPG to PNG'
            ]);

            return true;
        } catch (Exception $e) {
            Log::error('GD JPG to PNG conversion failed: ' . $e->getMessage());
            throw new Exception('GD gagal membaca JPEG: ' . $e->getMessage());
        }
    }

    /**
     * Specific PNG to JPG conversion
     */
    public static function pngToJpg(string $inputPath, string $outputPath, array $settings = []): bool
    {
        try {
            if (!self::isImageMagickAvailable()) {
                throw new Exception('ImageMagick is not available');
            }

            $imagick = new Imagick($inputPath);
            
            // Force JPEG format
            $imagick->setImageFormat('JPEG');
            
            // JPEG specific settings
            $imagick->setImageCompression(Imagick::COMPRESSION_JPEG);
            $imagick->setImageAlphaChannel(Imagick::ALPHACHANNEL_REMOVE);
            $imagick->setImageBackgroundColor('white');
            $imagick = $imagick->mergeImageLayers(Imagick::LAYERMETHOD_FLATTEN);
            
            // High quality
            $quality = max(90, self::getQualityValue($settings['quality'] ?? 'high'));
            $imagick->setImageCompressionQuality($quality);
            
            $imagick->writeImage($outputPath);
            $imagick->clear();
            $imagick->destroy();
            
            Log::info('PNG to JPG conversion completed', [
                'input' => basename($inputPath),
                'output' => basename($outputPath)
            ]);
            
            return true;
        } catch (Exception $e) {
            Log::error('PNG to JPG conversion failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Specific WebP to PNG conversion with proper re-encoding and GD fallback
     */
    public static function webpToPng(string $inputPath, string $outputPath, array $settings = []): bool
    {
        // Force output path to end with .png
        $outputPath = preg_replace('/\.[^.]+$/', '.png', $outputPath);

        try {
            if (!self::isImageMagickAvailable()) {
                throw new Exception('ImageMagick is not available');
            }

            $imagick = new Imagick($inputPath);
            
            // Force fallback to GD for better WebP to PNG conversion
            Log::info('Forcing GD fallback for better WebP to PNG conversion');
            throw new Exception('Force GD fallback for WebP to PNG');
        } catch (Exception $e) {
            Log::error('ImageMagick WebP to PNG conversion failed: ' . $e->getMessage());
            Log::info('Imagick gagal, fallback ke GD untuk WebP to PNG conversion');
            
            // Fallback to GD
            return self::webpToPngGD($inputPath, $outputPath, $settings);
        }
    }

    /**
     * GD fallback for WebP to PNG conversion
     */
    private static function webpToPngGD(string $inputPath, string $outputPath, array $settings = []): bool
    {
        try {
            if (!function_exists('imagecreatefromwebp')) {
                throw new Exception('GD gagal membaca WebP');
            }
            if (!function_exists('imagepng')) {
                throw new Exception('GD gagal menulis PNG');
            }

            $img = imagecreatefromwebp($inputPath);
            if (!$img) {
                throw new Exception('GD gagal membaca WebP');
            }

            // Save as PNG - CRITICAL: Use imagepng() not imagewebp()
            $result = imagepng($img, $outputPath);
            imagedestroy($img);

            if (!$result) {
                throw new Exception('GD gagal menyimpan PNG');
            }

            Log::info('Imagick gagal, fallback ke GD berhasil', [
                'input' => basename($inputPath),
                'output' => basename($outputPath),
                'method' => 'GD WebP to PNG'
            ]);

            return true;
        } catch (Exception $e) {
            Log::error('GD WebP to PNG conversion failed: ' . $e->getMessage());
            throw new Exception('GD gagal membaca WebP: ' . $e->getMessage());
        }
    }

    /**
     * Specific PNG to WebP conversion
     */
    public static function pngToWebp(string $inputPath, string $outputPath, array $settings = []): bool
    {
        try {
            if (!self::isImageMagickAvailable()) {
                throw new Exception('ImageMagick is not available');
            }

            $imagick = new Imagick($inputPath);
            
            // Force WebP format
            $imagick->setImageFormat('WEBP');
            
            // WebP specific settings for high quality
            $imagick->setOption('webp:lossless', 'false');
            $imagick->setOption('webp:alpha-quality', '100');
            $imagick->setOption('webp:method', '6'); // Best quality method
            
            // High quality
            $quality = max(90, self::getQualityValue($settings['quality'] ?? 'high'));
            $imagick->setImageCompressionQuality($quality);
            
            $imagick->writeImage($outputPath);
            $imagick->clear();
            $imagick->destroy();
            
            Log::info('PNG to WebP conversion completed', [
                'input' => basename($inputPath),
                'output' => basename($outputPath)
            ]);
            
            return true;
        } catch (Exception $e) {
            Log::error('PNG to WebP conversion failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Clean up temporary files
     */
    public static function cleanupTempFiles(array $filePaths): void
    {
        foreach ($filePaths as $filePath) {
            if (file_exists($filePath)) {
                unlink($filePath);
                Log::debug('Temporary file cleaned up', ['path' => $filePath]);
            }
        }
    }
}
