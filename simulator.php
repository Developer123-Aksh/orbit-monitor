<?php
// simulator.php - Robust CLI Modbus TCP PLC Simulator for Windows

set_time_limit(0);
ob_implicit_flush(true);

$port = 5020;
$address = '0.0.0.0';

echo "=========================================================\n";
echo "       PHP Modbus TCP PLC Simulator Starting...          \n";
echo "=========================================================\n";
echo "Listening on: tcp://$address:$port\n";
echo "Press Ctrl+C to terminate the simulator.\n\n";

$server = stream_socket_server("tcp://$address:$port", $errno, $errstr);

if (!$server) {
    die("Error creating socket server: $errstr ($errno)\n");
}

/**
 * Reads exactly $length bytes from a socket, looping if necessary.
 */
function readExactly($socket, $length) {
    $data = '';
    while (strlen($data) < $length) {
        if (feof($socket)) {
            return false;
        }
        
        $needed = $length - strlen($data);
        $chunk = @fread($socket, $needed);
        
        if ($chunk === false || $chunk === '') {
            return false;
        }
        
        $data .= $chunk;
    }
    return $data;
}

// Single-client connection loop (highly robust on Windows PHP)
while (true) {
    $client = @stream_socket_accept($server, -1);
    
    if (!$client) {
        continue;
    }
    
    $clientName = stream_socket_get_name($client, true);
    echo "[" . date('H:i:s') . "] Client Connected: $clientName\n";
    flush();

    // Set read timeout to 10 seconds to detect dead connections
    stream_set_timeout($client, 10, 0);

    // Read requests in a loop on the same connection
    while (true) {
        // Modbus TCP request is exactly 12 bytes
        $data = readExactly($client, 12);
        
        // Handle disconnect or empty read
        if ($data === false) {
            break;
        }
        
        // Parse Modbus MBAP Header (7 bytes)
        $header = unpack('nTransactionId/nProtocolId/nLength/CUnitId', substr($data, 0, 7));
        
        // Parse PDU (5 bytes)
        $pdu = unpack('CFunctionCode/nStartAddress/nQuantity', substr($data, 7, 5));
        
        $fc = $pdu['FunctionCode'];
        $startAddr = $pdu['StartAddress'];
        $qty = $pdu['Quantity'];
        
        echo "[" . date('H:i:s') . "] Request: FC$fc, Start: $startAddr, Qty: $qty\n";
        flush();
        
        $responseData = '';
        $byteCount = 0;
        $now = time();
        $isException = false;
        $exceptionCode = 0;

        if ($fc === 3 || $fc === 4) { // FC03: Holding Registers, FC04: Input Registers
            $values = [];
            for ($i = 0; $i < $qty; $i++) {
                $addr = $startAddr + $i;
                $val = 0;
                
                // Simulate telemetry loop
                switch ($addr) {
                    case 0: // Temperature
                        $val = round(550 + 150 * sin($now / 45)); // 40.0°C to 70.0°C
                        break;
                    case 1: // Pressure
                        $val = round(1013 + 30 * sin($now / 20) + rand(-3, 3));
                        break;
                    case 2: // Fluid Level
                        $val = ($now % 300) / 3;
                        break;
                    case 3: // Flow Rate
                        $val = round(250 + 40 * cos($now / 15));
                        break;
                    case 4: // Valve Position
                        $val = ($now % 120 < 60) ? 100 : 0;
                        break;
                    case 5: // Motor RPM
                        $val = ($now % 120 < 60) ? round(1450 + rand(-10, 10)) : 0;
                        break;
                    case 6: // Voltage
                        $val = round(2280 + 40 * sin($now / 60) + rand(-5, 5));
                        break;
                    case 7: // Current
                        $val = ($now % 120 < 60) ? round(125 + 15 * sin($now / 10)) : 0;
                        break;
                    case 8: // System Load
                        $val = round(65 + 15 * sin($now / 100) + rand(-2, 2));
                        break;
                    case 9: // Heartbeat Counter
                        $val = $now % 65535;
                        break;
                    default:
                        $val = ($addr * 7) % 1000;
                        break;
                }
                
                $values[] = max(0, min(65535, (int)$val));
            }
            $byteCount = $qty * 2;
            $responseData = pack('n*', ...$values);
            
        } elseif ($fc === 1 || $fc === 2) { // FC01: Coils, FC02: Discrete Inputs
            $bits = [];
            for ($i = 0; $i < $qty; $i++) {
                $addr = $startAddr + $i;
                $bit = 0;
                
                switch ($addr) {
                    case 0: $bit = ($now % 120 < 60) ? 1 : 0; break;
                    case 1: $bit = ($now % 120 < 60) ? 1 : 0; break;
                    case 2: $bit = ($now % 90 < 45) ? 1 : 0; break;
                    case 3: $bit = (sin($now / 20) > 0.7) ? 1 : 0; break;
                    case 4: $bit = (($now % 300) / 3 > 90) ? 1 : 0; break;
                    case 5: $bit = (($now % 300) / 3 < 10) ? 1 : 0; break;
                    case 6: $bit = 1; break;
                    case 7: $bit = 0; break;
                    default: $bit = ($addr % 2); break;
                }
                $bits[] = $bit;
            }
            
            $bytes = [];
            $currentByte = 0;
            for ($i = 0; $i < count($bits); $i++) {
                $bitPos = $i % 8;
                if ($bits[$i]) {
                    $currentByte |= (1 << $bitPos);
                }
                if ($bitPos === 7 || $i === count($bits) - 1) {
                    $bytes[] = $currentByte;
                    $currentByte = 0;
                }
            }
            $byteCount = count($bytes);
            $responseData = pack('C*', ...$bytes);
            
        } else {
            $isException = true;
            $exceptionCode = 0x01; // Illegal Function
        }

        // Response Assembly
        if ($isException) {
            $errFc = $fc | 0x80;
            $pduResponse = pack('CC', $errFc, $exceptionCode);
        } else {
            $pduResponse = pack('CC', $fc, $byteCount) . $responseData;
        }

        $mbapResponse = pack('nnnC', $header['TransactionId'], $header['ProtocolId'], strlen($pduResponse) + 1, $header['UnitId']);
        $responseFrame = $mbapResponse . $pduResponse;
        
        @fwrite($client, $responseFrame);
    }
    
    echo "[" . date('H:i:s') . "] Client Disconnected: $clientName\n";
    flush();
    @fclose($client);
}

fclose($server);
