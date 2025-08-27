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
            
            // Set output format
            $imagick->setImageFormat(strtoupper($outputFormat));
            
            // Apply quality settings
            if (isset($settings['quality'])) {
                $quality = self::getQualityValue($settings['quality']);
                $imagick->setImageCompressionQuality($quality);
            }

            // Apply compression for specific formats
            if (in_array(strtolower($outputFormat), ['jpg', 'jpeg'])) {
                $imagick->setImageCompression(Imagick::COMPRESSION_JPEG);
            }

            // Write the image
            $imagick->writeImage($outputPath);
            $imagick->clear();
            $imagick->destroy();

            Log::info('Image format conversion completed', [
                'input' => basename($inputPath),
                'output' => basename($outputPath),
                'format' => $outputFormat
            ]);

            return true;
        } catch (Exception $e) {
            Log::error('Image format conversion failed: ' . $e->getMessage(), [
                'input' => $inputPath,
                'output' => $outputPath,
                'format' => $outputFormat
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
                    $contrast = $settings['contrast'] ?? 100;
                    $imagick->modulateImage($brightness, 100, 100);
                    break;

                case 'contrast':
                    $contrast = $settings['contrast'] ?? 100;
                    $imagick->modulateImage(100, 100, 100);
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

            // Apply quality settings
            if (isset($settings['quality'])) {
                $quality = self::getQualityValue($settings['quality']);
                $imagick->setImageCompressionQuality($quality);
            }

            $imagick->writeImage($outputPath);
            $imagick->clear();
            $imagick->destroy();

            Log::info('Image effect applied successfully', [
                'input' => basename($inputPath),
                'output' => basename($outputPath),
                'effect' => $effect
            ]);

            return true;
        } catch (Exception $e) {
            Log::error('Image effect application failed: ' . $e->getMessage(), [
                'input' => $inputPath,
                'output' => $outputPath,
                'effect' => $effect
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
     * Convert quality setting to numeric value
     */
    private static function getQualityValue($quality): int
    {
        if (is_numeric($quality)) {
            return max(1, min(100, (int)$quality));
        }

        return match(strtolower($quality)) {
            'low' => 60,
            'medium' => 80,
            'high' => 95,
            'maximum' => 100,
            default => 85
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
