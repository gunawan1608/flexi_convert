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
        
        $stats = [
            'total_conversions' => $user->total_conversions,
            'storage_used' => $user->storage_used_human,
            'recent_conversions' => $user->conversions()
                ->latest()
                ->limit(5)
                ->get(),
            'completed_today' => $user->conversions()
                ->where('status', 'completed')
                ->whereDate('completed_at', today())
                ->count(),
        ];

        return view('dashboard.index', compact('stats'));
    }
}
