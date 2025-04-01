<?php

// app/Jobs/RegisterDocumentOnBlockchain.php - Blockchain registration job
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
            $success = $blockchainService->registerDocument($this->document);
            
            if (!$success) {
                // Retry with exponential backoff
                $this->release(30);
                Log::warning('Document registration on blockchain failed, retrying. Document ID: ' . $this->document->id);
            }
        } catch (\Exception $e) {
            Log::error('Error in RegisterDocumentOnBlockchain job: ' . $e->getMessage());
            $this->fail($e);
        }
    }
}