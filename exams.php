<?php
// Inside/dashboard page — suppress the marketing topbar (see includes/header.php).
$__hideTopbar = true;
require_once __DIR__ . '/data/lessons.php';
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
      header('Location: /login.php?next=' . urlencode('/exams.php'));
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

  if ($action === 'leave_class') {
    if ($user && $user['role'] === 'student') {
      $subjectId = (int) ($_POST['subject_id'] ?? 0);
      inkwell_unenroll_student($user['id'], $subjectId);
      $notice = 'Left the class.';
    }
  }
}

$allSubjects = inkwell_all_subjects();
$enrolledSubjects = ($user && $user['role'] === 'student') ? inkwell_student_enrolled_subjects($user['id']) : [];
$enrolledIds = array_column($enrolledSubjects, 'id');

$mySchool = null;
$mySchoolTeachers = [];
$mySchoolSubjects = [];
$mySchoolSubjectIds = [];
if ($user && $user['role'] === 'student' && !empty($user['school_id'])) {
  $mySchool = inkwell_get_school($user['school_id']);
  if ($mySchool) {
    $mySchoolTeachers = inkwell_list_school_teachers($mySchool['id'], true);
    $allMySchoolSubjects = inkwell_school_subjects($mySchool['id']);
    $mySchoolSubjectIds = array_column($allMySchoolSubjects, 'id');
    $mySchoolSubjects = array_filter($allMySchoolSubjects, function ($s) use ($enrolledIds) {
      return !in_array($s['id'], $enrolledIds);
    });
  }
}

// "Browse & join" is everyone else's subjects — the student's own school gets
// its own dedicated section above so it isn't listed twice.
$browseSubjects = array_filter($allSubjects, function ($s) use ($enrolledIds, $mySchoolSubjectIds) {
  return !in_array($s['id'], $enrolledIds) && !in_array($s['id'], $mySchoolSubjectIds);
});

$pageTitle = 'Exams & certificates';
include __DIR__ . '/includes/header.php';
$driveActive = 'exams';
$driveCrumbs = [['label' => 'Home', 'href' => '/index.php'], ['label' => 'Exams']];
$driveTitle = 'Exams & certificates';
$driveSubtitle = "Take a self-study certification exam on your own, or join a teacher's subject to unlock every exam inside it — either way you'll need to be logged in so your certificate is tied to your account.";
include __DIR__ . '/includes/drive_shell_top.php';
?>
    <?php if ($notice): ?><div class="exam-result pass"><?php echo $notice; ?></div><?php endif; ?>
    <?php if ($error): ?><div class="exam-result fail"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

    <section class="admin-card glass-card" id="selfstudy">
      <div class="exam-linkcard-row">
        <a class="exam-linkcard" href="/self-study-exams.php">
          <span class="exam-linkcard-icon" aria-hidden="true">📘</span>
          <span class="exam-linkcard-body">
            <span class="exam-linkcard-title">Self-study exams</span>
            <span class="exam-linkcard-sub">The standard Inkwell certification exam for each language — no teacher attached.</span>
          </span>
          <span class="exam-linkcard-arrow" aria-hidden="true">→</span>
        </a>
        <a class="exam-linkcard" href="/official-certification-exams.php" id="official">
          <span class="exam-linkcard-icon" aria-hidden="true">🏅</span>
          <span class="exam-linkcard-body">
            <span class="exam-linkcard-title">Official certification exams</span>
            <span class="exam-linkcard-sub">Admin-published certification exams — no class to join, just log in and take one.</span>
          </span>
          <span class="exam-linkcard-arrow" aria-hidden="true">→</span>
        </a>
      </div>
    </section>

    <section class="admin-card glass-card" id="subjects">
      <h2>Browse &amp; join classes</h2>
      <p class="admin-sub">Join a teacher's subject to unlock every exam inside it — a request goes to the teacher and you're in as soon as they approve it.</p>
      <a class="exam-linkcard" href="/join-class.php">
        <span class="exam-linkcard-icon" aria-hidden="true">📚</span>
        <span class="exam-linkcard-body">
          <span class="exam-linkcard-title">Join a Class</span>
          <span class="exam-linkcard-sub"><?php echo empty($browseSubjects) && empty($mySchoolSubjects) ? "You've joined every available class." : 'Browse ' . (count($browseSubjects) + count($mySchoolSubjects)) . ' open class' . ((count($browseSubjects) + count($mySchoolSubjects)) === 1 ? '' : 'es') . ' and request to join.'; ?></span>
        </span>
        <span class="exam-linkcard-arrow" aria-hidden="true">→</span>
      </a>
    </section>

<?php include __DIR__ . '/includes/teacher_profile_modal.php'; ?>
<?php include __DIR__ . '/includes/drive_shell_bottom.php'; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>

