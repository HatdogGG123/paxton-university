<?php
require_once __DIR__ . '/../includes/auth.php';

ssis_require_role(['admin', 'registrar']);

require_once __DIR__ . '/../../models/Admission.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$model = new Admission(Database::getConnection());

$applications = $model->queue();

$admissionError = $_SESSION['admission_error'] ?? null;

unset($_SESSION['admission_error']);

$requirements = $model->requirements();

$programs = $model->programs();

$departments = $model->academicDepartmentsWithPrograms();

$requirementsByProgram = [];

foreach ($requirements as $requirement) {
    $requirementsByProgram[(int) $requirement['program_id']][] = $requirement;
}

$documents = [];
if ($applications) {
    $documents = $model->documentsForApplications(array_column($applications, 'id'));
}

$isAdmin = strtolower((string) $_SESSION['user']['role']) === 'admin';

$esc = static fn($value): string => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admissions - Paxton University</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Plus Jakarta Sans', 'sans-serif']
                    },
                    colors: {
                        brand: {
                            primary: '#344e41',
                            secondary: '#588157',
                            accent: '#dad7cd',
                            dark: '#202020'
                        }
                    }
                }
            }
        }
    </script>
</head>

<body class="min-h-screen bg-gray-50 font-sans text-brand-dark">
    <?php include __DIR__ . '/../component/sidebar.php'; ?>
    <main class="mx-auto max-w-6xl space-y-6 px-4 py-8">
        <section>
            <h1 class="text-2xl font-bold">Admissions</h1>
            <p class="mt-1 text-sm text-gray-500">Open an applicant’s document list to review submissions and update each requirement.</p>
        </section>
        <?php if (!empty($_GET['updated'])): ?><script>
                showToast('success', 'Admission record updated.');
            </script><?php endif; ?>
        <?php if ($admissionError): ?><script>
                showToast('error', <?= json_encode($admissionError, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>);
            </script><?php endif; ?>

        <section class="rounded-lg border bg-white p-5">
            <div class="flex flex-wrap items-start justify-between gap-2">
                <div>
                    <h2 class="font-bold">Document requirements</h2>
                    <p class="mt-1 text-xs text-gray-500">Select an academic department and program to view its admission checklist. Requirements are specific to each program.</p>
                </div>
            </div>
            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                <label class="text-xs font-semibold text-gray-700">Academic department
                    <select id="requirement_department" class="mt-1 w-full rounded border px-3 py-2 text-sm">
                        <option value="">Select a department</option>
                        <?php foreach ($departments as $department): ?><option value="<?= (int) $department['id'] ?>"><?= $esc($department['name'] . ' (' . $department['code'] . ')') ?></option><?php endforeach; ?>
                    </select>
                </label>
                <label class="text-xs font-semibold text-gray-700">Program
                    <select id="requirement_program" disabled class="mt-1 w-full rounded border px-3 py-2 text-sm disabled:cursor-not-allowed disabled:bg-gray-100">
                        <option value="">Choose a department first</option>
                        <?php foreach ($programs as $program): ?><option value="<?= (int) $program['id'] ?>" data-department="<?= (int) $program['department_id'] ?>"><?= $esc($program['name'] . ' (' . $program['code'] . ')') ?></option><?php endforeach; ?>
                    </select>
                </label>
            </div>
            <?php if ($isAdmin): ?>
                <form id="add_requirement_form" data-page-ajax action="../../controller/admissionController.php" method="post" class="mt-4 hidden grid gap-3 sm:grid-cols-[1fr_auto]">
                    <input type="hidden" name="csrf_token" value="<?= $esc($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="action" value="save_requirement">
                    <input id="add_requirement_program_id" type="hidden" name="program_id" value="">
                    <input name="document_name" required maxlength="120" placeholder="New required document name for the selected program" class="rounded border px-3 py-2 text-sm">
                    <button class="rounded bg-brand-primary px-4 py-2 text-sm font-semibold text-white">Add requirement</button>
                </form>
            <?php endif; ?>
            <p id="requirement_program_prompt" class="mt-4 rounded bg-gray-50 p-4 text-sm text-gray-500"><?= $departments ? 'Choose a department and program to display its document requirements.' : 'No active academic departments with programs are configured.' ?></p>
            <?php foreach ($programs as $program): $programId = (int) $program['id'];
                $programRequirements = $requirementsByProgram[$programId] ?? []; ?>
                <div class="requirement-program-list mt-4 hidden divide-y" data-program="<?= $programId ?>">
                    <?php if ($programRequirements): foreach ($programRequirements as $requirement): ?>
                            <div class="grid gap-3 py-3 md:grid-cols-[1fr_2fr] md:items-center">
                                <div>
                                    <p class="text-sm font-semibold"><?= $esc($requirement['document_name']) ?></p>
                                    <p class="text-xs text-gray-500"><?= $esc($program['name']) ?></p>
                                </div>
                                <?php if ($isAdmin): ?>
                                    <div class="flex flex-wrap gap-2">
                                        <form data-page-ajax action="../../controller/admissionController.php" method="post" class="flex min-w-0 flex-1 flex-wrap gap-2">
                                            <input type="hidden" name="csrf_token" value="<?= $esc($_SESSION['csrf_token']) ?>"><input type="hidden" name="action" value="save_requirement"><input type="hidden" name="requirement_id" value="<?= (int) $requirement['id'] ?>"><input type="hidden" name="program_id" value="<?= $programId ?>">
                                            <input name="document_name" required maxlength="120" value="<?= $esc($requirement['document_name']) ?>" class="min-w-40 flex-1 rounded border px-2 py-2 text-xs">
                                            <button class="rounded bg-gray-100 px-3 py-2 text-xs font-semibold">Save changes</button>
                                        </form>
                                        <form data-page-ajax action="../../controller/admissionController.php" method="post" onsubmit="return confirm('Delete this requirement from the active applicant checklist? Existing submissions will be retained.');">
                                            <input type="hidden" name="csrf_token" value="<?= $esc($_SESSION['csrf_token']) ?>"><input type="hidden" name="action" value="delete_requirement"><input type="hidden" name="requirement_id" value="<?= (int) $requirement['id'] ?>">
                                            <button class="rounded bg-red-50 px-3 py-2 text-xs font-semibold text-red-700">Delete</button>
                                        </form>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach;
                    else: ?><p class="py-3 text-sm text-gray-500">No active document requirements are configured for this program yet.</p><?php endif; ?>
                </div>
            <?php endforeach; ?>
        </section>

        <section class="rounded-lg border bg-white p-4">
            <label for="application_search" class="mb-2 block text-xs font-semibold">Search applicants</label>
            <input id="application_search" type="search" placeholder="Search by name or application number" class="w-full rounded-md border px-3 py-2 text-sm">
        </section>
        <p id="application_search_empty" class="hidden rounded border bg-white p-5 text-center text-sm text-gray-500">No applicants match your search.</p>
        <p id="application_queue_empty" class="<?= $applications ? 'hidden ' : '' ?>rounded border bg-white p-8 text-center text-gray-500">No applications need review.</p>

        <?php foreach ($applications as $application): ?>
            <?php $appDocuments = $documents[(int) $application['id']];
            $submittedCount = count(array_filter($appDocuments, static fn(array $document): bool => !empty($document['id']))); ?>
            <article class="application-card overflow-hidden rounded-lg border bg-white shadow-sm" data-application-id="<?= (int) $application['id'] ?>" data-search="<?= $esc(mb_strtolower($application['application_no'] . ' ' . $application['first_name'] . ' ' . $application['middle_name'] . ' ' . $application['last_name'])) ?>">
                <header class="flex flex-col gap-3 border-b p-5 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-xs text-gray-500"><?= $esc($application['application_no']) ?> · <?= $esc($application['submitted_at']) ?></p>
                        <h2 class="mt-1 text-lg font-bold"><?= $esc(trim($application['first_name'] . ' ' . $application['middle_name'] . ' ' . $application['last_name'])) ?></h2>
                        <p class="text-sm text-gray-600"><?= $esc($application['program_name']) ?> · <?= $esc($application['email']) ?> · <?= $esc($application['contact_number']) ?></p>
                    </div>
                    <span class="self-start rounded-full bg-brand-accent/60 px-3 py-1 text-xs font-bold"><?= $esc($application['status']) ?></span>
                </header>
                <div class="p-5">
                    <details class="group">
                        <summary class="flex cursor-pointer list-none items-center justify-between gap-3 rounded-md bg-gray-50 px-4 py-3 text-sm font-semibold hover:bg-gray-100">
                            <span>Document checklist <span data-submitted-count class="ml-1 text-xs font-normal text-gray-500"><?= $submittedCount ?> of <?= count($appDocuments) ?> submitted</span></span>
                            <span class="text-brand-primary transition-transform group-open:rotate-180">⌄</span>
                        </summary>
                        <div class="mt-3 space-y-3">
                            <?php foreach ($appDocuments as $document): ?>
                                <section class="rounded-md border p-4" data-admission-document data-requirement-id="<?= (int) $document['requirement_id'] ?>" data-document-submitted="<?= !empty($document['id']) ? '1' : '0' ?>">
                                    <div class="flex flex-wrap items-start justify-between gap-3">
                                        <div>
                                            <h3 class="text-sm font-semibold"><?= $esc($document['document_name']) ?></h3>
                                            <p data-document-status class="mt-1 text-xs text-gray-500">Status: <?= $esc($document['review_status'] ?: 'Not submitted') ?><?= $document['submission_method'] ? ' · ' . $esc($document['submission_method']) : '' ?></p><?php if ($document['review_message']): ?><p data-document-review-message class="mt-2 rounded bg-amber-50 p-2 text-sm text-amber-900"><?= $esc($document['review_message']) ?></p><?php endif; ?>
                                        </div>
                                        <?php if (!empty($document['file_path'])): ?><a target="_blank" rel="noopener" class="inline-flex rounded border border-brand-primary px-3 py-2 text-xs font-semibold text-brand-primary hover:bg-green-50" href="../../controller/admissionDocumentController.php?id=<?= (int) $document['id'] ?>">View document</a><?php elseif (!empty($document['id'])): ?><span data-document-received class="rounded bg-gray-100 px-3 py-2 text-xs text-gray-600">Received in person</span><?php endif; ?>
                                    </div>
                                    <?php if (empty($document['id']) || $document['review_status'] === 'Resubmit Needed'): ?>
                                        <form data-page-ajax action="../../controller/admissionController.php" method="post" class="mt-3"><input type="hidden" name="csrf_token" value="<?= $esc($_SESSION['csrf_token']) ?>"><input type="hidden" name="action" value="submit_face_to_face"><input type="hidden" name="application_id" value="<?= (int) $application['id'] ?>"><input type="hidden" name="requirement_id" value="<?= (int) $document['requirement_id'] ?>"><button class="rounded bg-gray-100 px-3 py-2 text-xs font-semibold">Record face-to-face submission</button></form>
                                    <?php endif; ?>
                                    <?php if (!empty($document['id']) && $document['review_status'] !== 'Confirmed'): ?>
                                        <form data-review-document-form data-page-ajax action="../../controller/admissionController.php" method="post" class="mt-3 space-y-2 rounded bg-gray-50 p-3"><input type="hidden" name="csrf_token" value="<?= $esc($_SESSION['csrf_token']) ?>"><input type="hidden" name="action" value="review_document"><input type="hidden" name="document_id" value="<?= (int) $document['id'] ?>">
                                            <div class="flex flex-wrap gap-2"><select name="document_status" class="rounded border px-3 py-2 text-xs">
                                                    <option value="Confirmed">Approve document</option>
                                                    <option value="Resubmit Needed">Request resubmission</option>
                                                </select><select name="message_preset" class="rounded border px-3 py-2 text-xs">
                                                    <option value="">Select guidance if resubmitting</option>
                                                    <option>Document is incomplete</option>
                                                    <option>Image or scan is unreadable</option>
                                                    <option>Submit a certified copy</option>
                                                    <option>Name or details do not match</option>
                                                    <option>Custom message</option>
                                                </select><button class="rounded bg-brand-primary px-3 py-2 text-xs font-semibold text-white">Save document decision</button></div>
                                            <input name="document_message" maxlength="500" placeholder="Add instructions for resubmission (required when requesting resubmission)" class="w-full rounded border px-3 py-2 text-xs">
                                        </form>
                                    <?php elseif (!empty($document['id']) && $document['review_status'] === 'Confirmed'): ?><p data-document-decision class="mt-3 text-xs font-semibold text-emerald-700">Document approved</p><?php elseif (empty($document['id'])): ?><p data-document-empty class="mt-2 text-xs text-gray-500">The applicant has not submitted this requirement yet.</p><?php endif; ?>
                                    <template data-review-document-template>
                                        <form data-review-document-form data-page-ajax action="../../controller/admissionController.php" method="post" class="mt-3 space-y-2 rounded bg-gray-50 p-3"><input type="hidden" name="csrf_token" value="<?= $esc($_SESSION['csrf_token']) ?>"><input type="hidden" name="action" value="review_document"><input type="hidden" name="document_id" value="">
                                            <div class="flex flex-wrap gap-2"><select name="document_status" class="rounded border px-3 py-2 text-xs">
                                                    <option value="Confirmed">Approve document</option>
                                                    <option value="Resubmit Needed">Request resubmission</option>
                                                </select><select name="message_preset" class="rounded border px-3 py-2 text-xs">
                                                    <option value="">Select guidance if resubmitting</option>
                                                    <option>Document is incomplete</option>
                                                    <option>Image or scan is unreadable</option>
                                                    <option>Submit a certified copy</option>
                                                    <option>Name or details do not match</option>
                                                    <option>Custom message</option>
                                                </select><button class="rounded bg-brand-primary px-3 py-2 text-xs font-semibold text-white">Save document decision</button></div>
                                            <input name="document_message" maxlength="500" placeholder="Add instructions for resubmission (required when requesting resubmission)" class="w-full rounded border px-3 py-2 text-xs">
                                        </form>
                                    </template>
                                </section>
                            <?php endforeach; ?>
                            <?php if (!$appDocuments): ?><p class="rounded bg-amber-50 p-3 text-sm text-amber-900">No requirements are configured for this applicant’s program.</p><?php endif; ?>
                        </div>
                    </details>
                    <form data-review-application-form data-page-ajax action="../../controller/admissionController.php" method="post" class="mt-5 grid gap-2 border-t pt-4 sm:grid-cols-[1fr_2fr_auto]"><input type="hidden" name="csrf_token" value="<?= $esc($_SESSION['csrf_token']) ?>"><input type="hidden" name="action" value="review_application"><input type="hidden" name="application_id" value="<?= (int) $application['id'] ?>"><select name="status" class="rounded border px-3 py-2 text-sm">
                            <option value="Under Review" <?= $application['status'] === 'Under Review' ? ' selected' : '' ?>>Under review</option>
                            <option value="Documents Required" <?= $application['status'] === 'Documents Required' ? ' selected' : '' ?>>Documents required</option>
                            <option value="Approved" <?= $application['status'] === 'Approved' ? ' selected' : '' ?>>Approved</option>
                            <option value="Rejected" <?= $application['status'] === 'Rejected' ? ' selected' : '' ?>>Rejected</option>
                        </select><input name="remarks" maxlength="500" value="<?= $esc($application['remarks']) ?>" placeholder="Application note" class="rounded border px-3 py-2 text-sm"><button class="rounded bg-brand-primary px-4 py-2 text-sm font-semibold text-white">Save application status</button></form>
                </div>
            </article>
        <?php endforeach; ?>
    </main>
    <script>
        const departmentSelector = document.getElementById('requirement_department');
        const programSelector = document.getElementById('requirement_program');
        const programPrompt = document.getElementById('requirement_program_prompt');
        const requirementLists = Array.from(document.querySelectorAll('.requirement-program-list'));
        const addRequirementForm = document.getElementById('add_requirement_form');
        const addRequirementProgramId = document.getElementById('add_requirement_program_id');

        function updateRequirementProgramOptions() {
            const departmentId = departmentSelector.value;
            let programCount = 0;
            Array.from(programSelector.options).forEach((option) => {
                if (!option.value) return;
                const matches = option.dataset.department === departmentId;
                option.hidden = !matches;
                if (matches) programCount++;
            });
            programSelector.value = '';
            programSelector.disabled = !departmentId || programCount === 0;
            programSelector.options[0].textContent = departmentId ?
                (programCount ? 'Select a program' : 'No programs available') :
                'Choose a department first';
            updateRequirementList();
        }

        function updateRequirementList() {
            const programId = programSelector.value;
            requirementLists.forEach((list) => list.classList.toggle('hidden', list.dataset.program !== programId));
            const hasProgram = Boolean(programId);
            programPrompt.classList.toggle('hidden', hasProgram);
            addRequirementForm?.classList.toggle('hidden', !hasProgram);
            if (addRequirementProgramId) addRequirementProgramId.value = programId;
        }

        departmentSelector.addEventListener('change', updateRequirementProgramOptions);
        programSelector.addEventListener('change', updateRequirementList);

        const search = document.getElementById('application_search');
        const cards = Array.from(document.querySelectorAll('.application-card'));
        const emptyMessage = document.getElementById('application_search_empty');
        const queueEmpty = document.getElementById('application_queue_empty');

        function refreshApplicationEmptyStates() {
            const connectedCards = cards.filter((card) => card.isConnected);
            const visibleCount = connectedCards.filter((card) => !card.classList.contains('hidden')).length;
            queueEmpty?.classList.toggle('hidden', connectedCards.length > 0);
            emptyMessage?.classList.toggle('hidden', connectedCards.length === 0 || visibleCount > 0);
        }
        search?.addEventListener('input', () => {
            const query = search.value.trim().toLowerCase();
            cards.forEach((card) => {
                if (!card.isConnected) return;
                const matches = card.dataset.search.includes(query);
                card.classList.toggle('hidden', !matches);
            });
            refreshApplicationEmptyStates();
        });
    </script>
    <script>
        (function($) {
            if (!$) return;
            $(document).on('submit', 'form[data-page-ajax]', function(event) {
                if (event.isDefaultPrevented()) return;
                event.preventDefault();
                const form = this;
                const button = form.querySelector('[type="submit"]');
                if (button?.disabled) return;
                if (button) button.disabled = true;
                $.ajax({
                        url: form.getAttribute('action'),
                        method: 'POST',
                        data: new FormData(form),
                        processData: false,
                        contentType: false,
                        dataType: 'json',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    })
                    .done(function(response) {
                        if (!response || response.status === 'error') {
                            showToast('error', response?.message || 'The admissions update could not be completed.');
                            if (button) button.disabled = false;
                            return;
                        }
                        const action = form.querySelector('[name="action"]')?.value;
                        if (typeof window.refreshAdmissionsBadge === 'function') window.refreshAdmissionsBadge();
                        if (action === 'review_application') {
                            const card = form.closest('.application-card');
                            const status = form.querySelector('[name="status"]')?.value || '';
                            const badge = card?.querySelector('header > span');
                            if (badge) badge.textContent = status;
                            if (status === 'Approved') {
                                card?.remove();
                                refreshApplicationEmptyStates();
                            } else if (status === 'Rejected') form.remove();
                        } else if (action === 'review_document') {
                            const section = form.closest('[data-admission-document]');
                            const status = response.data?.review_status || form.querySelector('[name="document_status"]')?.value || '';
                            const label = section?.querySelector('[data-document-status]');
                            if (label) label.textContent = 'Status: ' + status + (section.querySelector('[data-document-received]') ? ' · Face to Face' : '');
                            const messageText = response.data?.review_message || form.querySelector('[name="document_message"]')?.value || '';
                            let messageNode = section?.querySelector('[data-document-review-message]');
                            if (messageText && !messageNode && section) {
                                messageNode = document.createElement('p');
                                messageNode.dataset.documentReviewMessage = '';
                                messageNode.className = 'mt-2 rounded bg-amber-50 p-2 text-sm text-amber-900';
                                section.querySelector('.flex.flex-wrap.items-start.justify-between > div')?.appendChild(messageNode);
                            }
                            if (messageNode) {
                                messageNode.textContent = messageText;
                                messageNode.classList.toggle('hidden', !messageText);
                            }
                            let decision = section?.querySelector('[data-document-decision]');
                            if (status === 'Confirmed') {
                                if (!decision && section) {
                                    decision = document.createElement('p');
                                    decision.dataset.documentDecision = '';
                                    section.appendChild(decision);
                                }
                                if (decision) {
                                    decision.textContent = 'Document approved';
                                    decision.className = 'mt-3 text-xs font-semibold text-emerald-700';
                                }
                            } else {
                                decision?.remove();
                            }
                            form.remove();
                            if (status === 'Resubmit Needed') ensureFaceToFaceForm(section);
                            if (status === 'Confirmed') section?.querySelector('[data-face-to-face-form]')?.remove();
                        } else if (action === 'submit_face_to_face') {
                            const section = form.closest('[data-admission-document]');
                            const wasSubmitted = section?.dataset.documentSubmitted === '1';
                            section?.setAttribute('data-document-submitted', '1');
                            form.remove();
                            const empty = section?.querySelector('[data-document-empty]');
                            empty?.remove();
                            const label = section?.querySelector('[data-document-status]');
                            if (label) label.textContent = 'Status: Pending · Face to Face';
                            if (!wasSubmitted) {
                                const count = section?.closest('.application-card')?.querySelector('[data-submitted-count]');
                                const match = count?.textContent.match(/(\d+) of (\d+)/);
                                if (count && match) count.textContent = (Number(match[1]) + 1) + ' of ' + match[2] + ' submitted';
                            }
                            const received = section?.querySelector('[data-document-received]') || document.createElement('span');
                            received.dataset.documentReceived = '';
                            received.className = 'rounded bg-gray-100 px-3 py-2 text-xs text-gray-600';
                            received.textContent = 'Received in person';
                            section?.querySelector('.flex.flex-wrap.items-start.justify-between')?.appendChild(received);
                            const template = section?.querySelector('template[data-review-document-template]');
                            const reviewForm = template?.content.firstElementChild.cloneNode(true);
                            if (reviewForm && section) {
                                reviewForm.querySelector('[name="document_id"]').value = response.document_id || response.data?.document_id || '';
                                template.before(reviewForm);
                            }
                        } else if (action === 'delete_requirement') form.closest('.grid.gap-3.py-3')?.remove();
                        else if (action === 'save_requirement' && form.querySelector('[name="requirement_id"]')) form.querySelector('input[name="document_name"]')?.setAttribute('value', form.querySelector('input[name="document_name"]').value);
                        showToast('success', response.message || 'Admissions update saved.');
                    })
                    .fail(function(xhr) {
                        showToast('error', xhr.responseJSON?.message || 'The admissions request failed. Please try again.');
                        if (button) button.disabled = false;
                    });
            });
        })(window.jQuery);

        function ensureFaceToFaceForm(section) {
            if (!section || section.querySelector('[data-face-to-face-form]')) return;
            const card = section.closest('.application-card');
            const form = document.createElement('form');
            form.dataset.pageAjax = '';
            form.dataset.faceToFaceForm = '';
            form.action = '../../controller/admissionController.php';
            form.method = 'post';
            form.className = 'mt-3';
            const values = {
                csrf_token: card?.querySelector('[name="csrf_token"]')?.value || '',
                action: 'submit_face_to_face',
                application_id: card?.dataset.applicationId || '',
                requirement_id: section.dataset.requirementId || ''
            };
            Object.entries(values).forEach(function([name, value]) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = name;
                input.value = value;
                form.appendChild(input);
            });
            const button = document.createElement('button');
            button.type = 'submit';
            button.className = 'rounded bg-gray-100 px-3 py-2 text-xs font-semibold';
            button.textContent = 'Record face-to-face submission';
            form.appendChild(button);
            section.querySelector('[data-document-status]')?.after(form);
        }
    </script>
</body>

</html>