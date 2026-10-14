<?php
require_once __DIR__ . '/php/includes/auth.php';
require_once __DIR__ . '/models/Admission.php';

ssis_start_session();
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$admissionModel = new Admission(Database::getConnection());
$programs = $admissionModel->programs();
$submittedNumber = is_string($_GET['submitted'] ?? null) ? trim($_GET['submitted']) : '';
$initialRequirements = [];

if ($submittedNumber !== '' && strtolower((string) ($_SESSION['user']['role'] ?? '')) === 'applicant') {
    $submittedApplication = $admissionModel->getApplicantApplication((int) $_SESSION['user']['id']);
    if ($submittedApplication) $initialRequirements = $admissionModel->applicantDocuments((int) $submittedApplication['id']);
}

$credentials = $_SESSION['applicant_credentials'] ?? null;
if (is_array($credentials) && ($credentials['application_no'] ?? '') === $submittedNumber) {
    unset($_SESSION['applicant_credentials']);
} else {
    $credentials = null;
}

$error = $_SESSION['application_error'] ?? null;
unset($_SESSION['application_error']);
?>

<!DOCTYPE html>
<html lang="en">
<?php include __DIR__ . '/php/includes/head.php'; ?>

<body class="font-sans antialiased text-brand-dark min-h-screen relative flex flex-col overflow-x-hidden bg-brand-dark">
    <div class="absolute inset-0 z-0 pointer-events-none"><img src="https://images.pexels.com/photos/28412565/pexels-photo-28412565.jpeg" alt="" class="w-full h-full object-cover opacity-30">
        <div class="absolute inset-0 bg-gradient-to-r from-brand-dark via-brand-dark/80 to-brand-dark/40"></div>
    </div>
    <!-- Top Logo Button -->
    <header class="relative z-10 px-8 py-8 md:px-12">
        <a href="index.php" class="w-10 h-10 rounded-md border border-brand-accent/30 backdrop-blur-md bg-brand-primary/40 flex items-center justify-center shadow-lg hover:border-brand-accent transition">
            <svg class="lucide lucide-arrow-left preview-icon size-5 text-white" xmlns="http://www.w3.org/2000/svg" width="0" height="0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <path d="m12 19-7-7 7-7" />
                <path d="M19 12H5" />
            </svg>
        </a>
    </header>
    <main class="relative z-10 px-5 md:px-12 py-6 flex-1 flex justify-center">
        <div class="w-full max-w-2xl self-center">
            <?php if (isset($_GET['submitted'])): ?>
                <section class="bg-brand-accent rounded-xl p-8 md:p-10 shadow-2xl text-center">
                    <h1 class="text-2xl font-bold">Application received</h1>
                    <p class="mt-3 text-sm">Your application number is <strong><?= htmlspecialchars($submittedNumber, ENT_QUOTES, 'UTF-8') ?></strong>. Keep it for your records. The Registrar will review your application.</p>
                    <?php if ($credentials): ?><div class="mt-6 rounded-lg border border-brand-primary/20 bg-white/70 p-4 text-left">
                            <h2 class="font-bold">Your applicant account is ready</h2>
                            <p class="mt-2 text-xs text-gray-600">Save these credentials. The temporary password is shown only once.</p>
                            <p class="mt-3 text-sm">Username: <strong><?= htmlspecialchars($credentials['application_no'], ENT_QUOTES, 'UTF-8') ?></strong></p>
                            <p class="mt-1 text-sm">Temporary password: <code class="font-bold select-all"><?= htmlspecialchars($credentials['password'], ENT_QUOTES, 'UTF-8') ?></code></p>
                        </div><?php endif; ?>
                    <div class="mt-6 rounded-lg border border-brand-primary/20 bg-white/70 p-4 text-left">
                        <h2 class="font-bold">Documents to submit</h2>
                        <p class="mt-1 text-xs text-gray-600">These requirements are for the program you applied to. You can upload them from your applicant dashboard or submit them in person to the Registrar.</p><?php if ($initialRequirements): ?><ul class="mt-3 space-y-2"><?php foreach ($initialRequirements as $requirement): ?><li class="flex flex-wrap justify-between gap-2 border-b border-gray-100 pb-2 text-sm"><span><?= htmlspecialchars($requirement['document_name'], ENT_QUOTES, 'UTF-8') ?></span><span class="text-xs font-medium text-gray-500"><?= htmlspecialchars($requirement['review_status'] ?: 'Not submitted', ENT_QUOTES, 'UTF-8') ?></span></li><?php endforeach; ?></ul><?php else: ?><p class="mt-3 text-sm text-amber-800">No requirements have been configured for this program yet. Check your dashboard again or contact the Registrar.</p><?php endif; ?>
                    </div>
                    <a href="php/applicant/dashboard.php" class="inline-flex mt-5 bg-brand-primary text-white px-6 py-3 rounded-lg text-sm font-semibold">Open applicant dashboard and submit documents</a>
                </section>
            <?php else: ?>
                <section class="bg-brand-accent rounded-xl p-6 sm:p-8 shadow-2xl border border-brand-accent/20">
                    <div class="mb-6">
                        <p class="text-xs uppercase tracking-widest font-bold text-brand-secondary">Paxton University admissions</p>
                        <h1 class="text-2xl font-bold mt-1">Student Application</h1>
                        <p class="text-xs text-brand-primary mt-2">Enter your details to apply for admission.</p>
                    </div>
                    <section id="application-result" class="hidden rounded-lg border border-brand-primary/20 bg-white/70 p-5" aria-live="polite">
                        <h2 class="font-bold">Application received</h2>
                        <p class="mt-2 text-sm">Application number: <strong id="application-number"></strong></p>
                        <p class="mt-1 text-sm">Username: <strong id="applicant-username"></strong></p>
                        <p class="mt-1 text-sm">Temporary password: <code id="applicant-password" class="font-bold select-all"></code></p>
                        <p class="mt-2 text-xs text-gray-600">Save these credentials. Your temporary password is shown only once.</p>
                        <a href="php/applicant/dashboard.php" class="inline-flex mt-4 bg-brand-primary text-white px-5 py-2.5 rounded-lg text-sm font-semibold">Open applicant dashboard</a>
                    </section>
                    <form id="application-form" data-page-ajax action="controller/admissionController.php" method="post" class="space-y-5">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="action" value="submit_application">
                        <div class="grid sm:grid-cols-2 gap-4">
                            <label class="text-xs font-semibold">First name<input required name="first_name" maxlength="60" class="mt-1 w-full rounded-md border border-brand-primary/20 px-3 py-2.5 text-sm" /></label>
                            <label class="text-xs font-semibold">Middle name (optional)<input name="middle_name" maxlength="60" class="mt-1 w-full rounded-md border border-brand-primary/20 px-3 py-2.5 text-sm" /></label>
                            <label class="text-xs font-semibold">Last name<input required name="last_name" maxlength="60" class="mt-1 w-full rounded-md border border-brand-primary/20 px-3 py-2.5 text-sm" /></label>
                            <label class="text-xs font-semibold">Email<input required type="email" name="email" maxlength="120" class="mt-1 w-full rounded-md border border-brand-primary/20 px-3 py-2.5 text-sm" /></label>
                            <label class="text-xs font-semibold">Contact number<input required type="tel" inputmode="numeric" name="contact_number" minlength="11" maxlength="11" pattern="[0-9]{11}" title="Enter exactly 11 digits using numbers only." class="mt-1 w-full rounded-md border border-brand-primary/20 px-3 py-2.5 text-sm" /></label>
                            <label class="text-xs font-semibold">Program<select required name="program_id" class="mt-1 w-full rounded-md border border-brand-primary/20 px-3 py-2.5 text-sm bg-white">
                                    <option value="">Select a program</option><?php foreach ($programs as $program): ?><option value="<?= (int) $program['id'] ?>"><?= htmlspecialchars($program['name'] . ' (' . $program['code'] . ')', ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?>
                                </select></label>
                            <label class="text-xs font-semibold sm:col-span-2">Home address<input required name="address" maxlength="255" class="mt-1 w-full rounded-md border border-brand-primary/20 px-3 py-2.5 text-sm" /></label>
                        </div>
                        <section class="rounded-lg border border-brand-primary/20 bg-white/70 p-4 text-sm" aria-labelledby="privacy-notice-title">
                            <h2 id="privacy-notice-title" class="font-semibold">Data Privacy Notice and Consent</h2>
                            <div class="mt-2 flex flex-wrap items-center justify-between gap-3">
                                <label for="privacy-consent" class="flex items-start gap-2">
                                    <input id="privacy-consent" name="privacy_consent" value="1" type="checkbox" class="mt-1 size-4 shrink-0 accent-brand-primary">
                                    <span>I have read and agree to the Data Privacy Notice.</span>
                                </label>
                                <button id="privacy-read-more" type="button" aria-expanded="false" aria-controls="privacy-full-notice" class="font-semibold text-brand-primary">Read more…</button>
                            </div>
                            <div id="privacy-full-notice" class="hidden mt-3 border-t border-brand-primary/10 pt-3 text-xs leading-relaxed text-gray-700">
                                By submitting this application form, you acknowledge and agree that the system administrators will collect, record, process, store, and retain your personal information (including your name, contact details, and submitted credentials) solely for the purpose of processing, evaluating, and managing your application request. All data collected is handled with strict confidentiality in accordance with the Data Privacy Act of 2012, protected by secure technical safeguards, and accessible only to authorized personnel. Your submission serves as your free, explicit, and informed consent to the processing of your personal data for these specified operational purposes.
                            </div>
                        </section>
                        <button type="submit" class="w-full sm:w-auto bg-brand-primary hover:bg-brand-dark text-white font-semibold px-7 py-3 rounded-lg text-sm">Submit application</button>
                    </form>
                </section><?php endif; ?>
        </div>
    </main>
    <footer class="relative z-10 text-center text-xs text-brand-accent/70 py-6">Paxton University Student Services Information System</footer>
    <script src="js/helperFunction.js"></script>
    <script>
        document.querySelector('#privacy-read-more')?.addEventListener('click', function() {
            const notice = document.querySelector('#privacy-full-notice');
            const expanded = this.getAttribute('aria-expanded') === 'true';
            this.setAttribute('aria-expanded', String(!expanded));
            this.textContent = expanded ? 'Read more…' : 'Read less';
            notice?.classList.toggle('hidden', expanded);
        });
        const contactNumberInput = document.querySelector('#application-form [name="contact_number"]');
        contactNumberInput?.addEventListener('input', function() {
            this.setCustomValidity(this.value !== '' && !/^\d{11}$/.test(this.value) ?
                'Contact number must contain exactly 11 digits using numbers only.' :
                '');
        });
        $('#application-form').on('submit', function(event) {
            event.preventDefault();
            const form = this;
            const button = form.querySelector('[type="submit"]');
            if (button?.disabled) return;
            if (!form.querySelector('[name="privacy_consent"]')?.checked) {
                showToast('error', 'Please review and accept the Data Privacy Notice and Consent before submitting.');
                form.querySelector('[name="privacy_consent"]')?.focus();
                return;
            }
            if (button) button.disabled = true;
            $.ajax({
                    url: form.getAttribute('action'),
                    method: 'POST',
                    data: $(form).serialize(),
                    dataType: 'json',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .done(function(result) {
                    if (!result || !result.application_no) {
                        showToast('error', result?.message || 'Your application could not be submitted.');
                        if (button) button.disabled = false;
                        return;
                    }
                    $('#application-number, #applicant-username').text(result.application_no);
                    $('#applicant-password').text(result.password);
                    $('#application-form').addClass('hidden');
                    $('#application-result').removeClass('hidden');
                })
                .fail(function(xhr, textStatus) {
                    const message = xhr.responseJSON?.message || (xhr.status === 0 ?
                        'Could not reach the admissions server. Check your connection and try again.' :
                        'The admissions server returned an unexpected response. Please try again.');
                    console.error('Application submission failed', {
                        status: xhr.status,
                        textStatus: textStatus
                    });
                    showToast('error', message);
                    if (button) button.disabled = false;
                });
        });
    </script>
    <?php if ($error): ?><script>
            showToast('error', <?= json_encode($error, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>);
        </script><?php endif; ?>
</body>

</html>