<?php
require_once __DIR__ . '/../php/includes/auth.php';
require_once __DIR__ . '/../models/Admission.php';

ssis_start_session();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$role = strtolower((string) ($_SESSION['user']['role'] ?? ''));
if (empty($_SESSION['user']['id']) || !in_array($role, ['admin', 'registrar'], true)) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'You are not authorized to view applicant counts.']);
    exit;
}

try {
    $count = (new Admission(Database::getConnection()))->newApplicantCount();
    echo json_encode(['status' => 'success', 'count' => $count]);
} catch (Throwable $exception) {
    error_log('SSIS admission count refresh failed: ' . $exception->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Applicant count is temporarily unavailable.']);
}
