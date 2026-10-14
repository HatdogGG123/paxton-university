<?php
/** Live Registrar report data grouped by academic program. */
final class RegistrarReports
{
    public function __construct(private PDO $db)
    {
    }

    public function getTerms(): array
    {
        return $this->db->query('SELECT id, academic_year, semester, is_current FROM terms ORDER BY is_current DESC, academic_year DESC, FIELD(semester, \'1st Semester\', \'2nd Semester\', \'Summer\')')->fetchAll();
    }

    public function getPrograms(): array
    {
        return $this->db->query('SELECT id, code, name, is_active FROM programs ORDER BY name')->fetchAll();
    }

    public function getRows(array $filters): array
    {
        $sql =
            'SELECT program.id, program.code, program.name, program.is_active,
                (SELECT COUNT(*) FROM enrollments enrollment
                 INNER JOIN students student ON student.id = enrollment.student_id
                 WHERE student.program_id = program.id AND enrollment.term_id = :enroll_term
                   AND (:enroll_status = \'\' OR enrollment.status = :enroll_status_match)
                   AND (:enroll_from = \'\' OR DATE(enrollment.submitted_at) >= :enroll_from_match)
                   AND (:enroll_to = \'\' OR DATE(enrollment.submitted_at) <= :enroll_to_match)) AS enrollment_count,
                (SELECT COUNT(*) FROM document_requests request
                 INNER JOIN students student ON student.id = request.student_id
                 WHERE student.program_id = program.id
                   AND (:request_from = \'\' OR DATE(request.created_at) >= :request_from_match)
                   AND (:request_to = \'\' OR DATE(request.created_at) <= :request_to_match)) AS request_count,
                (SELECT COUNT(*) FROM document_requests request
                 INNER JOIN students student ON student.id = request.student_id
                 WHERE student.program_id = program.id AND request.status IN (\'Pending\',\'Under Review\',\'For Payment\')
                   AND (:pending_from = \'\' OR DATE(request.created_at) >= :pending_from_match)
                   AND (:pending_to = \'\' OR DATE(request.created_at) <= :pending_to_match)) AS pending_request_count,
                (SELECT COUNT(*) FROM grades grade
                 INNER JOIN students student ON student.id = grade.student_id
                 WHERE student.program_id = program.id AND grade.term_id = :grade_term
                   AND (:grade_from = \'\' OR DATE(COALESCE(grade.released_at, grade.updated_at)) >= :grade_from_match)
                   AND (:grade_to = \'\' OR DATE(COALESCE(grade.released_at, grade.updated_at)) <= :grade_to_match)) AS grade_count,
                (SELECT COUNT(*) FROM grades grade
                 INNER JOIN students student ON student.id = grade.student_id
                 WHERE student.program_id = program.id AND grade.term_id = :released_term AND grade.status = \'Released\'
                   AND (:released_from = \'\' OR DATE(grade.released_at) >= :released_from_match)
                   AND (:released_to = \'\' OR DATE(grade.released_at) <= :released_to_match)) AS released_grade_count
             FROM programs program WHERE (:program_id = 0 OR program.id = :program_id_match)
             ORDER BY program.name';

        $statement = $this->db->prepare($sql);
        $statement->execute([
            'enroll_term' => (int) ($filters['term_id'] ?? 0),
            'enroll_status' => (string) ($filters['status'] ?? ''),
            'enroll_status_match' => (string) ($filters['status'] ?? ''),
            'enroll_from' => (string) ($filters['date_from'] ?? ''),
            'enroll_from_match' => (string) ($filters['date_from'] ?? ''),
            'enroll_to' => (string) ($filters['date_to'] ?? ''),
            'enroll_to_match' => (string) ($filters['date_to'] ?? ''),
            'request_from' => (string) ($filters['date_from'] ?? ''),
            'request_from_match' => (string) ($filters['date_from'] ?? ''),
            'request_to' => (string) ($filters['date_to'] ?? ''),
            'request_to_match' => (string) ($filters['date_to'] ?? ''),
            'pending_from' => (string) ($filters['date_from'] ?? ''),
            'pending_from_match' => (string) ($filters['date_from'] ?? ''),
            'pending_to' => (string) ($filters['date_to'] ?? ''),
            'pending_to_match' => (string) ($filters['date_to'] ?? ''),
            'grade_term' => (int) ($filters['term_id'] ?? 0),
            'grade_from' => (string) ($filters['date_from'] ?? ''),
            'grade_from_match' => (string) ($filters['date_from'] ?? ''),
            'grade_to' => (string) ($filters['date_to'] ?? ''),
            'grade_to_match' => (string) ($filters['date_to'] ?? ''),
            'released_term' => (int) ($filters['term_id'] ?? 0),
            'released_from' => (string) ($filters['date_from'] ?? ''),
            'released_from_match' => (string) ($filters['date_from'] ?? ''),
            'released_to' => (string) ($filters['date_to'] ?? ''),
            'released_to_match' => (string) ($filters['date_to'] ?? ''),
            'program_id' => (int) ($filters['program_id'] ?? 0),
            'program_id_match' => (int) ($filters['program_id'] ?? 0),
        ]);
        
        return $statement->fetchAll();
    }
}
