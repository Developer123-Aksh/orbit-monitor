<?php
// Make sure this path is correct for your 'lib' folder
require_once __DIR__ . '/lib/ModbusMasterTcp.php';
require_once __DIR__ . '/lib/PhpType.php';

$host = '127.0.0.1';
$port = 5020; 

echo "Attempting to poll the simulator at $host:$port...\n";

try {
    // USE THIS SPECIFIC CLASS. It only requires 1 argument ($host).
    $modbus = new ModbusMasterTcp($host, "TCP");
    $modbus->port = $port;

    // Test Reading 5 registers (FC03)
    $data = $modbus->readMultipleRegisters(1, 0, 5);
    
    echo "Successfully received data from simulator:\n";
    print_r($data);

    echo "Decoded values:\n";
    foreach(array_chunk($data, 2) as $index => $bytes) {
        echo "Register $index: " . PhpType::bytes2unsignedInt($bytes) . "\n";
    }

} catch (Exception $e) {
    echo "❌ CONNECTION ERROR: " . $e->getMessage() . "\n";
}
?>