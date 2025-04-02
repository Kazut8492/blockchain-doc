<?php

// Test with a known value (100000000000000000 Wei = 0.1 ETH)
$testBalance = '0x16345785d8a0000'; // Hex for 0.1 ETH in Wei
$testBalanceInEth = hexdec($testBalance) / pow(10, 18);
echo "Standard math: Test balance conversion: $testBalanceInEth ETH from $testBalance\n";

// Try with your actual account balance (0.0866 Sepolia ETH)
$actualBalance = '0.0866';
$actualWeiValue = $actualBalance * pow(10, 18); // This is approximate but fine for display
echo "\nYour actual balance: $actualBalance ETH\n";
echo "This would be approximately $actualWeiValue Wei\n";

// Here's the important test to debug your issue:
echo "\nTesting potential balance reading issues:\n";
echo "--------------------------------------\n";

// Let's check if it's reading the wrong value format
$testValues = [
    '0x154937901038610000000', // Your logged value but in hex (might be read incorrectly)
    '0x8C8C', // Small hex value as reference  
    '8C8C', // Without 0x prefix
    '154937901038610000000', // Decimal string (large value)
    '86600000000000000', // Your actual balance in Wei decimal
];

foreach ($testValues as $value) {
    echo "Testing value: $value\n";
    
    // Try converting to decimal
    if (substr($value, 0, 2) === '0x') {
        $decimal = hexdec($value);
        echo "  As hex → decimal: $decimal\n";
    } else {
        $decimal = $value;
        echo "  As decimal: $decimal\n";
    }
    
    // Convert to ETH
    $asEth = $decimal / pow(10, 18);
    echo "  Converted to ETH: $asEth\n\n";
}
