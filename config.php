<?php
// database & app configuration
define('DB_HOST', 'sql311.infinityfree.com');
define('DB_NAME', 'if0_41111172_soilmoisture_db');
define('DB_USER', 'if0_41111172');
define('DB_PASS', '2e8LXuaxhEwl');

if (session_status() === PHP_SESSION_NONE) session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
date_default_timezone_set('Asia/Manila');

function getDBConnection() {
    try {
        return new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER, DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]
        );
    } catch (PDOException $e) {
        error_log("database connection failed: " . $e->getMessage());
        return null;
    }
}

function isLoggedIn() {
    return !empty($_SESSION['user_id']);
}

// checks Administrator table with per-request caching
function isAdmin() {
    static $cached = null, $cachedUid = null;
    if (!isLoggedIn()) return false;
    if ($cached !== null && $cachedUid === $_SESSION['user_id']) return $cached;
    $cachedUid = $_SESSION['user_id'];
    $db = getDBConnection();
    if ($db) {
        try {
            $stmt = $db->prepare("SELECT administrator_id FROM Administrator WHERE user_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $_SESSION['is_admin'] = $cached = (bool)$stmt->fetch();
            return $cached;
        } catch (PDOException $e) {}
    }
    return $cached = !empty($_SESSION['is_admin']);
}

function isUserActive() {
    if (!isLoggedIn()) return false;
    $db = getDBConnection();
    if ($db) {
        try {
            $stmt = $db->prepare("SELECT is_active FROM Users WHERE user_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch();
            return $user && $user['is_active'];
        } catch (PDOException $e) {}
    }
    return true;
}

function getCurrentUserId() { return $_SESSION['user_id'] ?? null; }
function redirect($url) { header("Location: $url"); exit(); }
function sanitize($input) { return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8'); }

function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
}

function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
?>
