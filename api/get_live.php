<?php
<<<<<<< HEAD
// api/get_live.php - JSON endpoint for live telemetry and daemon status

define('API_REQUEST', true);
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../db.php';

header('Content-Type: application/json');

try {
    $db = getDBConnection();

    // 1. Fetch current Modbus configuration
    $stmtConfig = $db->query("SELECT * FROM `modbus_config` ORDER BY `id` ASC LIMIT 1");
    $config = $stmtConfig->fetch();

    if (!$config) {
        echo json_encode([
            'status' => 'error',
            'message' => 'No active Modbus configuration found.'
        ]);
        exit;
    }

    // 2. Fetch the latest value for each tag address
    $queryReadings = "
        SELECT t1.tag_address, t1.tag_value, UNIX_TIMESTAMP(t1.timestamp) as ts
        FROM `sensor_data` t1
        INNER JOIN (
            SELECT `tag_address`, MAX(`id`) as max_id
            FROM `sensor_data`
            GROUP BY `tag_address`
        ) t2 ON t1.id = t2.max_id
    ";
    $readings = $db->query($queryReadings)->fetchAll();

    // Index readings by tag address
    $latestValues = [];
    $maxTimestamp = 0;
    $plcStatusVal = 0.0; // Default offline

    foreach ($readings as $r) {
        $addr = (int)$r['tag_address'];
        $val = (double)$r['tag_value'];
        $ts = (int)$r['ts'];

        if ($addr === -1) {
            $plcStatusVal = $val;
        } else {
            $latestValues[$addr] = [
                'value' => $val,
                'timestamp' => date('Y-m-d H:i:s', $ts)
            ];
        }

        // Keep track of the most recent activity timestamp to check collector heartbeat
        if ($ts > $maxTimestamp) {
            $maxTimestamp = $ts;
        }
    }

    // 3. Determine Daemon Heartbeat & PLC Connection Status
    $currentTime = time();
    $daemonStatus = 'offline';
    $plcStatus = 'disconnected';

    // If we have any data and the latest record was within the last 6 seconds, daemon is active
    if ($maxTimestamp > 0 && ($currentTime - $maxTimestamp) <= 6) {
        $daemonStatus = 'active';
        if ($plcStatusVal == 1.0) {
            $plcStatus = 'connected';
        }
    }

    // 4. Construct live grid matching current configuration
    $grid = [];
    $start = (int)$config['start_address'];
    $count = (int)$config['register_count'];
    $type = $config['register_type'];

    for ($i = 0; $i < $count; $i++) {
        $addr = $start + $i;
        if (isset($latestValues[$addr])) {
            $grid[] = [
                'address' => $addr,
                'value' => $latestValues[$addr]['value'],
                'timestamp' => $latestValues[$addr]['timestamp'],
                'status' => 'OK'
            ];
        } else {
            $grid[] = [
                'address' => $addr,
                'value' => null,
                'timestamp' => null,
                'status' => 'Pending Polling'
            ];
        }
    }

    echo json_encode([
        'status' => 'success',
        'config' => [
            'device_ip' => $config['device_ip'],
            'tcp_port' => (int)$config['tcp_port'],
            'register_type' => $config['register_type'],
            'start_address' => $start,
            'register_count' => $count
        ],
        'daemon' => [
            'status' => $daemonStatus,
            'plc_connection' => $plcStatus,
            'last_poll' => $maxTimestamp > 0 ? date('Y-m-d H:i:s', $maxTimestamp) : 'Never'
        ],
        'telemetry' => $grid
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Internal server error: ' . $e->getMessage()
    ]);
}
=======
require_once '../auth.php'; // Protects this API endpoint
$pdo = new PDO("mysql:host=localhost;dbname=scada_live_db", "root", "");

// Fetch the most recent value for every active register
$stmt = $pdo->query("
    SELECT tag_address, tag_value 
    FROM sensor_data 
    WHERE id IN (SELECT MAX(id) FROM sensor_data GROUP BY tag_address)
    ORDER BY tag_address ASC
");
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
>>>>>>> 28b37767f32c545b0fd3633c89604c5adf1e3960
