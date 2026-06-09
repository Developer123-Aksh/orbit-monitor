<?php
// api/get_history.php - JSON endpoint for historical telemetry logs

define('API_REQUEST', true);
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../db.php';

header('Content-Type: application/json');

try {
    $db = getDBConnection();

    // Fetch up to 1000 records of valid sensor registers (excluding -1 status tag)
    // We order by id DESC to get the most recent data points, then reverse them chronologically.
    $stmt = $db->query("
        SELECT `tag_address`, `tag_value`, UNIX_TIMESTAMP(`timestamp`) as ts 
        FROM `sensor_data` 
        WHERE `tag_address` >= 0 
        ORDER BY `id` DESC 
        LIMIT 1000
    ");
    $rows = $stmt->fetchAll();

    // Reverse the data to be chronological (from past to present)
    $rows = array_reverse($rows);

    $history = [];
    
    foreach ($rows as $row) {
        $addr = (int)$row['tag_address'];
        $val = (double)$row['tag_value'];
        $timeStr = date('H:i:s', $row['ts']); // HH:MM:SS format for readability

        if (!isset($history[$addr])) {
            $history[$addr] = [];
        }

        $history[$addr][] = [
            'time' => $timeStr,
            'value' => $val
        ];
    }

    echo json_encode([
        'status' => 'success',
        'history' => $history
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Internal server error: ' . $e->getMessage()
    ]);
}
