<?php
require_once __DIR__ . '/../php/includes/auth.php';
require_once __DIR__ . '/../models/AuthAttemptLimiter.php';
require_once __DIR__ . '/../services/EmailJsMailer.php';
ssis_start_session();
header('Content-Type: application/json; charset=utf-8');

function password_reset_reply(int $status, array $body): never
{
    http_response_code($status);
    echo json_encode($body);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    password_reset_reply(405, ['status' => 'error', 'message' => 'POST is required.']);
}

$csrf = $_POST['csrf_token'] ?? '';
if (!is_string($csrf) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
    password_reset_reply(400, ['status' => 'error', 'message' => 'This form expired. Refresh and try again.']);
}

$db = Database::getConnection();
$limiter = new AuthAttemptLimiter($db);
$action = (string) ($_POST['action'] ?? '');
$ipAddress = substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
$ipIdentity = 'reset-ip:' . $ipAddress;

try {
    if ($action === 'request_reset') {
        $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            password_reset_reply(422, ['status' => 'error', 'message' => 'Enter a valid email address.']);
        }

        $emailState = $limiter->status($email, 'Password Reset');
        $ipState = $limiter->status($ipIdentity, 'Password Reset');

        if ($emailState['locked'] || $ipState['locked']) {
            password_reset_reply(429, ['status' => 'error', 'message' => AuthAttemptLimiter::message('Password reset', $emailState['locked'] ? $emailState : $ipState)]);
        }

        $limiter->fail($email, 'Password Reset');
        $limiter->fail($ipIdentity, 'Password Reset');

        $db->beginTransaction();
        $query = $db->prepare("SELECT id, email FROM users WHERE email = :email AND status = 'Active' LIMIT 1 FOR UPDATE");
        $query->execute(['email' => $email]);

        $user = $query->fetch();
        $rawToken = null;

        if ($user) {
            $monthlyCount = $db->prepare("SELECT COUNT(*) FROM password_resets WHERE user_id = :user_id AND created_at >= DATE_FORMAT(CURRENT_DATE(), '%Y-%m-01')");
            $monthlyCount->execute(['user_id' => $user['id']]);

            if ((int) $monthlyCount->fetchColumn() < 2) {
                $rawToken = bin2hex(random_bytes(32));
                $tokenHash = hash('sha256', $rawToken);

                $db->prepare('UPDATE password_resets SET used_at = NOW() WHERE user_id = :user_id AND used_at IS NULL')
                    ->execute(['user_id' => $user['id']]);
                $insert = $db->prepare(
                    'INSERT INTO password_resets (user_id, token_hash, ip_address, expires_at)
                     VALUES (:user_id, :token_hash, :ip_address, DATE_ADD(NOW(), INTERVAL 30 MINUTE))'
                );
                $insert->execute(['user_id' => $user['id'], 'token_hash' => $tokenHash, 'ip_address' => $ipAddress]);
            }
        }

        $db->commit();

        if ($user && $rawToken !== null) {
            try {
                $mailer = new EmailJsMailer();
                $appUrl = $mailer->appUrl();

                if (!preg_match('/\Ahttps?:\/\//i', $appUrl)) {
                    throw new RuntimeException('SSIS_APP_URL must be an absolute HTTP or HTTPS URL.');
                }

                $resetUrl = rtrim($appUrl, '/') . '/forgotPassword.php?token=' . rawurlencode($rawToken);

                $mailer->send((string) $user['email'], $resetUrl);
            } catch (Throwable $mailException) {
                $db->prepare('UPDATE password_resets SET used_at = NOW() WHERE token_hash = :token_hash')
                    ->execute(['token_hash' => hash('sha256', $rawToken)]);

                error_log('SSIS EmailJS delivery failed: ' . $mailException->getMessage());
            }
        }

        password_reset_reply(200, ['status' => 'success', 'message' => 'Request received. This confirmation does not tell you whether an email was sent. For security, reset links are limited to 2 per account each calendar month; additional requests will not send another link.']);
    }

    if ($action === 'reset_password') {
        $rawToken = (string) ($_POST['token'] ?? '');

        if (!preg_match('/\A[a-f0-9]{64}\z/', $rawToken)) {
            password_reset_reply(400, ['status' => 'error', 'message' => 'This reset link is invalid or has expired. Request a new link.']);
        }

        $password = (string) ($_POST['new_password'] ?? '');
        $confirmation = (string) ($_POST['confirm_password'] ?? '');

        if (strlen($password) < 8 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
            password_reset_reply(422, ['status' => 'error', 'message' => 'Use at least 8 characters, including a letter and a number.']);
        }

        if (!hash_equals($password, $confirmation)) {
            password_reset_reply(422, ['status' => 'error', 'message' => 'The password confirmation does not match.']);
        }

        $db->beginTransaction();
        $select = $db->prepare(
            "SELECT pr.id, pr.user_id, u.email FROM password_resets pr
             JOIN users u ON u.id = pr.user_id
             WHERE pr.token_hash = :token_hash AND pr.ip_address = :ip_address
               AND pr.used_at IS NULL AND pr.expires_at > NOW() AND u.status = 'Active'
             FOR UPDATE"
        );

        $select->execute(['token_hash' => hash('sha256', $rawToken), 'ip_address' => $ipAddress]);
        $reset = $select->fetch();

        if (!$reset) {
            $db->rollBack();
            password_reset_reply(400, ['status' => 'error', 'message' => 'This reset link is invalid or has expired. Request a new link.']);
        }

        $update = $db->prepare("UPDATE users SET password_hash = :password_hash, password_changed_at = NOW(), must_change_password = 0 WHERE id = :id AND status = 'Active'");
        $update->execute(['password_hash' => password_hash($password, PASSWORD_DEFAULT), 'id' => $reset['user_id']]);

        $db->prepare('UPDATE password_resets SET used_at = NOW() WHERE user_id = :user_id AND used_at IS NULL')
            ->execute(['user_id' => $reset['user_id']]);
        $db->commit();

        $limiter->clear((string) $reset['email'], 'Password Reset');
        $limiter->clear($ipIdentity, 'Password Reset');

        try {
            $audit = $db->prepare("INSERT INTO audit_logs (user_id, action, entity, details, ip_address) VALUES (:user_id, 'PASSWORD_RESET', 'Authentication', 'Password changed using a verified single-use email link.', :ip_address)");
            $audit->execute(['user_id' => $reset['user_id'], 'ip_address' => $ipAddress]);
        } catch (Throwable $auditException) {
            error_log('SSIS password reset audit failed: ' . $auditException->getMessage());
        }

        password_reset_reply(200, ['status' => 'success', 'message' => 'Your password has been changed. You can now sign in.']);
    }

    password_reset_reply(400, ['status' => 'error', 'message' => 'Choose a valid password reset action.']);
} catch (Throwable $exception) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }

    error_log('SSIS password reset failed: ' . $exception->getMessage());
    password_reset_reply(500, ['status' => 'error', 'message' => 'Password reset is temporarily unavailable. Please try again.']);
}
