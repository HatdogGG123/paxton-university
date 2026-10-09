<?php
/** Validates enrollment form input and delegates request changes to its model. */
require_once __DIR__ . '/../models/Enrollment.php';

final class EnrollmentService
{
    public function __construct(private Enrollment $enrollment)
    {
    }

    /** Validate and submit a student's selected subjects for the current term. */
    public function submitStudentRequest(int $userId, array $input): string
    {
        $yearLevel = $this->positiveInteger($input['year_level'] ?? null);
        if ($yearLevel === null || $yearLevel > 6) {
            throw new DomainException('Choose a valid year level.');
        }

        $rawSubjectIds = $input['subject_ids'] ?? null;
        if (!is_array($rawSubjectIds)) {
            throw new DomainException('Select at least one subject before submitting.');
        }
        $subjectIds = [];
        foreach ($rawSubjectIds as $rawId) {
            $subjectId = $this->positiveInteger($rawId);
            if ($subjectId === null) {
                throw new DomainException('The selected subject list is invalid. Refresh the page and try again.');
            }
            $subjectIds[] = $subjectId;
        }
        $subjectIds = array_values(array_unique($subjectIds));
        if (count($subjectIds) > 100) {
            throw new DomainException('Select no more than 100 subjects.');
        }

        return $this->enrollment->submitRequest($userId, $yearLevel, $subjectIds);
    }

    /** Cancel a pending request belonging to the signed-in student. */
    public function cancelStudentRequest(int $userId, mixed $requestId): void
    {
        $id = $this->positiveInteger($requestId);
        if ($id === null) {
            throw new DomainException('The enrollment request could not be identified.');
        }
        $this->enrollment->cancelPendingRequest($userId, $id);
    }

    /** Validate and apply a registrar review decision. */
    public function reviewRequest(int $reviewerId, array $input): void
    {
        $requestId = $this->positiveInteger($input['enrollment_id'] ?? null);
        $status = $input['new_status'] ?? '';
        $reason = $input['rejection_reason'] ?? '';
        if ($requestId === null || !is_string($status) || !is_string($reason)) {
            throw new DomainException('The review submission is invalid.');
        }

        $this->enrollment->reviewRequest($requestId, $reviewerId, $status, trim($reason));
    }

    /** Validate and save the Registrar's adjusted final subject selection. */
    public function updateReviewedSubjects(int $reviewerId, array $input): void
    {
        $requestId = $this->positiveInteger($input['enrollment_id'] ?? null);
        $rawSubjectIds = $input['subject_ids'] ?? null;
        if ($requestId === null || !is_array($rawSubjectIds)) {
            throw new DomainException('The subject adjustment form is invalid.');
        }
        $subjectIds = [];
        foreach ($rawSubjectIds as $rawId) {
            $subjectId = $this->positiveInteger($rawId);
            if ($subjectId === null) {
                throw new DomainException('The selected subject list is invalid.');
            }
            $subjectIds[] = $subjectId;
        }
        $subjectIds = array_values(array_unique($subjectIds));
        if (count($subjectIds) > 100) {
            throw new DomainException('Select no more than 100 subjects.');
        }

        $this->enrollment->updateRequestSubjects($requestId, $reviewerId, $subjectIds);
    }

    /** Parse a positive integer from a form value without accepting arrays or malformed text. */
    private function positiveInteger(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value > 0 ? $value : null;
        }
        if (!is_string($value) || $value === '' || !ctype_digit($value)) {
            return null;
        }
        $number = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return $number === false ? null : $number;
    }
}
