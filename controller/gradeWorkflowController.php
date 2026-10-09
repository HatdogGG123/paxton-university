<?php
require_once __DIR__ . '/../php/includes/auth.php';
require_once __DIR__ . '/../models/Grades.php';
ssis_start_session();
header('Content-Type: application/json; charset=utf-8');

$fail = static function (int $status, string $message): void {
    http_response_code($status);
    echo json_encode(['status' => 'error', 'message' => $message]);
    exit;
};
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET' && ($_GET['action'] ?? '') === 'department_grade_roster') {
    ssis_require_role('department');
    try {
        $termId = filter_var($_GET['term_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $sectionId = filter_var($_GET['section_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $subjectId = filter_var($_GET['subject_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (!$termId || !$sectionId || !$subjectId) throw new DomainException('Choose a valid section and subject.');
        $grades = new Grades(Database::getConnection());
        $term = $grades->currentTerm();
        if (!$term || (int) $term['id'] !== (int) $termId) throw new DomainException('The selected academic term is no longer active.');
        echo json_encode(['status' => 'success', 'students' => $grades->getDepartmentGradeRoster((int) $_SESSION['user']['department_id'], (int) $termId, (int) $sectionId, (int) $subjectId)]);
        exit;
    } catch (DomainException $exception) {
        $fail(422, $exception->getMessage());
    } catch (Throwable $exception) {
        error_log('SSIS department grade roster failed: ' . $exception->getMessage());
        $fail(500, 'The student roster could not be loaded.');
    }
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') $fail(405, 'POST is required.');
$token = $_POST['csrf_token'] ?? '';
if (!is_string($token) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) $fail(400, 'This form expired. Refresh and try again.');

try {
    $grades = new Grades(Database::getConnection());
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'department_grade_save') {
        ssis_require_role('department');
        $studentId = filter_var($_POST['student_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $termId = filter_var($_POST['term_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $sectionId = filter_var($_POST['section_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $subjectId = filter_var($_POST['subject_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $grade = $_POST['grade'] ?? null;
        if (!$studentId || !$termId || !$sectionId || !$subjectId) throw new DomainException('Select a valid section, subject, and student.');
        if (!is_string($grade) && !is_int($grade) && !is_float($grade)) throw new DomainException('Enter a grade from 1.00 to 5.00.');
        $grades->saveDepartmentGrade((int) $_SESSION['user']['department_id'], (int) $_SESSION['user']['id'], (int) $studentId, (int) $termId, (int) $sectionId, (int) $subjectId, $grade);
        echo json_encode(['status' => 'success', 'message' => 'Grade saved and sent to the Registrar for approval.', 'workflow_status' => 'Registrar Review']);
        exit;
    }
    if ($action === 'registrar_grade_review') {
        ssis_require_role('registrar');
        $gradeId = filter_var($_POST['grade_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (!$gradeId) throw new DomainException('Choose a valid grade.');
        $decision = (string) ($_POST['decision'] ?? '');
        $grades->reviewRegistrarGrade((int) $gradeId, (int) $_SESSION['user']['id'], $decision, trim((string) ($_POST['message'] ?? '')));
        echo json_encode(['status' => 'success', 'message' => $decision === 'release' ? 'Grade approved and released to the student.' : 'Grade returned to the department with your reason.']);
        exit;
    }
    $fail(422, 'Choose a valid grade action.');
} catch (DomainException $exception) {
    $fail(422, $exception->getMessage());
} catch (Throwable $exception) {
    error_log('SSIS grade workflow action failed: ' . $exception->getMessage());
    $fail(500, 'The grade action could not be completed.');
}
