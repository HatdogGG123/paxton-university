<?php
require_once __DIR__ . '/php/includes/auth.php';
ssis_start_session();
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$resetVerified = !empty($_SESSION['password_reset_user_id']) && !empty($_SESSION['password_reset_email']);
?>
<!DOCTYPE html>
<html lang="en">
<?php include __DIR__ . '/php/includes/head.php'; ?>
<body class="bg-brand-accent flex min-h-screen items-center justify-center p-4 font-sans">
    <main class="w-full max-w-md rounded-xl border border-white/50 bg-white/80 p-8 text-center shadow-xl sm:p-10">
        <div class="mx-auto mb-6 flex h-12 w-12 items-center justify-center rounded-xl border border-gray-200 bg-white text-brand-primary shadow-sm">
            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"></path></svg>
        </div>
        <p class="text-xs font-bold uppercase tracking-widest text-brand-secondary">Paxton University SSIS</p>
        <h1 class="mt-2 text-2xl font-bold text-gray-900">Reset your password</h1>
        <p class="mx-auto mt-2 max-w-xs text-sm leading-relaxed text-gray-500">Enter the email address associated with your account. The system will check it before showing the new password fields.</p>

        <form id="email_check_form" class="mt-8 space-y-4 text-left"<?= $resetVerified ? ' hidden' : '' ?>>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="action" value="check_email">
            <label for="reset_email" class="block text-xs font-semibold uppercase tracking-wide text-gray-700">Account email</label>
            <input type="email" id="reset_email" name="email" required maxlength="120" autocomplete="email" placeholder="you@example.com" class="w-full rounded-lg border border-gray-300 bg-white px-4 py-3 text-sm outline-none transition focus:border-brand-secondary focus:ring-2 focus:ring-brand-secondary/20" <?= $resetVerified ? 'disabled' : '' ?>>
            <button id="check_email_button" class="w-full rounded-lg bg-brand-primary px-4 py-3 text-sm font-semibold text-white transition hover:bg-brand-secondary">Continue</button>
        </form>

        <section id="password_reset_section" class="mt-8 text-left<?= $resetVerified ? '' : ' hidden' ?>">
            <p class="mb-4 rounded-lg bg-emerald-50 p-3 text-sm text-emerald-900">Email found. Choose a new password for <strong id="verified_email"><?= $resetVerified ? htmlspecialchars((string) $_SESSION['password_reset_email'], ENT_QUOTES, 'UTF-8') : '' ?></strong>.</p>
            <form id="password_reset_form" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="action" value="reset_password">
                <div><label for="new_password" class="mb-1 block text-xs font-semibold text-gray-700">New password</label><input type="password" id="new_password" name="new_password" required minlength="8" autocomplete="new-password" class="w-full rounded-lg border border-gray-300 bg-white px-4 py-3 text-sm outline-none focus:border-brand-secondary focus:ring-2 focus:ring-brand-secondary/20"></div>
                <div><label for="confirm_password" class="mb-1 block text-xs font-semibold text-gray-700">Confirm new password</label><input type="password" id="confirm_password" name="confirm_password" required minlength="8" autocomplete="new-password" class="w-full rounded-lg border border-gray-300 bg-white px-4 py-3 text-sm outline-none focus:border-brand-secondary focus:ring-2 focus:ring-brand-secondary/20"></div>
                <p class="text-xs text-gray-500">Use at least 8 characters, including a letter and a number.</p>
                <div class="flex items-center justify-between gap-3"><button type="button" id="toggle_password_visibility" aria-pressed="false" class="text-xs font-semibold text-brand-primary underline">Show passwords</button><button id="reset_password_button" class="rounded-lg bg-brand-primary px-4 py-3 text-sm font-semibold text-white transition hover:bg-brand-secondary">Change password</button></div>
            </form>
        </section>

        <a href="login.php" id="back_to_login" class="mt-7 inline-flex items-center text-xs font-medium text-gray-600 transition hover:text-gray-900 hover:underline">← Back to login</a>
    </main>
    <script src="js/helperFunction.js"></script>
    <script>
        (function ($) {
            const endpoint = 'controller/forgotPasswordController.php';
            $('#email_check_form').on('submit', function (event) {
                event.preventDefault();
                const button = $('#check_email_button');
                button.prop('disabled', true).text('Checking email…');
                $.post(endpoint, $(this).serialize(), null, 'json').done(function (response) {
                    $('#verified_email').text($('#reset_email').val().trim());
                    $('#email_check_form').addClass('hidden');
                    $('#password_reset_section').removeClass('hidden');
                    $('#new_password').trigger('focus');
                    const remaining = response.data && Number.isInteger(response.data.attempts_remaining) ? response.data.attempts_remaining : 5;
                    showToast('success', response.message + ' ' + remaining + ' password reset attempts available.');
                }).fail(function (xhr) {
                    showToast('error', xhr.responseJSON?.message || 'The email could not be checked.');
                }).always(function () {
                    button.prop('disabled', false).text('Continue');
                });
            });

            $('#toggle_password_visibility').on('click', function () {
                const reveal = $(this).attr('aria-pressed') !== 'true';
                $('#new_password, #confirm_password').attr('type', reveal ? 'text' : 'password');
                $(this).attr('aria-pressed', String(reveal)).text(reveal ? 'Hide passwords' : 'Show passwords');
            });

            $('#password_reset_form').on('submit', function (event) {
                event.preventDefault();
                const button = $('#reset_password_button');
                button.prop('disabled', true).text('Updating…');
                $.post(endpoint, $(this).serialize(), null, 'json').done(function (response) {
                    showToast('success', response.message);
                    $('#password_reset_form').addClass('hidden');
                    $('#password_reset_section > p').text('Your password has been changed. Return to Login and sign in with your new password.');
                    $('#back_to_login').text('Continue to login').addClass('rounded-lg bg-brand-primary px-5 py-3 font-semibold text-white hover:bg-brand-secondary');
                }).fail(function (xhr) {
                    showToast('error', xhr.responseJSON?.message || 'The password could not be changed.');
                }).always(function () {
                    button.prop('disabled', false).text('Change password');
                });
            });
        })(window.jQuery);
    </script>
</body>
</html>
