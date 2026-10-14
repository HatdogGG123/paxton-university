<?php
final class DocumentRequests
{
    public function __construct(private PDO $db) {}

    public function getActiveTypes(): array
    {
        return $this->db->query('SELECT id, code, name, description, fee FROM document_types WHERE is_active = 1 ORDER BY name')->fetchAll();
    }

    public function getStudentRequests(int $userId): array
    {
        $statement = $this->db->prepare(
            'SELECT request.id, request.request_no, request.purpose, request.copies, request.remarks,
                    request.fee_amount, request.status, request.rejection_reason, request.created_at,
                    type.name AS document_name, type.code AS document_code,
                    payment.status AS payment_status, payment.reference_number, payment.receipt_no
             FROM document_requests request
             INNER JOIN students student ON student.id = request.student_id AND student.user_id = :user_id
             INNER JOIN document_types type ON type.id = request.document_type_id
             LEFT JOIN payments payment ON payment.request_id = request.id
             ORDER BY request.created_at DESC, request.id DESC'
        );
        $statement->execute(['user_id' => $userId]);
        return $statement->fetchAll();
    }

    public function create(int $userId, int $typeId, string $purpose, int $copies, string $remarks): string
    {
        $allowedPurposes = ['Employment', 'Further Studies / Transfer', 'Scholarship Application', 'Visa / Travel Requirements', 'Other'];

        if (!in_array($purpose, $allowedPurposes, true)) {
            throw new DomainException('Choose a valid request purpose.');
        }
        if ($copies < 1 || $copies > 10) {
            throw new DomainException('Request between 1 and 10 copies.');
        }
        if (strlen($remarks) > 500) {
            throw new DomainException('Additional notes must be 500 characters or fewer.');
        }

        $this->db->beginTransaction();
        try {
            $studentLookup = $this->db->prepare('SELECT id FROM students WHERE user_id = :user_id LIMIT 1 FOR UPDATE');
            $studentLookup->execute(['user_id' => $userId]);
            $studentId = $studentLookup->fetchColumn();
            if ($studentId === false) {
                throw new DomainException('No student profile is linked to this account.');
            }

            $typeLookup = $this->db->prepare('SELECT id, name, fee FROM document_types WHERE id = :id AND is_active = 1 LIMIT 1 FOR UPDATE');
            $typeLookup->execute(['id' => $typeId]);
            $type = $typeLookup->fetch();
            if (!$type) {
                throw new DomainException('This document type is no longer available. Refresh the page and choose another.');
            }

            $temporaryNo = 'TMP' . bin2hex(random_bytes(6));
            $insert = $this->db->prepare(
                'INSERT INTO document_requests (request_no, student_id, document_type_id, purpose, copies, remarks, fee_amount, status)
                 VALUES (:request_no, :student_id, :type_id, :purpose, :copies, :remarks, :fee, \'Pending\')'
            );

            $fee = number_format((float) $type['fee'], 2, '.', '');
            $insert->execute([
                'request_no' => $temporaryNo,
                'student_id' => $studentId,
                'type_id' => $typeId,
                'purpose' => $purpose,
                'copies' => $copies,
                'remarks' => trim($remarks) === '' ? null : trim($remarks),
                'fee' => $fee,
            ]);

            $requestId = (int) $this->db->lastInsertId();
            $requestNo = 'DR-' . date('Y') . '-' . str_pad((string) $requestId, 4, '0', STR_PAD_LEFT);

            if (strlen($requestNo) > 15) {
                throw new DomainException('The request number limit has been reached. Contact the system administrator.');
            }

            $updateNo = $this->db->prepare('UPDATE document_requests SET request_no = :request_no WHERE id = :id');
            $updateNo->execute(['request_no' => $requestNo, 'id' => $requestId]);

            if ((float) $fee > 0) {
                $payment = $this->db->prepare("INSERT INTO payments (request_id, amount, status) VALUES (:request_id, :amount, 'Unpaid')");
                $payment->execute(['request_id' => $requestId, 'amount' => number_format((float) $fee * $copies, 2, '.', '')]);
            }

            $this->notifyRegistrars('New document request submitted', 'A student submitted ' . $requestNo . ' for ' . $type['name'] . '.', '/php/registrar/documentRequest.php');
            $this->audit($userId, 'DOCUMENT_REQUEST_SUBMITTED', $requestId, $requestNo . ' submitted for ' . $type['name'] . '.');
            $this->db->commit();

            return $requestNo;
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $exception;
        }
    }

    public function getRegistrarQueue(): array
    {
        return $this->db->query(
            'SELECT request.id, request.request_no, request.purpose, request.copies, request.remarks,
                    request.fee_amount, request.status, request.rejection_reason, request.created_at,
                    request.received_by, type.name AS document_name, type.code AS document_code,
                    student.student_no, student.year_level, program.code AS program_code,
                    CONCAT_WS(\' \', student.first_name, student.middle_name, student.last_name) AS student_name,
                    payment.status AS payment_status, payment.amount AS payment_amount, payment.receipt_no
             FROM document_requests request
             INNER JOIN document_types type ON type.id = request.document_type_id
             INNER JOIN students student ON student.id = request.student_id
             INNER JOIN programs program ON program.id = student.program_id
             LEFT JOIN payments payment ON payment.request_id = request.id
             ORDER BY request.created_at ASC, request.id ASC'
        )->fetchAll();
    }

    /** Move a request only through its allowed Registrar workflow transition. */
    public function transition(string $requestNo, int $registrarId, string $nextStatus, string $receivedBy = ''): void
    {
        $this->db->beginTransaction();
        try {
            $request = $this->lockRequest($requestNo);
            $allowed = match ($request['status']) {
                'Pending' => ['Under Review'],
                'Under Review' => (float) $request['fee_amount'] > 0 ? ['For Payment'] : ['Processing'],
                'Paid' => ['Processing'],
                'Processing' => ['Ready for Release'],
                'Ready for Release' => ['Completed'],
                default => [],
            };

            if (!in_array($nextStatus, $allowed, true)) {
                throw new DomainException('That status change is not allowed for this request. Refresh the queue and try again.');
            }

            if ($nextStatus === 'Processing' && $request['status'] === 'Paid' && $request['payment_status'] !== 'Paid') {
                throw new DomainException('The payment must be verified before processing this request.');
            }

            if (strlen($receivedBy) > 120) {
                throw new DomainException('The claimant name must be 120 characters or fewer.');
            }

            if ($nextStatus === 'For Payment' && empty($request['payment_id']) && (float) $request['fee_amount'] > 0) {
                $amount = number_format((float) $request['fee_amount'] * (int) $request['copies'], 2, '.', '');
                $createPayment = $this->db->prepare("INSERT INTO payments (request_id, amount, status) VALUES (:request_id, :amount, 'Unpaid')");
                $createPayment->execute(['request_id' => (int) $request['id'], 'amount' => $amount]);
                $request['payment_amount'] = $amount;
            }

            $update = $this->db->prepare(
                'UPDATE document_requests SET status = :status, processed_by = :reviewer_id,
                    received_by = :received_by, completed_at = :completed_at
                 WHERE id = :id'
            );
            $update->execute([
                'status' => $nextStatus,
                'reviewer_id' => $registrarId,
                'received_by' => $nextStatus === 'Completed' && trim($receivedBy) !== '' ? trim($receivedBy) : null,
                'completed_at' => $nextStatus === 'Completed' ? date('Y-m-d H:i:s') : null,
                'id' => $request['id'],
            ]);

            $this->notifyStudent((int) $request['user_id'], 'Document request updated', $requestNo . ' is now ' . $nextStatus . '.', '/php/student/requests.php');

            if ($nextStatus === 'For Payment') {
                $this->notifyCashiers(
                    'Document request ready for payment',
                    $requestNo . ' is ready for cashier payment processing. Amount due: ₱' . number_format((float) ($request['payment_amount'] ?? $request['fee_amount']), 2) . '.',
                    '/php/cashier/payments.php'
                );
            }

            $this->audit($registrarId, 'DOCUMENT_REQUEST_STATUS_CHANGED', (int) $request['id'], $requestNo . ' moved from ' . $request['status'] . ' to ' . $nextStatus . '.');
            $this->db->commit();
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }
    }

    public function reject(string $requestNo, int $registrarId, string $reason): void
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw new DomainException('A rejection reason is required.');
        }

        if (strlen($reason) > 500) {
            throw new DomainException('The rejection reason must be 500 characters or fewer.');
        }

        $this->db->beginTransaction();
        try {
            $request = $this->lockRequest($requestNo);

            if (!in_array($request['status'], ['Pending', 'Under Review'], true)) {
                throw new DomainException('Only pending or under-review requests can be rejected.');
            }

            if (in_array($request['payment_status'], ['Pending Verification', 'Paid', 'Refunded'], true)) {
                throw new DomainException('This request has a payment in progress or completed and cannot be rejected here.');
            }

            $update = $this->db->prepare("UPDATE document_requests SET status = 'Rejected', rejection_reason = :reason, processed_by = :reviewer_id WHERE id = :id");
            $update->execute(['reason' => $reason, 'reviewer_id' => $registrarId, 'id' => $request['id']]);

            $this->notifyStudent((int) $request['user_id'], 'Document request rejected', $requestNo . ' was rejected. Reason: ' . $reason, '/php/student/requests.php');
            $this->audit($registrarId, 'DOCUMENT_REQUEST_REJECTED', (int) $request['id'], $requestNo . ' rejected. Reason: ' . $reason);
            $this->db->commit();
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $exception;
        }
    }

    private function lockRequest(string $requestNo): array
    {
        $statement = $this->db->prepare(
            'SELECT request.id, request.status, request.fee_amount, request.copies, student.user_id,
                    payment.id AS payment_id, payment.status AS payment_status, payment.amount AS payment_amount
             FROM document_requests request
             INNER JOIN students student ON student.id = request.student_id
             LEFT JOIN payments payment ON payment.request_id = request.id
             WHERE request.request_no = :request_no LIMIT 1 FOR UPDATE'
        );
        $statement->execute(['request_no' => $requestNo]);

        $request = $statement->fetch();

        if (!$request) {
            throw new DomainException('Document request not found.');
        }

        return $request;
    }

    private function notifyRegistrars(string $title, string $message, string $link): void
    {
        $statement = $this->db->prepare("INSERT INTO notifications (user_id, category, title, message, link) SELECT id, 'Requests', :title, :message, :link FROM users WHERE role = 'Registrar' AND status = 'Active'");
        $statement->execute(['title' => $title, 'message' => $message, 'link' => $link]);
    }

    private function notifyCashiers(string $title, string $message, string $link): void
    {
        $statement = $this->db->prepare(
            "INSERT INTO notifications (user_id, category, title, message, link)
             SELECT id, 'Payments', :title, :message, :link
             FROM users WHERE role = 'Cashier' AND status = 'Active'"
        );
        $statement->execute(['title' => $title, 'message' => $message, 'link' => $link]);
    }

    private function notifyStudent(int $userId, string $title, string $message, string $link): void
    {
        $statement = $this->db->prepare("INSERT INTO notifications (user_id, category, title, message, link) VALUES (:user_id, 'Requests', :title, :message, :link)");
        $statement->execute(['user_id' => $userId, 'title' => $title, 'message' => $message, 'link' => $link]);
    }

    private function audit(int $userId, string $action, int $requestId, string $details): void
    {
        $statement = $this->db->prepare("INSERT INTO audit_logs (user_id, action, entity, entity_id, details, ip_address) VALUES (:user_id, :action, 'document_requests', :entity_id, :details, :ip_address)");
        $statement->execute([
            'user_id' => $userId,
            'action' => $action,
            'entity_id' => $requestId,
            'details' => $details,
            'ip_address' => substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45),
        ]);
    }
}
