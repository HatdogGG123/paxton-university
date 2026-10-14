<?php

final class StudentProfile
{
    public function __construct(private PDO $db) {}

    public function findForUser(int $userId): ?array
    {
        $statement = $this->db->prepare(
            'SELECT s.id AS student_id, s.user_id, s.student_no, s.first_name,
                    s.middle_name, s.last_name, s.email, s.contact_number, s.address,
                    s.year_level, s.student_type, s.curriculum_version,
                    p.name AS program_name, t.semester AS admission_semester,
                    t.academic_year AS admission_academic_year
             FROM students s
             INNER JOIN programs p ON p.id = s.program_id
             LEFT JOIN terms t ON t.id = s.admission_term_id
             WHERE s.user_id = :user_id
             LIMIT 1'
        );
        $statement->execute(['user_id' => $userId]);
        $profile = $statement->fetch();

        return $profile ?: null;
    }

    public function updateContact(int $userId, string $email, string $phoneNumber, string $address): void
    {
        $this->db->beginTransaction();
        try {
            $duplicateEmail = $this->db->prepare('SELECT 1 FROM users WHERE email = :email AND id <> :user_id UNION ALL SELECT 1 FROM admission_applications WHERE email = :application_email AND (applicant_user_id IS NULL OR applicant_user_id <> :application_user_id) LIMIT 1');
            $duplicateEmail->execute(['email' => mb_strtolower($email), 'user_id' => $userId, 'application_email' => mb_strtolower($email), 'application_user_id' => $userId]);

            if ($duplicateEmail->fetchColumn()) {
                throw new DomainException('That email is already assigned to another account or application.');
            }

            $statement = $this->db->prepare(
                'UPDATE students
                 SET email = :email, contact_number = :contact_number, address = :address
                 WHERE user_id = :user_id'
            );
            $statement->execute([
                'email' => $email,
                'contact_number' => $phoneNumber,
                'address' => $address,
                'user_id' => $userId,
            ]);

            $accountEmail = $this->db->prepare('UPDATE users SET email = :email WHERE id = :user_id');
            $accountEmail->execute(['email' => mb_strtolower($email), 'user_id' => $userId]);

            if ($statement->rowCount() === 0 && $this->findForUser($userId) === null) {
                throw new RuntimeException('Student profile not found.');
            }

            $this->writeAudit($userId, 'PROFILE_CONTACT_UPDATED', 'Student contact information updated.');
            $this->db->commit();
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }
    }


    public function changePassword(int $userId, string $currentPassword, string $newPassword): bool
    {
        $this->db->beginTransaction();
        try {
            $select = $this->db->prepare('SELECT password_hash FROM users WHERE id = :user_id FOR UPDATE');
            $select->execute(['user_id' => $userId]);

            $currentHash = $select->fetchColumn();
            if (!is_string($currentHash) || !password_verify($currentPassword, $currentHash)) {
                $this->db->rollBack();
                return false;
            }

            $update = $this->db->prepare(
                'UPDATE users
                 SET password_hash = :password_hash, password_changed_at = CURRENT_TIMESTAMP,
                     must_change_password = 0
                 WHERE id = :user_id'
            );
            $update->execute([
                'password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
                'user_id' => $userId,
            ]);

            $this->writeAudit($userId, 'PASSWORD_CHANGED', 'User changed their password.');
            $this->db->commit();

            return true;
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $exception;
        }
    }


    private function writeAudit(int $userId, string $action, string $details): void
    {
        $statement = $this->db->prepare(
            'INSERT INTO audit_logs (user_id, action, entity, details, ip_address)
             VALUES (:user_id, :action, \'Student\', :details, :ip_address)'
        );
        $statement->execute([
            'user_id' => $userId,
            'action' => $action,
            'details' => $details,
            'ip_address' => substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45),
        ]);
    }
}
