<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\VerificationController;
use App\Http\Controllers\ChunkedUploadController;
use App\Http\Controllers\SystemController;

// Public routes for testing (no authentication required)
Route::get('/documents', [DocumentController::class, 'index']);
Route::post('/documents', [DocumentController::class, 'store']);
Route::get('/documents/{document}', [DocumentController::class, 'show']);
Route::get('/documents/{document}/download', [DocumentController::class, 'download']);
Route::post('/verify', [VerificationController::class, 'verify']);
Route::post('/upload-chunk', [ChunkedUploadController::class, 'uploadChunk'])->middleware('auth:sanctum');

// システム情報
Route::get('/system/max-upload-size', [SystemController::class, 'getMaxUploadSize']);

// 通常のアップロードとベリフィケーション
Route::post('/documents', [DocumentController::class, 'store']);
Route::post('/verify', [VerificationController::class, 'verify']);

// チャンクアップロードのルート
Route::post('/upload-chunk', [ChunkedUploadController::class, 'upload']);
Route::post('/verify-chunk', [VerificationController::class, 'verifyChunk']);

// You can restore authentication later with:
// Route::middleware('auth:sanctum')->group(function () {
//     // Protected routes here
// });