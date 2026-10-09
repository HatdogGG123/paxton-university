<?php
require_once __DIR__ . '/../../models/Notifications.php';

$notificationUserId = (int) ($_SESSION['user']['id'] ?? 0);
$notificationModel = new Notifications(Database::getConnection());
if (strtolower((string) ($_SESSION['user']['role'] ?? '')) === 'cashier') {
    $notificationModel->syncCashierPaymentQueue($notificationUserId);
}
$notificationDisplayName = trim((string) ($_SESSION['user']['full_name'] ?? ''));
$notificationIdentityLine = (string) ($_SESSION['user']['role'] ?? 'User');
if (strtolower($notificationIdentityLine) === 'student') {
    $studentIdentity = Database::getConnection()->prepare(
        'SELECT student_no, CONCAT_WS(\' \', first_name, middle_name, last_name) AS full_name
         FROM students WHERE user_id = :user_id LIMIT 1'
    );
    $studentIdentity->execute(['user_id' => $notificationUserId]);
    $studentIdentity = $studentIdentity->fetch();
    if ($studentIdentity) {
        $notificationDisplayName = (string) $studentIdentity['full_name'];
        $notificationIdentityLine = (string) $studentIdentity['student_no'];
    }
}
if ($notificationDisplayName === '') {
    $notificationDisplayName = (string) ($_SESSION['user']['username'] ?? 'User');
}
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(400);
        exit('This form expired. Refresh the page and try again.');
    }
    $action = $_POST['notification_action'] ?? '';
    if ($action === 'read' && filter_var($_POST['notification_id'] ?? null, FILTER_VALIDATE_INT)) {
        $notificationModel->markRead($notificationUserId, (int) $_POST['notification_id']);
    } elseif ($action === 'read_all') {
        $notificationModel->markAllRead($notificationUserId);
    }
    header('Location: notifications.php');
    exit;
}
$notifications = $notificationModel->getForUser($notificationUserId);
$notificationUnreadCount = $notificationModel->getUnreadCount($notificationUserId);
$notificationEsc = static fn ($value): string => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
?>
