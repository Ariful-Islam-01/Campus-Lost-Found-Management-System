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

    // Dynamically check and add phone column if not exists
    $checkPhone = $db->query("SHOW COLUMNS FROM users LIKE 'phone'")->fetch();
    if (!$checkPhone) {
        $db->exec("ALTER TABLE users ADD COLUMN phone VARCHAR(20) NULL AFTER email");
    }

    // Dynamically check and add profile_photo column if not exists
    $checkPhoto = $db->query("SHOW COLUMNS FROM users LIKE 'profile_photo'")->fetch();
    if (!$checkPhoto) {
        $db->exec("ALTER TABLE users ADD COLUMN profile_photo VARCHAR(255) NULL AFTER phone");
    }
}

// Auto-initialize the tables
initDatabase();

function getUserByEmail($email) {
    $db = getDBConnection();
    $stmt = $db->prepare("SELECT * FROM users WHERE email = :email");
    $stmt->execute(['email' => $email]);
    return $stmt->fetch();
}

function getUserById($userId) {
    $db = getDBConnection();
    $stmt = $db->prepare("SELECT * FROM users WHERE id = :id");
    $stmt->execute(['id' => $userId]);
    return $stmt->fetch();
}

function updateUserProfile($userId, $name, $phone, $photoPath) {
    $db = getDBConnection();
    if ($photoPath !== null) {
        $stmt = $db->prepare("UPDATE users SET name = :name, phone = :phone, profile_photo = :photo WHERE id = :id");
        return $stmt->execute([
            'name' => $name,
            'phone' => $phone,
            'photo' => $photoPath,
            'id' => $userId
        ]);
    } else {
        $stmt = $db->prepare("UPDATE users SET name = :name, phone = :phone WHERE id = :id");
        return $stmt->execute([
            'name' => $name,
            'phone' => $phone,
            'id' => $userId
        ]);
    }
}

function createLostItem($userId, $itemName, $category, $description, $lastSeenLocation, $dateLost, $photoPath) {
    $db = getDBConnection();
    $stmt = $db->prepare("INSERT INTO lost_items (user_id, item_name, category, description, last_seen_location, date_lost, photo_path) VALUES (:user_id, :item_name, :category, :description, :last_seen_location, :date_lost, :photo_path)");
    return $stmt->execute([
        'user_id' => $userId,
        'item_name' => $itemName,
        'category' => $category,
        'description' => $description,
        'last_seen_location' => $lastSeenLocation,
        'date_lost' => $dateLost,
        'photo_path' => $photoPath
    ]);
}
