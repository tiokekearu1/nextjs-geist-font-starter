<?php
session_start([
    'cookie_httponly' => true,
    'cookie_secure' => true, // Enable in production with HTTPS
    'cookie_samesite' => 'Strict'
]);

// Authentication Functions
function isAuthenticated() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function checkRole($requiredRole) {
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== $requiredRole) {
        header("Location: /login.php");
        exit;
    }
}

// CSRF Protection
function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken($token) {
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        return false;
    }
    return true;
}

// Password Functions
function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}

function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

// User Session Management
function loginUser($userId, $role) {
    $_SESSION['user_id'] = $userId;
    $_SESSION['role'] = $role;
    session_regenerate_id(true); // Prevent session fixation
}

function logoutUser() {
    $_SESSION = array();
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }
    session_destroy();
}

// Input Sanitization
function sanitizeInput($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}
