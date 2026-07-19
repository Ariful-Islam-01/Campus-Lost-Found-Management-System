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
        role VARCHAR(20) NOT NULL DEFAULT 'user',
        phone VARCHAR(20) NULL,
        profile_photo VARCHAR(255) NULL,
        password_hash VARCHAR(255) NOT NULL,
        verification_code VARCHAR(255),
        is_verified TINYINT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $db->exec($sql);

    $checkRole = $db->query("SHOW COLUMNS FROM users LIKE 'role'")->fetch();
    if (!$checkRole) {
        $db->exec("ALTER TABLE users ADD COLUMN role VARCHAR(20) NOT NULL DEFAULT 'user' AFTER email");
    }

    // Dynamically check and add phone column if not exists
    $checkPhone = $db->query("SHOW COLUMNS FROM users LIKE 'phone'")->fetch();
    if (!$checkPhone) {
        $db->exec("ALTER TABLE users ADD COLUMN phone VARCHAR(20) NULL AFTER role");
    }

    // Dynamically check and add profile_photo column if not exists
    $checkPhoto = $db->query("SHOW COLUMNS FROM users LIKE 'profile_photo'")->fetch();
    if (!$checkPhoto) {
        $db->exec("ALTER TABLE users ADD COLUMN profile_photo VARCHAR(255) NULL AFTER phone");
    }

    // Create lost_items table if not exists
    $sqlLost = "CREATE TABLE IF NOT EXISTS lost_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        item_name VARCHAR(255) NOT NULL,
        category VARCHAR(100) NOT NULL,
        description TEXT NOT NULL,
        last_seen_location VARCHAR(255) NOT NULL,
        date_lost DATE NOT NULL,
        photo_path VARCHAR(255) NULL,
        status VARCHAR(50) NOT NULL DEFAULT 'Lost',
        moderation_status VARCHAR(20) NOT NULL DEFAULT 'Approved',
        moderated_at TIMESTAMP NULL DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $db->exec($sqlLost);

    // Create found_items table if not exists
    $sqlFound = "CREATE TABLE IF NOT EXISTS found_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        item_name VARCHAR(255) NOT NULL,
        category VARCHAR(100) NOT NULL,
        description TEXT NOT NULL,
        pickup_location VARCHAR(255) NOT NULL,
        photo_path VARCHAR(255) NOT NULL,
        status VARCHAR(50) NOT NULL DEFAULT 'Found',
        moderation_status VARCHAR(20) NOT NULL DEFAULT 'Approved',
        moderated_at TIMESTAMP NULL DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $db->exec($sqlFound);

    // Create claims table if not exists
    $sqlClaims = "CREATE TABLE IF NOT EXISTS claims (
        id INT AUTO_INCREMENT PRIMARY KEY,
        found_item_id INT NOT NULL,
        claimant_user_id INT NOT NULL,
        proof_of_ownership TEXT NOT NULL,
        status VARCHAR(50) NOT NULL DEFAULT 'Pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (found_item_id) REFERENCES found_items(id) ON DELETE CASCADE,
        FOREIGN KEY (claimant_user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $db->exec($sqlClaims);

    $adminEmail = 'admin@campus.local';
    $adminPassword = 'Admin@1234';
    $adminUser = getUserByEmail($adminEmail);
    if (!$adminUser) {
        $stmt = $db->prepare("INSERT INTO users (name, email, role, password_hash, is_verified) VALUES (:name, :email, 'admin', :password_hash, 1)");
        $stmt->execute([
            'name' => 'Campus Admin',
            'email' => $adminEmail,
            'password_hash' => password_hash($adminPassword, PASSWORD_DEFAULT)
        ]);
    } elseif (($adminUser['role'] ?? 'user') !== 'admin') {
        $stmt = $db->prepare("UPDATE users SET role = 'admin', is_verified = 1 WHERE id = :id");
        $stmt->execute(['id' => $adminUser['id']]);
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

function getUniqueLocations()
{
    $db = getDBConnection();
    $stmt = $db->query(
        "SELECT location FROM (
            SELECT last_seen_location AS location FROM lost_items
            UNION
            SELECT pickup_location AS location FROM found_items
        ) AS combined
        WHERE TRIM(location) <> ''
        ORDER BY location ASC"
    );

    $locations = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $locations[] = $row['location'];
    }

    return $locations;
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
    $stmt = $db->prepare("INSERT INTO lost_items (user_id, item_name, category, description, last_seen_location, date_lost, photo_path, status, moderation_status) VALUES (:user_id, :item_name, :category, :description, :last_seen_location, :date_lost, :photo_path, 'Lost', 'Approved')");
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

function getLostItems($filters = []) {
    $db = getDBConnection();
    $sql = "SELECT li.*, u.name as reporter_name, u.email as reporter_email, u.phone as reporter_phone 
            FROM lost_items li 
            JOIN users u ON li.user_id = u.id";
    
    $where = [];
    $params = [];
    
    if (!empty($filters['search'])) {
        $where[] = "(li.item_name LIKE :search_item OR li.description LIKE :search_desc OR u.name LIKE :search_user)";
        $search = '%' . $filters['search'] . '%';
        $params['search_item'] = $search;
        $params['search_desc'] = $search;
        $params['search_user'] = $search;
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

    $where[] = "li.moderation_status <> 'Archived'";
    
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
    $stmt = $db->prepare("INSERT INTO found_items (user_id, item_name, category, description, pickup_location, photo_path, status, moderation_status) VALUES (:user_id, :item_name, :category, :description, :pickup_location, :photo_path, 'Found', 'Approved')");
    return $stmt->execute([
        'user_id' => $userId,
        'item_name' => $itemName,
        'category' => $category,
        'description' => $description,
        'pickup_location' => $pickupLocation,
        'photo_path' => $photoPath
    ]);
}

function getFoundItems($filters = []) {
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

    $where[] = "fi.moderation_status <> 'Archived'";
    
    if (!empty($where)) {
        $sql .= " WHERE " . implode(" AND ", $where);
    }
    
    $sql .= " ORDER BY fi.created_at DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function getPaginatedItems($type, $category, $search, $limit, $offset) {
    $db = getDBConnection();
    
    $queries = [];
    $lostSearchCond = "";
    $foundSearchCond = "";
    if (!empty($search)) {
        $lostSearchCond = " AND (item_name LIKE :search OR description LIKE :search OR last_seen_location LIKE :search) ";
        $foundSearchCond = " AND (item_name LIKE :search OR description LIKE :search OR pickup_location LIKE :search) ";
    }
    
    $catCond = "";
    if (!empty($category)) {
        $catCond = " AND category = :category ";
    }

    if ($type === 'All' || $type === 'Lost') {
        $lostQuery = "SELECT 
            'Lost' AS type,
            li.id,
            li.user_id,
            li.item_name,
            li.category,
            li.description,
            li.last_seen_location AS location,
            li.date_lost AS item_date,
            li.photo_path,
            li.status,
            li.created_at,
            u.name as reporter_name,
            u.email as reporter_email,
            u.phone as reporter_phone
        FROM lost_items li
        JOIN users u ON li.user_id = u.id
        WHERE 1=1" . $catCond . $lostSearchCond;
        $queries[] = $lostQuery;
    }
    
    if ($type === 'All' || $type === 'Found') {
        $foundQuery = "SELECT 
            'Found' AS type,
            fi.id,
            fi.user_id,
            fi.item_name,
            fi.category,
            fi.description,
            fi.pickup_location AS location,
            fi.created_at AS item_date,
            fi.photo_path,
            fi.status,
            fi.created_at,
            u.name as reporter_name,
            u.email as reporter_email,
            u.phone as reporter_phone
        FROM found_items fi
        JOIN users u ON fi.user_id = u.id
        WHERE 1=1" . $catCond . $foundSearchCond;
        $queries[] = $foundQuery;
    }
    
    $sql = implode(" UNION ALL ", $queries);
    $sql .= " ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
    
    $stmt = $db->prepare($sql);
    
    if (!empty($category)) {
        $stmt->bindValue(':category', $category, PDO::PARAM_STR);
    }
    if (!empty($search)) {
        $stmt->bindValue(':search', '%' . $search . '%', PDO::PARAM_STR);
    }
    $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
    
    $stmt->execute();
    return $stmt->fetchAll();
}

function getTotalItemsCount($type, $category, $search) {
    $db = getDBConnection();
    
    $queries = [];
    $lostSearchCond = "";
    $foundSearchCond = "";
    if (!empty($search)) {
        $lostSearchCond = " AND (item_name LIKE :search OR description LIKE :search OR last_seen_location LIKE :search) ";
        $foundSearchCond = " AND (item_name LIKE :search OR description LIKE :search OR pickup_location LIKE :search) ";
    }
    
    $catCond = "";
    if (!empty($category)) {
        $catCond = " AND category = :category ";
    }

    if ($type === 'All' || $type === 'Lost') {
        $queries[] = "SELECT id FROM lost_items WHERE 1=1" . $catCond . $lostSearchCond;
    }
    
    if ($type === 'All' || $type === 'Found') {
        $queries[] = "SELECT id FROM found_items WHERE 1=1" . $catCond . $foundSearchCond;
    }
    
    $sql = "SELECT COUNT(*) FROM (" . implode(" UNION ALL ", $queries) . ") AS combined";
    $stmt = $db->prepare($sql);
    
    if (!empty($category)) {
        $stmt->bindValue(':category', $category, PDO::PARAM_STR);
    }
    if (!empty($search)) {
        $stmt->bindValue(':search', '%' . $search . '%', PDO::PARAM_STR);
    }
    
    $stmt->execute();
    return (int)$stmt->fetchColumn();
}

function getLostItemById($itemId)
{
    $db = getDBConnection();
    $stmt = $db->prepare("SELECT li.*, u.name as reporter_name, u.email as reporter_email, u.phone as reporter_phone FROM lost_items li JOIN users u ON li.user_id = u.id WHERE li.id = :id");
    $stmt->execute(['id' => $itemId]);
    return $stmt->fetch();
}

function getFoundItemById($itemId)
{
    $db = getDBConnection();
    $stmt = $db->prepare("SELECT fi.*, u.name as finder_name, u.email as finder_email, u.phone as finder_phone FROM found_items fi JOIN users u ON fi.user_id = u.id WHERE fi.id = :id");
    $stmt->execute(['id' => $itemId]);
    return $stmt->fetch();
}

function createClaim($foundItemId, $claimantUserId, $proofOfOwnership)
{
    $db = getDBConnection();
    $stmt = $db->prepare("INSERT INTO claims (found_item_id, claimant_user_id, proof_of_ownership, status) VALUES (:found_item_id, :claimant_user_id, :proof_of_ownership, 'Pending')");
    return $stmt->execute([
        'found_item_id' => $foundItemId,
        'claimant_user_id' => $claimantUserId,
        'proof_of_ownership' => $proofOfOwnership
    ]);
}

function getClaimsForItem($foundItemId)
{
    $db = getDBConnection();
    $stmt = $db->prepare("SELECT c.*, u.name as claimant_name, u.email as claimant_email, u.phone as claimant_phone FROM claims c JOIN users u ON c.claimant_user_id = u.id WHERE c.found_item_id = :found_item_id ORDER BY c.created_at DESC");
    $stmt->execute(['found_item_id' => $foundItemId]);
    return $stmt->fetchAll();
}

function getClaimById($claimId)
{
    $db = getDBConnection();
    $stmt = $db->prepare("SELECT c.*, u.name as claimant_name, u.email as claimant_email, u.phone as claimant_phone, fi.item_name, fi.status FROM claims c JOIN users u ON c.claimant_user_id = u.id JOIN found_items fi ON c.found_item_id = fi.id WHERE c.id = :id");
    $stmt->execute(['id' => $claimId]);
    return $stmt->fetch();
}

function updateClaimStatus($claimId, $status)
{
    $db = getDBConnection();
    $stmt = $db->prepare("UPDATE claims SET status = :status WHERE id = :id");
    return $stmt->execute(['status' => $status, 'id' => $claimId]);
}

function getClaimsByUser($userId)
{
    $db = getDBConnection();
    $stmt = $db->prepare("SELECT c.*, fi.item_name, fi.photo_path, fi.status FROM claims c JOIN found_items fi ON c.found_item_id = fi.id WHERE c.claimant_user_id = :user_id ORDER BY c.created_at DESC");
    $stmt->execute(['user_id' => $userId]);
    return $stmt->fetchAll();
}

function getAdminDashboardStats()
{
    $db = getDBConnection();

    $lostCount = (int)$db->query("SELECT COUNT(*) FROM lost_items WHERE status = 'Lost'")->fetchColumn();
    $foundCount = (int)$db->query("SELECT COUNT(*) FROM found_items WHERE status = 'Found'")->fetchColumn();
    $claimedCount = (int)$db->query("SELECT COUNT(DISTINCT found_item_id) FROM claims")->fetchColumn();
    $returnedCount = (int)$db->query("SELECT (
        SELECT COUNT(*) FROM lost_items WHERE status = 'Returned'
    ) + (
        SELECT COUNT(*) FROM found_items WHERE status = 'Returned'
    )")->fetchColumn();
    $pendingClaims = (int)$db->query("SELECT COUNT(*) FROM claims WHERE status = 'Pending'")->fetchColumn();

    return [
        'lost' => $lostCount,
        'found' => $foundCount,
        'claimed' => $claimedCount,
        'returned' => $returnedCount,
        'pending_claims' => $pendingClaims,
    ];
}

function getRecentAdminActivity($limit = 10)
{
    $db = getDBConnection();
    $sql = "
        SELECT * FROM (
            SELECT
                'lost' AS activity_kind,
                li.id AS entity_id,
                li.item_name AS title,
                li.status AS item_status,
                li.created_at AS activity_time,
                u.name AS actor_name,
                CONCAT('Lost item reported at ', li.last_seen_location) AS details,
                'item-detail.php?id=' AS base_link,
                'lost' AS item_type
            FROM lost_items li
            JOIN users u ON li.user_id = u.id

            UNION ALL

            SELECT
                'found' AS activity_kind,
                fi.id AS entity_id,
                fi.item_name AS title,
                fi.status AS item_status,
                fi.created_at AS activity_time,
                u.name AS actor_name,
                CONCAT('Found item reported at ', fi.pickup_location) AS details,
                'item-detail.php?id=' AS base_link,
                'found' AS item_type
            FROM found_items fi
            JOIN users u ON fi.user_id = u.id

            UNION ALL

            SELECT
                'claim' AS activity_kind,
                fi.id AS entity_id,
                fi.item_name AS title,
                c.status AS item_status,
                c.updated_at AS activity_time,
                cu.name AS actor_name,
                CONCAT('Claim ', LOWER(c.status), ' by ', cu.name) AS details,
                'item-detail.php?id=' AS base_link,
                'found' AS item_type
            FROM claims c
            JOIN found_items fi ON c.found_item_id = fi.id
            JOIN users cu ON c.claimant_user_id = cu.id
        ) AS activity
        ORDER BY activity_time DESC
        LIMIT :limit";

    $stmt = $db->prepare($sql);
    $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
    $stmt->execute();

    $rows = [];
    foreach ($stmt->fetchAll() as $row) {
        $link = $row['base_link'] . $row['entity_id'] . '&type=' . $row['item_type'];
        $kind = $row['activity_kind'];
        $status = strtolower((string)$row['item_status']);

        $rows[] = [
            'kind' => $kind,
            'title' => $row['title'],
            'status' => $row['item_status'],
            'actor_name' => $row['actor_name'],
            'details' => $row['details'],
            'activity_time' => date('c', strtotime($row['activity_time'])),
            'link' => $link,
            'badge_class' => $status,
        ];
    }

    return $rows;
}

