<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\VerificationController;
use App\Http\Controllers\FiscalEntryController;

// All routes will use the TestAuthMiddleware automatically
// so we don't need to specify auth middleware

// Fiscal Entries routes
Route::get('/fiscal-entries', [FiscalEntryController::class, 'index']);
Route::post('/fiscal-entries', [FiscalEntryController::class, 'store']);
Route::get('/fiscal-entries/{entry}', [FiscalEntryController::class, 'show']);
Route::put('/fiscal-entries/{entry}', [FiscalEntryController::class, 'update']);
Route::delete('/fiscal-entries/{entry}', [FiscalEntryController::class, 'destroy']);

// Document routes
Route::get('/documents', [DocumentController::class, 'index']);
Route::post('/documents', [DocumentController::class, 'store']);
Route::get('/documents/{document}', [DocumentController::class, 'show']);
Route::get('/documents/{document}/download', [DocumentController::class, 'download']);
Route::get('/documents/{document}/status', [DocumentController::class, 'checkStatus']);

// Verification route
Route::post('/verify', [VerificationController::class, 'verify']);

// Get current user info (for demonstration)
Route::get('/user', function (Request $request) {
    return $request->user();
});