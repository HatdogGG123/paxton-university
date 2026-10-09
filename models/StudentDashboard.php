<?php
/** Aggregate current student data for the existing dashboard layout. */
final class StudentDashboard
{
    public function __construct(private PDO $db)
    {
    }

    public function getData(int $userId): array
    {
        $profileQuery = $this->db->prepare(
            'SELECT student.id, student.student_no, student.first_name, student.middle_name,
                    student.last_name, student.year_level, program.name AS program_name,
                    program.code AS program_code, program.department_id
             FROM students student INNER JOIN programs program ON program.id = student.program_id
             WHERE student.user_id = :user_id LIMIT 1'
        );
        $profileQuery->execute(['user_id' => $userId]);
        $profile = $profileQuery->fetch();
        if (!$profile) {
            throw new DomainException('No student profile is linked to this account.');
        }
        $studentId = (int) $profile['id'];

        $term = $this->db->query('SELECT id, academic_year, semester FROM terms WHERE is_current = 1 LIMIT 1')->fetch();
        if (!$term) {
            $termQuery = $this->db->prepare(
                'SELECT term.id, term.academic_year, term.semester FROM enrollments enrollment
                 INNER JOIN terms term ON term.id = enrollment.term_id
                 WHERE enrollment.student_id = :student_id ORDER BY term.id DESC LIMIT 1'
            );
            $termQuery->execute(['student_id' => $studentId]);
            $term = $termQuery->fetch() ?: null;
        }

        $enrollment = null;
        $clearance = null;
        $grades = [];
        if ($term) {
            $enrollmentQuery = $this->db->prepare(
                'SELECT enrollment.enrollment_no, enrollment.status, enrollment.submitted_at,
                        term.academic_year, term.semester
                 FROM enrollments enrollment INNER JOIN terms term ON term.id = enrollment.term_id
                 WHERE enrollment.student_id = :student_id AND enrollment.term_id = :term_id
                 ORDER BY enrollment.submitted_at DESC, enrollment.id DESC LIMIT 1'
            );
            $enrollmentQuery->execute(['student_id' => $studentId, 'term_id' => (int) $term['id']]);
            $enrollment = $enrollmentQuery->fetch() ?: null;

            $clearanceQuery = $this->db->prepare(
                "SELECT CASE
                            WHEN COUNT(CASE WHEN requirement.is_active = 1
                                AND (requirement.office <> 'Department' OR requirement_department.dept_type <> 'Academic'
                                     OR requirement.department_id = program.department_id) THEN requirement.id END) > 0
                             AND SUM(CASE WHEN requirement.is_active = 1 AND item.status = 'Approved'
                                AND (requirement.office <> 'Department' OR requirement_department.dept_type <> 'Academic'
                                     OR requirement.department_id = program.department_id) THEN 1 ELSE 0 END)
                                = COUNT(CASE WHEN requirement.is_active = 1
                                    AND (requirement.office <> 'Department' OR requirement_department.dept_type <> 'Academic'
                                         OR requirement.department_id = program.department_id) THEN requirement.id END) THEN 'Cleared'
                            WHEN SUM(CASE WHEN requirement.is_active = 1 AND item.status = 'Rejected'
                                AND (requirement.office <> 'Department' OR requirement_department.dept_type <> 'Academic'
                                     OR requirement.department_id = program.department_id) THEN 1 ELSE 0 END) > 0 THEN 'Action Needed'
                            ELSE 'In Progress'
                        END AS overall_status,
                        SUM(CASE WHEN requirement.is_active = 1 AND item.status = 'Approved'
                            AND (requirement.office <> 'Department' OR requirement_department.dept_type <> 'Academic'
                                 OR requirement.department_id = program.department_id) THEN 1 ELSE 0 END) AS approved_count,
                        COUNT(CASE WHEN requirement.is_active = 1
                            AND (requirement.office <> 'Department' OR requirement_department.dept_type <> 'Academic'
                                 OR requirement.department_id = program.department_id) THEN requirement.id END) AS item_count
                 FROM clearances clearance
                 INNER JOIN students student ON student.id = clearance.student_id
                 INNER JOIN programs program ON program.id = student.program_id
                 LEFT JOIN clearance_items item ON item.clearance_id = clearance.id
                 LEFT JOIN clearance_requirements requirement ON requirement.id = item.requirement_id
                    AND requirement.is_active = 1
                 LEFT JOIN departments requirement_department ON requirement_department.id = requirement.department_id
                 WHERE clearance.student_id = :student_id AND clearance.term_id = :term_id
                 GROUP BY clearance.id LIMIT 1"
            );
            $clearanceQuery->execute(['student_id' => $studentId, 'term_id' => (int) $term['id']]);
            $clearance = $clearanceQuery->fetch() ?: null;

            $gradeQuery = $this->db->prepare(
                "SELECT subject.code, subject.name, subject.units, grade.grade, grade.grade_status
                 FROM grades grade INNER JOIN subjects subject ON subject.id = grade.subject_id
                 WHERE grade.student_id = :student_id AND grade.term_id = :term_id AND grade.status = 'Released'
                 ORDER BY grade.released_at DESC, subject.code LIMIT 5"
            );
            $gradeQuery->execute(['student_id' => $studentId, 'term_id' => (int) $term['id']]);
            $grades = $gradeQuery->fetchAll();
        }

        $requestQuery = $this->db->prepare(
            'SELECT request.request_no, request.status, request.fee_amount, request.created_at,
                    type.name AS document_name, payment.status AS payment_status
             FROM document_requests request
             INNER JOIN document_types type ON type.id = request.document_type_id
             LEFT JOIN payments payment ON payment.request_id = request.id
             WHERE request.student_id = :student_id
             ORDER BY request.created_at DESC, request.id DESC LIMIT 5'
        );
        $requestQuery->execute(['student_id' => $studentId]);
        $requests = $requestQuery->fetchAll();

        $requestSummary = $this->db->prepare(
            "SELECT COUNT(CASE WHEN request.status NOT IN ('Completed','Rejected','Cancelled') THEN 1 END) AS active_count,
                    COALESCE(SUM(CASE WHEN payment.status IN ('Unpaid','Pending Verification','Rejected') THEN payment.amount ELSE 0 END), 0) AS outstanding
             FROM document_requests request LEFT JOIN payments payment ON payment.request_id = request.id
             WHERE request.student_id = :student_id"
        );
        $requestSummary->execute(['student_id' => $studentId]);
        $financial = $requestSummary->fetch() ?: ['active_count' => 0, 'outstanding' => 0];

        $clearanceStatusQuery = $this->db->prepare(
            "SELECT CASE
                        WHEN COUNT(CASE WHEN requirement.is_active = 1
                            AND (requirement.office <> 'Department' OR requirement_department.dept_type <> 'Academic'
                                 OR requirement.department_id = program.department_id) THEN requirement.id END) > 0
                         AND SUM(CASE WHEN requirement.is_active = 1 AND item.status = 'Approved'
                            AND (requirement.office <> 'Department' OR requirement_department.dept_type <> 'Academic'
                                 OR requirement.department_id = program.department_id) THEN 1 ELSE 0 END)
                            = COUNT(CASE WHEN requirement.is_active = 1
                                AND (requirement.office <> 'Department' OR requirement_department.dept_type <> 'Academic'
                                     OR requirement.department_id = program.department_id) THEN requirement.id END) THEN 'Cleared'
                        WHEN SUM(CASE WHEN requirement.is_active = 1 AND item.status = 'Rejected'
                            AND (requirement.office <> 'Department' OR requirement_department.dept_type <> 'Academic'
                                 OR requirement.department_id = program.department_id) THEN 1 ELSE 0 END) > 0 THEN 'Action Needed'
                        ELSE 'In Progress'
                    END AS overall_status
             FROM clearances clearance
             INNER JOIN students student ON student.id = clearance.student_id
             INNER JOIN programs program ON program.id = student.program_id
             LEFT JOIN clearance_items item ON item.clearance_id = clearance.id
             LEFT JOIN clearance_requirements requirement ON requirement.id = item.requirement_id AND requirement.is_active = 1
             LEFT JOIN departments requirement_department ON requirement_department.id = requirement.department_id
             WHERE clearance.student_id = :student_id
             GROUP BY clearance.id ORDER BY clearance.term_id DESC LIMIT 1"
        );
        $clearanceStatusQuery->execute(['student_id' => $studentId]);

        return [
            'profile' => $profile,
            'term' => $term,
            'enrollment' => $enrollment,
            'clearance' => $clearance,
            'latest_clearance_status' => $clearanceStatusQuery->fetchColumn() ?: 'Not Started',
            'grades' => $grades,
            'requests' => $requests,
            'active_request_count' => (int) $financial['active_count'],
            'outstanding_balance' => (float) $financial['outstanding'],
        ];
    }

    public function getAnnouncements(int $limit = 3): array
    {
        $limit = max(1, min(10, $limit));
        return $this->db->query(
            "SELECT announcement.title, announcement.body, announcement.created_at,
                    COALESCE(account.full_name, account.username, 'SSIS') AS posted_by
             FROM announcements announcement
             INNER JOIN users account ON account.id = announcement.posted_by
             WHERE announcement.is_active = 1 AND announcement.audience IN ('All','Students')
             ORDER BY announcement.created_at DESC, announcement.id DESC LIMIT $limit"
        )->fetchAll();
    }
}
