<?php
// Inside/dashboard page — suppress the marketing topbar (see includes/header.php).
$__hideTopbar = true;
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/exams_db.php';
require_once __DIR__ . '/includes/schools.php';

$user = inkwell_current_user();
$notice = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';

  if ($action === 'join_class') {
    if (!$user) {
      header('Location: /login.php?next=' . urlencode('/join-class.php'));
      exit;
    }
    if ($user['role'] !== 'student') {
      $error = 'Only student accounts can request to join a class.';
    } else {
      $subjectId = (int) ($_POST['subject_id'] ?? 0);
      $subj = inkwell_get_subject($subjectId);
      if (!$subj || $subj['teacher_status'] !== 'active') {
        $error = 'That class is not available.';
      } else {
        inkwell_request_enrollment($user['id'], $subjectId);
        $notice = 'Request sent to ' . htmlspecialchars($subj['teacher_name']) . ' — you\'ll get access to "' . htmlspecialchars($subj['title']) . '" once they approve it.';
      }
    }
  }
}

$allSubjects = inkwell_all_subjects();
$enrolledSubjects = ($user && $user['role'] === 'student') ? inkwell_student_enrolled_subjects($user['id']) : [];
$enrolledIds = array_column($enrolledSubjects, 'id');

$mySchool = null;
$mySchoolSubjects = [];
$mySchoolSubjectIds = [];
if ($user && $user['role'] === 'student' && !empty($user['school_id'])) {
  $mySchool = inkwell_get_school($user['school_id']);
  if ($mySchool) {
    $allMySchoolSubjects = inkwell_school_subjects($mySchool['id']);
    $mySchoolSubjectIds = array_column($allMySchoolSubjects, 'id');
    $mySchoolSubjects = array_values(array_filter($allMySchoolSubjects, function ($s) use ($enrolledIds) {
      return !in_array($s['id'], $enrolledIds);
    }));
  }
}

// "Browse & join" is everyone else's subjects — the student's own school gets
// its own dedicated section above so it isn't listed twice.
$browseSubjects = array_values(array_filter($allSubjects, function ($s) use ($enrolledIds, $mySchoolSubjectIds) {
  return !in_array($s['id'], $enrolledIds) && !in_array($s['id'], $mySchoolSubjectIds);
}));

$pageTitle = 'Join a Class';
include __DIR__ . '/includes/header.php';
$driveActive = 'subjects';
$driveCrumbs = [['label' => 'Home', 'href' => '/index.php'], ['label' => 'Exams', 'href' => '/exams.php'], ['label' => 'Join a class']];
$driveTitle = 'Join a Class';
$driveSubtitle = "Browse every teacher's subject and request to join. You'll get access to a class's exams as soon as the teacher approves it.";
include __DIR__ . '/includes/drive_shell_top.php';

/**
 * Renders one subject as a join-card. Shared by the "your school" and
 * "browse everyone else" sections below so the two look identical.
 */
if (!function_exists('inkwell_render_join_card')) {
function inkwell_render_join_card($s, $user) {
  $pending = $user && $user['role'] === 'student' && inkwell_has_pending_request($user['id'], $s['id']);
  ?>
  <div class="join-card" data-search="<?php echo htmlspecialchars(strtolower($s['title'] . ' ' . $s['teacher_name'])); ?>">
    <div class="join-card-top">
      <span class="join-card-icon" aria-hidden="true">📚</span>
      <?php if (!empty($s['code'])): ?><span class="drive-file-badge"><?php echo htmlspecialchars($s['code']); ?></span><?php endif; ?>
    </div>
    <div class="join-card-body">
      <span class="join-card-name"><?php echo htmlspecialchars($s['title']); ?></span>
      <button type="button" class="teacher-chip" data-teacher-id="<?php echo (int) $s['teacher_id']; ?>" data-modal-open="teacherProfileModal">
        <span class="teacher-chip-avatar"><?php echo strtoupper(substr($s['teacher_name'], 0, 1)); ?></span>
        <?php echo htmlspecialchars($s['teacher_name']); ?>
      </button>
      <p class="join-card-desc"><?php echo htmlspecialchars($s['description'] ?: 'No description yet.'); ?></p>
      <div class="join-card-meta">
        <span><?php echo (int) $s['exam_count']; ?> exam<?php echo (int) $s['exam_count'] === 1 ? '' : 's'; ?></span>
        <span><?php echo (int) $s['student_count']; ?> student<?php echo (int) $s['student_count'] === 1 ? '' : 's'; ?></span>
      </div>
      <div class="join-card-cta">
        <?php if (!$user): ?>
          <a class="btn" style="width:100%; text-align:center;" href="/login.php?next=<?php echo urlencode('/join-class.php'); ?>">Log in to join</a>
        <?php elseif ($user['role'] !== 'student'): ?>
          <span class="admin-sub">Student accounts only</span>
        <?php elseif ($pending): ?>
          <span class="badge badge-status-pending" style="width:100%; text-align:center; display:block;">Requested</span>
        <?php else: ?>
          <form method="post" action="/join-class.php" style="width:100%;">
            <input type="hidden" name="action" value="join_class">
            <input type="hidden" name="subject_id" value="<?php echo (int) $s['id']; ?>">
            <button class="btn primary" style="width:100%;" type="submit">Request to join</button>
          </form>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <?php
}
}
?>

<?php if ($notice): ?><div class="exam-result pass"><?php echo $notice; ?></div><?php endif; ?>
<?php if ($error): ?><div class="exam-result fail"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

<div class="table-search-wrap" style="max-width:420px;">
  <input type="text" id="joinClassSearch" class="table-search-input" placeholder="Search by subject or teacher…">
</div>

<?php if ($mySchool && !empty($mySchoolSubjects)): ?>
  <section class="admin-card glass-card" id="school" data-join-section>
    <div class="school-card-head" style="margin-bottom:14px;">
      <?php if (!empty($mySchool['logo'])): ?>
        <img class="school-logo" src="/assets/uploads/<?php echo htmlspecialchars($mySchool['logo']); ?>" alt="" loading="lazy">
      <?php else: ?>
        <span class="school-logo-placeholder" aria-hidden="true">🏫</span>
      <?php endif; ?>
      <div>
        <h2 style="margin:0;">Classes at <?php echo htmlspecialchars($mySchool['name']); ?></h2>
        <p class="admin-sub" style="margin:0;">Your school — these teachers already know you.</p>
      </div>
    </div>
    <div class="join-grid">
      <?php foreach ($mySchoolSubjects as $s): inkwell_render_join_card($s, $user); ?><?php endforeach; ?>
    </div>
  </section>
<?php endif; ?>

<section class="admin-card glass-card" id="browse" data-join-section>
  <h2><?php echo $mySchool ? 'Other classes' : 'Browse & join classes'; ?></h2>
  <p class="admin-sub"><?php echo $mySchool ? 'Subjects from other schools and independent teachers.' : "Every approved teacher's subject."; ?></p>
  <?php if (empty($browseSubjects)): ?>
    <p class="admin-sub"><?php echo empty($allSubjects) ? 'No teacher subjects are available yet.' : "You've joined every available class."; ?></p>
  <?php else: ?>
    <div class="join-grid">
      <?php foreach ($browseSubjects as $s): inkwell_render_join_card($s, $user); ?><?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<p id="joinClassNoResults" class="admin-sub" style="display:none; padding:14px;">No classes match your search.</p>

<script>
(function () {
  var input = document.getElementById('joinClassSearch');
  if (!input) return;
  var cards = Array.prototype.slice.call(document.querySelectorAll('.join-card'));
  var sections = Array.prototype.slice.call(document.querySelectorAll('[data-join-section]'));
  var noResults = document.getElementById('joinClassNoResults');
  input.addEventListener('input', function () {
    var q = input.value.trim().toLowerCase();
    var visibleCount = 0;
    cards.forEach(function (card) {
      var match = !q || (card.getAttribute('data-search') || '').indexOf(q) !== -1;
      card.style.display = match ? '' : 'none';
      if (match) visibleCount++;
    });
    sections.forEach(function (sec) {
      var anyVisible = Array.prototype.slice.call(sec.querySelectorAll('.join-card')).some(function (c) { return c.style.display !== 'none'; });
      sec.style.display = (q && !anyVisible) ? 'none' : '';
    });
    if (noResults) noResults.style.display = (q && visibleCount === 0) ? '' : 'none';
  });
})();
</script>

<style>
.join-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 16px; }
.join-card {
  background: var(--surface); border: 1px solid var(--border-soft); border-radius: 16px; overflow: hidden;
  box-shadow: var(--shadow-sm); transition: transform 0.18s ease, box-shadow 0.18s ease; display: flex; flex-direction: column;
}
.join-card:hover { transform: translateY(-4px); box-shadow: var(--shadow); }
.join-card-top { display: flex; align-items: flex-start; justify-content: space-between; padding: 16px 16px 0; }
.join-card-icon { width: 38px; height: 38px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; background: #6c5ce71f; color: #6c5ce7; }
.join-card-body { padding: 12px 16px 16px; display: flex; flex-direction: column; gap: 8px; flex: 1; }
.join-card-name { font-weight: 700; color: var(--ink); font-size: 0.98rem; }
.join-card-desc { font-size: 0.8rem; color: var(--ink-dim); margin: 0; line-height: 1.4; }
.join-card-meta { display: flex; gap: 14px; font-size: 0.76rem; color: var(--ink-dim); }
.join-card-cta { margin-top: auto; padding-top: 6px; }
@media (max-width: 640px) { .join-grid { grid-template-columns: 1fr; } }
</style>

<?php include __DIR__ . '/includes/teacher_profile_modal.php'; ?>
<?php include __DIR__ . '/includes/drive_shell_bottom.php'; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>
