<?php
session_start();
header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed.']);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Authentication required.']);
    exit;
}

$currentAdmin = getUserById($_SESSION['user_id']);
if (!$currentAdmin || strtolower($currentAdmin['role']) !== 'admin') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Admin permissions required.']);
    exit;
}

function sanitize($value)
{
    return trim(filter_var($value, FILTER_SANITIZE_STRING));
}

$action = isset($_POST['action']) ? sanitize($_POST['action']) : '';
$userId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
$csrfToken = isset($_POST['csrf_token']) ? trim($_POST['csrf_token']) : '';

if (empty($csrfToken) || !hash_equals($_SESSION['csrf_token'] ?? '', $csrfToken)) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token.']);
    exit;
}

if ($userId <= 0) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid user ID.']);
    exit;
}

if ($userId === $currentAdmin['id'] && in_array($action, ['delete', 'deactivate'], true)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'You cannot modify your own admin account.']);
    exit;
}

$targetUser = getUserById($userId);
if (!$targetUser) {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'message' => 'Requested user not found.']);
    exit;
}

try {
    if ($action === 'activate') {
        if (setUserStatus($userId, 'Active')) {
            logAdminAction($currentAdmin['id'], 'Activate User', 'user', $userId, 'Reactivated user account');
            echo json_encode(['status' => 'success', 'message' => 'User account has been activated.']);
            exit;
        }
    }

    if ($action === 'deactivate') {
        if (setUserStatus($userId, 'Inactive')) {
            logAdminAction($currentAdmin['id'], 'Deactivate User', 'user', $userId, 'Deactivated user account');
            echo json_encode(['status' => 'success', 'message' => 'User account has been deactivated.']);
            exit;
        }
    }

    if ($action === 'delete') {
        if (deleteUserById($userId)) {
            logAdminAction($currentAdmin['id'], 'Delete User', 'user', $userId, 'Deleted user account permanently');
            echo json_encode(['status' => 'success', 'message' => 'User account has been deleted permanently.']);
            exit;
        }
    }

    if ($action === 'edit') {
        $name = isset($_POST['name']) ? trim($_POST['name']) : '';
        $email = isset($_POST['email']) ? trim($_POST['email']) : '';
        $role = isset($_POST['role']) ? trim($_POST['role']) : 'user';

        if ($name === '' || $email === '') {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Name and email are required.']);
            exit;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Please enter a valid email address.']);
            exit;
        }

        if (!in_array($role, ['admin', 'user'], true)) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Invalid role selected.']);
            exit;
        }

        if (isEmailTaken($email, $userId)) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'This email address is already in use.']);
            exit;
        }

        if (updateUserAccount($userId, $name, $email, $role)) {
            logAdminAction($currentAdmin['id'], 'Edit User', 'user', $userId, 'Updated user profile and role');
            echo json_encode(['status' => 'success', 'message' => 'User account updated successfully.']);
            exit;
        }
    }

    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid action request.']);
    exit;
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'An unexpected server error occurred.']);
    exit;
}
