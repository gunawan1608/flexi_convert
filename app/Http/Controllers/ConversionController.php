<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use App\Models\PdfProcessing;
use App\Models\ImageProcessing;
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
            
            $query = PdfProcessing::where('user_id', $user->id);

            // Apply filters
            if ($request->filter && $request->filter !== 'all') {
                $query->where('status', $request->filter);
            }

            // Apply search
            if ($request->search) {
                $query->where(function($q) use ($request) {
                    $q->where('original_filename', 'like', '%' . $request->search . '%')
                      ->orWhere('processed_filename', 'like', '%' . $request->search . '%')
                      ->orWhere('tool_name', 'like', '%' . $request->search . '%');
                });
            }

            // Apply sorting
            switch ($request->sort) {
                case 'oldest':
                    $query->orderBy('created_at', 'asc');
                    break;
                case 'name':
                    $query->orderBy('original_filename', 'asc');
                    break;
                case 'size':
                    $query->orderBy('file_size', 'desc');
                    break;
                default: // newest
                    $query->orderBy('created_at', 'desc');
                    break;
            }

            $conversions = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'conversions' => $conversions
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
            
            $total = PdfProcessing::where('user_id', $user->id)->count();
            $completed = PdfProcessing::where('user_id', $user->id)
                ->where('status', 'completed')
                ->count();
            $processing = PdfProcessing::where('user_id', $user->id)
                ->where('status', 'processing')
                ->count();
            $failed = PdfProcessing::where('user_id', $user->id)
                ->where('status', 'failed')
                ->count();

            return response()->json([
                'success' => true,
                'stats' => [
                    'total' => $total,
                    'completed' => $completed,
                    'processing' => $processing,
                    'failed' => $failed
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
}
