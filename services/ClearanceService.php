<?php
/** Validates clearance actions and exposes role-scoped clearance data. */
require_once __DIR__ . '/../models/Clearance.php';

final class ClearanceService
{
    public function __construct(private Clearance $clearance)
    {
    }

    public function review(int $reviewerId, string $office, ?int $departmentId, array $input): void
    {
        $itemId = $this->positiveInteger($input['item_id'] ?? null);
        $status = $input['status'] ?? '';
        $reason = $input['rejection_reason'] ?? '';
        $remarks = $input['remarks'] ?? '';
        $settlementReference = $input['settlement_reference'] ?? '';
        if ($itemId === null || !is_string($status) || !is_string($reason) || !is_string($remarks) || !is_string($settlementReference)) {
            throw new DomainException('The clearance submission is invalid.');
        }
        if ($office === 'Cashier' && $status === 'Rejected' && trim($reason) === '') {
            throw new DomainException('Enter a reason before rejecting this clearance item.');
        }
        $this->clearance->reviewItem($itemId, $reviewerId, $office, $departmentId, $status, trim($reason), trim($remarks), trim($settlementReference));
    }

    private function positiveInteger(mixed $value): ?int
    {
        if (!is_string($value) && !is_int($value)) { return null; }
        $value = (string) $value;
        if ($value === '' || !ctype_digit($value)) { return null; }
        $number = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        return $number === false ? null : $number;
    }
}
