<?php
require_once __DIR__ . '/../php/includes/auth.php';
require_once __DIR__ . '/../models/RegistrarCommunications.php';
require_once __DIR__ . '/../models/Notifications.php';
ssis_start_session();
header('Content-Type: application/json; charset=utf-8');
ssis_require_role('registrar');
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
    $model = new RegistrarCommunications(Database::getConnection());
    $action = (string) ($_POST['communication_action'] ?? '');
    $userId = (int) $_SESSION['user']['id'];
    $data = [];
    if ($action === 'publish') {
        $data = ['announcement_id' => $model->publishAnnouncement($userId, (string) ($_POST['title'] ?? ''), (string) ($_POST['body'] ?? ''), (string) ($_POST['audience'] ?? '')), 'title' => trim((string) ($_POST['title'] ?? '')), 'body' => trim((string) ($_POST['body'] ?? '')), 'audience' => (string) ($_POST['audience'] ?? ''), 'created_at' => date('Y-m-d H:i:s')];
        $message = 'Announcement published.';
    } elseif ($action === 'send_message') {
        $data = $model->sendDirectMessage($userId, (string) ($_POST['student_no'] ?? ''), (string) ($_POST['category'] ?? ''), (string) ($_POST['title'] ?? ''), (string) ($_POST['body'] ?? '')) + ['category' => (string) ($_POST['category'] ?? ''), 'title' => trim((string) ($_POST['title'] ?? '')), 'body' => trim((string) ($_POST['body'] ?? '')), 'created_at' => date('Y-m-d H:i:s')];
        $message = 'Direct student message sent.';
    } elseif ($action === 'deactivate') {
        $model->deactivateAnnouncement($userId, (int) ($_POST['announcement_id'] ?? 0));
        $message = 'Announcement deactivated.';
    } else
        throw new DomainException('Choose a valid announcement action.');
    if ($action === 'deactivate') $data = ['announcement_id' => (int) ($_POST['announcement_id'] ?? 0)];
    if ($action === 'publish' && (string) ($_POST['audience'] ?? '') === 'All') {
        $data['unread_count'] = (new Notifications(Database::getConnection()))->getUnreadCount($userId);
    }
    echo json_encode(['status' => 'success', 'message' => $message, 'data' => $data]);
} catch (DomainException $e) {
    http_response_code(422);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('SSIS communications API failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'The action could not be completed. Please try again.']);
}
