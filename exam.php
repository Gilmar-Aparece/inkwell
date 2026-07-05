<?php
require_once __DIR__ . '/data/lessons.php';
require_once __DIR__ . '/data/exams.php';
require_once __DIR__ . '/includes/store.php';

$catKey = $_GET['cat'] ?? ($_POST['cat'] ?? '');
$category = inkwell_category($catKey);
$exam = inkwell_exam($catKey);

if (!$category || !$exam) {
  http_response_code(404);
  $pageTitle = 'Exam not found';
  include __DIR__ . '/includes/header.php';
  echo '<main style="padding:60px 20px;"><h1>Exam not found</h1><p><a href="/index.php">Back to all lessons</a></p></main>';
  include __DIR__ . '/includes/footer.php';
  exit;
}

$result = null; // ['score' => int, 'total' => int, 'passed' => bool, 'percent' => int]
$learnerName = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $learnerName = trim($_POST['learner_name'] ?? '');
  $answers = $_POST['answer'] ?? [];
  $total = count($exam['questions']);
  $score = 0;

  foreach ($exam['questions'] as $i => $question) {
    if (isset($answers[$i]) && (int) $answers[$i] === (int) $question['correct']) {
      $score++;
    }
  }

  $percent = $total > 0 ? round(($score / $total) * 100) : 0;
  $passed = $percent >= $exam['passScore'];

  if ($passed) {
    $cert = inkwell_add_certificate($learnerName, $catKey, $category['label'], $score, $total);
    header('Location: /certificate.php?id=' . urlencode($cert['id']));
    exit;
  }

  $result = ['score' => $score, 'total' => $total, 'percent' => $percent, 'passed' => $passed];
}

$pageTitle = $exam['title'];
include __DIR__ . '/includes/header.php';
?>
<main class="exam-main">
  <div class="exam-wrap">
    <div class="crumb"><a href="/index.php">Inkwell</a> / <a href="/index.php#<?php echo htmlspecialchars($catKey); ?>"><?php echo htmlspecialchars($category['label']); ?></a> / Exam</div>
    <h1><?php echo htmlspecialchars($exam['title']); ?></h1>
    <p class="exam-sub">Answer at least <?php echo (int) $exam['passScore']; ?>% correctly to earn your certificate. You can retake this exam as many times as you like.</p>

    <?php if ($result): ?>
      <div class="exam-result fail">
        <span class="exam-result-icon" aria-hidden="true">✕</span>
        <span>
          <strong>Not quite — <?php echo $result['percent']; ?>%</strong>
          You needed <?php echo (int) $exam['passScore']; ?>%. Review the lessons and try again below — your answers have been cleared so you can take another pass.
        </span>
      </div>
    <?php endif; ?>

    <form method="post" action="/exam.php" class="exam-form" id="examForm" novalidate>
      <input type="hidden" name="cat" value="<?php echo htmlspecialchars($catKey); ?>">

      <div class="exam-progress-bar">
        <div class="exam-progress-track"><div class="exam-progress-fill" id="examProgressFill"></div></div>
        <span class="exam-progress-text" id="examProgressText">0 / <?php echo count($exam['questions']); ?> answered</span>
      </div>

      <div class="exam-name-field">
        <label for="learner_name">Name for your certificate</label>
        <input type="text" id="learner_name" name="learner_name" value="<?php echo htmlspecialchars($learnerName); ?>" placeholder="e.g. Juan Dela Cruz" maxlength="80" required>
      </div>

      <?php foreach ($exam['questions'] as $i => $question): ?>
        <fieldset class="exam-question" id="q-<?php echo $i; ?>" data-question>
          <legend><span class="qnum"><?php echo $i + 1; ?></span> <?php echo htmlspecialchars($question['q']); ?></legend>
          <?php foreach ($question['options'] as $optIndex => $option): ?>
            <label class="exam-option">
              <input type="radio" name="answer[<?php echo $i; ?>]" value="<?php echo $optIndex; ?>" required>
              <span class="opt-letter" aria-hidden="true"><?php echo chr(65 + $optIndex); ?></span>
              <span class="opt-text"><?php echo htmlspecialchars($option); ?></span>
            </label>
          <?php endforeach; ?>
        </fieldset>
      <?php endforeach; ?>

      <div class="exam-submit-bar">
        <span class="exam-submit-hint" id="examSubmitHint"><?php echo count($exam['questions']); ?> question<?php echo count($exam['questions']) === 1 ? '' : 's'; ?> to go</span>
        <button class="btn primary" type="submit">Submit exam</button>
      </div>
    </form>
  </div>
</main>

<script>
  (function () {
    const form = document.getElementById('examForm');
    if (!form) return;
    const questions = Array.from(form.querySelectorAll('[data-question]'));
    const fill = document.getElementById('examProgressFill');
    const text = document.getElementById('examProgressText');
    const hint = document.getElementById('examSubmitHint');
    const total = questions.length;

    function answeredCount() {
      return questions.filter((q) => q.querySelector('input[type=radio]:checked')).length;
    }

    function updateProgress() {
      const done = answeredCount();
      const pct = total ? Math.round((done / total) * 100) : 0;
      fill.style.width = pct + '%';
      text.textContent = done + ' / ' + total + ' answered';
      hint.textContent = done >= total ? 'All set — ready to submit' : (total - done) + ' question' + (total - done === 1 ? '' : 's') + ' to go';
      questions.forEach((q) => {
        if (q.querySelector('input[type=radio]:checked')) q.classList.remove('needs-answer');
      });
    }

    form.addEventListener('change', updateProgress);
    updateProgress();

    form.addEventListener('submit', function (e) {
      const nameField = document.getElementById('learner_name');
      let firstBad = null;
      questions.forEach((q) => {
        const answered = q.querySelector('input[type=radio]:checked');
        q.classList.toggle('needs-answer', !answered);
        if (!answered && !firstBad) firstBad = q;
      });
      if (!nameField.value.trim() || firstBad) {
        e.preventDefault();
        const target = !nameField.value.trim() ? nameField : firstBad;
        target.scrollIntoView({ behavior: 'smooth', block: 'center' });
        if (target === nameField) nameField.focus();
      }
    });
  })();
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>
