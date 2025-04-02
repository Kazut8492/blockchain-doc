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
        // 設定値を取得
        $this->contractAddress = config('blockchain.contract_address');
        $this->accountAddress = config('blockchain.account_address');
        $this->privateKey = config('blockchain.private_key');
        
        // Web3初期化
        $providerUrl = config('blockchain.provider_url');
        $requestManager = new HttpRequestManager($providerUrl, 10);
        $provider = new HttpProvider($requestManager);
        $this->web3 = new Web3($provider);
        
        try {
            // ABIをJSONファイルから取得
            $contractAbi = json_decode(file_get_contents(storage_path('app/contract/DocumentVerification.json')), true);
            
            // JSONが配列の場合、直接使用
            if (isset($contractAbi[0])) {
                $this->contract = new Contract($this->web3->provider, $contractAbi);
            } 
            // 'abi'キーがある場合はそれを使用
            else if (isset($contractAbi['abi'])) {
                $this->contract = new Contract($this->web3->provider, $contractAbi['abi']);
            }
            // 形式が認識できない場合はデフォルトABIを使用
            else {
                Log::warning('ABI format not recognized, using default ABI');
                $this->contract = new Contract($this->web3->provider, $this->getDefaultContractAbi());
            }
            
            // コントラクトアドレスを設定
            $this->contract->at($this->contractAddress);
        } catch (\Exception $e) {
            Log::error('Error loading contract ABI: ' . $e->getMessage());
            // デフォルトABIにフォールバック
            $this->contract = new Contract($this->web3->provider, $this->getDefaultContractAbi());
            $this->contract->at($this->contractAddress);
        }
    }
    
    /**
     * Get the default contract ABI array directly
     */
    protected function getDefaultContractAbi()
    {
        // bytes32型を使用する最適化されたABI
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

    protected function convertToBytes32($sha512Hash)
    {
        // SHA512ハッシュをkeccak256でハッシュ化して変換
        return \Web3\Utils::sha3($sha512Hash);
    }
    
    public function registerDocument(Document $document)
    {
        try {
            // 重要な情報をログに記録
            Log::info('Attempting to register document with hash: ' . $document->hash);
            Log::info('Using contract address: ' . $this->contractAddress);
            Log::info('Using account address: ' . $this->accountAddress);
            Log::info('Private key length: ' . strlen($this->privateKey));
            
            // *** 最適化1: SHA512ハッシュをkeccak256に変換 ***
            $bytes32Hash = $this->convertToBytes32($document->hash);
            Log::info('Original SHA512 hash: ' . $document->hash);
            Log::info('Converted to bytes32 hash: ' . $bytes32Hash);
            
            // コントラクト呼び出しデータの取得
            $data = $this->contract->getData('registerDocument', $bytes32Hash);
            if (!$data) {
                Log::error('Failed to encode contract method data');
                return false;
            }
            
            // *** 最適化2: ガス価格を固定 ***
            $gasPrice = '0x' . dechex(10 * pow(10, 9)); // 10 Gwei
            Log::info('Using fixed gas price: 10 Gwei');
            
            // ノンス取得
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
            
            // *** 最適化3: ガスリミットを削減 ***
            $gas = 100000; // 以前は 200000
            Log::info('Using reduced gas limit: ' . $gas);
            
            // トランザクションパラメータ準備
            $txParams = [
                'nonce' => '0x' . dechex($nonce),
                'gasPrice' => $gasPrice, 
                'gas' => '0x' . dechex($gas),
                'to' => $this->contractAddress,
                'value' => '0x0',
                'data' => $data,
                'chainId' => '0xaa36a7' // Sepolia
            ];
            
            // トランザクション作成・署名
            try {
                // ガス価格を10進数に変換
                $gasPriceValue = is_string($gasPrice) && substr($gasPrice, 0, 2) === '0x' 
                    ? hexdec($gasPrice) 
                    : $gasPrice;
                
                // トランザクションオブジェクト作成
                $transaction = new Transaction(
                    $nonce,
                    $gasPriceValue,
                    $gas,
                    $this->contractAddress,
                    0, // value
                    $data, 
                    11155111 // Sepolia chainId
                );
                
                // トランザクション署名
                $signedTransaction = '0x' . $transaction->getRaw($this->privateKey);
                Log::info('Transaction signed successfully');
                
                // RPC呼び出しでトランザクション送信
                $providerUrl = config('blockchain.provider_url');
                $response = Http::post($providerUrl, [
                    'jsonrpc' => '2.0',
                    'method' => 'eth_sendRawTransaction',
                    'params' => [$signedTransaction],
                    'id' => 1
                ]);
                
                $result = $response->json();
                
                if (isset($result['error'])) {
                    Log::error('RPC error sending transaction: ' . json_encode($result['error']));
                    return false;
                }
                
                if (isset($result['result'])) {
                    $transactionHash = $result['result'];
                    
                    // ドキュメントのトランザクションハッシュを更新
                    $document->transaction_hash = $transactionHash;
                    $document->blockchain_network = config('blockchain.network_name', 'Sepolia Testnet');
                    $document->save();
                    
                    Log::info('Document registered on blockchain: ' . $transactionHash);
                    
                    // トランザクション確認をチェック
                    $this->checkTransactionConfirmation($document);
                    
                    return true;
                }
                
                Log::error('Unexpected response from RPC: ' . json_encode($result));
                return false;
                
            } catch (\Exception $e) {
                Log::error('Error signing or sending transaction: ' . $e->getMessage());
                return false;
            }
            
        } catch (\Exception $e) {
            Log::error('Blockchain service error: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            return false;
        }
    }

    /**
     * 統合されたトランザクション確認メソッド
     * 以前のcheckTransactionConfirmationとcheckTransactionStatusを置き換える
     *
     * @param Document $document
     * @return bool トランザクションが確認されたかどうか
     */
    public function checkTransaction(Document $document)
    {
        try {
            if (empty($document->transaction_hash)) {
                Log::warning('Document has no transaction hash: ' . $document->id);
                return false;
            }

            Log::info('Checking transaction: ' . $document->transaction_hash . ' for document: ' . $document->id);
            
            $receipt = null;
            $confirmed = false;
            
            $receiptPromise = new Promise(function ($resolve, $reject) use ($document, &$receipt) {
                $this->web3->eth->getTransactionReceipt(
                    $document->transaction_hash,
                    function ($err, $rcpt) use (&$receipt, $resolve, $reject) {
                        if ($err) {
                            Log::error('Error checking transaction: ' . $err->getMessage());
                            $reject($err);
                            return;
                        }
                        
                        $receipt = $rcpt;
                        $resolve($rcpt);
                    }
                );
            });
            
            // Wait for the receipt to be checked
            $receiptPromise->wait();
            
            // 値が取得できた場合に処理
            if ($receipt && isset($receipt->status)) {
                if ($receipt->status == '0x1') {
                    // Transaction confirmed
                    $document->blockchain_status = 'confirmed';
                    $document->blockchain_timestamp = now();
                    $document->save();
                    
                    Log::info('Document transaction confirmed: ' . $document->transaction_hash);
                    $confirmed = true;
                } else {
                    // Transaction failed
                    $document->blockchain_status = 'failed';
                    $document->save();
                    Log::warning('Document transaction failed: ' . $document->transaction_hash);
                    $confirmed = false;
                }
            } else {
                // Receipt not found or status not available
                Log::info('Transaction not yet confirmed or not found: ' . $document->transaction_hash);
                $confirmed = false;
            }
            
            return $confirmed;
        } catch (\Exception $e) {
            Log::error('Error checking transaction: ' . $e->getMessage(), [
                'document_id' => $document->id,
                'transaction_hash' => $document->transaction_hash,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    public function checkTransactionConfirmation(Document $document)
    {
        return $this->checkTransaction($document);
    }
    
    public function checkTransactionStatus($transactionHash)
    {
        try {
            // Find document with this transaction hash
            $document = Document::where('transaction_hash', $transactionHash)->first();
            
            if ($document) {
                // Use the integrated method but don't save changes
                $confirmed = $this->checkTransaction($document);
                return $confirmed;
            }
            
            // If no document found, do a direct transaction check
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
            $statusPromise->wait();
            
            return $confirmed;
        } catch (\Exception $e) {
            Log::error('Error checking transaction status: ' . $e->getMessage());
            return false;
        }
    }
    
    public function verifyDocumentHash($hash, $transactionHash = null)
    {
        try {
            // ハッシュのブロックチェーン上での検証試行をログに記録
            Log::info("Attempting to verify document hash on blockchain: $hash");
            
            // *** 最適化: SHA512ハッシュをkeccak256に変換 ***
            $bytes32Hash = $this->convertToBytes32($hash);
            Log::info("Original SHA512 hash: $hash");
            Log::info("Converted to bytes32 hash: $bytes32Hash");
            
            // ブロックチェーン上で直接検証
            $verified = false;
            $timestamp = 0;
            
            $verifyPromise = new Promise(function ($resolve, $reject) use ($bytes32Hash, &$verified, &$timestamp) {
                $this->contract->call('verifyDocument', $bytes32Hash, function ($err, $result) use (&$verified, &$timestamp, $resolve, $reject, $bytes32Hash) {
                    if ($err) {
                        Log::error('Blockchain verification error: ' . $err->getMessage());
                        $reject($err);
                        return;
                    }
                    
                    // デバッグ用に結果を記録
                    Log::info('Verification result for hash ' . $bytes32Hash . ': ' . json_encode($result));
                    
                    // $result[0] はドキュメントが存在するかどうかを示す真偽値
                    // $result[1] はドキュメントが登録されたタイムスタンプ
                    if (isset($result[0]) && $result[0] === true) {
                        $verified = true;
                        
                        // タイムスタンプがある場合は取得
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
            
            // 検証完了を待機
            $result = $verifyPromise->wait();
            
            // メイン検証に失敗したがトランザクションハッシュがある場合、
            // フォールバックとしてトランザクションの有効性を確認
            if (!$result['verified'] && $transactionHash) {
                Log::info("Primary verification failed, checking transaction status as fallback: $transactionHash");
                $txConfirmed = $this->checkTransactionStatus($transactionHash);
                
                if ($txConfirmed) {
                    Log::info("Transaction confirmed but document not found in contract. This might indicate contract data issue.");
                    // コントラクトにデータがなくトランザクションが存在する場合、
                    // これは部分的な検証に過ぎない
                    // セキュリティのため、falseを返す
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