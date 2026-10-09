<?php
require_once __DIR__ . '/../php/includes/auth.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Admission.php';

ssis_start_session();
header('Content-Type: application/json; charset=utf-8');
function admin_user_json(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') admin_user_json(405, ['status' => 'error', 'message' => 'This request must use POST.']);
if (empty($_SESSION['user']['id']) || strtolower((string)($_SESSION['user']['role'] ?? '')) !== 'admin') admin_user_json(403, ['status' => 'error', 'message' => 'You are not authorized to manage accounts.']);
if (empty($_SESSION['csrf_token']) || !is_string($_POST['csrf_token'] ?? null) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) admin_user_json(400, ['status' => 'error', 'message' => 'This form expired. Refresh the page and try again.']);

try {
    $db = Database::getConnection();
    $userId = filter_var($_POST['user_id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null;
    $username = trim((string) ($_POST['username'] ?? ''));
    $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
    $role = trim((string) ($_POST['role'] ?? ''));
    $departmentId = filter_var($_POST['department_id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null;
    $allowedRoles = ['Student', 'Applicant', 'Registrar', 'Cashier', 'Department', 'Admin'];
    if ($username === '' || strlen($username) > 50 || !preg_match('/^[A-Za-z0-9._-]+$/', $username)) throw new DomainException('Enter a valid username or account ID (letters, numbers, dots, underscores, and hyphens).');
    if (!in_array($role, $allowedRoles, true)) throw new DomainException('Choose a valid account role.');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 120) throw new DomainException('Enter a valid account email address.');
    if (($role === 'Department') !== ($departmentId !== null)) throw new DomainException('Choose a department for Department accounts.');
    if ($departmentId !== null) {
        $checkDepartment = $db->prepare('SELECT 1 FROM departments WHERE id = :id AND is_active = 1');
        $checkDepartment->execute(['id' => $departmentId]);
        if (!$checkDepartment->fetchColumn()) throw new DomainException('Choose an active department.');
    }

    $duplicate = $db->prepare('SELECT 1 FROM users WHERE (username = :username OR email = :email) AND (:check_user_id IS NULL OR id <> :exclude_user_id) UNION ALL SELECT 1 FROM admission_applications WHERE email = :application_email AND (applicant_user_id IS NULL OR applicant_user_id <> :application_user_id) LIMIT 1');
    $duplicate->execute(['username' => $username, 'email' => $email, 'check_user_id' => $userId, 'exclude_user_id' => $userId, 'application_email' => $email, 'application_user_id' => $userId]);
    if ($duplicate->fetchColumn()) throw new DomainException('That username or email is already assigned to an account.');

    if ($userId) {
        $existingApplicant = $db->prepare('SELECT 1 FROM admission_applications WHERE applicant_user_id = :id LIMIT 1');
        $existingApplicant->execute(['id' => $userId]);
        $hasApplication = (bool) $existingApplicant->fetchColumn();
        if ($role === 'Applicant' && !$hasApplication) throw new DomainException('Applicant accounts need an application profile. Create a new Applicant account and complete its applicant details.');
        if ($hasApplication && $role !== 'Applicant') throw new DomainException('An account linked to an application must keep the Applicant role.');
        (new User($db))->updateAccountEmail($userId, $email);
        $update = $db->prepare('UPDATE users SET role = :role, department_id = :department_id WHERE id = :id');
        $update->execute(['role' => $role, 'department_id' => $departmentId, 'id' => $userId]);
        $id = $userId;
        $message = 'Account details updated.';
    } else {
        $password = (string) ($_POST['password'] ?? '');
        if (strlen($password) < 8 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) throw new DomainException('Use an initial password with at least 8 characters, including a letter and a number.');
        $applicant = null;
        if ($role === 'Applicant') {
            $applicant = [
                'first_name' => trim((string)($_POST['first_name']??'')),
                'middle_name' => trim((string)($_POST['middle_name']??'')),
                'last_name' => trim((string)($_POST['last_name']??'')),
                'contact_number' => trim((string)($_POST['contact_number']??'')),
                'address' => trim((string)($_POST['address']??'')),
                'program_id' => filter_var($_POST['program_id']??'',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]) ?: null,
            ];
            if($applicant['first_name']===''||mb_strlen($applicant['first_name'])>60||mb_strlen($applicant['middle_name'])>60||$applicant['last_name']===''||mb_strlen($applicant['last_name'])>60||$applicant['contact_number']===''||mb_strlen($applicant['contact_number'])>20||$applicant['address']===''||mb_strlen($applicant['address'])>255||!$applicant['program_id']) throw new DomainException('Complete the applicant name, contact, address, and program fields.');
            $validProgram=$db->prepare("SELECT 1 FROM programs p JOIN departments d ON d.id=p.department_id WHERE p.id=:id AND p.is_active=1 AND d.is_active=1 AND d.dept_type='Academic'");
            $validProgram->execute(['id'=>$applicant['program_id']]);
            if(!$validProgram->fetchColumn())throw new DomainException('Choose an active academic program.');
        }
        $db->beginTransaction();
        try {
            $fullName=$applicant ? trim($applicant['first_name'].' '.$applicant['middle_name'].' '.$applicant['last_name']) : null;
            $insert = $db->prepare("INSERT INTO users (username, email, password_hash, role, department_id, full_name, status, must_change_password) VALUES (:username, :email, :password_hash, :role, :department_id, :full_name, 'Active', 1)");
            $insert->execute(['username' => $username, 'email' => $email, 'password_hash' => password_hash($password, PASSWORD_DEFAULT), 'role' => $role, 'department_id' => $departmentId, 'full_name'=>$fullName]);
            $id = (int) $db->lastInsertId();
            if($applicant){
                $year=(int)date('Y');
                $db->prepare("INSERT INTO number_sequences(seq_type,seq_year,last_number) VALUES('application_no',:year,1) ON DUPLICATE KEY UPDATE last_number=last_number+1")->execute(['year'=>$year]);
                $seq=$db->prepare("SELECT last_number FROM number_sequences WHERE seq_type='application_no' AND seq_year=:year FOR UPDATE");$seq->execute(['year'=>$year]);
                $applicationNo=sprintf('APP-%d-%05d',$year,(int)$seq->fetchColumn());
                $createApplication=$db->prepare("INSERT INTO admission_applications(application_no,first_name,middle_name,last_name,email,contact_number,address,program_id,applicant_user_id) VALUES(:number,:first,:middle,:last,:email,:contact,:address,:program,:user_id)");
                $createApplication->execute(['number'=>$applicationNo,'first'=>$applicant['first_name'],'middle'=>$applicant['middle_name']?:null,'last'=>$applicant['last_name'],'email'=>$email,'contact'=>$applicant['contact_number'],'address'=>$applicant['address'],'program'=>$applicant['program_id'],'user_id'=>$id]);
            }
            $db->commit();
        } catch(Throwable $exception) { if($db->inTransaction())$db->rollBack(); throw $exception; }
        $message = 'User account created.';
    }
    $query = $db->prepare('SELECT u.id, u.username, u.email, u.role, u.department_id, u.status, d.name AS department_name, a.first_name, a.middle_name, a.last_name, a.contact_number, a.address, a.program_id FROM users u LEFT JOIN departments d ON d.id = u.department_id LEFT JOIN admission_applications a ON a.applicant_user_id=u.id WHERE u.id = :id');
    $query->execute(['id' => $id]);
    $user = $query->fetch(PDO::FETCH_ASSOC);
    if (!$user) throw new RuntimeException('Saved account could not be loaded.');
    try {
        $audit = $db->prepare('INSERT INTO audit_logs (user_id,action,entity,entity_id,details,ip_address) VALUES (:admin,:action,\'User Account\',:id,:details,:ip)');
        $audit->execute(['admin'=>(int)$_SESSION['user']['id'],'action'=>$userId?'USER_ACCOUNT_UPDATED':'USER_ACCOUNT_CREATED','id'=>$id,'details'=>($userId?'Updated':'Created').' account '.$username.'.','ip'=>substr((string)($_SERVER['REMOTE_ADDR']??''),0,45)]);
    } catch (Throwable $auditException) { error_log('SSIS admin user audit failed: '.$auditException->getMessage()); }
    $newApplicantCount = (new Admission($db))->newApplicantCount();
    admin_user_json(200, ['status' => 'success', 'message' => $message, 'data' => $user, 'new_applicant_count' => $newApplicantCount]);
} catch (DomainException $exception) {
    admin_user_json(422, ['status' => 'error', 'message' => $exception->getMessage()]);
} catch (Throwable $exception) {
    error_log('SSIS admin user save failed: ' . $exception->getMessage());
    admin_user_json(500, ['status' => 'error', 'message' => 'The account could not be saved. Please try again.']);
}
