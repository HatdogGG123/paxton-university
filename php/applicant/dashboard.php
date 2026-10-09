<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../../models/Admission.php';
require_once __DIR__ . '/../../models/Notifications.php';
ssis_start_session();
ssis_require_role('applicant');
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$model = new Admission(Database::getConnection());
$application = $model->getApplicantApplication((int)$_SESSION['user']['id']);
$unreadNotificationCount = (new Notifications(Database::getConnection()))->getUnreadCount((int)$_SESSION['user']['id']);
$documents = $application ? $model->applicantDocuments((int)$application['id']) : [];
$applicationError = $_SESSION['application_error'] ?? null;
unset($_SESSION['application_error']);
$allConfirmed = count($documents) > 0;
foreach ($documents as $document) if (($document['review_status'] ?? '') !== 'Confirmed') $allConfirmed = false;
$esc = static fn($v): string => htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<?php include __DIR__ . '/../includes/head.php'; ?>

<body class="bg-gray-50 font-sans text-brand-dark antialiased min-h-screen flex flex-col">
    <?php include __DIR__ . '/../component/sidebar.php'; ?>

    <header class="bg-white border-b">
        <div class="max-w-4xl mx-auto px-5 py-4 flex justify-between items-center">
            <div>
                <p class="font-bold tracking-wide">PAXTON UNIVERSITY</p>
                <p class="text-xs text-gray-500">Applicant Portal</p>
            </div>
            <div class="flex items-center gap-4"><a class="text-sm font-semibold text-brand-primary hover:underline" href="notifications.php">Notifications<?php if ($unreadNotificationCount > 0): ?> <span class="rounded-full bg-blue-100 px-2 py-0.5 text-xs text-blue-800"><?= $unreadNotificationCount > 99 ? '99+' : $unreadNotificationCount ?></span><?php endif; ?></a><a class="text-sm text-red-700" href="../logout.php">Sign out</a></div>
        </div>
    </header>
    <main class="max-w-4xl mx-auto px-4 py-8 space-y-6">
        <section>
            <p class="text-xs uppercase tracking-widest text-brand-secondary font-bold">Admissions</p>
            <h1 class="text-2xl font-bold mt-1">Application status</h1>
        </section>
        <?php if ($application): ?><section class="bg-white rounded-xl border shadow-sm p-6 space-y-3">
                <div class="flex flex-wrap justify-between gap-3">
                    <div>
                        <p class="text-xs text-gray-500">Application number</p>
                        <p class="font-semibold"><?= $esc($application['application_no']) ?></p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">Program</p>
                        <p class="font-semibold"><?= $esc($application['program_name']) ?></p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">Status</p>
                        <p class="font-bold"><?= $esc($application['status']) ?></p>
                    </div>
                </div><?php if ($application['remarks']): ?><p class="rounded bg-gray-50 p-3 text-sm"><?= $esc($application['remarks']) ?></p><?php endif; ?><p class="text-sm text-gray-600">Submit each required document online, or bring it to the Registrar’s office for face-to-face recording. The Registrar will update its status here.</p>
            </section>
            <section class="bg-white rounded-xl border shadow-sm p-6">
                <h2 class="font-bold text-lg">Required documents</h2>
                <div class="mt-4 space-y-4"><?php foreach ($documents as $d): ?><article class="border rounded-lg p-4" data-applicant-document="<?= (int) $d['requirement_id'] ?>">
                            <div class="flex justify-between gap-2">
                                <div>
                                    <h3 class="font-semibold"><?= $esc($d['document_name']) ?></h3>
                                    <p class="text-xs text-gray-500 mt-1">Status: <?= $esc($d['review_status'] ?: 'Not submitted') ?><?= $d['submission_method'] ? ' · ' . $esc($d['submission_method']) : '' ?></p><?php if ($d['review_message']): ?><p class="mt-2 rounded bg-amber-50 p-2 text-sm text-amber-900"><?= $esc($d['review_message']) ?></p><?php endif; ?>
                                </div><?php if ($d['review_status'] === 'Confirmed'): ?><span class="text-emerald-700 text-xs font-semibold">Confirmed</span><?php endif; ?>
                            </div>
                            <?php if (!$d['document_id'] || $d['review_status'] === 'Resubmit Needed'): ?><form data-page-ajax action="../../controller/admissionController.php" method="post" enctype="multipart/form-data" class="mt-4 flex flex-col sm:flex-row gap-2 items-start sm:items-center"><input type="hidden" name="csrf_token" value="<?= $esc($_SESSION['csrf_token']) ?>"><input type="hidden" name="action" value="submit_document"><input type="hidden" name="requirement_id" value="<?= (int)$d['requirement_id'] ?>"><input type="file" name="document_file" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" required class="w-full text-xs"><button class="rounded bg-brand-primary px-4 py-2 text-xs font-semibold text-white">Upload document</button></form><?php endif; ?>
                        </article><?php endforeach; ?><?php if (!$documents): ?><p class="text-sm text-gray-600">The Registrar has not configured required documents for this program yet. Please check back later.</p><?php endif; ?></div>
            </section>
            <?php if ($allConfirmed && $application['status'] !== 'Approved'): ?><section class="rounded-xl bg-amber-50 border border-amber-200 p-6">
                    <h2 class="font-bold text-amber-900">All required documents are confirmed</h2>
                    <p class="text-sm text-amber-800 mt-1">Your application is currently <?= $esc($application['status']) ?>. The Registrar must approve the application before you can activate your student account. The activation button will appear here once that final review is complete.</p>
                </section><?php endif; ?>
            <?php if ($application['status'] === 'Approved' && $allConfirmed): ?><section class="rounded-xl bg-emerald-50 border border-emerald-200 p-6">
                    <h2 class="font-bold text-emerald-900">Your application is approved</h2>
                    <p class="text-sm text-emerald-800 mt-1">Confirm below to activate your student account and continue to enrollment. Your username will change to your student number; your password stays the same.</p>
                    <form data-page-ajax action="../../controller/admissionController.php" method="post" class="mt-4"><input type="hidden" name="csrf_token" value="<?= $esc($_SESSION['csrf_token']) ?>"><input type="hidden" name="action" value="confirm_approval"><button class="rounded bg-brand-primary px-5 py-3 text-sm font-semibold text-white">Confirm and activate student account</button></form>
                </section><?php endif; ?>
        <?php else: ?><section class="bg-white rounded-xl border p-6 text-sm text-gray-600">We could not find an application linked to this account. Contact the Registrar for help.</section><?php endif; ?>
    </main>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

    <?php if ($applicationError): ?>
        <script>
            showToast('error', <?= json_encode($applicationError, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>);
        </script>
    <?php endif; ?>
<script>
(function($) {
    if (!$) return;
    $(document).on('submit', 'form[data-page-ajax]', function(event) {
        event.preventDefault();
        const form = this;
        const button = form.querySelector('[type="submit"]');
        if (button?.disabled) return;
        if (button) button.disabled = true;
        $.ajax({ url: form.getAttribute('action'), method: 'POST', data: new FormData(form), processData: false, contentType: false, dataType: 'json', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .done(function(response) {
                if (!response || response.status === 'error') {
                    showToast('error', response?.message || 'The application request could not be completed.');
                    if (button) button.disabled = false;
                    return;
                }
                const redirect = response.redirect || response.data?.redirect;
                const action = form.querySelector('[name="action"]')?.value;
                if (action === 'confirm_approval' && redirect) { window.location.assign(redirect); return; }
                const requirementId = form.querySelector('[name="requirement_id"]')?.value;
                const card = form.closest('[data-applicant-document]') || document.querySelector('[data-applicant-document="' + Number(requirementId) + '"]');
                if (card) {
                    const status = card.querySelector('.text-gray-500');
                    if (status) status.textContent = 'Status: Submitted';
                    form.remove();
                    const received = document.createElement('span'); received.className = 'text-xs font-semibold text-emerald-700'; received.textContent = 'Document submitted';
                    card.querySelector('.flex.justify-between')?.appendChild(received);
                }
                showToast('success', response.message || 'Application updated successfully.');
            })
            .fail(function(xhr) {
                showToast('error', xhr.responseJSON?.message || 'The application request failed. Please try again.');
                if (button) button.disabled = false;
            });
    });
})(window.jQuery);
</script>
</body>

</html>
