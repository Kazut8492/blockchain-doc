<?php

namespace App\Console\Commands;

use App\Services\BlockchainService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use App\Services\Web3\Promise;
use Web3\Web3;
use Web3\Providers\HttpProvider;
use Web3\RequestManagers\HttpRequestManager;

class CheckAccountBalance extends Command
{
    protected $signature = 'blockchain:check-balance';
    protected $description = 'Check the blockchain account balance and gas costs';

    protected $web3;
    protected $accountAddress;

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $this->info('Checking blockchain account balance...');
        
        // Get configuration values
        $providerUrl = config('blockchain.provider_url');
        $this->accountAddress = config('blockchain.account_address');
        
        // Initialize Web3
        $requestManager = new HttpRequestManager($providerUrl, 10);
        $provider = new HttpProvider($requestManager);
        $this->web3 = new Web3($provider);
        
        // Check and display account balance
        try {
            $balance = $this->getAccountBalance();
            $balanceInEth = bcdiv(hexdec($balance), bcpow(10, 18, 18), 18);
            
            $this->info("Account: {$this->accountAddress}");
            $this->info("Balance: {$balanceInEth} ETH");
            
            // Get current gas price
            $gasPrice = $this->getGasPrice();
            $gasPriceGwei = bcdiv(hexdec($gasPrice), bcpow(10, 9, 9), 9);
            
            $this->info("Current gas price: {$gasPriceGwei} Gwei");
            
            // Calculate cost for contract interaction
            $gasLimit = config('blockchain.gas_limit', 200000);
            $estimatedCostWei = bcmul(hexdec($gasPrice), $gasLimit);
            $estimatedCostEth = bcdiv($estimatedCostWei, bcpow(10, 18, 18), 18);
            
            $this->info("Estimated gas limit for contract call: {$gasLimit}");
            $this->info("Estimated cost per transaction: {$estimatedCostEth} ETH");
            
            // Calculate how many transactions are possible with current balance
            if ($estimatedCostWei > 0) {
                $possibleTransactions = bcdiv(hexdec($balance), $estimatedCostWei, 2);
                $this->info("Possible transactions with current balance: approximately {$possibleTransactions}");
            }
            
            // Warning if balance is low
            if (bccomp($balanceInEth, '0.01', 18) < 0) {
                $this->error("WARNING: Account balance is very low. Transaction failures due to insufficient funds are likely!");
                $this->error("Consider adding more ETH to your account.");
            } else if (bccomp($balanceInEth, '0.1', 18) < 0) {
                $this->warn("CAUTION: Account balance is getting low. Consider adding more ETH soon.");
            } else {
                $this->info("Account balance is sufficient for operations.");
            }
            
        } catch (\Exception $e) {
            $this->error("Error checking account balance: " . $e->getMessage());
            Log::error("Error in blockchain:check-balance: " . $e->getMessage());
        }
    }
    
    protected function getAccountBalance()
    {
        $balancePromise = new Promise(function ($resolve, $reject) {
            $this->web3->eth->getBalance($this->accountAddress, 'latest', function ($err, $balance) use ($resolve, $reject) {
                if ($err) {
                    $reject($err);
                    return;
                }
                $resolve($balance);
            });
        });
        
        return $balancePromise->wait();
    }
    
    protected function getGasPrice()
    {
        $gasPricePromise = new Promise(function ($resolve, $reject) {
            $this->web3->eth->gasPrice(function ($err, $price) use ($resolve, $reject) {
                if ($err) {
                    $reject($err);
                    return;
                }
                $resolve($price);
            });
        });
        
        return $gasPricePromise->wait();
    }
}