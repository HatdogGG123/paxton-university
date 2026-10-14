<?php

final class Clearance
{
    public function __construct(private PDO $db) {}

    public function getCurrentTerm(): ?array
    {
        $term = $this->db->query('SELECT id, academic_year, semester FROM terms WHERE is_current = 1 LIMIT 1')->fetch();
        return $term ?: null;
    }

    public function getDepartmentId(int $userId): ?int
    {
        $statement = $this->db->prepare("SELECT department_id FROM users WHERE id = :user_id AND role = 'Department' LIMIT 1");
        $statement->execute(['user_id' => $userId]);
        $departmentId = $statement->fetchColumn();
        return $departmentId === false || $departmentId === null ? null : (int) $departmentId;
    }

    public function getDepartmentName(?int $departmentId): ?string
    {
        if ($departmentId === null) {
            return null;
        }
        $statement = $this->db->prepare('SELECT name FROM departments WHERE id = :department_id LIMIT 1');
        $statement->execute(['department_id' => $departmentId]);
        $name = $statement->fetchColumn();
        return $name === false ? null : (string) $name;
    }

    public function ensureTermClearances(int $termId): void
    {
        $this->db->beginTransaction();
        try {
            $clearances = $this->db->prepare(
                'INSERT INTO clearances (student_id, term_id)
                 SELECT student.id, :term_id FROM students student
                 ON DUPLICATE KEY UPDATE term_id = VALUES(term_id)'
            );
            $clearances->execute(['term_id' => $termId]);

            $removeInvalidItems = $this->db->prepare(
                "DELETE item FROM clearance_items item
                 INNER JOIN clearances clearance ON clearance.id = item.clearance_id
                 INNER JOIN students student ON student.id = clearance.student_id
                 INNER JOIN programs program ON program.id = student.program_id
                 INNER JOIN clearance_requirements requirement ON requirement.id = item.requirement_id
                 INNER JOIN departments requirement_department ON requirement_department.id = requirement.department_id
                 WHERE clearance.term_id = :term_id
                   AND requirement.office = 'Department'
                   AND requirement_department.dept_type = 'Academic'
                   AND requirement.department_id <> program.department_id"
            );
            $removeInvalidItems->execute(['term_id' => $termId]);

            $items = $this->db->prepare(
                "INSERT INTO clearance_items (clearance_id, requirement_id)
                 SELECT clearance.id, requirement.id
                 FROM clearances clearance
                 INNER JOIN students student ON student.id = clearance.student_id
                 INNER JOIN programs program ON program.id = student.program_id
                 CROSS JOIN clearance_requirements requirement
                 LEFT JOIN departments requirement_department ON requirement_department.id = requirement.department_id
                 LEFT JOIN clearance_items item
                    ON item.clearance_id = clearance.id AND item.requirement_id = requirement.id
                 WHERE clearance.term_id = :term_id AND requirement.is_active = 1 AND item.id IS NULL
                   AND (requirement.office <> 'Department'
                        OR requirement_department.dept_type <> 'Academic'
                        OR requirement.department_id = program.department_id)
            "
            );
            $items->execute(['term_id' => $termId]);

            $this->refreshOverallStatusesForTerm($termId);
            $this->db->commit();
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }
    }

    public function getStudentClearance(int $userId, int $termId): ?array
    {
        $statement = $this->db->prepare(
            'SELECT clearance.id, clearance.overall_status, student.id AS student_id,
                    student.student_no, student.first_name, student.middle_name, student.last_name,
                    program.name AS program_name, student.year_level
             FROM clearances clearance
             INNER JOIN students student ON student.id = clearance.student_id AND student.user_id = :user_id
             INNER JOIN programs program ON program.id = student.program_id
             WHERE clearance.term_id = :term_id LIMIT 1'
        );
        $statement->execute(['user_id' => $userId, 'term_id' => $termId]);
        $clearance = $statement->fetch();

        if (!$clearance) {
            return null;
        }

        $clearance['items'] = $this->getItemsForClearance((int) $clearance['id']);

        return $clearance;
    }

    public function getItemsForClearance(int $clearanceId): array
    {
        $statement = $this->db->prepare(
            "SELECT item.id AS item_id, item.status, item.remarks, item.rejection_reason,
                    item.submitted_at, item.reviewed_at, requirement.id AS requirement_id,
                    requirement.name, requirement.description, requirement.office,
                    department.name AS department_name
             FROM clearance_items item
             INNER JOIN clearance_requirements requirement ON requirement.id = item.requirement_id
             INNER JOIN clearances clearance ON clearance.id = item.clearance_id
             INNER JOIN students student ON student.id = clearance.student_id
             INNER JOIN programs program ON program.id = student.program_id
             LEFT JOIN departments department ON department.id = requirement.department_id
             WHERE item.clearance_id = :clearance_id AND requirement.is_active = 1
               AND (requirement.office <> 'Department'
                    OR department.dept_type <> 'Academic'
                    OR requirement.department_id = program.department_id)
             ORDER BY FIELD(requirement.office, 'Registrar', 'Cashier', 'Department'), department.name, requirement.name"
        );

        $statement->execute(['clearance_id' => $clearanceId]);

        return $statement->fetchAll();
    }

    public function getOfficeQueue(int $termId, string $office, ?int $departmentId = null): array
    {
        if (!in_array($office, ['Registrar', 'Cashier', 'Department'], true)) {
            throw new DomainException('The clearance office is invalid.');
        }

        $sql =
            'SELECT item.id AS item_id, item.status, item.remarks, item.rejection_reason,
                    item.submitted_at, item.reviewed_at, requirement.id AS requirement_id,
                    requirement.name AS requirement_name, requirement.description, requirement.office,
                    clearance.id AS clearance_id, clearance.overall_status,
                    student.id AS student_id, student.student_no, student.first_name,
                    student.middle_name, student.last_name, student.year_level,
                    program.code AS program_code, program.name AS program_name,
                    COALESCE(payment_balance.outstanding_balance, 0) AS outstanding_balance,
                    department.name AS department_name
             FROM clearance_items item
             INNER JOIN clearances clearance ON clearance.id = item.clearance_id
             INNER JOIN students student ON student.id = clearance.student_id
             INNER JOIN programs program ON program.id = student.program_id
             INNER JOIN clearance_requirements requirement ON requirement.id = item.requirement_id AND requirement.is_active = 1
             LEFT JOIN departments department ON department.id = requirement.department_id
             LEFT JOIN (
                 SELECT request.student_id, SUM(payment.amount) AS outstanding_balance
                 FROM document_requests request
                 INNER JOIN payments payment ON payment.request_id = request.id
                 WHERE request.status = \'For Payment\' AND payment.status IN (\'Unpaid\',\'Pending Verification\',\'Rejected\')
                 GROUP BY request.student_id
             ) payment_balance ON payment_balance.student_id = student.id
             WHERE clearance.term_id = :term_id AND requirement.office = :office';

        $parameters = ['term_id' => $termId, 'office' => $office];

        if ($office === 'Department') {
            if ($departmentId === null) {
                return [];
            }
            $sql .= ' AND requirement.department_id = :department_id';
            $sql .= " AND (department.dept_type <> 'Academic' OR program.department_id = requirement.department_id)";
            $parameters['department_id'] = $departmentId;
        }

        $sql .= " ORDER BY FIELD(item.status, 'Pending', 'Rejected', 'Approved'), student.last_name, student.first_name, requirement.name";

        $statement = $this->db->prepare($sql);
        $statement->execute($parameters);

        $items = $statement->fetchAll();
        $clearanceIds = array_values(array_unique(array_map(
            static fn(array $item): int => (int) $item['clearance_id'],
            $items
        )));

        $itemsByClearance = $this->getItemsForClearances($clearanceIds);

        foreach ($items as &$item) {
            $item['student_name'] = trim(implode(' ', array_filter([$item['first_name'], $item['middle_name'], $item['last_name']], static fn($part): bool => $part !== null && $part !== '')));
            $item['all_items'] = $itemsByClearance[(int) $item['clearance_id']] ?? [];
        }

        unset($item);

        return $items;
    }

    public function getDepartmentRequirements(int $departmentId): array
    {
        $statement = $this->db->prepare("SELECT name, description FROM clearance_requirements WHERE office = 'Department' AND department_id = :department_id AND is_active = 1 ORDER BY name");
        $statement->execute(['department_id' => $departmentId]);
        return $statement->fetchAll();
    }

    public function getSummary(int $termId): array
    {
        $statement = $this->db->prepare(
            "SELECT COUNT(*) AS total,
                    SUM(overall_status = 'Cleared') AS cleared,
                    SUM(overall_status = 'Action Needed') AS action_needed,
                    SUM(overall_status = 'In Progress') AS in_progress
             FROM clearances WHERE term_id = :term_id"
        );

        $statement->execute(['term_id' => $termId]);

        return $statement->fetch() ?: ['total' => 0, 'cleared' => 0, 'action_needed' => 0, 'in_progress' => 0];
    }

    public function reviewItem(int $itemId, int $reviewerId, string $office, ?int $departmentId, string $status, string $reason = '', string $remarks = '', string $settlementReference = ''): void
    {
        if (!in_array($status, ['Approved', 'Rejected'], true)) {
            throw new DomainException('Choose an allowed clearance decision.');
        }

        if ($status === 'Rejected' && trim($reason) === '') {
            throw new DomainException('A reason is required when rejecting a clearance item.');
        }

        if (strlen($reason) > 500 || strlen($remarks) > 255 || strlen($settlementReference) > 60) {
            throw new DomainException('Clearance notes are too long.');
        }

        $this->db->beginTransaction();
        try {
            $scope = "requirement.office = :office";
            $parameters = ['item_id' => $itemId, 'office' => $office];

            if ($office === 'Department') {
                if ($departmentId === null) {
                    throw new DomainException('Your account is not assigned to a department.');
                }
                $scope .= ' AND requirement.department_id = :department_id';
                $scope .= " AND (requirement_department.dept_type <> 'Academic' OR program.department_id = requirement.department_id)";
                $parameters['department_id'] = $departmentId;
            }

            $lookup = $this->db->prepare(
                "SELECT item.id, item.status, requirement.name AS requirement_name,
                        clearance.id AS clearance_id, student.id AS student_id, student.user_id,
                        COALESCE((
                            SELECT SUM(payment.amount)
                            FROM document_requests request
                            INNER JOIN payments payment ON payment.request_id = request.id
                            WHERE request.student_id = student.id AND request.status = 'For Payment'
                              AND payment.status IN ('Unpaid','Pending Verification','Rejected')
                        ), 0) AS outstanding_balance
                 FROM clearance_items item
                 INNER JOIN clearance_requirements requirement ON requirement.id = item.requirement_id
                 LEFT JOIN departments requirement_department ON requirement_department.id = requirement.department_id
                 INNER JOIN clearances clearance ON clearance.id = item.clearance_id
                 INNER JOIN students student ON student.id = clearance.student_id
                 INNER JOIN programs program ON program.id = student.program_id
                 WHERE item.id = :item_id AND $scope FOR UPDATE"
            );

            $lookup->execute($parameters);
            $item = $lookup->fetch();

            if (!$item) {
                throw new DomainException('This clearance item is not assigned to your office.');
            }

            if ($office === 'Cashier' && $status === 'Approved') {
                $hasOutstandingBalance = (float) $item['outstanding_balance'] > 0;

                if ($hasOutstandingBalance && $settlementReference === '') {
                    throw new DomainException('Enter the receipt or payment reference for this student before clearing the balance.');
                }

                if ($settlementReference !== '') {
                    $referenceCheck = $this->db->prepare(
                        "SELECT payment.id
                         FROM payments payment
                         INNER JOIN document_requests request ON request.id = payment.request_id
                         WHERE request.student_id = :student_id AND payment.status = 'Paid'
                           AND (payment.receipt_no = :receipt_no OR payment.reference_number = :reference_no)
                         LIMIT 1"
                    );
                    $referenceCheck->execute([
                        'student_id' => (int) $item['student_id'],
                        'receipt_no' => $settlementReference,
                        'reference_no' => $settlementReference,
                    ]);

                    if (!$referenceCheck->fetchColumn()) {
                        throw new DomainException('That receipt or payment reference does not belong to this student.');
                    }
                }
            }

            $storedRemarks = trim($remarks);

            if ($office === 'Cashier' && $status === 'Approved' && $settlementReference !== '') {
                $storedRemarks = $settlementReference . ($storedRemarks !== '' ? ' — ' . $storedRemarks : '');
            }

            if (strlen($storedRemarks) > 255) {
                throw new DomainException('Clearance notes are too long.');
            }

            $update = $this->db->prepare(
                'UPDATE clearance_items
                 SET status = :status, remarks = :remarks, rejection_reason = :reason,
                     reviewed_by = :reviewer_id, reviewed_at = CURRENT_TIMESTAMP,
                     submitted_at = COALESCE(submitted_at, CURRENT_TIMESTAMP)
                 WHERE id = :item_id'
            );
            $update->execute([
                'status' => $status,
                'remarks' => $storedRemarks === '' ? null : $storedRemarks,
                'reason' => $status === 'Rejected' ? trim($reason) : null,
                'reviewer_id' => $reviewerId,
                'item_id' => $itemId,
            ]);

            $this->refreshOverallStatus((int) $item['clearance_id']);

            $message = 'Your ' . $item['requirement_name'] . ' clearance was ' . strtolower($status) . '.';

            if ($status === 'Rejected') {
                $message .= ' Reason: ' . trim($reason);
            }

            $notify = $this->db->prepare("INSERT INTO notifications (user_id, category, title, message) VALUES (:user_id, 'Clearance', :title, :message)");
            $notify->execute(['user_id' => $item['user_id'], 'title' => 'Clearance status updated', 'message' => $message]);

            $audit = $this->db->prepare('INSERT INTO audit_logs (user_id, action, entity, entity_id, details, ip_address) VALUES (:user_id, :action, \'clearance_items\', :entity_id, :details, :ip_address)');
            $audit->execute([
                'user_id' => $reviewerId,
                'action' => $status === 'Approved' ? 'CLEARANCE_APPROVED' : 'CLEARANCE_REJECTED',
                'entity_id' => $itemId,
                'details' => $office . ' office ' . strtolower($status) . ' ' . $item['requirement_name'] . ($status === 'Rejected' ? ': ' . trim($reason) : ''),
                'ip_address' => substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45),
            ]);

            $this->db->commit();
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $exception;
        }
    }

    private function refreshOverallStatus(int $clearanceId): void
    {
        $statement = $this->db->prepare(
            "SELECT COUNT(*) AS total, SUM(status = 'Approved') AS approved, SUM(status = 'Rejected') AS rejected
             FROM clearance_items item
             INNER JOIN clearance_requirements requirement ON requirement.id = item.requirement_id AND requirement.is_active = 1
             INNER JOIN clearances clearance ON clearance.id = item.clearance_id
             INNER JOIN students student ON student.id = clearance.student_id
             INNER JOIN programs program ON program.id = student.program_id
             LEFT JOIN departments requirement_department ON requirement_department.id = requirement.department_id
             WHERE item.clearance_id = :clearance_id
               AND (requirement.office <> 'Department'
                    OR requirement_department.dept_type <> 'Academic'
                    OR requirement.department_id = program.department_id)"
        );

        $statement->execute(['clearance_id' => $clearanceId]);

        $counts = $statement->fetch();

        $overall = (int) $counts['total'] > 0 && (int) $counts['approved'] === (int) $counts['total']
            ? 'Cleared'
            : ((int) $counts['rejected'] > 0 ? 'Action Needed' : 'In Progress');

        $update = $this->db->prepare('UPDATE clearances SET overall_status = :status WHERE id = :id');
        $update->execute(['status' => $overall, 'id' => $clearanceId]);
    }

    private function getItemsForClearances(array $clearanceIds): array
    {
        if ($clearanceIds === []) {
            return [];
        }

        $clearanceIds = array_values(array_unique(array_map('intval', $clearanceIds)));
        $placeholders = implode(',', array_fill(0, count($clearanceIds), '?'));

        $statement = $this->db->prepare(
            "SELECT item.clearance_id, item.id AS item_id, item.status, item.remarks, item.rejection_reason,
                    item.submitted_at, item.reviewed_at, requirement.id AS requirement_id,
                    requirement.name, requirement.description, requirement.office,
                    department.name AS department_name
             FROM clearance_items item
             INNER JOIN clearance_requirements requirement ON requirement.id = item.requirement_id
             INNER JOIN clearances clearance ON clearance.id = item.clearance_id
             INNER JOIN students student ON student.id = clearance.student_id
             INNER JOIN programs program ON program.id = student.program_id
             LEFT JOIN departments department ON department.id = requirement.department_id
             WHERE item.clearance_id IN ($placeholders) AND requirement.is_active = 1
               AND (requirement.office <> 'Department'
                    OR department.dept_type <> 'Academic'
                    OR requirement.department_id = program.department_id)
             ORDER BY FIELD(requirement.office, 'Registrar', 'Cashier', 'Department'), department.name, requirement.name"
        );

        $statement->execute($clearanceIds);

        $itemsByClearance = [];

        foreach ($statement->fetchAll() as $item) {
            $itemsByClearance[(int) $item['clearance_id']][] = $item;
        }

        return $itemsByClearance;
    }

    private function refreshOverallStatusesForTerm(int $termId): void
    {
        $statement = $this->db->prepare(
            "UPDATE clearances clearance
             LEFT JOIN (
                 SELECT clearance.id AS clearance_id,
                        COUNT(item.id) AS total,
                        SUM(item.status = 'Approved') AS approved,
                        SUM(item.status = 'Rejected') AS rejected
                 FROM clearances clearance
                 INNER JOIN students student ON student.id = clearance.student_id
                 INNER JOIN programs program ON program.id = student.program_id
                 INNER JOIN clearance_items item ON item.clearance_id = clearance.id
                 INNER JOIN clearance_requirements requirement
                    ON requirement.id = item.requirement_id AND requirement.is_active = 1
                 LEFT JOIN departments department ON department.id = requirement.department_id
                 WHERE clearance.term_id = :aggregate_term_id
                   AND (requirement.office <> 'Department'
                        OR department.dept_type <> 'Academic'
                        OR requirement.department_id = program.department_id)
                 GROUP BY clearance.id
             ) item_counts ON item_counts.clearance_id = clearance.id
             SET clearance.overall_status = CASE
                 WHEN COALESCE(item_counts.total, 0) > 0
                      AND item_counts.approved = item_counts.total THEN 'Cleared'
                 WHEN COALESCE(item_counts.rejected, 0) > 0 THEN 'Action Needed'
                 ELSE 'In Progress'
             END
             WHERE clearance.term_id = :update_term_id
               AND NOT (clearance.overall_status <=> CASE
                   WHEN COALESCE(item_counts.total, 0) > 0
                        AND item_counts.approved = item_counts.total THEN 'Cleared'
                   WHEN COALESCE(item_counts.rejected, 0) > 0 THEN 'Action Needed'
                   ELSE 'In Progress'
               END)"
        );

        $statement->execute([
            'aggregate_term_id' => $termId,
            'update_term_id' => $termId,
        ]);
    }
}
