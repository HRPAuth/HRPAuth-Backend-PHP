<?php
require_once __DIR__ . '/config/db.php';

// Start session and attempt to remove server-side remember token if present
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!empty($_COOKIE['hrpa_auth'])) {
    $parts = explode('|', $_COOKIE['hrpa_auth'], 2);
    if (count($parts) === 2) {
        [$uid, $token] = $parts;
        try {
            $pdo = getPDO();
            $stmt = $pdo->prepare('SELECT remember_token FROM users WHERE uid = ? LIMIT 1');
            $stmt->execute([$uid]);
            $row = $stmt->fetch();
            if ($row && hash_equals($row['remember_token'] ?? '', $token)) {
                $update = $pdo->prepare('UPDATE users SET remember_token = NULL WHERE uid = ?');
                $update->execute([$uid]);
            }
        } catch (Exception $e) {
            // ignore DB errors while clearing cookies
        }
    }
}

// Clear all cookies available in the request
foreach ($_COOKIE as $name => $value) {
    // Clear without domain
    setcookie($name, '', time() - 3600, '/');

    // Attempt clearing with host as domain (best-effort)
    if (!empty($_SERVER['HTTP_HOST'])) {
        $host = $_SERVER['HTTP_HOST'];
        // strip port if present
        $host = preg_replace('/:\d+$/', '', $host);
        setcookie($name, '', time() - 3600, '/', $host);
    }
}

// Clear PHP session cookie as well
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 3600, $params['path'] ?? '/', $params['domain'] ?? '', $params['secure'] ?? false, $params['httponly'] ?? true);
}

// Clear session data
$_SESSION = [];
if (session_status() !== PHP_SESSION_NONE) {
    session_destroy();
}

// Redirect to login
header('Location: login.php');
exit;
