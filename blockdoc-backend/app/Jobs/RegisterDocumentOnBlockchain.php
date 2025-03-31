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
     * @param  Document  $document
     * @return void
     */
    public function __construct(Document $document)
    {
        $this->document = $document;
    }

    /**
     * Execute the job.
     *
     * @param  BlockchainService  $blockchainService
     * @return void
     */
    public function handle(BlockchainService $blockchainService)
    {
        try {
            $blockchainService->registerDocument($this->document);
        } catch (\Exception $e) {
            Log::error('Failed to register document on blockchain: ' . $e->getMessage());
            $this->fail($e);
        }
    }
}