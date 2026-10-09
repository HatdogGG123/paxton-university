<?php
require_once __DIR__ . '/../php/includes/auth.php';
require_once __DIR__ . '/../models/User.php';

ssis_start_session();
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

$userId = filter_var($_POST['user_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
try {
    if (!$userId || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 120) throw new DomainException('Choose an account and enter a valid email address.');
    (new User(Database::getConnection()))->updateAccountEmail((int) $userId, $email);
    $_SESSION['account_email_notice'] = ['type' => 'success', 'message' => 'Account email saved. This address can now be used for Login and Forgot Password.'];
} catch (DomainException $exception) {
    $_SESSION['account_email_notice'] = ['type' => 'error', 'message' => $exception->getMessage()];
} catch (Throwable $exception) {
    error_log('SSIS account email update failed: ' . $exception->getMessage());
    $_SESSION['account_email_notice'] = ['type' => 'error', 'message' => 'The account email could not be updated.'];
}
header('Location: ' . ssis_app_base_path() . '/php/admin/userManagement.php#account-email');
