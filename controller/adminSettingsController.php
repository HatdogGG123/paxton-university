<?php

require_once __DIR__ . '/../php/includes/auth.php';

ssis_start_session();

header('Content-Type: application/json; charset=utf-8');

$db = Database::getConnection();
$json = static function (int $code, array $data): never {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
};

if (strtolower((string)($_SESSION['user']['role'] ?? '')) !== 'admin') {
    $json(403, ['status' => 'error', 'message' => 'You are not authorized to change system settings.']);
}

if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') === 'GET' && ($_GET['action'] ?? '') === 'list') {
    try {
        $json(
            200,
            [
                'status' => 'success',
                'data' => [
                    'departments' => $db->query('SELECT id, code, name, dept_type, is_active FROM departments ORDER BY name')->fetchAll(PDO::FETCH_ASSOC),
                    'programs' => $db->query('SELECT p.id,p.code,p.name,p.department_id,p.is_active,d.name department_name FROM programs p JOIN departments d ON d.id=p.department_id ORDER BY p.name')->fetchAll(PDO::FETCH_ASSOC),
                    'subjects' => $db->query('SELECT s.id,s.code,s.name,s.units,s.program_id,s.year_level,s.semester,s.is_active,p.name program_name FROM subjects s JOIN programs p ON p.id=s.program_id ORDER BY s.code')->fetchAll(PDO::FETCH_ASSOC),
                    'terms' => $db->query('SELECT id,academic_year,semester,is_current,enrollment_open_at,enrollment_close_at FROM terms ORDER BY academic_year DESC,semester')->fetchAll(PDO::FETCH_ASSOC),
                    'documents' => $db->query('SELECT id,code,name,fee,is_active FROM document_types ORDER BY name')->fetchAll(PDO::FETCH_ASSOC),
                    'clearance' => $db->query('SELECT id,name,office,department_id,is_active FROM clearance_requirements ORDER BY name')->fetchAll(PDO::FETCH_ASSOC),
                    'settings' => $db->query('SELECT setting_key,setting_value FROM settings')->fetchAll(PDO::FETCH_KEY_PAIR)
                ]
            ]
        );
    } catch (Throwable $e) {
        error_log('Admin settings list failed: ' . $e->getMessage());
        $json(500, ['status' => 'error', 'message' => 'System settings could not be loaded.']);
    }
}

if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    $json(405, ['status' => 'error', 'message' => 'This request must use POST.']);
}

if (empty($_SESSION['csrf_token']) || !is_string($_POST['csrf_token'] ?? null) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $json(400, ['status' => 'error', 'message' => 'This form expired. Refresh the page and try again.']);
}

try {
    $action = (string)($_POST['action'] ?? '');
    $adminId = (int)($_SESSION['user']['id'] ?? 0);

    if ($action === 'save_settings') {
        $map = [
            'school_name' => 'school_name',
            'max_units_per_enrollment' => 'max_units_per_enrollment',
            'login_attempt_limit' => 'login_attempt_limit',
            'lockout_time_minutes' => 'lockout_minutes',
            'session_timeout_minutes' => 'session_timeout_minutes'
        ];
        $values = [];

        foreach ($map as $field => $key) {
            $value = trim((string)($_POST[$field] ?? ''));
            if ($value === '' || strlen($value) > 255) throw new DomainException('Enter a value for every system setting.');
            if ($field !== 'school_name' && (!ctype_digit($value) || (int)$value < 1)) throw new DomainException('Enter a positive whole number for the numeric settings.');
            $bounds = ['max_units_per_enrollment' => [1, 40], 'login_attempt_limit' => [1, 10], 'lockout_time_minutes' => [1, 1440], 'session_timeout_minutes' => [5, 480]];
            if (isset($bounds[$field]) && ((int)$value < $bounds[$field][0] || (int)$value > $bounds[$field][1])) throw new DomainException('One or more numeric settings are outside the allowed range.');
            $values[$key] = $value;
        }

        $save = $db->prepare('INSERT INTO settings (setting_key,setting_value,updated_by) VALUES (:key,:value,:admin) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value),updated_by=VALUES(updated_by)');
        foreach ($values as $key => $value) $save->execute(['key' => $key, 'value' => $value, 'admin' => $adminId]);

        $json(200, ['status' => 'success', 'message' => 'System settings updated.']);
    }
    if ($action === 'save_enrollment_window') {
        $open = str_replace('T', ' ', trim((string)($_POST['enrollment_open_at'] ?? '')));
        $close = str_replace('T', ' ', trim((string)($_POST['enrollment_close_at'] ?? '')));
        $openTime = strtotime($open);
        $closeTime = strtotime($close);

        if (!$openTime || !$closeTime || $closeTime <= $openTime) {
            throw new DomainException('Enter a valid enrollment period where the closing time is after the opening time.');
        }

        $q = $db->prepare('UPDATE terms SET enrollment_open_at=:open,enrollment_close_at=:close WHERE is_current=1');
        $q->execute(['open' => date('Y-m-d H:i:s', $openTime), 'close' => date('Y-m-d H:i:s', $closeTime)]);

        if (!$q->rowCount()) {
            $exists = (bool)$db->query('SELECT 1 FROM terms WHERE is_current=1')->fetchColumn();
            if (!$exists) throw new DomainException('Set an active academic term before configuring enrollment dates.');
        }

        $json(200, ['status' => 'success', 'message' => 'Enrollment period saved.']);
    }
    if ($action === 'set_active') {
        $types = [
            'department' => 'departments',
            'program' => 'programs',
            'subject' => 'subjects',
            'document' => 'document_types',
            'clearance' => 'clearance_requirements'
        ];

        $type = (string)($_POST['type'] ?? '');
        $id = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $active = filter_var($_POST['is_active'] ?? null, FILTER_VALIDATE_INT);

        if (!isset($types[$type]) || !$id || !in_array($active, [0, 1], true)) {
            throw new DomainException('Choose a valid record status.');
        }

        $q = $db->prepare('UPDATE ' . $types[$type] . ' SET is_active=:active WHERE id=:id');
        $q->execute(['active' => $active, 'id' => $id]);

        if (!$q->rowCount()) {
            $exists = $db->prepare('SELECT is_active FROM ' . $types[$type] . ' WHERE id=:id');
            $exists->execute(['id' => $id]);
            $current = $exists->fetchColumn();

            if ($current === false) {
                throw new DomainException('That record no longer exists.');
            }

            if ((int)$current !== $active) {
                throw new RuntimeException('The record status could not be updated.');
            }
        }

        $json(200, ['status' => 'success', 'message' => $active ? 'Record enabled.' : 'Record disabled.']);
    }

    if ($action === 'set_current_term') {
        $id = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if (!$id) {
            throw new DomainException('Choose an academic term.');
        }

        $db->beginTransaction();
        $db->exec('UPDATE terms SET is_current=0 WHERE is_current=1');
        $q = $db->prepare('UPDATE terms SET is_current=1 WHERE id=:id');
        $q->execute(['id' => $id]);

        if (!$q->rowCount()) {
            $db->rollBack();
            throw new DomainException('That academic term could not be found.');
        }

        $db->commit();
        $json(200, ['status' => 'success', 'message' => 'Current academic term updated.']);
    }

    if ($action === 'add_department') {
        $code = strtoupper(trim((string)($_POST['department_code'] ?? '')));
        $name = trim((string)($_POST['department_name'] ?? ''));

        if (!preg_match('/^[A-Z0-9-]{2,10}$/', $code) || $name === '' || mb_strlen($name) > 120) {
            throw new DomainException('Enter a valid department code and name.');
        }

        $q = $db->prepare("INSERT INTO departments(code,name,dept_type) VALUES(:code,:name,'Academic')");
        $q->execute(['code' => $code, 'name' => $name]);
    } elseif ($action === 'add_program') {
        $code = strtoupper(trim((string)($_POST['program_code'] ?? '')));
        $name = trim((string)($_POST['program_name'] ?? ''));
        $dept = (int)($_POST['department_id'] ?? 0);

        if ($code === '' || $name === '' || $dept < 1) {
            throw new DomainException('Enter the program details and choose a department.');
        }

        $check = $db->prepare('SELECT 1 FROM departments WHERE id=:id AND is_active=1');
        $check->execute(['id' => $dept]);

        if (!$check->fetchColumn()) {
            throw new DomainException('Choose an active department.');
        }

        $q = $db->prepare('INSERT INTO programs(department_id,code,name) VALUES(:dept,:code,:name)');
        $q->execute(['dept' => $dept, 'code' => $code, 'name' => $name]);
    } elseif ($action === 'add_subject') {
        $code = strtoupper(trim((string)($_POST['subject_code'] ?? '')));
        $name = trim((string)($_POST['subject_title'] ?? ''));
        $program = (int)($_POST['program_id'] ?? 0);
        $units = (float)($_POST['units'] ?? 0);
        $year = (int)($_POST['year_level'] ?? 0);
        $semester = (string)($_POST['semester'] ?? '');

        if (
            $code === '' ||
            $name === '' ||
            $program < 1 ||
            $units <= 0 ||
            $year < 1 ||
            $year > 6 ||
            !in_array($semester, ['1st Semester', '2nd Semester', 'Summer'], true)
        ) {
            throw new DomainException('Complete all subject details with valid values.');
        }

        $check = $db->prepare('SELECT 1 FROM programs WHERE id=:id AND is_active=1');
        $check->execute(['id' => $program]);

        if (!$check->fetchColumn()) {
            throw new DomainException('Choose an active program.');
        }

        $q = $db->prepare('INSERT INTO subjects(program_id,code,name,units,year_level,semester) VALUES(:program,:code,:name,:units,:year,:semester)');
        $q->execute(['program' => $program, 'code' => $code, 'name' => $name, 'units' => $units, 'year' => $year, 'semester' => $semester]);
    } elseif ($action === 'add_term') {
        $termName = trim((string)($_POST['term_name'] ?? ''));
        $matches = [];

        if (!preg_match('/(\d{4}-\d{4}).*(1st Semester|2nd Semester|Summer)/i', $termName, $matches)) {
            throw new DomainException('Use a term name like “A.Y. 2026-2027, 1st Semester”.');
        }

        $year = $matches[1];
        $semester = ucwords(strtolower($matches[2]));
        $current = !empty($_POST['is_current']) ? 1 : 0;

        if ($current) {
            $db->beginTransaction();
            $db->exec('UPDATE terms SET is_current=0 WHERE is_current=1');
        }

        $q = $db->prepare('INSERT INTO terms(academic_year,semester,is_current) VALUES(:year,:semester,:current)');
        $q->execute(['year' => $year, 'semester' => $semester, 'current' => $current]);

        if ($current && $db->inTransaction()) $db->commit();
    } elseif ($action === 'add_document') {
        $name = trim((string)($_POST['document_name'] ?? ''));
        $fee = filter_var($_POST['fee'] ?? null, FILTER_VALIDATE_FLOAT);
        $active = (int)($_POST['is_active'] ?? 1);

        if (
            $name === '' ||
            mb_strlen($name) > 120 ||
            $fee === false ||
            $fee < 0 ||
            !in_array($active, [0, 1], true)
        ) {
            throw new DomainException('Enter a valid document name, fee, and status.');
        }

        $base = strtoupper(substr(preg_replace('/[^A-Z0-9]/', '', strtoupper($name)) ?: 'DOC', 0, 7));
        $code = $base . substr(strtoupper(bin2hex(random_bytes(2))), 0, 3);

        $q = $db->prepare('INSERT INTO document_types(code,name,fee,is_active) VALUES(:code,:name,:fee,:active)');
        $q->execute(['code' => $code, 'name' => $name, 'fee' => $fee, 'active' => $active]);
    } elseif ($action === 'add_clearance') {
        $name = trim((string)($_POST['requirement_name'] ?? ''));
        $office = (string)($_POST['reviewing_office'] ?? '');
        $active = (int)($_POST['is_active'] ?? 1);

        if (
            $name === '' ||
            mb_strlen($name) > 120 ||
            !in_array($office, ['Department', 'Cashier', 'Registrar'], true) ||
            !in_array($active, [0, 1], true)
        ) {
            throw new DomainException('Enter a valid requirement name, reviewing office, and status.');
        }

        $dept = $office === 'Department' ? (int)($_POST['department_id'] ?? 0) : null;

        if ($office === 'Department' && $dept < 1) {
            throw new DomainException('Choose the department responsible for this requirement.');
        }

        $q = $db->prepare('INSERT INTO clearance_requirements(name,office,department_id,is_active) VALUES(:name,:office,:dept,:active)');
        $q->execute(['name' => $name, 'office' => $office, 'dept' => $dept, 'active' => $active]);
    } else {
        throw new DomainException('Choose a valid settings action.');
    }

    $json(200, ['status' => 'success', 'message' => 'Record saved.', 'data' => ['id' => (int)$db->lastInsertId()]]);
} catch (DomainException $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }

    $json(422, ['status' => 'error', 'message' => $e->getMessage()]);
} catch (PDOException $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }

    error_log('Admin setting save failed: ' . $e->getMessage());
    $json(409, ['status' => 'error', 'message' => 'A record with the same code or name may already exist. Check the values and try again.']);
} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }

    error_log('Admin setting save failed: ' . $e->getMessage());
    $json(500, ['status' => 'error', 'message' => 'The system setting could not be saved. Please try again.']);
}
