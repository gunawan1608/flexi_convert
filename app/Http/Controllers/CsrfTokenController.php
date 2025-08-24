<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CsrfTokenController extends Controller
{
    /**
     * Get a fresh CSRF token.
     */
    public function getToken(Request $request): JsonResponse
    {
        $request->session()->regenerateToken();
        
        return response()->json([
            'csrf_token' => csrf_token()
        ]);
    }
}
