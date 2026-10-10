<?php

/** Send password reset messages through EmailJS's HTTP API. */
final class EmailJsMailer
{
    private array $config;

    public function __construct()
    {
        $this->config = require __DIR__ . '/../config/mail.php';
        foreach (['emailjs_public_key', 'emailjs_private_key', 'emailjs_service_id', 'emailjs_template_id'] as $key) {
            if (empty($this->config[$key])) {
                throw new RuntimeException('EmailJS is not fully configured.');
            }
        }
        if (!function_exists('curl_init')) {
            throw new RuntimeException('PHP cURL is required for EmailJS.');
        }
    }

    public function send(string $recipient, string $resetUrl): void
    {
        $appUrl = $this->config['app_url'];
        $parts = parse_url($appUrl);
        if (!$parts || empty($parts['scheme']) || empty($parts['host'])) {
            throw new RuntimeException('SSIS_APP_URL must be a full site URL.');
        }
        $origin = $parts['scheme'] . '://' . $parts['host']
            . (isset($parts['port']) ? ':' . $parts['port'] : '');

        $payload = json_encode([
            'service_id' => $this->config['emailjs_service_id'],
            'template_id' => $this->config['emailjs_template_id'],
            'user_id' => $this->config['emailjs_public_key'],
            'accessToken' => $this->config['emailjs_private_key'],
            'template_params' => [
                'recipient_email' => $recipient,
                'reset_link' => $resetUrl,
                'expires_minutes' => 30,
            ],
        ], JSON_THROW_ON_ERROR);

        $request = curl_init('https://api.emailjs.com/api/v1.0/email/send');
        curl_setopt_array($request, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Origin: ' . $origin],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $response = curl_exec($request);
        $status = (int) curl_getinfo($request, CURLINFO_RESPONSE_CODE);
        $error = curl_error($request);
        curl_close($request);

        if ($response === false || $status < 200 || $status >= 300) {
            throw new RuntimeException($response === false
                ? 'EmailJS request failed: ' . $error
                : 'EmailJS returned HTTP ' . $status . '.');
        }
    }

    public function appUrl(): string
    {
        return $this->config['app_url'];
    }
}
