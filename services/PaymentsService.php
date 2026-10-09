<?php
/** Validates cashier payment actions. */
require_once __DIR__ . '/../models/Payments.php';

final class PaymentsService
{
    public function __construct(private Payments $payments) {}

    public function handle(int $cashierId, array $input): string
    {
        $action = $input['action'] ?? '';
        $paymentId = $this->positiveInteger($input['payment_id'] ?? null);
        if ($paymentId === null) { throw new DomainException('Choose a valid payment record.'); }
        if ($action === 'verify') { return $this->payments->verify($paymentId, $cashierId); }
        if ($action === 'reject') { $this->payments->reject($paymentId, $cashierId, $this->text($input['reason'] ?? '', 'rejection reason')); return ''; }
        if ($action === 'walkin') {
            $method = $this->text($input['method'] ?? '', 'payment method');
            return $this->payments->recordWalkIn($paymentId, $cashierId, $method, $this->text($input['amount'] ?? '', 'amount'), $this->text($input['reference'] ?? '', 'reference number', true));
        }
        if ($action === 'refund') { $this->payments->refund($paymentId, $cashierId, $this->text($input['reason'] ?? '', 'refund reason')); return ''; }
        throw new DomainException('Choose a valid payment action.');
    }

    private function text(mixed $value, string $label, bool $optional = false): string
    {
        if (!is_string($value) || (!$optional && trim($value) === '')) { throw new DomainException('Enter a valid ' . $label . '.'); }
        return trim($value);
    }

    private function positiveInteger(mixed $value): ?int
    {
        if (!is_string($value) && !is_int($value)) { return null; }
        if (!ctype_digit((string) $value)) { return null; }
        $number = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        return $number === false ? null : $number;
    }
}
