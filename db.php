<?php
// db.php

// MySQL Settings
define('DB_HOST', 'localhost');
define('DB_NAME', 'campus_lost_found');
define('DB_USER', 'root');
define('DB_PASS', '');

function getDBConnection() {
    try {
        $dsn = "mysql:host=" . DB_HOST . ";charset=utf8mb4";
        $pdo = new PDO($dsn, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Create database if not exists
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `" . DB_NAME . "`");
        
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $pdo;
    } catch (PDOException $e) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Database connection failed: ' . $e->getMessage()]);
        exit;
    }
}

function initDatabase() {
    $db = getDBConnection();
    
    $sql = "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        email VARCHAR(255) UNIQUE NOT NULL,
        password_hash VARCHAR(255) NOT NULL,
        verification_code VARCHAR(255),
        is_verified TINYINT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $db->exec($sql);
}

// Auto-initialize the tables
initDatabase();
