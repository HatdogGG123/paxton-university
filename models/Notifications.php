<?php
/** User-scoped notification inbox operations. */
final class Notifications
{
    public function __construct(private PDO $db)
    {
    }

    public function getForUser(int $userId, int $limit = 100): array
    {
        $limit = max(1, min(250, $limit));
        $statement = $this->db->prepare(
            "SELECT id, category, title, message, link, is_read, created_at
             FROM notifications WHERE user_id = :user_id
             ORDER BY is_read ASC, created_at DESC, id DESC LIMIT $limit"
        );
        $statement->execute(['user_id' => $userId]);
        return $statement->fetchAll();
    }

    public function getRecentForUser(int $userId, int $limit = 5): array
    {
        $limit = max(1, min(20, $limit));
        $statement = $this->db->prepare(
            "SELECT id, category, title, message, link, is_read, created_at
             FROM notifications WHERE user_id = :user_id
             ORDER BY created_at DESC, id DESC LIMIT $limit"
        );
        $statement->execute(['user_id' => $userId]);
        return $statement->fetchAll();
    }

    public function getUnreadCount(int $userId): int
    {
        $statement = $this->db->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = :user_id AND is_read = 0');
        $statement->execute(['user_id' => $userId]);
        return (int) $statement->fetchColumn();
    }

    /** Backfill alerts for active cashier queue items, including requests that predate event notifications. */
    public function syncCashierPaymentQueue(int $userId): void
    {
        $statement = $this->db->prepare(
            "INSERT INTO notifications (user_id, category, title, message, link)
             SELECT :user_id_insert, 'Payments',
                    CASE WHEN payment.status = 'Pending Verification'
                         THEN 'Payment submission needs verification'
                         ELSE 'Document request ready for payment' END,
                    CONCAT(request.request_no, ' is ready for cashier processing. Amount due: ₱', FORMAT(payment.amount, 2), '.'),
                    '/php/cashier/payments.php'
             FROM document_requests request
             INNER JOIN payments payment ON payment.request_id = request.id
             WHERE request.status = 'For Payment'
               AND payment.status IN ('Unpaid','Rejected','Pending Verification')
               AND NOT EXISTS (
                   SELECT 1 FROM notifications existing
                   WHERE existing.user_id = :user_id_exists
                     AND existing.category = 'Payments'
                     AND existing.title = CASE WHEN payment.status = 'Pending Verification'
                                               THEN 'Payment submission needs verification'
                                               ELSE 'Document request ready for payment' END
                     AND existing.message LIKE CONCAT('%', request.request_no, '%')
               )"
        );
        $statement->execute(['user_id_insert' => $userId, 'user_id_exists' => $userId]);
    }

    public function markRead(int $userId, int $notificationId): void
    {
        $statement = $this->db->prepare(
            'UPDATE notifications SET is_read = 1, read_at = COALESCE(read_at, CURRENT_TIMESTAMP)
             WHERE id = :id AND user_id = :user_id'
        );
        $statement->execute(['id' => $notificationId, 'user_id' => $userId]);
    }

    public function markAllRead(int $userId): void
    {
        $statement = $this->db->prepare(
            'UPDATE notifications SET is_read = 1, read_at = COALESCE(read_at, CURRENT_TIMESTAMP)
             WHERE user_id = :user_id AND is_read = 0'
        );
        $statement->execute(['user_id' => $userId]);
    }
}
