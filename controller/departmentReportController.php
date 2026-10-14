<?php
require_once __DIR__ . '/../php/includes/auth.php';
require_once __DIR__ . '/../models/DepartmentReports.php';

ssis_start_session();
header('Content-Type: application/json; charset=utf-8');
ssis_require_role('department');

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
    $db = Database::getConnection();
    $departmentStatement = $db->prepare("SELECT department_id FROM users WHERE id = :user_id AND role = 'Department' LIMIT 1");
    $departmentStatement->execute(['user_id' => (int) $_SESSION['user']['id']]);
    $departmentId = $departmentStatement->fetchColumn();

    if ($departmentId === false || $departmentId === null) {
        throw new DomainException('Your account is not assigned to a department.');
    }

    $programId = ($_POST['program_id'] ?? '') === '' ? null : filter_var($_POST['program_id'], FILTER_VALIDATE_INT);
    $yearLevel = ($_POST['year_level'] ?? '') === '' ? null : filter_var($_POST['year_level'], FILTER_VALIDATE_INT);

    if ($programId === false || ($programId !== null && $programId < 1)) {
        throw new DomainException('Choose a valid academic program.');
    }

    if ($yearLevel === false || ($yearLevel !== null && ($yearLevel < 1 || $yearLevel > 6))) {
        throw new DomainException('Choose a valid year level.');
    }

    $model = new DepartmentReports($db);
    $programIds = array_map(static fn(array $program): int => (int) $program['id'], $model->getPrograms((int) $departmentId));

    if ($programId !== null && !in_array($programId, $programIds, true)) {
        throw new DomainException('That program is not part of your department.');
    }
    echo json_encode(['status' => 'success', 'message' => 'Department report loaded.', 'data' => $model->getClearanceReport((int) $departmentId, $programId, $yearLevel)]);
} catch (DomainException $e) {
    http_response_code(422);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('SSIS department report failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'The department report could not be loaded. Please try again.']);
}
