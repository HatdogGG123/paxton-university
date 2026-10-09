<?php
require_once __DIR__ . '/../php/includes/auth.php';

ssis_require_role(['admin', 'registrar']);

require_once __DIR__ . '/../models/Admission.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

if (!$id) {
    http_response_code(404);
    exit('Document not found.');
}

$query = Database::getConnection()->prepare('SELECT document.file_path, requirement.document_name FROM admission_documents document JOIN admission_document_requirements requirement ON requirement.id = document.requirement_id WHERE document.id = :id');
$query->execute(['id' => $id]);

$document = $query->fetch();

if (!$document || !$document['file_path']) {
    http_response_code(404);
    exit('Document not found.');
}

$path = realpath(__DIR__ . '/../uploads/admissions/' . basename($document['file_path']));
$root = realpath(__DIR__ . '/../uploads/admissions');

if (!$path || !$root || !str_starts_with($path, $root . DIRECTORY_SEPARATOR) || !is_file($path)) {
    http_response_code(404);
    exit('Document not found.');
}

$mime = (new finfo(FILEINFO_MIME_TYPE))->file($path);

if (!in_array($mime, ['application/pdf', 'image/jpeg', 'image/png'], true)) {
    http_response_code(415);
    exit('Unsupported document.');
}

header('Content-Type: ' . $mime);
header('Content-Disposition: inline; filename="' . rawurlencode((string) $document['document_name']) . '"');
header('X-Content-Type-Options: nosniff');
readfile($path);
