<?php
// api/update_network.php - Admin-only endpoint to update Modbus configurations

define('API_REQUEST', true);
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../db.php';

header('Content-Type: application/json');

// Enforce admin privileges
requireAdmin();

// Support both URL-encoded forms and JSON payloads
$input = $_POST;
if (empty($input)) {
    $raw = file_get_contents('php://input');
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        $input = $decoded;
    }
}

// Extract inputs
$deviceIp = isset($input['device_ip']) ? trim($input['device_ip']) : '';
$tcpPort = isset($input['tcp_port']) ? (int)$input['tcp_port'] : 0;
$registerType = isset($input['register_type']) ? trim($input['register_type']) : '';
$startAddress = isset($input['start_address']) ? (int)$input['start_address'] : -1;
$registerCount = isset($input['register_count']) ? (int)$input['register_count'] : 0;

// Validations
$errors = [];

if (empty($deviceIp)) {
    $errors[] = 'Device IP/Host is required.';
} elseif (!filter_var($deviceIp, FILTER_VALIDATE_IP) && !preg_match('/^[a-zA-Z0-9.-]+$/', $deviceIp)) {
    // Allows IP addresses or local hostname strings like 'localhost'
    $errors[] = 'Device IP or Hostname is invalid.';
}

if ($tcpPort < 1 || $tcpPort > 65535) {
    $errors[] = 'TCP Port must be between 1 and 65535.';
}

if (!in_array($registerType, ['FC01', 'FC02', 'FC03', 'FC04'])) {
    $errors[] = 'Register Type must be FC01, FC02, FC03, or FC04.';
}

if ($startAddress < 0 || $startAddress > 65535) {
    $errors[] = 'Start Address must be between 0 and 65535.';
}

if ($registerCount < 1 || $registerCount > 125) {
    // Modbus protocol limit for read registers is normally 125 (holding) or 2000 (coils). 
    // We restrict to 125 to remain standard and keep dashboard clean.
    $errors[] = 'Register Count must be between 1 and 125.';
}

if (!empty($errors)) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => implode(' ', $errors)
    ]);
    exit;
}

try {
    $db = getDBConnection();

    // Since we seeded config, we update the existing config row.
    // Fetch the ID of the first config row.
    $stmt = $db->query("SELECT id FROM `modbus_config` ORDER BY id ASC LIMIT 1");
    $configId = $stmt->fetchColumn();

    if ($configId) {
        $updateStmt = $db->prepare("
            UPDATE `modbus_config` 
            SET `device_ip` = :ip, 
                `tcp_port` = :port, 
                `register_type` = :type, 
                `start_address` = :start, 
                `register_count` = :count 
            WHERE `id` = :id
        ");
        $updateStmt->execute([
            'ip' => $deviceIp,
            'port' => $tcpPort,
            'type' => $registerType,
            'start' => $startAddress,
            'count' => $registerCount,
            'id' => $configId
        ]);
    } else {
        // Fallback: insert new row if config table got cleared somehow
        $insertStmt = $db->prepare("
            INSERT INTO `modbus_config` (`device_ip`, `tcp_port`, `register_type`, `start_address`, `register_count`)
            VALUES (:ip, :port, :type, :start, :count)
        ");
        $insertStmt->execute([
            'ip' => $deviceIp,
            'port' => $tcpPort,
            'type' => $registerType,
            'start' => $startAddress,
            'count' => $registerCount
        ]);
    }

    echo json_encode([
        'status' => 'success',
        'message' => 'PLC configuration updated. Background collector will hot-swap targets immediately.'
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Database update failed: ' . $e->getMessage()
    ]);
}
