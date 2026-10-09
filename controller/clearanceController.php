<?php
require_once __DIR__ . '/../php/includes/auth.php';
require_once __DIR__ . '/../services/ClearanceService.php';

ssis_start_session();

header('Content-Type: application/json; charset=utf-8');

$role = strtolower((string) ($_SESSION['user']['role'] ?? ''));

if (!in_array($role, ['registrar', 'cashier', 'department'], true)) {
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
    $model = new Clearance(Database::getConnection());

    $departmentId = $role === 'department' ? $model->getDepartmentId((int) $_SESSION['user']['id']) : null;

    (new ClearanceService($model))->review((int) $_SESSION['user']['id'], ucfirst($role), $departmentId, $_POST);

    $messages = ['registrar' => 'The clearance decision was saved and the student was notified.', 'cashier' => 'Cashier clearance updated.', 'department' => 'Clearance decision saved and student notified.'];
    $message = $messages[$role];

    echo json_encode(['status' => 'success', 'message' => $message, 'data' => ['redirect' => 'clearance.php']]);
} catch (DomainException $e) {
    http_response_code(422);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('SSIS clearance API failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'We could not update this clearance item. Please try again.']);
}
