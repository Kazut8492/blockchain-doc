<?php

// app/Services/BlockchainService.php - Blockchain service
namespace App\Services;

use App\Models\Document;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Web3\Web3;
use Web3\Contract;
use Web3\Providers\HttpProvider;

class BlockchainService
{
    protected $web3;
    protected $contract;
    protected $contractAddress;
    protected $accountAddress;
    
    public function __construct()
    {
        $this->contractAddress = config('blockchain.contract_address');
        $this->accountAddress = config('blockchain.account_address');
        
        $provider = new HttpProvider(config('blockchain.provider_url'));
        $this->web3 = new Web3($provider);
        
        // Get ABI from the contract JSON file
        $contractJson = json_decode(file_get_contents(storage_path('app/contract/DocumentVerification.json')), true);
        $this->contract = new Contract($this->web3->provider, $contractJson['abi']);
    }
    
    public function registerDocument(Document $document)
    {
        try {
            // The contract is already initialized in the constructor
            $this->contract->at($this->contractAddress);
            
            // Prepare the transaction
            $gas = 100000;
            $gasPrice = $this->web3->eth->gasPrice->toWei('20', 'gwei');
            
            // Register document hash on blockchain
            $this->contract->send(
                'registerDocument',
                $document->hash,
                [
                    'from' => $this->accountAddress,
                    'gas' => '0x' . dechex($gas),
                    'gasPrice' => '0x' . dechex($gasPrice)
                ],
                function ($err, $transactionHash) use ($document) {
                    if ($err) {
                        Log::error('Blockchain registration error: ' . $err->getMessage());
                        return;
                    }
                    
                    // Update document with transaction hash
                    $document->transaction_hash = $transactionHash;
                    $document->save();
                    
                    // Start checking for transaction confirmation
                    $this->checkTransactionConfirmation($document);
                }
            );
            
            return true;
        } catch (\Exception $e) {
            Log::error('Blockchain service error: ' . $e->getMessage());
            return false;
        }
    }
    
    protected function checkTransactionConfirmation(Document $document)
    {
        // This would typically be handled by a queue job that periodically checks
        // the transaction status until it's confirmed
        try {
            $this->web3->eth->getTransactionReceipt($document->transaction_hash, function ($err, $receipt) use ($document) {
                if ($err) {
                    Log::error('Error checking transaction: ' . $err->getMessage());
                    return;
                }
                
                if ($receipt && $receipt->status == '0x1') {
                    // Transaction confirmed
                    $document->blockchain_status = 'confirmed';
                    $document->blockchain_timestamp = now();
                    $document->save();
                }
            });
        } catch (\Exception $e) {
            Log::error('Error checking transaction confirmation: ' . $e->getMessage());
        }
    }
    
    public function verifyDocumentHash($hash, $transactionHash)
    {
        try {
            // Initialize the contract
            $this->contract->at($this->contractAddress);
            
            // Check if the hash is registered and matches the transaction
            $verified = false;
            
            $this->contract->call('verifyDocument', $hash, function ($err, $result) use (&$verified) {
                if ($err) {
                    Log::error('Blockchain verification error: ' . $err->getMessage());
                    return;
                }
                
                // $result will be true if the document is registered
                $verified = $result[0];
            });
            
            return $verified;
        } catch (\Exception $e) {
            Log::error('Blockchain verification error: ' . $e->getMessage());
            return false;
        }
    }
}