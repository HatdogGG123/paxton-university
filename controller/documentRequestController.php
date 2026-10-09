<?php
require_once __DIR__ . '/../php/includes/auth.php';
require_once __DIR__ . '/../services/DocumentRequestsService.php';
ssis_start_session();
header('Content-Type: application/json; charset=utf-8');
$role = strtolower((string) ($_SESSION['user']['role'] ?? ''));
if ($role === 'student')
    ssis_require_role('student');
elseif ($role === 'registrar')
    ssis_require_role('registrar');
else {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Access denied.']);
    exit;
}
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
    $service = new DocumentRequestsService(new DocumentRequests(Database::getConnection()));
    $userId = (int) $_SESSION['user']['id'];
    if ($role === 'student') {
        $number = $service->submitStudentRequest($userId, $_POST);
        $message = 'Request ' . $number . ' was submitted.';
        $redirect = 'requests.php';
    } else {
        if (($_POST['request_action'] ?? '') === 'reject')
            $service->reject($userId, $_POST);
        elseif (($_POST['request_action'] ?? '') === 'transition')
            $service->updateStatus($userId, $_POST);
        else
            throw new DomainException('Choose a valid request action.');
        $message = 'Document request updated.';
        $redirect = 'documentRequest.php';
    }
    $data = ['redirect' => $redirect];
    if ($role === 'student') $data['request_no'] = $number;
    echo json_encode(['status' => 'success', 'message' => $message, 'data' => $data]);
} catch (DomainException $e) {
    http_response_code(422);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('SSIS document request API failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'The document request could not be processed. Please try again.']);
}
