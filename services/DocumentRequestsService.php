<?php

/** Validate student and Registrar document request submissions. */
require_once __DIR__ . '/../models/DocumentRequests.php';

final class DocumentRequestsService
{
    public function __construct(private DocumentRequests $requests) {}

    /** Create a student request using only validated form values. */
    public function submitStudentRequest(int $userId, array $input): string
    {
        $typeId = $this->positiveInteger($input['document_type_id'] ?? null);
        $copies = $this->positiveInteger($input['quantity'] ?? null);
        $purpose = $input['purpose'] ?? '';
        $remarks = $input['remarks'] ?? '';
        if ($typeId === null || $copies === null || !is_string($purpose) || !is_string($remarks)) {
            throw new DomainException('The document request form is invalid. Check the fields and try again.');
        }
        $purposeLabels = [
            'employment' => 'Employment',
            'further_studies' => 'Further Studies / Transfer',
            'scholarship' => 'Scholarship Application',
            'visa' => 'Visa / Travel Requirements',
            'other' => 'Other',
        ];
        if (!isset($purposeLabels[$purpose])) {
            throw new DomainException('Choose a valid request purpose.');
        }
        return $this->requests->create($userId, $typeId, $purposeLabels[$purpose], $copies, trim($remarks));
    }

    /** Validate a Registrar's next status for a document request. */
    public function updateStatus(int $registrarId, array $input): void
    {
        $requestNo = $input['request_no'] ?? '';
        $status = $input['status'] ?? '';
        $receivedBy = $input['received_by'] ?? '';
        if (
            !is_string($requestNo) || !preg_match('/^DR-[0-9]{4}-[0-9]{4,7}$/', $requestNo)
            || !is_string($status) || !is_string($receivedBy)
        ) {
            throw new DomainException('The document request update is invalid. Refresh the page and try again.');
        }
        $this->requests->transition($requestNo, $registrarId, $status, trim($receivedBy));
    }

    /** Validate and apply a Registrar's required rejection reason. */
    public function reject(int $registrarId, array $input): void
    {
        $requestNo = $input['request_no'] ?? '';
        $reason = $input['rejection_reason'] ?? '';
        if (!is_string($requestNo) || !preg_match('/^DR-[0-9]{4}-[0-9]{4,7}$/', $requestNo) || !is_string($reason)) {
            throw new DomainException('The rejection submission is invalid.');
        }
        $this->requests->reject($requestNo, $registrarId, $reason);
    }

    /** Parse an integer greater than zero from form data. */
    private function positiveInteger(mixed $value): ?int
    {
        if (!is_string($value) && !is_int($value)) {
            return null;
        }
        $value = (string) $value;
        if ($value === '' || !ctype_digit($value)) {
            return null;
        }
        $number = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        return $number === false ? null : $number;
    }
}
