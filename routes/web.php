<?php

use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\CsrfTokenController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::get('/', function () {
    return view('react-home');
})->name('home');

Route::get('/blade-home', [HomeController::class, 'index'])->name('blade.home');

// CSRF token refresh route
Route::get('/csrf-token', [CsrfTokenController::class, 'getToken'])->name('csrf.token');

// Authentication routes with relaxed rate limiting
Route::middleware(['guest'])->group(function () {
    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])
        ->middleware('throttle:10,1');
    
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])
        ->middleware('throttle:20,1');
    
    Route::get('/forgot-password', [PasswordResetController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'store'])
        ->middleware('throttle:10,1')->name('password.email');
    
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'edit'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'update'])
        ->middleware('throttle:20,1')->name('password.update');
});

// Email verification routes with relaxed rate limiting
Route::middleware(['auth'])->group(function () {
    Route::get('/email/verify', [EmailVerificationController::class, 'notice'])->name('verification.notice');
    Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware(['signed', 'throttle:20,1'])
        ->name('verification.verify');
    Route::post('/email/verification-notification', [EmailVerificationController::class, 'send'])
        ->middleware('throttle:10,1')
        ->name('verification.send');
});

// Protected routes - allow password reset users to access dashboard without email verification
Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    
    // Conversion tool routes (protected)
    Route::prefix('tools')->name('tools.')->group(function () {
        Route::get('/documents', [App\Http\Controllers\DocumentController::class, 'index'])->name('documents');
        Route::get('/images', function () { return view('tools.images'); })->name('images');
        Route::get('/audio', function () { return view('tools.audio'); })->name('audio');
        Route::get('/video', function () { return view('tools.video'); })->name('video');
    });
    
    // Document conversion API routes
    Route::prefix('api/documents')->name('documents.')->group(function () {
        Route::post('/upload', [App\Http\Controllers\DocumentController::class, 'upload'])->name('upload');
        Route::post('/convert', [App\Http\Controllers\DocumentController::class, 'convert'])->name('convert');
        Route::get('/status/{id}', [App\Http\Controllers\DocumentController::class, 'status'])->name('status');
        Route::get('/download/{id}', [App\Http\Controllers\DocumentController::class, 'download'])->name('download');
        Route::get('/history', [App\Http\Controllers\DocumentController::class, 'history'])->name('history');
    });
    
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
});
