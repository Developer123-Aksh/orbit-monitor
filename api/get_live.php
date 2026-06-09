<?php
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