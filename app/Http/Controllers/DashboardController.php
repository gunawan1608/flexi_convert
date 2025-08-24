<?php

namespace App\Http\Controllers;

use App\Models\Conversion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $user = Auth::user();
        
        // Show email verification notice if not verified, but allow access
        $showEmailNotice = !$user->hasVerifiedEmail();
        
        $stats = [
            'total_conversions' => $user->total_conversions ?? 0,
            'storage_used' => $user->storage_used_human ?? '0 B',
            'recent_conversions' => $user->conversions()
                ->latest()
                ->limit(5)
                ->get(),
            'completed_today' => $user->conversions()
                ->where('status', 'completed')
                ->whereDate('completed_at', today())
                ->count(),
            'show_email_notice' => $showEmailNotice,
        ];

        return view('dashboard.index', compact('stats'));
    }
}
