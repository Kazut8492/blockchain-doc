<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\VerificationController;

// Public routes for testing (no authentication required)
Route::get('/documents', [DocumentController::class, 'index']);
Route::post('/documents', [DocumentController::class, 'store']);
Route::get('/documents/{document}', [DocumentController::class, 'show']);
Route::get('/documents/{document}/download', [DocumentController::class, 'download']);
Route::post('/verify', [VerificationController::class, 'verify']);

// You can restore authentication later with:
// Route::middleware('auth:sanctum')->group(function () {
//     // Protected routes here
// });