<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;
use App\Models\PdfProcessing;
use Carbon\Carbon;

class UserController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function getProfile()
    {
        try {
            $user = Auth::user();
            
            return response()->json([
                'success' => true,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'company' => $user->company,
                    'bio' => $user->bio,
                    'timezone' => $user->timezone ?? 'Asia/Jakarta',
                    'language' => $user->language ?? 'en',
                    'email_notifications' => $user->email_notifications ?? true,
                    'marketing_emails' => $user->marketing_emails ?? false,
                    'created_at' => $user->created_at,
                    'email_verified_at' => $user->email_verified_at
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch profile: ' . $e->getMessage()
            ], 500);
        }
    }

    public function updateProfile(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users,email,' . Auth::id(),
                'phone' => 'nullable|string|max:20',
                'company' => 'nullable|string|max:255',
                'bio' => 'nullable|string|max:1000',
                'timezone' => 'nullable|string|max:50',
                'language' => 'nullable|string|max:10',
                'emailNotifications' => 'boolean',
                'marketingEmails' => 'boolean'
            ]);

            $user = Auth::user();
            $user->update([
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'company' => $request->company,
                'bio' => $request->bio,
                'timezone' => $request->timezone ?? 'Asia/Jakarta',
                'language' => $request->language ?? 'en',
                'email_notifications' => $request->emailNotifications ?? true,
                'marketing_emails' => $request->marketingEmails ?? false
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Profile updated successfully',
                'user' => $user->fresh()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update profile: ' . $e->getMessage(),
                'errors' => $e instanceof \Illuminate\Validation\ValidationException ? $e->errors() : []
            ], 422);
        }
    }

    public function changePassword(Request $request)
    {
        try {
            $request->validate([
                'current_password' => 'required',
                'password' => ['required', 'confirmed', Password::defaults()],
            ]);

            $user = Auth::user();

            if (!Hash::check($request->current_password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Current password is incorrect',
                    'errors' => ['current_password' => ['Current password is incorrect']]
                ], 422);
            }

            $user->update([
                'password' => Hash::make($request->password)
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Password changed successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to change password: ' . $e->getMessage(),
                'errors' => $e instanceof \Illuminate\Validation\ValidationException ? $e->errors() : []
            ], 422);
        }
    }

    public function getStats()
    {
        try {
            $user = Auth::user();
            
            // Get conversion statistics
            $totalConversions = PdfProcessing::where('user_id', $user->id)->count();
            $completedConversions = PdfProcessing::where('user_id', $user->id)
                ->where('status', 'completed')
                ->count();
            $processingConversions = PdfProcessing::where('user_id', $user->id)
                ->where('status', 'processing')
                ->count();
            $failedConversions = PdfProcessing::where('user_id', $user->id)
                ->where('status', 'failed')
                ->count();

            // Calculate files processed (assuming each processing record = 1 file)
            $filesProcessed = $completedConversions;

            // Calculate storage usage (rough estimate)
            $storageUsed = PdfProcessing::where('user_id', $user->id)
                ->where('status', 'completed')
                ->sum('file_size') ?? 0;
            
            $storageUsedFormatted = $this->formatBytes($storageUsed);

            // Member since
            $memberSince = $user->created_at ? $user->created_at->format('M Y') : 'Unknown';

            return response()->json([
                'success' => true,
                'stats' => [
                    'totalConversions' => $totalConversions,
                    'filesProcessed' => $filesProcessed,
                    'storageUsed' => $storageUsedFormatted,
                    'memberSince' => $memberSince,
                    'completed' => $completedConversions,
                    'processing' => $processingConversions,
                    'failed' => $failedConversions,
                    'total' => $totalConversions
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch stats: ' . $e->getMessage()
            ], 500);
        }
    }

    public function deleteAccount()
    {
        try {
            $user = Auth::user();
            
            // Delete user's conversion files from storage
            $conversions = PdfProcessing::where('user_id', $user->id)->get();
            foreach ($conversions as $conversion) {
                if ($conversion->processed_filename) {
                    $filePath = 'pdf-tools/outputs/' . $conversion->processed_filename;
                    if (Storage::exists($filePath)) {
                        Storage::delete($filePath);
                    }
                }
            }

            // Delete conversion records
            PdfProcessing::where('user_id', $user->id)->delete();

            // Delete user account
            $user->delete();

            // Logout
            Auth::logout();

            return response()->json([
                'success' => true,
                'message' => 'Account deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete account: ' . $e->getMessage()
            ], 500);
        }
    }

    private function formatBytes($bytes, $precision = 2)
    {
        if ($bytes == 0) return '0 MB';
        
        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
}
