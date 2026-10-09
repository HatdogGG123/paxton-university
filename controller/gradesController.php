<?php
require_once __DIR__ . '/../php/includes/auth.php';
ssis_start_session();
ssis_require_role('registrar');
http_response_code(410);
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['status' => 'error', 'message' => 'Direct grade entry is disabled. Use the Department grade entry and Registrar approval workflow.']);
