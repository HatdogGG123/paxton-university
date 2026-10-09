<?php
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/AuthAttemptLimiter.php';

/** Data access for user accounts and credential verification. */
final class User
{
    private ?string $lastLoginFailureMessage = null;
    private array $lastLoginFailureState = [];
    private array $lastLoginFieldErrors = [];

    public function __construct(private PDO $db)
    {
    }

    /**
     * Return an active user when the supplied credentials are valid.
     * Student numbers work as identifiers because the seed data stores them
     * in the username column for student accounts.
     */
    public function authenticate(string $identifier, string $password): ?array
    {
        $statement = $this->db->prepare(
            'SELECT id, username, email, password_hash, role, department_id, full_name,
                    status, must_change_password
             FROM users
             WHERE username = :username OR email = :email
             LIMIT 1'
        );
        $statement->execute(['username' => $identifier, 'email' => $identifier]);

        $user = $statement->fetch();
        $attemptIdentity = trim((string) ($user['email'] ?? $identifier));
        $sessionIdentity = 'session:' . (session_id() ?: 'anonymous');
        $limiter = new AuthAttemptLimiter($this->db);
        $accountAttemptState = $limiter->status($attemptIdentity, 'Login');
        $sessionAttemptState = $limiter->status($sessionIdentity, 'Login');
        $attemptState = $this->combineLoginAttemptStates($accountAttemptState, $sessionAttemptState);
        $credentialsValid = $user
            && $user['status'] === 'Active'
            && password_verify($password, $user['password_hash']);

        if (!$credentialsValid) {
            if (!$user && filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
                $this->lastLoginFieldErrors = ['username' => 'This email is not registered.'];
            } elseif (!$user) {
                $this->lastLoginFieldErrors = ['username' => 'This username or student number is not registered.'];
            } elseif ($user['status'] !== 'Active') {
                $this->lastLoginFieldErrors = ['username' => 'This account is inactive.'];
            } else {
                $this->lastLoginFieldErrors = ['password' => 'Wrong password.'];
            }
        }

        if ($attemptState['locked'] && !$credentialsValid) {
            // Count wrong attempts in this session even if another session had already
            // locked the account-specific counter.
            if (!$sessionAttemptState['locked']) {
                $sessionAttemptState = $limiter->fail($sessionIdentity, 'Login');
                $attemptState = $this->combineLoginAttemptStates($accountAttemptState, $sessionAttemptState);
            }
            $this->lastLoginFailureMessage = AuthAttemptLimiter::message('Login', $attemptState, $limiter->maxFailures());
            $this->lastLoginFailureState = $attemptState;
            $this->recordLoginAttempt($identifier, false, $user ? (int) $user['id'] : null);
            throw new DomainException($this->lastLoginFailureMessage);
        }

        if (!$credentialsValid) {
            $accountAttemptState = $limiter->fail($attemptIdentity, 'Login');
            $sessionAttemptState = $limiter->fail($sessionIdentity, 'Login');
            $attemptState = $this->combineLoginAttemptStates($accountAttemptState, $sessionAttemptState);
            $this->lastLoginFailureMessage = AuthAttemptLimiter::message('Login', $attemptState, $limiter->maxFailures());
            $this->lastLoginFailureState = $attemptState;
            $this->recordLoginAttempt($identifier, false, $user ? (int) $user['id'] : null);
            return null;
        }

        $limiter->clear($attemptIdentity, 'Login');
        $limiter->clear($sessionIdentity, 'Login');
        $this->lastLoginFailureMessage = null;
        $this->lastLoginFailureState = [];
        $update = $this->db->prepare('UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = :id');
        $update->execute(['id' => $user['id']]);
        $this->recordLoginAttempt($identifier, true, (int) $user['id'], (string) $user['role']);

        unset($user['password_hash'], $user['status']);
        $user['must_change_password'] = (bool) $user['must_change_password'];

        return $user;
    }

    public function getLastLoginFailureMessage(): string
    {
        return $this->lastLoginFailureMessage ?? 'The username/email or password is incorrect.';
    }

    public function getLastLoginFailureState(): array
    {
        return $this->lastLoginFailureState;
    }

    public function getLastLoginFieldErrors(): array
    {
        return $this->lastLoginFieldErrors;
    }

    /** Combine persistent per-account protection with this browser session's counter. */
    private function combineLoginAttemptStates(array $accountState, array $sessionState): array
    {
        $locked = !empty($accountState['locked']) || !empty($sessionState['locked']);
        return [
            'remaining' => min((int) ($accountState['remaining'] ?? 5), (int) ($sessionState['remaining'] ?? 5)),
            'locked' => $locked,
            'retry_after' => $locked
                ? max((int) ($accountState['retry_after'] ?? 0), (int) ($sessionState['retry_after'] ?? 0))
                : 0,
        ];
    }

    public function updateAccountEmail(int $userId, string $email): void
    {
        $this->db->beginTransaction();
        try {
            $account = $this->db->prepare('SELECT id FROM users WHERE id = :user_id FOR UPDATE');
            $account->execute(['user_id' => $userId]);
            if (!$account->fetchColumn()) throw new DomainException('Account not found.');
            $duplicate = $this->db->prepare('SELECT 1 FROM users WHERE email = :email AND id <> :user_id UNION ALL SELECT 1 FROM admission_applications WHERE email = :application_email AND (applicant_user_id IS NULL OR applicant_user_id <> :application_user_id) LIMIT 1');
            $duplicate->execute(['email' => $email, 'user_id' => $userId, 'application_email' => $email, 'application_user_id' => $userId]);
            if ($duplicate->fetchColumn()) throw new DomainException('That email is already assigned to another account or application.');
            $this->db->prepare('UPDATE users SET email = :email WHERE id = :user_id')->execute(['email' => $email, 'user_id' => $userId]);
            $this->db->prepare('UPDATE students SET email = :email WHERE user_id = :user_id')->execute(['email' => $email, 'user_id' => $userId]);
            $this->db->prepare('UPDATE admission_applications SET email = :email WHERE applicant_user_id = :user_id')->execute(['email' => $email, 'user_id' => $userId]);
            $this->db->commit();
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $exception;
        }
    }

    /** Save authentication history without exposing credentials or breaking sign-in if logging fails. */
    private function recordLoginAttempt(string $identifier, bool $successful, ?int $userId, string $role = ''): void
    {
        $username = substr(preg_replace('/[\x00-\x1F\x7F]/', '', trim($identifier)) ?? '', 0, 50);
        if ($username === '') {
            $username = '(empty)';
        }
        $action = $successful ? 'USER_LOGIN' : 'LOGIN_FAILED';
        $details = $successful
            ? 'Successful login for ' . $username . ' (' . $role . ').'
            : 'Failed login attempt for identifier ' . $username . '.';

        try {
            $this->db->beginTransaction();
            $attempt = $this->db->prepare(
                'INSERT INTO login_attempts (username, ip_address, was_successful)
                 VALUES (:username, :ip_address, :was_successful)'
            );
            $attempt->execute([
                'username' => $username,
                'ip_address' => substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45),
                'was_successful' => $successful ? 1 : 0,
            ]);

            $audit = $this->db->prepare(
                'INSERT INTO audit_logs (user_id, action, entity, details, ip_address)
                 VALUES (:user_id, :action, \'Authentication\', :details, :ip_address)'
            );
            $audit->execute([
                'user_id' => $userId,
                'action' => $action,
                'details' => $details,
                'ip_address' => substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45),
            ]);
            $this->db->commit();
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log('SSIS login audit failed: ' . $exception->getMessage());
        }
    }
}
