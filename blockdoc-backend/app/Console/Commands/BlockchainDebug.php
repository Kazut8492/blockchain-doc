<?php

namespace App\Console\Commands;

use App\Services\BlockchainDebugger;
use App\Services\BlockchainService;
use App\Models\Document;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class BlockchainDebug extends Command
{
    protected $signature = 'blockchain:debug {action=diagnose} {document_id?}';
    protected $description = 'Debug blockchain integration issues';

    public function handle()
    {
        $action = $this->argument('action');
        
        $this->info("Running blockchain debug: {$action}");
        
        switch ($action) {
            case 'diagnose':
                $this->runDiagnostics();
                break;
                
            case 'direct-register':
                $documentId = $this->argument('document_id');
                if (!$documentId) {
                    $this->error('Document ID is required for direct-register action');
                    return 1;
                }
                $this->directRegister($documentId);
                break;
                
            case 'fix-pending':
                $this->fixPendingDocuments();
                break;
                
            case 'list-pending':
                $this->listPendingDocuments();
                break;
                
            case 'check-transaction':
                $documentId = $this->argument('document_id');
                if (!$documentId) {
                    $this->error('Document ID is required for check-transaction action');
                    return 1;
                }
                $this->checkTransaction($documentId);
                break;
                
            default:
                $this->error("Unknown action: {$action}");
                return 1;
        }
        
        return 0;
    }
    
    protected function runDiagnostics()
    {
        $this->info('Running comprehensive blockchain diagnostics...');
        
        $results = BlockchainDebugger::diagnose();
        
        // Output configuration
        $this->outputSection('Configuration', $results['configuration']);
        
        // Output connectivity
        $this->outputSection('Connectivity', $results['connectivity']);
        
        // Output pending documents
        $this->outputSection('Pending Documents', $results['pending_documents']);
        
        // Output queue
        $this->outputSection('Queue Status', $results['queue']);
    }
    
    protected function outputSection($title, $data)
    {
        $this->info("\n=== {$title} ===");
        
        if (isset($data['status'])) {
            $status = strtoupper($data['status']);
            switch ($data['status']) {
                case 'ok':
                    $this->info("Status: {$status}");
                    break;
                case 'warning':
                    $this->warn("Status: {$status}");
                    break;
                case 'error':
                    $this->error("Status: {$status}");
                    break;
                default:
                    $this->line("Status: {$status}");
            }
        }
        
        if (isset($data['details']) && is_array($data['details'])) {
            foreach ($data['details'] as $key => $detail) {
                if (is_array($detail)) {
                    $this->line("- {$key}:");
                    
                    foreach ($detail as $subKey => $value) {
                        if ($subKey === 'status') {
                            continue;
                        }
                        
                        if (is_array($value)) {
                            $this->line("  - {$subKey}: " . json_encode($value));
                        } else {
                            $this->line("  - {$subKey}: {$value}");
                        }
                    }
                    
                    if (isset($detail['status'])) {
                        switch ($detail['status']) {
                            case 'ok':
                                $this->info("  - Status: OK");
                                break;
                            case 'warning':
                                $this->warn("  - Status: WARNING");
                                break;
                            case 'error':
                                $this->error("  - Status: ERROR");
                                break;
                            default:
                                $this->line("  - Status: {$detail['status']}");
                        }
                    }
                } else {
                    $this->line("- {$key}: {$detail}");
                }
            }
        }
    }
    
    protected function directRegister($documentId)
    {
        $this->info("Attempting direct blockchain registration for document ID: {$documentId}");
        
        $document = Document::find($documentId);
        
        if (!$document) {
            $this->error("Document not found: {$documentId}");
            return;
        }
        
        $this->info("Document details:");
        $this->line("- Hash: {$document->hash}");
        $this->line("- Status: {$document->blockchain_status}");
        $this->line("- Transaction hash: " . ($document->transaction_hash ?? 'NULL'));
        
        if ($this->confirm('Do you want to proceed with direct registration?')) {
            $this->info('Registering on blockchain...');
            
            $result = BlockchainDebugger::attemptDirectRegistration($documentId);
            
            if ($result['status'] === 'success') {
                $this->info('Registration successful!');
                $this->line("Transaction hash: {$result['transaction_hash']}");
            } else {
                $this->error('Registration failed!');
                $this->line("Message: {$result['message']}");
                
                if (isset($result['trace'])) {
                    $this->line("\nStack trace:");
                    $this->line($result['trace']);
                }
            }
        }
    }
    
    protected function fixPendingDocuments()
    {
        $this->info('Looking for pending documents without transaction hash...');
        
        $documents = Document::where('blockchain_status', 'pending')
            ->whereNull('transaction_hash')
            ->get();
            
        $count = $documents->count();
        
        if ($count === 0) {
            $this->info('No pending documents without transaction hash found.');
            return;
        }
        
        $this->warn("Found {$count} pending documents without transaction hash.");
        
        if ($this->confirm('Do you want to attempt registration for these documents?')) {
            $blockchainService = app(BlockchainService::class);
            
            $success = 0;
            $failed = 0;
            
            foreach ($documents as $document) {
                $this->line("\nProcessing document ID: {$document->id}");
                
                try {
                    $result = $blockchainService->registerDocument($document);
                    
                    if ($result) {
                        $this->info("Successfully registered document ID: {$document->id}");
                        $this->line("Transaction hash: {$document->transaction_hash}");
                        $success++;
                    } else {
                        $this->error("Failed to register document ID: {$document->id}");
                        $failed++;
                    }
                } catch (\Exception $e) {
                    $this->error("Error registering document ID: {$document->id}");
                    $this->line("Error: {$e->getMessage()}");
                    $failed++;
                }
            }
            
            $this->info("\nSummary:");
            $this->info("- Successfully registered: {$success}");
            $this->error("- Failed to register: {$failed}");
        }
    }
    
    protected function listPendingDocuments()
    {
        $this->info('Listing all pending documents...');
        
        $documents = Document::where('blockchain_status', 'pending')
            ->orderBy('created_at')
            ->get();
            
        $count = $documents->count();
        
        if ($count === 0) {
            $this->info('No pending documents found.');
            return;
        }
        
        $this->info("Found {$count} pending documents.");
        
        $headers = ['ID', 'Created', 'Hours Ago', 'Has TX Hash', 'TX Hash'];
        $rows = [];
        
        foreach ($documents as $document) {
            $hoursAgo = $document->created_at->diffInHours(now());
            $hasTxHash = $document->transaction_hash ? 'Yes' : 'No';
            $txHash = $document->transaction_hash ? substr($document->transaction_hash, 0, 10) . '...' : 'NULL';
            
            $rows[] = [
                $document->id,
                $document->created_at->toDateTimeString(),
                $hoursAgo,
                $hasTxHash,
                $txHash
            ];
        }
        
        $this->table($headers, $rows);
    }
    
    protected function checkTransaction($documentId)
    {
        $this->info("Checking transaction for document ID: {$documentId}");
        
        $document = Document::find($documentId);
        
        if (!$document) {
            $this->error("Document not found: {$documentId}");
            return;
        }
        
        $this->info("Document details:");
        $this->line("- Hash: {$document->hash}");
        $this->line("- Status: {$document->blockchain_status}");
        
        if (!$document->transaction_hash) {
            $this->warn("This document has no transaction hash.");
            return;
        }
        
        $this->line("- Transaction hash: {$document->transaction_hash}");
        
        $blockchainService = app(BlockchainService::class);
        
        $this->info('Checking transaction on blockchain...');
        
        try {
            $result = $blockchainService->checkTransaction($document);
            
            if ($result) {
                $this->info('Transaction is confirmed on blockchain!');
                
                // Refresh document to get updated status
                $document->refresh();
                
                $this->line("- Current status: {$document->blockchain_status}");
                $this->line("- Blockchain timestamp: " . ($document->blockchain_timestamp ? $document->blockchain_timestamp->toDateTimeString() : 'NULL'));
            } else {
                $this->warn('Transaction is not confirmed on blockchain.');
                
                // Try to get more details from the blockchain provider
                $this->info('Attempting to get transaction details from provider...');
                
                $providerUrl = config('blockchain.provider_url');
                $response = \Illuminate\Support\Facades\Http::post($providerUrl, [
                    'jsonrpc' => '2.0',
                    'method' => 'eth_getTransactionReceipt',
                    'params' => [$document->transaction_hash],
                    'id' => 1
                ]);
                
                $data = $response->json();
                
                if (isset($data['result'])) {
                    $receipt = $data['result'];
                    
                    if ($receipt === null) {
                        $this->warn('Transaction not yet mined or not found on blockchain.');
                    } else {
                        $status = isset($receipt['status']) ? hexdec($receipt['status']) : null;
                        
                        if ($status === 1) {
                            $this->info('Transaction was successful on blockchain but document status is not updated!');
                            
                            if ($this->confirm('Do you want to update the document status to confirmed?')) {
                                $document->blockchain_status = 'confirmed';
                                $document->blockchain_timestamp = now();
                                $document->save();
                                
                                $this->info('Document status updated to confirmed.');
                            }
                        } else if ($status === 0) {
                            $this->error('Transaction failed on blockchain!');
                            
                            if ($this->confirm('Do you want to update the document status to failed?')) {
                                $document->blockchain_status = 'failed';
                                $document->save();
                                
                                $this->info('Document status updated to failed.');
                            }
                        } else {
                            $this->warn('Could not determine transaction status from receipt.');
                            $this->line('Receipt: ' . json_encode($receipt));
                        }
                    }
                } else {
                    $this->error('Failed to get transaction receipt from provider.');
                    
                    if (isset($data['error'])) {
                        $this->line('Error: ' . json_encode($data['error']));
                    }
                }
            }
        } catch (\Exception $e) {
            $this->error('Error checking transaction: ' . $e->getMessage());
        }
    }
}