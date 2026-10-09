<?php
/** Cashier payment queries and transactional payment operations. */
final class Payments
{
    public function __construct(private PDO $db)
    {
    }

    /** List payments with their document request and student details. */
    public function getQueue(): array
    {
        return $this->db->query(
            'SELECT payment.id AS payment_id, payment.amount, payment.method, payment.status AS payment_status,
                    payment.reference_number, payment.receipt_no, payment.rejection_reason, payment.submitted_at,
                    payment.verified_at, request.id AS request_id, request.request_no, request.status AS request_status,
                    request.copies, request.fee_amount, request.created_at, type.name AS document_name,
                    student.student_no, student.first_name, student.middle_name, student.last_name,
                    program.code AS program_code
             FROM payments payment
             INNER JOIN document_requests request ON request.id = payment.request_id
             INNER JOIN document_types type ON type.id = request.document_type_id
             INNER JOIN students student ON student.id = request.student_id
             INNER JOIN programs program ON program.id = student.program_id
             ORDER BY FIELD(payment.status, \'Pending Verification\', \'Unpaid\', \'Rejected\', \'Paid\', \'Refunded\'), payment.updated_at DESC'
        )->fetchAll();
    }

    /** List unpaid requests eligible for a walk-in payment. */
    public function getWalkInEligible(): array
    {
        return $this->db->query(
            "SELECT payment.id AS payment_id, payment.amount, request.request_no, request.copies,
                    type.name AS document_name, student.student_no,
                    CONCAT_WS(' ', student.first_name, student.middle_name, student.last_name) AS student_name
             FROM payments payment
             INNER JOIN document_requests request ON request.id = payment.request_id
             INNER JOIN document_types type ON type.id = request.document_type_id
             INNER JOIN students student ON student.id = request.student_id
             WHERE request.status = 'For Payment' AND payment.status IN ('Unpaid','Rejected')
             ORDER BY request.created_at"
        )->fetchAll();
    }

    /** Return payments with receipts for the transaction history. */
    public function getTransactions(): array
    {
        return $this->db->query(
            "SELECT payment.id AS payment_id, payment.receipt_no, payment.reference_number,
                    payment.method, payment.amount, payment.status AS payment_status, payment.verified_at,
                    request.request_no, request.status AS request_status,
                    CONCAT_WS(' ', student.first_name, student.middle_name, student.last_name) AS student_name,
                    student.student_no
             FROM payments payment
             INNER JOIN document_requests request ON request.id = payment.request_id
             INNER JOIN students student ON student.id = request.student_id
             WHERE payment.status IN ('Paid','Refunded')
             ORDER BY payment.verified_at DESC, payment.id DESC"
        )->fetchAll();
    }

    /** Fetch a paid receipt with the student and cashier details for printing. */
    public function getReceipt(int $paymentId): ?array
    {
        $statement = $this->db->prepare(
            "SELECT payment.id AS payment_id, payment.receipt_no, payment.reference_number,
                    payment.method, payment.amount, payment.status AS payment_status, payment.verified_at,
                    request.request_no, request.copies, request.purpose,
                    type.name AS document_name, student.student_no, student.first_name,
                    student.middle_name, student.last_name, program.name AS program_name,
                    cashier.full_name AS cashier_name, cashier.username AS cashier_username
             FROM payments payment
             INNER JOIN document_requests request ON request.id = payment.request_id
             INNER JOIN document_types type ON type.id = request.document_type_id
             INNER JOIN students student ON student.id = request.student_id
             INNER JOIN programs program ON program.id = student.program_id
             LEFT JOIN users cashier ON cashier.id = payment.verified_by
             WHERE payment.id = :payment_id AND payment.status IN ('Paid','Refunded')
               AND payment.receipt_no IS NOT NULL LIMIT 1"
        );
        $statement->execute(['payment_id' => $paymentId]);
        $receipt = $statement->fetch();
        return $receipt ?: null;
    }

    /** Calculate date-range collections grouped by payment method. */
    public function getCollectionReport(string $from, string $to): array
    {
        $statement = $this->db->prepare(
            "SELECT COALESCE(SUM(CASE WHEN status = 'Paid' THEN amount ELSE 0 END), 0) AS total,
                    COALESCE(SUM(CASE WHEN status = 'Paid' AND method = 'Cash' THEN amount ELSE 0 END), 0) AS cash,
                    COALESCE(SUM(CASE WHEN status = 'Paid' AND method <> 'Cash' THEN amount ELSE 0 END), 0) AS online,
                    COUNT(CASE WHEN status = 'Paid' THEN 1 END) AS payment_count
             FROM payments WHERE verified_at >= :from_date AND verified_at < DATE_ADD(:to_date, INTERVAL 1 DAY)"
        );
        $statement->execute(['from_date' => $from, 'to_date' => $to]);
        return $statement->fetch() ?: ['total' => 0, 'cash' => 0, 'online' => 0, 'payment_count' => 0];
    }

    /** List paid receipts included in a collection report date range. */
    public function getCollectionReportTransactions(string $from, string $to): array
    {
        $statement = $this->db->prepare(
            "SELECT payment.receipt_no, request.request_no,
                    CONCAT_WS(' ', student.first_name, student.middle_name, student.last_name) AS student_name,
                    student.student_no, payment.method, payment.reference_number,
                    payment.amount, payment.verified_at
             FROM payments payment
             INNER JOIN document_requests request ON request.id = payment.request_id
             INNER JOIN students student ON student.id = request.student_id
             WHERE payment.status = 'Paid' AND payment.verified_at >= :from_date
               AND payment.verified_at < DATE_ADD(:to_date, INTERVAL 1 DAY)
             ORDER BY payment.verified_at DESC, payment.id DESC"
        );
        $statement->execute(['from_date' => $from, 'to_date' => $to]);
        return $statement->fetchAll();
    }

    /** Verify a student-submitted payment reference and release the request to Registrar. */
    public function verify(int $paymentId, int $cashierId): string
    {
        $this->db->beginTransaction();
        try {
            $payment = $this->lockPayment($paymentId);
            if ($payment['payment_status'] !== 'Pending Verification' || $payment['request_status'] !== 'For Payment') {
                throw new DomainException('This payment is no longer awaiting verification. Refresh the page.');
            }
            if (!$payment['reference_number'] || !$payment['method']) { throw new DomainException('The payment submission is missing its reference or method.'); }
            $receiptNo = $this->receiptNumber($paymentId);
            $update = $this->db->prepare("UPDATE payments SET status = 'Paid', receipt_no = :receipt_no, verified_by = :cashier_id, verified_at = CURRENT_TIMESTAMP, rejection_reason = NULL WHERE id = :id");
            $update->execute(['receipt_no' => $receiptNo, 'cashier_id' => $cashierId, 'id' => $paymentId]);
            $this->markRequestPaid($payment, $cashierId);
            $this->notifyStudent((int) $payment['user_id'], 'Payment verified', 'Your payment for ' . $payment['request_no'] . ' was verified. Receipt ' . $receiptNo . '.', '/php/student/requests.php');
            $this->notifyRegistrars($payment['request_no'] . ' has been paid and is ready for processing.');
            $this->audit($cashierId, 'PAYMENT_VERIFIED', $paymentId, $payment['request_no'] . ' payment verified. Receipt ' . $receiptNo . '.');
            $this->db->commit();
            return $receiptNo;
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) { $this->db->rollBack(); }
            throw $exception;
        }
    }

    /** Reject a submitted reference and return the request to the student for another payment attempt. */
    public function reject(int $paymentId, int $cashierId, string $reason): void
    {
        $reason = trim($reason);
        if ($reason === '') { throw new DomainException('A reason is required when rejecting a payment.'); }
        if (strlen($reason) > 500) { throw new DomainException('The rejection reason must be 500 characters or fewer.'); }
        $this->db->beginTransaction();
        try {
            $payment = $this->lockPayment($paymentId);
            if ($payment['payment_status'] !== 'Pending Verification' || $payment['request_status'] !== 'For Payment') {
                throw new DomainException('Only a submitted payment reference can be rejected.');
            }
            $update = $this->db->prepare("UPDATE payments SET status = 'Rejected', rejection_reason = :reason, verified_by = :cashier_id, verified_at = CURRENT_TIMESTAMP WHERE id = :id");
            $update->execute(['reason' => $reason, 'cashier_id' => $cashierId, 'id' => $paymentId]);
            $this->notifyStudent((int) $payment['user_id'], 'Payment reference rejected', 'The reference for ' . $payment['request_no'] . ' was rejected. Reason: ' . $reason, '/php/student/requests.php');
            $this->audit($cashierId, 'PAYMENT_REJECTED', $paymentId, $payment['request_no'] . ' payment rejected. Reason: ' . $reason);
            $this->db->commit();
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) { $this->db->rollBack(); }
            throw $exception;
        }
    }

    /** Record and immediately verify a walk-in payment for the exact outstanding amount. */
    public function recordWalkIn(int $paymentId, int $cashierId, string $method, string $amount, string $reference): string
    {
        if (!in_array($method, ['Cash', 'GCash', 'Bank Transfer', 'Other'], true)) { throw new DomainException('Choose a valid payment method.'); }
        if (!preg_match('/^(?:0|[1-9][0-9]{0,7})(?:\.[0-9]{1,2})?$/', $amount)) { throw new DomainException('Enter a valid payment amount.'); }
        if ($method !== 'Cash' && trim($reference) === '') { throw new DomainException('Enter a reference number for this payment method.'); }
        if (strlen($reference) > 60) { throw new DomainException('The reference number must be 60 characters or fewer.'); }

        $this->db->beginTransaction();
        try {
            $payment = $this->lockPayment($paymentId);
            if (!in_array($payment['payment_status'], ['Unpaid', 'Rejected'], true) || $payment['request_status'] !== 'For Payment') {
                throw new DomainException('This request is no longer eligible for a walk-in payment.');
            }
            if (number_format((float) $amount, 2, '.', '') !== number_format((float) $payment['amount'], 2, '.', '')) {
                throw new DomainException('The payment amount must match the request total of ₱' . number_format((float) $payment['amount'], 2) . '.');
            }
            $receiptNo = $this->receiptNumber($paymentId);
            $update = $this->db->prepare("UPDATE payments SET status = 'Paid', method = :method, reference_number = :reference, receipt_no = :receipt_no, recorded_by = :recorded_by, verified_by = :verified_by, verified_at = CURRENT_TIMESTAMP, submitted_at = CURRENT_TIMESTAMP, rejection_reason = NULL WHERE id = :id");
            $update->execute([
                'method' => $method, 'reference' => trim($reference) === '' ? null : trim($reference),
                'receipt_no' => $receiptNo, 'recorded_by' => $cashierId, 'verified_by' => $cashierId, 'id' => $paymentId,
            ]);
            $this->markRequestPaid($payment, $cashierId);
            $this->notifyStudent((int) $payment['user_id'], 'Payment recorded', 'Your walk-in payment for ' . $payment['request_no'] . ' was recorded. Receipt ' . $receiptNo . '.', '/php/student/requests.php');
            $this->notifyRegistrars($payment['request_no'] . ' has been paid and is ready for processing.');
            $this->audit($cashierId, 'PAYMENT_RECORDED', $paymentId, $payment['request_no'] . ' walk-in payment of ' . number_format((float) $amount, 2) . ' recorded. Receipt ' . $receiptNo . '.');
            $this->db->commit();
            return $receiptNo;
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) { $this->db->rollBack(); }
            throw $exception;
        }
    }

    /** Refund a paid payment when its document request was cancelled or rejected. */
    public function refund(int $paymentId, int $cashierId, string $reason): void
    {
        $reason = trim($reason);
        if ($reason === '') { throw new DomainException('A refund reason is required.'); }
        if (strlen($reason) > 500) { throw new DomainException('The refund reason must be 500 characters or fewer.'); }
        $this->db->beginTransaction();
        try {
            $payment = $this->lockPayment($paymentId);
            if ($payment['payment_status'] !== 'Paid' || !in_array($payment['request_status'], ['Cancelled', 'Rejected'], true)) {
                throw new DomainException('A payment can only be refunded when its request is cancelled or rejected.');
            }
            $update = $this->db->prepare("UPDATE payments SET status = 'Refunded', refund_reason = :reason, refunded_by = :cashier_id, refunded_at = CURRENT_TIMESTAMP WHERE id = :id");
            $update->execute(['reason' => $reason, 'cashier_id' => $cashierId, 'id' => $paymentId]);
            $this->notifyStudent((int) $payment['user_id'], 'Payment refunded', 'Payment for ' . $payment['request_no'] . ' was refunded. Reason: ' . $reason, '/php/student/requests.php');
            $this->audit($cashierId, 'PAYMENT_REFUNDED', $paymentId, $payment['request_no'] . ' payment refunded. Reason: ' . $reason);
            $this->db->commit();
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) { $this->db->rollBack(); }
            throw $exception;
        }
    }

    /** Lock a payment and its parent request before changing payment status. */
    private function lockPayment(int $paymentId): array
    {
        $statement = $this->db->prepare(
            'SELECT payment.id, payment.amount, payment.status AS payment_status, payment.reference_number,
                    payment.method, request.id AS request_id, request.request_no, request.status AS request_status,
                    request.fee_amount, request.copies, student.user_id
             FROM payments payment
             INNER JOIN document_requests request ON request.id = payment.request_id
             INNER JOIN students student ON student.id = request.student_id
             WHERE payment.id = :id LIMIT 1 FOR UPDATE'
        );
        $statement->execute(['id' => $paymentId]);
        $payment = $statement->fetch();
        if (!$payment) { throw new DomainException('Payment record not found.'); }
        return $payment;
    }

    /** Update the linked request after payment becomes paid. */
    private function markRequestPaid(array $payment, int $cashierId): void
    {
        $update = $this->db->prepare("UPDATE document_requests SET status = 'Paid', processed_by = :cashier_id WHERE id = :id AND status = 'For Payment'");
        $update->execute(['cashier_id' => $cashierId, 'id' => $payment['request_id']]);
        if ($update->rowCount() !== 1) { throw new DomainException('The document request is no longer waiting for payment.'); }
    }

    /** Build the system receipt number for a payment. */
    private function receiptNumber(int $paymentId): string
    {
        $receipt = 'OR-' . date('Y') . '-' . str_pad((string) $paymentId, 6, '0', STR_PAD_LEFT);
        if (strlen($receipt) > 15) { throw new DomainException('The receipt number limit has been reached.'); }
        return $receipt;
    }

    private function notifyStudent(int $userId, string $title, string $message, string $link): void
    {
        $statement = $this->db->prepare("INSERT INTO notifications (user_id, category, title, message, link) VALUES (:user_id, 'Payments', :title, :message, :link)");
        $statement->execute(['user_id' => $userId, 'title' => $title, 'message' => $message, 'link' => $link]);
    }

    private function notifyRegistrars(string $message): void
    {
        $statement = $this->db->prepare("INSERT INTO notifications (user_id, category, title, message, link) SELECT id, 'Requests', 'Paid document request ready', :message, '/php/registrar/documentRequest.php' FROM users WHERE role = 'Registrar' AND status = 'Active'");
        $statement->execute(['message' => $message]);
    }

    private function audit(int $userId, string $action, int $paymentId, string $details): void
    {
        $statement = $this->db->prepare("INSERT INTO audit_logs (user_id, action, entity, entity_id, details, ip_address) VALUES (:user_id, :action, 'payments', :entity_id, :details, :ip_address)");
        $statement->execute([
            'user_id' => $userId, 'action' => $action, 'entity_id' => $paymentId, 'details' => $details,
            'ip_address' => substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45),
        ]);
    }
}
