<?php
final class Admission
{
    public function __construct(private PDO $db) {}

    public function programs(): array
    {
        return $this->db->query(
            "SELECT program.id, program.name, program.code, program.department_id
             FROM programs program
             INNER JOIN departments department ON department.id = program.department_id
             WHERE program.is_active = 1 AND department.is_active = 1 AND department.dept_type = 'Academic'
             ORDER BY department.name, program.name"
        )->fetchAll();
    }

    public function academicDepartmentsWithPrograms(): array
    {
        return $this->db->query(
            "SELECT DISTINCT department.id, department.code, department.name
             FROM departments department
             INNER JOIN programs program ON program.department_id = department.id
             WHERE department.is_active = 1 AND department.dept_type = 'Academic' AND program.is_active = 1
             ORDER BY department.name"
        )->fetchAll();
    }

    public function emailInUse(string $email): bool
    {
        $query = $this->db->prepare('SELECT 1 FROM admission_applications WHERE email = :application_email UNION ALL SELECT 1 FROM users WHERE email = :user_email LIMIT 1');
        $normalized = mb_strtolower(trim($email));
        $query->execute(['application_email' => $normalized, 'user_email' => $normalized]);
        return (bool) $query->fetchColumn();
    }

    public function getApplicantApplication(int $userId): ?array
    {
        $query = $this->db->prepare('SELECT a.*, p.name AS program_name FROM admission_applications a JOIN programs p ON p.id = a.program_id WHERE a.applicant_user_id = :user_id LIMIT 1');
        $query->execute(['user_id' => $userId]);
        return $query->fetch() ?: null;
    }

    public function applicantDocuments(int $applicationId): array
    {
        $query = $this->db->prepare('SELECT r.id AS requirement_id, r.document_name, d.id AS document_id, d.submission_method, d.review_status, d.review_message, d.submitted_at FROM admission_document_requirements r LEFT JOIN admission_documents d ON d.requirement_id = r.id AND d.application_id = :application_id WHERE r.program_id = (SELECT program_id FROM admission_applications WHERE id = :app_id) AND r.is_active = 1 ORDER BY r.document_name');
        $query->execute(['application_id' => $applicationId, 'app_id' => $applicationId]);
        return $query->fetchAll();
    }

    public function submit(array $data, string $password): array
    {
        $this->db->beginTransaction();
        try {
            $year = (int) date('Y');
            $this->db->prepare("INSERT INTO number_sequences (seq_type, seq_year, last_number) VALUES ('application_no', :year, 1) ON DUPLICATE KEY UPDATE last_number = last_number + 1")->execute(['year' => $year]);
            $sequence = $this->db->prepare("SELECT last_number FROM number_sequences WHERE seq_type = 'application_no' AND seq_year = :year FOR UPDATE");
            $sequence->execute(['year' => $year]);
            $number = sprintf('APP-%d-%05d', $year, (int) $sequence->fetchColumn());
            $insert = $this->db->prepare('INSERT INTO admission_applications (application_no, first_name, middle_name, last_name, email, contact_number, address, program_id) VALUES (:application_no, :first_name, :middle_name, :last_name, :email, :contact_number, :address, :program_id)');
            $insert->execute($data + ['application_no' => $number]);
            $applicationId = (int) $this->db->lastInsertId();
            $account = $this->db->prepare("INSERT INTO users (username, email, password_hash, role, full_name) VALUES (:username, :email, :password_hash, 'Applicant', :full_name)");
            $account->execute(['username' => $number, 'email' => mb_strtolower($data['email']), 'password_hash' => password_hash($password, PASSWORD_DEFAULT), 'full_name' => trim($data['first_name'] . ' ' . ($data['middle_name'] ?? '') . ' ' . $data['last_name'])]);
            $applicantUserId = (int) $this->db->lastInsertId();
            $link = $this->db->prepare('UPDATE admission_applications SET applicant_user_id = :user_id WHERE id = :id');
            $link->execute(['user_id' => $applicantUserId, 'id' => $applicationId]);
            $this->notifyUser($applicantUserId, 'Application received', 'Your application ' . $number . ' was submitted. You can track its status and required documents here.', '/php/applicant/dashboard.php');
            $this->notifyRoles(['Admin', 'Registrar'], 'New application received', $number . ' was submitted by ' . trim($data['first_name'] . ' ' . ($data['middle_name'] ?? '') . ' ' . $data['last_name']) . '.', '/php/registrar/admissions.php');
            $this->db->commit();
            return ['application_no' => $number, 'password' => $password];
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $exception;
        }
    }

    public function queue(): array
    {
        return $this->db->query(
            "SELECT a.*, p.name AS program_name
             FROM admission_applications a
             JOIN programs p ON p.id = a.program_id
             WHERE a.status NOT IN ('Enrolled', 'Completed', 'Approved')
             ORDER BY a.submitted_at ASC, a.id ASC"
        )->fetchAll();
    }

    public function newApplicantCount(): int
    {
        // New applicants: still open and not yet touched by staff (no application decision and no document reviewed).
        // Uploading documents moves the status past 'Pending', so the status alone cannot identify a new applicant.
        return (int) $this->db->query(
            "SELECT COUNT(*)
             FROM admission_applications a
             WHERE a.status IN ('Pending', 'Under Review', 'Documents Required')
               AND a.reviewed_at IS NULL
               AND NOT EXISTS (SELECT 1 FROM admission_documents d WHERE d.application_id = a.id AND d.reviewed_at IS NOT NULL)"
        )->fetchColumn();
    }

    public function requirements(): array
    {
        return $this->db->query('SELECT r.*, p.name AS program_name FROM admission_document_requirements r JOIN programs p ON p.id = r.program_id WHERE r.is_active = 1 ORDER BY p.name, r.document_name')->fetchAll();
    }

    public function saveRequirement(?int $id, int $programId, string $name, int $userId): int
    {
        if ($name === '' || mb_strlen($name) > 120) throw new DomainException('Enter a document name up to 120 characters.');
        if ($id) {
            $statement = $this->db->prepare('UPDATE admission_document_requirements SET program_id = :program_id, document_name = :name, is_active = 1 WHERE id = :id');
            $statement->execute(['program_id' => $programId, 'name' => $name, 'id' => $id]);
        } else {
            $statement = $this->db->prepare('INSERT INTO admission_document_requirements (program_id, document_name, created_by) VALUES (:program_id, :name, :user_id)');
            $statement->execute(['program_id' => $programId, 'name' => $name, 'user_id' => $userId]);
            $id = (int) $this->db->lastInsertId();
        }
        return (int) $id;
    }

    public function deleteRequirement(int $id): void
    {
        $statement = $this->db->prepare('UPDATE admission_document_requirements SET is_active = 0 WHERE id = :id AND is_active = 1');
        $statement->execute(['id' => $id]);
        if ($statement->rowCount() !== 1) throw new DomainException('That active document requirement could not be found.');
    }

    public function recordSubmission(int $applicationId, int $requirementId, string $method, ?string $filePath): int
    {
        if (!in_array($method, ['Online', 'Face to Face'], true)) throw new DomainException('Choose a valid submission method.');
        $check = $this->db->prepare("SELECT d.id, d.review_status, application.application_no, application.applicant_user_id, requirement.document_name, application.first_name, application.last_name FROM admission_document_requirements requirement JOIN admission_applications application ON application.id = :app_id LEFT JOIN admission_documents d ON d.requirement_id = requirement.id AND d.application_id = application.id WHERE requirement.id = :requirement_id AND requirement.is_active = 1 AND requirement.program_id = application.program_id");
        $check->execute(['app_id' => $applicationId, 'requirement_id' => $requirementId]);
        $existing = $check->fetch();
        if (!$existing) throw new DomainException('That document is not required for this application.');
        if (($existing['review_status'] ?? null) === 'Confirmed') throw new DomainException('This document is already confirmed.');
        if ($method === 'Online' && !$filePath) throw new DomainException('Choose a document file to upload.');
        $statement = $this->db->prepare("INSERT INTO admission_documents (application_id, requirement_id, submission_method, file_path, review_status, review_message, submitted_at) VALUES (:application_id, :requirement_id, :method, :file_path, 'Pending', NULL, NOW()) ON DUPLICATE KEY UPDATE submission_method = VALUES(submission_method), file_path = VALUES(file_path), review_status = 'Pending', review_message = NULL, reviewer_id = NULL, reviewed_at = NULL, submitted_at = NOW()");
        $statement->execute(['application_id' => $applicationId, 'requirement_id' => $requirementId, 'method' => $method, 'file_path' => $filePath]);
        $documentQuery = $this->db->prepare('SELECT id FROM admission_documents WHERE application_id = :application_id AND requirement_id = :requirement_id');
        $documentQuery->execute(['application_id' => $applicationId, 'requirement_id' => $requirementId]);
        $documentId = (int) $documentQuery->fetchColumn();
        if ($documentId < 1) throw new RuntimeException('The submitted document could not be retrieved.');
        $this->syncApplicationStatus($applicationId);
        $this->notifyRoles(['Admin', 'Registrar'], 'Applicant document submitted', $existing['application_no'] . ' submitted ' . $existing['document_name'] . '.', '/php/registrar/admissions.php');
        return $documentId;
    }

    public function documents(int $applicationId): array
    {
        return $this->documentsForApplications([$applicationId])[$applicationId] ?? [];
    }

    public function documentsForApplications(array $applicationIds): array
    {
        $applicationIds = array_values(array_unique(array_filter(
            array_map('intval', $applicationIds),
            static fn(int $id): bool => $id > 0
        )));
        if ($applicationIds === []) {
            return [];
        }

        $documentsByApplication = array_fill_keys($applicationIds, []);
        $placeholders = implode(',', array_fill(0, count($applicationIds), '?'));
        $query = $this->db->prepare(
            "SELECT application.id AS application_id, requirement.id AS requirement_id,
                    requirement.document_name, document.id, document.submission_method,
                    document.file_path, document.review_status, document.review_message,
                    document.submitted_at
             FROM admission_applications application
             INNER JOIN admission_document_requirements requirement
                ON requirement.program_id = application.program_id AND requirement.is_active = 1
             LEFT JOIN admission_documents document
                ON document.requirement_id = requirement.id AND document.application_id = application.id
             WHERE application.id IN ($placeholders)
             ORDER BY application.id, requirement.document_name"
        );
        $query->execute($applicationIds);
        foreach ($query->fetchAll() as $document) {
            $applicationId = (int) $document['application_id'];
            unset($document['application_id']);
            $documentsByApplication[$applicationId][] = $document;
        }
        return $documentsByApplication;
    }

    public function reviewDocument(int $documentId, int $reviewerId, string $status, string $message): void
    {
        if (!in_array($status, ['Confirmed', 'Resubmit Needed'], true)) throw new DomainException('Choose a valid document decision.');
        if ($status === 'Resubmit Needed' && $message === '') throw new DomainException('Give the applicant instructions for resubmitting this document.');
        $details = $this->db->prepare('SELECT application.applicant_user_id, application.application_no, requirement.document_name FROM admission_documents document JOIN admission_applications application ON application.id = document.application_id JOIN admission_document_requirements requirement ON requirement.id = document.requirement_id WHERE document.id = :id');
        $details->execute(['id' => $documentId]);
        $document = $details->fetch();
        if (!$document) throw new DomainException('Document not found.');
        $query = $this->db->prepare('UPDATE admission_documents SET review_status = :status, reviewer_id = :reviewer_id, reviewed_at = NOW(), review_message = :message WHERE id = :id');
        $query->execute(['status' => $status, 'reviewer_id' => $reviewerId, 'message' => $message ?: null, 'id' => $documentId]);
        if (!$query->rowCount()) throw new DomainException('Document not found or unchanged.');
        $applicationQuery = $this->db->prepare('SELECT application_id FROM admission_documents WHERE id = :id');
        $applicationQuery->execute(['id' => $documentId]);
        $this->syncApplicationStatus((int) $applicationQuery->fetchColumn());
        $notice = 'Your ' . $document['document_name'] . ' for application ' . $document['application_no'] . ' was ' . strtolower($status) . '.';
        if ($message !== '') $notice .= ' ' . trim($message);
        $this->notifyUser((int) $document['applicant_user_id'], 'Application document reviewed', $notice, '/php/applicant/dashboard.php');
    }

    public function review(int $applicationId, int $reviewerId, string $status, string $remarks): void
    {
        if (!in_array($status, ['Under Review', 'Documents Required', 'Approved', 'Rejected'], true)) throw new DomainException('Choose a valid review decision.');
        if ($status === 'Approved') {
            $requirementCount = $this->db->prepare('SELECT COUNT(*) FROM admission_document_requirements WHERE program_id = (SELECT program_id FROM admission_applications WHERE id = :id) AND is_active = 1');
            $requirementCount->execute(['id' => $applicationId]);
            if ((int) $requirementCount->fetchColumn() === 0) throw new DomainException('Configure at least one required document for this program before approving applications.');
            $check = $this->db->prepare("SELECT COUNT(*) FROM admission_document_requirements r LEFT JOIN admission_documents d ON d.requirement_id = r.id AND d.application_id = :application_id WHERE r.program_id = (SELECT program_id FROM admission_applications WHERE id = :app_id) AND r.is_active = 1 AND (d.id IS NULL OR d.review_status <> 'Confirmed')");
            $check->execute(['application_id' => $applicationId, 'app_id' => $applicationId]);
            if ((int) $check->fetchColumn() > 0) throw new DomainException('Confirm all required documents before approving this application.');
        }
        $applicantQuery = $this->db->prepare('SELECT applicant_user_id, application_no FROM admission_applications WHERE id = :id');
        $applicantQuery->execute(['id' => $applicationId]);
        $applicant = $applicantQuery->fetch();
        if (!$applicant) throw new DomainException('Application not found.');
        $query = $this->db->prepare('UPDATE admission_applications SET status = :status, reviewer_id = :reviewer_id, reviewed_at = NOW(), remarks = :remarks WHERE id = :id');
        $query->execute(['status' => $status, 'reviewer_id' => $reviewerId, 'remarks' => $remarks ?: null, 'id' => $applicationId]);
        if (!$query->rowCount()) throw new DomainException('Application not found or unchanged.');
        $notice = 'Application ' . $applicant['application_no'] . ' is now ' . $status . '.';
        if ($remarks !== '') $notice .= ' ' . trim($remarks);
        $this->notifyUser((int) $applicant['applicant_user_id'], 'Application status updated', $notice, '/php/applicant/dashboard.php');
    }

    private function syncApplicationStatus(int $applicationId): void
    {
        $query = $this->db->prepare("UPDATE admission_applications a SET a.status = CASE WHEN EXISTS (SELECT 1 FROM admission_document_requirements r WHERE r.program_id = a.program_id AND r.is_active = 1) AND NOT EXISTS (SELECT 1 FROM admission_document_requirements r LEFT JOIN admission_documents d ON d.requirement_id = r.id AND d.application_id = a.id WHERE r.program_id = a.program_id AND r.is_active = 1 AND (d.id IS NULL OR d.review_status <> 'Confirmed')) THEN 'Under Review' ELSE 'Documents Required' END WHERE a.id = :id AND a.status NOT IN ('Approved','Rejected','Enrolled')");
        $query->execute(['id' => $applicationId]);
    }

    public function confirmApplicantApproval(int $userId): string
    {
        $this->db->beginTransaction();
        try {
            $query = $this->db->prepare("SELECT a.*, u.username FROM admission_applications a JOIN users u ON u.id = a.applicant_user_id WHERE a.applicant_user_id = :user_id AND a.status = 'Approved' FOR UPDATE");
            $query->execute(['user_id' => $userId]);
            $app = $query->fetch();
            if (!$app) throw new DomainException('Your application has not been approved yet.');
            $requirementCount = $this->db->prepare('SELECT COUNT(*) FROM admission_document_requirements WHERE program_id = :program_id AND is_active = 1');
            $requirementCount->execute(['program_id' => $app['program_id']]);
            if ((int) $requirementCount->fetchColumn() === 0) throw new DomainException('No document requirements are linked to your program yet. Contact the Registrar.');
            $check = $this->db->prepare("SELECT COUNT(*) FROM admission_document_requirements r LEFT JOIN admission_documents d ON d.requirement_id = r.id AND d.application_id = :application_id WHERE r.program_id = :program_id AND r.is_active = 1 AND (d.id IS NULL OR d.review_status <> 'Confirmed')");
            $check->execute(['application_id' => $app['id'], 'program_id' => $app['program_id']]);
            if ((int) $check->fetchColumn() > 0) throw new DomainException('All required documents must be confirmed before you can continue.');
            $year = (int) date('Y');
            $this->db->prepare("INSERT INTO number_sequences (seq_type, seq_year, last_number) VALUES ('student_no', :year, 1) ON DUPLICATE KEY UPDATE last_number = last_number + 1")->execute(['year' => $year]);
            $numberQuery = $this->db->prepare("SELECT last_number FROM number_sequences WHERE seq_type = 'student_no' AND seq_year = :year FOR UPDATE");
            $numberQuery->execute(['year' => $year]);
            $studentNo = sprintf('%04d-%05d', $year, (int) $numberQuery->fetchColumn());
            $student = $this->db->prepare('INSERT INTO students (user_id, student_no, first_name, middle_name, last_name, email, contact_number, address, program_id, year_level, student_type, admission_term_id) VALUES (:user_id, :student_no, :first_name, :middle_name, :last_name, :email, :contact_number, :address, :program_id, 1, \'Regular\', (SELECT id FROM terms WHERE is_current = 1 LIMIT 1))');
            $student->execute(['user_id' => $userId, 'student_no' => $studentNo, 'first_name' => $app['first_name'], 'middle_name' => $app['middle_name'], 'last_name' => $app['last_name'], 'email' => $app['email'], 'contact_number' => $app['contact_number'], 'address' => $app['address'], 'program_id' => $app['program_id']]);
            $this->db->prepare("UPDATE users SET role = 'Student', username = :student_no WHERE id = :id")->execute(['student_no' => $studentNo, 'id' => $userId]);
            $this->db->prepare("UPDATE admission_applications SET status = 'Enrolled' WHERE id = :id")->execute(['id' => $app['id']]);
            $this->notifyUser($userId, 'Student account activated', 'Your student account is active. Your student number is ' . $studentNo . '.', '/php/student/dashboard.php');
            $this->db->commit();
            return $studentNo;
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $exception;
        }
    }

    private function notifyUser(int $userId, string $title, string $message, string $link): void
    {
        if ($userId < 1) return;
        $statement = $this->db->prepare("INSERT INTO notifications (user_id, category, title, message, link) VALUES (:user_id, 'Account', :title, :message, :link)");
        $statement->execute(['user_id' => $userId, 'title' => mb_substr($title, 0, 150), 'message' => mb_substr($message, 0, 500), 'link' => $link]);
    }

    private function notifyRoles(array $roles, string $title, string $message, string $link): void
    {
        $placeholders = implode(',', array_fill(0, count($roles), '?'));
        $statement = $this->db->prepare("INSERT INTO notifications (user_id, category, title, message, link) SELECT id, 'Account', ?, ?, ? FROM users WHERE role IN ($placeholders) AND status = 'Active'");
        $statement->execute([mb_substr($title, 0, 150), mb_substr($message, 0, 500), $link, ...$roles]);
    }
}
