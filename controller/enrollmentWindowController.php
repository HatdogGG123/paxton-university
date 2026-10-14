<?php
require_once __DIR__ . '/../php/includes/auth.php';

ssis_require_role('admin');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    exit('POST is required.');
}

$token = $_POST['csrf_token'] ?? '';
if (!is_string($token) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
    http_response_code(400);
    exit('This form expired. Refresh and try again.');
}

$open = DateTime::createFromFormat('Y-m-d\\TH:i', (string) ($_POST['enrollment_open_at'] ?? ''));
$close = DateTime::createFromFormat('Y-m-d\\TH:i', (string) ($_POST['enrollment_close_at'] ?? ''));

if (!$open || !$close || $close <= $open) {
    $_SESSION['settings_notice'] = ['type' => 'error', 'message' => 'Enter a valid enrollment date range.'];
    header('Location: ' . ssis_app_base_path() . '/php/admin/systemSettings.php');
    exit;
}

$statement = Database::getConnection()->prepare('UPDATE terms SET enrollment_open_at = :opens, enrollment_close_at = :closes WHERE is_current = 1');
$statement->execute(['opens' => $open->format('Y-m-d H:i:s'), 'closes' => $close->format('Y-m-d H:i:s')]);

$_SESSION['settings_notice'] = $statement->rowCount()
    ? ['type' => 'success', 'message' => 'Enrollment period saved.']
    : ['type' => 'warning', 'message' => 'No current term was found or the dates were unchanged.'];

header('Location: ' . ssis_app_base_path() . '/php/admin/systemSettings.php');
