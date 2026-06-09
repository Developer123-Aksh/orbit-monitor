<?php
/**
 * ONGC SCADA - Production Data Collector
 * Run via CLI: php collector.php
 */

if (PHP_SAPI !== 'cli') die("Run this via CLI only.\n");

require_once __DIR__ . '/lib/ModbusMasterTcp.php';
require_once __DIR__ . '/lib/PhpType.php';

// Connect to Database
$pdo = new PDO("mysql:host=localhost;dbname=scada_live_db", "root", "");

echo "--- Collector Engine Online ---\n";

while (true) {
    try {
        // 1. Fetch live config from DB
        $config = $pdo->query("SELECT * FROM modbus_config LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        $device = $pdo->query("SELECT * FROM devices LIMIT 1")->fetch(PDO::FETCH_ASSOC);

        // 2. Initialize Modbus (using TCP subclass)
        $modbus = new ModbusMasterTcp($device['ip_address'], "TCP");
        $modbus->port = $device['port'];

        // 3. Polling Logic: Select FC03 or FC04 dynamically
        if ($config['register_type'] === 'FC04') {
            $data = $modbus->readInputRegisters($device['device_id'], $config['start_address'], $config['register_count']);
        } else {
            $data = $modbus->readMultipleRegisters($device['device_id'], $config['start_address'], $config['register_count']);
        }

        // 4. Log data to Database
        $stmt = $pdo->prepare("INSERT INTO sensor_data (tag_address, tag_value) VALUES (?, ?)");
        foreach ($data as $i => $value) {
            $stmt->execute([$config['start_address'] + $i, $value]);
        }
        
        $pdo->query("UPDATE collector_status SET last_poll = NOW()");
        echo "Successfully polled " . count($data) . " registers at " . date('H:i:s') . "\n";

    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
    sleep(3); // 3-second cycle
}
?>