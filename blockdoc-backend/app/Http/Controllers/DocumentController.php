<?php

namespace App\Http\Controllers;

use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use App\Services\BlockchainService;
use Illuminate\Support\Facades\Log;
use App\Jobs\RegisterDocumentOnBlockchain;

class DocumentController extends Controller
{
    protected $blockchainService;
    
    public function __construct(BlockchainService $blockchainService)
    {
        $this->blockchainService = $blockchainService;
    }
    
    /**
     * Upload and register a new document
     */
    public function store(Request $request)
    {
        $maxUpload = $this->getMaximumFileUploadSize();
    
        $request->validate([
            'document' => 'required|file|mimes:pdf|max:' . ($maxUpload / 1024),
        ]);
    
        try {
            $file = $request->file('document');
            $filename = time() . '_' . $file->getClientOriginalName();
            
            // Store file
            $path = $file->storeAs('documents', $filename);
            
            // Calculate file hash using streaming
            $hashContext = hash_init('sha512');
            $handle = fopen(storage_path('app/private/' . $path), 'rb');
            if ($handle === false) {
                throw new \Exception("Failed to open file for hashing");
            }
            while (!feof($handle)) {
                $buffer = fread($handle, 8192);
                hash_update($hashContext, $buffer);
            }
            fclose($handle);
            $hash = hash_final($hashContext);
            
            // Check if document with this hash already exists
            $existingDocument = Document::where('hash', $hash)->first();
            
            if ($existingDocument) {
                return response()->json([
                    'message' => 'This document has already been registered',
                    'document' => $existingDocument
                ], 400);
            }
            
            // Create document record
            $document = new Document();
            $document->filename = $filename;
            $document->original_filename = $file->getClientOriginalName();
            $document->mime_type = $file->getMimeType();
            $document->size = $file->getSize();
            $document->hash = $hash;
            $document->path = $path;
            $document->user_id = null;
            $document->blockchain_status = 'pending';
            $document->blockchain_network = config('blockchain.network_name', 'Sepolia Testnet');
            $document->save();
            
            Log::info('Document saved with hash: ' . $hash);
            
            // Dispatch job to register document on blockchain (asynchronously)
            RegisterDocumentOnBlockchain::dispatch($document);
            
            return response()->json([
                'message' => 'Document uploaded and blockchain registration initiated',
                'document' => $document
            ], 201);
            
        } catch (\Exception $e) {
            Log::error('Error uploading document: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Error uploading document: ' . $e->getMessage()
            ], 500);
        }
    }
    
    // Helper method to get maximum PHP upload size
    private function getMaximumFileUploadSize()
    {
        // Calculate maximum size from PHP settings
        $upload_max_filesize = $this->parseSize(ini_get('upload_max_filesize'));
        $post_max_size = $this->parseSize(ini_get('post_max_size'));
        return min($upload_max_filesize, $post_max_size);
    }

    private function parseSize($size)
    {
        $unit = preg_replace('/[^bkmgtpezy]/i', '', $size);
        $size = preg_replace('/[^0-9\.]/', '', $size);
        if ($unit) {
            return round($size * pow(1024, stripos('bkmgtpezy', $unit[0])));
        }
        return round($size);
    }

    /**
     * Display the specified document
     */
    public function show(Document $document, Request $request)
    {
        return response()->json($document);
    }

    /**
     * Download the specified document
     */
    public function download(Document $document, Request $request)
    {
        try {
            // Debug path information
            Log::info('Requested file path: ' . $document->path);
            Log::info('Full storage path: ' . storage_path('app/' . $document->path));
            
            if (!Storage::exists($document->path)) {
                Log::error('File not found: ' . $document->path);
                return response()->json(['message' => 'File not found'], 404);
            }
            
            return Storage::download($document->path, $document->original_filename);
        } catch (\Exception $e) {
            Log::error('Download error: ' . $e->getMessage());
            return response()->json(['message' => 'Error downloading file: ' . $e->getMessage()], 500);
        }
    }
    
    /**
     * Check status of a document's blockchain registration
     */
    public function checkStatus($id)
    {
        $document = Document::findOrFail($id);
        
        // If already confirmed, just return the status
        if ($document->blockchain_status === 'confirmed') {
            return response()->json([
                'status' => $document->blockchain_status,
                'transaction_hash' => $document->transaction_hash,
                'timestamp' => $document->blockchain_timestamp
            ]);
        }
        
        // If has transaction hash but not confirmed, check confirmation
        if ($document->transaction_hash) {
            $confirmed = $this->blockchainService->checkTransaction($document);
            
            // Refresh document data
            $document->refresh();
            
            return response()->json([
                'status' => $document->blockchain_status,
                'transaction_hash' => $document->transaction_hash,
                'timestamp' => $document->blockchain_timestamp
            ]);
        }
        
        // If no transaction hash yet, still pending
        return response()->json([
            'status' => $document->blockchain_status
        ]);
    }
    
    /**
     * List all documents
     */
    public function index(Request $request)
    {
        // Get user's documents with pagination
        $documents = Document::where('user_id', Auth::id())
            ->orderBy('created_at', 'desc')
            ->paginate(10);
            
        return response()->json($documents);
    }
}