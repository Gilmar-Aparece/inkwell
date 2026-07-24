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

$catId = (int) ($_GET['id'] ?? $_POST['category_id'] ?? 0);
$category = inkwell_get_teacher_category($catId);
if (!$category || (int) $category['teacher_id'] !== (int) $user['id']) {
  http_response_code(404);
  die('Exam not found.');
}

$error = '';
$notice = '';
$questionError = '';
$CODE_LANGS = ['javascript' => 'JavaScript', 'python' => 'Python', 'php' => 'PHP', 'java' => 'Java', 'csharp' => 'C#', 'cpp' => 'C++', 'html' => 'HTML/CSS', 'sql' => 'SQL'];

// Sticky values for the "Add question" modal — repopulated from $_POST below
// if validation fails, so the form re-opens with what the teacher typed
// instead of silently closing and losing it.
$qv = [
  'qtype' => 'mcq', 'question' => '',
  'option_a' => '', 'option_b' => '', 'option_c' => '', 'option_d' => '', 'correct_index' => 0,
  'code_language' => 'javascript', 'code_starter' => '', 'auto_grade_output' => true, 'expected_output' => '',
  'max_points' => 10,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';

  if ($action === 'add_question') {
    $qtype = in_array($_POST['qtype'] ?? '', ['mcq', 'code', 'essay'], true) ? $_POST['qtype'] : 'mcq';
    $question = trim($_POST['question'] ?? '');
    $qv['qtype'] = $qtype;
    $qv['question'] = $question;

    if ($question === '') {
      $questionError = 'Write the question prompt.';
    } elseif ($qtype === 'mcq') {
      $options = [
        trim($_POST['option_a'] ?? ''), trim($_POST['option_b'] ?? ''),
        trim($_POST['option_c'] ?? ''), trim($_POST['option_d'] ?? ''),
      ];
      $correct = (int) ($_POST['correct_index'] ?? 0);
      $qv['option_a'] = $options[0]; $qv['option_b'] = $options[1];
      $qv['option_c'] = $options[2]; $qv['option_d'] = $options[3];
      $qv['correct_index'] = $correct;
      if (in_array('', $options, true)) {
        $questionError = 'Fill in all four options.';
      } else {
        inkwell_add_teacher_question($catId, [
          'qtype' => 'mcq', 'question' => $question, 'options' => $options,
          'correct_index' => $correct, 'max_points' => 1,
        ]);
        $notice = 'Question added.';
      }
    } elseif ($qtype === 'code') {
      $lang = $_POST['code_language'] ?? 'javascript';
      if (!isset($CODE_LANGS[$lang])) $lang = 'javascript';
      $starter = $_POST['code_starter'] ?? '';
      $points = max(1, min(100, (int) ($_POST['max_points'] ?? 10)));
      $autoGrade = isset($_POST['auto_grade_output']) && inkwell_code_lang_supports_autograde($lang);
      $expectedOutput = trim($_POST['expected_output'] ?? '');
      $qv['code_language'] = $lang; $qv['code_starter'] = $starter; $qv['max_points'] = $points;
      $qv['auto_grade_output'] = $autoGrade; $qv['expected_output'] = $expectedOutput;
      if ($autoGrade && $expectedOutput === '') {
        $autoGrade = false; // no sample output given, so fall back to manual grading instead of silently marking everyone wrong
      }
      inkwell_add_teacher_question($catId, [
        'qtype' => 'code', 'question' => $question, 'code_language' => $lang,
        'code_starter' => $starter, 'max_points' => $points,
        'auto_grade_output' => $autoGrade, 'expected_output' => $autoGrade ? $expectedOutput : null,
      ]);
      $notice = $autoGrade ? 'Code question added — will be auto-graded by matching output.' : 'Code question added.';
    } else { // essay
      $points = max(1, min(100, (int) ($_POST['max_points'] ?? 10)));
      $qv['max_points'] = $points;
      inkwell_add_teacher_question($catId, [
        'qtype' => 'essay', 'question' => $question, 'max_points' => $points,
      ]);
      $notice = 'Essay question added.';
    }
  }

  if ($action === 'delete_question') {
    inkwell_delete_teacher_question((int) ($_POST['question_id'] ?? 0));
    $notice = 'Question deleted.';
  }

  if ($action === 'update_schedule') {
    $isEnabled = isset($_POST['is_enabled']);
    $from = trim($_POST['available_from'] ?? '');
    $until = trim($_POST['available_until'] ?? '');
    $from = $from !== '' ? str_replace('T', ' ', $from) . ':00' : '';
    $until = $until !== '' ? str_replace('T', ' ', $until) . ':00' : '';
    $timeLimitRaw = trim($_POST['time_limit_minutes'] ?? '');
    $timeLimit = $timeLimitRaw !== '' ? max(1, min(600, (int) $timeLimitRaw)) : null;
    if ($from !== '' && $until !== '' && $from >= $until) {
      $error = '"Opens" has to be before "Closes".';
    } else {
      inkwell_update_exam_schedule($catId, $user['id'], $isEnabled, $from, $until, $timeLimit);
      $category = inkwell_get_teacher_category($catId);
      $notice = 'Schedule updated.';
    }
  }
}

$questions = inkwell_get_teacher_questions($catId);
$schedule = inkwell_exam_schedule_status($category);
$pageTitle = 'Manage: ' . $category['title'];
include __DIR__ . '/../includes/header.php';
?>
<main class="admin-main">
  <div class="admin-header-row">
    <div>
      <div class="crumb"><a href="/teacher/dashboard.php">Subjects</a> / <a href="/teacher/subject.php?id=<?php echo (int) $category['subject_id']; ?>"><?php echo htmlspecialchars($category['subject_title'] ?? 'Subject'); ?></a></div>
      <h1><?php echo htmlspecialchars($category['title']); ?></h1>
    </div>
    <div style="display:flex; gap:8px;">
      <a class="btn primary" href="/teacher/download-exam.php?id=<?php echo (int) $category['id']; ?>">⬇ Download (.docx)</a>
      <a class="btn" href="/teacher/subject.php?id=<?php echo (int) $category['subject_id']; ?>">← Back to exams</a>
    </div>
  </div>

  <p class="admin-sub" style="margin-top:-8px;">
    <span class="badge badge-purpose-<?php echo $category['purpose']; ?>"><?php echo $category['purpose'] === 'cert' ? 'Certificate exam' : 'Grade-only exam'; ?></span>
  </p>

  <?php if ($notice): ?><div class="exam-result pass"><?php echo htmlspecialchars($notice); ?></div><?php endif; ?>
  <?php if ($error): ?><div class="exam-result fail"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

  <section class="admin-card glass-card">
    <div class="admin-header-row" style="margin-bottom:8px;">
      <div>
        <h2 style="margin:0;">Schedule &amp; availability</h2>
        <p class="admin-sub" style="margin:2px 0 0;">Control when students can take this exam — turn it off any time, or set a window that closes itself automatically.</p>
      </div>
      <?php if ($schedule['open']): ?>
        <span class="badge badge-status-active">● Open now</span>
      <?php elseif ($schedule['reason'] === 'not_yet'): ?>
        <span class="badge badge-status-pending">Opens <?php echo htmlspecialchars(date('M j, g:i A', strtotime($schedule['at']))); ?></span>
      <?php elseif ($schedule['reason'] === 'ended'): ?>
        <span class="badge badge-status-pending">Closed automatically <?php echo htmlspecialchars(date('M j, g:i A', strtotime($schedule['at']))); ?></span>
      <?php else: ?>
        <span class="badge badge-status-pending">Closed</span>
      <?php endif; ?>
      <?php if (!empty($category['time_limit_minutes'])): ?>
        <span class="badge badge-status-active">⏱ <?php echo (int) $category['time_limit_minutes']; ?> min limit</span>
      <?php endif; ?>
    </div>

    <form method="post" action="/teacher/category.php?id=<?php echo $catId; ?>" class="admin-form">
      <input type="hidden" name="action" value="update_schedule">
      <label style="display:flex; align-items:center; gap:8px; font-weight:600;">
        <input type="checkbox" name="is_enabled" value="1" style="width:auto;" <?php echo (int) ($category['is_enabled'] ?? 1) ? 'checked' : ''; ?>>
        Exam is open for students
      </label>
      <p class="admin-sub" style="margin-top:-4px;">Turn this off to close the exam immediately, regardless of the schedule below.</p>

      <label for="available_from">Opens (optional)</label>
      <input type="datetime-local" id="available_from" name="available_from" value="<?php echo !empty($category['available_from']) ? htmlspecialchars(date('Y-m-d\TH:i', strtotime($category['available_from']))) : ''; ?>">
      <label for="available_until">Closes (optional — exam disables itself automatically at this time)</label>
      <input type="datetime-local" id="available_until" name="available_until" value="<?php echo !empty($category['available_until']) ? htmlspecialchars(date('Y-m-d\TH:i', strtotime($category['available_until']))) : ''; ?>">
      <p class="admin-sub" style="margin-top:-4px;">Leave either blank to skip that boundary. Leave both blank for no schedule at all.</p>

      <label for="time_limit_minutes">Time limit in minutes (optional)</label>
      <input type="number" id="time_limit_minutes" name="time_limit_minutes" min="1" max="600" placeholder="No time limit" value="<?php echo !empty($category['time_limit_minutes']) ? (int) $category['time_limit_minutes'] : ''; ?>">
      <p class="admin-sub" style="margin-top:-4px;">Countdown starts the moment a student opens the exam. It auto-submits whatever they've answered when time runs out. Leave blank for no limit.</p>

      <button class="btn primary" type="submit">Save schedule</button>
    </form>
  </section>

  <div class="admin-header-row" style="margin-bottom:0;">
    <h2 style="margin:0;">Questions (<?php echo count($questions); ?>)</h2>
    <button class="btn primary" type="button" data-modal-open="addQuestionModal">+ Add question</button>
  </div>

  <div class="modal-backdrop<?php echo $questionError ? ' open' : ''; ?>" id="addQuestionModal">
    <div class="modal">
    <div class="modal-head">
      <h2>Add a question</h2>
      <button type="button" data-modal-close aria-label="Close">✕</button>
    </div>
    <?php if ($questionError): ?><div class="exam-result fail" style="margin-top:10px;"><?php echo htmlspecialchars($questionError); ?></div><?php endif; ?>
    <form method="post" action="/teacher/category.php?id=<?php echo $catId; ?>" class="admin-form" id="questionForm">
      <input type="hidden" name="action" value="add_question">
      <input type="hidden" name="category_id" value="<?php echo $catId; ?>">

      <label>Question type</label>
      <div class="qtype-picker">
        <label class="qtype-option<?php echo $qv['qtype'] === 'mcq' ? ' active' : ''; ?>" data-qtype-btn="mcq"><input type="radio" name="qtype" value="mcq" <?php echo $qv['qtype'] === 'mcq' ? 'checked' : ''; ?>> ☑ Multiple choice</label>
        <label class="qtype-option<?php echo $qv['qtype'] === 'code' ? ' active' : ''; ?>" data-qtype-btn="code"><input type="radio" name="qtype" value="code" <?php echo $qv['qtype'] === 'code' ? 'checked' : ''; ?>> {'}'} Code</label>
        <label class="qtype-option<?php echo $qv['qtype'] === 'essay' ? ' active' : ''; ?>" data-qtype-btn="essay"><input type="radio" name="qtype" value="essay" <?php echo $qv['qtype'] === 'essay' ? 'checked' : ''; ?>> ✎ Essay</label>
      </div>

      <label for="question">Question / prompt</label>
      <textarea id="question" name="question" rows="2" maxlength="1000" required><?php echo htmlspecialchars($qv['question']); ?></textarea>

      <div data-qtype-fields="mcq" <?php echo $qv['qtype'] !== 'mcq' ? 'style="display:none;"' : ''; ?>>
        <label for="option_a">Option A</label>
        <input type="text" id="option_a" name="option_a" maxlength="255" value="<?php echo htmlspecialchars($qv['option_a']); ?>">
        <label for="option_b">Option B</label>
        <input type="text" id="option_b" name="option_b" maxlength="255" value="<?php echo htmlspecialchars($qv['option_b']); ?>">
        <label for="option_c">Option C</label>
        <input type="text" id="option_c" name="option_c" maxlength="255" value="<?php echo htmlspecialchars($qv['option_c']); ?>">
        <label for="option_d">Option D</label>
        <input type="text" id="option_d" name="option_d" maxlength="255" value="<?php echo htmlspecialchars($qv['option_d']); ?>">
        <label for="correct_index">Correct option</label>
        <select id="correct_index" name="correct_index">
          <?php foreach (['0'=>'A','1'=>'B','2'=>'C','3'=>'D'] as $val => $label): ?>
            <option value="<?php echo $val; ?>" <?php echo (int) $qv['correct_index'] === (int) $val ? 'selected' : ''; ?>><?php echo $label; ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div data-qtype-fields="code" <?php echo $qv['qtype'] !== 'code' ? 'style="display:none;"' : ''; ?>>
        <label for="code_language">Language (e.g. for an IT / programming course)</label>
        <select id="code_language" name="code_language">
          <?php foreach ($CODE_LANGS as $val => $label): ?>
            <option value="<?php echo $val; ?>" <?php echo $qv['code_language'] === $val ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
          <?php endforeach; ?>
        </select>
        <label for="code_starter">Starter code (optional — shown pre-filled in the student's editor)</label>
        <textarea id="code_starter" name="code_starter" rows="5" class="mono-input" placeholder="function solve() {&#10;  // student writes code here&#10;}"><?php echo htmlspecialchars($qv['code_starter']); ?></textarea>

        <label style="display:flex; align-items:center; gap:8px; font-weight:600; margin-top:8px;">
          <input type="checkbox" id="auto_grade_output" name="auto_grade_output" value="1" style="width:auto;" <?php echo $qv['auto_grade_output'] ? 'checked' : ''; ?>>
          Auto-grade by matching output
        </label>
        <p class="admin-sub" id="autoGradeHint" style="margin-top:-4px;">
          We run the student's code and compare what it prints to your sample output below — any correct approach that produces the same output is marked correct, even if their code looks nothing like yours. Not available for HTML/CSS (leave unchecked and grade those by hand).
        </p>
        <div id="expectedOutputWrap" style="<?php echo $qv['auto_grade_output'] ? '' : 'display:none;'; ?>">
          <label for="expected_output">Expected output (exactly what a correct program should print)</label>
          <textarea id="expected_output" name="expected_output" rows="3" class="mono-input" placeholder="1,2,3,4,5"><?php echo htmlspecialchars($qv['expected_output']); ?></textarea>
        </div>

        <label for="max_points_code" style="margin-top:8px;">Points<span id="codePointsHint">&nbsp;(manually graded by you)</span></label>
        <input type="number" id="max_points_code" name="max_points" min="1" max="100" value="<?php echo $qv['qtype'] === 'code' ? (int) $qv['max_points'] : 10; ?>">
      </div>

      <div data-qtype-fields="essay" <?php echo $qv['qtype'] !== 'essay' ? 'style="display:none;"' : ''; ?>>
        <label for="max_points_essay">Points (manually graded by you)</label>
        <input type="number" id="max_points_essay" name="max_points" min="1" max="100" value="<?php echo $qv['qtype'] === 'essay' ? (int) $qv['max_points'] : 10; ?>">
      </div>

      <button class="btn primary" type="submit">Add question</button>
    </form>
    </div>
  </div>

  <section class="admin-card glass-card">
    <?php if (empty($questions)): ?>
      <p class="admin-sub">No questions yet — students can't take this exam until you add at least one.</p>
    <?php else: ?>
      <div class="search-filter">
        <input type="search" class="search-filter-input" data-filter-target="#teacherQuestionList" placeholder="Search questions...">
      </div>
      <div class="question-list" id="teacherQuestionList" data-paginate="10">
        <?php foreach ($questions as $q): ?>
          <div class="question-item" data-filter-row>
            <div class="question-item-top">
              <span class="badge badge-qtype-<?php echo $q['qtype']; ?>">
                <?php echo $q['qtype'] === 'mcq' ? 'Multiple choice' : ($q['qtype'] === 'code' ? 'Code · ' . htmlspecialchars($CODE_LANGS[$q['code_language']] ?? $q['code_language']) . (!empty($q['auto_grade_output']) ? ' · auto-graded' : '') : 'Essay'); ?>
              </span>
              <span class="admin-sub" style="margin:0;"><?php echo (int) $q['max_points']; ?> pt<?php echo $q['max_points'] == 1 ? '' : 's'; ?></span>
              <form method="post" action="/teacher/category.php?id=<?php echo $catId; ?>" onsubmit="return confirm('Delete this question?');" style="margin-left:auto;">
                <input type="hidden" name="action" value="delete_question">
                <input type="hidden" name="question_id" value="<?php echo (int) $q['id']; ?>">
                <button class="icon-btn danger" type="submit" title="Delete">✕</button>
              </form>
            </div>
            <p class="question-item-text"><?php echo nl2br(htmlspecialchars($q['question'])); ?></p>
            <?php if ($q['qtype'] === 'mcq'): ?>
              <ul class="question-opts">
                <?php $opts = [$q['option_a'], $q['option_b'], $q['option_c'], $q['option_d']]; foreach ($opts as $i => $o): ?>
                  <li class="<?php echo $i == $q['correct_index'] ? 'correct' : ''; ?>"><?php echo chr(65 + $i); ?>. <?php echo htmlspecialchars($o); ?><?php echo $i == $q['correct_index'] ? ' ✓' : ''; ?></li>
                <?php endforeach; ?>
              </ul>
            <?php elseif ($q['qtype'] === 'code'): ?>
              <?php if (!empty($q['auto_grade_output'])): ?>
                <p class="admin-sub" style="margin:4px 0;">⚡ Auto-graded — student code is run and marked correct if its output matches:</p>
                <pre class="question-code-preview"><code><?php echo htmlspecialchars($q['expected_output']); ?></code></pre>
              <?php endif; ?>
              <?php if ($q['code_starter']): ?>
                <pre class="question-code-preview"><code><?php echo htmlspecialchars($q['code_starter']); ?></code></pre>
              <?php endif; ?>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>
</main>
<script>
(function () {
  const buttons = document.querySelectorAll('[data-qtype-btn]');
  const panels = document.querySelectorAll('[data-qtype-fields]');
  buttons.forEach((btn) => {
    btn.addEventListener('click', () => {
      const type = btn.getAttribute('data-qtype-btn');
      buttons.forEach((b) => b.classList.toggle('active', b === btn));
      panels.forEach((p) => { p.style.display = p.getAttribute('data-qtype-fields') === type ? '' : 'none'; });
    });
  });

  // Auto-grade-by-output controls: only makes sense for languages Wandbox
  // can actually run (not HTML/CSS, which is browser-preview only).
  const NO_AUTOGRADE_LANGS = ['html'];
  const langSelect = document.getElementById('code_language');
  const autoGradeChk = document.getElementById('auto_grade_output');
  const expectedWrap = document.getElementById('expectedOutputWrap');
  const autoGradeHint = document.getElementById('autoGradeHint');
  const codePointsHint = document.getElementById('codePointsHint');

  function syncAutoGradeAvailability() {
    const supported = langSelect && !NO_AUTOGRADE_LANGS.includes(langSelect.value);
    autoGradeChk.disabled = !supported;
    if (!supported) autoGradeChk.checked = false;
    autoGradeHint.style.opacity = supported ? '1' : '0.6';
  }
  function syncExpectedOutputVisibility() {
    const on = autoGradeChk.checked;
    expectedWrap.style.display = on ? '' : 'none';
    document.getElementById('expected_output').required = on;
    codePointsHint.textContent = on ? ' (auto-graded — full points if output matches)' : ' (manually graded by you)';
  }

  if (langSelect) langSelect.addEventListener('change', syncAutoGradeAvailability);
  if (autoGradeChk) autoGradeChk.addEventListener('change', syncExpectedOutputVisibility);
  syncAutoGradeAvailability();
  syncExpectedOutputVisibility();
})();
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
