<?php
// Load host-specific credentials from config/mail.local.php. Keep that file out of version control.
$localConfigPath = __DIR__ . '/mail.local.php';
$localConfig = is_file($localConfigPath) ? require $localConfigPath : [];
if (!is_array($localConfig)) {
    $localConfig = [];
}

return [
    'emailjs_public_key' => (string) (getenv('EMAILJS_PUBLIC_KEY') ?: ($localConfig['emailjs_public_key'] ?? '')),
    'emailjs_private_key' => (string) (getenv('EMAILJS_PRIVATE_KEY') ?: ($localConfig['emailjs_private_key'] ?? '')),
    'emailjs_service_id' => (string) (getenv('EMAILJS_SERVICE_ID') ?: ($localConfig['emailjs_service_id'] ?? '')),
    'emailjs_template_id' => (string) (getenv('EMAILJS_TEMPLATE_ID') ?: ($localConfig['emailjs_template_id'] ?? '')),
    'app_url' => rtrim((string) (getenv('SSIS_APP_URL') ?: ($localConfig['app_url'] ?? '')), '/'),
];
