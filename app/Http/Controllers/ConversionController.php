<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use App\Models\PdfProcessing;
use App\Models\ImageProcessing;
use App\Models\VideoProcessing;
use App\Models\AudioProcessing;
use Carbon\Carbon;

class ConversionController extends Controller
{
    public function __construct()
    {
        // Middleware is now handled in routes or via Route::middleware()
    }

    public function getHistory(Request $request)
    {
        try {
            $user = Auth::user();
            $perPage = 15;
            
            // Get conversions from all processing types
            $pdfConversions = PdfProcessing::where('user_id', $user->id)
                ->get()
                ->map(function ($conversion) {
                    $conversion->conversion_type = 'document';
                    $conversion->file_size_human = $this->formatBytes($conversion->file_size ?? 0);
                    return $conversion;
                });

            $imageConversions = ImageProcessing::where('user_id', $user->id)
                ->get()
                ->map(function ($conversion) {
                    $conversion->conversion_type = 'image';
                    $conversion->file_size_human = $this->formatBytes($conversion->file_size ?? 0);
                    return $conversion;
                });

            $videoConversions = VideoProcessing::where('user_id', $user->id)
                ->get()
                ->map(function ($conversion) {
                    $conversion->conversion_type = 'video';
                    $conversion->file_size_human = $this->formatBytes($conversion->original_file_size ?? 0);
                    return $conversion;
                });

            $audioConversions = AudioProcessing::where('user_id', $user->id)
                ->get()
                ->map(function ($conversion) {
                    $conversion->conversion_type = 'audio';
                    $conversion->file_size_human = $this->formatBytes($conversion->original_file_size ?? 0);
                    return $conversion;
                });

            // Combine all conversions
            $allConversions = $pdfConversions->concat($imageConversions)
                ->concat($videoConversions)
                ->concat($audioConversions);

            // Apply filters
            if ($request->filter && $request->filter !== 'all') {
                $allConversions = $allConversions->where('status', $request->filter);
            }

            // Apply search
            if ($request->search) {
                $searchTerm = strtolower($request->search);
                $allConversions = $allConversions->filter(function($conversion) use ($searchTerm) {
                    return str_contains(strtolower($conversion->original_filename ?? ''), $searchTerm) ||
                           str_contains(strtolower($conversion->processed_filename ?? ''), $searchTerm) ||
                           str_contains(strtolower($conversion->tool_name ?? ''), $searchTerm);
                });
            }

            // Apply sorting
            switch ($request->sort) {
                case 'oldest':
                    $allConversions = $allConversions->sortBy('created_at');
                    break;
                case 'name':
                    $allConversions = $allConversions->sortBy('original_filename');
                    break;
                case 'size':
                    $allConversions = $allConversions->sortByDesc(function($conversion) {
                        return $conversion->file_size ?? $conversion->original_file_size ?? 0;
                    });
                    break;
                default: // newest
                    $allConversions = $allConversions->sortByDesc('created_at');
                    break;
            }

            // Manual pagination
            $currentPage = $request->get('page', 1);
            $offset = ($currentPage - 1) * $perPage;
            $items = $allConversions->slice($offset, $perPage)->values();
            $total = $allConversions->count();

            $paginatedData = [
                'data' => $items,
                'current_page' => $currentPage,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => ceil($total / $perPage),
                'from' => $offset + 1,
                'to' => min($offset + $perPage, $total)
            ];

            return response()->json([
                'success' => true,
                'conversions' => $paginatedData
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => true,
                'message' => 'Failed to fetch conversion history: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getStats()
    {
        try {
            $user = Auth::user();
            
            // Get stats from all conversion types
            $pdfTotal = PdfProcessing::where('user_id', $user->id)->count();
            $pdfCompleted = PdfProcessing::where('user_id', $user->id)->where('status', 'completed')->count();
            $pdfProcessing = PdfProcessing::where('user_id', $user->id)->where('status', 'processing')->count();
            $pdfFailed = PdfProcessing::where('user_id', $user->id)->where('status', 'failed')->count();

            $imageTotal = ImageProcessing::where('user_id', $user->id)->count();
            $imageCompleted = ImageProcessing::where('user_id', $user->id)->where('status', 'completed')->count();
            $imageProcessing = ImageProcessing::where('user_id', $user->id)->where('status', 'processing')->count();
            $imageFailed = ImageProcessing::where('user_id', $user->id)->where('status', 'failed')->count();

            $videoTotal = VideoProcessing::where('user_id', $user->id)->count();
            $videoCompleted = VideoProcessing::where('user_id', $user->id)->where('status', 'completed')->count();
            $videoProcessing = VideoProcessing::where('user_id', $user->id)->where('status', 'processing')->count();
            $videoFailed = VideoProcessing::where('user_id', $user->id)->where('status', 'failed')->count();

            $audioTotal = AudioProcessing::where('user_id', $user->id)->count();
            $audioCompleted = AudioProcessing::where('user_id', $user->id)->where('status', 'completed')->count();
            $audioProcessing = AudioProcessing::where('user_id', $user->id)->where('status', 'processing')->count();
            $audioFailed = AudioProcessing::where('user_id', $user->id)->where('status', 'failed')->count();

            return response()->json([
                'success' => true,
                'stats' => [
                    'total' => $pdfTotal + $imageTotal + $videoTotal + $audioTotal,
                    'completed' => $pdfCompleted + $imageCompleted + $videoCompleted + $audioCompleted,
                    'processing' => $pdfProcessing + $imageProcessing + $videoProcessing + $audioProcessing,
                    'failed' => $pdfFailed + $imageFailed + $videoFailed + $audioFailed
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => true,
                'message' => 'Failed to fetch conversion stats: ' . $e->getMessage()
            ], 500);
        }
    }

    public function download($id)
    {
        try {
            $user = Auth::user();
            $conversion = PdfProcessing::where('id', $id)
                ->where('user_id', $user->id)
                ->where('status', 'completed')
                ->first();

            if (!$conversion) {
                return response()->json([
                    'error' => true,
                    'message' => 'Conversion not found or not completed'
                ], 404);
            }

            if (!$conversion->processed_filename) {
                return response()->json([
                    'error' => true,
                    'message' => 'No processed file available'
                ], 404);
            }

            $filePath = 'pdf-tools/outputs/' . $conversion->processed_filename;
            
            if (!Storage::exists($filePath)) {
                return response()->json([
                    'error' => true,
                    'message' => 'File not found in storage'
                ], 404);
            }

            $fileContent = Storage::get($filePath);
            $mimeType = Storage::mimeType($filePath);
            
            return response($fileContent)
                ->header('Content-Type', $mimeType)
                ->header('Content-Disposition', 'attachment; filename="' . $conversion->processed_filename . '"');

        } catch (\Exception $e) {
            Log::error("Download failed: " . $e->getMessage());
            return response()->json([
                'error' => true,
                'message' => 'Download failed: ' . $e->getMessage()
            ], 500);
        }
    }

    public function delete($id)
    {
        try {
            $user = Auth::user();
            $conversion = PdfProcessing::where('id', $id)
                ->where('user_id', $user->id)
                ->first();

            if (!$conversion) {
                return response()->json([
                    'error' => true,
                    'message' => 'Conversion not found'
                ], 404);
            }

            // Delete file from storage if exists
            if ($conversion->processed_filename) {
                $filePath = 'pdf-tools/outputs/' . $conversion->processed_filename;
                if (Storage::exists($filePath)) {
                    Storage::delete($filePath);
                }
            }

            // Delete conversion record
            $conversion->delete();

            return response()->json([
                'success' => true,
                'message' => 'Conversion deleted successfully'
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to delete conversion: " . $e->getMessage());
            return response()->json([
                'error' => true,
                'message' => 'Failed to delete conversion: ' . $e->getMessage()
            ], 500);
        }
    }

    private function formatBytes($bytes)
    {
        if ($bytes == 0) return '0 B';
        
        $k = 1024;
        $sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = floor(log($bytes) / log($k));
        
        return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
    }
}
