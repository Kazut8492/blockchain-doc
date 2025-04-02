<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use kornrunner\Ethereum\Transaction;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TestLowGasTransaction extends Command
{
    protected $signature = 'blockchain:test-lowgas';
    protected $description = 'Test sending a transaction with forced low gas price';

    public function handle()
    {
        $this->info('Testing low gas transaction...');
        
        // Get configuration
        $providerUrl = config('blockchain.provider_url');
        $accountAddress = config('blockchain.account_address');
        $privateKey = config('blockchain.private_key');
        
        $this->info("Provider URL: $providerUrl");
        $this->info("Account: $accountAddress");
        
        try {
            // Get nonce via direct RPC call
            $response = Http::post($providerUrl, [
                'jsonrpc' => '2.0',
                'method' => 'eth_getTransactionCount',
                'params' => [$accountAddress, 'pending'],
                'id' => 1
            ]);
            
            $result = $response->json();
            if (isset($result['error'])) {
                $this->error('RPC error getting nonce: ' . json_encode($result['error']));
                return 1;
            }
            
            $nonce = hexdec($result['result']);
            $this->info("Current nonce: $nonce");
            
            $gasPrice = 3 * pow(10, 9);
            $this->info("Using fixed gas price: 1 Gwei");
            
            // MINIMAL GAS LIMIT - Basic transfer
            $gas = 21000;
            $this->info("Using minimal gas limit: $gas");
            
            // Create simple transaction - sending 0 ETH to yourself
            $transaction = new Transaction(
                $nonce,
                $gasPrice,
                $gas,
                $accountAddress, // Send to self
                0, // 0 ETH
                '', // No data
                11155111 // Sepolia chain ID
            );
            
            $this->info("Transaction created");
            
            // Sign transaction
            $signedTransaction = '0x' . $transaction->getRaw($privateKey);
            $this->info("Transaction signed");
            
            // Calculate cost
            $costWei = $gasPrice * $gas;
            $costEth = $costWei / pow(10, 18);
            $this->info("Estimated cost: $costEth ETH");
            
            // Send transaction
            $response = Http::post($providerUrl, [
                'jsonrpc' => '2.0',
                'method' => 'eth_sendRawTransaction',
                'params' => [$signedTransaction],
                'id' => 1
            ]);
            
            $this->info("RPC response: " . $response->body());
            
            $result = $response->json();
            if (isset($result['error'])) {
                $this->error("RPC error: " . json_encode($result['error']));
                return 1;
            }
            
            if (isset($result['result'])) {
                $txHash = $result['result'];
                $this->info("Transaction sent! Hash: $txHash");
                $this->info("View on Etherscan: https://sepolia.etherscan.io/tx/$txHash");
                return 0;
            }
            
            $this->error("Unexpected response: " . json_encode($result));
            return 1;
            
        } catch (\Exception $e) {
            $this->error("Error: " . $e->getMessage());
            return 1;
        }
    }
}