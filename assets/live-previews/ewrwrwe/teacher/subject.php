<?php
// Inside/dashboard page — suppress the marketing topbar (see includes/header.php).
$__hideTopbar = true;
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/exams_db.php';

$user = inkwell_require_role('teacher');
if ($user['status'] !== 'active') {
  header('Location: /teacher/dashboard.php');
  exit;
}

$subId = (int) ($_GET['id'] ?? $_POST['subject_id'] ?? 0);
$subject = inkwell_get_subject($subId);
if (!$subject || (int) $subject['teacher_id'] !== (int) $user['id']) {
  http_response_code(404);
  die('Subject not found.');
}
$subjectColsOk = inkwell_ensure_subject_code_units_columns();

$error = '';
$notice = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';

  if ($action === 'edit_subject') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $code = trim($_POST['code'] ?? '');
    $units = (int) ($_POST['units'] ?? 3);
    if ($title === '') {
      $error = 'Give the subject a title.';
    } else {
      inkwell_update_subject($subId, $title, $description, $code, $units);
      $subject = inkwell_get_subject($subId);
      $notice = 'Subject details updated.';
    }
  }

  if ($action === 'create_exam') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $passScore = max(1, min(100, (int) ($_POST['pass_score'] ?? 70)));
    $purpose = in_array($_POST['purpose'] ?? '', ['cert', 'grade'], true) ? $_POST['purpose'] : 'cert';
    $maxAttempts = ($_POST['attempt_limit'] ?? 'unlimited') === 'single' ? 1 : null;
    $kind = ($_POST['kind'] ?? 'exam') === 'project' ? 'project' : 'exam';
    $term = array_key_exists($_POST['term'] ?? '', inkwell_class_record_terms()) ? $_POST['term'] : 'prelim';
    if ($title === '') {
      $error = 'Give the exam a title.';
    } else {
      $newId = inkwell_create_teacher_exam($user['id'], $subId, $title, $description, $passScore, $purpose, $maxAttempts, $kind, $term);
      header('Location: /teacher/category.php?id=' . $newId);
      exit;
    }
  }

  if ($action === 'delete_exam') {
    $examId = (int) ($_POST['category_id'] ?? 0);
    $exam = inkwell_get_teacher_category($examId);
    if ($exam && (int) $exam['teacher_id'] === (int) $user['id']) {
      inkwell_delete_teacher_category($examId);
      $notice = 'Exam deleted.';
    }
  }
}

$exams = inkwell_subject_exams($subId);
$pageTitle = $subject['title'];
include __DIR__ . '/../includes/header.php';
?>
<main class="admin-main">
  <div class="admin-header-row">
    <div>
      <h1><?php echo htmlspecialchars($subject['title']); ?><?php echo !empty($subject['code']) ? ' <span class="admin-sub" style="font-weight:400;">(' . htmlspecialchars($subject['code']) . ')</span>' : ''; ?></h1>
      <p class="admin-sub"><?php echo (int) ($subject['units'] ?? 3); ?> unit<?php echo (int) ($subject['units'] ?? 3) === 1 ? '' : 's'; ?></p>
    </div>
    <div style="display:flex; gap:10px;">
      <button class="btn" type="button" data-modal-open="editSubjectModal">Edit subject</button>
      <a class="btn" href="/teacher/dashboard.php">← Back to subjects</a>
    </div>
  </div>

  <?php if ($notice): ?><div class="exam-result pass"><?php echo htmlspecialchars($notice); ?></div><?php endif; ?>
  <?php if ($error): ?><div class="exam-result fail"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

  <div class="modal-backdrop" id="editSubjectModal">
    <div class="modal">
      <div class="modal-head">
        <h2>Edit subject</h2>
        <button type="button" data-modal-close aria-label="Close">✕</button>
      </div>
      <p class="admin-sub">The code and units show up on a student's Certificate of Registration.</p>
      <form method="post" action="/teacher/subject.php?id=<?php echo (int) $subId; ?>" class="admin-form">
        <input type="hidden" name="action" value="edit_subject">
        <label for="edit_title">Title</label>
        <input type="text" id="edit_title" name="title" maxlength="150" required value="<?php echo htmlspecialchars($subject['title']); ?>">
        <label for="edit_description">Description (optional)</label>
        <input type="text" id="edit_description" name="description" maxlength="500" value="<?php echo htmlspecialchars($subject['description'] ?? ''); ?>">
        <div class="form-grid-2">
          <div>
            <label for="edit_code">Subject code (optional)</label>
            <input type="text" id="edit_code" name="code" maxlength="20" placeholder="e.g. SE101" value="<?php echo htmlspecialchars($subject['code'] ?? ''); ?>"<?php echo (!$subjectColsOk['code']) ? ' disabled' : ''; ?>>
          </div>
          <div>
            <label for="edit_units">Units</label>
            <input type="number" id="edit_units" name="units" min="1" max="12" value="<?php echo (int) ($subject['units'] ?? 3); ?>"<?php echo (!$subjectColsOk['units']) ? ' disabled' : ''; ?>>
          </div>
        </div>
        <?php if (!$subjectColsOk['code'] || !$subjectColsOk['units']): ?>
          <p class="exam-result fail">Subject code / units can't be saved yet — the database is missing those columns. Run <code>MIGRATION_ADD_subject_code_units.sql</code> from phpMyAdmin, then reload this page.</p>
        <?php endif; ?>
        <button class="btn primary" type="submit">Save changes</button>
      </form>
    </div>
  </div>

  <section class="admin-card glass-card">
    <div class="admin-header-row" style="margin-bottom:0;">
      <div>
        <h2>Exams in this subject (<?php echo count($exams); ?>)</h2>
        <p class="admin-sub">One exam students take under this subject. Choose whether it's for certification or just a grade.</p>
      </div>
      <button class="btn primary" type="button" data-modal-open="createExamModal">+ New exam</button>
    </div>
    <?php if (empty($exams)): ?>
      <p class="admin-sub">No exams yet — create one above.</p>
    <?php else: ?>
      <div class="search-filter">
        <input type="search" class="search-filter-input" data-filter-target="#subjectExamsTable" placeholder="Search exams...">
      </div>
      <div class="admin-table-wrap">
        <table class="admin-table" id="subjectExamsTable" data-paginate="10">
          <thead><tr><th>Title</th><th>Kind</th><th>Term</th><th>Purpose</th><th>Pass score</th><th>Attempts</th><th>Questions</th><th></th><th></th><th></th></tr></thead>
          <tbody>
            <?php foreach ($exams as $ex): ?>
              <tr data-filter-row>
                <td><?php echo htmlspecialchars($ex['title']); ?></td>
                <td><span class="badge <?php echo ($ex['kind'] ?? 'exam') === 'project' ? 'badge-status-pending' : 'badge-status-active'; ?>"><?php echo ($ex['kind'] ?? 'exam') === 'project' ? 'Project' : 'Exam'; ?></span></td>
                <td><span class="badge badge-status-active"><?php echo htmlspecialchars(inkwell_class_record_terms()[$ex['term'] ?? 'prelim'] ?? 'Prelim'); ?></span></td>
                <td><span class="badge badge-purpose-<?php echo $ex['purpose']; ?>"><?php echo $ex['purpose'] === 'cert' ? 'Certificate' : 'Grade only'; ?></span></td>
                <td><?php echo (int) $ex['pass_score']; ?>%</td>
                <td><span class="badge <?php echo (int) ($ex['max_attempts'] ?? 0) === 1 ? 'badge-status-pending' : 'badge-status-active'; ?>"><?php echo (int) ($ex['max_attempts'] ?? 0) === 1 ? '1 attempt' : 'Unlimited'; ?></span></td>
                <td><?php echo (int) $ex['question_count']; ?></td>
                <td><a href="/teacher/category.php?id=<?php echo (int) $ex['id']; ?>">Manage questions →</a></td>
                <td><a class="btn" href="/teacher/download-exam.php?id=<?php echo (int) $ex['id']; ?>">⬇ Download (.docx)</a></td>
                <td>
                  <form method="post" action="/teacher/subject.php?id=<?php echo $subId; ?>" onsubmit="return confirm('Delete this exam and all its questions?');">
                    <input type="hidden" name="action" value="delete_exam">
                    <input type="hidden" name="category_id" value="<?php echo (int) $ex['id']; ?>">
                    <button class="btn" type="submit">Delete</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>

  <!-- New exam modal -->
  <div class="modal-backdrop" id="createExamModal">
    <div class="modal">
      <div class="modal-head">
        <h2>New exam</h2>
        <button type="button" data-modal-close aria-label="Close">✕</button>
      </div>
      <form method="post" action="/teacher/subject.php?id=<?php echo $subId; ?>" class="admin-form">
        <input type="hidden" name="action" value="create_exam">
        <input type="hidden" name="subject_id" value="<?php echo $subId; ?>">

        <label>Exam or project?</label>
        <div class="purpose-picker">
          <label class="purpose-option active" data-purpose-btn="exam">
            <input type="radio" name="kind" value="exam" checked>
            <strong>Exam</strong>
            <span class="hint">A timed/question-based assessment.</span>
          </label>
          <label class="purpose-option" data-purpose-btn="project">
            <input type="radio" name="kind" value="project">
            <strong>Project</strong>
            <span class="hint">A submitted/manually-graded piece of work.</span>
          </label>
        </div>

        <label>Which term does this count toward?</label>
        <div class="purpose-picker">
          <label class="purpose-option active" data-purpose-btn="prelim">
            <input type="radio" name="term" value="prelim" checked>
            <strong>Prelim</strong>
          </label>
          <label class="purpose-option" data-purpose-btn="midterm">
            <input type="radio" name="term" value="midterm">
            <strong>Midterm</strong>
          </label>
          <label class="purpose-option" data-purpose-btn="final">
            <input type="radio" name="term" value="final">
            <strong>Final</strong>
          </label>
        </div>
        <p class="admin-sub" style="margin-top:-8px;">Its grade is averaged with everything else in that term to make the Term Grade on the Class Record; Prelim, Midterm, and Final are then averaged into the Final Grade.</p>

        <label>What is this exam for?</label>
        <div class="purpose-picker">
          <label class="purpose-option active" data-purpose-btn="cert">
            <input type="radio" name="purpose" value="cert" checked>
            <strong>Certification</strong>
            <span class="hint">Issues a certificate to students who pass.</span>
          </label>
          <label class="purpose-option" data-purpose-btn="grade">
            <input type="radio" name="purpose" value="grade">
            <strong>Grade only</strong>
            <span class="hint">Records a pass/fail score — no certificate.</span>
          </label>
        </div>

        <label>How many times can a student take it?</label>
        <div class="purpose-picker">
          <label class="purpose-option active" data-purpose-btn="unlimited">
            <input type="radio" name="attempt_limit" value="unlimited" checked>
            <strong>Every student, unlimited</strong>
            <span class="hint">Any student can retake this exam as many times as they like.</span>
          </label>
          <label class="purpose-option" data-purpose-btn="single">
            <input type="radio" name="attempt_limit" value="single">
            <strong>1 attempt per student</strong>
            <span class="hint">Each student only gets a single try — no retakes.</span>
          </label>
        </div>

        <label for="title">Title</label>
        <input type="text" id="title" name="title" maxlength="150" required>
        <label for="description">Description (optional)</label>
        <input type="text" id="description" name="description" maxlength="500">
        <label for="pass_score">Pass score (%)</label>
        <input type="number" id="pass_score" name="pass_score" min="1" max="100" value="70">
        <button class="btn primary" type="submit">Create exam</button>
      </form>
    </div>
  </div>
</main>
<script>
(function () {
  document.querySelectorAll('.purpose-picker').forEach((group) => {
    const buttons = group.querySelectorAll('[data-purpose-btn]');
    buttons.forEach((btn) => {
      btn.addEventListener('click', () => {
        buttons.forEach((b) => b.classList.toggle('active', b === btn));
      });
    });
  });
})();
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
