<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AudioToolsController;
use App\Http\Controllers\ImageToolsController;
use App\Http\Controllers\PDFToolsController;
use App\Http\Controllers\VideoToolsController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// PDF tool APIs also use the web middleware stack so same-origin requests can
// reuse the browser session and attribute conversions to the signed-in user.
Route::middleware('web')->prefix('pdf-tools')->name('pdf-tools.')->group(function () {
    Route::get('/health', [PDFToolsController::class, 'health'])->name('health');
    Route::post('/process', [PDFToolsController::class, 'process'])->name('process');
    Route::get('/download/{id}', [PDFToolsController::class, 'download'])->name('download');
});

Route::prefix('image-tools')->name('image-tools.')->group(function () {
    Route::post('/process', [ImageToolsController::class, 'process'])->name('process');
    Route::get('/download/{id}', [ImageToolsController::class, 'download'])->name('download');
});

Route::prefix('audio-tools')->name('audio-tools.')->group(function () {
    Route::post('/process', [AudioToolsController::class, 'process'])->name('process');
    Route::get('/download/{id}', [AudioToolsController::class, 'download'])->name('download');
});

Route::prefix('video-tools')->name('video-tools.')->group(function () {
    Route::post('/process', [VideoToolsController::class, 'process'])->name('process');
    Route::get('/download/{id}', [VideoToolsController::class, 'download'])->name('download');
});
