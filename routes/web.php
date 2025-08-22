<?php

use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::get('/', function () {
    return view('react-home');
})->name('home');

Route::get('/blade-home', [HomeController::class, 'index'])->name('blade.home');

// Authentication routes with improved session handling
Route::middleware(['guest', 'throttle:10,1'])->group(function () {
    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])
        ->middleware('throttle:5,1');
    
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])
        ->middleware('throttle:5,1');
    
    Route::get('/forgot-password', [PasswordResetController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'store'])
        ->middleware('throttle:3,1')->name('password.email');
    
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'edit'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'update'])
        ->middleware('throttle:3,1')->name('password.update');
});

// Email verification routes with session protection
Route::middleware(['auth', 'throttle:10,1'])->group(function () {
    Route::get('/email/verify', [EmailVerificationController::class, 'notice'])->name('verification.notice');
    Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');
    Route::post('/email/verification-notification', [EmailVerificationController::class, 'send'])
        ->middleware('throttle:3,1')
        ->name('verification.send');
});

// Protected routes with enhanced session security
Route::middleware(['auth', 'verified', 'throttle:60,1'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    
    // Conversion tool routes (protected)
    Route::prefix('tools')->name('tools.')->group(function () {
        Route::get('/documents', function () { return view('tools.documents'); })->name('documents');
        Route::get('/images', function () { return view('tools.images'); })->name('images');
        Route::get('/audio', function () { return view('tools.audio'); })->name('audio');
        Route::get('/video', function () { return view('tools.video'); })->name('video');
    });
    
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout')
        ->middleware('throttle:10,1');
});
