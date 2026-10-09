<?php
session_start();

require_once __DIR__ . '/../models/Database.php';

$host = strtolower((string) parse_url('http://' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST));
$remoteAddress = $_SERVER['REMOTE_ADDR'] ?? '';
$isLocalRequest = in_array($host, ['localhost', '127.0.0.1', '::1'], true)
    && in_array($remoteAddress, ['127.0.0.1', '::1'], true);

if (!$isLocalRequest) {
    http_response_code(404);
    exit('Not found');
}

if (empty($_SESSION['demo_accounts_csrf'])) {
    $_SESSION['demo_accounts_csrf'] = bin2hex(random_bytes(32));
}

$accounts = [
    ['username' => 'demo.admin@ssis.local', 'role' => 'Admin', 'name' => 'Demo Administrator'],
    ['username' => 'demo.registrar@ssis.local', 'role' => 'Registrar', 'name' => 'Demo Registrar'],
    ['username' => 'demo.cashier@ssis.local', 'role' => 'Cashier', 'name' => 'Demo Cashier'],
    ['username' => 'demo.department@ssis.local', 'role' => 'Department', 'name' => 'Demo Department Staff'],
    ['username' => 'demo.student@ssis.local', 'role' => 'Student', 'name' => 'Demo Student'],
];
$password = 'SSIS-Demo-2026!';
$message = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals($_SESSION['demo_accounts_csrf'], $token)) {
        http_response_code(400);
        $error = 'The form expired. Refresh this page and try again.';
    } else {
        try {
            $db = Database::getConnection();
            $db->beginTransaction();

            $departmentId = $db->query("SELECT id FROM departments WHERE code = 'CCS' LIMIT 1")->fetchColumn();
            $programId = $db->query("SELECT id FROM programs WHERE code = 'BSCS' LIMIT 1")->fetchColumn();
            if (!$departmentId || !$programId) {
                throw new RuntimeException('Required CCS department or BSCS program is missing. Import ssis-schema.sql and ssis-seed.sql first.');
            }

            $saveUser = $db->prepare(
                'INSERT INTO users (username, password_hash, role, department_id, full_name, status, must_change_password)
                 VALUES (:username, :password_hash, :role, :department_id, :full_name, \'Active\', 0)
                 ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash), role = VALUES(role),
                     department_id = VALUES(department_id), full_name = VALUES(full_name),
                     status = \'Active\', must_change_password = 0'
            );
            $findUser = $db->prepare('SELECT id FROM users WHERE username = :username');

            foreach ($accounts as $account) {
                $saveUser->execute([
                    'username' => $account['username'],
                    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                    'role' => $account['role'],
                    'department_id' => $account['role'] === 'Department' ? $departmentId : null,
                    'full_name' => $account['name'],
                ]);
                $findUser->execute(['username' => $account['username']]);
                $userId = $findUser->fetchColumn();

                if ($account['role'] === 'Student') {
                    $saveStudent = $db->prepare(
                        'INSERT INTO students (user_id, student_no, first_name, last_name, email, program_id, year_level, student_type)
                         VALUES (:user_id, \'DEMO00001\', \'Demo\', \'Student\', :email, :program_id, 1, \'Regular\')
                         ON DUPLICATE KEY UPDATE student_no = VALUES(student_no), first_name = VALUES(first_name),
                             last_name = VALUES(last_name), email = VALUES(email), program_id = VALUES(program_id), year_level = 1'
                    );
                    $saveStudent->execute([
                        'user_id' => $userId,
                        'email' => $account['username'],
                        'program_id' => $programId,
                    ]);
                }
            }

            $db->commit();
            $message = 'Demo accounts are ready. The same password works for every role.';
        } catch (Throwable $exception) {
            if (isset($db) && $db->inTransaction()) {
                $db->rollBack();
            }
            $error = $exception->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Create SSIS Demo Accounts</title>
    <style>
        body { font: 16px/1.5 system-ui, sans-serif; max-width: 760px; margin: 48px auto; padding: 0 20px; color: #202020; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        th { background: #f4f4f4; }
        code { background: #f4f4f4; padding: 3px 6px; }
        button { padding: 10px 16px; cursor: pointer; }
        .success { color: #176b35; }
        .error { color: #a32020; }
    </style>
</head>
<body>
    <h1>Create SSIS demo accounts</h1>
    <p>This local setup page creates one active account for each role. You can run it again to reset these demo accounts to the password shown below.</p>

    <?php if ($message !== null): ?>
        <p class="success" role="status"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>
    <?php if ($error !== null): ?>
        <p class="error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>

    <p><strong>Password for every account:</strong> <code><?= htmlspecialchars($password, ENT_QUOTES, 'UTF-8') ?></code></p>
    <table>
        <thead><tr><th>Role</th><th>Email / login username</th></tr></thead>
        <tbody>
        <?php foreach ($accounts as $account): ?>
            <tr>
                <td><?= htmlspecialchars($account['role'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($account['username'], ENT_QUOTES, 'UTF-8') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['demo_accounts_csrf'], ENT_QUOTES, 'UTF-8') ?>">
        <button type="submit">Create or reset demo accounts</button>
    </form>
</body>
</html>
