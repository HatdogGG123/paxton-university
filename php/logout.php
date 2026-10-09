<?php
/** Destroy the current SSIS session and return the user to the login page. */
require_once __DIR__ . '/includes/auth.php';
ssis_start_session();

$userId = isset($_SESSION['user']['id']) ? (int) $_SESSION['user']['id'] : null;
if ($userId !== null) {
    ssis_auth_audit($userId, 'LOGOUT', 'User logged out.');
}

$_SESSION = [];
$cookie = session_get_cookie_params();
if (ini_get('session.use_cookies')) {
    setcookie(session_name(), '', [
        'expires' => time() - 42000,
        'path' => $cookie['path'],
        'domain' => $cookie['domain'],
        'secure' => $cookie['secure'],
        'httponly' => $cookie['httponly'],
        'samesite' => $cookie['samesite'] ?? 'Lax',
    ]);
}
session_destroy();

header('Location: ' . ssis_app_base_path() . '/login.php?logged_out=1');
exit;
