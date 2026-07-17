<?php
// db.php

// MySQL Settings
define('DB_HOST', 'localhost');
define('DB_NAME', 'campus_lost_found');
define('DB_USER', 'root');
define('DB_PASS', '');

function getDBConnection()
{
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

function initDatabase()
{
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

function getUserByEmail($email)
{
    $db = getDBConnection();
    $stmt = $db->prepare("SELECT * FROM users WHERE email = :email");
    $stmt->execute(['email' => $email]);
    return $stmt->fetch();
}

function getUserById($userId)
{
    $db = getDBConnection();
    $stmt = $db->prepare("SELECT * FROM users WHERE id = :id");
    $stmt->execute(['id' => $userId]);
    return $stmt->fetch();
}

function updateUserProfile($userId, $name, $phone, $photoPath)
{
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

function createLostItem($userId, $itemName, $category, $description, $lastSeenLocation, $dateLost, $photoPath)
{
    $db = getDBConnection();
    $stmt = $db->prepare("INSERT INTO lost_items (user_id, item_name, category, description, last_seen_location, date_lost, photo_path, status) VALUES (:user_id, :item_name, :category, :description, :last_seen_location, :date_lost, :photo_path, 'Lost')");
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

function getLostItems($filters = [])
{
    $db = getDBConnection();
    $sql = "SELECT li.*, u.name as reporter_name, u.email as reporter_email, u.phone as reporter_phone 
            FROM lost_items li 
            JOIN users u ON li.user_id = u.id";

    $where = [];
    $params = [];

    if (!empty($filters['search'])) {
        $where[] = "(li.item_name LIKE :search OR li.description LIKE :search OR u.name LIKE :search)";
        $params['search'] = '%' . $filters['search'] . '%';
    }

    if (!empty($filters['category'])) {
        $where[] = "li.category = :category";
        $params['category'] = $filters['category'];
    }

    if (!empty($filters['location'])) {
        $where[] = "li.last_seen_location = :location";
        $params['location'] = $filters['location'];
    }

    if (!empty($filters['date_range'])) {
        $today = date('Y-m-d');
        switch ($filters['date_range']) {
            case 'today':
                $where[] = "li.date_lost = :today";
                $params['today'] = $today;
                break;
            case '7days':
                $where[] = "li.date_lost >= :seven_days_ago";
                $params['seven_days_ago'] = date('Y-m-d', strtotime('-7 days'));
                break;
            case '30days':
                $where[] = "li.date_lost >= :thirty_days_ago";
                $params['thirty_days_ago'] = date('Y-m-d', strtotime('-30 days'));
                break;
            case 'older':
                $where[] = "li.date_lost < :thirty_days_ago";
                $params['thirty_days_ago'] = date('Y-m-d', strtotime('-30 days'));
                break;
        }
    }

    if (!empty($where)) {
        $sql .= " WHERE " . implode(" AND ", $where);
    }

    $sql .= " ORDER BY li.created_at DESC";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function createFoundItem($userId, $itemName, $category, $description, $pickupLocation, $photoPath)
{
    $db = getDBConnection();
    $stmt = $db->prepare("INSERT INTO found_items (user_id, item_name, category, description, pickup_location, photo_path, status) VALUES (:user_id, :item_name, :category, :description, :pickup_location, :photo_path, 'Found')");
    return $stmt->execute([
        'user_id' => $userId,
        'item_name' => $itemName,
        'category' => $category,
        'description' => $description,
        'pickup_location' => $pickupLocation,
        'photo_path' => $photoPath
    ]);
}

function getFoundItems($filters = [])
{
    $db = getDBConnection();
    $sql = "SELECT fi.*, u.name as finder_name, u.email as finder_email, u.phone as finder_phone 
            FROM found_items fi 
            JOIN users u ON fi.user_id = u.id";

    $where = [];
    $params = [];

    if (!empty($filters['search'])) {
        $where[] = "(fi.item_name LIKE :search OR fi.description LIKE :search OR u.name LIKE :search)";
        $params['search'] = '%' . $filters['search'] . '%';
    }

    if (!empty($filters['category'])) {
        $where[] = "fi.category = :category";
        $params['category'] = $filters['category'];
    }

    if (!empty($filters['location'])) {
        $where[] = "fi.pickup_location = :location";
        $params['location'] = $filters['location'];
    }

    if (!empty($filters['date_range'])) {
        $today_start = date('Y-m-d 00:00:00');
        switch ($filters['date_range']) {
            case 'today':
                $where[] = "fi.created_at >= :today_start";
                $params['today_start'] = $today_start;
                break;
            case '7days':
                $where[] = "fi.created_at >= :seven_days_ago";
                $params['seven_days_ago'] = date('Y-m-d 00:00:00', strtotime('-7 days'));
                break;
            case '30days':
                $where[] = "fi.created_at >= :thirty_days_ago";
                $params['thirty_days_ago'] = date('Y-m-d 00:00:00', strtotime('-30 days'));
                break;
            case 'older':
                $where[] = "fi.created_at < :thirty_days_ago";
                $params['thirty_days_ago'] = date('Y-m-d 00:00:00', strtotime('-30 days'));
                break;
        }
    }

    if (!empty($where)) {
        $sql .= " WHERE " . implode(" AND ", $where);
    }

    $sql .= " ORDER BY fi.created_at DESC";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function getUniqueLocations()
{
    $db = getDBConnection();
    $sql = "SELECT DISTINCT last_seen_location AS location FROM lost_items
            UNION
            SELECT DISTINCT pickup_location AS location FROM found_items
            ORDER BY location ASC";
    $stmt = $db->query($sql);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

function getLostItemById($id)
{
    $db = getDBConnection();
    $stmt = $db->prepare("SELECT li.*, u.name as reporter_name, u.email as reporter_email, u.phone as reporter_phone 
                          FROM lost_items li 
                          JOIN users u ON li.user_id = u.id 
                          WHERE li.id = :id");
    $stmt->execute(['id' => $id]);
    return $stmt->fetch();
}

function getFoundItemById($id)
{
    $db = getDBConnection();
    $stmt = $db->prepare("SELECT fi.*, u.name as finder_name, u.email as finder_email, u.phone as finder_phone 
                          FROM found_items fi 
                          JOIN users u ON fi.user_id = u.id 
                          WHERE fi.id = :id");
    $stmt->execute(['id' => $id]);
    return $stmt->fetch();
}
