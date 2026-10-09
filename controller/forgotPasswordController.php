<?php
require_once __DIR__ . '/../php/includes/auth.php';
require_once __DIR__ . '/../models/AuthAttemptLimiter.php';
ssis_start_session();
header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'POST is required.']);
    exit;
}
$token = $_POST['csrf_token'] ?? '';
if (!is_string($token) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'This form expired. Refresh and try again.']);
    exit;
}

$db = Database::getConnection();
$limiter = new AuthAttemptLimiter($db);
$action = (string) ($_POST['action'] ?? '');

try {
    if ($action === 'check_email') {
        $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
        $identity = $email !== '' ? $email : '(empty email)';
        $state = $limiter->status($identity, 'Password Reset');
        if ($state['locked']) {
            http_response_code(429);
            echo json_encode(['status' => 'error', 'message' => AuthAttemptLimiter::message('Password reset', $state)]);
            exit;
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $state = $limiter->fail($identity, 'Password Reset');
            http_response_code(!empty($state['locked']) ? 429 : 422);
            echo json_encode(['status' => 'error', 'message' => 'Enter a valid email address. ' . AuthAttemptLimiter::message('Password reset', $state)]);
            exit;
        }
        $account = $db->prepare("SELECT id, email FROM users WHERE email = :email AND status = 'Active' LIMIT 1");
        $account->execute(['email' => $email]);
        $user = $account->fetch();
        if (!$user) {
            $state = $limiter->fail($email, 'Password Reset');
            http_response_code(!empty($state['locked']) ? 429 : 404);
            echo json_encode(['status' => 'error', 'message' => 'No active account uses that email. ' . AuthAttemptLimiter::message('Password reset', $state)]);
            exit;
        }
        $_SESSION['password_reset_user_id'] = (int) $user['id'];
        $_SESSION['password_reset_email'] = $email;
        echo json_encode(['status' => 'success', 'message' => 'Email found. Enter your new password.', 'data' => ['attempts_remaining' => (int) $state['remaining']]]);
        exit;
    }

    if ($action === 'reset_password') {
        $userId = (int) ($_SESSION['password_reset_user_id'] ?? 0);
        $email = (string) ($_SESSION['password_reset_email'] ?? '');
        if ($userId < 1 || $email === '') {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Verify your email before choosing a new password.']);
            exit;
        }
        $state = $limiter->status($email, 'Password Reset');
        if ($state['locked']) {
            http_response_code(429);
            echo json_encode(['status' => 'error', 'message' => AuthAttemptLimiter::message('Password reset', $state)]);
            exit;
        }
        $password = (string) ($_POST['new_password'] ?? '');
        $confirmation = (string) ($_POST['confirm_password'] ?? '');
        $failure = '';
        if (strlen($password) < 8 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
            $failure = 'Use at least 8 characters, including a letter and a number.';
        } elseif (!hash_equals($password, $confirmation)) {
            $failure = 'The password confirmation does not match.';
        }
        if ($failure !== '') {
            $state = $limiter->fail($email, 'Password Reset');
            http_response_code(!empty($state['locked']) ? 429 : 422);
            echo json_encode(['status' => 'error', 'message' => $failure . ' ' . AuthAttemptLimiter::message('Password reset', $state)]);
            exit;
        }

        $update = $db->prepare("UPDATE users SET password_hash = :password_hash, password_changed_at = NOW(), must_change_password = 0 WHERE id = :id AND email = :email AND status = 'Active'");
        $update->execute(['password_hash' => password_hash($password, PASSWORD_DEFAULT), 'id' => $userId, 'email' => $email]);
        if ($update->rowCount() !== 1) throw new DomainException('The account could not be updated. Restart the reset process.');
        $limiter->clear($email, 'Password Reset');
        try {
            $audit = $db->prepare("INSERT INTO audit_logs (user_id, action, entity, details, ip_address) VALUES (:user_id, 'PASSWORD_RESET', 'Authentication', 'Password changed through the email reset form.', :ip_address)");
            $audit->execute(['user_id' => $userId, 'ip_address' => substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45)]);
        } catch (Throwable $auditException) {
            error_log('SSIS password reset audit failed: ' . $auditException->getMessage());
        }
        unset($_SESSION['password_reset_user_id'], $_SESSION['password_reset_email']);
        echo json_encode(['status' => 'success', 'message' => 'Your password has been changed. You can now sign in.']);
        exit;
    }

    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Choose a valid password reset action.']);
} catch (DomainException $exception) {
    http_response_code(422);
    echo json_encode(['status' => 'error', 'message' => $exception->getMessage()]);
} catch (Throwable $exception) {
    error_log('SSIS password reset failed: ' . $exception->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Password reset is temporarily unavailable. Please try again.']);
}
