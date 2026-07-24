<?php
// Inside/dashboard page — suppress the marketing topbar (see includes/header.php).
$__hideTopbar = true;
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/exams_db.php';
require_once __DIR__ . '/includes/sections.php';
require_once __DIR__ . '/includes/schools.php';

$user = inkwell_require_role(['student', 'teacher']);

// Teachers see their own subjects grid (the "My subjects" block that used
// to live inline on the Lessons page) instead of the student join-class
// flow below, since a teacher owns their subjects rather than joining them.
if ($user['role'] === 'teacher') {
  $__me = $user;
  $__mySubjects = inkwell_teacher_subjects($user['id']);
  $__mySubjectsLabel = 'My subjects';

  $pageTitle = 'My Section';
  include __DIR__ . '/includes/header.php';
  $driveActive = 'my-section';
  $driveCrumbs = [['label' => 'Home', 'href' => '/index.php'], ['label' => 'My Section']];
  $driveTitle = 'My Section';
  $driveSubtitle = 'Every subject you teach, grouped by year level.';
  include __DIR__ . '/includes/drive_shell_top.php';
  ?>
    <?php if (empty($__mySubjects)): ?>
      <section class="admin-card glass-card">
        <h2>No subjects yet</h2>
        <p class="admin-sub">Create a subject from <a href="/teacher/subject.php">Subjects</a> to see it here.</p>
      </section>
    <?php else: ?>
      <?php include __DIR__ . '/includes/mysection.php'; ?>
    <?php endif; ?>
  <?php include __DIR__ . '/includes/drive_shell_bottom.php'; ?>
  <?php include __DIR__ . '/includes/footer.php'; ?>
  <?php
  exit;
}

$notice = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';

  if ($action === 'join_class') {
    $subjectId = (int) ($_POST['subject_id'] ?? 0);
    $subj = inkwell_get_subject($subjectId);
    if (!$subj) {
      $error = 'That class is not available.';
    } else {
      inkwell_request_enrollment($user['id'], $subjectId);
      $notice = 'Request sent to ' . htmlspecialchars($subj['teacher_name'] ?? 'the teacher') . ' — you\'ll get access to "' . htmlspecialchars($subj['title']) . '" once they approve it.';
    }
  }
}

$mySections = inkwell_student_sections($user['id']);

$enrolledSubjects = inkwell_student_enrolled_subjects($user['id']);
$enrolledIds = array_column($enrolledSubjects, 'id');

$mySchool = null;
$mySchoolTeachers = [];
$mySchoolSubjects = [];
if (!empty($user['school_id'])) {
  $mySchool = inkwell_get_school($user['school_id']);
  if ($mySchool) {
    $mySchoolTeachers = inkwell_list_school_teachers($mySchool['id'], true);
    $allMySchoolSubjects = inkwell_school_subjects($mySchool['id']);
    $mySchoolSubjects = array_filter($allMySchoolSubjects, function ($s) use ($enrolledIds) {
      return !in_array($s['id'], $enrolledIds);
    });
  }
}

$pageTitle = 'My Section';
include __DIR__ . '/includes/header.php';
$driveActive = 'my-section';
$driveCrumbs = [['label' => 'Home', 'href' => '/index.php'], ['label' => 'My Section']];
$driveTitle = 'My Section';
$driveSubtitle = 'Every subject/class under your section — the same block your classmates are taking. Join the ones you haven\'t yet.';
include __DIR__ . '/includes/drive_shell_top.php';
?>
  <?php if ($notice): ?><div class="exam-result pass"><?php echo htmlspecialchars($notice); ?></div><?php endif; ?>
  <?php if ($error): ?><div class="exam-result fail"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

  <?php if (empty($mySections)): ?>
    <section class="admin-card glass-card">
      <h2>No section yet</h2>
      <p class="admin-sub">You'll show up in a section automatically once you're approved into a subject that your teacher has tagged to one. Browse and join subjects from <a href="/exams.php">Exams &amp; subjects</a> in the meantime.</p>
    </section>
  <?php else: ?>
    <?php foreach ($mySections as $sec): ?>
      <?php $subjects = inkwell_section_subjects($sec['id']); ?>
      <section class="admin-card glass-card">
        <div class="admin-header-row" style="margin-bottom:0;">
          <div>
            <h2><?php echo htmlspecialchars($sec['name']); ?></h2>
            <p class="admin-sub">Adviser: <?php echo htmlspecialchars($sec['adviser_name']); ?><?php echo !empty($sec['term']) ? ' · ' . htmlspecialchars($sec['term']) : ''; ?><?php echo !empty($sec['academic_year']) ? ' ' . htmlspecialchars($sec['academic_year']) : ''; ?><?php echo !empty($sec['year_level']) ? ' · ' . htmlspecialchars($sec['year_level']) : ''; ?></p>
          </div>
        </div>
        <?php if (empty($subjects)): ?>
          <p class="admin-sub">No subjects tagged to this section yet.</p>
        <?php else: ?>
          <div class="drive-grid">
            <?php foreach ($subjects as $subj):
              $approved = inkwell_is_enrolled($user['id'], $subj['id']);
              $pending = !$approved && inkwell_has_pending_request($user['id'], $subj['id']);
            ?>
              <?php if ($approved): ?>
                <a class="drive-file-card" href="/class.php?id=<?php echo (int) $subj['id']; ?>">
              <?php else: ?>
                <div class="drive-file-card">
              <?php endif; ?>
                <div class="drive-file-cover" style="background:linear-gradient(135deg, #6c5ce726, #6c5ce70d);">
                  <span class="drive-file-icon" style="background:#6c5ce71f; color:#6c5ce7;">📚</span>
                  <?php if (!empty($subj['code'])): ?><span class="drive-file-badge"><?php echo htmlspecialchars($subj['code']); ?></span><?php endif; ?>
                </div>
                <div class="drive-file-body">
                  <span class="drive-file-name"><?php echo htmlspecialchars($subj['title']); ?></span>
                  <div class="drive-file-meta">
                    <span><?php echo htmlspecialchars($subj['teacher_name']); ?></span>
                    <span><?php echo (int) $subj['exam_count']; ?> exam<?php echo (int) $subj['exam_count'] === 1 ? '' : 's'; ?></span>
                  </div>
                  <?php if ($approved): ?>
                    <span class="badge badge-status-active" style="margin-top:8px;">Joined — click to view exams</span>
                  <?php elseif ($pending): ?>
                    <span class="badge badge-status-pending" style="margin-top:8px;">Requested</span>
                  <?php else: ?>
                    <form method="post" action="/my-section.php" style="margin-top:8px;" onclick="event.stopPropagation();">
                      <input type="hidden" name="action" value="join_class">
                      <input type="hidden" name="subject_id" value="<?php echo (int) $subj['id']; ?>">
                      <button class="btn primary" type="submit">Request to join</button>
                    </form>
                  <?php endif; ?>
                </div>
              <?php echo $approved ? '</a>' : '</div>'; ?>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </section>
    <?php endforeach; ?>
  <?php endif; ?>

  <?php include __DIR__ . '/includes/my_classes.php'; ?>

  <?php include __DIR__ . '/includes/my_school_classes.php'; ?>

<?php include __DIR__ . '/includes/drive_shell_bottom.php'; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>
