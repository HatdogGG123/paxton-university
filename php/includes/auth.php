<?php

/** Shared session startup, access checks, and security headers for SSIS pages. */
require_once __DIR__ . '/../../models/Database.php';

/** Return the web path where this application is installed. */
function ssis_app_base_path(): string
{
    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/');
    foreach (['/php/', '/controller/'] as $applicationDirectory) {
        $directoryPosition = strpos($scriptName, $applicationDirectory);
        if ($directoryPosition !== false) {
            return rtrim(substr($scriptName, 0, $directoryPosition), '/');
        }
    }

    $basePath = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
    return $basePath === '' || $basePath === '.' ? '' : $basePath;
}

/** Start a hardened session with an application-scoped cookie. */
function ssis_start_session(): void
{
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');

    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? '') === '443');

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => (ssis_app_base_path() ?: '') . '/',
        'secure' => $isSecure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

/** Record a security event without interrupting the request if auditing fails. */
function ssis_auth_audit(?int $userId, string $action, string $details): void
{
    try {
        $statement = Database::getConnection()->prepare(
            'INSERT INTO audit_logs (user_id, action, entity, details, ip_address)
             VALUES (:user_id, :action, \'Authentication\', :details, :ip_address)'
        );
        $statement->execute([
            'user_id' => $userId,
            'action' => $action,
            'details' => $details,
            'ip_address' => substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45),
        ]);
    } catch (Throwable $exception) {
        error_log('SSIS authentication audit failed: ' . $exception->getMessage());
    }
}

/** Require an authenticated account with an active status and an allowed role. */
function ssis_require_role(string|array $allowedRoles): void
{
    ssis_start_session();
    $allowedRoles = array_map('strtolower', (array) $allowedRoles);

    if (empty($_SESSION['user']['id']) || empty($_SESSION['user']['role'])) {
        header('Location: ' . ssis_app_base_path() . '/login.php');
        exit;
    }

    $now = time();
    $lastActivity = (int) ($_SESSION['last_activity'] ?? 0);
    $sessionTimeoutMinutes = 30;
    try {
        $timeoutQuery = Database::getConnection()->query("SELECT setting_value FROM settings WHERE setting_key = 'session_timeout_minutes' LIMIT 1");
        $timeoutValue = $timeoutQuery->fetchColumn();

        if ($timeoutValue !== false && ctype_digit((string) $timeoutValue)) $sessionTimeoutMinutes = min(480, max(5, (int) $timeoutValue));
    } catch (Throwable $exception) {
        error_log('SSIS session timeout setting lookup failed: ' . $exception->getMessage());
    }
    if ($lastActivity === 0 || ($now - $lastActivity) >= ($sessionTimeoutMinutes * 60)) {
        ssis_auth_audit((int) $_SESSION['user']['id'], 'SESSION_EXPIRED', 'Session expired after inactivity.');
        $_SESSION = [];
        session_destroy();
        header('Location: ' . ssis_app_base_path() . '/login.php?error=expired');
        exit;
    }

    try {
        $statement = Database::getConnection()->prepare('SELECT status FROM users WHERE id = :id LIMIT 1');
        $statement->execute(['id' => (int) $_SESSION['user']['id']]);
        $status = $statement->fetchColumn();
    } catch (Throwable $exception) {
        error_log('SSIS session account check failed: ' . $exception->getMessage());
        http_response_code(503);
        exit('The service is temporarily unavailable. Please try again later.');
    }

    if ($status !== 'Active') {
        ssis_auth_audit((int) $_SESSION['user']['id'], 'INACTIVE_ACCOUNT_BLOCKED', 'Inactive or missing account blocked on page request.');
        $_SESSION = [];
        session_destroy();
        header('Location: ' . ssis_app_base_path() . '/login.php?error=inactive');
        exit;
    }

    $actualRole = strtolower((string) $_SESSION['user']['role']);
    if (!in_array($actualRole, $allowedRoles, true)) {
        ssis_auth_audit((int) $_SESSION['user']['id'], 'ACCESS_DENIED', 'Role ' . $actualRole . ' attempted a restricted page.');
        http_response_code(403);
        exit('You do not have permission to view this page.');
    }

    $_SESSION['last_activity'] = $now;
}
