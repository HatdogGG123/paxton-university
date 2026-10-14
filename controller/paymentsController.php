<?php
require_once __DIR__ . '/../php/includes/auth.php';
require_once __DIR__ . '/../services/PaymentsService.php';

ssis_start_session();

header('Content-Type: application/json; charset=utf-8');

ssis_require_role('cashier');

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
    $receipt = (new PaymentsService(new Payments(Database::getConnection())))->handle((int) $_SESSION['user']['id'], $_POST);
    
    $paymentId = $receipt !== '' ? (int) ($_POST['payment_id'] ?? 0) : 0;
    
    $message = $receipt !== '' ? 'Payment processed successfully. Receipt ' . $receipt . ' generated.' : 'Payment status updated successfully.';
    
    $_SESSION['cashier_payment_notice'] = [
        'success' => true,
        'message' => $message,
        'receipt_payment_id' => $paymentId > 0 ? $paymentId : null
    ];
    
    echo json_encode(['status' => 'success', 'message' => $message, 'data' => ['redirect' => 'payments.php', 'receipt_payment_id' => $paymentId ?: null]]);
} catch (DomainException $e) {
    http_response_code(422);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('SSIS payment API failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'We could not process this payment. Please try again.']);
}
