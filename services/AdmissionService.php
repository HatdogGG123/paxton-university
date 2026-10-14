<?php
require_once __DIR__ . '/../models/Admission.php';

/** Validates applicant submissions before persisting them. */
final class AdmissionService
{
    public function __construct(private Admission $admission) {}

    public function submit(array $input): array
    {
        $data = [
            'first_name' => trim((string) ($input['first_name'] ?? '')),
            'middle_name' => trim((string) ($input['middle_name'] ?? '')) ?: null,
            'last_name' => trim((string) ($input['last_name'] ?? '')),
            'email' => mb_strtolower(trim((string) ($input['email'] ?? ''))),
            'contact_number' => trim((string) ($input['contact_number'] ?? '')),
            'address' => trim((string) ($input['address'] ?? '')),
            'program_id' => filter_var($input['program_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]),
        ];

        if (
            $data['first_name'] === '' ||
            $data['last_name'] === '' ||
            $data['contact_number'] === '' ||
            $data['address'] === '' ||
            !filter_var($data['email'], FILTER_VALIDATE_EMAIL) ||
            $data['program_id'] === false
        ) {
            throw new DomainException('Complete all applicant details with a valid email and program.');
        }

        if (!preg_match('/^[0-9]{11}$/', $data['contact_number'])) {
            throw new DomainException('Contact number must contain exactly 11 digits using numbers only.');
        }

        if ($this->admission->emailInUse($data['email'])) {
            throw new DomainException('This email is already associated with an account or application. Use another email address or sign in to your existing account.');
        }

        return $this->admission->submit($data, bin2hex(random_bytes(8)));
    }
}
