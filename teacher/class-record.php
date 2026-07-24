<?php
/**
 * E-Class Record — editable, point-based gradebook matching the school's
 * Excel template (Quizzes / Performance Task / Attendance / Major Exam /
 * Essay), scoped to one subject + term. Every score cell autosaves; adding
 * an item (a new quiz, PT, or essay) instantly adds a column for every
 * student. "Export Excel" downloads a styled .xlsx built client-side with
 * ExcelJS: the school header block, then a two-row table header (section
 * names — with each section's current point value — spanning their item
 * columns, then item numbers/T/R), one row per student, borders and fills
 * throughout so it looks like the printed template instead of a bare CSV.
 */
$__hideTopbar = true;
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/exams_db.php';
require_once __DIR__ . '/../includes/class_record.php';

$user = inkwell_require_role('teacher');

/* ---------------- AJAX handlers ---------------- */
if (isset($_POST['action'])) {
  header('Content-Type: application/json');

  // Every action is scoped to a config the caller must prove ownership of
  // (via subject_id -> subjects.teacher_id), except add/list which take
  // subject_id + term directly.
  function erecord_owned_config($configId, $teacherId) {
    $pdo = inkwell_db();
    $stmt = $pdo->prepare(
      'SELECT c.* FROM erecord_config c JOIN subjects s ON s.id = c.subject_id
       WHERE c.id = ? AND s.teacher_id = ?'
    );
    $stmt->execute([$configId, $teacherId]);
    return $stmt->fetch();
  }

  $action = $_POST['action'];

  if ($action === 'get_record') {
    $subjectId = (int) ($_POST['subject_id'] ?? 0);
    $term = in_array($_POST['term'] ?? '', array_keys(inkwell_class_record_terms()), true) ? $_POST['term'] : 'prelim';
    $pdo = inkwell_db();
    $chk = $pdo->prepare('SELECT * FROM subjects WHERE id = ? AND teacher_id = ?');
    $chk->execute([$subjectId, $user['id']]);
    $subject = $chk->fetch();
    if (!$subject) { echo json_encode(['ok' => false, 'error' => "That subject isn't yours."]); exit; }

    $config = inkwell_erecord_get_or_create_config($subjectId, $term);
    if (!$config) { echo json_encode(['ok' => false, 'error' => 'Class Record tables are unavailable on this host. See MIGRATION_ADD_class_record.sql.']); exit; }
    $items = inkwell_erecord_items($config['id']);
    $scores = inkwell_erecord_scores($config['id']);
    $students = inkwell_erecord_roster($subjectId);
    $overrides = inkwell_erecord_overrides($config['id']);
    $computed = inkwell_erecord_compute($config, $items, $scores, $students, $overrides);

    echo json_encode([
      'ok' => true,
      'subject' => $subject,
      'config' => $config,
      'items' => $items,
      'scores' => $scores,
      'students' => $students,
      'overrides' => $overrides,
      'computed' => $computed,
      'max_total' => inkwell_erecord_max_total($config),
      'sections' => inkwell_erecord_sections(),
    ]);
    exit;
  }

  if ($action === 'save_header') {
    $config = erecord_owned_config((int) ($_POST['config_id'] ?? 0), $user['id']);
    if (!$config) { echo json_encode(['ok' => false, 'error' => 'Not found.']); exit; }
    inkwell_erecord_save_header($config['id'], $_POST['instructor_name'] ?? '', $_POST['time_schedule'] ?? '', $_POST['school_attended'] ?? '');
    echo json_encode(['ok' => true]);
    exit;
  }

  if ($action === 'save_points') {
    $config = erecord_owned_config((int) ($_POST['config_id'] ?? 0), $user['id']);
    if (!$config) { echo json_encode(['ok' => false, 'error' => 'Not found.']); exit; }
    $points = [];
    foreach (['quiz', 'pt', 'attendance', 'major_exam', 'essay'] as $k) {
      if (isset($_POST[$k])) $points[$k] = $_POST[$k];
    }
    inkwell_erecord_save_points($config['id'], $points);
    echo json_encode(['ok' => true]);
    exit;
  }

  if ($action === 'add_item') {
    $config = erecord_owned_config((int) ($_POST['config_id'] ?? 0), $user['id']);
    if (!$config) { echo json_encode(['ok' => false, 'error' => 'Not found.']); exit; }
    $result = inkwell_erecord_add_item($config['id'], $_POST['section'] ?? '', $_POST['label'] ?? '', $_POST['max_score'] ?? 100);
    echo json_encode($result);
    exit;
  }

  if ($action === 'delete_item') {
    $itemId = (int) ($_POST['item_id'] ?? 0);
    $pdo = inkwell_db();
    $stmt = $pdo->prepare(
      'SELECT i.* FROM erecord_items i JOIN erecord_config c ON c.id = i.config_id
       JOIN subjects s ON s.id = c.subject_id WHERE i.id = ? AND s.teacher_id = ?'
    );
    $stmt->execute([$itemId, $user['id']]);
    if (!$stmt->fetch()) { echo json_encode(['ok' => false, 'error' => 'Not found.']); exit; }
    inkwell_erecord_delete_item($itemId);
    echo json_encode(['ok' => true]);
    exit;
  }

  if ($action === 'save_score') {
    $itemId = (int) ($_POST['item_id'] ?? 0);
    $studentId = (int) ($_POST['student_id'] ?? 0);
    $pdo = inkwell_db();
    $stmt = $pdo->prepare(
      'SELECT i.* FROM erecord_items i JOIN erecord_config c ON c.id = i.config_id
       JOIN subjects s ON s.id = c.subject_id WHERE i.id = ? AND s.teacher_id = ?'
    );
    $stmt->execute([$itemId, $user['id']]);
    if (!$stmt->fetch()) { echo json_encode(['ok' => false, 'error' => 'Not found.']); exit; }
    inkwell_erecord_save_score($itemId, $studentId, $_POST['score'] ?? null);
    echo json_encode(['ok' => true]);
    exit;
  }

  if ($action === 'save_override') {
    $config = erecord_owned_config((int) ($_POST['config_id'] ?? 0), $user['id']);
    if (!$config) { echo json_encode(['ok' => false, 'error' => 'Not found.']); exit; }
    $sectionR = [];
    foreach (['quiz', 'pt', 'attendance', 'major_exam', 'essay'] as $sec) {
      $field = $sec . '_r';
      if (isset($_POST[$field])) $sectionR[$sec] = $_POST[$field];
    }
    inkwell_erecord_save_override($config['id'], (int) ($_POST['student_id'] ?? 0), $_POST['fr'] ?? null, $_POST['final_grade'] ?? null, $_POST['remarks'] ?? '', $sectionR);
    echo json_encode(['ok' => true]);
    exit;
  }

  echo json_encode(['ok' => false, 'error' => 'Unknown action.']);
  exit;
}

/* ---------------- Page render ---------------- */
$mySubjects = inkwell_teacher_subjects($user['id']);
$termLabels = inkwell_class_record_terms();
$pageTitle = 'E-Class Record';
include __DIR__ . '/../includes/header.php';

$driveActive = 'my-section';
$driveCrumbs = [
  ['label' => 'Home', 'href' => '/index.php'],
  ['label' => 'My Section', 'href' => '/my-section.php'],
  ['label' => 'E-Class Record'],
];
$driveTitle = 'E-Class Record';
$driveSubtitle = 'Point-based gradebook — Quizzes, Performance Task, Attendance, Major Exam, and Essay, each converted into points you set.';
include __DIR__ . '/../includes/drive_shell_top.php';
?>

  <?php if (empty($mySubjects)): ?>
    <section class="admin-card glass-card">
      <h2>No subjects yet</h2>
      <p class="admin-sub">Create a subject from <a href="/teacher/subject.php">Subjects</a> first.</p>
    </section>
  <?php else: ?>
    <section class="admin-card glass-card" style="margin-bottom:16px;">
      <div class="erec-toolbar">
        <label>Subject
          <select id="erecSubject">
            <?php foreach ($mySubjects as $s): ?>
              <option value="<?php echo (int) $s['id']; ?>"><?php echo htmlspecialchars($s['title']); ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>Term
          <select id="erecTerm">
            <?php foreach ($termLabels as $key => $label): ?>
              <option value="<?php echo htmlspecialchars($key); ?>"><?php echo htmlspecialchars($label); ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <button class="btn primary" id="erecExportBtn" type="button">⬇ Export Excel</button>
      </div>
    </section>

    <section class="admin-card glass-card erec-card">
      <div id="erecStatus" class="erec-status"></div>

      <div class="erec-head">
        <div class="erec-head-title">
          <img src="/assets/img/logo.png" onerror="this.style.display='none'" alt="">
          <div>
            <div class="erec-college">E-CLASS RECORD</div>
          </div>
        </div>
        <div class="erec-head-fields">
          <label>Name of Instructor:
            <input type="text" id="erecInstructor" placeholder="Instructor name">
          </label>
          <label>Subject:
            <input type="text" id="erecSubjectLabel" disabled>
          </label>
          <label>Time &amp; Schedule:
            <input type="text" id="erecSchedule" placeholder="e.g. MWF 8:00–9:00 AM">
          </label>
          <label>Term:
            <input type="text" id="erecTermLabel" disabled>
          </label>
          <label>School Attended:
            <input type="text" id="erecSchoolAttended" placeholder="e.g. University of Bohol">
          </label>
        </div>
      </div>

      <div class="erec-points-bar" id="erecPointsBar"></div>

      <div class="erec-table-wrap">
        <table class="erec-table" id="erecTable"></table>
      </div>

      <p class="admin-sub" style="margin-top:10px;">Tip: click a section's <strong>+</strong> button to add a Quiz, Performance Task, Essay, Major Exam, or Attendance column — it appears for every student immediately. Double-click a column header to remove it. The <strong>R</strong> cell is editable too — type a value to override the computed points for that student's section, or leave it blank to keep using the auto-calculated value shown as the placeholder.</p>
    </section>

    <div class="erec-modal-overlay" id="erecModalOverlay" hidden>
      <div class="erec-modal" role="dialog" aria-modal="true" aria-labelledby="erecModalTitle">
        <h3 id="erecModalTitle">Add item</h3>
        <div id="erecModalBody"></div>
        <div class="erec-modal-actions">
          <button type="button" class="btn" id="erecModalCancel">Cancel</button>
          <button type="button" class="btn primary" id="erecModalConfirm">OK</button>
        </div>
      </div>
    </div>
  <?php endif; ?>

<style>
/* Built on Inkwell's real design tokens (assets/css/style.css :root) —
   --surface / --border / --ink / --ink-dim / --nib / --pine / --danger /
   --radius — instead of guessed variable names, so this renders correctly
   in both the light and dark theme instead of falling back to hardcoded
   dark colors on a light page. */

.erec-toolbar { display: flex; gap: 18px; align-items: end; flex-wrap: wrap; }
.erec-toolbar label { display: flex; flex-direction: column; gap: 6px; font-size: 0.78rem; font-weight: 600; color: var(--ink-dim); text-transform: uppercase; letter-spacing: 0.03em; }
.erec-toolbar select {
  padding: 10px 14px; border-radius: var(--radius-sm); border: 1px solid var(--border);
  background: var(--surface); color: var(--ink); font-size: 0.9rem; font-weight: 600; min-width: 160px;
}
.erec-toolbar .btn { margin-left: auto; }

.erec-card { overflow: hidden; }
.erec-status { min-height: 20px; font-size: 0.8rem; font-weight: 600; color: var(--pine); margin-bottom: 8px; transition: opacity 0.2s; }
.erec-status.err { color: var(--danger); }
.erec-status:empty { opacity: 0; }

.erec-head {
  display: flex; justify-content: space-between; gap: 24px; flex-wrap: wrap;
  margin-bottom: 18px; padding-bottom: 18px; border-bottom: 1px solid var(--border-soft, var(--border));
}
.erec-head-title { display: flex; gap: 12px; align-items: center; }
.erec-head-title img { width: 44px; height: 44px; object-fit: contain; }
.erec-college { font-weight: 800; letter-spacing: 0.02em; font-size: 1.2rem; color: var(--ink); }
.erec-head-fields { display: grid; grid-template-columns: 1fr 1fr; gap: 12px 24px; flex: 1; min-width: 340px; }
.erec-head-fields label { display: flex; flex-direction: column; gap: 5px; font-size: 0.74rem; font-weight: 600; color: var(--ink-dim); text-transform: uppercase; letter-spacing: 0.03em; }
.erec-head-fields input {
  padding: 8px 12px; border-radius: var(--radius-sm); border: 1px solid var(--border);
  background: var(--surface); color: var(--ink); font-size: 0.9rem;
}
.erec-head-fields input:disabled { color: var(--ink-dim); background: color-mix(in srgb, var(--border) 25%, transparent); }
.erec-head-fields input:focus { outline: none; border-color: var(--nib); box-shadow: 0 0 0 3px color-mix(in srgb, var(--nib) 18%, transparent); }

.erec-points-bar { display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 18px; }
.erec-points-chip {
  display: flex; align-items: center; gap: 8px; background: var(--surface);
  border: 1px solid var(--border); border-radius: 999px; padding: 8px 14px; font-size: 0.82rem; font-weight: 600; color: var(--ink);
}
.erec-points-chip input {
  width: 56px; padding: 4px 6px; border-radius: 7px; border: 1px solid var(--border);
  background: var(--void); color: var(--ink); text-align: center; font-weight: 700;
}
.erec-points-chip input:focus { outline: none; border-color: var(--nib); }
#erecMaxTotalChip { background: color-mix(in srgb, var(--nib) 10%, var(--surface)); border-color: color-mix(in srgb, var(--nib) 35%, var(--border)); color: var(--nib); }

.erec-table-wrap { overflow-x: auto; border: 1px solid var(--border); border-radius: var(--radius-sm); }
.erec-table { border-collapse: collapse; width: 100%; font-size: 0.82rem; white-space: nowrap; }
.erec-table th, .erec-table td { border: 1px solid var(--border-soft, var(--border)); padding: 8px 10px; text-align: center; color: var(--ink); }
.erec-table thead th {
  background: color-mix(in srgb, var(--nib) 6%, var(--surface)); position: sticky; top: 0; z-index: 2;
  font-weight: 700; color: var(--ink); cursor: default;
}
.erec-table thead tr:first-child th { font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.03em; color: var(--ink-dim); border-bottom-width: 2px; }
.erec-table thead th.erec-section-head { cursor: pointer; }
.erec-table thead th.erec-add-col {
  cursor: pointer; color: var(--nib); font-weight: 800; font-size: 1rem; width: 30px;
}
.erec-table thead th.erec-add-col:hover { background: color-mix(in srgb, var(--nib) 16%, transparent); }
.erec-table thead th[data-item] { cursor: pointer; }
.erec-table thead th[data-item]:hover { background: color-mix(in srgb, var(--danger) 12%, transparent); color: var(--danger); }
.erec-table thead th[data-item]:hover::after { content: " ✕"; }

.erec-table tbody tr:nth-child(even) td { background: color-mix(in srgb, var(--border-soft, var(--border)) 40%, transparent); }
.erec-table tbody tr:hover td { background: color-mix(in srgb, var(--nib) 6%, transparent); }

.erec-table td.erec-name {
  text-align: left; font-weight: 700; position: sticky; left: 0; z-index: 1; min-width: 170px;
  background: var(--surface);
}
.erec-table tbody tr:nth-child(even) td.erec-name { background: color-mix(in srgb, var(--border-soft, var(--border)) 60%, var(--surface)); }
.erec-table tbody tr:hover td.erec-name { background: color-mix(in srgb, var(--nib) 8%, var(--surface)); }

.erec-table input.erec-score {
  width: 44px; text-align: center; border: 1px solid transparent; border-radius: 6px;
  background: transparent; color: var(--ink); padding: 4px; font-size: 0.85rem;
}
.erec-table input.erec-score:hover { border-color: var(--border); }
.erec-table input.erec-score:focus { outline: none; border-color: var(--nib); background: color-mix(in srgb, var(--nib) 8%, transparent); }

.erec-table td.erec-computed { background: color-mix(in srgb, var(--border-soft, var(--border)) 55%, transparent); font-variant-numeric: tabular-nums; color: var(--ink-dim); font-weight: 600; }
.erec-table td.erec-total { font-weight: 800; color: var(--nib); background: color-mix(in srgb, var(--nib) 10%, transparent); font-size: 0.95rem; }

.erec-table input.erec-r-override { color: var(--pine); font-weight: 700; }
.erec-table input.erec-r-override:not(:placeholder-shown) { border-color: var(--pine); background: color-mix(in srgb, var(--pine) 10%, transparent); }
.erec-table input.erec-r-override::placeholder { color: var(--pine); opacity: 0.55; font-weight: 600; }

.erec-table input.erec-remarks {
  width: 90px; border: 1px solid transparent; border-radius: 6px; background: transparent;
  color: var(--ink); text-align: center; padding: 4px; font-size: 0.85rem;
}
.erec-table input.erec-remarks:hover { border-color: var(--border); }
.erec-table input.erec-remarks:focus { outline: none; border-color: var(--nib); background: color-mix(in srgb, var(--nib) 8%, transparent); }
.erec-table input.erec-remarks::placeholder { color: var(--ink-dim); opacity: 0.6; }

.erec-empty-hint { padding: 30px 16px; color: var(--ink-dim); font-size: 0.88rem; }

.erec-modal-overlay {
  position: fixed; inset: 0; background: rgba(10,14,26,0.55); backdrop-filter: blur(2px);
  display: flex; align-items: center; justify-content: center; z-index: 1000; padding: 20px;
}
.erec-modal-overlay[hidden] { display: none; }
.erec-modal {
  background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius);
  box-shadow: var(--shadow); padding: 24px; width: 100%; max-width: 380px;
  animation: erecModalIn 0.15s ease;
}
@keyframes erecModalIn { from { opacity: 0; transform: translateY(6px) scale(0.98); } to { opacity: 1; transform: none; } }
.erec-modal h3 { margin: 0 0 14px; font-size: 1.05rem; color: var(--ink); }
.erec-modal-field { display: flex; flex-direction: column; gap: 6px; font-size: 0.8rem; font-weight: 600; color: var(--ink-dim); text-transform: uppercase; letter-spacing: 0.03em; margin-bottom: 14px; }
.erec-modal-field input {
  padding: 10px 12px; border-radius: var(--radius-sm); border: 1px solid var(--border);
  background: var(--void); color: var(--ink); font-size: 0.95rem; font-weight: 500; text-transform: none; letter-spacing: normal;
}
.erec-modal-field input:focus { outline: none; border-color: var(--nib); box-shadow: 0 0 0 3px color-mix(in srgb, var(--nib) 18%, transparent); }
.erec-modal-text { color: var(--ink); font-size: 0.92rem; line-height: 1.5; margin: 0 0 4px; }
.erec-modal-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 18px; }

@media (max-width: 720px) {
  .erec-head-fields { grid-template-columns: 1fr; }
  .erec-toolbar { align-items: stretch; }
  .erec-toolbar .btn { margin-left: 0; }
}
</style>

<script>
(function () {
  const subjectSel = document.getElementById('erecSubject');
  const termSel = document.getElementById('erecTerm');
  if (!subjectSel) return;
  const statusEl = document.getElementById('erecStatus');
  const instructorInput = document.getElementById('erecInstructor');
  const subjectLabelInput = document.getElementById('erecSubjectLabel');
  const scheduleInput = document.getElementById('erecSchedule');
  const termLabelInput = document.getElementById('erecTermLabel');
  const schoolAttendedInput = document.getElementById('erecSchoolAttended');
  const pointsBar = document.getElementById('erecPointsBar');
  const table = document.getElementById('erecTable');
  const exportBtn = document.getElementById('erecExportBtn');

  /* ---- Reusable modal (replaces native prompt()/confirm()) ---- */
  const modalOverlay = document.getElementById('erecModalOverlay');
  const modalTitleEl = document.getElementById('erecModalTitle');
  const modalBody = document.getElementById('erecModalBody');
  const modalCancel = document.getElementById('erecModalCancel');
  const modalConfirm = document.getElementById('erecModalConfirm');
  let modalOnConfirm = null;

  function openModal({ title, bodyHtml, confirmLabel = 'OK', danger = false, focusSelector, onConfirm }) {
    modalTitleEl.textContent = title;
    modalBody.innerHTML = bodyHtml;
    modalConfirm.textContent = confirmLabel;
    modalConfirm.className = 'btn ' + (danger ? 'danger' : 'primary');
    modalOnConfirm = onConfirm;
    modalOverlay.hidden = false;
    document.body.style.overflow = 'hidden';
    const toFocus = modalBody.querySelector(focusSelector || 'input, textarea');
    if (toFocus) setTimeout(() => toFocus.focus(), 30);
  }
  function closeModal() {
    modalOverlay.hidden = true;
    document.body.style.overflow = '';
    modalOnConfirm = null;
  }
  modalCancel.addEventListener('click', closeModal);
  modalOverlay.addEventListener('click', e => { if (e.target === modalOverlay) closeModal(); });
  document.addEventListener('keydown', e => { if (e.key === 'Escape' && !modalOverlay.hidden) closeModal(); });
  modalConfirm.addEventListener('click', () => { if (modalOnConfirm) modalOnConfirm(); });
  modalBody.addEventListener('keydown', e => {
    if (e.key === 'Enter' && e.target.tagName === 'INPUT') { e.preventDefault(); modalConfirm.click(); }
  });

  const SECTION_KEYS = ['quiz', 'pt', 'attendance', 'major_exam', 'essay'];
  let state = null; // last payload from get_record
  let saveDebounce = {};

  function flash(msg, isErr) {
    statusEl.textContent = msg;
    statusEl.className = 'erec-status' + (isErr ? ' err' : '');
    if (!isErr) setTimeout(() => { if (statusEl.textContent === msg) statusEl.textContent = ''; }, 1500);
  }

  function post(action, data) {
    const body = new URLSearchParams(Object.assign({ action }, data));
    return fetch(location.pathname, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body })
      .then(r => r.json());
  }

  function loadRecord() {
    post('get_record', { subject_id: subjectSel.value, term: termSel.value }).then(res => {
      if (!res.ok) { flash(res.error || 'Failed to load.', true); return; }
      state = res;
      instructorInput.value = res.config.instructor_name || '';
      subjectLabelInput.value = res.subject.title;
      scheduleInput.value = res.config.time_schedule || '';
      termLabelInput.value = termSel.options[termSel.selectedIndex].text;
      schoolAttendedInput.value = res.config.school_attended || '';
      renderPointsBar();
      renderTable();
    });
  }

  function renderPointsBar() {
    const labels = { quiz: 'Quizzes', pt: 'Performance Task', attendance: 'Attendance', major_exam: 'Major Exam', essay: 'Essay' };
    const fieldMap = { quiz: 'quiz_points', pt: 'pt_points', attendance: 'attendance_points', major_exam: 'major_exam_points', essay: 'essay_points' };
    pointsBar.innerHTML = '';
    SECTION_KEYS.forEach(sec => {
      const chip = document.createElement('div');
      chip.className = 'erec-points-chip';
      chip.innerHTML = `<span>${labels[sec]}</span><input type="number" min="0" step="0.5" value="${state.config[fieldMap[sec]]}" data-sec="${sec}">`;
      const input = chip.querySelector('input');
      input.addEventListener('change', () => {
        post('save_points', { config_id: state.config.id, [sec]: input.value }).then(res => {
          if (res.ok) { state.config[fieldMap[sec]] = input.value; recomputeAll(); flash('Saved.'); }
          else flash(res.error || 'Save failed.', true);
        });
      });
      pointsBar.appendChild(chip);
    });
    const totalChip = document.createElement('div');
    totalChip.className = 'erec-points-chip';
    totalChip.id = 'erecMaxTotalChip';
    pointsBar.appendChild(totalChip);
    updateMaxTotalChip();
  }

  function updateMaxTotalChip() {
    const c = state.config;
    const max = ['quiz_points', 'pt_points', 'attendance_points', 'major_exam_points', 'essay_points']
      .reduce((sum, k) => sum + parseFloat(c[k] || 0), 0);
    const chip = document.getElementById('erecMaxTotalChip');
    if (chip) chip.innerHTML = `<strong>Total possible: ${max.toFixed(2)} pts</strong>`;
  }

  function sectionLabel(sec) {
    return { quiz: 'Quizzes', pt: 'Performance Task', attendance: 'Attendance', major_exam: 'Major Exam', essay: 'Essay' }[sec];
  }

  function renderTable() {
    const items = state.items;
    let theadTop = '<tr><th rowspan="2" class="erec-name">Name of Student</th>';
    let theadBottom = '<tr>';

    SECTION_KEYS.forEach(sec => {
      const secItems = items[sec] || [];
      const span = secItems.length + 3; // items + T + R + add-col button
      theadTop += `<th colspan="${span}" class="erec-section-head" title="${sectionLabel(sec)}">${sectionLabel(sec)}</th>`;
      secItems.forEach((it, idx) => {
        theadBottom += `<th data-item="${it.id}" title="Double-click to remove • HPS ${it.max_score}">${idx + 1}</th>`;
      });
      theadBottom += `<th>T</th><th>R</th><th class="erec-add-col" data-add-sec="${sec}">+</th>`;
    });

    theadTop += '<th rowspan="2">Total</th><th rowspan="2">FR</th><th rowspan="2">Final Grade</th><th rowspan="2">Remarks</th></tr>';
    theadBottom += '</tr>';

    let tbody = '';
    state.students.forEach(stu => {
      const row = state.computed[stu.id];
      const ov = state.overrides[stu.id] || {};
      tbody += `<tr><td class="erec-name">${escapeHtml(stu.name)}</td>`;
      SECTION_KEYS.forEach(sec => {
        const secItems = items[sec] || [];
        secItems.forEach(it => {
          const key = stu.id + ':' + it.id;
          const val = state.scores[key];
          tbody += `<td><input class="erec-score" type="number" step="0.5" min="0" max="${it.max_score}" value="${val === null || val === undefined ? '' : val}" data-item="${it.id}" data-student="${stu.id}" data-section="${sec}"></td>`;
        });
        const sr = row ? row.sections[sec] : { t: 0, r: 0, hps: 0 };
        const empty = !secItems.length;
        const rOvKey = sec + '_r';
        const rOv = ov[rOvKey];
        tbody += `<td class="erec-computed" data-role="t" data-student="${stu.id}" data-section="${sec}">${empty ? '–' : fmt(sr.t)}</td>`;
        tbody += `<td><input class="erec-remarks erec-r-override" style="width:56px" type="number" step="0.5" value="${rOv === null || rOv === undefined ? '' : rOv}" data-ov="${rOvKey}" data-student="${stu.id}" data-section="${sec}" placeholder="${empty ? '0' : fmt(sr.r)}" title="Type to override the computed R for this section — leave blank to use the auto value shown as placeholder."></td>`;
        tbody += `<td></td>`;
      });
      tbody += `<td class="erec-computed erec-total" data-role="total" data-student="${stu.id}">${row ? fmt(row.total) : '0'}</td>`;
      tbody += `<td><input class="erec-remarks" style="width:50px" type="number" step="0.5" value="${ov.fr ?? ''}" data-ov="fr" data-student="${stu.id}" placeholder="${row ? fmt(row.total) : ''}"></td>`;
      tbody += `<td><input class="erec-remarks" style="width:60px" type="number" step="0.5" value="${ov.final_grade ?? ''}" data-ov="final_grade" data-student="${stu.id}" placeholder="${row ? fmt(row.total) : ''}"></td>`;
      tbody += `<td><input class="erec-remarks" type="text" value="${escapeHtml(ov.remarks || '')}" data-ov="remarks" data-student="${stu.id}" placeholder="—"></td>`;
      tbody += '</tr>';
    });

    if (!state.students.length) {
      tbody = `<tr><td colspan="99" class="erec-empty-hint">No approved students enrolled in this subject yet — once students enroll and you approve them, they'll show up here.</td></tr>`;
    }

    table.innerHTML = `<thead>${theadTop}${theadBottom}</thead><tbody>${tbody}</tbody>`;
    wireTableEvents();
  }

  function fmt(n) {
    n = parseFloat(n || 0);
    return (Math.round(n * 100) / 100).toString();
  }

  function escapeHtml(s) {
    return String(s || '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  }

  function wireTableEvents() {
    table.querySelectorAll('input.erec-score').forEach(inp => {
      inp.addEventListener('input', () => {
        const stu = inp.dataset.student, item = inp.dataset.item, sec = inp.dataset.section;
        recomputeStudentSection(stu, sec);
        const key = stu + ':' + item;
        clearTimeout(saveDebounce[key]);
        saveDebounce[key] = setTimeout(() => {
          post('save_score', { item_id: item, student_id: stu, score: inp.value }).then(res => {
            if (res.ok) { state.scores[key] = inp.value === '' ? null : parseFloat(inp.value); flash('Saved.'); }
            else flash(res.error || 'Save failed.', true);
          });
        }, 500);
      });
    });

    table.querySelectorAll('input.erec-remarks').forEach(inp => {
      inp.addEventListener('change', () => {
        const stu = inp.dataset.student;
        const row = table.querySelectorAll(`input[data-ov][data-student="${stu}"]`);
        const vals = {}; row.forEach(r => vals[r.dataset.ov] = r.value);
        if (inp.dataset.section) recomputeStudentSection(stu, inp.dataset.section);
        post('save_override', { config_id: state.config.id, student_id: stu, ...vals }).then(res => {
          if (res.ok) flash('Saved.'); else flash(res.error || 'Save failed.', true);
        });
      });
    });

    table.querySelectorAll('th[data-item]').forEach(th => {
      th.addEventListener('dblclick', () => {
        const label = th.textContent.trim();
        openModal({
          title: 'Remove this column?',
          bodyHtml: `<p class="erec-modal-text">Scores entered under column <strong>"${escapeHtml(label)}"</strong> will be permanently deleted for every student. This can't be undone.</p>`,
          confirmLabel: 'Remove column',
          danger: true,
          onConfirm: () => {
            closeModal();
            post('delete_item', { item_id: th.dataset.item }).then(res => {
              if (res.ok) loadRecord(); else flash(res.error || 'Delete failed.', true);
            });
          },
        });
      });
    });

    table.querySelectorAll('th.erec-add-col').forEach(th => {
      th.addEventListener('click', () => {
        const sec = th.dataset.addSec;
        const nextNum = (state.items[sec] || []).length + 1;
        openModal({
          title: `Add to ${sectionLabel(sec)}`,
          bodyHtml: `
            <label class="erec-modal-field">Label
              <input type="text" id="erecModalLabel" placeholder='e.g. "${sectionLabel(sec)} ${nextNum}"'>
            </label>
            <label class="erec-modal-field">Highest possible score (HPS)
              <input type="number" id="erecModalMax" value="10" min="0.5" step="0.5">
            </label>
          `,
          confirmLabel: 'Add column',
          focusSelector: '#erecModalLabel',
          onConfirm: () => {
            const label = document.getElementById('erecModalLabel').value;
            const maxScore = document.getElementById('erecModalMax').value;
            if (!maxScore || parseFloat(maxScore) <= 0) { flash('Enter a highest possible score greater than 0.', true); return; }
            closeModal();
            post('add_item', { config_id: state.config.id, section: sec, label: label, max_score: maxScore }).then(res => {
              if (res.ok) loadRecord(); else flash(res.error || 'Add failed.', true);
            });
          },
        });
      });
    });
  }

  function recomputeStudentSection(stuId, sec) {
    const items = (state.items[sec] || []);
    let t = 0, hps = 0, any = false;
    items.forEach(it => {
      hps += parseFloat(it.max_score);
      const inp = table.querySelector(`input.erec-score[data-student="${stuId}"][data-item="${it.id}"]`);
      if (inp && inp.value !== '') { t += parseFloat(inp.value); any = true; }
    });
    const fieldMap = { quiz: 'quiz_points', pt: 'pt_points', attendance: 'attendance_points', major_exam: 'major_exam_points', essay: 'essay_points' };
    const secPoints = parseFloat(state.config[fieldMap[sec]] || 0);
    const autoR = (hps > 0 && any) ? Math.round((t / hps) * secPoints * 100) / 100 : 0;

    const emptySec = !items.length;
    const rInput = table.querySelector(`input.erec-r-override[data-student="${stuId}"][data-section="${sec}"]`);
    let r = autoR;
    if (rInput) {
      rInput.placeholder = emptySec ? '0' : fmt(autoR);
      if (rInput.value !== '') r = parseFloat(rInput.value) || 0;
    }

    if (!state.computed[stuId]) state.computed[stuId] = { sections: {}, total: 0 };
    state.computed[stuId].sections[sec] = { t, hps, r, has_scores: any };

    table.querySelector(`td.erec-computed[data-role="t"][data-student="${stuId}"][data-section="${sec}"]`).textContent = emptySec ? '–' : fmt(t);

    let total = 0;
    SECTION_KEYS.forEach(s => total += (state.computed[stuId].sections[s] ? state.computed[stuId].sections[s].r : 0));
    state.computed[stuId].total = Math.round(total * 100) / 100;
    table.querySelector(`td.erec-total[data-student="${stuId}"]`).textContent = fmt(total);
  }

  function recomputeAll() {
    state.students.forEach(stu => SECTION_KEYS.forEach(sec => recomputeStudentSection(stu.id, sec)));
  }

  instructorInput.addEventListener('change', saveHeader);
  scheduleInput.addEventListener('change', saveHeader);
  schoolAttendedInput.addEventListener('change', saveHeader);
  function saveHeader() {
    post('save_header', { config_id: state.config.id, instructor_name: instructorInput.value, time_schedule: scheduleInput.value, school_attended: schoolAttendedInput.value })
      .then(res => { if (res.ok) flash('Saved.'); else flash(res.error || 'Save failed.', true); });
  }

  subjectSel.addEventListener('change', loadRecord);
  termSel.addEventListener('change', loadRecord);

  const SECTION_FIELD = { quiz: 'quiz_points', pt: 'pt_points', attendance: 'attendance_points', major_exam: 'major_exam_points', essay: 'essay_points' };
  const SECTION_FILL = { quiz: 'FFDDEBF7', pt: 'FFFCE4D6', attendance: 'FFE2EFDA', major_exam: 'FFFFF2CC', essay: 'FFEDEDED' };
  let exceljsPromise = null;

  function ensureExcelJs() {
    if (window.ExcelJS) return Promise.resolve();
    if (exceljsPromise) return exceljsPromise;
    exceljsPromise = new Promise((resolve, reject) => {
      const s = document.createElement('script');
      s.src = 'https://cdn.jsdelivr.net/npm/exceljs@4.4.0/dist/exceljs.min.js';
      s.onload = () => resolve();
      s.onerror = () => reject(new Error('Could not load the Excel export library — check your connection and try again.'));
      document.head.appendChild(s);
    });
    return exceljsPromise;
  }

  const THIN_BORDER = { top: { style: 'thin' }, left: { style: 'thin' }, bottom: { style: 'thin' }, right: { style: 'thin' } };

  async function buildWorkbook() {
    const items = state.items;
    const wb = new ExcelJS.Workbook();
    wb.creator = 'Inkwell';
    const ws = wb.addWorksheet((state.subject.title || 'Class Record').slice(0, 28).replace(/[\\/*?:[\]]/g, ' '));

    const colCount = 1 + SECTION_KEYS.reduce((n, sec) => n + (items[sec] || []).length + 2, 0) + 4;
    const half = Math.max(2, Math.floor(colCount / 2));

    ws.mergeCells(1, 1, 1, colCount);
    ws.getCell(1, 1).value = 'BIT INTERNATIONAL COLLEGE - TALIBON';
    ws.getCell(1, 1).font = { bold: true, size: 16, color: { argb: 'FF1F3864' } };
    ws.getCell(1, 1).alignment = { horizontal: 'center', vertical: 'middle' };

    ws.mergeCells(2, 1, 2, colCount);
    ws.getCell(2, 1).value = 'San Jose, Talibon, Bohol';
    ws.getCell(2, 1).font = { italic: true, underline: true, size: 10, color: { argb: 'FF1F3864' } };
    ws.getCell(2, 1).alignment = { horizontal: 'center' };

    ws.mergeCells(4, 1, 4, colCount);
    ws.getCell(4, 1).value = 'E-CLASS RECORD';
    ws.getCell(4, 1).font = { bold: true, underline: true, size: 13 };
    ws.getCell(4, 1).alignment = { horizontal: 'center' };

    ws.mergeCells(6, 1, 6, half);
    ws.getCell(6, 1).value = `Name of Instructor: ${instructorInput.value || ''}`;
    ws.mergeCells(6, half + 1, 6, colCount);
    ws.getCell(6, half + 1).value = `Subject: ${state.subject.title || ''}`;

    ws.mergeCells(7, 1, 7, half);
    ws.getCell(7, 1).value = `Time & Schedule: ${scheduleInput.value || ''}`;
    ws.mergeCells(7, half + 1, 7, colCount);
    ws.getCell(7, half + 1).value = `Term: ${termSel.options[termSel.selectedIndex].text}`;

    ws.mergeCells(8, 1, 8, colCount);
    ws.getCell(8, 1).value = `School Attended: ${schoolAttendedInput.value || ''}`;

    [6, 7, 8].forEach(rn => { for (let c = 1; c <= colCount; c++) ws.getCell(rn, c).font = { bold: true, size: 10 }; });

    const headTopRow = 10, headBottomRow = 11;
    ws.mergeCells(headTopRow, 1, headBottomRow, 1);
    ws.getCell(headTopRow, 1).value = 'Name of Student';

    let col = 2;
    SECTION_KEYS.forEach(sec => {
      const secItems = items[sec] || [];
      const span = secItems.length + 2;
      ws.mergeCells(headTopRow, col, headTopRow, col + span - 1);
      const pts = parseFloat(state.config[SECTION_FIELD[sec]] || 0);
      ws.getCell(headTopRow, col).value = `${sectionLabel(sec)} (${fmt(pts)} pts)`;
      for (let c = col; c < col + span; c++) {
        ws.getCell(headTopRow, c).fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: SECTION_FILL[sec] || 'FFF2F2F2' } };
      }
      secItems.forEach((it, idx) => { ws.getCell(headBottomRow, col).value = idx + 1; col++; });
      ws.getCell(headBottomRow, col).value = 'T'; col++;
      ws.getCell(headBottomRow, col).value = 'R'; col++;
    });
    ['Total', 'FR', 'Final Grade', 'Remarks'].forEach(label => {
      ws.mergeCells(headTopRow, col, headBottomRow, col);
      ws.getCell(headTopRow, col).value = label;
      col++;
    });

    for (let r = headTopRow; r <= headBottomRow; r++) {
      for (let c = 1; c <= colCount; c++) {
        const cell = ws.getCell(r, c);
        cell.alignment = { horizontal: 'center', vertical: 'middle', wrapText: true };
        cell.font = Object.assign({ bold: true, size: 9 }, cell.font || {});
        cell.border = THIN_BORDER;
      }
    }

    let r = headBottomRow + 1;
    state.students.forEach(stu => {
      const row = state.computed[stu.id] || { sections: {}, total: 0 };
      const ov = state.overrides[stu.id] || {};
      let c = 1;
      ws.getCell(r, c).value = stu.name; c++;
      SECTION_KEYS.forEach(sec => {
        const secItems = items[sec] || [];
        secItems.forEach(it => {
          const v = state.scores[stu.id + ':' + it.id];
          ws.getCell(r, c).value = (v === null || v === undefined) ? null : v;
          c++;
        });
        const sr = row.sections[sec] || { t: 0, r: 0 };
        ws.getCell(r, c).value = secItems.length ? parseFloat(fmt(sr.t)) : null; c++;
        ws.getCell(r, c).value = parseFloat(fmt(sr.r)); c++;
      });
      ws.getCell(r, c).value = parseFloat(fmt(row.total)); c++;
      ws.getCell(r, c).value = (ov.fr ?? '') !== '' ? parseFloat(ov.fr) : parseFloat(fmt(row.total)); c++;
      ws.getCell(r, c).value = (ov.final_grade ?? '') !== '' ? parseFloat(ov.final_grade) : parseFloat(fmt(row.total)); c++;
      ws.getCell(r, c).value = ov.remarks || ''; c++;

      for (let cc = 1; cc <= colCount; cc++) {
        const cell = ws.getCell(r, cc);
        cell.border = THIN_BORDER;
        cell.alignment = { horizontal: cc === 1 ? 'left' : 'center', vertical: 'middle' };
        if ((r - headBottomRow) % 2 === 0) cell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFF7F7F7' } };
      }
      r++;
    });

    if (!state.students.length) {
      ws.mergeCells(r, 1, r, colCount);
      ws.getCell(r, 1).value = 'No approved students enrolled in this subject yet.';
      ws.getCell(r, 1).alignment = { horizontal: 'center' };
    }

    ws.getColumn(1).width = 26;
    for (let c = 2; c <= colCount; c++) ws.getColumn(c).width = 8;
    ws.views = [{ state: 'frozen', xSplit: 1, ySplit: headBottomRow }];

    try {
      const resp = await fetch('/assets/img/logo.png');
      if (resp.ok) {
        const buf = await resp.arrayBuffer();
        if (buf.byteLength) {
          const imgId = wb.addImage({ buffer: buf, extension: 'png' });
          ws.addImage(imgId, { tl: { col: 0.15, row: 0.1 }, ext: { width: 50, height: 50 } });
        }
      }
    } catch (e) { /* no logo available — export still works without it */ }

    return wb;
  }

  exportBtn.addEventListener('click', async () => {
    if (!state) return;
    const prevLabel = exportBtn.textContent;
    exportBtn.disabled = true;
    exportBtn.textContent = 'Preparing…';
    try {
      await ensureExcelJs();
      const wb = await buildWorkbook();
      const buffer = await wb.xlsx.writeBuffer();
      const blob = new Blob([buffer], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
      const a = document.createElement('a');
      a.href = URL.createObjectURL(blob);
      const safeSubj = (state.subject.title || 'subject').replace(/[^a-z0-9]+/gi, '_');
      a.download = `class-record_${safeSubj}_${termSel.value}.xlsx`;
      a.click();
      setTimeout(() => URL.revokeObjectURL(a.href), 4000);
    } catch (err) {
      flash((err && err.message) || 'Export failed.', true);
    } finally {
      exportBtn.disabled = false;
      exportBtn.textContent = prevLabel;
    }
  });

  loadRecord();
})();
</script>

<?php include __DIR__ . '/../includes/drive_shell_bottom.php'; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
