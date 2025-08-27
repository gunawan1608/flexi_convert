<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PDFToolsController;

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

// PDF Tools API routes (tanpa auth untuk testing)
Route::post('/pdf-tools/process', [PDFToolsController::class, 'process']);
Route::get('/pdf-tools/download/{id}', [PDFToolsController::class, 'download']);

// Image Tools API routes (tanpa auth untuk testing)
Route::post('/image-tools/process', [App\Http\Controllers\ImageToolsController::class, 'process']);
Route::get('/image-tools/download/{id}', [App\Http\Controllers\ImageToolsController::class, 'download']);
