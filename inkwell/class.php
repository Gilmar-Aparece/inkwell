<?php
// Inside/dashboard page — suppress the marketing topbar (see includes/header.php).
$__hideTopbar = true;
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/exams_db.php';
require_once __DIR__ . '/includes/schools.php';

$user = inkwell_require_role(['student', 'teacher']);

$subjectId = (int) ($_GET['id'] ?? 0);
$subj = $subjectId ? inkwell_get_subject($subjectId) : null;

$notice = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';
  $postSubjectId = (int) ($_POST['subject_id'] ?? 0);

  if ($action === 'join_class' && $user['role'] === 'student') {
    $joinSubj = inkwell_get_subject($postSubjectId);
    if (!$joinSubj) {
      $error = 'That class is not available.';
    } else {
      inkwell_request_enrollment($user['id'], $postSubjectId);
      $notice = 'Request sent to ' . htmlspecialchars($joinSubj['teacher_name'] ?? 'the teacher') . ' — you\'ll get access once they approve it.';
    }
  }

  if ($action === 'leave_class' && $user['role'] === 'student') {
    inkwell_unenroll_student($user['id'], $postSubjectId);
    header('Location: /my-section.php');
    exit;
  }
}

if (!$subj) {
  $pageTitle = 'Class not found';
  include __DIR__ . '/includes/header.php';
  ?>
  <main class="admin-main">
    <div class="admin-header-row"><h1>Class not found</h1></div>
    <section class="admin-card glass-card">
      <p class="admin-sub">This class doesn't exist or may have been removed.</p>
      <a class="btn primary" href="/my-section.php" style="margin-top:10px; display:inline-block;">← Back to My Section</a>
    </section>
  </main>
  <?php include __DIR__ . '/includes/footer.php'; ?>
  <?php
  exit;
}

$isOwnerTeacher = $user['role'] === 'teacher' && (int) $subj['teacher_id'] === (int) $user['id'];
$isEnrolled = $user['role'] === 'student' && inkwell_is_enrolled($user['id'], $subjectId);
$isPending = $user['role'] === 'student' && !$isEnrolled && inkwell_has_pending_request($user['id'], $subjectId);
$canView = $isOwnerTeacher || $isEnrolled;

$exams = $canView ? inkwell_subject_exams($subjectId) : [];
$studentCount = (int) inkwell_enrollment_count($subjectId);

$pageTitle = $subj['title'];
include __DIR__ . '/includes/header.php';
$driveActive = 'my-section';
$driveCrumbs = [
  ['label' => 'Home', 'href' => '/index.php'],
  ['label' => 'My Section', 'href' => '/my-section.php'],
  ['label' => $subj['title']],
];
$driveTitle = $subj['title'];
$driveSubtitle = $canView
  ? 'Every exam under this class, ready to take.'
  : 'You need to join this class to see its exams.';
include __DIR__ . '/includes/drive_shell_top.php';
?>

<?php if ($notice): ?><div class="exam-result pass"><?php echo htmlspecialchars($notice); ?></div><?php endif; ?>
<?php if ($error): ?><div class="exam-result fail"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

<section class="admin-card glass-card class-hero">
  <div class="class-hero-row">
    <span class="class-hero-icon" aria-hidden="true">📚</span>
    <div class="class-hero-body">
      <div class="class-hero-title-row">
        <h2><?php echo htmlspecialchars($subj['title']); ?></h2>
        <?php if (!empty($subj['code'])): ?><span class="drive-file-badge"><?php echo htmlspecialchars($subj['code']); ?></span><?php endif; ?>
      </div>
      <button type="button" class="teacher-chip" data-teacher-id="<?php echo (int) $subj['teacher_id']; ?>" data-modal-open="teacherProfileModal">
        <span class="teacher-chip-avatar"><?php echo strtoupper(substr($subj['teacher_name'], 0, 1)); ?></span>
        with <?php echo htmlspecialchars($subj['teacher_name']); ?>
      </button>
      <?php if (!empty($subj['description'])): ?><p class="admin-sub" style="margin-top:8px;"><?php echo htmlspecialchars($subj['description']); ?></p><?php endif; ?>
      <div class="class-hero-meta">
        <span><?php echo (int) count($exams); ?> exam<?php echo count($exams) === 1 ? '' : 's'; ?></span>
        <span><?php echo $studentCount; ?> student<?php echo $studentCount === 1 ? '' : 's'; ?></span>
      </div>
    </div>
    <?php if ($isEnrolled): ?>
      <form method="post" action="/class.php?id=<?php echo $subjectId; ?>" onsubmit="return confirm('Leave this class?');">
        <input type="hidden" name="action" value="leave_class">
        <input type="hidden" name="subject_id" value="<?php echo $subjectId; ?>">
        <button class="btn" type="submit">Leave class</button>
      </form>
    <?php endif; ?>
  </div>
</section>

<?php if ($canView): ?>
  <section class="admin-card glass-card">
    <h2>Exams</h2>
    <?php if (empty($exams)): ?>
      <p class="admin-sub">No exams published yet — check back once <?php echo htmlspecialchars($subj['teacher_name']); ?> adds some.</p>
    <?php else: ?>
      <div class="drive-grid">
        <?php foreach ($exams as $ex): $__hasQ = (int) $ex['question_count'] > 0; ?>
          <?php if ($__hasQ): ?>
            <a class="drive-file-card" href="/exam.php?teacher_cat=<?php echo (int) $ex['id']; ?>">
          <?php else: ?>
            <div class="drive-file-card drive-file-card-disabled">
          <?php endif; ?>
              <div class="drive-file-cover" style="background:linear-gradient(135deg, #00b89426, #00b8940d);">
                <span class="drive-file-icon" style="background:#00b8941f; color:#00b894;">🎓</span>
                <span class="drive-file-badge"><?php echo $ex['purpose'] === 'cert' ? 'Certificate' : 'Grade only'; ?></span>
              </div>
              <div class="drive-file-body">
                <span class="drive-file-name"><?php echo htmlspecialchars($ex['title']); ?></span>
                <div class="drive-file-meta">
                  <span><?php echo (int) $ex['question_count']; ?> question<?php echo (int) $ex['question_count'] === 1 ? '' : 's'; ?></span>
                  <span>Pass <?php echo (int) $ex['pass_score']; ?>%</span>
                </div>
                <?php if (!$__hasQ): ?>
                  <span class="admin-sub" style="margin-top:8px; display:block;">No questions yet</span>
                <?php endif; ?>
              </div>
          <?php echo $__hasQ ? '</a>' : '</div>'; ?>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>
<?php else: ?>
  <section class="admin-card glass-card">
    <h2>You haven't joined this class yet</h2>
    <p class="admin-sub">Join to unlock every exam inside <?php echo htmlspecialchars($subj['title']); ?>.</p>
    <?php if ($isPending): ?>
      <span class="badge badge-status-pending" style="margin-top:10px; display:inline-block;">Requested — waiting on <?php echo htmlspecialchars($subj['teacher_name']); ?></span>
    <?php elseif ($user['role'] === 'student'): ?>
      <form method="post" action="/class.php?id=<?php echo $subjectId; ?>" style="margin-top:10px;">
        <input type="hidden" name="action" value="join_class">
        <input type="hidden" name="subject_id" value="<?php echo $subjectId; ?>">
        <button class="btn primary" type="submit">Request to join</button>
      </form>
    <?php endif; ?>
  </section>
<?php endif; ?>

<?php include __DIR__ . '/includes/teacher_profile_modal.php'; ?>
<?php include __DIR__ . '/includes/drive_shell_bottom.php'; ?>
<style>
.class-hero-row { display: flex; align-items: flex-start; gap: 16px; }
.class-hero-icon { width: 52px; height: 52px; border-radius: 14px; background: #6c5ce71f; color: #6c5ce7; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; flex-shrink: 0; }
.class-hero-body { flex: 1; min-width: 0; }
.class-hero-title-row { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin-bottom: 8px; }
.class-hero-title-row h2 { margin: 0; }
.class-hero-meta { display: flex; gap: 16px; font-size: 0.82rem; color: var(--ink-dim); margin-top: 10px; }
.drive-file-card-disabled { cursor: default; opacity: 0.7; }
@media (max-width: 640px) { .class-hero-row { flex-direction: column; } }
</style>
<?php include __DIR__ . '/includes/footer.php'; ?>
