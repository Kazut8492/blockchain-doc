<?php

namespace App\Console\Commands;

use App\Models\Document;
use App\Services\BlockchainService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckPendingTransactions extends Command
{
    protected $signature = 'blockchain:check-pending';
    protected $description = 'Check pending blockchain transactions for confirmation';

    protected $blockchainService;

    public function __construct(BlockchainService $blockchainService)
    {
        parent::__construct();
        $this->blockchainService = $blockchainService;
    }

    public function handle()
    {
        $this->info('Checking pending blockchain transactions...');
        
        $pendingDocuments = Document::where('blockchain_status', 'pending')
            ->whereNotNull('transaction_hash')
            ->get();
            
        $this->info("Found {$pendingDocuments->count()} pending documents.");
        
        foreach ($pendingDocuments as $document) {
            try {
                $this->info("Checking document ID: {$document->id}, transaction: {$document->transaction_hash}");
                
                $confirmed = $this->blockchainService->checkTransactionStatus($document->transaction_hash);
                
                if ($confirmed) {
                    $document->blockchain_status = 'confirmed';
                    $document->blockchain_timestamp = now();
                    $document->save();
                    
                    $this->info("Document ID: {$document->id} confirmed on blockchain.");
                } else {
                    $this->info("Document ID: {$document->id} still pending.");
                }
            } catch (\Exception $e) {
                $this->error("Error checking document ID: {$document->id}: " . $e->getMessage());
                Log::error("Error in blockchain:check-pending for document {$document->id}: " . $e->getMessage());
            }
        }
        
        $this->info('Finished checking pending blockchain transactions.');
    }
}