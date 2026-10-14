<?php

final class Enrollment
{
    private const SECTION_CAPACITY = 50;

    public function __construct(private PDO $db) {}

    public function getStudentContext(int $userId): ?array
    {
        $statement = $this->db->prepare(
            'SELECT s.id AS student_id, s.user_id, s.student_no, s.first_name, s.middle_name,
                    s.last_name, s.program_id, p.name AS program_name, s.year_level
             FROM students s
             INNER JOIN programs p ON p.id = s.program_id
             WHERE s.user_id = :user_id LIMIT 1'
        );
        $statement->execute(['user_id' => $userId]);

        $student = $statement->fetch();

        return $student ?: null;
    }

    public function getCurrentTerm(): ?array
    {
        $statement = $this->db->query(
            'SELECT id, academic_year, semester, enrollment_open_at, enrollment_close_at FROM terms WHERE is_current = 1 LIMIT 1'
        );

        $term = $statement->fetch();

        return $term ?: null;
    }

    public function getUnitLimit(): int
    {
        $statement = $this->db->prepare("SELECT setting_value FROM settings WHERE setting_key = 'max_units_per_enrollment' LIMIT 1");
        $statement->execute();

        $limit = $statement->fetchColumn();

        return $limit !== false && ctype_digit((string) $limit) ? max(1, (int) $limit) : 24;
    }

    public function getAvailableSubjects(int $programId, int $yearLevel, string $semester): array
    {
        $statement = $this->db->prepare(
            'SELECT s.id, s.code, s.name, s.units, s.year_level, s.semester,
                    GROUP_CONCAT(DISTINCT prerequisite.code ORDER BY prerequisite.code SEPARATOR \' , \') AS prerequisites
             FROM subjects s
             LEFT JOIN subject_prerequisites sp ON sp.subject_id = s.id
             LEFT JOIN subjects prerequisite ON prerequisite.id = sp.prerequisite_id
             WHERE s.program_id = :program_id AND s.year_level = :year_level
               AND s.semester = :semester AND s.is_active = 1
             GROUP BY s.id, s.code, s.name, s.units, s.year_level, s.semester
             ORDER BY s.code'
        );
        $statement->execute([
            'program_id' => $programId,
            'year_level' => $yearLevel,
            'semester' => $semester,
        ]);

        return $statement->fetchAll();
    }

    public function getLatestStudentRequest(int $studentId, int $termId): ?array
    {
        $statement = $this->db->prepare(
            'SELECT e.id, e.enrollment_no, e.term_id, e.year_level, e.status, section.code AS section_code,
                    e.rejection_reason, e.submitted_at, e.reviewed_at,
                    COALESCE(u.full_name, CONCAT_WS(\' \', s.first_name, s.middle_name, s.last_name)) AS student_name,
                    s.student_no, p.name AS program_name
             FROM enrollments e
             INNER JOIN students s ON s.id = e.student_id
             INNER JOIN programs p ON p.id = s.program_id
             LEFT JOIN sections section ON section.id = e.section_id
             LEFT JOIN users u ON u.id = s.user_id
             WHERE e.student_id = :student_id AND e.term_id = :term_id
             ORDER BY e.submitted_at DESC, e.id DESC LIMIT 1'
        );
        $statement->execute(['student_id' => $studentId, 'term_id' => $termId]);

        $request = $statement->fetch();
        if (!$request) {
            return null;
        }

        $request['subjects'] = $this->getEnrollmentSubjects((int) $request['id']);
        $request['total_units'] = array_sum(array_map(static fn(array $subject): float => (float) $subject['units'], $request['subjects']));

        return $request;
    }

    public function getEnrollmentSubjects(int $enrollmentId): array
    {
        return $this->getEnrollmentSubjectsForRequests([$enrollmentId])[$enrollmentId] ?? [];
    }

    public function getEligibleSubjectsForRequest(int $enrollmentId): array
    {
        return $this->getEligibleSubjectsForRequests([$enrollmentId])[$enrollmentId] ?? [];
    }

    private function getEnrollmentSubjectsForRequests(array $enrollmentIds): array
    {
        $enrollmentIds = array_values(array_unique(array_filter(
            array_map('intval', $enrollmentIds),
            static fn(int $id): bool => $id > 0
        )));
        if ($enrollmentIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($enrollmentIds), '?'));
        $statement = $this->db->prepare(
            "SELECT es.enrollment_id, subject.id, subject.code, subject.name,
                    subject.units, subject.semester, subject.year_level
             FROM enrollment_subjects es
             INNER JOIN subjects subject ON subject.id = es.subject_id
             WHERE es.enrollment_id IN ($placeholders)
             ORDER BY es.enrollment_id, subject.code"
        );
        $statement->execute($enrollmentIds);

        $subjectsByRequest = [];
        foreach ($statement->fetchAll() as $subject) {
            $enrollmentId = (int) $subject['enrollment_id'];
            unset($subject['enrollment_id']);
            $subjectsByRequest[$enrollmentId][] = $subject;
        }
        return $subjectsByRequest;
    }

    private function getEligibleSubjectsForRequests(array $enrollmentIds): array
    {
        $enrollmentIds = array_values(array_unique(array_filter(
            array_map('intval', $enrollmentIds),
            static fn(int $id): bool => $id > 0
        )));
        if ($enrollmentIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($enrollmentIds), '?'));
        $statement = $this->db->prepare(
            "SELECT e.id AS enrollment_id, subject.id, subject.code, subject.name, subject.units,
                    subject.semester,
                    CASE WHEN selected.subject_id IS NULL THEN 0 ELSE 1 END AS is_selected,
                    GROUP_CONCAT(DISTINCT prerequisite.code ORDER BY prerequisite.code SEPARATOR ' , ') AS prerequisites
             FROM enrollments e
             INNER JOIN students student ON student.id = e.student_id
             INNER JOIN terms term ON term.id = e.term_id AND term.is_current = 1
             INNER JOIN subjects subject ON subject.program_id = student.program_id
                    AND subject.year_level = e.year_level AND subject.semester = term.semester
                    AND subject.is_active = 1
             LEFT JOIN enrollment_subjects selected ON selected.enrollment_id = e.id AND selected.subject_id = subject.id
             LEFT JOIN subject_prerequisites sp ON sp.subject_id = subject.id
             LEFT JOIN subjects prerequisite ON prerequisite.id = sp.prerequisite_id
             WHERE e.id IN ($placeholders) AND e.status = 'Under Review'
             GROUP BY e.id, subject.id, subject.code, subject.name, subject.units, subject.semester, selected.subject_id
             ORDER BY e.id, subject.code"
        );
        $statement->execute($enrollmentIds);

        $subjectsByRequest = [];

        foreach ($statement->fetchAll() as $subject) {
            $enrollmentId = (int) $subject['enrollment_id'];
            unset($subject['enrollment_id']);
            $subjectsByRequest[$enrollmentId][] = $subject;
        }

        return $subjectsByRequest;
    }

    public function submitRequest(int $userId, int $yearLevel, array $subjectIds): string
    {
        $this->db->beginTransaction();
        try {
            $term = $this->db->query('SELECT id, academic_year, semester, enrollment_open_at, enrollment_close_at FROM terms WHERE is_current = 1 LIMIT 1 FOR UPDATE')->fetch();
            if (!$term) {
                throw new DomainException('There is no current enrollment term. Please contact the Registrar.');
            }

            $now = new DateTimeImmutable('now');
            if (empty($term['enrollment_open_at']) || empty($term['enrollment_close_at'])) {
                throw new DomainException('The Registrar has not set the enrollment period for this term.');
            }

            if ($now < new DateTimeImmutable($term['enrollment_open_at']) || $now > new DateTimeImmutable($term['enrollment_close_at'])) {
                throw new DomainException('Enrollment is available from ' . date('M j, Y g:i A', strtotime($term['enrollment_open_at'])) . ' to ' . date('M j, Y g:i A', strtotime($term['enrollment_close_at'])) . '.');
            }

            $studentQuery = $this->db->prepare('SELECT id, program_id, year_level FROM students WHERE user_id = :user_id FOR UPDATE');
            $studentQuery->execute(['user_id' => $userId]);
            $student = $studentQuery->fetch();

            if (!$student) {
                throw new DomainException('Your student profile could not be found. Please contact the Registrar.');
            }

            if ($yearLevel < 1 || $yearLevel > (int) $student['year_level']) {
                throw new DomainException('Choose a year level within your current academic standing.');
            }

            if (count($subjectIds) === 0) {
                throw new DomainException('Select at least one subject before submitting.');
            }

            $activeQuery = $this->db->prepare(
                "SELECT id FROM enrollments
                 WHERE student_id = :student_id AND term_id = :term_id
                   AND status NOT IN ('Rejected', 'Cancelled') LIMIT 1 FOR UPDATE"
            );
            $activeQuery->execute(['student_id' => $student['id'], 'term_id' => $term['id']]);

            if ($activeQuery->fetchColumn()) {
                throw new DomainException('You already have an active enrollment request for this term.');
            }

            if ($this->findAvailableSectionId((int) $student['program_id'], $yearLevel, (int) $term['id']) === null) {
                throw new DomainException('Your enrollment request cannot be submitted because all sections for your program and year are full. Please contact the Registrar.');
            }

            $placeholders = implode(',', array_fill(0, count($subjectIds), '?'));
            $subjectsQuery = $this->db->prepare(
                "SELECT id, code, units FROM subjects
                 WHERE id IN ($placeholders) AND program_id = ? AND year_level = ?
                   AND semester = ? AND is_active = 1"
            );
            $subjectsQuery->execute(array_merge($subjectIds, [
                (int) $student['program_id'],
                $yearLevel,
                $term['semester'],
            ]));

            $subjects = $subjectsQuery->fetchAll();
            if (count($subjects) !== count($subjectIds)) {
                throw new DomainException('One or more selected subjects are not available for your program, year level, or current term. Refresh the page and try again.');
            }

            $totalUnits = array_sum(array_map(static fn(array $subject): float => (float) $subject['units'], $subjects));
            $unitLimit = $this->getUnitLimit();
            if ($totalUnits > $unitLimit) {
                throw new DomainException('Your selected subjects total ' . number_format($totalUnits, 1) . ' units. The current limit is ' . $unitLimit . ' units.');
            }

            $prerequisites = $this->getUnmetPrerequisites((int) $student['id'], $subjectIds);
            if ($prerequisites !== []) {
                throw new DomainException('Complete these prerequisites before enrolling: ' . implode(', ', $prerequisites) . '.');
            }

            $sequenceYear = (int) substr((string) $term['academic_year'], 0, 4);
            $sequence = $this->db->prepare(
                "INSERT INTO number_sequences (seq_type, seq_year, last_number)
                 VALUES ('enrollment_no', :seq_year, 1)
                 ON DUPLICATE KEY UPDATE last_number = last_number + 1"
            );

            $sequence->execute(['seq_year' => $sequenceYear]);
            $numberQuery = $this->db->prepare(
                "SELECT last_number FROM number_sequences WHERE seq_type = 'enrollment_no' AND seq_year = :seq_year"
            );

            $numberQuery->execute(['seq_year' => $sequenceYear]);
            $enrollmentNo = sprintf('ENR-%04d-%04d', $sequenceYear, (int) $numberQuery->fetchColumn());

            $insert = $this->db->prepare(
                "INSERT INTO enrollments (enrollment_no, student_id, term_id, year_level, status)
                 VALUES (:enrollment_no, :student_id, :term_id, :year_level, 'Pending')"
            );
            $insert->execute([
                'enrollment_no' => $enrollmentNo,
                'student_id' => $student['id'],
                'term_id' => $term['id'],
                'year_level' => $yearLevel,
            ]);
            $enrollmentId = (int) $this->db->lastInsertId();

            $attachSubject = $this->db->prepare(
                'INSERT INTO enrollment_subjects (enrollment_id, subject_id) VALUES (:enrollment_id, :subject_id)'
            );

            foreach ($subjectIds as $subjectId) {
                $attachSubject->execute(['enrollment_id' => $enrollmentId, 'subject_id' => $subjectId]);
            }

            $studentDetails = $this->getStudentContext($userId);
            $name = trim(implode(' ', array_filter([
                $studentDetails['first_name'],
                $studentDetails['middle_name'],
                $studentDetails['last_name'],
            ], static fn($part): bool => $part !== null && $part !== '')));

            $this->notifyRole('Registrar', 'Enrollment request submitted', $name . ' submitted enrollment request ' . $enrollmentNo . '.');
            $this->writeAudit($userId, 'ENROLLMENT_SUBMITTED', 'Enrollment request ' . $enrollmentNo . ' submitted.');
            $this->db->commit();

            return $enrollmentNo;
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            if ($exception instanceof PDOException && $exception->getCode() === '23000') {
                throw new DomainException('An active enrollment request already exists for this term.');
            }

            throw $exception;
        }
    }

    public function cancelPendingRequest(int $userId, int $enrollmentId): void
    {
        $this->db->beginTransaction();
        try {
            $statement = $this->db->prepare(
                "UPDATE enrollments e
                 INNER JOIN students s ON s.id = e.student_id
                 SET e.status = 'Cancelled'
                 WHERE e.id = :enrollment_id AND s.user_id = :user_id AND e.status = 'Pending'"
            );
            $statement->execute(['enrollment_id' => $enrollmentId, 'user_id' => $userId]);

            if ($statement->rowCount() !== 1) {
                throw new DomainException('Only your own pending enrollment request can be cancelled.');
            }

            $this->writeAudit($userId, 'ENROLLMENT_CANCELLED', 'Student cancelled enrollment request ' . $enrollmentId . '.');
            $this->notifyRole('Registrar', 'Enrollment request cancelled', 'A student cancelled a pending enrollment request.');
            $this->db->commit();
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $exception;
        }
    }

    public function updateRequestSubjects(int $enrollmentId, int $reviewerId, array $subjectIds): void
    {
        $this->db->beginTransaction();
        try {
            $currentTermId = $this->db->query('SELECT id FROM terms WHERE is_current = 1 LIMIT 1 FOR UPDATE')->fetchColumn();
            if (!$currentTermId) {
                throw new DomainException('There is no active enrollment term to review.');
            }

            $requestQuery = $this->db->prepare(
                'SELECT e.status, e.year_level, e.enrollment_no, student.id AS student_id,
                        student.program_id, student.user_id
                 FROM enrollments e INNER JOIN students student ON student.id = e.student_id
                 WHERE e.id = :enrollment_id AND e.term_id = :term_id FOR UPDATE'
            );
            $requestQuery->execute(['enrollment_id' => $enrollmentId, 'term_id' => $currentTermId]);

            $request = $requestQuery->fetch();
            if (!$request || $request['status'] !== 'Under Review') {
                throw new DomainException('Only a request under review can have its subject list changed.');
            }

            if ($subjectIds === []) {
                throw new DomainException('Keep at least one subject in the final enrollment load.');
            }

            $termQuery = $this->db->prepare('SELECT semester FROM terms WHERE id = :term_id');
            $termQuery->execute(['term_id' => $currentTermId]);

            $semester = $termQuery->fetchColumn();
            $placeholders = implode(',', array_fill(0, count($subjectIds), '?'));

            $subjectsQuery = $this->db->prepare(
                "SELECT id, units FROM subjects
                 WHERE id IN ($placeholders) AND program_id = ? AND year_level = ?
                   AND semester = ? AND is_active = 1"
            );
            $subjectsQuery->execute(array_merge($subjectIds, [
                (int) $request['program_id'],
                (int) $request['year_level'],
                $semester,
            ]));

            $subjects = $subjectsQuery->fetchAll();
            if (count($subjects) !== count($subjectIds)) {
                throw new DomainException('One or more selected subjects are not valid for this student and term.');
            }

            $totalUnits = array_sum(array_map(static fn(array $subject): float => (float) $subject['units'], $subjects));
            $unitLimit = $this->getUnitLimit();
            if ($totalUnits > $unitLimit) {
                throw new DomainException('The final subject load exceeds the ' . $unitLimit . ' unit limit.');
            }

            $unmet = $this->getUnmetPrerequisites((int) $request['student_id'], $subjectIds);
            if ($unmet !== []) {
                throw new DomainException('These subjects still have unmet prerequisites: ' . implode(', ', $unmet) . '.');
            }

            $delete = $this->db->prepare('DELETE FROM enrollment_subjects WHERE enrollment_id = :enrollment_id');
            $delete->execute(['enrollment_id' => $enrollmentId]);

            $insert = $this->db->prepare('INSERT INTO enrollment_subjects (enrollment_id, subject_id) VALUES (:enrollment_id, :subject_id)');

            foreach ($subjectIds as $subjectId) {
                $insert->execute(['enrollment_id' => $enrollmentId, 'subject_id' => $subjectId]);
            }

            $notification = $this->db->prepare(
                "INSERT INTO notifications (user_id, category, title, message)
                 VALUES (:user_id, 'Enrollment', :title, :message)"
            );
            $notification->execute([
                'user_id' => $request['user_id'],
                'title' => 'Enrollment subject list updated',
                'message' => 'The Registrar updated the subject list for request ' . $request['enrollment_no'] . '.',
            ]);

            $this->writeAudit($reviewerId, 'ENROLLMENT_SUBJECTS_UPDATED', 'Final subject list updated for ' . $request['enrollment_no'] . '.');
            $this->db->commit();
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $exception;
        }
    }

    /** Return the active-term enrollment queue for Registrar review. */
    public function getRegistrarQueue(int $termId, ?string $status = null): array
    {
        $sql =
            'SELECT e.id, e.enrollment_no, e.year_level, e.status, e.rejection_reason,
                    e.submitted_at, e.reviewed_at, s.student_no, s.user_id,
                    CONCAT_WS(\' \', s.first_name, s.middle_name, s.last_name) AS student_name,
                    p.name AS program_name, COUNT(es.id) AS subject_count,
                    COALESCE(SUM(sub.units), 0) AS total_units
             FROM enrollments e
             INNER JOIN students s ON s.id = e.student_id
             INNER JOIN programs p ON p.id = s.program_id
             LEFT JOIN enrollment_subjects es ON es.enrollment_id = e.id
             LEFT JOIN subjects sub ON sub.id = es.subject_id
             WHERE e.term_id = :term_id';

        $parameters = ['term_id' => $termId];

        if ($status !== null && in_array($status, ['Pending', 'Under Review', 'Approved', 'Rejected', 'Enrolled', 'Cancelled'], true)) {
            $sql .= ' AND e.status = :status';
            $parameters['status'] = $status;
        }

        $sql .= ' GROUP BY e.id, e.enrollment_no, e.year_level, e.status, e.rejection_reason,
                           e.submitted_at, e.reviewed_at, s.student_no, s.user_id,
                           s.first_name, s.middle_name, s.last_name, p.name
                  ORDER BY e.submitted_at ASC, e.id ASC';

        $statement = $this->db->prepare($sql);
        $statement->execute($parameters);

        $requests = $statement->fetchAll();
        $requestIds = array_map(static fn(array $request): int => (int) $request['id'], $requests);

        $subjectsByRequest = $this->getEnrollmentSubjectsForRequests($requestIds);

        $underReviewIds = array_map(
            static fn(array $request): int => (int) $request['id'],
            array_filter($requests, static fn(array $request): bool => $request['status'] === 'Under Review')
        );

        $eligibleByRequest = $this->getEligibleSubjectsForRequests($underReviewIds);

        foreach ($requests as &$request) {
            $requestId = (int) $request['id'];
            $request['subjects'] = $subjectsByRequest[$requestId] ?? [];
            $request['eligible_subjects'] = $eligibleByRequest[$requestId] ?? [];
        }

        unset($request);

        return $requests;
    }

    public function reviewRequest(int $enrollmentId, int $reviewerId, string $newStatus, string $reason = ''): array
    {
        $this->db->beginTransaction();
        try {
            $currentTermId = $this->db->query('SELECT id FROM terms WHERE is_current = 1 LIMIT 1 FOR UPDATE')->fetchColumn();
            if (!$currentTermId) {
                throw new DomainException('There is no active enrollment term to review.');
            }

            $select = $this->db->prepare(
                'SELECT e.status, e.enrollment_no, e.year_level, s.user_id, s.program_id
                 FROM enrollments e INNER JOIN students s ON s.id = e.student_id
                 WHERE e.id = :enrollment_id AND e.term_id = :term_id FOR UPDATE'
            );
            $select->execute(['enrollment_id' => $enrollmentId, 'term_id' => $currentTermId]);

            $request = $select->fetch();
            if (!$request) {
                throw new DomainException('Enrollment request not found.');
            }

            $current = $request['status'];
            $allowed = [
                'Under Review' => ['Pending'],
                'Approved' => ['Under Review'],
                'Rejected' => ['Pending', 'Under Review'],
                'Enrolled' => ['Approved'],
                'Cancelled' => ['Approved'],
            ];

            if (!isset($allowed[$newStatus]) || !in_array($current, $allowed[$newStatus], true)) {
                throw new DomainException('That action is not available for the current request status. Refresh the page and try again.');
            }

            if ($newStatus === 'Rejected' && (trim($reason) === '' || strlen($reason) > 500)) {
                throw new DomainException('Enter a rejection reason of no more than 500 characters.');
            }

            $sectionId = null;
            if ($newStatus === 'Enrolled') {
                $sectionId = $this->findAvailableSectionId((int) $request['program_id'], (int) $request['year_level'], $currentTermId);
                if ($sectionId === null) {
                    $newStatus = 'Cancelled';
                    $reason = 'Enrollment cancelled because all sections for this program and year have reached the 50-student capacity.';
                }
            }

            $update = $this->db->prepare(
                'UPDATE enrollments
                 SET status = :status, section_id = :section_id, rejection_reason = :reason, reviewed_by = :reviewed_by,
                     reviewed_at = CURRENT_TIMESTAMP,
                     enrolled_at = CASE WHEN :is_enrolled = 1 THEN CURRENT_TIMESTAMP ELSE enrolled_at END
                 WHERE id = :enrollment_id'
            );
            $update->execute([
                'status' => $newStatus,
                'section_id' => $sectionId,
                'reason' => in_array($newStatus, ['Rejected', 'Cancelled'], true) ? trim($reason) : null,
                'reviewed_by' => $reviewerId,
                'is_enrolled' => $newStatus === 'Enrolled' ? 1 : 0,
                'enrollment_id' => $enrollmentId,
            ]);

            $message = match ($newStatus) {
                'Under Review' => 'Your enrollment request ' . $request['enrollment_no'] . ' is under review.',
                'Approved' => 'Your enrollment request ' . $request['enrollment_no'] . ' was approved. The Registrar will confirm your final enrollment.',
                'Rejected' => 'Your enrollment request ' . $request['enrollment_no'] . ' was rejected: ' . trim($reason),
                'Enrolled' => 'Your enrollment request ' . $request['enrollment_no'] . ' is confirmed as enrolled.',
                'Cancelled' => 'Your enrollment request ' . $request['enrollment_no'] . ' was cancelled. ' . trim($reason),
            };

            $notification = $this->db->prepare(
                "INSERT INTO notifications (user_id, category, title, message)
                 VALUES (:user_id, 'Enrollment', :title, :message)"
            );
            $notification->execute([
                'user_id' => $request['user_id'],
                'title' => 'Enrollment status updated',
                'message' => $message,
            ]);

            $this->writeAudit($reviewerId, 'ENROLLMENT_STATUS_CHANGED', $request['enrollment_no'] . ' changed from ' . $current . ' to ' . $newStatus . '.');
            $this->db->commit();

            return [
                'status' => $newStatus,
                'message' => $newStatus === 'Cancelled'
                    ? 'Enrollment was cancelled because no section has space available. The student was notified.'
                    : 'Enrollment request updated and the student was notified.',
            ];
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }
    }

    private function findAvailableSectionId(int $programId, int $yearLevel, int $termId): ?int
    {
        $sectionsQuery = $this->db->prepare(
            'SELECT id FROM sections
             WHERE program_id = :program_id AND year_level = :year_level AND is_active = 1
             ORDER BY code, id FOR UPDATE'
        );
        $sectionsQuery->execute(['program_id' => $programId, 'year_level' => $yearLevel]);

        $sections = $sectionsQuery->fetchAll(PDO::FETCH_COLUMN);
        if (!$sections) {
            return null;
        }

        $countQuery = $this->db->prepare(
            "SELECT COUNT(*) FROM enrollments
             WHERE section_id = :section_id AND term_id = :term_id AND status = 'Enrolled'"
        );

        foreach ($sections as $sectionId) {
            $countQuery->execute(['section_id' => (int) $sectionId, 'term_id' => $termId]);
            if ((int) $countQuery->fetchColumn() < self::SECTION_CAPACITY) {
                return (int) $sectionId;
            }
        }

        return null;
    }

    private function getUnmetPrerequisites(int $studentId, array $subjectIds): array
    {
        if ($subjectIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($subjectIds), '?'));

        $query = $this->db->prepare(
            "SELECT DISTINCT prerequisite.id, prerequisite.code
             FROM subject_prerequisites sp
             INNER JOIN subjects prerequisite ON prerequisite.id = sp.prerequisite_id
             WHERE sp.subject_id IN ($placeholders)"
        );
        $query->execute($subjectIds);

        $prerequisites = $query->fetchAll();

        $unmet = [];
        $grade = $this->db->prepare(
            "SELECT COUNT(*) FROM grades
             WHERE student_id = :student_id AND subject_id = :subject_id
               AND status = 'Released' AND grade_status = 'Passed'"
        );

        foreach ($prerequisites as $prerequisite) {
            $grade->execute(['student_id' => $studentId, 'subject_id' => $prerequisite['id']]);
            if ((int) $grade->fetchColumn() === 0) {
                $unmet[] = $prerequisite['code'];
            }
        }

        return $unmet;
    }

    private function notifyRole(string $role, string $title, string $message): void
    {
        $statement = $this->db->prepare(
            "INSERT INTO notifications (user_id, category, title, message)
             SELECT id, 'Enrollment', :title, :message
             FROM users WHERE role = :role AND status = 'Active'"
        );
        $statement->execute(['title' => $title, 'message' => $message, 'role' => $role]);
    }

    private function writeAudit(int $userId, string $action, string $details): void
    {
        $statement = $this->db->prepare(
            'INSERT INTO audit_logs (user_id, action, entity, details, ip_address)
             VALUES (:user_id, :action, \'Enrollment\', :details, :ip_address)'
        );
        $statement->execute([
            'user_id' => $userId,
            'action' => $action,
            'details' => $details,
            'ip_address' => substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45),
        ]);
    }
}
