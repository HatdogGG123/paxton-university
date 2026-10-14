<?php
final class AuditLog
{
    public function __construct(private PDO $db) {}

    public function getEntries(array $filters = [], int $limit = 250): array
    {
        $conditions = [];
        $parameters = [];

        if (!empty($filters['role'])) {
            $conditions[] = 'account.role = :role';
            $parameters['role'] = $filters['role'];
        }

        if (!empty($filters['action'])) {
            $conditions[] = 'audit.action LIKE :action';
            $parameters['action'] = '%' . $filters['action'] . '%';
        }

        if (!empty($filters['search'])) {
            $conditions[] = '(COALESCE(account.full_name, account.username, \'System\') LIKE :search_name
                             OR account.username LIKE :search_username OR audit.action LIKE :search_action
                             OR audit.details LIKE :search_details OR audit.ip_address LIKE :search_ip)';
            $search = '%' . $filters['search'] . '%';
            $parameters += [
                'search_name' => $search,
                'search_username' => $search,
                'search_action' => $search,
                'search_details' => $search,
                'search_ip' => $search
            ];
        }

        if (!empty($filters['date'])) {
            $conditions[] = 'DATE(audit.created_at) = :audit_date';
            $parameters['audit_date'] = $filters['date'];
        }

        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
        $limit = max(1, min(1000, $limit));

        $statement = $this->db->prepare(
            "SELECT audit.id, audit.action, audit.entity, audit.entity_id, audit.details,
                    audit.ip_address, audit.created_at,
                    COALESCE(account.full_name, account.username, 'System') AS full_name,
                    account.username, account.role
             FROM audit_logs audit LEFT JOIN users account ON account.id = audit.user_id
             $where ORDER BY audit.created_at DESC, audit.id DESC LIMIT $limit"
        );

        $statement->execute($parameters);

        return $statement->fetchAll();
    }

    public function getSummary(): array
    {
        $result = $this->db->query(
            "SELECT (SELECT COUNT(*) FROM users WHERE status = 'Active') AS active_users,
                    (SELECT COUNT(*) FROM audit_logs WHERE created_at >= CURRENT_DATE) AS today_events,
                    (SELECT COUNT(*) FROM audit_logs WHERE action = 'USER_LOGIN' AND created_at >= CURRENT_DATE) AS today_logins,
                    (SELECT COUNT(*) FROM login_attempts WHERE was_successful = 0 AND attempted_at >= CURRENT_DATE) AS failed_logins"
        )->fetch();

        return $result ?: ['active_users' => 0, 'today_events' => 0, 'today_logins' => 0, 'failed_logins' => 0];
    }
}
