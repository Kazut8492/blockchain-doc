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
    public $tries = 3;  // Number of times to attempt the job
    public $backoff = 10; // Seconds to wait before retrying
    
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
            Log::info('Starting blockchain registration job for document ID: ' . $this->document->id);
            
            // Check if document is already confirmed or has transaction hash
            if ($this->document->blockchain_status === 'confirmed') {
                Log::info('Document already confirmed, skipping registration');
                return;
            }
            
            if ($this->document->transaction_hash) {
                // Check confirmation instead of re-registering
                Log::info('Document has transaction hash, checking confirmation: ' . $this->document->transaction_hash);
                $confirmed = $blockchainService->checkTransactionConfirmation($this->document);
                
                if ($confirmed) {
                    Log::info('Document confirmed on blockchain: ' . $this->document->transaction_hash);
                    return;
                }
                
                // If not confirmed, and it's been retried multiple times, 
                // clear the transaction hash to allow a new attempt
                if ($this->attempts() > 1) {
                    Log::warning('Transaction not confirmed after multiple attempts, will try a new transaction');
                    $this->document->transaction_hash = null;
                    $this->document->save();
                } else {
                    // On first attempt, just release to try again later
                    Log::info('Transaction pending, will check again later');
                    $this->release(30); // Try again in 30 seconds
                    return;
                }
            }
            
            // Attempt blockchain registration
            Log::info('Registering document on blockchain, attempt #' . $this->attempts());
            $success = $blockchainService->registerDocument($this->document);
            
            if ($success) {
                Log::info('Document successfully registered on blockchain: ' . $this->document->transaction_hash);
                // Success! No need for further processing
            } else {
                Log::error('Failed to register document on blockchain. Document ID: ' . $this->document->id);
                Log::error('Error: ' . ($this->document->blockchain_error ?? 'Unknown error'));
                
                // Failed - determine if we should retry
                if ($this->attempts() < $this->tries) {
                    Log::info('Will retry registration later');
                    $this->release($this->backoff * $this->attempts());
                } else {
                    Log::error('Maximum retries reached, giving up on blockchain registration');
                    // The job will be marked as failed
                }
            }
        } catch (\Exception $e) {
            Log::error('Exception in RegisterDocumentOnBlockchain job: ' . $e->getMessage());
            
            // Update document status
            $this->document->blockchain_status = 'failed';
            $this->document->blockchain_error = 'Job exception: ' . $e->getMessage();
            $this->document->save();
            
            // Rethrow to mark job as failed
            throw $e;
        }
    }
    
    /**
     * Handle a job failure.
     *
     * @param  \Throwable  $exception
     * @return void
     */
    public function failed(\Throwable $exception)
    {
        Log::error('RegisterDocumentOnBlockchain job failed: ' . $exception->getMessage());
        
        // Update document status
        $this->document->blockchain_status = 'failed';
        $this->document->blockchain_error = 'Job failed: ' . $exception->getMessage();
        $this->document->save();
    }
}