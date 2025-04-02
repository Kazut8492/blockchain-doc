<?php

namespace App\Services;

use App\Models\Document;
use App\Services\Web3\Promise;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Web3\Web3;
use Web3\Contract;
use Web3\Providers\HttpProvider;
use Web3\RequestManagers\HttpRequestManager;
use Web3\Utils;
use Web3\Eth;
use kornrunner\Ethereum\Transaction;
use Web3\Formatters\HexFormatter;

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
        
        // Check account balance to log warnings about insufficient funds
        $this->checkAccountBalance();
        
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
            // If neither format is found, use the updated ABI
            else {
                Log::warning('ABI format not recognized, using default ABI');
                $this->contract = new Contract($this->web3->provider, $this->getUpdatedContractAbi());
            }
            
            // Set contract address immediately
            $this->contract->at($this->contractAddress);
        } catch (\Exception $e) {
            Log::error('Error loading contract ABI: ' . $e->getMessage());
            // Fallback to updated ABI
            $this->contract = new Contract($this->web3->provider, $this->getUpdatedContractAbi());
            // Set contract address immediately
            $this->contract->at($this->contractAddress);
        }
    }
    
    /**
     * Get the updated contract ABI array with bytes32 parameter types
     */
    protected function getUpdatedContractAbi()
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
                        "indexed" => false,
                        "internalType" => "bytes32",
                        "name" => "documentHash",
                        "type" => "bytes32"
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
                        "internalType" => "bytes32",
                        "name" => "documentHash",
                        "type" => "bytes32"
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
                        "internalType" => "bytes32",
                        "name" => "documentHash",
                        "type" => "bytes32"
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
                        "internalType" => "bytes32",
                        "name" => "documentHash",
                        "type" => "bytes32"
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
            // Log important information
            Log::info('Attempting to register document with hash: ' . $document->hash);
            Log::info('Using contract address: ' . $this->contractAddress);
            Log::info('Using account address: ' . $this->accountAddress);
            
            // Ensure hash is in the proper format for bytes32
            $hashValue = $document->hash;
            
            // Debug the hash value
            Log::info('Hash value format being sent to contract: ' . $hashValue);
            
            // IMPORTANT: Get the encoded data for the contract call
            // This creates the function signature and parameters formatted for the EVM
            $data = $this->contract->getData('registerDocument', $hashValue);
            if (!$data) {
                Log::error('Failed to encode contract method data');
                return false;
            }
            
            // Log the encoded data for debugging
            Log::info('Encoded contract method data: ' . $data);
            
            $gasPrice = 3 * pow(10, 9);
            $gasPriceHex = '0x' . dechex($gasPrice);
            
            Log::info('Using fixed gas price: ' . $gasPriceGwei . ' Gwei (' . $gasPrice . ' Wei)');
            
            // Get nonce for the account
            $nonce = null;
            $noncePromise = new Promise(function ($resolve, $reject) use (&$nonce) {
                $this->web3->eth->getTransactionCount(
                    $this->accountAddress,
                    'pending',
                    function ($err, $count) use (&$nonce, $resolve, $reject) {
                        if ($err) {
                            Log::error('Error getting nonce: ' . $err->getMessage());
                            $reject($err);
                            return;
                        }
                        
                        $nonce = hexdec($count);
                        Log::info('Current nonce: ' . $nonce);
                        $resolve($nonce);
                    }
                );
            });
            $noncePromise->wait();
            
            if ($nonce === null) {
                Log::error('Failed to get nonce');
                return false;
            }

            // // Replace your current nonce retrieval code with this direct HTTP approach
            // $nonce = null;
            // try {
            //     // Get nonce directly via JSON-RPC
            //     $response = Http::post(config('blockchain.provider_url'), [
            //         'jsonrpc' => '2.0',
            //         'method' => 'eth_getTransactionCount',
            //         'params' => [$this->accountAddress, 'latest'],
            //         'id' => 1
            //     ]);
                
            //     $result = $response->json();
                
            //     if (isset($result['error'])) {
            //         Log::error('Error getting nonce via direct RPC: ' . json_encode($result['error']));
            //         return false;
            //     }
                
            //     if (!isset($result['result'])) {
            //         Log::error('Invalid response when getting nonce: ' . json_encode($result));
            //         return false;
            //     }
                
            //     $nonce = hexdec($result['result']);
            //     Log::info('Nonce retrieved via direct RPC: ' . $nonce);
                
            //     // Sanity check - a nonce should never be this high
            //     if ($nonce > 10000) {
            //         Log::error('Nonce value appears invalid: ' . $nonce);
            //         Log::info('Forcing nonce to 0 as a fallback');
            //         $nonce = 0; // Force a low nonce as a test
            //     }
            // } catch (\Exception $e) {
            //     Log::error('Exception getting nonce: ' . $e->getMessage());
            //     return false;
            // }

            // Add this check after getting the nonce
            if ($nonce > 100000) { // Unusually high nonce
                Log::warning('Unusually high nonce detected: ' . $nonce . '. This might indicate an issue.');
                
                // Try with latest instead of pending
                $latestNoncePromise = new Promise(function ($resolve, $reject) {
                    $this->web3->eth->getTransactionCount(
                        $this->accountAddress,
                        'latest', // Use latest instead of pending
                        function ($err, $count) use ($resolve, $reject) {
                            if ($err) {
                                Log::error('Error getting latest nonce: ' . $err->getMessage());
                                $reject($err);
                                return;
                            }
                            
                            $latestNonce = hexdec($count);
                            Log::info('Latest nonce: ' . $latestNonce);
                            $resolve($latestNonce);
                        }
                    );
                });
                
                try {
                    $nonce = $latestNoncePromise->wait();
                    Log::info('Using latest nonce instead: ' . $nonce);
                } catch (\Exception $e) {
                    Log::error('Error getting latest nonce: ' . $e->getMessage());
                    // Continue with original nonce as fallback
                }
            }
            
            // Set a modest gas limit
            $gas = 80000; // Lower than before
            Log::info('Using gas limit: ' . $gas);

            // Calculate costs with detailed logging
            $costInWei = $gasPrice * $gas;
            $costInEth = $costInWei / pow(10, 18);
            $balanceWei = $this->document->balance; // Make sure this is defined
            
            // Calculate the estimated cost
            $estimatedCost = $gasPrice * $gas;
            $estimatedCostEth = $estimatedCost / pow(10, 18);
            Log::info('Estimated transaction cost: ' . $estimatedCostEth . ' ETH');
            
            // Check account balance (optional, since we're using very low gas)
            $balancePromise = new Promise(function ($resolve, $reject) {
                $this->web3->eth->getBalance($this->accountAddress, 'latest', function ($err, $balance) use ($resolve, $reject) {
                    if ($err) {
                        Log::error('Error checking account balance: ' . $err->getMessage());
                        $reject($err);
                        return;
                    }
                    $resolve($balance);
                });
            });
            
            try {
                $balance = $balancePromise->wait();
                
                // Convert balance to decimal
                if (is_object($balance) && method_exists($balance, 'toString')) {
                    $balanceWei = $balance->toString();
                } else if (substr((string)$balance, 0, 2) === '0x') {
                    $balanceWei = hexdec((string)$balance);
                } else {
                    $balanceWei = (string)$balance;
                }
                
                $balanceEth = $balanceWei / pow(10, 18);
                Log::info('Current account balance: ' . $balanceEth . ' ETH');
                
                // Check if we have enough funds
                if ($balanceWei < $estimatedCost) {
                    Log::error('Insufficient funds for transaction. Need: ' . $estimatedCostEth . ' ETH, Have: ' . $balanceEth . ' ETH');
                    $document->blockchain_status = 'failed';
                    $document->save();
                    return false;
                }
            } catch (\Exception $e) {
                Log::warning('Could not check balance, proceeding anyway: ' . $e->getMessage());
                // Continue anyway since we're using very low gas
            }

            Log::info('Transaction cost calculation:');
            Log::info('- Gas price: ' . ($gasPrice / pow(10, 9)) . ' Gwei (' . $gasPrice . ' Wei)');
            Log::info('- Gas limit: ' . $gas);
            Log::info('- Cost in Wei: ' . $costInWei);
            Log::info('- Cost in ETH: ' . $costInEth);
            Log::info('- Available balance in ETH: ' . ($balanceWei / pow(10, 18)));

            if ($costInWei > $balanceWei) {
                Log::error('INSUFFICIENT FUNDS: Need ' . $costInEth . ' ETH, have ' . ($balanceWei / pow(10, 18)) . ' ETH');
            } else {
                Log::info('FUNDS SUFFICIENT: Cost is ' . $costInEth . ' ETH, have ' . ($balanceWei / pow(10, 18)) . ' ETH');
            }
            
            // Create transaction object
            try {
                $transaction = new Transaction(
                    $nonce,
                    $gasPrice,  // Use our fixed low gas price
                    $gas,
                    $this->contractAddress,
                    0, // value
                    $data, 
                    11155111 // chainId for Sepolia
                );
                
                // Sign the transaction
                $signedTransaction = '0x' . $transaction->getRaw($this->privateKey);
                Log::info('Transaction signed successfully');
                
                // Send the raw transaction
                $providerUrl = config('blockchain.provider_url');
                $response = Http::post($providerUrl, [
                    'jsonrpc' => '2.0',
                    'method' => 'eth_sendRawTransaction',
                    'params' => [$signedTransaction],
                    'id' => 1
                ]);
                
                // Log full response for debugging
                Log::info('RPC response: ' . $response->body());
                
                $result = $response->json();
                
                if (isset($result['error'])) {
                    // Log the full error for debugging
                    Log::error('RPC error sending transaction: ' . json_encode($result['error']));
                    
                    // Check specifically for insufficient funds errors
                    $errorMessage = $result['error']['message'] ?? '';
                    if (stripos($errorMessage, 'insufficient funds') !== false || 
                        stripos($errorMessage, 'insufficient balance') !== false) {
                        Log::critical('INSUFFICIENT FUNDS ERROR: The account does not have enough ETH to pay for gas fees. ' .
                                    'Account: ' . $this->accountAddress . 
                                    ', Gas Price: ' . ($gasPrice / pow(10, 9)) . ' Gwei' .
                                    ', Gas Limit: ' . $gas);
                    }
                    
                    // Update document status to reflect the error
                    $document->blockchain_status = 'failed';
                    $document->save();
                    return false;
                }
                
                if (isset($result['result'])) {
                    $transactionHash = $result['result'];
                    
                    // Update document with transaction hash
                    $document->transaction_hash = $transactionHash;
                    $document->blockchain_status = 'pending';
                    $document->blockchain_network = config('blockchain.network_name', 'Sepolia Testnet');
                    $document->save();
                    
                    Log::info('Document registered on blockchain with transaction hash: ' . $transactionHash);
                    Log::info('Verify transaction on Etherscan: https://sepolia.etherscan.io/tx/' . $transactionHash);

                    // ADD THE POLLING CODE HERE
                    $maxAttempts = 5;
                    $attempt = 0;
                    $confirmed = false;

                    while ($attempt < $maxAttempts && !$confirmed) {
                        $attempt++;
                        Log::info("Polling for transaction receipt, attempt $attempt/$maxAttempts");
                        
                        try {
                            sleep(2); // Wait 2 seconds between attempts
                            
                            // Use getTransactionReceipt instead of getTransaction
                            $this->web3->eth->getTransactionReceipt($transactionHash, function ($err, $receipt) use (&$confirmed, $transactionHash) {
                                if ($err) {
                                    Log::error('Error checking transaction receipt: ' . $err->getMessage());
                                    return;
                                }
                                
                                if ($receipt) {
                                    Log::info('Transaction receipt found: ' . $transactionHash);
                                    $status = isset($receipt->status) ? $receipt->status : 'unknown';
                                    Log::info('Transaction status: ' . $status);
                                    $confirmed = true;
                                } else {
                                    Log::info('Transaction pending (no receipt yet): ' . $transactionHash);
                                }
                            });
                        } catch (\Exception $e) {
                            Log::error('Error polling for transaction receipt: ' . $e->getMessage());
                        }
                    }

                    if (!$confirmed) {
                        Log::warning('Transaction may still be pending: ' . $transactionHash);
                        Log::warning('Check status manually on Etherscan');
                    }
                    
                    $this->checkTransactionConfirmation($document);
                    
                    return true;
                }
                
                Log::error('Unexpected response from RPC: ' . json_encode($result));
                return false;
                
            } catch (\Exception $e) {
                Log::error('Error signing or sending transaction: ' . $e->getMessage());
                $document->blockchain_status = 'failed';
                $document->save();
                return false;
            }
            
        } catch (\Exception $e) {
            Log::error('Blockchain service error: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            $document->blockchain_status = 'failed';
            $document->save();
            return false;
        }
    }
    
    public function checkTransactionConfirmation(Document $document)
    {
        try {
            $receiptPromise = new Promise(function ($resolve, $reject) use ($document) {
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
    
    /**
     * Check account balance and log warning if too low
     */
    protected function checkAccountBalance()
    {
        try {
            $balancePromise = new Promise(function ($resolve, $reject) {
                $this->web3->eth->getBalance($this->accountAddress, 'latest', function ($err, $balance) use ($resolve, $reject) {
                    if ($err) {
                        Log::error('Error checking account balance: ' . $err->getMessage());
                        $reject($err);
                        return;
                    }
                    
                    $resolve($balance);
                });
            });
            
            // Wait for the balance check
            $balance = $balancePromise->wait();
            
            // Log raw balance for debugging
            Log::info('Raw balance type: ' . gettype($balance) . ' value: ' . (string)$balance);
            
            // Convert BigInteger to string
            if (is_object($balance) && method_exists($balance, 'toString')) {
                $balanceInWei = $balance->toString();
                Log::info('Balance from BigInteger toString(): ' . $balanceInWei);
            } else if (is_object($balance)) {
                $balanceInWei = (string)$balance;
                Log::info('Balance from object cast: ' . $balanceInWei);
            } else if (substr((string)$balance, 0, 2) === '0x') {
                $balanceInWei = hexdec((string)$balance);
                Log::info('Balance from hexdec: ' . $balanceInWei);
            } else {
                $balanceInWei = (string)$balance;
                Log::info('Balance from string cast: ' . $balanceInWei);
            }
            
            // Convert Wei to ETH safely using bcmath
            $balanceInEth = bcdiv($balanceInWei, bcpow(10, 18, 0), 18);
            Log::info('Account balance: ' . $balanceInEth . ' ETH');
            
            // Warn if balance is low (less than 0.01 ETH)
            if (bccomp($balanceInEth, '0.01', 18) < 0) {
                Log::warning(
                    'WARNING: Account balance is very low (' . $balanceInEth . ' ETH). ' .
                    'This may cause transaction failures due to insufficient funds. ' .
                    'Consider adding ETH to account: ' . $this->accountAddress
                );
            }
        } catch (\Exception $e) {
            Log::error('Failed to check account balance: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
        }
    }
    
    public function checkTransactionStatus($transactionHash)
    {
        try {
            $confirmed = false;
            $statusPromise = new Promise(function ($resolve, $reject) use ($transactionHash, &$confirmed) {
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
            
            // Verify directly on the blockchain using the contract's verifyDocument function
            $verified = false;
            $timestamp = 0;
            
            $verifyPromise = new Promise(function ($resolve, $reject) use ($hash, &$verified, &$timestamp) {
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