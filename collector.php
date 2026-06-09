<?php
// collector.php - CLI Background Modbus TCP Data Collector using phpmodbus

if (php_sapi_name() !== 'cli') {
    die("Error: This script must be run in CLI mode (php collector.php).\n");
}

set_time_limit(0);
ob_implicit_flush(true);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/lib/ModbusMasterTcp.php';

echo "=========================================================\n";
echo "       PHP SCADA Background Collector Started            \n";
echo "=========================================================\n";
echo "Polling MySQL for configuration on every iteration...\n";
echo "Using phpmodbus library for data acquisition.\n";
echo "Press Ctrl+C to stop.\n\n";

$db = null;

while (true) {
    try {
        // 1. Establish database connection if not active
        if ($db === null) {
            $db = getDBConnection();
        }

        // 2. Fetch Modbus settings from database
        $stmt = $db->query("SELECT * FROM `modbus_config` ORDER BY `id` ASC LIMIT 1");
        $config = $stmt->fetch();

        if (!$config) {
            echo "[" . date('H:i:s') . "] Warning: No Modbus configuration found in modbus_config table.\n";
            sleep(2);
            continue;
        }

        $ip = $config['device_ip'];
        $port = (int)$config['tcp_port'];
        $registerType = $config['register_type'];
        $startAddr = (int)$config['start_address'];
        $count = (int)$config['register_count'];

        // 3. Instantiate ModbusMasterTcp
        $modbus = new ModbusMasterTcp($ip);
        $modbus->port = $port;
        // Set timeout to 2 seconds
        $modbus->timeout_sec = 2;

        // 4. Poll according to the configuration register type
        $tagValues = [];

        if ($registerType === 'FC01') {
            // Read Coils (FC 01)
            // ModbusMaster::readCoils($unitId, $reference, $quantity)
            // Unit ID is set to 1 by default
            $coils = $modbus->readCoils(1, $startAddr, $count);
            if ($coils === false || !is_array($coils)) {
                throw new Exception("Read coils returned false or invalid structure.");
            }
            for ($k = 0; $k < $count; $k++) {
                $addr = $startAddr + $k;
                $tagValues[$addr] = isset($coils[$k]) ? ($coils[$k] ? 1.0 : 0.0) : 0.0;
            }
        } elseif ($registerType === 'FC02') {
            // Read Input Discretes (FC 02)
            $discretes = $modbus->readInputDiscretes(1, $startAddr, $count);
            if ($discretes === false || !is_array($discretes)) {
                throw new Exception("Read input discretes returned false or invalid structure.");
            }
            for ($k = 0; $k < $count; $k++) {
                $addr = $startAddr + $k;
                $tagValues[$addr] = isset($discretes[$k]) ? ($discretes[$k] ? 1.0 : 0.0) : 0.0;
            }
        } elseif ($registerType === 'FC03') {
            // Read Multiple Holding Registers (FC 03)
            $bytes = $modbus->readMultipleRegisters(1, $startAddr, $count);
            if ($bytes === false || !is_array($bytes)) {
                throw new Exception("Read multiple holding registers returned false or invalid structure.");
            }
            // Parse bytes into 16-bit unsigned integers
            for ($k = 0; $k < $count; $k++) {
                $addr = $startAddr + $k;
                $offset = $k * 2;
                if (isset($bytes[$offset]) && isset($bytes[$offset + 1])) {
                    $regBytes = [$bytes[$offset], $bytes[$offset + 1]];
                    $tagValues[$addr] = (double)PhpType::bytes2unsignedInt($regBytes);
                } else {
                    $tagValues[$addr] = 0.0;
                }
            }
        } elseif ($registerType === 'FC04') {
            // Read Input Registers (FC 04)
            $bytes = $modbus->readInputRegisters(1, $startAddr, $count);
            if ($bytes === false || !is_array($bytes)) {
                throw new Exception("Read input registers returned false or invalid structure.");
            }
            // Parse bytes into 16-bit unsigned integers
            for ($k = 0; $k < $count; $k++) {
                $addr = $startAddr + $k;
                $offset = $k * 2;
                if (isset($bytes[$offset]) && isset($bytes[$offset + 1])) {
                    $regBytes = [$bytes[$offset], $bytes[$offset + 1]];
                    $tagValues[$addr] = (double)PhpType::bytes2unsignedInt($regBytes);
                } else {
                    $tagValues[$addr] = 0.0;
                }
            }
        } else {
            throw new Exception("Unsupported register type: $registerType");
        }

        // 5. Save to Database
        $db->beginTransaction();
        $insertStmt = $db->prepare("INSERT INTO `sensor_data` (`tag_address`, `tag_value`) VALUES (:tag, :val)");
        
        foreach ($tagValues as $tag => $val) {
            $insertStmt->execute(['tag' => $tag, 'val' => $val]);
        }
        
        // Save PLC Status tag (ONLINE = 1.0)
        $insertStmt->execute(['tag' => -1, 'val' => 1.0]);
        
        $db->commit();
        
        echo "[" . date('H:i:s') . "] Polled target successfully via phpmodbus. IP: $ip, Port: $port, Type: $registerType, Registers: $count\n";

    } catch (PDOException $e) {
        echo "[" . date('H:i:s') . "] DATABASE ERROR: " . $e->getMessage() . "\n";
        $db = null; // Reconnect database next loop
    } catch (Exception $e) {
        echo "[" . date('H:i:s') . "] TELEMETRY ERROR: " . $e->getMessage() . "\n";
        
        // Write PLC Status tag to database to report offline (tag_address = -1, tag_value = 0 for OFFLINE)
        if ($db !== null) {
            try {
                $insertStmt = $db->prepare("INSERT INTO `sensor_data` (`tag_address`, `tag_value`) VALUES (-1, 0.0)");
                $insertStmt->execute();
            } catch (PDOException $ex) {
                echo "[" . date('H:i:s') . "] Failed to write offline heartbeat to DB: " . $ex->getMessage() . "\n";
                $db = null;
            }
        }
    }

    sleep(1);
}
