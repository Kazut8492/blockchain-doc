<?php

namespace App\Http\Controllers;

use App\Models\Document;
use Illuminate\Http\Request;
use App\Services\BlockchainService;
use Illuminate\Support\Facades\Log;

class VerificationController extends Controller
{
    protected $blockchainService;
    
    public function __construct(BlockchainService $blockchainService)
    {
        $this->blockchainService = $blockchainService;
    }
    
    /**
     * Verify uploaded document against blockchain
     */
    public function verify(Request $request)
    {
        $maxUploadSize = $this->getMaximumFileUploadSize();
        
        // Validate request with dynamic max file size based on PHP settings
        $request->validate([
            'document' => 'required|file|mimes:pdf|max:' . ($maxUploadSize / 1024),
        ]);

        $file = $request->file('document');
        
        // Generate SHA512 hash of the file using streaming to handle large files
        $hashContext = hash_init('sha512');
        $handle = fopen($file->getRealPath(), 'rb');
        while (!feof($handle)) {
            $buffer = fread($handle, 8192);
            hash_update($hashContext, $buffer);
        }
        fclose($handle);
        $hash = hash_final($hashContext);
        
        // Log the hash for debugging
        Log::info('Verifying document with hash: ' . $hash);
        
        // IMPORTANT: First verify directly on blockchain regardless of database
        // This ensures we're checking the true blockchain state
        $blockchainVerified = $this->blockchainService->verifyDocumentHash($hash);
        
        // If verified on blockchain, we can return success immediately
        if ($blockchainVerified) {
            // Try to get document from database for additional info
            $document = Document::where('hash', $hash)->first();
            
            return response()->json([
                'verified' => true,
                'message' => 'Document verification successful on blockchain',
                'hash' => $hash,
                'timestamp' => $document ? $document->blockchain_timestamp : now(),
                'transaction_hash' => $document ? $document->transaction_hash : 'Unknown',
                'network' => $document ? 
                    ($document->blockchain_network ?? config('blockchain.network_name', 'Sepolia Testnet')) : 
                    config('blockchain.network_name', 'Sepolia Testnet')
            ]);
        }
        
        // If not verified on blockchain, check database for more detailed status
        $document = Document::where('hash', $hash)->first();
            
        if (!$document) {
            // Document not found in our database and not on blockchain
            Log::warning('Document not found in database or blockchain with hash: ' . $hash);
            return response()->json([
                'verified' => false,
                'message' => 'This document has not been registered on our blockchain',
                'hash' => $hash
            ]);
        }
        
        // Log document status
        Log::info('Document found in database with status: ' . ($document->blockchain_status ?? 'null'));
        
        // If document exists in database but not on blockchain, check status
        if ($document->blockchain_status === 'pending') {
            return response()->json([
                'verified' => false,
                'message' => 'This document is registered but not yet confirmed on blockchain',
                'hash' => $hash,
                'status' => 'pending'
            ]);
        } else if ($document->blockchain_status === 'failed') {
            return response()->json([
                'verified' => false,
                'message' => 'This document registration failed on blockchain',
                'hash' => $hash,
                'status' => 'failed'
            ]);
        } else {
            // Document marked as confirmed in database but not found on blockchain
            // This indicates a potential issue with the blockchain data
            Log::warning('Document marked as confirmed in database but not found on blockchain: ' . $hash);
            
            // Try to verify using transaction hash as fallback
            if ($document->transaction_hash) {
                // 統合されたcheckTransactionメソッドを使用
                $txVerified = $this->blockchainService->checkTransaction($document);
                
                if ($txVerified) {
                    return response()->json([
                        'verified' => true,
                        'message' => 'Document verified by transaction, but not found in contract data',
                        'hash' => $hash,
                        'timestamp' => $document->blockchain_timestamp,
                        'transaction_hash' => $document->transaction_hash,
                        'network' => $document->blockchain_network ?? config('blockchain.network_name', 'Sepolia Testnet'),
                        'warning' => 'Contract data inconsistency detected'
                    ]);
                }
            }
            
            return response()->json([
                'verified' => false,
                'message' => 'This document is marked as confirmed in our database but could not be verified on the blockchain',
                'hash' => $hash,
                'status' => 'verification_failed'
            ]);
        }
    }
    
    /**
     * Handle verification with chunk upload for large files
     */
    public function verifyChunk(Request $request)
    {
        $request->validate([
            'chunk' => 'required',
            'index' => 'required|integer',
            'totalChunks' => 'required|integer',
            'filename' => 'required|string',
            'chunkId' => 'required|string',
        ]);
        
        $chunkId = $request->input('chunkId');
        $index = $request->input('index');
        $totalChunks = $request->input('totalChunks');
        $filename = $request->input('filename');
        
        // Create temp directory if it doesn't exist
        $tempDir = storage_path('app/temp/chunks/' . $chunkId);
        if (!file_exists($tempDir)) {
            mkdir($tempDir, 0755, true);
        }
        
        // Save this chunk to temp directory
        $chunk = $request->file('chunk');
        $chunkPath = $tempDir . '/' . $index;
        $chunk->move($tempDir, $index);
        
        // If this is not the last chunk, return status
        if ($index < $totalChunks - 1) {
            return response()->json([
                'status' => 'chunk_received',
                'message' => 'Chunk received successfully',
                'chunksReceived' => $index + 1,
                'totalChunks' => $totalChunks
            ]);
        }
        
        // If this is the last chunk, process the complete file
        $hashContext = hash_init('sha512');
        
        // Process each chunk in order
        for ($i = 0; $i < $totalChunks; $i++) {
            $chunkPath = $tempDir . '/' . $i;
            $handle = fopen($chunkPath, 'rb');
            
            if (!$handle) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Failed to process chunks'
                ], 500);
            }
            
            while (!feof($handle)) {
                $buffer = fread($handle, 8192);
                hash_update($hashContext, $buffer);
            }
            
            fclose($handle);
        }
        
        // Get the final hash
        $hash = hash_final($hashContext);
        
        // Clean up the temp chunks
        $this->cleanupChunks($tempDir);
        
        // Now verify the hash on blockchain
        $blockchainVerified = $this->blockchainService->verifyDocumentHash($hash);
        
        // Process verification result (similar to normal verify method)
        if ($blockchainVerified) {
            $document = Document::where('hash', $hash)->first();
            
            return response()->json([
                'verified' => true,
                'message' => 'Document verification successful on blockchain',
                'hash' => $hash,
                'timestamp' => $document ? $document->blockchain_timestamp : now(),
                'transaction_hash' => $document ? $document->transaction_hash : 'Unknown',
                'network' => $document ? 
                    ($document->blockchain_network ?? config('blockchain.network_name', 'Sepolia Testnet')) : 
                    config('blockchain.network_name', 'Sepolia Testnet')
            ]);
        }
        
        // Check database for more info
        $document = Document::where('hash', $hash)->first();
        
        if (!$document) {
            return response()->json([
                'verified' => false,
                'message' => 'This document has not been registered on our blockchain',
                'hash' => $hash
            ]);
        }
        
        // Logic for different database statuses - same as regular verify
        if ($document->blockchain_status === 'pending') {
            return response()->json([
                'verified' => false,
                'message' => 'This document is registered but not yet confirmed on blockchain',
                'hash' => $hash,
                'status' => 'pending'
            ]);
        } else if ($document->blockchain_status === 'failed') {
            return response()->json([
                'verified' => false,
                'message' => 'This document registration failed on blockchain',
                'hash' => $hash,
                'status' => 'failed'
            ]);
        } else {
            // Fallback transaction verification
            if ($document->transaction_hash) {
                $txVerified = $this->blockchainService->checkTransaction($document);
                
                if ($txVerified) {
                    return response()->json([
                        'verified' => true,
                        'message' => 'Document verified by transaction, but not found in contract data',
                        'hash' => $hash,
                        'timestamp' => $document->blockchain_timestamp,
                        'transaction_hash' => $document->transaction_hash,
                        'network' => $document->blockchain_network ?? config('blockchain.network_name', 'Sepolia Testnet'),
                        'warning' => 'Contract data inconsistency detected'
                    ]);
                }
            }
            
            return response()->json([
                'verified' => false,
                'message' => 'This document is marked as confirmed in our database but could not be verified on the blockchain',
                'hash' => $hash,
                'status' => 'verification_failed'
            ]);
        }
    }
    
    /**
     * Helper to clean up temp chunk files
     */
    private function cleanupChunks($tempDir)
    {
        if (is_dir($tempDir)) {
            $files = scandir($tempDir);
            foreach ($files as $file) {
                if ($file != "." && $file != "..") {
                    unlink($tempDir . '/' . $file);
                }
            }
            rmdir($tempDir);
        }
    }
    
    /**
     * Get maximum upload file size based on PHP settings
     */
    private function getMaximumFileUploadSize()
    {
        $upload_max_filesize = $this->parseSize(ini_get('upload_max_filesize'));
        $post_max_size = $this->parseSize(ini_get('post_max_size'));
        return min($upload_max_filesize, $post_max_size);
    }
    
    /**
     * Convert PHP size string to bytes
     */
    private function parseSize($size)
    {
        $unit = preg_replace('/[^bkmgtpezy]/i', '', $size);
        $size = preg_replace('/[^0-9\.]/', '', $size);
        if ($unit) {
            return round($size * pow(1024, stripos('bkmgtpezy', $unit[0])));
        }
        return round($size);
    }
}