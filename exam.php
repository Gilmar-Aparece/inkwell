<?php
// Inside/dashboard page — suppress the marketing topbar (see includes/header.php).
$__hideTopbar = true;
require_once __DIR__ . '/includes/store.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/exams_db.php';
require_once __DIR__ . '/includes/billing.php';

$student = inkwell_require_login(); // certificates are tied to an account now

$teacherCatId = (int) ($_GET['teacher_cat'] ?? $_POST['teacher_cat'] ?? 0);
$catKey = $_GET['cat'] ?? ($_POST['cat'] ?? '');

function inkwell_exam_error_page($title, $message) {
  http_response_code(404);
  $pageTitle = $title;
  include __DIR__ . '/includes/header.php';
  echo '<main style="padding:60px 20px;"><h1>' . htmlspecialchars($title) . '</h1><p>' . $message . '</p></main>';
  include __DIR__ . '/includes/footer.php';
  exit;
}

// Self-study (per-language) exams are DB-backed (owner_type = 'selfstudy') so
// admin can customize/add them, same as any other exam. Resolve ?cat=xxx to
// its underlying exam_categories row and fall through to the same code path
// used for teacher/admin exams — just without an enrollment requirement.
if ($catKey !== '' && $teacherCatId <= 0) {
  $selfCat = inkwell_get_selfstudy_category_by_key($catKey);
  if ($selfCat) $teacherCatId = (int) $selfCat['id'];
}

$isTeacherExam = $teacherCatId > 0;

if ($isTeacherExam) {
  $teacherCategory = inkwell_get_teacher_category($teacherCatId);
  $ownerType = $teacherCategory['owner_type'] ?? null;
  if (!$teacherCategory || ($ownerType === 'teacher' && $teacherCategory['teacher_status'] !== 'active')) {
    inkwell_exam_error_page('Exam not found', '<a href="/exams.php">Back to exams</a>');
  }
  // Admin-authored and self-study exams have no class to join — open to any student.
  $bypassEnrollment = in_array($ownerType, ['admin', 'selfstudy'], true);
  $isAdminExam = $ownerType === 'admin';
  if ($student['role'] !== 'student') {
    inkwell_exam_error_page('Students only', 'Only student accounts can take exams. <a href="/exams.php">Back to exams</a>');
  }
  if (!$bypassEnrollment && !inkwell_is_enrolled($student['id'], (int) $teacherCategory['subject_id'])) {
    inkwell_exam_error_page('Join this class first', 'You need to join "' . htmlspecialchars($teacherCategory['subject_title'] ?? '') . '" before you can take this exam. <a href="/exams.php">Browse &amp; join classes →</a>');
  }
  $questionRows = inkwell_get_teacher_questions($teacherCatId);
  if (empty($questionRows)) {
    inkwell_exam_error_page('Exam not ready', 'This exam has no questions yet. <a href="/exams.php">Back to exams</a>');
  }

  // Enforce "1 attempt per student" exams — blocks both viewing the form
  // again and re-submitting once the student already has an attempt on file.
  $maxAttempts = (int) ($teacherCategory['max_attempts'] ?? 0);
  if ($maxAttempts === 1 && inkwell_student_attempt_count($student['id'], $teacherCatId) >= 1) {
    $latestAttempt = inkwell_student_latest_attempt($student['id'], $teacherCatId);
    $resultLink = $latestAttempt ? ('/results.php?attempt=' . (int) $latestAttempt['id']) : '/account.php';
    inkwell_exam_error_page('Already taken', 'This exam only allows one attempt, and you\'ve already taken it. <a href="' . htmlspecialchars($resultLink) . '">View your result →</a>');
  }

  // Teacher-controlled availability: a manual on/off switch, plus an
  // optional open/close time window. Once "until" passes, the exam reads
  // as closed automatically on every request — no cron needed — until the
  // teacher re-opens or edits the schedule from their exam page.
  $schedule = inkwell_exam_schedule_status($teacherCategory);
  if (!$schedule['open']) {
    if ($schedule['reason'] === 'not_yet') {
      inkwell_exam_error_page('Not open yet', 'This exam opens on ' . htmlspecialchars(date('M j, Y g:i A', strtotime($schedule['at']))) . '. <a href="/exams.php">Back to exams</a>');
    } elseif ($schedule['reason'] === 'ended') {
      inkwell_exam_error_page('Exam closed', 'This exam\'s window closed on ' . htmlspecialchars(date('M j, Y g:i A', strtotime($schedule['at']))) . ' and was automatically closed. <a href="/exams.php">Back to exams</a>');
    } else {
      inkwell_exam_error_page('Exam closed', 'This exam is currently closed by the teacher. <a href="/exams.php">Back to exams</a>');
    }
  }

  if ($ownerType === 'selfstudy') {
    $label = $teacherCategory['title'] . ' (self-study)';
  } elseif ($isAdminExam) {
    $label = $teacherCategory['title'] . ' (official certification)';
  } else {
    $label = $teacherCategory['title'] . ' (with ' . $teacherCategory['teacher_name'] . ')';
  }

  // Free tier gets lessons + community notes only. Any exam that issues a
  // certificate (purpose = 'cert', regardless of owner type) requires an
  // active plan that unlocks exams. 'grade'-purpose exams (no certificate)
  // stay free, since a teacher is just using them to score students.
  if ($teacherCategory['purpose'] === 'cert' && !inkwell_user_has_exam_access($student)) {
    $lockedMessage = ($student['plan_status'] ?? 'none') === 'expired'
      ? 'Your plan has expired, so certification exams are locked. <a href="/my-billing.php">Renew your plan →</a>'
      : 'Certification exams and certificates are part of a paid plan. <a href="/my-billing.php">View plans →</a>';
    inkwell_exam_error_page('Upgrade to unlock this exam', $lockedMessage);
  }
} else {
  inkwell_exam_error_page('Exam not found', '<a href="/index.php">Back to all lessons</a>');
}

$result = null;      // ['score','total','percent','passed']
$pendingSubmitted = false;
$autoReason = '';    // 'time_up' or 'tab_switch' when JS force-submitted this attempt

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $answers = []; // question_id => value
  foreach ($questionRows as $q) {
    if ($q['qtype'] === 'mcq') {
      if (isset($_POST['answer'][$q['id']])) $answers[(int) $q['id']] = (int) $_POST['answer'][$q['id']];
    } else {
      $answers[(int) $q['id']] = trim($_POST['answer'][$q['id']] ?? '');
    }
  }
  $attempt = inkwell_submit_attempt($student['id'], $teacherCatId, $questionRows, $answers);
  $autoReason = in_array($_POST['auto_reason'] ?? '', ['time_up', 'tab_switch'], true) ? $_POST['auto_reason'] : '';

  // This attempt is finished one way or another — clear the countdown so a
  // fresh timer starts if/when the student begins another attempt.
  unset($_SESSION['inkwell_exam_start_' . $teacherCatId]);

  if ($attempt['auto_complete']) {
    $percent = (int) $attempt['percent'];
    $passed = $percent >= (int) $teacherCategory['pass_score'];
    if ($passed && $teacherCategory['purpose'] === 'cert') {
      $certType = $ownerType === 'selfstudy' ? 'selfstudy' : 'teacher';
      $certKey = $ownerType === 'selfstudy' ? $teacherCategory['language_key'] : null;
      $cert = inkwell_db_add_certificate(
        $student['id'], $student['name'], $certType, $certKey, $teacherCatId,
        $teacherCategory['title'], $teacherCategory['teacher_id'], $teacherCategory['teacher_name'],
        (int) $attempt['auto_points'], (int) $attempt['total_points']
      );
      header('Location: /certificate.php?id=' . urlencode($cert['id']));
      exit;
    }
    // 'grade' purpose exams never issue a certificate — just a pass/fail score
    // the teacher can use as a grade, shown on the student's account page.
    $result = ['score' => (int) $attempt['auto_points'], 'total' => (int) $attempt['total_points'], 'percent' => $percent, 'passed' => $passed];
  } else {
    $pendingSubmitted = true;
  }
}

$pageTitle = $teacherCategory['title'];
include __DIR__ . '/includes/header.php';
$driveActive = 'exams';
$driveCrumbs = [['label' => 'Home', 'href' => '/index.php'], ['label' => 'Exams', 'href' => '/exams.php'], ['label' => $label]];
$driveHideCta = true;
include __DIR__ . '/includes/drive_shell_top.php';

$CODE_LANGS = ['javascript' => 'JavaScript', 'python' => 'Python', 'php' => 'PHP', 'java' => 'Java', 'csharp' => 'C#', 'cpp' => 'C++', 'html' => 'HTML/CSS', 'sql' => 'SQL'];
$questionCount = count($questionRows);
$passScoreDisplay = (int) $teacherCategory['pass_score'];

// Countdown timer: kept in the session so a page refresh mid-attempt doesn't
// hand the student extra time, but a brand new attempt (this page rendering
// the form fresh after a POST retry, or a first-ever GET visit) always
// starts the clock over.
$timeLimitMinutes = (int) ($teacherCategory['time_limit_minutes'] ?? 0);
$hasTimeLimit = $timeLimitMinutes > 0;
$examExpiredOnLoad = false;
$examRemainingSeconds = null;
if ($hasTimeLimit) {
  $examStartKey = 'inkwell_exam_start_' . $teacherCatId;
  if ($_SERVER['REQUEST_METHOD'] === 'POST' || empty($_SESSION[$examStartKey])) {
    $_SESSION[$examStartKey] = time();
  }
  $examDeadline = (int) $_SESSION[$examStartKey] + ($timeLimitMinutes * 60);
  $examRemainingSeconds = max(0, $examDeadline - time());
  if ($examRemainingSeconds <= 0 && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $examExpiredOnLoad = true;
  }
}
?>
    <h1 class="drive-title"><?php echo htmlspecialchars($pageTitle); ?></h1>

    <?php if ($pendingSubmitted): ?>
      <div class="exam-result pending">
        <span class="exam-result-icon" aria-hidden="true">🕐</span>
        <span>
          <strong>Submitted — awaiting grading</strong>
          <?php if ($autoReason === 'time_up'): ?>Time ran out, so your exam was submitted automatically with whatever you had answered. <?php elseif ($autoReason === 'tab_switch'): ?>Your exam was submitted automatically because you left this tab. <?php endif; ?>
          This exam includes questions <?php echo $teacherCategory['teacher_name'] ? 'your teacher' : 'the admin team'; ?> grades by hand. You'll see the result on your <a href="/account.php">account page</a> once <?php echo htmlspecialchars($teacherCategory['teacher_name'] ?? 'grading is complete'); ?><?php echo $teacherCategory['teacher_name'] ? ' finishes grading' : ''; ?>, and your certificate (if you pass) will appear there too.
        </span>
      </div>
    <?php else: ?>
      <p class="exam-sub">
        <?php if ($teacherCategory['purpose'] === 'grade'): ?>
          Answer at least <?php echo $passScoreDisplay; ?>% correctly to pass. This exam is for grading — <?php echo htmlspecialchars($teacherCategory['teacher_name'] ?? 'the admin team'); ?> will see your score, but no certificate is issued.
        <?php else: ?>
          Answer at least <?php echo $passScoreDisplay; ?>% correctly to earn your certificate, issued to <strong><?php echo htmlspecialchars($student['name']); ?></strong>.
          <?php echo ($ownerType === 'teacher') ? 'Taking this under ' . htmlspecialchars($teacherCategory['teacher_name']) . '.' : 'Taking this as self-study.'; ?>
        <?php endif; ?>
        <?php if (!inkwell_exam_is_auto_gradable($teacherCatId)): ?>
          This exam has code or essay questions, so your teacher grades those manually before <?php echo $teacherCategory['purpose'] === 'grade' ? 'your grade is finalized.' : 'your certificate is issued.'; ?>
        <?php elseif ($maxAttempts === 1): ?>
          You only get one attempt at this exam — make it count.
        <?php else: ?>
          You can retake this exam as many times as you like.
        <?php endif; ?>
      </p>

      <?php if ($autoReason === 'time_up'): ?>
        <div class="exam-result fail">
          <span class="exam-result-icon" aria-hidden="true">⏱</span>
          <span><strong>Time ran out</strong> — your exam was submitted automatically with whatever you had answered so far.</span>
        </div>
      <?php elseif ($autoReason === 'tab_switch'): ?>
        <div class="exam-result fail">
          <span class="exam-result-icon" aria-hidden="true">⚠️</span>
          <span><strong>Submitted automatically</strong> — you left this tab while taking the exam.</span>
        </div>
      <?php endif; ?>

      <?php if ($result && $result['passed']): ?>
        <div class="exam-result pass">
          <span class="exam-result-icon" aria-hidden="true">✓</span>
          <span>
            <strong>Graded — <?php echo $result['percent']; ?>%</strong>
            This exam is for grading, not certification, so no certificate is issued — your score has been recorded for your teacher.
          </span>
        </div>
      <?php elseif ($result): ?>
        <div class="exam-result fail">
          <span class="exam-result-icon" aria-hidden="true">✕</span>
          <span>
            <strong>Not quite — <?php echo $result['percent']; ?>%</strong>
            You needed <?php echo $passScoreDisplay; ?>%. Review the lessons and try again below — your answers have been cleared so you can take another pass.
          </span>
        </div>
      <?php endif; ?>

      <?php $singleAttemptUsedUp = ($maxAttempts === 1 && $result !== null); ?>
      <?php if ($singleAttemptUsedUp): ?>
        <p class="admin-sub" style="margin-top:20px;">This exam only allows one attempt, so it's now closed for you. <a href="/account.php">View your results on your account page →</a></p>
      <?php else: ?>
      <div class="exam-result" style="border-color:#f0ad4e; background:rgba(240,173,78,0.08);">
        <span class="exam-result-icon" aria-hidden="true">⚠️</span>
        <span>
          <strong>Stay on this tab.</strong>
          Switching to another tab or window will submit this exam automatically, right away.
          <?php if ($hasTimeLimit): ?> You also have <strong><?php echo (int) ceil($examRemainingSeconds / 60); ?> minute<?php echo (int) ceil($examRemainingSeconds / 60) === 1 ? '' : 's'; ?></strong> to finish once you start — the timer is already running below.<?php endif; ?>
        </span>
      </div>

      <form method="post" action="/exam.php" class="exam-form" id="examForm" novalidate data-time-remaining="<?php echo $hasTimeLimit ? (int) $examRemainingSeconds : ''; ?>" data-expired="<?php echo $examExpiredOnLoad ? '1' : '0'; ?>">
        <input type="hidden" name="teacher_cat" value="<?php echo (int) $teacherCatId; ?>">
        <input type="hidden" name="auto_reason" id="examAutoReason" value="">

        <?php if ($hasTimeLimit): ?>
          <div class="exam-timer-bar" id="examTimerBar" style="display:flex; align-items:center; gap:8px; margin-bottom:14px; font-weight:700;">
            <span aria-hidden="true">⏱</span>
            <span id="examTimerText">--:--</span>
          </div>
        <?php endif; ?>

        <div class="exam-progress-bar">
          <div class="exam-progress-track"><div class="exam-progress-fill" id="examProgressFill"></div></div>
          <span class="exam-progress-text" id="examProgressText">0 / <?php echo $questionCount; ?> answered</span>
        </div>

        <?php foreach ($questionRows as $i => $q): ?>
          <fieldset class="exam-question" id="q-<?php echo $i; ?>" data-question data-qtype="<?php echo $q['qtype']; ?>">
            <legend>
              <span class="qnum"><?php echo $i + 1; ?></span>
              <span class="qtext"><?php echo nl2br(htmlspecialchars($q['question'])); ?></span>
              <?php if ($q['qtype'] !== 'mcq'): ?><span class="badge badge-qtype-<?php echo $q['qtype']; ?>" style="margin-left:8px;"><?php echo $q['qtype'] === 'code' ? htmlspecialchars($CODE_LANGS[$q['code_language']] ?? $q['code_language']) : 'Essay'; ?> · <?php echo (int) $q['max_points']; ?> pts</span><?php endif; ?>
            </legend>

            <?php if ($q['qtype'] === 'mcq'): ?>
              <?php $opts = [$q['option_a'], $q['option_b'], $q['option_c'], $q['option_d']]; ?>
              <?php foreach ($opts as $optIndex => $option): ?>
                <label class="exam-option">
                  <input type="radio" name="answer[<?php echo (int) $q['id']; ?>]" value="<?php echo $optIndex; ?>" required>
                  <span class="opt-letter" aria-hidden="true"><?php echo chr(65 + $optIndex); ?></span>
                  <span class="opt-text"><?php echo htmlspecialchars($option); ?></span>
                </label>
              <?php endforeach; ?>
            <?php elseif ($q['qtype'] === 'code'): ?>
              <div class="code-answer-wrap">
                <div class="code-answer-bar"><span>✎</span> <?php echo htmlspecialchars($CODE_LANGS[$q['code_language']] ?? $q['code_language']); ?> editor</div>
                <textarea name="answer[<?php echo (int) $q['id']; ?>]" class="code-answer-input" spellcheck="false" required><?php echo htmlspecialchars($q['code_starter'] ?? ''); ?></textarea>
              </div>
            <?php else: ?>
              <textarea name="answer[<?php echo (int) $q['id']; ?>]" class="essay-answer-input" rows="6" placeholder="Write your answer here…" required></textarea>
              <div class="essay-word-count" data-word-count>0 words</div>
            <?php endif; ?>
          </fieldset>
        <?php endforeach; ?>

        <div class="exam-submit-bar">
          <span class="exam-submit-hint" id="examSubmitHint"><?php echo $questionCount; ?> question<?php echo $questionCount === 1 ? '' : 's'; ?> to go</span>
          <button class="btn primary" type="submit">Submit exam</button>
        </div>
      </form>
      <?php endif; ?>
  <?php endif; ?>
<?php include __DIR__ . '/includes/drive_shell_bottom.php'; ?>

<?php if (!$pendingSubmitted): ?>
<script>
  (function () {
    const form = document.getElementById('examForm');
    if (!form) return;
    const questions = Array.from(form.querySelectorAll('[data-question]'));
    const fill = document.getElementById('examProgressFill');
    const text = document.getElementById('examProgressText');
    const hint = document.getElementById('examSubmitHint');
    const total = questions.length;

    function isAnswered(q) {
      const type = q.getAttribute('data-qtype');
      if (type === 'mcq') return !!q.querySelector('input[type=radio]:checked');
      const el = q.querySelector('textarea');
      return el && el.value.trim().length > 0;
    }

    function answeredCount() { return questions.filter(isAnswered).length; }

    function updateProgress() {
      const done = answeredCount();
      const pct = total ? Math.round((done / total) * 100) : 0;
      fill.style.width = pct + '%';
      text.textContent = done + ' / ' + total + ' answered';
      hint.textContent = done >= total ? 'All set — ready to submit' : (total - done) + ' question' + (total - done === 1 ? '' : 's') + ' to go';
      questions.forEach((q) => { if (isAnswered(q)) q.classList.remove('needs-answer'); });
    }

    form.addEventListener('input', updateProgress);
    form.addEventListener('change', updateProgress);
    updateProgress();

    // Live word counters on essay answers.
    form.querySelectorAll('.essay-answer-input').forEach((ta) => {
      const counter = ta.closest('[data-question]').querySelector('[data-word-count]');
      const update = () => {
        const words = ta.value.trim().split(/\s+/).filter(Boolean).length;
        counter.textContent = words + ' word' + (words === 1 ? '' : 's');
      };
      ta.addEventListener('input', update);
      update();
    });

    // Tab key inserts spaces inside code editors instead of moving focus.
    form.querySelectorAll('.code-answer-input').forEach((ta) => {
      ta.addEventListener('keydown', (e) => {
        if (e.key === 'Tab') {
          e.preventDefault();
          const start = ta.selectionStart, end = ta.selectionEnd;
          ta.value = ta.value.slice(0, start) + '  ' + ta.value.slice(end);
          ta.selectionStart = ta.selectionEnd = start + 2;
        }
      });
    });

    form.addEventListener('submit', function (e) {
      // Auto-submits (time up / tab switch) skip the "all questions answered" check —
      // we want whatever the student had, not to trap them in an expired exam.
      if (form.dataset.autoSubmitting === '1') return;
      let firstBad = null;
      questions.forEach((q) => {
        const answered = isAnswered(q);
        q.classList.toggle('needs-answer', !answered);
        if (!answered && !firstBad) firstBad = q;
      });
      if (firstBad) {
        e.preventDefault();
        firstBad.scrollIntoView({ behavior: 'smooth', block: 'center' });
      }
    });

    // ---- Anti-cheat: leaving the tab, or the timer hitting zero, both force-submit. ----
    let autoSubmitted = false;
    function forceSubmit(reason, message) {
      if (autoSubmitted) return;
      autoSubmitted = true;
      form.dataset.autoSubmitting = '1';
      const reasonField = document.getElementById('examAutoReason');
      if (reasonField) reasonField.value = reason;
      // Submit first — alert() blocks the thread, and if the tab is hidden
      // it won't even show until the student comes back, so it must not be
      // allowed to hold up the actual submission.
      form.submit();
      if (message) {
        setTimeout(function () {
          try { alert(message); } catch (e) {}
        }, 0);
      }
    }

    document.addEventListener('visibilitychange', function () {
      if (document.hidden) {
        forceSubmit('tab_switch', 'You switched tabs, so your exam is being submitted automatically now.');
      }
    });

    // ---- Countdown timer ----
    let remaining = form.dataset.timeRemaining !== '' ? parseInt(form.dataset.timeRemaining, 10) : null;
    const expiredOnLoad = form.dataset.expired === '1';
    const timerText = document.getElementById('examTimerText');
    const timerBar = document.getElementById('examTimerBar');

    function renderTimer() {
      if (remaining === null || !timerText) return;
      const m = Math.floor(remaining / 60);
      const s = remaining % 60;
      timerText.textContent = m + ':' + String(s).padStart(2, '0') + ' remaining';
      if (timerBar) timerBar.style.color = remaining <= 60 ? '#d9534f' : '';
    }

    if (remaining !== null) {
      renderTimer();
      if (expiredOnLoad || remaining <= 0) {
        forceSubmit('time_up', "Time's up! Submitting your exam automatically.");
      } else {
        const tick = setInterval(function () {
          remaining -= 1;
          if (remaining <= 0) {
            clearInterval(tick);
            renderTimer();
            forceSubmit('time_up', "Time's up! Submitting your exam automatically.");
          } else {
            renderTimer();
          }
        }, 1000);
      }
    }
  })();
</script>
<?php endif; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>
