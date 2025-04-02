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
                
                // 統合されたcheckTransactionメソッドを使用
                // これはドキュメントのステータスも自動的に更新します
                $confirmed = $this->blockchainService->checkTransaction($document);
                
                if ($confirmed) {
                    // ステータスは既にserviceで更新されているため、ここでは更新しない
                    $this->info("Document ID: {$document->id} confirmed on blockchain.");
                } else {
                    $this->info("Document ID: {$document->id} still pending.");
                    
                    // トランザクション送信からの経過時間をチェック
                    $hoursElapsed = $document->updated_at->diffInHours(now());
                    
                    // トランザクションが24時間以上pending状態の場合、警告ログを記録
                    if ($hoursElapsed >= 24) {
                        $this->warn("Document ID: {$document->id} has been pending for {$hoursElapsed} hours.");
                        Log::warning("Transaction pending for {$hoursElapsed} hours: {$document->transaction_hash}");
                    }
                }
            } catch (\Exception $e) {
                $this->error("Error checking document ID: {$document->id}: " . $e->getMessage());
                Log::error("Error in blockchain:check-pending for document {$document->id}: " . $e->getMessage());
            }
        }
        
        $this->info('Finished checking pending blockchain transactions.');
        
        // 失敗したトランザクションの数も報告
        $failedCount = Document::where('blockchain_status', 'failed')->count();
        if ($failedCount > 0) {
            $this->warn("There are {$failedCount} failed blockchain transactions.");
        }
    }
}