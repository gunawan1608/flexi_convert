<?php

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\Storage;
use App\Http\Controllers\ImageToolsHelperMethods;

// Test if our conversion methods are working
echo "Testing Image Conversion Methods\n";
echo "================================\n\n";

// Check if Imagick is available
if (!extension_loaded('imagick')) {
    echo "❌ Imagick extension is NOT loaded\n";
    exit(1);
} else {
    echo "✅ Imagick extension is loaded\n";
}

// Test jpgToPng method exists
if (method_exists(ImageToolsHelperMethods::class, 'jpgToPng')) {
    echo "✅ jpgToPng method exists\n";
} else {
    echo "❌ jpgToPng method does NOT exist\n";
}

// Test webpToPng method exists
if (method_exists(ImageToolsHelperMethods::class, 'webpToPng')) {
    echo "✅ webpToPng method exists\n";
} else {
    echo "❌ webpToPng method does NOT exist\n";
}

// Test pngToJpg method exists
if (method_exists(ImageToolsHelperMethods::class, 'pngToJpg')) {
    echo "✅ pngToJpg method exists\n";
} else {
    echo "❌ pngToJpg method does NOT exist\n";
}

// Test pngToWebp method exists
if (method_exists(ImageToolsHelperMethods::class, 'pngToWebp')) {
    echo "✅ pngToWebp method exists\n";
} else {
    echo "❌ pngToWebp method does NOT exist\n";
}

echo "\nTest completed.\n";
