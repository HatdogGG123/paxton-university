<?php
require_once __DIR__ . '/../includes/auth.php';
ssis_require_role('registrar');
require_once __DIR__ . '/../../models/Grades.php';
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$queue = (new Grades(Database::getConnection()))->getRegistrarGradeQueue();
$esc = static fn($value): string => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
$gradeDepartments = [];
$gradeSections = [];
$gradeSubjects = [];
foreach ($queue as $row) {
    $gradeDepartments[(int) $row['department_id']] = $row['department_name'];
    $sectionKey = (int) $row['department_id'] . ':' . (int) $row['section_id'];
    $gradeSections[$sectionKey] = ['id' => (int) $row['section_id'], 'department_id' => (int) $row['department_id'], 'code' => $row['section_code']];
    $subjectKey = (int) $row['subject_id'];
    if (!isset($gradeSubjects[$subjectKey])) {
        $gradeSubjects[$subjectKey] = ['id' => $subjectKey, 'department_id' => (int) $row['department_id'], 'section_ids' => [], 'code' => $row['subject_code'], 'name' => $row['subject_name']];
    }
    $gradeSubjects[$subjectKey]['section_ids'][] = (int) $row['section_id'];
}
$pageTitle = 'Registrar Grade Review | Paxton University';
?>
<!doctype html><html lang="en"><?php include __DIR__ . '/../includes/head.php'; ?>
<body class="min-h-screen bg-gray-50 font-sans text-brand-dark antialiased">
<?php include __DIR__ . '/../component/sidebar.php'; ?>
<main class="mx-auto w-full max-w-7xl space-y-8 px-4 py-8 sm:px-6 lg:px-8">
    <section class="flex flex-col gap-4 rounded-md border border-gray-200 bg-white p-6 shadow-sm md:flex-row md:items-center md:justify-between">
        <div><p class="text-xs font-bold uppercase tracking-wider text-brand-secondary">Final grade review</p><h1 class="mt-1 text-2xl font-bold tracking-tight text-brand-dark">Registrar grade approvals</h1><p class="mt-2 max-w-3xl text-sm text-gray-500">Grades submitted by departments are listed here. Approval makes the final grade visible to the student.</p></div>
        <div class="shrink-0 rounded-md border border-gray-200 bg-gray-50 px-4 py-3"><span class="block text-[10px] font-bold uppercase tracking-wider text-gray-400">Waiting for review</span><span id="grade-queue-count" class="mt-1 block text-xl font-bold text-brand-primary"><?= count($queue) ?></span></div>
    </section>

    <section class="overflow-hidden rounded-md border border-gray-200 bg-white shadow-sm">
        <div class="flex flex-col gap-1 border-b border-gray-100 px-5 py-4 sm:px-6">
            <h2 class="text-lg font-bold">Grades awaiting approval</h2>
            <p class="text-sm text-gray-500">Review the student and class details, then approve or return each grade.</p>
            <div class="mt-4 grid gap-3 sm:grid-cols-3">
                <label class="text-xs font-semibold text-gray-700">Academic department<select id="grade-filter-department" class="mt-1 w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm"><option value="">All departments</option><?php foreach ($gradeDepartments as $id => $name): ?><option value="<?= (int) $id ?>"><?= $esc($name) ?></option><?php endforeach; ?></select></label>
                <label class="text-xs font-semibold text-gray-700">Section<select id="grade-filter-section" class="mt-1 w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm"><option value="">All sections</option></select></label>
                <label class="text-xs font-semibold text-gray-700">Subject<select id="grade-filter-subject" class="mt-1 w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm"><option value="">All subjects</option></select></label>
            </div>
        </div>
        <div class="hidden overflow-x-auto lg:block">
            <table id="registrar-grade-queue-table" class="w-full min-w-[980px] text-left text-sm">
                <thead class="bg-gray-50 text-[11px] uppercase tracking-wide text-gray-500">
                    <tr>
                        <th scope="col" class="px-5 py-3">Student</th>
                        <th scope="col" class="px-5 py-3">Subject / Class</th>
                        <th scope="col" class="px-5 py-3">Term</th>
                        <th scope="col" class="px-5 py-3">Grade</th>
                        <th scope="col" class="px-5 py-3">Submitted by</th>
                        <th scope="col" class="px-5 py-3">Review action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php foreach ($queue as $row): ?>
                    <tr data-grade-row data-grade-id="<?= (int) $row['grade_id'] ?>" data-department-id="<?= (int) $row['department_id'] ?>" data-section-id="<?= (int) $row['section_id'] ?>" data-subject-id="<?= (int) $row['subject_id'] ?>" class="align-top hover:bg-gray-50/70">
                        <td class="px-5 py-4"><p class="font-semibold text-gray-800"><?= $esc($row['student_name']) ?></p><p class="mt-1 text-xs text-gray-500"><?= $esc($row['student_no']) ?></p></td>
                        <td class="px-5 py-4"><p class="font-semibold text-brand-primary"><?= $esc($row['subject_code']) ?></p><p class="mt-1 text-xs text-gray-600"><?= $esc($row['subject_name']) ?></p><p class="mt-1 text-xs text-gray-400"><?= $esc($row['department_name']) ?> · Section <?= $esc($row['section_code']) ?></p></td>
                        <td class="whitespace-nowrap px-5 py-4 text-gray-600"><p><?= $esc($row['semester']) ?></p><p class="mt-1 text-xs text-gray-400">AY <?= $esc($row['academic_year']) ?></p></td>
                        <td class="whitespace-nowrap px-5 py-4"><p class="text-lg font-bold text-gray-800"><?= $esc(number_format((float) $row['grade'], 2)) ?></p><span class="mt-1 inline-flex rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-600"><?= $esc($row['grade_status']) ?></span></td>
                        <td class="px-5 py-4 text-gray-600"><?= $esc($row['entered_by_name']) ?></td>
                        <td class="min-w-[330px] px-5 py-4">
                            <form data-page-ajax action="../../controller/gradeWorkflowController.php" method="post" class="space-y-2">
                                <input type="hidden" name="csrf_token" value="<?= $esc($_SESSION['csrf_token']) ?>">
                                <input type="hidden" name="action" value="registrar_grade_review">
                                <input type="hidden" name="grade_id" value="<?= (int) $row['grade_id'] ?>">
                                <input type="hidden" class="grade-decision" name="decision" value="release">
                                <label class="sr-only" for="grade-message-table-<?= (int) $row['grade_id'] ?>">Reason for returning grade</label>
                                <textarea id="grade-message-table-<?= (int) $row['grade_id'] ?>" name="message" maxlength="500" rows="2" class="w-full rounded-md border border-gray-300 px-3 py-2 text-xs outline-none focus:border-brand-secondary focus:ring-2 focus:ring-brand-secondary/20" placeholder="Reason if returning this grade"></textarea>
                                <div class="flex flex-wrap gap-2">
                                    <button type="submit" onclick="this.form.querySelector('.grade-decision').value='return'" class="rounded-md border border-amber-300 px-3 py-2 text-xs font-semibold text-amber-800 transition hover:bg-amber-50">Return</button>
                                    <button type="submit" onclick="this.form.querySelector('.grade-decision').value='release'" class="rounded-md bg-brand-primary px-3 py-2 text-xs font-semibold text-white transition hover:bg-brand-secondary">Approve &amp; release</button>
                                </div>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="space-y-4 p-4 lg:hidden">
            <?php foreach ($queue as $row): ?>
            <article data-grade-row data-grade-id="<?= (int) $row['grade_id'] ?>" data-department-id="<?= (int) $row['department_id'] ?>" data-section-id="<?= (int) $row['section_id'] ?>" data-subject-id="<?= (int) $row['subject_id'] ?>" class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <div class="flex flex-wrap items-center gap-2"><h3 class="text-base font-bold text-gray-800"><?= $esc($row['student_name']) ?></h3><span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-600"><?= $esc($row['student_no']) ?></span></div>
                        <p class="mt-3 text-sm font-semibold text-brand-primary"><?= $esc($row['subject_code']) ?> <span class="font-normal text-gray-500">· <?= $esc($row['subject_name']) ?></span></p>
                        <p class="mt-1 text-xs leading-5 text-gray-500"><?= $esc($row['department_name']) ?> · Section <?= $esc($row['section_code']) ?> · <?= $esc($row['semester']) ?>, AY <?= $esc($row['academic_year']) ?></p>
                        <p class="mt-1 text-xs text-gray-400">Submitted by: <?= $esc($row['entered_by_name']) ?></p>
                    </div>
                    <div class="rounded-lg bg-brand-accent/30 px-4 py-3 sm:min-w-28 sm:text-right"><p class="text-2xl font-bold"><?= $esc(number_format((float) $row['grade'], 2)) ?></p><p class="text-xs font-semibold text-gray-500"><?= $esc($row['grade_status']) ?></p></div>
                </div>
                <form data-page-ajax action="../../controller/gradeWorkflowController.php" method="post" class="mt-4 space-y-3 border-t border-gray-100 pt-4">
                    <input type="hidden" name="csrf_token" value="<?= $esc($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="action" value="registrar_grade_review">
                    <input type="hidden" name="grade_id" value="<?= (int) $row['grade_id'] ?>">
                    <input type="hidden" class="grade-decision" name="decision" value="release">
                    <label for="grade-message-card-<?= (int) $row['grade_id'] ?>" class="block text-xs font-semibold text-gray-700">Reason for return <span class="font-normal text-gray-400">(required when returning)</span></label>
                    <textarea id="grade-message-card-<?= (int) $row['grade_id'] ?>" name="message" maxlength="500" rows="2" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm outline-none focus:border-brand-secondary focus:ring-2 focus:ring-brand-secondary/20" placeholder="Explain the correction required"></textarea>
                    <div class="flex flex-col gap-2 sm:flex-row sm:justify-end">
                        <button type="submit" onclick="this.form.querySelector('.grade-decision').value='return'" class="rounded-md border border-amber-300 px-4 py-2.5 text-sm font-semibold text-amber-800 transition hover:bg-amber-50">Return to department</button>
                        <button type="submit" onclick="this.form.querySelector('.grade-decision').value='release'" class="rounded-md bg-brand-primary px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-brand-secondary">Approve and release</button>
                    </div>
                </form>
            </article>
            <?php endforeach; ?>
        </div>
        <div id="grade-queue-empty" class="<?= $queue ? 'hidden ' : '' ?>flex min-h-56 flex-col items-center justify-center px-6 py-12 text-center"><h2 id="grade-queue-empty-title" class="font-semibold">No grades to review</h2><p id="grade-queue-empty-message" class="mt-1 text-sm text-gray-500">Grades submitted by departments will appear in this queue.</p></div>
    </section>
</main>
<script>
const gradeSectionOptions = <?= json_encode(array_values($gradeSections), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
const gradeSubjectOptions = <?= json_encode(array_values($gradeSubjects), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
const gradeFilterDepartment = document.getElementById('grade-filter-department');
const gradeFilterSection = document.getElementById('grade-filter-section');
const gradeFilterSubject = document.getElementById('grade-filter-subject');

function refreshGradeQueueFilters() {
    const departmentId = gradeFilterDepartment.value;
    const previousSection = gradeFilterSection.value;
    gradeFilterSection.innerHTML = '<option value="">All sections</option>';
    gradeSectionOptions.filter(item => !departmentId || String(item.department_id) === departmentId).forEach(item => {
        gradeFilterSection.add(new Option(item.code, String(item.id)));
    });
    if ([...gradeFilterSection.options].some(option => option.value === previousSection)) gradeFilterSection.value = previousSection;

    const sectionId = gradeFilterSection.value;
    const previousSubject = gradeFilterSubject.value;
    gradeFilterSubject.innerHTML = '<option value="">All subjects</option>';
    gradeSubjectOptions.filter(item => (!departmentId || String(item.department_id) === departmentId)
        && (!sectionId || item.section_ids.map(String).includes(sectionId))).forEach(item => {
        gradeFilterSubject.add(new Option(item.code + ' · ' + item.name, String(item.id)));
    });
    if ([...gradeFilterSubject.options].some(option => option.value === previousSubject)) gradeFilterSubject.value = previousSubject;

    const subjectId = gradeFilterSubject.value;
    document.querySelectorAll('[data-grade-row]').forEach(row => {
        const matches = (!departmentId || row.dataset.departmentId === departmentId)
            && (!sectionId || row.dataset.sectionId === sectionId)
            && (!subjectId || row.dataset.subjectId === subjectId);
        row.classList.toggle('hidden', !matches);
    });
    const matchingIds = new Set(Array.from(document.querySelectorAll('[data-grade-row]:not(.hidden)')).map(row => row.dataset.gradeId));
    document.getElementById('grade-queue-count').textContent = matchingIds.size;
    const empty = matchingIds.size === 0;
    document.getElementById('grade-queue-empty').classList.toggle('hidden', !empty);
    document.getElementById('grade-queue-empty-title').textContent = empty && document.querySelectorAll('[data-grade-row]').length
        ? 'No grades match these filters' : 'No grades to review';
    document.getElementById('grade-queue-empty-message').textContent = empty && document.querySelectorAll('[data-grade-row]').length
        ? 'Choose a different department, section, or subject to see other grades.' : 'Grades submitted by departments will appear in this queue.';
    window.refreshLocalTables?.();
}

gradeFilterDepartment.addEventListener('change', () => {
    gradeFilterSection.value = '';
    gradeFilterSubject.value = '';
    refreshGradeQueueFilters();
});
gradeFilterSection.addEventListener('change', () => {
    gradeFilterSubject.value = '';
    refreshGradeQueueFilters();
});
gradeFilterSubject.addEventListener('change', () => refreshGradeQueueFilters());
refreshGradeQueueFilters();

$(document).on('submit', 'form[data-page-ajax]', function(event) {
    event.preventDefault();
    const form = this;
    const button = form.querySelector('[type="submit"]');
    if (button?.disabled) return;
    if (button) button.disabled = true;
    $.ajax({ url: form.getAttribute('action'), method: 'POST', data: $(form).serialize(), dataType: 'json' })
        .done(function(response) {
            if (!response || response.status !== 'success') {
                showToast('error', response?.message || 'The grade update could not be completed.');
                if (button) button.disabled = false;
                return;
            }
            const gradeId = $(form).find('[name="grade_id"]').val();
            const $rows = $('[data-grade-row][data-grade-id="' + gradeId + '"]');
            $rows.remove();
            refreshGradeQueueFilters();
        })
        .fail(function(xhr) {
            showToast('error', xhr.responseJSON?.message || 'The grade update failed. Please try again.');
            if (button) button.disabled = false;
        });
});
</script>
<script>
(function () {
    const pageSize = 10;
    const tables = Array.from(document.querySelectorAll('table'));
    function visibleRows(table) {
        return Array.from(table.tBodies[0]?.rows || []).filter(row => row.style.display !== 'none' && !row.classList.contains('hidden'));
    }
    function controlsFor(table) {
        if (!table.id) table.id = 'local-table-' + (tables.indexOf(table) + 1);
        const section = table.closest('section');
        let controls = section?.querySelector('[data-local-table-pages][data-table-id="' + table.id + '"]');
        if (!controls) {
            controls = document.createElement('div');
            controls.dataset.localTablePages = '';
            controls.className = 'flex w-full items-center justify-between gap-3 border-t border-gray-100 bg-gray-50 px-4 py-3 text-xs text-gray-600';
            controls.innerHTML = '<span data-local-page-summary></span><div class="flex gap-2"><button type="button" data-local-page-step="-1" class="rounded border border-gray-300 bg-white px-3 py-2 font-semibold hover:bg-gray-50">Previous</button><button type="button" data-local-page-step="1" class="rounded border border-gray-300 bg-white px-3 py-2 font-semibold hover:bg-gray-50">Next</button></div>';
        }
        controls.dataset.tableId = table.id;
        if (section) section.appendChild(controls);
        else if (controls.parentElement !== table.parentElement) table.insertAdjacentElement('afterend', controls);
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
        allRows.forEach(row => { row.hidden = true; });
        const start = (page - 1) * pageSize;
        rows.slice(start, start + pageSize).forEach(row => { row.hidden = false; });
        const controls = controlsFor(table);
        const showControls = rows.length === 0 || rows.length > pageSize;
        const desktopTableVisible = window.matchMedia('(min-width: 1024px)').matches;
        controls.hidden = !(showControls && desktopTableVisible);
        controls.style.display = showControls && desktopTableVisible ? 'flex' : 'none';
        controls.querySelector('[data-local-page-summary]').textContent = rows.length ? `Showing ${start + 1} to ${Math.min(start + pageSize, rows.length)} of ${rows.length}` : 'No records to show';
        controls.querySelector('[data-local-page-step="-1"]').disabled = page <= 1;
        controls.querySelector('[data-local-page-step="1"]').disabled = page >= pageCount;
    }
    let refreshPending = false;
    function scheduleRefresh() {
        if (refreshPending) return;
        refreshPending = true;
        window.requestAnimationFrame(() => { refreshPending = false; refreshAll(); });
    }
    function refreshAll() { tables.forEach(refresh); }
    window.refreshLocalTables = refreshAll;
    window.addEventListener('resize', refreshAll);
    document.addEventListener('click', event => {
        const button = event.target.closest('[data-local-page-step]');
        if (!button) return;
        const controls = button.closest('[data-local-table-pages]');
        const table = controls?.dataset.tableId ? document.getElementById(controls.dataset.tableId) : null;
        if (!table || table.tagName !== 'TABLE') return;
        table.dataset.localPage = String(Number(table.dataset.localPage || 1) + Number(button.dataset.localPageStep));
        refresh(table);
    });
    ['input', 'change', 'keyup'].forEach(name => document.addEventListener(name, scheduleRefresh, true));
    const observer = new MutationObserver(records => {
        if (records.some(record => [...record.addedNodes, ...record.removedNodes].some(node => node.nodeType === 1 && (node.matches('tr') || node.querySelector('tr'))))) refreshAll();
    });
    observer.observe(document.body, { childList: true, subtree: true });
    refreshAll();
})();
</script>
</body></html>
