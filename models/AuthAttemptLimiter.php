<?php
final class AuthAttemptLimiter
{
    private const DEFAULT_MAX_FAILURES = 5;

    public function __construct(private PDO $db) {}

    public function status(string $identity, string $type): array
    {
        $maxFailures = $this->maxFailures();

        $key = $this->key($identity);

        $query = $this->db->prepare('SELECT failed_attempts, window_started_at, locked_until FROM auth_attempt_counters WHERE email_hash = :hash AND attempt_type = :type LIMIT 1');
        $query->execute(['hash' => $key, 'type' => $type]);

        $row = $query->fetch();

        if (!$row) {
            return ['remaining' => $maxFailures, 'locked' => false, 'retry_after' => 0];
        }

        $retryAfter = !empty($row['locked_until']) ? max(0, strtotime($row['locked_until']) - time()) : 0;

        if ($retryAfter > 0) {
            return ['remaining' => 0, 'locked' => true, 'retry_after' => $retryAfter];
        }

        if (strtotime($row['window_started_at']) < time() - ($this->lockoutMinutes() * 60)) {
            return ['remaining' => $maxFailures, 'locked' => false, 'retry_after' => 0];
        }

        $remaining = max(0, $maxFailures - (int) $row['failed_attempts']);

        return ['remaining' => $remaining, 'locked' => $remaining === 0, 'retry_after' => 0];
    }

    public function fail(string $identity, string $type): array
    {
        $key = $this->key($identity);
        $minutes = $this->lockoutMinutes();
        $maxFailures = $this->maxFailures();

        $this->db->beginTransaction();

        try {
            $create = $this->db->prepare('INSERT IGNORE INTO auth_attempt_counters (email_hash, attempt_type, failed_attempts, window_started_at) VALUES (:hash, :type, 0, NOW())');
            $create->execute(['hash' => $key, 'type' => $type]);

            $read = $this->db->prepare('SELECT failed_attempts, window_started_at, locked_until FROM auth_attempt_counters WHERE email_hash = :hash AND attempt_type = :type FOR UPDATE');
            $read->execute(['hash' => $key, 'type' => $type]);

            $row = $read->fetch();

            if (!empty($row['locked_until']) && strtotime($row['locked_until']) > time()) {
                $this->db->commit();
                return ['remaining' => 0, 'locked' => true, 'retry_after' => strtotime($row['locked_until']) - time()];
            }

            if (strtotime($row['window_started_at']) < time() - ($minutes * 60)) {
                $count = 0;
                $started = date('Y-m-d H:i:s');
            } else {
                $count = (int) $row['failed_attempts'];
                $started = $row['window_started_at'];
            }

            $count++;
            $lockedUntil = $count >= $maxFailures ? date('Y-m-d H:i:s', time() + ($minutes * 60)) : null;

            $update = $this->db->prepare('UPDATE auth_attempt_counters SET failed_attempts = :count, window_started_at = :started, locked_until = :locked_until WHERE email_hash = :hash AND attempt_type = :type');
            $update->execute(['count' => $count, 'started' => $started, 'locked_until' => $lockedUntil, 'hash' => $key, 'type' => $type]);

            $this->db->commit();

            return [
                'remaining' => max(0, $maxFailures - $count),
                'locked' => $count >= $maxFailures,
                'retry_after' => $count >= $maxFailures ? $minutes * 60 : 0
            ];
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $exception;
        }
    }

    public function clear(string $identity, string $type): void
    {
        $query = $this->db->prepare('DELETE FROM auth_attempt_counters WHERE email_hash = :hash AND attempt_type = :type');
        $query->execute(['hash' => $this->key($identity), 'type' => $type]);
    }

    public static function message(string $action, array $state, int $maxFailures = self::DEFAULT_MAX_FAILURES): string
    {
        if (!empty($state['locked'])) {
            $seconds = max(1, (int) ($state['retry_after'] ?? 0));
            $minutes = intdiv($seconds, 60);

            $remainingSeconds = $seconds % 60;
            $duration = [];

            if ($minutes > 0) {
                $duration[] = $minutes . ' minute' . ($minutes === 1 ? '' : 's');
            }

            if ($remainingSeconds > 0) {
                $duration[] = $remainingSeconds . ' second' . ($remainingSeconds === 1 ? '' : 's');
            }

            return 'Maximum of ' . $maxFailures . ' ' . $action . ' attempts reached (' . $maxFailures . ' of ' . $maxFailures . ' used). Try again in ' . implode(' and ', $duration) . '.';
        }

        $remaining = min($maxFailures, max(0, (int) ($state['remaining'] ?? $maxFailures)));

        $used = $maxFailures - $remaining;

        return $action . ' failed. ' . $used . ' of ' . $maxFailures . ' attempts used; ' . $remaining . ' remaining.';
    }

    private function key(string $identity): string
    {
        return hash('sha256', mb_strtolower(trim($identity)));
    }

    private function lockoutMinutes(): int
    {
        try {
            $query = $this->db->query("SELECT setting_value FROM settings WHERE setting_key = 'lockout_minutes' LIMIT 1");
            $value = $query->fetchColumn();
            return $value !== false && ctype_digit((string) $value) ? max(1, (int) $value) : 15;
        } catch (Throwable) {
            return 15;
        }
    }

    public function maxFailures(): int
    {
        try {
            $query = $this->db->query("SELECT setting_value FROM settings WHERE setting_key = 'login_attempt_limit' LIMIT 1");
            $value = $query->fetchColumn();
            return $value !== false && ctype_digit((string) $value) ? min(10, max(1, (int) $value)) : self::DEFAULT_MAX_FAILURES;
        } catch (Throwable) {
            return self::DEFAULT_MAX_FAILURES;
        }
    }
}
