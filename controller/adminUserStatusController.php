<?php
require_once __DIR__ . '/../php/includes/auth.php';
require_once __DIR__ . '/../models/User.php';

ssis_start_session();
header('Content-Type: application/json; charset=utf-8');
function admin_status_json(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') admin_status_json(405, ['status' => 'error', 'message' => 'This request must use POST.']);
if (empty($_SESSION['user']['id']) || strtolower((string)($_SESSION['user']['role'] ?? '')) !== 'admin') admin_status_json(403, ['status' => 'error', 'message' => 'You are not authorized to manage accounts.']);
if (empty($_SESSION['csrf_token']) || !is_string($_POST['csrf_token'] ?? null) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) admin_status_json(400, ['status' => 'error', 'message' => 'This form expired. Refresh the page and try again.']);

try {
    $db = Database::getConnection();
    $userId = filter_var($_POST['user_id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null;
    $status = trim((string) ($_POST['status'] ?? ''));
    if ($userId === null) throw new DomainException('Choose a valid account.');
    if (!in_array($status, ['Active', 'Inactive'], true)) throw new DomainException('Choose a valid account status.');
    if ($userId === 1) throw new DomainException('The main administrator account cannot be activated or deactivated.');
    if ($userId === (int) $_SESSION['user']['id']) throw new DomainException('You cannot change the status of your own account.');

    $db->beginTransaction();
    try {
        $find = $db->prepare('SELECT id, username, role, status FROM users WHERE id = :id LIMIT 1 FOR UPDATE');
        $find->execute(['id' => $userId]);
        $target = $find->fetch(PDO::FETCH_ASSOC);
        if (!$target) throw new DomainException('Account not found.');
        if ($target['status'] === $status) throw new DomainException('This account is already ' . strtolower($status) . '.');
        if ($status === 'Inactive' && $target['role'] === 'Admin') {
            $others = $db->prepare("SELECT COUNT(*) FROM users WHERE role = 'Admin' AND status = 'Active' AND id <> :id");
            $others->execute(['id' => $userId]);
            if ((int) $others->fetchColumn() === 0) throw new DomainException('At least one active administrator account must remain.');
        }
        $update = $db->prepare('UPDATE users SET status = :status WHERE id = :id');
        $update->execute(['status' => $status, 'id' => $userId]);
        $db->commit();
    } catch (Throwable $exception) {
        if ($db->inTransaction()) $db->rollBack();
        throw $exception;
    }

    try {
        $audit = $db->prepare('INSERT INTO audit_logs (user_id,action,entity,entity_id,details,ip_address) VALUES (:admin,:action,\'User Account\',:id,:details,:ip)');
        $audit->execute(['admin' => (int) $_SESSION['user']['id'], 'action' => $status === 'Active' ? 'USER_ACCOUNT_ACTIVATED' : 'USER_ACCOUNT_DEACTIVATED', 'id' => $userId, 'details' => ($status === 'Active' ? 'Activated' : 'Deactivated') . ' account ' . $target['username'] . '.', 'ip' => substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45)]);
    } catch (Throwable $auditException) {
        error_log('SSIS admin user status audit failed: ' . $auditException->getMessage());
    }

    admin_status_json(200, ['status' => 'success', 'message' => 'Account "' . $target['username'] . '" is now ' . $status . '.', 'data' => ['id' => $userId, 'username' => $target['username'], 'status' => $status]]);
} catch (DomainException $exception) {
    admin_status_json(422, ['status' => 'error', 'message' => $exception->getMessage()]);
} catch (Throwable $exception) {
    error_log('SSIS admin user status update failed: ' . $exception->getMessage());
    admin_status_json(500, ['status' => 'error', 'message' => 'The account status could not be updated. Please try again.']);
}
