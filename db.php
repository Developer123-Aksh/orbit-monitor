<?php
// db.php - Database connection and auto-initialization

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'scada_db');

/**
 * Returns a PDO connection instance.
 * Automatically handles database and table creation if they don't exist.
 */
function getDBConnection() {
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    try {
        // First, connect to MySQL server without database to check/create the DB
        $dsn = "mysql:host=" . DB_HOST . ";charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        
        $tempPdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        
        // Create database if it doesn't exist
        $tempPdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $tempPdo = null; // Close connection

        // Now connect to the specific database
        $dsnWithDb = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $pdo = new PDO($dsnWithDb, DB_USER, DB_PASS, $options);

        // Initialize schema
        initializeSchema($pdo);

        return $pdo;
    } catch (PDOException $e) {
        // In a real environment, log this. For CLI/Web output:
        die("Database connection failed: " . $e->getMessage());
    }
}

/**
 * Creates the required tables and seeds default records if they are missing.
 */
function initializeSchema(PDO $pdo) {
    // 1. Create users table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `users` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `username` VARCHAR(50) UNIQUE NOT NULL,
        `password_hash` VARCHAR(255) NOT NULL,
        `role` ENUM('admin', 'operator') NOT NULL
    ) ENGINE=InnoDB;");

    // 2. Create modbus_config table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `modbus_config` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `device_ip` VARCHAR(45) NOT NULL,
        `tcp_port` INT NOT NULL,
        `register_type` ENUM('FC01', 'FC02', 'FC03', 'FC04') NOT NULL,
        `start_address` INT NOT NULL,
        `register_count` INT NOT NULL
    ) ENGINE=InnoDB;");

    // 3. Create sensor_data table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `sensor_data` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `tag_address` INT NOT NULL,
        `tag_value` DOUBLE NOT NULL,
        `timestamp` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB;");

    // Seed default users if table is empty
    $stmt = $pdo->query("SELECT COUNT(*) FROM `users`");
    if ($stmt->fetchColumn() == 0) {
        $usersToSeed = [
            ['username' => 'admin', 'password' => 'admin123', 'role' => 'admin'],
            ['username' => 'operator', 'password' => 'operator123', 'role' => 'operator']
        ];
        
        $insertStmt = $pdo->prepare("INSERT INTO `users` (`username`, `password_hash`, `role`) VALUES (:username, :password_hash, :role)");
        foreach ($usersToSeed as $user) {
            $insertStmt->execute([
                'username' => $user['username'],
                'password_hash' => password_hash($user['password'], PASSWORD_DEFAULT),
                'role' => $user['role']
            ]);
        }
    }

    // Seed default modbus_config if table is empty
    $stmtConfig = $pdo->query("SELECT COUNT(*) FROM `modbus_config`");
    if ($stmtConfig->fetchColumn() == 0) {
        $pdo->exec("INSERT INTO `modbus_config` (`device_ip`, `tcp_port`, `register_type`, `start_address`, `register_count`) 
                    VALUES ('127.0.0.1', 5020, 'FC03', 0, 10)");
    }
}
