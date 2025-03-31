<?php

return [
    // Ethereum network provider URL (Infura, Alchemy, etc.)
    'provider_url' => env('BLOCKCHAIN_PROVIDER_URL'),
    
    // Smart contract address
    'contract_address' => env('BLOCKCHAIN_CONTRACT_ADDRESS'),
    
    // Account address used for sending transactions
    'account_address' => env('BLOCKCHAIN_ACCOUNT_ADDRESS'),
    
    // Private key for the account (stored securely)
    'private_key' => env('BLOCKCHAIN_PRIVATE_KEY'),
    
    // Gas limit for transactions
    'gas_limit' => env('BLOCKCHAIN_GAS_LIMIT', 100000),
    
    // Gas price in Gwei
    'gas_price' => env('BLOCKCHAIN_GAS_PRICE', 20),
];
