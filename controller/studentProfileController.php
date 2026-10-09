<?php
require_once __DIR__ . '/../php/includes/auth.php';
require_once __DIR__ . '/../services/StudentProfileService.php';

ssis_start_session();

header('Content-Type: application/json; charset=utf-8');

ssis_require_role('student');
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
    $service = new StudentProfileService(new StudentProfile(Database::getConnection()));
    $action = $_POST['action'] ?? '';
    $result = $action === 'update_contact' ? $service->updateContact((int) $_SESSION['user']['id'], $_POST) : ($action === 'change_password' ? $service->changePassword((int) $_SESSION['user']['id'], $_POST) : ['success' => false, 'message' => 'Choose a valid profile action.']);
    if (!$result['success'])
        throw new DomainException($result['message']);
    $_SESSION['student_profile_notice'] = ['type' => 'success', 'message' => $result['message']];
    echo json_encode(['status' => 'success', 'message' => $result['message'], 'data' => ['redirect' => 'profile.php']]);
} catch (DomainException $e) {
    http_response_code(422);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('SSIS profile API failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'We could not save your changes. Please try again.']);
}
