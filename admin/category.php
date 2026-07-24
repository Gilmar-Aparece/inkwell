<?php
// Inside/dashboard page — suppress the marketing topbar (see includes/header.php).
$__hideTopbar = true;
require_once __DIR__ . '/../includes/store.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/exams_db.php';
inkwell_require_admin();

$catId = (int) ($_GET['id'] ?? $_POST['category_id'] ?? 0);
$category = inkwell_get_teacher_category($catId);
if (!$category) {
  http_response_code(404);
  die('Exam not found.');
}

$error = '';
$notice = '';
$questionError = '';
$CODE_LANGS = ['javascript' => 'JavaScript', 'python' => 'Python', 'php' => 'PHP', 'java' => 'Java', 'csharp' => 'C#', 'cpp' => 'C++', 'html' => 'HTML/CSS', 'sql' => 'SQL'];

// Sticky values for the "Add question" modal — repopulated from $_POST below
// if validation fails, so the form re-opens with what was typed instead of
// silently closing and losing it.
$qv = [
  'qtype' => 'mcq', 'question' => '',
  'option_a' => '', 'option_b' => '', 'option_c' => '', 'option_d' => '', 'correct_index' => 0,
  'code_language' => 'javascript', 'code_starter' => '', 'max_points' => 10,
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
      $qv['code_language'] = $lang; $qv['code_starter'] = $starter; $qv['max_points'] = $points;
      inkwell_add_teacher_question($catId, [
        'qtype' => 'code', 'question' => $question, 'code_language' => $lang,
        'code_starter' => $starter, 'max_points' => $points,
      ]);
      $notice = 'Code question added.';
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
}

$questions = inkwell_get_teacher_questions($catId);
$pageTitle = 'Manage: ' . $category['title'];
include __DIR__ . '/../includes/header.php';
?>
<main class="admin-main">
  <div class="admin-header-row">
    <div>
      <div class="crumb"><a href="/admin/index.php">Admin dashboard</a> / <a href="/admin/exams.php">All exams</a></div>
      <h1><?php echo htmlspecialchars($category['title']); ?></h1>
      <p class="admin-sub" style="margin-top:2px;">
        <?php if ($category['owner_type'] === 'selfstudy'): ?>
          Self-study exam · language key <code><?php echo htmlspecialchars($category['language_key']); ?></code>
        <?php else: ?>
          <?php echo htmlspecialchars($category['subject_title'] ?? 'No subject'); ?>
          · Created by <?php echo htmlspecialchars($category['teacher_name'] ?? 'Admin'); ?>
        <?php endif; ?>
      </p>
    </div>
    <div style="display:flex; gap:8px;">
      <a class="btn primary" href="/admin/download-exam.php?id=<?php echo (int) $category['id']; ?>">⬇ Download (.docx)</a>
      <a class="btn" href="/admin/exams.php">← Back to all exams</a>
    </div>
  </div>

  <div class="exam-result" style="background: rgba(91,124,250,0.1); border: 1px solid color-mix(in srgb, var(--nib) 35%, var(--border));">
    You're editing this exam as admin. Questions you add here appear immediately for students, the same as if <?php echo htmlspecialchars($category['teacher_name'] ?? 'the admin team'); ?> added them.
  </div>

  <p class="admin-sub" style="margin-top:-8px;">
    <span class="badge badge-purpose-<?php echo $category['purpose']; ?>"><?php echo $category['purpose'] === 'cert' ? 'Certificate exam' : 'Grade-only exam'; ?></span>
  </p>

  <?php if ($notice): ?><div class="exam-result pass"><?php echo htmlspecialchars($notice); ?></div><?php endif; ?>
  <?php if ($error): ?><div class="exam-result fail"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

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
    <form method="post" action="/admin/category.php?id=<?php echo $catId; ?>" class="admin-form" id="questionForm">
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
        <label for="max_points_code">Points (manually graded)</label>
        <input type="number" id="max_points_code" name="max_points" min="1" max="100" value="<?php echo $qv['qtype'] === 'code' ? (int) $qv['max_points'] : 10; ?>">
      </div>

      <div data-qtype-fields="essay" <?php echo $qv['qtype'] !== 'essay' ? 'style="display:none;"' : ''; ?>>
        <label for="max_points_essay">Points (manually graded)</label>
        <input type="number" id="max_points_essay" name="max_points" min="1" max="100" value="<?php echo $qv['qtype'] === 'essay' ? (int) $qv['max_points'] : 10; ?>">
      </div>

      <button class="btn primary" type="submit">Add question</button>
    </form>
    </div>
  </div>

  <section class="admin-card glass-card">
    <?php if (empty($questions)): ?>
      <p class="admin-sub">No questions yet — students can't take this exam until at least one is added.</p>
    <?php else: ?>
      <div class="search-filter">
        <input type="search" class="search-filter-input" data-filter-target="#adminQuestionList" placeholder="Search questions...">
      </div>
      <div class="question-list" id="adminQuestionList">
        <?php foreach ($questions as $q): ?>
          <div class="question-item" data-filter-row>
            <div class="question-item-top">
              <span class="badge badge-qtype-<?php echo $q['qtype']; ?>">
                <?php echo $q['qtype'] === 'mcq' ? 'Multiple choice' : ($q['qtype'] === 'code' ? 'Code · ' . htmlspecialchars($CODE_LANGS[$q['code_language']] ?? $q['code_language']) : 'Essay'); ?>
              </span>
              <span class="admin-sub" style="margin:0;"><?php echo (int) $q['max_points']; ?> pt<?php echo $q['max_points'] == 1 ? '' : 's'; ?></span>
              <form method="post" action="/admin/category.php?id=<?php echo $catId; ?>" onsubmit="return confirm('Delete this question?');" style="margin-left:auto;">
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
            <?php elseif ($q['qtype'] === 'code' && $q['code_starter']): ?>
              <pre class="question-code-preview"><code><?php echo htmlspecialchars($q['code_starter']); ?></code></pre>
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
})();
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
