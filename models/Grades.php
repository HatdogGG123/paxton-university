<?php
/** Department grade entry, Registrar review, and student grade queries. */
final class Grades
{
    public function __construct(private PDO $db)
    {
    }

    public function currentTerm(): ?array
    {
        return $this->db->query('SELECT id, academic_year, semester FROM terms WHERE is_current = 1 LIMIT 1')->fetch() ?: null;
    }

    /** Return the active enrolled section/subject pairs for one department. */
    public function getDepartmentGradeClasses(int $departmentId, int $termId): array
    {
        $query = $this->db->prepare("SELECT DISTINCT section.id AS section_id, section.code AS section_code, program.name AS program_name, subject.id AS subject_id, subject.code AS subject_code, subject.name AS subject_name
            FROM enrollments enrollment
            JOIN students student ON student.id = enrollment.student_id
            JOIN programs program ON program.id = student.program_id
            JOIN departments department ON department.id = program.department_id
            JOIN sections section ON section.id = enrollment.section_id AND section.program_id = program.id
            JOIN enrollment_subjects es ON es.enrollment_id = enrollment.id
            JOIN subjects subject ON subject.id = es.subject_id AND subject.program_id = program.id
                AND subject.year_level = section.year_level
            WHERE enrollment.term_id = :term_id AND enrollment.status = 'Enrolled'
              AND department.id = :department_id AND department.dept_type = 'Academic'
              AND section.is_active = 1 AND subject.is_active = 1
            ORDER BY section.code, subject.code");
        $query->execute(['term_id' => $termId, 'department_id' => $departmentId]);
        return $query->fetchAll();
    }

    /** Return an alphabetized roster and current workflow state for a valid class. */
    public function getDepartmentGradeRoster(int $departmentId, int $termId, int $sectionId, int $subjectId): array
    {
        $query = $this->db->prepare("SELECT student.id AS student_id, student.student_no, student.first_name, student.middle_name, student.last_name,
                    CONCAT_WS(' ', student.first_name, student.middle_name, student.last_name) AS student_name,
                    grade.id AS grade_id, grade.grade, grade.grade_status, grade.status AS workflow_status, grade.review_message
            FROM enrollments enrollment
            JOIN students student ON student.id = enrollment.student_id
            JOIN programs program ON program.id = student.program_id
            JOIN departments department ON department.id = program.department_id
            JOIN sections section ON section.id = enrollment.section_id AND section.program_id = program.id
            JOIN enrollment_subjects es ON es.enrollment_id = enrollment.id AND es.subject_id = :subject_id
            JOIN subjects subject ON subject.id = es.subject_id AND subject.program_id = program.id AND subject.year_level = section.year_level
            LEFT JOIN grades grade ON grade.student_id = student.id AND grade.subject_id = subject.id AND grade.term_id = enrollment.term_id
            WHERE enrollment.term_id = :term_id AND enrollment.status = 'Enrolled'
              AND department.id = :department_id AND department.dept_type = 'Academic'
              AND section.id = :section_id AND section.is_active = 1 AND subject.is_active = 1
            ORDER BY student.last_name, student.first_name, student.middle_name, student.student_no");
        $query->execute(['subject_id' => $subjectId, 'term_id' => $termId, 'department_id' => $departmentId, 'section_id' => $sectionId]);
        return $query->fetchAll();
    }

    /** Save or resubmit one department grade into the Registrar's review queue. */
    public function saveDepartmentGrade(int $departmentId, int $departmentUserId, int $studentId, int $termId, int $sectionId, int $subjectId, mixed $rawGrade): void
    {
        $grade = $this->validateGrade($rawGrade);
        $lookup = $this->db->prepare("SELECT student.id, student.student_no, student.first_name, student.last_name, subject.code AS subject_code, grade.id AS grade_id, grade.status
            FROM enrollments enrollment
            JOIN students student ON student.id = enrollment.student_id
            JOIN programs program ON program.id = student.program_id
            JOIN departments department ON department.id = program.department_id
            JOIN sections section ON section.id = enrollment.section_id AND section.program_id = program.id
            JOIN enrollment_subjects es ON es.enrollment_id = enrollment.id AND es.subject_id = :subject_id
            JOIN subjects subject ON subject.id = es.subject_id AND subject.program_id = program.id AND subject.year_level = section.year_level
            LEFT JOIN grades grade ON grade.student_id = student.id AND grade.subject_id = subject.id AND grade.term_id = enrollment.term_id
            WHERE enrollment.term_id = :term_id AND enrollment.status = 'Enrolled'
              AND department.id = :department_id AND department.dept_type = 'Academic'
              AND section.id = :section_id AND student.id = :student_id
            LIMIT 1");
        $lookup->execute(['subject_id' => $subjectId, 'term_id' => $termId, 'department_id' => $departmentId, 'section_id' => $sectionId, 'student_id' => $studentId]);
        $row = $lookup->fetch();
        if (!$row) throw new DomainException('The student is not enrolled in a class managed by your department.');
        if ($row['grade_id'] && $row['status'] !== 'Department Revision') {
            throw new DomainException('This grade is already waiting for Registrar review or has been released.');
        }

        $gradeStatus = (float) $grade <= 3.0 ? 'Passed' : 'Failed';
        if ($row['grade_id']) {
            $save = $this->db->prepare("UPDATE grades SET grade = :grade, grade_status = :grade_status, status = 'Registrar Review', entered_by = :department_user_id, released_by = NULL, released_at = NULL, review_message = NULL WHERE id = :grade_id AND status = 'Department Revision'");
            $save->execute(['grade' => $grade, 'grade_status' => $gradeStatus, 'department_user_id' => $departmentUserId, 'grade_id' => $row['grade_id']]);
        } else {
            $save = $this->db->prepare("INSERT INTO grades (student_id, subject_id, term_id, grade, grade_status, status, entered_by) VALUES (:student_id, :subject_id, :term_id, :grade, :grade_status, 'Registrar Review', :department_user_id)");
            $save->execute(['student_id' => $studentId, 'subject_id' => $subjectId, 'term_id' => $termId, 'grade' => $grade, 'grade_status' => $gradeStatus, 'department_user_id' => $departmentUserId]);
        }
        $this->audit($departmentUserId, 'GRADE_SUBMITTED_REGISTRAR', 'Department submitted a grade for student ID ' . $studentId . '.');
        $notice = $row['student_no'] . ' (' . trim($row['first_name'] . ' ' . $row['last_name']) . ') has a grade for ' . $row['subject_code'] . ' waiting for review.';
        $notify = $this->db->prepare("INSERT INTO notifications (user_id, category, title, message, link) SELECT id, 'Grades', 'Grade awaiting review', :message, '/php/registrar/grades.php' FROM users WHERE role = 'Registrar' AND status = 'Active'");
        $notify->execute(['message' => $notice]);
    }

    /** Return grades awaiting Registrar approval, scoped by each student's department and section. */
    public function getRegistrarGradeQueue(): array
    {
        return $this->db->query("SELECT grade.id AS grade_id, grade.grade, grade.grade_status, grade.review_message, subject.id AS subject_id, subject.code AS subject_code, subject.name AS subject_name,
                department.id AS department_id, department.name AS department_name, section.id AS section_id, section.code AS section_code, term.academic_year, term.semester,
                student.student_no, CONCAT_WS(' ', student.first_name, student.middle_name, student.last_name) AS student_name,
                encoder.full_name AS entered_by_name
            FROM grades grade
            JOIN students student ON student.id = grade.student_id
            JOIN subjects subject ON subject.id = grade.subject_id
            JOIN programs program ON program.id = student.program_id AND program.id = subject.program_id
            JOIN departments department ON department.id = program.department_id
            JOIN terms term ON term.id = grade.term_id
            JOIN users encoder ON encoder.id = grade.entered_by
            JOIN enrollments enrollment ON enrollment.student_id = student.id AND enrollment.term_id = grade.term_id AND enrollment.status = 'Enrolled'
            JOIN sections section ON section.id = enrollment.section_id AND section.program_id = program.id
            WHERE grade.status = 'Registrar Review'
            ORDER BY grade.updated_at, student.last_name, student.first_name, subject.code")->fetchAll();
    }

    /** Release a grade to the student or return it to the department with a reason. */
    public function reviewRegistrarGrade(int $gradeId, int $registrarId, string $decision, string $message): void
    {
        if (!in_array($decision, ['release', 'return'], true)) throw new DomainException('Choose approve or return to department.');
        if ($decision === 'return' && trim($message) === '') throw new DomainException('Provide a reason for resubmitting this grade.');
        $details = $this->db->prepare("SELECT grade.entered_by, student.user_id, student.student_no, subject.code AS subject_code FROM grades grade JOIN students student ON student.id = grade.student_id JOIN subjects subject ON subject.id = grade.subject_id WHERE grade.id = :grade_id AND grade.status = 'Registrar Review'");
        $details->execute(['grade_id' => $gradeId]);
        $gradeDetails = $details->fetch();
        if (!$gradeDetails) throw new DomainException('That grade is no longer waiting for Registrar review.');
        $released = $decision === 'release';
        $status = $released ? 'Released' : 'Department Revision';
        $query = $this->db->prepare("UPDATE grades SET status = :status, released_by = IF(:release_flag = 1, :reviewer_id, NULL), released_at = IF(:release_time_flag = 1, CURRENT_TIMESTAMP, NULL), review_message = :message WHERE id = :grade_id AND status = 'Registrar Review'");
        $query->execute(['status' => $status, 'release_flag' => $released ? 1 : 0, 'reviewer_id' => $registrarId, 'release_time_flag' => $released ? 1 : 0, 'message' => $message !== '' ? trim($message) : null, 'grade_id' => $gradeId]);
        if (!$query->rowCount()) throw new DomainException('That grade is no longer waiting for Registrar review.');
        if ($released) {
            $notify = $this->db->prepare("INSERT INTO notifications (user_id, category, title, message, link) VALUES (:user_id, 'Grades', 'Grade released', :message, '/php/student/grades.php')");
            $notify->execute(['user_id' => $gradeDetails['user_id'], 'message' => 'Your grade for ' . $gradeDetails['subject_code'] . ' is now available.']);
        } else {
            $notify = $this->db->prepare("INSERT INTO notifications (user_id, category, title, message, link) VALUES (:user_id, 'Grades', 'Grade returned for revision', :message, '/php/department/grades.php')");
            $notify->execute(['user_id' => $gradeDetails['entered_by'], 'message' => $gradeDetails['student_no'] . ' grade for ' . $gradeDetails['subject_code'] . ' was returned. Reason: ' . trim($message)]);
        }
        $this->audit($registrarId, $released ? 'GRADE_REGISTRAR_RELEASED' : 'GRADE_RETURNED_TO_DEPARTMENT', 'Registrar reviewed grade #' . $gradeId . '.');
    }

    public function getStudentGrades(int $userId, ?int $termId = null): array
    {
        $sql = "SELECT grade.grade, grade.grade_status, grade.released_at, subject.code, subject.name AS subject_name,
                       subject.units, term.id AS term_id, term.academic_year, term.semester
                FROM grades grade
                INNER JOIN students student ON student.id = grade.student_id AND student.user_id = :user_id
                INNER JOIN subjects subject ON subject.id = grade.subject_id
                INNER JOIN terms term ON term.id = grade.term_id
                WHERE grade.status = 'Released'";
        $parameters = ['user_id' => $userId];
        if ($termId !== null) { $sql .= ' AND term.id = :term_id'; $parameters['term_id'] = $termId; }
        $sql .= ' ORDER BY term.id DESC, subject.code';
        $statement = $this->db->prepare($sql);
        $statement->execute($parameters);
        return $statement->fetchAll();
    }

    public function getStudentTerms(int $userId): array
    {
        $statement = $this->db->prepare("SELECT DISTINCT term.id, term.academic_year, term.semester, term.is_current FROM terms term INNER JOIN grades grade ON grade.term_id = term.id AND grade.status = 'Released' INNER JOIN students student ON student.id = grade.student_id WHERE student.user_id = :user_id ORDER BY term.id DESC");
        $statement->execute(['user_id' => $userId]);
        return $statement->fetchAll();
    }

    private function validateGrade(mixed $value): string
    {
        if (!is_scalar($value) || !preg_match('/^(?:[1-4](?:\.\d{1,2})?|5(?:\.0{1,2})?)$/', (string) $value)) {
            throw new DomainException('Grades must be numeric values from 1.00 through 5.00.');
        }
        return number_format((float) $value, 2, '.', '');
    }

    private function audit(int $userId, string $action, string $details): void
    {
        $statement = $this->db->prepare('INSERT INTO audit_logs (user_id, action, entity, details, ip_address) VALUES (:user_id, :action, \'grades\', :details, :ip_address)');
        $statement->execute(['user_id' => $userId, 'action' => $action, 'details' => $details, 'ip_address' => substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45)]);
    }
}
