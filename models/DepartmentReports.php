<?php
/** Department-scoped clearance report queries. */
final class DepartmentReports
{
    public function __construct(private PDO $db)
    {
    }

    public function getPrograms(int $departmentId): array
    {
        $statement = $this->db->prepare(
            'SELECT id, code, name FROM programs WHERE department_id = :department_id ORDER BY name'
        );
        $statement->execute(['department_id' => $departmentId]);
        return $statement->fetchAll();
    }

    public function getClearanceReport(int $departmentId, ?int $programId = null, ?int $yearLevel = null): array
    {
        $term = $this->db->query('SELECT id, academic_year, semester FROM terms WHERE is_current = 1 LIMIT 1')->fetch();
        if (!$term) {
            return ['term' => null, 'summary' => ['total' => 0, 'cleared' => 0, 'not_cleared' => 0], 'rows' => []];
        }

        $filters = '';
        $parameters = ['term_id' => (int) $term['id'], 'department_id' => $departmentId, 'requirement_department_id' => $departmentId];
        if ($programId !== null) {
            $filters .= ' AND student.program_id = :program_id';
            $parameters['program_id'] = $programId;
        }
        if ($yearLevel !== null) {
            $filters .= ' AND student.year_level = :year_level';
            $parameters['year_level'] = $yearLevel;
        }

        $statement = $this->db->prepare(
            "SELECT student.student_no,
                    CONCAT_WS(' ', student.first_name, student.middle_name, student.last_name) AS student_name,
                    student.year_level, program.code AS program_code, program.name AS program_name,
                    COUNT(requirement.id) AS requirement_count,
                    SUM(requirement.id IS NOT NULL AND item.status = 'Approved') AS approved_count,
                    SUM(requirement.id IS NOT NULL AND item.status = 'Rejected') AS rejected_count,
                    GROUP_CONCAT(CASE WHEN item.status <> 'Approved'
                        THEN CONCAT(requirement.name, ' (', item.status, ')') END
                        ORDER BY requirement.name SEPARATOR ', ') AS pending_requirements
             FROM clearances clearance
             INNER JOIN students student ON student.id = clearance.student_id
             INNER JOIN programs program ON program.id = student.program_id AND program.department_id = :department_id
             LEFT JOIN clearance_items item ON item.clearance_id = clearance.id
             LEFT JOIN clearance_requirements requirement ON requirement.id = item.requirement_id
                AND requirement.office = 'Department'
                AND requirement.department_id = :requirement_department_id
                AND requirement.is_active = 1
             WHERE clearance.term_id = :term_id $filters
             GROUP BY student.id, student.student_no, student.first_name, student.middle_name,
                      student.last_name, student.year_level, program.code, program.name
             ORDER BY student.last_name, student.first_name, student.student_no"
        );
        $statement->execute($parameters);
        $rows = [];
        foreach ($statement->fetchAll() as $row) {
            $total = (int) $row['requirement_count'];
            $approved = (int) $row['approved_count'];
            $row['status'] = $total > 0 && $approved === $total
                ? 'Cleared'
                : ((int) $row['rejected_count'] > 0 ? 'Action Needed' : 'In Progress');
            $row['pending_requirements'] = $row['pending_requirements'] ?: null;
            unset($row['requirement_count'], $row['approved_count'], $row['rejected_count']);
            $rows[] = $row;
        }

        $cleared = count(array_filter($rows, static fn (array $row): bool => $row['status'] === 'Cleared'));
        return [
            'term' => $term,
            'summary' => ['total' => count($rows), 'cleared' => $cleared, 'not_cleared' => count($rows) - $cleared],
            'rows' => $rows,
        ];
    }
}
