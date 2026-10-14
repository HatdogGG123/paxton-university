<?php

/** Validates grade entry and student grade query input. */
require_once __DIR__ . '/../models/Grades.php';

final class GradesService
{
    public function __construct(private Grades $grades) {}

    public function getStudentGrades(int $userId, mixed $rawTermId): array
    {
        $termId = $rawTermId === null || $rawTermId === '' ? null : $this->positiveInteger($rawTermId);
        if ($rawTermId !== null && $rawTermId !== '' && $termId === null) {
            throw new DomainException('Choose a valid academic term.');
        }

        return $this->grades->getStudentGrades($userId, $termId);
    }

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

    private function nonNegativeInteger(mixed $value): ?int
    {
        if (!is_string($value) && !is_int($value)) {
            return null;
        }
        $value = (string) $value;
        if ($value === '' || !ctype_digit($value)) {
            return null;
        }
        $number = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);

        return $number === false ? null : $number;
    }
}
