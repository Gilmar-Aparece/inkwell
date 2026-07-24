<?php
// Inside/dashboard page — suppress the marketing topbar (see includes/header.php).
$__hideTopbar = true;
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/exams_db.php';
require_once __DIR__ . '/../includes/students.php';
require_once __DIR__ . '/../includes/events.php';

$user = inkwell_require_role('teacher');
if ($user['status'] !== 'active') {
  header('Location: /teacher/dashboard.php');
  exit;
}

$attemptId = (int) ($_GET['attempt'] ?? 0);
$notice = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $postAttemptId = (int) ($_POST['attempt_id'] ?? 0);
  $attempt = inkwell_get_attempt($postAttemptId);
  if (!$attempt || (int) $attempt['teacher_id'] !== (int) $user['id']) {
    $error = 'That attempt could not be found.';
  } else {
    $points = array_map('intval', $_POST['points'] ?? []);
    $feedback = array_map('strval', $_POST['feedback'] ?? []);
    $result = inkwell_grade_attempt($postAttemptId, $points, $feedback);
    $notice = $result['passed']
      ? 'Graded — ' . $result['percent'] . '% — student passed and their certificate has been issued.'
      : 'Graded — ' . $result['percent'] . '% — below the pass score, no certificate issued.';
    $attemptId = 0;
  }
}

$attempt = $attemptId ? inkwell_get_attempt($attemptId) : null;
if ($attempt && (int) $attempt['teacher_id'] !== (int) $user['id']) $attempt = null;
$answers = $attempt ? inkwell_attempt_answers($attemptId) : [];

$pending = inkwell_teacher_pending_attempts($user['id']);
$recentlyGraded = inkwell_teacher_graded_attempts($user['id'], 15);

$dashNavTitle = 'Teacher';
$dashNavActive = 'grade';
$dashNavItems = [
  ['key' => 'dashboard', 'group' => 'General', 'href' => '/teacher/overview.php', 'label' => 'Dashboard', 'icon' => '🏠'],
  ['key' => 'subjects', 'group' => 'Teaching', 'href' => '/teacher/dashboard.php', 'label' => 'Subjects', 'icon' => '🗂'],
  ['key' => 'sections', 'group' => 'Teaching', 'href' => '/teacher/sections.php', 'label' => 'Sections', 'icon' => '👥'],
  ['key' => 'grade', 'group' => 'Teaching', 'href' => '/teacher/grade.php', 'label' => 'Grade', 'icon' => '🖊', 'count' => count($pending)],
  ['key' => 'students', 'group' => 'People', 'href' => '/teacher/students.php', 'label' => 'Students', 'icon' => '🎓', 'count' => count(inkwell_teacher_students($user['id']))],
  ['key' => 'certificates', 'group' => 'People', 'href' => '/teacher/certificates.php', 'label' => 'Certificates', 'icon' => '📜'],
  ['key' => 'events', 'group' => 'Activity', 'href' => '/teacher/events.php', 'label' => 'Events', 'icon' => '📣', 'count' => count(inkwell_events_by_author($user['id']))],
];

$pageTitle = $attempt ? 'Grade attempt' : 'Grade attempts';
include __DIR__ . '/../includes/header.php';
?>
<div class="dash-shell">
  <?php include __DIR__ . '/../includes/dash_nav.php'; ?>
  <main class="admin-main">
  <div class="admin-header-row">
    <h1><?php echo $attempt ? 'Grading: ' . htmlspecialchars($attempt['exam_title']) : 'Grade attempts'; ?></h1>
    <a class="btn" href="/teacher/dashboard.php">← Back to dashboard</a>
  </div>

  <?php if ($notice): ?><div class="exam-result pass"><?php echo htmlspecialchars($notice); ?></div><?php endif; ?>
  <?php if ($error): ?><div class="exam-result fail"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

  <?php if ($attempt): ?>
    <section class="admin-card glass-card">
      <p class="admin-sub">Student: <strong><?php echo htmlspecialchars($attempt['student_name']); ?></strong> · Submitted <?php echo htmlspecialchars(date('M j, Y g:i A', strtotime($attempt['submitted_at']))); ?> · Pass score <?php echo (int) $attempt['pass_score']; ?>%</p>

      <form method="post" action="/teacher/grade.php" class="admin-form">
        <input type="hidden" name="attempt_id" value="<?php echo (int) $attempt['id']; ?>">
        <?php foreach ($answers as $ans): ?>
          <?php $isAutoCode = $ans['qtype'] === 'code' && !empty($ans['autograded']); ?>
          <div class="grade-item">
            <div class="question-item-top">
              <span class="badge badge-qtype-<?php echo $ans['qtype']; ?>"><?php echo $ans['qtype'] === 'mcq' ? 'Multiple choice · auto-graded' : ($ans['qtype'] === 'code' ? 'Code · ' . htmlspecialchars($ans['code_language'] ?: '') . ($isAutoCode ? ' · auto-graded' : '') : 'Essay'); ?></span>
            </div>
            <p class="question-item-text"><?php echo nl2br(htmlspecialchars($ans['question'])); ?></p>

            <?php if ($ans['qtype'] === 'mcq'): ?>
              <p class="admin-sub"><?php echo $ans['is_correct'] ? '✓ Answered correctly' : '✕ Answered incorrectly'; ?> — auto-scored <?php echo (int) $ans['points_awarded']; ?>/<?php echo (int) $ans['max_points']; ?> pts.</p>
            <?php elseif ($isAutoCode): ?>
              <pre class="grade-answer-text"><code><?php echo htmlspecialchars($ans['text_answer'] ?: '(no answer submitted)'); ?></code></pre>
              <p class="admin-sub" style="margin:6px 0 2px;"><strong>Expected output:</strong></p>
              <pre class="grade-answer-text"><code><?php echo htmlspecialchars($ans['expected_output'] ?: ''); ?></code></pre>
              <p class="admin-sub" style="margin:6px 0 2px;"><strong>Student's actual output:</strong></p>
              <pre class="grade-answer-text"><code><?php echo htmlspecialchars($ans['run_output'] ?: '(no output)'); ?></code></pre>
              <p class="admin-sub"><?php echo $ans['is_correct'] ? '✓ Output matched' : '✕ Output did not match'; ?> — auto-scored <?php echo (int) $ans['points_awarded']; ?>/<?php echo (int) $ans['max_points']; ?> pts.</p>
            <?php else: ?>
              <pre class="grade-answer-text"><code><?php echo htmlspecialchars($ans['text_answer'] ?: '(no answer submitted)'); ?></code></pre>
              <?php if ($ans['qtype'] === 'code' && !empty($ans['run_output'])): ?>
                <p class="admin-sub" style="margin:6px 0 2px;"><strong>Auto-run service was unavailable at submission — grade by hand.</strong> Last error: <?php echo htmlspecialchars($ans['run_output']); ?></p>
              <?php endif; ?>
              <div class="grade-controls">
                <label>Points (0–<?php echo (int) $ans['max_points']; ?>)</label>
                <input type="number" name="points[<?php echo (int) $ans['id']; ?>]" min="0" max="<?php echo (int) $ans['max_points']; ?>" value="0" required>
                <label>Feedback (optional)</label>
                <input type="text" name="feedback[<?php echo (int) $ans['id']; ?>]" maxlength="500" placeholder="Notes for the student">
              </div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
        <button class="btn primary" type="submit">Submit grades &amp; finalize</button>
      </form>
    </section>
  <?php else: ?>
    <section class="admin-card glass-card">
      <h2>Awaiting grading (<?php echo count($pending); ?>)</h2>
      <?php if (empty($pending)): ?>
        <p class="admin-sub">Nothing to grade right now.</p>
      <?php else: ?>
        <div class="search-filter">
          <input type="search" class="search-filter-input" data-filter-target="#pendingGradeTable" placeholder="Search by student or exam...">
          <div class="search-filter-buttons">
            <button type="button" class="search-filter-btn active" data-filter-when="all">All</button>
            <button type="button" class="search-filter-btn" data-filter-when="now">Now</button>
            <button type="button" class="search-filter-btn" data-filter-when="before">Before</button>
          </div>
        </div>
        <div class="admin-table-wrap">
          <table class="admin-table" id="pendingGradeTable" data-paginate="10">
            <thead><tr><th>Student</th><th>Exam</th><th>Submitted</th><th></th></tr></thead>
            <tbody>
              <?php foreach ($pending as $p): ?>
                <tr data-filter-row data-filter-date="<?php echo htmlspecialchars(date('Y-m-d', strtotime($p['submitted_at']))); ?>">
                  <td><?php echo htmlspecialchars($p['student_name']); ?></td>
                  <td><?php echo htmlspecialchars($p['exam_title']); ?></td>
                  <td><?php echo htmlspecialchars(date('M j, Y g:i A', strtotime($p['submitted_at']))); ?></td>
                  <td><a class="btn primary" href="/teacher/grade.php?attempt=<?php echo (int) $p['id']; ?>">Grade →</a></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </section>

    <section class="admin-card glass-card">
      <h2>Recently graded</h2>
      <?php if (empty($recentlyGraded)): ?>
        <p class="admin-sub">Nothing graded yet.</p>
      <?php else: ?>
        <div class="search-filter">
          <input type="search" class="search-filter-input" data-filter-target="#gradedTable" placeholder="Search by student or exam...">
          <div class="search-filter-buttons">
            <button type="button" class="search-filter-btn active" data-filter-when="all">All</button>
            <button type="button" class="search-filter-btn" data-filter-when="now">Now</button>
            <button type="button" class="search-filter-btn" data-filter-when="before">Before</button>
          </div>
        </div>
        <div class="admin-table-wrap">
          <table class="admin-table" id="gradedTable" data-paginate="10">
            <thead><tr><th>Student</th><th>Exam</th><th>Result</th><th>Graded</th><th></th></tr></thead>
            <tbody>
              <?php foreach ($recentlyGraded as $g): ?>
                <tr data-filter-row data-filter-date="<?php echo htmlspecialchars(date('Y-m-d', strtotime($g['graded_at']))); ?>">
                  <td><?php echo htmlspecialchars($g['student_name']); ?></td>
                  <td><?php echo htmlspecialchars($g['exam_title']); ?></td>
                  <td><?php echo $g['passed'] ? '✅' : '❌'; ?> <?php echo (int) $g['percent']; ?>%</td>
                  <td><?php echo htmlspecialchars(date('M j, Y', strtotime($g['graded_at']))); ?></td>
                  <td><a href="/results.php?attempt=<?php echo (int) $g['id']; ?>">View / download →</a></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </section>
  <?php endif; ?>
  </main>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
