<?php
final class CashierDashboard
{
    public function __construct(private PDO $db) {}

    public function getData(): array
    {
        $term = $this->db->query('SELECT id, semester, academic_year FROM terms WHERE is_current = 1 LIMIT 1')->fetch() ?: null;

        $today = $this->db->query(
            "SELECT COALESCE(SUM(amount), 0) AS total,
                    COUNT(*) AS transaction_count
             FROM payments
             WHERE status = 'Paid' AND verified_at >= CURRENT_DATE
               AND verified_at < DATE_ADD(CURRENT_DATE, INTERVAL 1 DAY)"
        )->fetch() ?: ['total' => 0, 'transaction_count' => 0];

        $pendingQuery = $this->db->query(
            "SELECT payment.id AS payment_id, payment.amount, payment.method, payment.reference_number,
                    request.request_no, student.student_no, student.first_name, student.middle_name,
                    student.last_name
             FROM payments payment
             INNER JOIN document_requests request ON request.id = payment.request_id
             INNER JOIN students student ON student.id = request.student_id
             WHERE payment.status = 'Pending Verification'
             ORDER BY payment.submitted_at ASC, payment.id ASC LIMIT 5"
        );

        $pendingPayments = $pendingQuery->fetchAll();
        $pendingCount = (int) $this->db->query("SELECT COUNT(*) FROM payments WHERE status = 'Pending Verification'")->fetchColumn();

        $unpaidCount = (int) $this->db->query(
            "SELECT COUNT(*) FROM payments payment
             INNER JOIN document_requests request ON request.id = payment.request_id
             WHERE request.status = 'For Payment' AND payment.status IN ('Unpaid','Rejected')"
        )->fetchColumn();

        $clearance = ['pending_reviews' => 0, 'cleared_students' => 0, 'action_needed_students' => 0];

        if ($term) {
            $clearanceQuery = $this->db->prepare(
                "SELECT clearance.id AS clearance_id,
                        COUNT(CASE WHEN requirement.is_active = 1
                            AND (requirement.office <> 'Department'
                                 OR requirement_department.dept_type <> 'Academic'
                                 OR requirement.department_id = program.department_id)
                            THEN requirement.id END) AS requirement_count,
                        SUM(CASE WHEN requirement.is_active = 1 AND item.status = 'Approved'
                            AND (requirement.office <> 'Department'
                                 OR requirement_department.dept_type <> 'Academic'
                                 OR requirement.department_id = program.department_id)
                            THEN 1 ELSE 0 END) AS approved_count,
                        SUM(CASE WHEN requirement.is_active = 1 AND item.status = 'Rejected'
                            AND (requirement.office <> 'Department'
                                 OR requirement_department.dept_type <> 'Academic'
                                 OR requirement.department_id = program.department_id)
                            THEN 1 ELSE 0 END) AS rejected_count,
                        SUM(CASE WHEN requirement.office = 'Cashier' AND requirement.is_active = 1
                            AND item.status = 'Pending' THEN 1 ELSE 0 END) AS pending_reviews
                 FROM clearances clearance
                 INNER JOIN students student ON student.id = clearance.student_id
                 INNER JOIN programs program ON program.id = student.program_id
                 LEFT JOIN clearance_items item ON item.clearance_id = clearance.id
                 LEFT JOIN clearance_requirements requirement ON requirement.id = item.requirement_id
                 LEFT JOIN departments requirement_department ON requirement_department.id = requirement.department_id
                 WHERE clearance.term_id = :term_id
                 GROUP BY clearance.id, clearance.student_id"
            );

            $clearanceQuery->execute(['term_id' => (int) $term['id']]);

            foreach ($clearanceQuery->fetchAll() as $record) {
                $total = (int) $record['requirement_count'];
                $approved = (int) $record['approved_count'];

                if ($total > 0 && $approved === $total) {
                    $clearance['cleared_students']++;
                } elseif ((int) $record['rejected_count'] > 0) {
                    $clearance['action_needed_students']++;
                }
                $clearance['pending_reviews'] += (int) $record['pending_reviews'];
            }
        }

        $recentQuery = $this->db->query(
            "SELECT payment.receipt_no, payment.reference_number, payment.method, payment.amount,
                    payment.status AS payment_status, payment.verified_at, request.request_no,
                    CONCAT_WS(' ', student.first_name, student.middle_name, student.last_name) AS student_name
             FROM payments payment
             INNER JOIN document_requests request ON request.id = payment.request_id
             INNER JOIN students student ON student.id = request.student_id
             WHERE payment.status IN ('Paid','Refunded')
             ORDER BY payment.verified_at DESC, payment.id DESC LIMIT 4"
        );

        return [
            'term' => $term,
            'collected_today' => (float) $today['total'],
            'verified_today_count' => (int) $today['transaction_count'],
            'pending_payment_count' => $pendingCount,
            'pending_payments' => $pendingPayments,
            'unpaid_request_count' => $unpaidCount,
            'pending_clearance_count' => (int) $clearance['pending_reviews'],
            'cleared_students_count' => (int) $clearance['cleared_students'],
            'action_needed_students_count' => (int) $clearance['action_needed_students'],
            'recent_transactions' => $recentQuery->fetchAll(),
        ];
    }
}
