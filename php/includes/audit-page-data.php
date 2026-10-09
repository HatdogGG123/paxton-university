<?php
require_once __DIR__ . '/../../models/AuditLog.php';
try {
    $auditModel = new AuditLog(Database::getConnection());
    $auditEntries = $auditModel->getEntries([], 500);
    $auditSummary = $auditModel->getSummary();
} catch (Throwable $exception) {
    error_log('SSIS admin audit page failed: ' . $exception->getMessage());
    http_response_code(503);
    exit('Audit history is temporarily unavailable. Please try again later.');
}
$auditEsc = static fn ($value): string => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
$auditFilterAction = static function (string $action): string {
    $upper = strtoupper($action);
    if (str_contains($upper, 'LOGIN') || str_contains($upper, 'LOGOUT') || str_contains($upper, 'AUTH')) { return 'Login'; }
    if (str_contains($upper, 'PAYMENT')) { return 'PAYMENT'; }
    if (str_contains($upper, 'DISABLE') || str_contains($upper, 'DELETE') || str_contains($upper, 'REJECT')) { return 'DISABLE'; }
    if (str_contains($upper, 'UPDATE') || str_contains($upper, 'EDIT') || str_contains($upper, 'MODIFY')) { return 'UPDATE'; }
    return 'CREATE';
};
?>
