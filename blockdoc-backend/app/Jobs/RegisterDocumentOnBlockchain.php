<?php

namespace App\Jobs;

use App\Models\Document;
use App\Services\BlockchainService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RegisterDocumentOnBlockchain implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    
    protected $document;
    
    /**
     * Number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 5;
    
    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var int
     */
    public $backoff = [30, 60, 120, 300, 600];
    
    /**
     * Create a new job instance.
     *
     * @param  \App\Models\Document  $document
     * @return void
     */
    public function __construct(Document $document)
    {
        $this->document = $document;
    }
    
    /**
     * Execute the job.
     *
     * @param  \App\Services\BlockchainService  $blockchainService
     * @return void
     */
    public function handle(BlockchainService $blockchainService)
    {
        try {
            Log::info('Starting blockchain registration for document ID: ' . $this->document->id);
            
            // Reload the document to ensure we have the latest state
            $document = Document::find($this->document->id);
            
            // Check if document is already registered or failed
            if ($document->blockchain_status !== 'pending') {
                Log::info('Document ID: ' . $document->id . ' is already in ' . $document->blockchain_status . ' state. Skipping registration.');
                return;
            }
            
            $success = $blockchainService->registerDocument($document);
            
            if (!$success) {
                if ($this->attempts() < $this->tries) {
                    Log::warning('Document registration on blockchain failed, retrying. Document ID: ' . $document->id . ', Attempt: ' . $this->attempts());
                    $this->release($this->backoff[$this->attempts() - 1] ?? 600);
                } else {
                    Log::error('Document registration on blockchain failed after ' . $this->tries . ' attempts. Document ID: ' . $document->id);
                    
                    // Update document status to failed
                    $document->blockchain_status = 'failed';
                    $document->save();
                }
            } else {
                Log::info('Successfully submitted document ID: ' . $document->id . ' to blockchain. Transaction hash: ' . $document->transaction_hash);
            }
        } catch (\Exception $e) {
            Log::error('Error in RegisterDocumentOnBlockchain job for document ID: ' . $this->document->id . ': ' . $e->getMessage());
            
            // Update document status to failed on error
            $document = Document::find($this->document->id);
            if ($document) {
                $document->blockchain_status = 'failed';
                $document->save();
            }
            
            $this->fail($e);
        }
    }
}