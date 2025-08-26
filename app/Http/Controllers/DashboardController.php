<?php

namespace App\Http\Controllers;

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
            'total_processings' => $user->total_processings,
            'completed_processings' => $user->completed_processings,
            'today_processings' => $user->today_processings,
            'storage_used' => $user->storage_used_human,
            'recent_conversions' => $user->pdfProcessings()
                ->latest()
                ->limit(5)
                ->get(),
            'show_email_notice' => $showEmailNotice,
        ];

        return view('dashboard.index', compact('stats'));
    }
}
