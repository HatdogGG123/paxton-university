<?php
require_once __DIR__ . '/../php/includes/auth.php';
require_once __DIR__ . '/../models/User.php';
ssis_start_session();
header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'POST is required.']);
    exit;
}
$token = $_POST['csrf_token'] ?? '';
if (!is_string($token) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'This form expired. Refresh the page and try again.']);
    exit;
}
if (($_POST['action'] ?? '') !== 'handleUserLogin') {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
    exit;
}
try {
    $userModel = new User(Database::getConnection());
    $user = $userModel->authenticate(trim((string) ($_POST['username'] ?? '')), (string) ($_POST['password'] ?? ''));
    if ($user === null) {
        $message = $userModel->getLastLoginFailureMessage();
        http_response_code(str_starts_with($message, 'Maximum of 5') ? 429 : 401);
        $state = $userModel->getLastLoginFailureState();
        echo json_encode([
            'status' => 'error',
            'message' => $message,
            'data' => [
                'attempts_used' => max(0, 5 - (int) ($state['remaining'] ?? 5)),
                'attempts_remaining' => (int) ($state['remaining'] ?? 5),
                'retry_after' => (int) ($state['retry_after'] ?? 0),
            ],
        ]);
        exit;
    }
    if (($user['role'] ?? '') === 'Faculty') {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Faculty accounts are no longer supported. Please contact an administrator.']);
        exit;
    }
    session_regenerate_id(true);
    $_SESSION['user'] = $user;
    $_SESSION['last_activity'] = time();
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    $destinations = ['Student' => 'php/student/dashboard.php', 'Applicant' => 'php/applicant/dashboard.php', 'Registrar' => 'php/registrar/enrollment.php', 'Cashier' => 'php/cashier/dashboard.php', 'Department' => 'php/department/clearance.php', 'Admin' => 'php/admin/userManagement.php'];
    echo json_encode(['status' => 'success', 'message' => 'Signed in successfully.', 'data' => ['redirect' => ssis_app_base_path() . '/' . ($destinations[$user['role']] ?? 'index.php')]]);
} catch (DomainException $exception) {
    http_response_code(429);
    $state = isset($userModel) ? $userModel->getLastLoginFailureState() : [];
    echo json_encode([
        'status' => 'error',
        'message' => $exception->getMessage(),
        'data' => [
            'attempts_used' => max(0, 5 - (int) ($state['remaining'] ?? 5)),
            'attempts_remaining' => (int) ($state['remaining'] ?? 5),
            'retry_after' => (int) ($state['retry_after'] ?? 0),
        ],
    ]);
} catch (Throwable $exception) {
    error_log('SSIS AJAX login failed: ' . $exception->getMessage());
    http_response_code(503);
    echo json_encode(['status' => 'error', 'message' => 'Sign in is temporarily unavailable. Please try again.']);
}
