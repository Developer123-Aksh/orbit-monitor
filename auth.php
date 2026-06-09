<?php
// auth.php - Session-based Authentication and Role-Based Access Control

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Helper to check if the request is an API call
$isApi = (strpos($_SERVER['REQUEST_URI'], '/api/') !== false) || defined('API_REQUEST');

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['username']) || !isset($_SESSION['role'])) {
    if ($isApi) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Unauthorized. Please log in.']);
        exit;
    } else {
        header('Location: login.php');
        exit;
    }
}

/**
 * Ensures the logged-in user is an administrator.
 * Denies access if the user is an operator.
 */
function requireAdmin() {
    global $isApi;
    if ($_SESSION['role'] !== 'admin') {
        if ($isApi) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Forbidden. Admin role required.']);
            exit;
        } else {
            // Redirect to dashboard with a flash message
            $_SESSION['error'] = 'Access denied. Administrator privileges required.';
            header('Location: dashboard.php');
            exit;
        }
    }
}

/**
 * Checks if the logged-in user is an admin.
 * @return bool
 */
function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}
