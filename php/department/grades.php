<?php
require_once __DIR__ . '/../includes/auth.php';

ssis_require_role('department');

require_once __DIR__ . '/../../models/Grades.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$grades = new Grades(Database::getConnection());

$departmentId = (int) ($_SESSION['user']['department_id'] ?? 0);

$term = $grades->currentTerm();

$classes = $term ? $grades->getDepartmentGradeClasses($departmentId, (int) $term['id']) : [];

$sectionId = filter_input(INPUT_GET, 'section_id', FILTER_VALIDATE_INT) ?: 0;
$subjectId = filter_input(INPUT_GET, 'subject_id', FILTER_VALIDATE_INT) ?: 0;

$sections = [];
$subjects = [];

$selectedClass = null;

foreach ($classes as $class) {
    $sections[(int) $class['section_id']] = $class;
    if ((int) $class['section_id'] === $sectionId) {
        $subjects[(int) $class['subject_id']] = $class;
    }

    if ((int) $class['section_id'] === $sectionId && (int) $class['subject_id'] === $subjectId) {
        $selectedClass = $class;
    }
}

$esc = static fn($value): string => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');

$classesJson = json_encode($classes, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE);

$pageTitle = 'Department Grade Entry | Paxton University';
?>
<!doctype html>
<html lang="en"><?php include __DIR__ . '/../includes/head.php'; ?>

<body class="min-h-screen bg-gray-50 font-sans text-brand-dark antialiased">
    <?php include __DIR__ . '/../component/sidebar.php'; ?>
    <main class="mx-auto w-full max-w-7xl space-y-8 px-4 py-8 sm:px-6 lg:px-8">
        <section class="flex flex-col gap-4 rounded-md border border-gray-200 bg-white p-6 shadow-sm md:flex-row md:items-center md:justify-between">
            <div>
                <p class="text-xs font-bold uppercase tracking-wider text-brand-secondary">Academic records</p>
                <h1 class="mt-1 text-2xl font-bold tracking-tight text-brand-dark">Department grade entry</h1>
                <p class="mt-2 max-w-3xl text-sm text-gray-500">Select a section and subject to enter grades for your enrolled students. Saved grades go to the Registrar for approval.</p>
            </div>
            <?php if ($term): ?><div class="shrink-0 rounded-md border border-gray-200 bg-gray-50 px-4 py-3 text-sm"><span class="block text-[10px] font-bold uppercase tracking-wider text-gray-400">Active term</span><span class="mt-1 block font-semibold text-brand-primary"><?= $esc($term['semester']) ?> · AY <?= $esc($term['academic_year']) ?></span></div><?php endif; ?>
        </section>

        <?php if (!$term): ?>
            <section class="rounded-md border border-amber-200 bg-amber-50 p-5 text-sm text-amber-900">
                <h2 class="font-semibold">No active term</h2>
                <p class="mt-1">Grade entry is unavailable until an administrator sets the current academic term.</p>
            </section>
        <?php elseif (!$classes): ?>
            <section class="rounded-md border border-gray-200 bg-white p-8 text-center shadow-sm">
                <h2 class="font-semibold">No enrolled classes found</h2>
                <p class="mt-1 text-sm text-gray-500">Classes in your academic department will appear here when students are enrolled.</p>
            </section>
        <?php else: ?>
            <section class="rounded-md border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                <div class="mb-5">
                    <h2 class="text-lg font-bold">Choose a class</h2>
                    <p class="mt-1 text-sm text-gray-500">The student list appears after both selections are made.</p>
                </div>
                <div class="grid gap-4 md:grid-cols-2">
                    <label for="grade-section" class="text-sm font-semibold text-gray-700">Section<select id="grade-section" class="mt-2 w-full rounded-md border border-gray-300 bg-white px-3 py-2.5 text-sm outline-none focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/20">
                            <option value="">Select section</option><?php foreach ($sections as $id => $class): ?><option value="<?= $id ?>" <?= $sectionId === $id ? 'selected' : '' ?>><?= $esc($class['section_code']) ?> · <?= $esc($class['program_name']) ?></option><?php endforeach; ?>
                        </select></label>
                    <label for="grade-subject" class="text-sm font-semibold text-gray-700">Subject<select id="grade-subject" class="mt-2 w-full rounded-md border border-gray-300 bg-white px-3 py-2.5 text-sm outline-none focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/20" <?= !$sectionId ? 'disabled' : '' ?>>
                            <option value="">Select subject</option><?php foreach ($subjects as $id => $class): ?><option value="<?= $id ?>" <?= $subjectId === $id ? 'selected' : '' ?>><?= $esc($class['subject_code']) ?> · <?= $esc($class['subject_name']) ?></option><?php endforeach; ?>
                        </select></label>
                </div>
            </section>

            <section id="grade-roster-card" class="<?= $selectedClass ? '' : 'hidden ' ?>overflow-hidden rounded-md border border-gray-200 bg-white shadow-sm">
                <div class="flex flex-col gap-4 border-b border-gray-100 p-5 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                    <div>
                        <h2 id="grade-class-title" class="text-lg font-bold"><?php if ($selectedClass): ?><?= $esc($selectedClass['subject_code']) ?> <span class="font-normal text-gray-500">· <?= $esc($selectedClass['subject_name']) ?></span><?php else: ?>Student roster<?php endif; ?></h2>
                        <p id="grade-class-count" class="mt-1 text-sm text-gray-500"><?php if ($selectedClass): ?>Section <?= $esc($selectedClass['section_code']) ?><?php else: ?>Select a section and subject to load students.<?php endif; ?></p>
                    </div>
                    <label class="w-full sm:max-w-sm"><span class="sr-only">Search by student name or number</span><span class="relative block"><svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="11" cy="11" r="7" />
                                <path d="m20 20-4-4" />
                            </svg><input id="grade-student-search" type="search" disabled placeholder="Search name or student number" class="w-full rounded-md border border-gray-300 py-2.5 pl-9 pr-3 text-sm outline-none focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/20 disabled:bg-gray-50"></span></label>
                </div>
                <div id="grade-roster-loading" class="hidden px-6 py-8 text-center text-sm text-gray-500" role="status">Loading students…</div>
                <div id="grade-roster-error" class="hidden mx-5 my-4 rounded-md border border-red-200 bg-red-50 p-4 text-sm text-red-800 sm:mx-6"></div>
                <div id="grade-roster-empty" class="hidden px-6 py-10 text-center text-sm text-gray-500">There are no enrolled students for this subject and section.</div>
                <div id="grade-search-empty" class="hidden px-6 py-10 text-center text-sm text-gray-500">No students match your search.</div>
                <div id="grade-roster-table" class="hidden overflow-x-auto">
                    <table class="w-full min-w-[760px] text-left text-sm">
                        <thead class="bg-gray-50 text-[11px] uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-5 py-3">Student</th>
                                <th class="px-5 py-3">Grade</th>
                                <th class="px-5 py-3">Status</th>
                                <th class="px-5 py-3 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody id="grade-roster" class="divide-y divide-gray-100"></tbody>
                    </table>
                </div>
            </section>
        <?php endif; ?>
    </main>
    <script>
        const departmentGradeClasses = <?= $classesJson ?: '[]' ?>;
        const gradeTermId = <?= (int) ($term['id'] ?? 0) ?>;
        const gradeCsrfToken = <?= json_encode($_SESSION['csrf_token'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
        const initialGradeSelection = {
            section: <?= (int) $sectionId ?>,
            subject: <?= (int) $subjectId ?>
        };

        function matchingClass() {
            return departmentGradeClasses.find(item => Number(item.section_id) === Number($('#grade-section').val()) && Number(item.subject_id) === Number($('#grade-subject').val()));
        }

        function updateGradeUrl() {
            const query = new URLSearchParams();
            if ($('#grade-section').val()) query.set('section_id', $('#grade-section').val());
            if ($('#grade-subject').val()) query.set('subject_id', $('#grade-subject').val());
            history.replaceState(null, '', window.location.pathname + (query.toString() ? '?' + query.toString() : ''));
        }

        function fillGradeSubjects(selected = 0) {
            const sectionId = Number($('#grade-section').val());
            const matches = departmentGradeClasses.filter(item => Number(item.section_id) === sectionId);
            const $subject = $('#grade-subject').empty().append($('<option>').val('').text('Select subject'));
            const seen = new Set();
            matches.forEach(item => {
                if (!seen.has(item.subject_id)) {
                    seen.add(item.subject_id);
                    $subject.append($('<option>').val(item.subject_id).text(item.subject_code + ' · ' + item.subject_name));
                }
            });
            $subject.prop('disabled', !matches.length).val(String(selected || ''));
        }

        function showRosterState(state, message = '') {
            $('#grade-roster-loading').toggleClass('hidden', state !== 'loading');
            $('#grade-roster-error').toggleClass('hidden', state !== 'error').text(message);
            $('#grade-roster-empty').toggleClass('hidden', state !== 'empty');
            $('#grade-search-empty').addClass('hidden');
            $('#grade-roster-table').toggleClass('hidden', state !== 'ready');
            $('#grade-student-search').prop('disabled', state !== 'ready').val('');
        }

        function statusBadge(status) {
            const label = status === 'Registrar Review' ? 'Waiting for Registrar' : (status === 'Department Revision' ? 'Revision needed' : (status === 'Released' ? 'Released' : 'Not submitted'));
            const color = status === 'Released' ? 'bg-emerald-50 text-emerald-800' : (status === 'Department Revision' ? 'bg-amber-50 text-amber-800' : 'bg-gray-100 text-gray-600');
            return $('<span data-grade-status>').attr('class', 'inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ' + color).text(label);
        }

        function renderGradeRoster(students, currentClass) {
            const $body = $('#grade-roster').empty();
            if (!students.length) {
                showRosterState('empty');
                return;
            }
            students.forEach(student => {
                const editable = !student.grade_id || student.workflow_status === 'Department Revision';
                const formId = 'grade-form-' + Number(student.student_id);
                const fullName = [student.first_name, student.middle_name, student.last_name].filter(Boolean).join(' ');
                const searchable = (student.student_no + ' ' + fullName).toLocaleLowerCase();
                const $row = $('<tr data-grade-row>').attr('data-search', searchable);
                const $name = $('<td class="px-5 py-4">');
                $name.append($('<p class="font-semibold text-gray-800">').text(student.last_name + ', ' + [student.first_name, student.middle_name].filter(Boolean).join(' ')));
                $name.append($('<p class="mt-1 text-xs text-gray-400">').text(student.student_no));
                $row.append($name);

                const $gradeCell = $('<td class="px-5 py-4">');
                const $gradeInput = $('<input type="number" min="1" max="5" step="0.01" name="grade" class="grade-input w-28 rounded-md border border-gray-300 px-3 py-2 text-sm outline-none focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/20 disabled:bg-gray-50 disabled:text-gray-500">').attr('form', formId).val(student.grade ?? '');
                if (editable) $gradeInput.prop('required', true);
                else $gradeInput.prop('disabled', true);
                $gradeCell.append($gradeInput);
                $row.append($gradeCell);

                const $statusCell = $('<td class="px-5 py-4">').append(statusBadge(student.workflow_status));
                if (student.workflow_status === 'Department Revision' && student.review_message) $statusCell.append($('<p class="mt-1 max-w-xs text-xs text-amber-700">').text(student.review_message));
                $row.append($statusCell);

                const $actionCell = $('<td class="px-5 py-4 text-right">');
                if (editable) {
                    const $form = $('<form data-page-ajax method="post" class="inline">').attr({
                        id: formId,
                        action: '../../controller/gradeWorkflowController.php'
                    });
                    [
                        ['csrf_token', gradeCsrfToken],
                        ['action', 'department_grade_save'],
                        ['student_id', student.student_id],
                        ['term_id', gradeTermId],
                        ['section_id', currentClass.section_id],
                        ['subject_id', currentClass.subject_id]
                    ].forEach(([name, value]) => $form.append($('<input type="hidden">').attr('name', name).val(value)));
                    $form.append($('<button type="submit" class="grade-submit rounded-md bg-brand-primary px-3 py-2 text-xs font-semibold text-white transition hover:bg-brand-secondary">').text('Save & send'));
                    $actionCell.append($form);
                } else {
                    $actionCell.append($('<span class="text-xs text-gray-400">').text('No action'));
                }
                $row.append($actionCell);
                $body.append($row);
            });
            $('#grade-class-title').text(currentClass.subject_code + ' · ' + currentClass.subject_name);
            $('#grade-class-count').text('Section ' + currentClass.section_code + ' · ' + students.length + ' students');
            showRosterState('ready');
        }
        async function loadDepartmentRoster() {
            const currentClass = matchingClass();
            if (!currentClass) {
                $('#grade-roster-card').addClass('hidden');
                return;
            }
            $('#grade-roster-card').removeClass('hidden');
            $('#grade-class-title').text(currentClass.subject_code + ' · ' + currentClass.subject_name);
            $('#grade-class-count').text('Section ' + currentClass.section_code);
            $('#grade-roster').empty();
            showRosterState('loading');
            try {
                const result = await $.ajax({
                    url: '../../controller/gradeWorkflowController.php',
                    method: 'GET',
                    dataType: 'json',
                    data: {
                        action: 'department_grade_roster',
                        term_id: gradeTermId,
                        section_id: currentClass.section_id,
                        subject_id: currentClass.subject_id
                    }
                });
                renderGradeRoster(result.students || [], currentClass);
            } catch (xhr) {
                const message = xhr.responseJSON?.message || 'The grade roster could not be loaded. Please try again.';
                showRosterState('error', message);
                showToast('error', message);
            }
        }
        $('#grade-section').on('change', function() {
            fillGradeSubjects();
            updateGradeUrl();
            $('#grade-roster-card').addClass('hidden');
        });
        $('#grade-subject').on('change', function() {
            updateGradeUrl();
            loadDepartmentRoster();
        });
        $('#grade-student-search').on('input', function() {
            const search = this.value.trim().toLocaleLowerCase();
            let shown = 0;
            $('#grade-roster [data-grade-row]').each(function() {
                const match = !search || String($(this).attr('data-search')).includes(search);
                $(this).toggleClass('hidden', !match);
                if (match) shown++;
            });
            $('#grade-search-empty').toggleClass('hidden', shown > 0 || !$('#grade-roster [data-grade-row]').length);
        });
        $(document).on('submit', '#grade-roster form[data-page-ajax]', function(event) {
            event.preventDefault();
            const form = this;
            const $button = $(form).find('[type="submit"]');
            if ($button.prop('disabled')) return;
            $button.prop('disabled', true).text('Sending...');
            $.ajax({
                    url: form.getAttribute('action'),
                    method: 'POST',
                    data: $(form).serialize(),
                    dataType: 'json'
                })
                .done(function(response) {
                    if (!response || response.status !== 'success') {
                        showToast('error', response?.message || 'The grade could not be submitted.');
                        $button.prop('disabled', false).text('Save & send');
                        return;
                    }
                    const $row = $(form).closest('[data-grade-row]');
                    $row.find('[data-grade-status]').text('Waiting for Registrar').attr('class', 'inline-flex rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-600');
                    $row.find('.grade-input').prop('disabled', true);
                    $button.replaceWith('<span class="text-xs text-gray-400">Submitted</span>');
                    showToast('success', response.message || 'Grade submitted for Registrar review.');
                })
                .fail(function(xhr) {
                    showToast('error', xhr.responseJSON?.message || 'The grade submission failed. Please try again.');
                    $button.prop('disabled', false).text('Save & send');
                });
        });
        if (initialGradeSelection.section) fillGradeSubjects(initialGradeSelection.subject);
        if (initialGradeSelection.section && initialGradeSelection.subject && matchingClass()) loadDepartmentRoster();
    </script>
    <script>
        (function() {
            const pageSize = 10;
            const tables = Array.from(document.querySelectorAll('table'));

            function visibleRows(table) {
                return Array.from(table.tBodies[0]?.rows || []).filter(row => row.style.display !== 'none' && !row.classList.contains('hidden'));
            }

            function controlsFor(table) {
                let controls = table.nextElementSibling;
                if (!controls || !controls.matches('[data-local-table-pages]')) {
                    controls = document.createElement('div');
                    controls.dataset.localTablePages = '';
                    controls.className = 'flex w-full items-center justify-between gap-3 border-t border-gray-100 bg-gray-50 px-4 py-3 text-xs text-gray-600';
                    controls.innerHTML = '<span data-local-page-summary></span><div class="flex gap-2"><button type="button" data-local-page-step="-1" class="rounded border border-gray-300 bg-white px-3 py-2 font-semibold hover:bg-gray-50">Previous</button><button type="button" data-local-page-step="1" class="rounded border border-gray-300 bg-white px-3 py-2 font-semibold hover:bg-gray-50">Next</button></div>';
                    table.insertAdjacentElement('afterend', controls);
                }
                return controls;
            }

            function refresh(table) {
                const body = table.tBodies[0];
                if (!body) return;
                const allRows = Array.from(body.rows);
                const rows = visibleRows(table);
                const pageCount = Math.max(1, Math.ceil(rows.length / pageSize));
                const page = Math.min(Math.max(Number(table.dataset.localPage || 1), 1), pageCount);
                table.dataset.localPage = String(page);
                allRows.forEach(row => {
                    row.hidden = true;
                });
                const start = (page - 1) * pageSize;
                rows.slice(start, start + pageSize).forEach(row => {
                    row.hidden = false;
                });
                const controls = controlsFor(table);
                controls.hidden = rows.length <= pageSize;
                controls.querySelector('[data-local-page-summary]').textContent = rows.length ? `Showing ${start + 1} to ${Math.min(start + pageSize, rows.length)} of ${rows.length}` : 'No records to show';
                controls.querySelector('[data-local-page-step="-1"]').disabled = page <= 1;
                controls.querySelector('[data-local-page-step="1"]').disabled = page >= pageCount;
            }
            let refreshPending = false;

            function scheduleRefresh() {
                if (refreshPending) return;
                refreshPending = true;
                window.requestAnimationFrame(() => {
                    refreshPending = false;
                    refreshAll();
                });
            }

            function refreshAll() {
                tables.forEach(refresh);
            }
            document.addEventListener('click', event => {
                const button = event.target.closest('[data-local-page-step]');
                if (!button) return;
                const controls = button.closest('[data-local-table-pages]');
                const table = controls?.previousElementSibling;
                if (!table || table.tagName !== 'TABLE') return;
                table.dataset.localPage = String(Number(table.dataset.localPage || 1) + Number(button.dataset.localPageStep));
                refresh(table);
            });
            ['input', 'change', 'keyup'].forEach(name => document.addEventListener(name, scheduleRefresh, true));
            const observer = new MutationObserver(records => {
                if (records.some(record => [...record.addedNodes, ...record.removedNodes].some(node => node.nodeType === 1 && (node.matches('tr') || node.querySelector('tr'))))) refreshAll();
            });
            observer.observe(document.body, {
                childList: true,
                subtree: true
            });
            refreshAll();
        })();
    </script>
</body>

</html>