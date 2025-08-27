<?php

use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\CsrfTokenController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentController;
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
    
    // Profile and History routes
    Route::get('/profile', function () { return view('profile.index'); })->name('profile');
    Route::get('/history', function () { return view('history.index'); })->name('history');
    
    // Conversion tool routes (protected)
    Route::prefix('tools')->name('tools.')->group(function () {
        Route::get('/documents', function () {
            return view('tools.documents');
        })->name('documents');
        Route::get('/images', function () { return view('tools.images'); })->name('images');
        Route::get('/audio', function () { return view('tools.audio'); })->name('audio');
        Route::get('/video', function () { return view('tools.video'); })->name('video');
    });
    
    
    // User API routes
    Route::prefix('api/user')->name('user.')->group(function () {
        Route::get('/profile', [App\Http\Controllers\UserController::class, 'getProfile'])->name('profile.get');
        Route::put('/profile', [App\Http\Controllers\UserController::class, 'updateProfile'])->name('profile.update');
        Route::put('/change-password', [App\Http\Controllers\UserController::class, 'changePassword'])->name('password.change');
        Route::get('/stats', [App\Http\Controllers\UserController::class, 'getStats'])->name('stats');
        Route::delete('/delete-account', [App\Http\Controllers\UserController::class, 'deleteAccount'])->name('delete');
    });
    
    // Conversion history API routes
    Route::prefix('api/conversions')->name('conversions.')->group(function () {
        Route::get('/history', [App\Http\Controllers\ConversionController::class, 'getHistory'])->name('history');
        Route::get('/stats', [App\Http\Controllers\ConversionController::class, 'getStats'])->name('stats');
        Route::get('/download/{id}', [App\Http\Controllers\ConversionController::class, 'download'])->name('download');
        Route::delete('/{id}', [App\Http\Controllers\ConversionController::class, 'delete'])->name('delete');
    });
    
    // PDF Tools API routes
    Route::prefix('api/pdf-tools')->name('pdf-tools.')->group(function () {
        Route::post('/process', [App\Http\Controllers\PDFToolsController::class, 'process'])->name('process');
        Route::get('/download/{id}', [App\Http\Controllers\PDFToolsController::class, 'download'])->name('download');
    });
    
    // Document conversion routes
    Route::get('/documents/download/{id}', [DocumentController::class, 'download'])->name('documents.download');
    
    // Image Tools API routes
    Route::prefix('api/image-tools')->name('image-tools.')->group(function () {
        Route::post('/process', [App\Http\Controllers\ImageToolsController::class, 'process'])->name('process');
        Route::get('/download/{id}', [App\Http\Controllers\ImageToolsController::class, 'download'])->name('download');
    });
    
    // Audio Tools API routes
    Route::prefix('api/audio-tools')->name('audio-tools.')->group(function () {
        Route::post('/process', [App\Http\Controllers\AudioToolsController::class, 'process'])->name('process');
        Route::get('/download/{filename}', [App\Http\Controllers\AudioToolsController::class, 'download'])->name('download');
    });
    
    // Video Tools API routes
    Route::prefix('api/video-tools')->name('video-tools.')->group(function () {
        Route::post('/process', [App\Http\Controllers\VideoToolsController::class, 'process'])->name('process');
        Route::get('/download/{filename}', [App\Http\Controllers\VideoToolsController::class, 'download'])->name('download');
    });
    
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
});
