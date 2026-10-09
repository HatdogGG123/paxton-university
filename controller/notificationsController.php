<?php
require_once __DIR__ . '/../php/includes/auth.php';
require_once __DIR__ . '/../models/Notifications.php';
ssis_start_session();
header('Content-Type: application/json; charset=utf-8');
$role = strtolower((string) ($_SESSION['user']['role'] ?? ''));
if (!in_array($role, ['student', 'applicant', 'registrar', 'cashier', 'department', 'admin'], true)) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Access denied.']);
    exit;
}
ssis_require_role($role);
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'POST is required.']);
    exit;
}
$token = $_POST['csrf_token'] ?? '';
if (!is_string($token) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'This form expired. Refresh the page and try again.']);
    exit;
}
try {
    $model = new Notifications(Database::getConnection());
    $id = (int) $_SESSION['user']['id'];
    $action = $_POST['notification_action'] ?? '';
    if ($action === 'read' && filter_var($_POST['notification_id'] ?? null, FILTER_VALIDATE_INT)) {
        $model->markRead($id, (int) $_POST['notification_id']);
        $message = 'Notification marked as read.';
    } elseif ($action === 'read_all') {
        $model->markAllRead($id);
        $message = 'All notifications marked as read.';
    } else
        throw new DomainException('Choose a valid notification action.');
    echo json_encode([
        'status' => 'success',
        'message' => $message,
        'unread_count' => $model->getUnreadCount($id),
        'data' => ['redirect' => 'notifications.php'],
    ]);
} catch (DomainException $e) {
    http_response_code(422);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('SSIS notifications API failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'The notification action could not be completed. Please try again.']);
}
