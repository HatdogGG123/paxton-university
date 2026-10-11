<?php
require_once __DIR__ . '/../php/includes/auth.php';
require_once __DIR__ . '/../models/Admission.php';
require_once __DIR__ . '/../services/AdmissionService.php';
require_once __DIR__ . '/../models/User.php';

ssis_start_session();

$isAjax = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest'
    || str_contains(strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? '')), 'application/json');
$sendJson = static function (int $status, array $payload): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
};

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    exit('POST is required.');
}

$token = $_POST['csrf_token'] ?? '';

if (!is_string($token) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
    if ($isAjax) $sendJson(400, ['message' => 'This form expired. Refresh and try again.']);
    http_response_code(400);
    exit('This form expired. Refresh and try again.');
}

$action = (string) ($_POST['action'] ?? '');
try {
    $db = Database::getConnection();
    $model = new Admission($db);
    if ($action === 'submit_application') {
        if (($_POST['privacy_consent'] ?? '') !== '1') {
            throw new DomainException('Please review and accept the Data Privacy Notice and Consent before submitting.');
        }
        $credentials = (new AdmissionService($model))->submit($_POST);
        $_SESSION['applicant_credentials'] = $credentials;
        $user = (new User($db))->authenticate($credentials['application_no'], $credentials['password']);
        if ($user === null) throw new RuntimeException('Applicant account was created but sign-in failed.');
        session_regenerate_id(true);
        $_SESSION['user'] = $user;
        $_SESSION['last_activity'] = time();
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        if ($isAjax) $sendJson(201, [
            'message' => 'Application received and applicant account created.',
            'application_no' => $credentials['application_no'],
            'password' => $credentials['password'],
        ]);
        header('Location: ' . ssis_app_base_path() . '/application.php?submitted=' . rawurlencode($credentials['application_no']));
        exit;
    }
    if ($action === 'confirm_approval') {
        ssis_require_role('applicant');
        $studentNo = $model->confirmApplicantApproval((int) $_SESSION['user']['id']);
        $_SESSION['user']['role'] = 'Student';
        $_SESSION['user']['username'] = $studentNo;
        if ($isAjax) $sendJson(200, ['message' => 'Your student account is now active.', 'redirect' => ssis_app_base_path() . '/php/student/dashboard.php?admitted=1']);
        header('Location: ' . ssis_app_base_path() . '/php/student/dashboard.php?admitted=1');
        exit;
    }
    if (in_array($action, ['submit_document', 'submit_face_to_face'], true)) {
        if ($action === 'submit_document') ssis_require_role('applicant');
        else ssis_require_role(['admin', 'registrar']);
        $app = $action === 'submit_document'
            ? $model->getApplicantApplication((int) $_SESSION['user']['id'])
            : (static function () use ($model): ?array {
                $appId = filter_var($_POST['application_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                foreach ($model->queue() as $candidate) if ((int) $candidate['id'] === (int) $appId) return $candidate;
                return null;
            })();
        if (!$app) throw new DomainException('No application is linked to this account.');
        $requirementId = filter_var($_POST['requirement_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (!$requirementId) throw new DomainException('Choose a valid required document.');
        $path = null;
        $method = $action === 'submit_document' ? 'Online' : 'Face to Face';
        if ($method === 'Online') {
            $file = $_FILES['document_file'] ?? null;
            if (!$file || $file['error'] !== UPLOAD_ERR_OK || $file['size'] > 5 * 1024 * 1024) throw new DomainException('Upload a PDF, JPG, or PNG file no larger than 5 MB.');
            $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
            $extensions = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'];
            if (!isset($extensions[$mime])) throw new DomainException('Only PDF, JPG, and PNG documents are accepted.');
            $directory = __DIR__ . '/../uploads/admissions';
            if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) throw new RuntimeException('Upload folder is unavailable.');
            $name = bin2hex(random_bytes(20)) . '.' . $extensions[$mime];
            if (!move_uploaded_file($file['tmp_name'], $directory . '/' . $name)) throw new RuntimeException('The document upload could not be saved.');
            $path = $name;
        }
        $documentId = $model->recordSubmission((int) $app['id'], $requirementId, $method, $path);
        $returnPath = $action === 'submit_document' ? '/php/applicant/dashboard.php?updated=1' : '/php/registrar/admissions.php?updated=1';
        if ($isAjax) $sendJson(200, ['message' => 'Document submission saved.', 'document_id' => $documentId, 'data' => ['document_id' => $documentId, 'review_status' => 'Pending', 'submission_method' => $method]]);
        header('Location: ' . ssis_app_base_path() . $returnPath);
        exit;
    }
    ssis_require_role(['admin', 'registrar']);
    $reviewer = (int) $_SESSION['user']['id'];
    $id = filter_var($_POST['application_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $result = ['action' => $action];
    if ($action === 'review_document') {
        $docId = filter_var($_POST['document_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (!$docId) throw new DomainException('Choose a valid document.');
        $message = trim((string) ($_POST['document_message'] ?? ''));
        $preset = trim((string) ($_POST['message_preset'] ?? ''));
        if ($preset !== '' && $preset !== 'Custom message') $message = $preset . ($message !== '' ? ': ' . $message : '');
        $model->reviewDocument($docId, $reviewer, (string) ($_POST['document_status'] ?? ''), $message);
        $result += ['document_id' => $docId, 'review_status' => (string) ($_POST['document_status'] ?? ''), 'review_message' => $message];
    } elseif ($action === 'save_requirement') {
        ssis_require_role('admin');
        $requirementId = filter_var($_POST['requirement_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null;
        $programId = filter_var($_POST['program_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (!$programId) throw new DomainException('Choose a program.');
        $result += ['requirement_id' => $model->saveRequirement($requirementId, $programId, trim((string) ($_POST['document_name'] ?? '')), $reviewer), 'program_id' => (int) $programId, 'document_name' => trim((string) ($_POST['document_name'] ?? ''))];
    } elseif ($action === 'delete_requirement') {
        ssis_require_role('admin');
        $requirementId = filter_var($_POST['requirement_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (!$requirementId) throw new DomainException('Choose a valid document requirement.');
        $model->deleteRequirement($requirementId);
        $result['requirement_id'] = (int) $requirementId;
    } elseif ($action === 'review_application' && $id) {
        $model->review($id, $reviewer, (string) ($_POST['status'] ?? ''), trim((string) ($_POST['remarks'] ?? '')));
        $result += ['application_id' => (int) $id, 'status' => (string) ($_POST['status'] ?? ''), 'remarks' => trim((string) ($_POST['remarks'] ?? ''))];
    } else throw new DomainException('Choose a valid admissions action.');
    if ($isAjax) $sendJson(200, ['message' => 'Admission information saved.', 'data' => $result]);
    header('Location: ' . ssis_app_base_path() . '/php/registrar/admissions.php?updated=1');
    exit;
} catch (DomainException $exception) {
    if ($isAjax) $sendJson(422, ['message' => $exception->getMessage()]);
    if ($action === 'submit_application') {
        $_SESSION['application_error'] = $exception->getMessage();
        header('Location: ' . ssis_app_base_path() . '/application.php');
        exit;
    }
    if (in_array($action, ['submit_document', 'confirm_approval'], true)) {
        $_SESSION['application_error'] = $exception->getMessage();
        header('Location: ' . ssis_app_base_path() . '/php/applicant/dashboard.php');
        exit;
    }
    $_SESSION['admission_error'] = $exception->getMessage();
    header('Location: ' . ssis_app_base_path() . '/php/registrar/admissions.php');
    exit;
} catch (PDOException $exception) {
    if ($isAjax) {
        if ($action === 'submit_application' && (string) $exception->getCode() === '23000') $sendJson(409, ['message' => 'This email is already associated with an account or application.']);
        error_log('SSIS admission database action failed: ' . $exception->getMessage());
        $sendJson(500, ['message' => 'The admission action could not be completed.']);
    }
    if ($action === 'submit_application' && (string) $exception->getCode() === '23000') {
        $_SESSION['application_error'] = 'This email is already associated with an account or application. Use another email address or sign in to your existing account.';
        header('Location: ' . ssis_app_base_path() . '/application.php');
        exit;
    }
    error_log('SSIS admission database action failed: ' . $exception->getMessage());
    http_response_code(500);
    echo 'The admission action could not be completed.';
} catch (Throwable $exception) {
    if ($isAjax) $sendJson(500, ['message' => 'The admission action could not be completed.']);
    error_log('SSIS admission action failed: ' . $exception->getMessage());
    http_response_code(500);
    echo 'The admission action could not be completed.';
}
