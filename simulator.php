<?php
/**
 * VIRTUAL PLC SIMULATOR (For Local Testing Only)
 * Do not upload this to the live server.
 */

if (PHP_SAPI !== 'cli') {
    die("ERROR: This simulator must be run from the command line.\n");
}

$host = "0.0.0.0"; 
$port = 5020; // Using 5020 to avoid needing Administrator privileges on Windows

echo "════════════════════════════════════════════════════\n";
echo "🟢 VIRTUAL SCADA PLC STARTED\n";
echo "   Listening on tcp://$host:$port\n";
echo "   Waiting for connection from data engine...\n";
echo "════════════════════════════════════════════════════\n\n";

$server = stream_socket_server("tcp://$host:$port", $errno, $errstr);
if (!$server) die("Failed to start: $errstr ($errno)\n");

while ($client = @stream_socket_accept($server, -1)) {
    $request = fread($client, 1024);
    
    if ($request && strlen($request) >= 12) {
        $transaction_id = substr($request, 0, 2);
        $protocol_id    = substr($request, 2, 2);
        $unit_id        = $request[6];
        $function_code  = ord($request[7]);
        $register_count = unpack("n", substr($request, 10, 2))[1];

        // Respond to FC03 (Holding) or FC04 (Input)
        if ($function_code === 3 || $function_code === 4) {
            $byte_count = $register_count * 2;
            $response_length = pack("n", 3 + $byte_count);
            
            // Build Modbus Header
            $response = $transaction_id . $protocol_id . $response_length . $unit_id . chr($function_code) . chr($byte_count);
            
            // Generate synthetic integer readings (e.g., matching the 4200-4300 range in your live data)
            for ($i = 0; $i < $register_count; $i++) {
                // Add a bit of random noise to make the charts move
                $simulated_value = rand(4250, 4350); 
                $response .= pack("n", $simulated_value);
            }
            
            fwrite($client, $response);
            echo "[" . date('H:i:s') . "] ✅ Handled request. Sent $register_count registers.\n";
        }
    }
    fclose($client);
}
fclose($server);
?>