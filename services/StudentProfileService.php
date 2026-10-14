<?php

/** Validates profile form input and delegates changes to the profile model. */
require_once __DIR__ . '/../models/StudentProfile.php';

final class StudentProfileService
{
    public function __construct(private StudentProfile $profile) {}

    /** Validate and save a contact information form submission. */
    public function updateContact(int $userId, array $input): array
    {
        $values = [
            'email' => trim($this->stringValue($input, 'email')),
            'phone_number' => trim($this->stringValue($input, 'phone_number')),
            'address' => trim($this->stringValue($input, 'address')),
        ];

        if ($values['email'] === '' || strlen($values['email']) > 120 || !filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Enter a valid email address.', 'values' => $values];
        }
        if (!preg_match('/^[0-9+().\-\s]{5,20}$/', $values['phone_number'])) {
            return ['success' => false, 'message' => 'Enter a valid phone number using 5 to 20 digits or common phone characters.', 'values' => $values];
        }
        if ($values['address'] === '' || strlen($values['address']) > 255) {
            return ['success' => false, 'message' => 'Enter an address no longer than 255 characters.', 'values' => $values];
        }

        $this->profile->updateContact($userId, $values['email'], $values['phone_number'], $values['address']);

        return ['success' => true, 'message' => 'Contact information saved.', 'values' => $values];
    }

    /** Validate and save a change password form submission. */
    public function changePassword(int $userId, array $input): array
    {
        $currentPassword = $this->stringValue($input, 'current_password');
        $newPassword = $this->stringValue($input, 'new_password');
        $confirmation = $this->stringValue($input, 'confirm_password');

        if ($currentPassword === '') {
            return ['success' => false, 'message' => 'Enter your current password.'];
        }
        if (
            strlen($newPassword) < 8 || strlen($newPassword) > 255
            || !preg_match('/[A-Za-z]/', $newPassword) || !preg_match('/[0-9]/', $newPassword)
        ) {
            return ['success' => false, 'message' => 'Your new password must be at least 8 characters and include a letter and a number.'];
        }
        if (!hash_equals($newPassword, $confirmation)) {
            return ['success' => false, 'message' => 'The new password and confirmation do not match.'];
        }
        if (!$this->profile->changePassword($userId, $currentPassword, $newPassword)) {
            return ['success' => false, 'message' => 'Your current password is incorrect.'];
        }

        return ['success' => true, 'message' => 'Password updated successfully.'];
    }

    /** Read a scalar form value, treating arrays and missing fields as empty. */
    private function stringValue(array $input, string $key): string
    {
        return isset($input[$key]) && is_string($input[$key]) ? $input[$key] : '';
    }
}
