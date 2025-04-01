<?php

namespace App\Services;

use App\Models\Document;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Web3\Web3;
use Web3\Contract;
use Web3\Providers\HttpProvider;
use Web3\RequestManagers\HttpRequestManager;

class BlockchainService
{
    protected $web3;
    protected $contract;
    protected $contractAddress;
    protected $accountAddress;
    protected $privateKey;
    
    public function __construct()
    {
        // Get configuration values
        $this->contractAddress = config('blockchain.contract_address');
        $this->accountAddress = config('blockchain.account_address');
        $this->privateKey = config('blockchain.private_key');
        
        // Initialize Web3 with proper provider setup
        $providerUrl = config('blockchain.provider_url');
        $requestManager = new HttpRequestManager($providerUrl, 10);
        $provider = new HttpProvider($requestManager);
        $this->web3 = new Web3($provider);
        
        try {
            // Get ABI from the contract JSON file
            $contractAbi = json_decode(file_get_contents(storage_path('app/contract/DocumentVerification.json')), true);
            
            // If the JSON is already an array, use it directly (no 'abi' key needed)
            if (isset($contractAbi[0])) {
                $this->contract = new Contract($this->web3->provider, $contractAbi);
            } 
            // Otherwise, look for the 'abi' key
            else if (isset($contractAbi['abi'])) {
                $this->contract = new Contract($this->web3->provider, $contractAbi['abi']);
            }
            // If neither format is found, use the default ABI
            else {
                Log::warning('ABI format not recognized, using default ABI');
                $this->contract = new Contract($this->web3->provider, $this->getDefaultContractAbi());
            }
            
            // Set contract address immediately
            $this->contract->at($this->contractAddress);
        } catch (\Exception $e) {
            Log::error('Error loading contract ABI: ' . $e->getMessage());
            // Fallback to default ABI
            $this->contract = new Contract($this->web3->provider, $this->getDefaultContractAbi());
            // Set contract address immediately
            $this->contract->at($this->contractAddress);
        }
    }
    
    /**
     * Get the default contract ABI array directly
     */
    protected function getDefaultContractAbi()
    {
        return [
            [
                "inputs" => [],
                "stateMutability" => "nonpayable",
                "type" => "constructor"
            ],
            [
                "anonymous" => false,
                "inputs" => [
                    [
                        "indexed" => true,
                        "internalType" => "string",
                        "name" => "documentHash",
                        "type" => "string"
                    ],
                    [
                        "indexed" => false,
                        "internalType" => "uint256",
                        "name" => "timestamp",
                        "type" => "uint256"
                    ]
                ],
                "name" => "DocumentRegistered",
                "type" => "event"
            ],
            [
                "inputs" => [
                    [
                        "internalType" => "string",
                        "name" => "documentHash",
                        "type" => "string"
                    ]
                ],
                "name" => "getDocumentTimestamp",
                "outputs" => [
                    [
                        "internalType" => "uint256",
                        "name" => "",
                        "type" => "uint256"
                    ]
                ],
                "stateMutability" => "view",
                "type" => "function"
            ],
            [
                "inputs" => [],
                "name" => "owner",
                "outputs" => [
                    [
                        "internalType" => "address",
                        "name" => "",
                        "type" => "address"
                    ]
                ],
                "stateMutability" => "view",
                "type" => "function"
            ],
            [
                "inputs" => [
                    [
                        "internalType" => "string",
                        "name" => "documentHash",
                        "type" => "string"
                    ]
                ],
                "name" => "registerDocument",
                "outputs" => [
                    [
                        "internalType" => "bool",
                        "name" => "",
                        "type" => "bool"
                    ]
                ],
                "stateMutability" => "nonpayable",
                "type" => "function"
            ],
            [
                "inputs" => [
                    [
                        "internalType" => "address",
                        "name" => "newOwner",
                        "type" => "address"
                    ]
                ],
                "name" => "transferOwnership",
                "outputs" => [],
                "stateMutability" => "nonpayable",
                "type" => "function"
            ],
            [
                "inputs" => [
                    [
                        "internalType" => "string",
                        "name" => "documentHash",
                        "type" => "string"
                    ]
                ],
                "name" => "verifyDocument",
                "outputs" => [
                    [
                        "internalType" => "bool",
                        "name" => "",
                        "type" => "bool"
                    ],
                    [
                        "internalType" => "uint256",
                        "name" => "",
                        "type" => "uint256"
                    ]
                ],
                "stateMutability" => "view",
                "type" => "function"
            ]
        ];
    }
    
    public function registerDocument(Document $document)
    {
        try {
            // No need to initialize contract address again, it's done in constructor
            
            // Prepare the transaction - Sepolia might need different gas settings
            $gas = config('blockchain.gas_limit', 200000);
            
            // Get current gas price from the network and add a bit more for Sepolia testnet
            $gasPrice = null;
            $gasPricePromise = new \Web3\Promise(function ($resolve, $reject) use (&$gasPrice) {
                $this->web3->eth->gasPrice(function ($err, $price) use (&$gasPrice, $resolve, $reject) {
                    if ($err) {
                        Log::error('Error getting gas price: ' . $err->getMessage());
                        $reject($err);
                        return;
                    }
                    $gasPrice = $price;
                    $resolve($price);
                });
            });
            
            // Wait for gas price to be resolved
            $gasPricePromise->wait();
            
            if (!$gasPrice) {
                // Default gas price (50 Gwei for Sepolia)
                $gasPrice = '0x' . dechex(50 * pow(10, 9));
            }
            
            // Set up promise for sending transaction
            $transactionPromise = new \Web3\Promise(function ($resolve, $reject) use ($document, $gas, $gasPrice) {
                $this->contract->send(
                    'registerDocument',
                    $document->hash,
                    [
                        'from' => $this->accountAddress,
                        'gas' => '0x' . dechex($gas),
                        'gasPrice' => $gasPrice
                    ],
                    function ($err, $transactionHash) use ($document, $resolve, $reject) {
                        if ($err) {
                            Log::error('Blockchain registration error: ' . $err->getMessage());
                            $reject($err);
                            return;
                        }
                        
                        // Update document with transaction hash
                        $document->transaction_hash = $transactionHash;
                        $document->blockchain_network = config('blockchain.network_name', 'Sepolia Testnet');
                        $document->save();
                        
                        Log::info('Document registered on blockchain: ' . $transactionHash);
                        $resolve($transactionHash);
                    }
                );
            });
            
            // Wait for transaction to be sent
            $transactionPromise->wait();
            
            // Start checking for transaction confirmation
            $this->checkTransactionConfirmation($document);
            
            return true;
        } catch (\Exception $e) {
            Log::error('Blockchain service error: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            return false;
        }
    }
    
    public function checkTransactionConfirmation(Document $document)
    {
        try {
            $receiptPromise = new \Web3\Promise(function ($resolve, $reject) use ($document) {
                $this->web3->eth->getTransactionReceipt($document->transaction_hash, function ($err, $receipt) use ($document, $resolve, $reject) {
                    if ($err) {
                        Log::error('Error checking transaction: ' . $err->getMessage());
                        $reject($err);
                        return;
                    }
                    
                    if ($receipt && isset($receipt->status) && $receipt->status == '0x1') {
                        // Transaction confirmed
                        $document->blockchain_status = 'confirmed';
                        $document->blockchain_timestamp = now();
                        $document->save();
                        
                        Log::info('Document transaction confirmed: ' . $document->transaction_hash);
                        $resolve(true);
                    } else if ($receipt) {
                        // Transaction found but not successful
                        $document->blockchain_status = 'failed';
                        $document->save();
                        Log::warning('Document transaction failed: ' . $document->transaction_hash);
                        $resolve(false);
                    } else {
                        // Transaction not yet confirmed
                        Log::info('Transaction not yet confirmed: ' . $document->transaction_hash);
                        $resolve(false);
                    }
                });
            });
            
            // Wait for the receipt to be checked
            $confirmed = $receiptPromise->wait();
            
            return $confirmed;
        } catch (\Exception $e) {
            Log::error('Error checking transaction confirmation: ' . $e->getMessage());
            return false;
        }
    }
    
    public function checkTransactionStatus($transactionHash)
    {
        try {
            $confirmed = false;
            $statusPromise = new \Web3\Promise(function ($resolve, $reject) use ($transactionHash, &$confirmed) {
                $this->web3->eth->getTransactionReceipt($transactionHash, function ($err, $receipt) use (&$confirmed, $resolve, $reject) {
                    if ($err) {
                        Log::error('Error checking transaction: ' . $err->getMessage());
                        $reject($err);
                        return;
                    }
                    
                    if ($receipt && isset($receipt->status) && $receipt->status == '0x1') {
                        $confirmed = true;
                        $resolve(true);
                    } else {
                        $resolve(false);
                    }
                });
            });
            
            // Wait for the status to be checked
            $confirmed = $statusPromise->wait();
            
            return $confirmed;
        } catch (\Exception $e) {
            Log::error('Error checking transaction status: ' . $e->getMessage());
            return false;
        }
    }
    
    public function verifyDocumentHash($hash, $transactionHash = null)
    {
        try {
            // Log verification attempt
            Log::info("Attempting to verify document hash on blockchain: $hash");
            
            // Even if transaction hash is provided, we're going to verify directly on blockchain
            // as that's the most accurate method
            
            // Verify directly on the blockchain using the contract's verifyDocument function
            $verified = false;
            $timestamp = 0;
            
            $verifyPromise = new \Web3\Promise(function ($resolve, $reject) use ($hash, &$verified, &$timestamp) {
                $this->contract->call('verifyDocument', $hash, function ($err, $result) use (&$verified, &$timestamp, $resolve, $reject, $hash) {
                    if ($err) {
                        Log::error('Blockchain verification error: ' . $err->getMessage());
                        $reject($err);
                        return;
                    }
                    
                    // Log the full result for debugging
                    Log::info('Verification result for hash ' . $hash . ': ' . json_encode($result));
                    
                    // $result[0] should be true/false indicating if document exists
                    // $result[1] should be the timestamp when document was registered
                    if (isset($result[0]) && $result[0] === true) {
                        $verified = true;
                        
                        // Get timestamp if available
                        if (isset($result[1])) {
                            $timestamp = hexdec($result[1]->value);
                            Log::info("Document verified on blockchain with timestamp: $timestamp");
                        }
                        
                        $resolve(['verified' => true, 'timestamp' => $timestamp]);
                    } else {
                        Log::warning("Document hash not found on blockchain: $hash");
                        $resolve(['verified' => false, 'timestamp' => 0]);
                    }
                });
            });
            
            // Wait for verification to complete
            $result = $verifyPromise->wait();
            
            // If the main verification failed but we have a transaction hash,
            // double-check if the transaction itself is valid as a fallback
            if (!$result['verified'] && $transactionHash) {
                Log::info("Primary verification failed, checking transaction status as fallback: $transactionHash");
                $txConfirmed = $this->checkTransactionStatus($transactionHash);
                
                if ($txConfirmed) {
                    Log::info("Transaction confirmed but document not found in contract. This might indicate contract data issue.");
                    // We could return true here, but this is a partial verification at best
                    // Since the contract doesn't have the data but transaction exists
                    // For security, we'll still return false
                    return false;
                }
            }
            
            return $result['verified'];
        } catch (\Exception $e) {
            Log::error('Blockchain verification error: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            return false;
        }
    }
}