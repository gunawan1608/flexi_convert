<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use App\Models\PdfProcessing;
use App\Models\ImageProcessing;
use App\Models\VideoProcessing;
use App\Models\AudioProcessing;

class DashboardController extends Controller
{
    public function index(): View
    {
        $user = Auth::user();
        
        // Show email verification notice if not verified, but allow access
        $showEmailNotice = !$user->hasVerifiedEmail();
        
        // Get recent conversions from all processing types
        $pdfConversions = PdfProcessing::where('user_id', $user->id)
            ->latest()
            ->limit(10)
            ->get()
            ->map(function ($conversion) {
                $conversion->conversion_type = 'document';
                $conversion->original_extension = pathinfo($conversion->original_filename, PATHINFO_EXTENSION);
                $conversion->target_extension = $this->getPdfTargetExtension($conversion->tool_name);
                $conversion->file_size_human = $this->formatBytes($conversion->file_size ?? 0);
                return $conversion;
            });

        $imageConversions = ImageProcessing::where('user_id', $user->id)
            ->latest()
            ->limit(10)
            ->get()
            ->map(function ($conversion) {
                $conversion->conversion_type = 'image';
                // Add missing fields for consistency
                $conversion->original_extension = pathinfo($conversion->original_filename, PATHINFO_EXTENSION);
                $conversion->target_extension = $this->getTargetExtension($conversion->tool_name);
                $conversion->file_size_human = $this->formatBytes($conversion->file_size ?? 0);
                return $conversion;
            });

        $videoConversions = VideoProcessing::where('user_id', $user->id)
            ->latest()
            ->limit(10)
            ->get()
            ->map(function ($conversion) {
                $conversion->conversion_type = 'video';
                $conversion->original_extension = pathinfo($conversion->original_filename, PATHINFO_EXTENSION);
                $conversion->target_extension = $this->getVideoTargetExtension($conversion->tool_name);
                $conversion->file_size_human = $this->formatBytes($conversion->original_file_size ?? 0);
                return $conversion;
            });

        $audioConversions = AudioProcessing::where('user_id', $user->id)
            ->latest()
            ->limit(10)
            ->get()
            ->map(function ($conversion) {
                $conversion->conversion_type = 'audio';
                $conversion->original_extension = pathinfo($conversion->original_filename, PATHINFO_EXTENSION);
                $conversion->target_extension = $this->getAudioTargetExtension($conversion->tool_name);
                $conversion->file_size_human = $this->formatBytes($conversion->original_file_size ?? 0);
                return $conversion;
            });

        // Combine and sort by created_at
        $allConversions = $pdfConversions->concat($imageConversions)
            ->concat($videoConversions)
            ->concat($audioConversions)
            ->sortByDesc('created_at')
            ->take(5);

        // Calculate comprehensive statistics
        $totalPdfProcessings = PdfProcessing::where('user_id', $user->id)->count();
        $totalImageProcessings = ImageProcessing::where('user_id', $user->id)->count();
        $totalVideoProcessings = VideoProcessing::where('user_id', $user->id)->count();
        $totalAudioProcessings = AudioProcessing::where('user_id', $user->id)->count();
        
        $completedPdfToday = PdfProcessing::where('user_id', $user->id)
            ->where('status', 'completed')
            ->whereDate('created_at', today())
            ->count();
        $completedImageToday = ImageProcessing::where('user_id', $user->id)
            ->where('status', 'completed')
            ->whereDate('created_at', today())
            ->count();
        $completedVideoToday = VideoProcessing::where('user_id', $user->id)
            ->where('status', 'completed')
            ->whereDate('created_at', today())
            ->count();
        $completedAudioToday = AudioProcessing::where('user_id', $user->id)
            ->where('status', 'completed')
            ->whereDate('created_at', today())
            ->count();

        $stats = [
            'total_processings' => $totalPdfProcessings + $totalImageProcessings + $totalVideoProcessings + $totalAudioProcessings,
            'completed_processings' => $user->completed_processings ?? 0,
            'today_processings' => $completedPdfToday + $completedImageToday + $completedVideoToday + $completedAudioToday,
            'storage_used' => $user->storage_used_human ?? '0 B',
            'recent_conversions' => $allConversions,
            'show_email_notice' => $showEmailNotice,
            // Individual type statistics
            'pdf_processings' => $totalPdfProcessings,
            'image_processings' => $totalImageProcessings,
            'video_processings' => $totalVideoProcessings,
            'audio_processings' => $totalAudioProcessings,
            'pdf_today' => $completedPdfToday,
            'image_today' => $completedImageToday,
            'video_today' => $completedVideoToday,
            'audio_today' => $completedAudioToday,
        ];

        return view('dashboard.index', compact('stats'));
    }

    private function getPdfTargetExtension($toolName)
    {
        // Map PDF tool names to target extensions
        $toolMap = [
            'word-to-pdf' => 'pdf',
            'excel-to-pdf' => 'pdf',
            'ppt-to-pdf' => 'pdf',
            'html-to-pdf' => 'pdf',
            'jpg-to-pdf' => 'pdf',
            'png-to-pdf' => 'pdf',
            'image-to-pdf' => 'pdf',
            'images-to-pdf' => 'pdf',
            'pdf-to-word' => 'docx',
            'pdf-to-excel' => 'xlsx',
            'pdf-to-ppt' => 'pptx',
            'pdf-to-jpg' => 'jpg',
            'compress-pdf' => 'pdf',
            'merge-pdf' => 'pdf',
            'split-pdf' => 'pdf',
            'rotate-pdf' => 'pdf',
            'add-watermark' => 'pdf',
            'add-page-numbers' => 'pdf',
        ];

        return $toolMap[$toolName] ?? 'pdf';
    }

    private function getTargetExtension($toolName)
    {
        // Map tool names to target extensions
        $toolMap = [
            'jpg-to-png' => 'png',
            'png-to-jpg' => 'jpg',
            'webp-to-png' => 'png',
            'png-to-webp' => 'webp',
            'jpg-to-webp' => 'webp',
            'webp-to-jpg' => 'jpg',
            'resize-image' => null, // Same as original
            'rotate-image' => null, // Same as original
        ];

        return $toolMap[$toolName] ?? null;
    }

    private function getVideoTargetExtension($toolName)
    {
        // Map video tool names to target extensions
        $toolMap = [
            'mp4-to-avi' => 'avi',
            'avi-to-mp4' => 'mp4',
            'mov-to-mp4' => 'mp4',
            'mkv-to-mp4' => 'mp4',
            'webm-to-mp4' => 'mp4',
            'compress-video' => 'mp4',
            'trim-video' => null, // Same as original
            'merge-videos' => 'mp4',
            'extract-audio' => 'mp3',
            'add-watermark' => null, // Same as original
        ];

        return $toolMap[$toolName] ?? 'mp4';
    }

    private function getAudioTargetExtension($toolName)
    {
        // Map audio tool names to target extensions
        $toolMap = [
            'mp3-to-wav' => 'wav',
            'wav-to-mp3' => 'mp3',
            'flac-to-mp3' => 'mp3',
            'aac-to-mp3' => 'mp3',
            'ogg-to-mp3' => 'mp3',
            'mp3-to-flac' => 'flac',
            'compress-audio' => 'mp3',
            'trim-audio' => null, // Same as original
            'merge-audio' => 'mp3',
            'normalize-audio' => null, // Same as original
            'extract-vocals' => null, // Same as original
        ];

        return $toolMap[$toolName] ?? 'mp3';
    }

    private function formatBytes($bytes)
    {
        if ($bytes === 0) return '0 B';
        
        $k = 1024;
        $sizes = ['B', 'KB', 'MB', 'GB'];
        $i = floor(log($bytes) / log($k));
        
        return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
    }
}
