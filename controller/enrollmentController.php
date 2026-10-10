<?php
require_once __DIR__ . '/../php/includes/auth.php';
require_once __DIR__ . '/../services/EnrollmentService.php';
ssis_start_session();
header('Content-Type: application/json; charset=utf-8');

$role = strtolower((string) ($_SESSION['user']['role'] ?? ''));
if ($role === 'student')
    ssis_require_role('student');
elseif ($role === 'registrar')
    ssis_require_role('registrar');
else {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'You do not have permission to update enrollment.']);
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
    $service = new EnrollmentService(new Enrollment(Database::getConnection()));
    $userId = (int) $_SESSION['user']['id'];
    if ($role === 'student') {
        $action = (string) ($_POST['action'] ?? '');
        if ($action === 'submit_request')
            $message = 'Enrollment request ' . $service->submitStudentRequest($userId, $_POST) . ' was submitted.';
        elseif ($action === 'cancel_request') {
            $service->cancelStudentRequest($userId, $_POST['enrollment_id'] ?? null);
            $message = 'Your pending enrollment request was cancelled.';
        } else
            throw new DomainException('Choose a valid enrollment action.');
        $_SESSION['enrollment_notice'] = ['type' => 'success', 'message' => $message];
        echo json_encode(['status' => 'success', 'message' => $message, 'data' => ['redirect' => 'enrollment.php']]);
        exit;
    }

    $action = (string) ($_POST['action'] ?? 'review');
    $result = null;
    if ($action === 'approve_and_update') {
        $service->updateReviewedSubjects($userId, $_POST);
        $result = $service->reviewRequest($userId, $_POST + ['new_status' => 'Approved']);
        $message = 'The subject load was saved and the enrollment request was approved.';
    } elseif ($action === 'update_subjects') {
        $service->updateReviewedSubjects($userId, $_POST);
        $message = 'The subject list was updated and the student was notified.';
    } else {
        $result = $service->reviewRequest($userId, $_POST);
        $message = $result['message'];
    }
    if ($result && $result['status'] === 'Cancelled') $message = $result['message'];
    $_SESSION['registrar_enrollment_notice'] = ['type' => 'success', 'message' => $message];
    echo json_encode(['status' => 'success', 'message' => $message, 'data' => ['redirect' => 'enrollment.php', 'status' => $result['status'] ?? null]]);
} catch (DomainException $exception) {
    http_response_code(422);
    echo json_encode(['status' => 'error', 'message' => $exception->getMessage()]);
} catch (Throwable $exception) {
    error_log('SSIS enrollment API failed: ' . $exception->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'We could not update this enrollment request. Please try again.']);
}
