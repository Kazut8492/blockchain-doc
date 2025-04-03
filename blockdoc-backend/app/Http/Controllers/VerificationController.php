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
    
    public function verify(Request $request)
    {
        $request->validate([
            'document' => 'required|file|mimes:pdf|max:102400', // 100MB max
        ]);

        $file = $request->file('document');
        
        // Generate SHA512 hash of the file
        $fileContent = file_get_contents($file->getRealPath());
        $hash = hash('sha512', $fileContent);
        
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
                $txVerified = $this->blockchainService->checkTransactionStatus($document->transaction_hash);
                
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
}