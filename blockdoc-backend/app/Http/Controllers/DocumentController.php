<?php

// app/Http/Controllers/DocumentController.php - Document controller
namespace App\Http\Controllers;

use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Services\BlockchainService;
use App\Jobs\RegisterDocumentOnBlockchain;

class DocumentController extends Controller
{
    public function index(Request $request)
    {
        $documents = Document::orderBy('created_at', 'desc')->get();
        return response()->json($documents->toArray());
    }

    public function store(Request $request)
    {
        $request->validate([
            'document' => 'required|file|mimes:pdf|max:10240', // 10MB max
        ]);

        $file = $request->file('document');
        $filename = $file->getClientOriginalName();
        $path = $file->store('documents');
        
        // Generate SHA512 hash of the file
        $fileContent = file_get_contents($file->getRealPath());
        $hash = hash('sha512', $fileContent);
        
        // Save document in database
        $document = Document::create([
            'user_id' => null,
            'filename' => $filename,
            'path' => $path,
            'hash' => $hash,
            'blockchain_status' => 'pending',
        ]);
        
        // Queue job to register document on blockchain
        RegisterDocumentOnBlockchain::dispatch($document);
        
        return response()->json($document, 201);
    }

    public function show(Document $document, Request $request)
    {
        return response()->json($document);
    }

    public function download(Document $document, Request $request)
    {
        if (!Storage::exists($document->path)) {
            return response()->json(['message' => 'File not found'], 404);
        }
        
        return Storage::download($document->path, $document->filename);
    }
}