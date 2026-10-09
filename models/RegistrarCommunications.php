<?php
/** Registrar broadcasts and direct student messages. */
final class RegistrarCommunications
{
    public function __construct(private PDO $db)
    {
    }

    public function publishAnnouncement(int $registrarId, string $title, string $body, string $audience): int
    {
        $title = trim($title);
        $body = trim($body);
        if ($title === '' || strlen($title) > 150) { throw new DomainException('Enter an announcement title of 1 to 150 characters.'); }
        if ($body === '' || strlen($body) > 60000) { throw new DomainException('Enter announcement details (up to 60,000 characters).'); }
        if (!in_array($audience, ['All', 'Students'], true)) { throw new DomainException('Choose a valid announcement audience.'); }
        $this->db->beginTransaction();
        try {
            $statement = $this->db->prepare('INSERT INTO announcements (title, body, audience, posted_by) VALUES (:title, :body, :audience, :posted_by)');
            $statement->execute(['title' => $title, 'body' => $body, 'audience' => $audience, 'posted_by' => $registrarId]);
            $id = (int) $this->db->lastInsertId();
            $recipients = $audience === 'Students' ? "role = 'Student'" : '1 = 1';
            $notify = $this->db->prepare("INSERT INTO notifications (user_id, category, title, message) SELECT id, 'Announcements', :title, :message FROM users WHERE status = 'Active' AND $recipients");
            $notify->execute(['title' => $title, 'message' => mb_substr($body, 0, 500)]);
            $this->audit($registrarId, 'ANNOUNCEMENT_PUBLISHED', $id, $title . ' published to ' . $audience . '.');
            $this->db->commit();
            return $id;
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $exception;
        }
    }

    public function sendDirectMessage(int $registrarId, string $studentNo, string $category, string $title, string $body): array
    {
        $studentNo = trim($studentNo);
        $title = trim($title);
        $body = trim($body);
        if ($studentNo === '' || $title === '' || $body === '') { throw new DomainException('Complete the student number, subject, and message.'); }
        if (strlen($title) > 150 || strlen($body) > 450) { throw new DomainException('The subject or message is too long.'); }
        $categories = ['Clearance' => 'Clearance Notice', 'Requests' => 'Document Request Update', 'Enrollment' => 'Enrollment Assessment Alert', 'System' => 'General Notice'];
        if (!isset($categories[$category])) { throw new DomainException('Choose a valid message category.'); }
        $lookup = $this->db->prepare('SELECT student.id, student.user_id, student.student_no, CONCAT_WS(\' \', student.first_name, student.middle_name, student.last_name) AS student_name FROM students student WHERE student.student_no = :student_no LIMIT 1');
        $lookup->execute(['student_no' => $studentNo]);
        $student = $lookup->fetch();
        if (!$student) { throw new DomainException('No student was found with that student number.'); }
        $message = '[' . $categories[$category] . '] ' . $body;
        $insert = $this->db->prepare("INSERT INTO notifications (user_id, category, title, message, link) VALUES (:user_id, 'Announcements', :title, :message, '/php/student/notifications.php')");
        $insert->execute(['user_id' => (int) $student['user_id'], 'title' => $title, 'message' => $message]);
        $notificationId = (int) $this->db->lastInsertId();
        $this->audit($registrarId, 'STUDENT_MESSAGE_SENT', $notificationId, 'Direct message sent to ' . $student['student_no'] . ' (' . trim($student['student_name']) . '): ' . $title . '.');
        return ['message_id' => $notificationId, 'student_no' => (string) $student['student_no'], 'student_name' => trim((string) $student['student_name'])];
    }

    public function deactivateAnnouncement(int $registrarId, int $announcementId): void
    {
        $statement = $this->db->prepare('UPDATE announcements SET is_active = 0 WHERE id = :id AND is_active = 1');
        $statement->execute(['id' => $announcementId]);
        if ($statement->rowCount() === 0) { throw new DomainException('That announcement is no longer active. Refresh the page and try again.'); }
        $this->audit($registrarId, 'ANNOUNCEMENT_DEACTIVATED', $announcementId, 'Announcement #' . $announcementId . ' was deactivated.');
    }

    public function getAnnouncements(int $limit = 100): array
    {
        $limit = max(1, min(250, $limit));
        return $this->db->query("SELECT announcement.id, announcement.title, announcement.body, announcement.audience, announcement.created_at, announcement.is_active, account.username AS posted_by FROM announcements announcement INNER JOIN users account ON account.id = announcement.posted_by ORDER BY announcement.is_active DESC, announcement.created_at DESC, announcement.id DESC LIMIT $limit")->fetchAll();
    }

    public function getDirectMessages(int $limit = 100): array
    {
        $limit = max(1, min(250, $limit));
        $statement = $this->db->query("SELECT notification.id, notification.title, notification.message, notification.is_read, notification.created_at, student.student_no, CONCAT_WS(' ', student.first_name, student.middle_name, student.last_name) AS student_name FROM notifications notification INNER JOIN students student ON student.user_id = notification.user_id WHERE notification.category = 'Announcements' AND SUBSTRING(notification.message, 1, 1) = '[' ORDER BY notification.created_at DESC, notification.id DESC LIMIT $limit");
        return $statement->fetchAll();
    }

    public function getCounts(): array
    {
        $announcementCount = (int) $this->db->query('SELECT COUNT(*) FROM announcements WHERE is_active = 1')->fetchColumn();
        $messageCount = (int) $this->db->query("SELECT COUNT(*) FROM notifications WHERE category = 'Announcements' AND SUBSTRING(message, 1, 1) = '['")->fetchColumn();
        return ['announcements' => $announcementCount, 'messages' => $messageCount];
    }

    private function audit(int $userId, string $action, int $entityId, string $details): void
    {
        $statement = $this->db->prepare("INSERT INTO audit_logs (user_id, action, entity, entity_id, details, ip_address) VALUES (:user_id, :action, 'announcements', :entity_id, :details, :ip_address)");
        $statement->execute(['user_id' => $userId, 'action' => $action, 'entity_id' => $entityId, 'details' => $details, 'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null]);
    }
}
