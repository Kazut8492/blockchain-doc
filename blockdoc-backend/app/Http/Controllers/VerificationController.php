<?php

// app/Http/Controllers/VerificationController.php - Verification controller
namespace App\Http\Controllers;

use App\Models\Document;
use Illuminate\Http\Request;
use App\Services\BlockchainService;

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
            'document' => 'required|file|mimes:pdf|max:10240', // 10MB max
        ]);

        $file = $request->file('document');
        
        // Generate SHA512 hash of the file
        $fileContent = file_get_contents($file->getRealPath());
        $hash = hash('sha512', $fileContent);
        
        // Check if this document hash exists in our database
        $document = Document::where('hash', $hash)
            ->where('blockchain_status', 'confirmed')
            ->first();
            
        if (!$document) {
            // Document not found or not confirmed on blockchain
            return response()->json([
                'verified' => false,
                'hash' => $hash
            ]);
        }
        
        // Verify the hash on the blockchain
        $verified = $this->blockchainService->verifyDocumentHash($hash, $document->transaction_hash);
        
        if ($verified) {
            return response()->json([
                'verified' => true,
                'hash' => $hash,
                'timestamp' => $document->blockchain_timestamp,
                'transaction_hash' => $document->transaction_hash
            ]);
        } else {
            // Blockchain verification failed
            return response()->json([
                'verified' => false,
                'hash' => $hash
            ]);
        }
    }
}