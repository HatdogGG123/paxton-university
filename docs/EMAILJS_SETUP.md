# EmailJS setup for password reset email

Password reset emails are sent by the PHP server to EmailJS's HTTP API using cURL. The browser never receives the EmailJS private key. The reset token is generated and validated by SSIS; the email template only delivers the resulting link.

## Configure EmailJS

1. In EmailJS, add and connect an email service (for example, an account you control).
2. Create an email template for password resets. Set **To Email** to `{{recipient_email}}`. Include `{{reset_link}}` in the message body and state that it expires after `{{expires_minutes}}` minutes. Keep the subject and body fixed; do not let the requester supply template content.
3. In the EmailJS dashboard, note the **Public Key**, **Private Key**, **Service ID**, and **Template ID**. Enable private-key authorization for API requests if the account setting requires it.
4. If you enabled the EmailJS domain allowlist, add the app origin only: `http://localhost` for local XAMPP or `https://your-domain.example` for live hosting (no application path). SSIS sends this origin with its server-side API request.

## Local XAMPP

1. Copy `config/mail.local.php.example` to `config/mail.local.php`.
2. Enter the four EmailJS values and set `app_url` to the base address where this app runs, such as `http://localhost/ssis`. Do not add `/forgotPassword.php`; SSIS appends that path.
3. Confirm PHP cURL is enabled, then restart Apache if you changed PHP configuration.
4. Request a reset and check EmailJS's email history and the PHP error log if delivery fails. Open the reset link from the same machine/network that requested it because the reset token is IP-bound.

## InfinityFree

1. Upload the updated application, including `services/EmailJsMailer.php`, `config/mail.php`, and `config/.htaccess`.
2. In the site's `htdocs/config` folder, create `mail.local.php` with the four EmailJS values. Set `app_url` to the live HTTPS base URL, adding the SSIS subfolder if applicable. Keep the private key in this PHP config file; do not put it in JavaScript or a public page. `config/.htaccess` blocks direct web requests to the configuration directory.
3. For an existing database, import `migrations/20261010_password_resets.sql` in phpMyAdmin. Fresh installs get the table from `ssis-schema.sql`.
4. Request a reset and confirm the message in EmailJS's email history. If it fails, check the PHP error log for `SSIS EmailJS delivery failed`. InfinityFree must allow the PHP cURL request to `api.emailjs.com` over HTTPS; the server error log will show a connection failure if it cannot connect.

The host-specific `config/mail.local.php` is excluded from Git. Keep separate local and live credentials, and rotate the private key if it is ever exposed.
