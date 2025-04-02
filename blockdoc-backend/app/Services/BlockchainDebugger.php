<?php

namespace App\Services;

use App\Models\Document;
use Illuminate\Support\Facades\Log;

class BlockchainDebugger
{
    /**
     * Manually attempt to register a document (for debugging)
     */
    public static function attemptDirectRegistration($documentId)
    {
        try {
            $document = Document::findOrFail($documentId);
            
            if ($document->blockchain_status !== 'pending') {
                return [
                    'status' => 'error',
                    'message' => "Document is not in pending state (current: {$document->blockchain_status})"
                ];
            }
            
            // Log detailed info
            Log::info("Attempting direct blockchain registration of document ID: {$documentId}", [
                'document_hash' => $document->hash,
                'size' => $document->size,
                'filename' => $document->filename
            ]);
            
            // Create blockchain service
            $blockchainService = app(BlockchainService::class);
            
            // Attempt registration with detailed logging
            $result = $blockchainService->registerDocument($document);
            
            // Check result
            if ($result) {
                return [
                    'status' => 'success',
                    'message' => 'Document registration initiated',
                    'transaction_hash' => $document->transaction_hash
                ];
            } else {
                return [
                    'status' => 'error',
                    'message' => 'Document registration failed',
                    'document' => $document
                ];
            }
            
        } catch (\Exception $e) {
            Log::error('Error in direct registration attempt: ' . $e->getMessage(), [
                'document_id' => $documentId,
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'status' => 'error',
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ];
        }
    }

    /**
     * Placeholder for diagnose method
     */
    public static function diagnose()
    {
        // Simple placeholder for now
        return [
            'configuration' => ['status' => 'info', 'details' => []],
            'connectivity' => ['status' => 'info', 'details' => []],
            'pending_documents' => ['status' => 'info', 'details' => []],
            'queue' => ['status' => 'info', 'details' => []]
        ];
    }
}