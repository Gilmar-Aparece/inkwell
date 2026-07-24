<?php
// Inside/dashboard page — suppress the marketing topbar (see includes/header.php).
$__hideTopbar = true;
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/exams_db.php';
require_once __DIR__ . '/includes/exam_docx.php';

$user = inkwell_require_login();

$attemptId = (int) ($_GET['attempt'] ?? 0);
$attempt = $attemptId ? inkwell_get_attempt($attemptId) : null;

function inkwell_results_error($title, $message, $code = 404) {
  http_response_code($code);
  $pageTitle = $title;
  include __DIR__ . '/includes/header.php';
  echo '<main style="padding:60px 20px;"><h1>' . htmlspecialchars($title) . '</h1><p>' . $message . '</p></main>';
  include __DIR__ . '/includes/footer.php';
  exit;
}

if (!$attempt) {
  inkwell_results_error('Result not found', 'That exam attempt does not exist. <a href="/account.php">Back to my account</a>');
}

$isOwner = (int) $attempt['student_id'] === (int) $user['id'];
$isGraderTeacher = $user['role'] === 'teacher' && !empty($attempt['teacher_id']) && (int) $attempt['teacher_id'] === (int) $user['id'];

if (!$isOwner && !$isGraderTeacher) {
  inkwell_results_error('Not allowed', 'You can only view results for your own exam attempts. <a href="/account.php">Back to my account</a>', 403);
}

$answers = inkwell_attempt_answers($attemptId);

// -------- Download as Word (.docx) --------
if (isset($_GET['download'])) {
  inkwell_stream_attempt_result_docx($attempt, $answers);
}

$CODE_LANGS = ['javascript' => 'JavaScript', 'python' => 'Python', 'php' => 'PHP', 'java' => 'Java', 'csharp' => 'C#', 'cpp' => 'C++', 'html' => 'HTML/CSS', 'sql' => 'SQL'];
$isGraded = $attempt['status'] === 'graded';
$finalPoints = (int) $attempt['auto_points'] + (int) $attempt['manual_points'];

$pageTitle = 'Results — ' . $attempt['exam_title'];
include __DIR__ . '/includes/header.php';
$driveActive = 'account';
$driveCrumbs = [['label' => 'Home', 'href' => '/index.php'], ['label' => 'My account', 'href' => '/account.php'], ['label' => 'Results']];
$driveTitle = $attempt['exam_title'];
include __DIR__ . '/includes/drive_shell_top.php';
?>
  <div class="admin-header-row" style="margin-bottom:18px;">
    <span></span>
    <div style="display:flex; gap:8px;">
      <?php if ($isGraded): ?>
        <a class="btn primary" href="/results.php?attempt=<?php echo (int) $attempt['id']; ?>&download=1">⬇ Download as Word (.docx)</a>
      <?php endif; ?>
      <a class="btn" href="/account.php">← Back to my account</a>
    </div>
  </div>

  <section class="admin-card glass-card">
    <p class="admin-sub">
      Student: <strong><?php echo htmlspecialchars($attempt['student_name']); ?></strong>
      <?php if (!empty($attempt['teacher_id'])): ?> · Teacher: <strong><?php echo htmlspecialchars($attempt['teacher_name'] ?? ('#' . $attempt['teacher_id'])); ?></strong><?php endif; ?>
      · Submitted <?php echo htmlspecialchars(date('M j, Y g:i A', strtotime($attempt['submitted_at']))); ?>
      <?php if ($isGraded): ?> · Graded <?php echo htmlspecialchars(date('M j, Y g:i A', strtotime($attempt['graded_at']))); ?><?php endif; ?>
    </p>

    <?php if ($isGraded): ?>
      <div class="exam-result <?php echo $attempt['passed'] ? 'pass' : 'fail'; ?>">
        <span class="exam-result-icon" aria-hidden="true"><?php echo $attempt['passed'] ? '✓' : '✕'; ?></span>
        <span>
          <strong><?php echo $finalPoints; ?> / <?php echo (int) $attempt['total_points']; ?> points — <?php echo (int) $attempt['percent']; ?>%</strong>
          <?php echo $attempt['passed'] ? 'Passed' : 'Not passed'; ?> — pass score required was <?php echo (int) $attempt['pass_score']; ?>%.
          <?php if ($attempt['passed'] && $attempt['certificate_id']): ?>
            <a href="/certificate.php?id=<?php echo htmlspecialchars($attempt['certificate_id']); ?>">View certificate →</a>
          <?php endif; ?>
        </span>
      </div>
    <?php else: ?>
      <div class="exam-result pending">
        <span class="exam-result-icon" aria-hidden="true">🕐</span>
        <span><strong>Awaiting grading</strong> This exam has questions still being graded by hand. The final score will appear here once grading is finished.</span>
      </div>
    <?php endif; ?>
  </section>

  <section class="admin-card glass-card">
    <h2>Question by question</h2>
    <p class="admin-sub">Your answer next to the correct answer, with the points earned per question<?php echo $isGraded ? '' : ' (mcq questions are already scored; code/essay scores appear once graded)'; ?>.</p>

    <?php foreach ($answers as $i => $ans): ?>
      <div class="grade-item">
        <div class="question-item-top">
          <?php $isAutoCode = $ans['qtype'] === 'code' && !empty($ans['autograded']); ?>
          <span class="badge badge-qtype-<?php echo $ans['qtype']; ?>"><?php echo $ans['qtype'] === 'mcq' ? 'Multiple choice' : ($ans['qtype'] === 'code' ? 'Code · ' . htmlspecialchars($CODE_LANGS[$ans['code_language']] ?? $ans['code_language']) . ($isAutoCode ? ' · auto-graded' : '') : 'Essay'); ?></span>
          <?php if ($ans['qtype'] === 'mcq' || $isAutoCode): ?>
            <span class="badge <?php echo $ans['is_correct'] ? 'badge-status-active' : 'badge-status-disabled'; ?>"><?php echo $ans['is_correct'] ? '✓ Correct' : '✕ Incorrect'; ?></span>
          <?php elseif ($ans['points_awarded'] !== null): ?>
            <span class="badge badge-status-active"><?php echo (int) $ans['points_awarded']; ?>/<?php echo (int) $ans['max_points']; ?> pts</span>
          <?php else: ?>
            <span class="badge badge-status-pending">Not graded yet</span>
          <?php endif; ?>
        </div>
        <p class="question-item-text"><strong>Q<?php echo $i + 1; ?>.</strong> <?php echo nl2br(htmlspecialchars($ans['question'])); ?></p>

        <?php if ($ans['qtype'] === 'mcq'): ?>
          <?php $opts = [$ans['option_a'], $ans['option_b'], $ans['option_c'], $ans['option_d']]; ?>
          <div class="exam-form" style="gap:6px;">
            <?php foreach ($opts as $oi => $opt): ?>
              <?php if ($opt === null || $opt === '') continue; ?>
              <?php
                $isCorrectOpt = (int) $ans['correct_index'] === $oi;
                $isPicked = $ans['selected_index'] !== null && (int) $ans['selected_index'] === $oi;
                $style = 'padding:8px 12px; border-radius:8px; border:1px solid var(--border-soft);';
                if ($isCorrectOpt) $style .= ' background: var(--pine-dim); border-color: var(--pine);';
                elseif ($isPicked) $style .= ' background: color-mix(in srgb, var(--danger) 12%, transparent); border-color: var(--danger);';
              ?>
              <div style="<?php echo $style; ?>">
                <?php echo chr(65 + $oi); ?>) <?php echo htmlspecialchars($opt); ?>
                <?php if ($isCorrectOpt): ?><strong style="color:var(--pine);"> — correct answer</strong><?php endif; ?>
                <?php if ($isPicked && !$isCorrectOpt): ?><strong style="color:var(--danger);"> — your answer</strong><?php elseif ($isPicked): ?><em> (your answer)</em><?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
        <?php elseif ($isAutoCode): ?>
          <pre class="grade-answer-text"><code><?php echo htmlspecialchars($ans['text_answer'] ?: '(no answer submitted)'); ?></code></pre>
          <p class="admin-sub" style="margin:6px 0 2px;"><strong>Expected output:</strong></p>
          <pre class="grade-answer-text"><code><?php echo htmlspecialchars($ans['expected_output'] ?: ''); ?></code></pre>
          <p class="admin-sub" style="margin:6px 0 2px;"><strong>Your code's actual output:</strong></p>
          <pre class="grade-answer-text"><code><?php echo htmlspecialchars($ans['run_output'] ?: '(no output)'); ?></code></pre>
        <?php else: ?>
          <pre class="grade-answer-text"><code><?php echo htmlspecialchars($ans['text_answer'] ?: '(no answer submitted)'); ?></code></pre>
          <?php if (!empty($ans['feedback'])): ?>
            <p class="admin-sub"><strong>Teacher feedback / correction:</strong> <?php echo nl2br(htmlspecialchars($ans['feedback'])); ?></p>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </section>
<?php include __DIR__ . '/includes/drive_shell_bottom.php'; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>
