<?php
// Inside/dashboard page — suppress the marketing topbar (see includes/header.php).
$__hideTopbar = true;
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/students.php';
require_once __DIR__ . '/../includes/schools.php';
require_once __DIR__ . '/../includes/exams_db.php';
require_once __DIR__ . '/../includes/events.php';

$user = inkwell_require_role('teacher');
if ($user['status'] !== 'active') {
  header('Location: /teacher/dashboard.php');
  exit;
}

$notice = '';
$error = '';
$schoolId = $user['school_id'] ? (int) $user['school_id'] : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';
  $studentId = (int) ($_POST['student_id'] ?? 0);

  if (!$schoolId) {
    $error = 'You need to be attached to a school to feature top students. Ask your dean to add you, or set your school from your account.';
  } elseif ($action === 'feature_student') {
    // Only feature students actually enrolled in one of this teacher's subjects.
    if (inkwell_is_teacher_student($user['id'], $studentId)) {
      $result = inkwell_add_featured_student($schoolId, $studentId, $user['id'], $_POST['note'] ?? '', $_POST['description'] ?? '', $_POST['accomplishment'] ?? '');
      $notice = $result['ok'] ? 'Added to top students.' : $result['error'];
    } else {
      $error = 'That student is not enrolled in one of your subjects.';
    }
  } elseif ($action === 'update_featured_student') {
    inkwell_update_featured_student((int) ($_POST['featured_id'] ?? 0), $schoolId, $_POST['note'] ?? '', $_POST['description'] ?? '', $_POST['accomplishment'] ?? '');
    $notice = 'Top student updated.';
  } elseif ($action === 'unfeature_student') {
    inkwell_remove_featured_student((int) ($_POST['featured_id'] ?? 0), $schoolId);
    $notice = 'Removed from top students.';
  }
}

$students = inkwell_teacher_students($user['id']);
$featuredByStudent = [];
if ($schoolId) {
  foreach (inkwell_featured_students($schoolId) as $f) { $featuredByStudent[$f['student_id']] = $f; }
}

$dashNavTitle = 'Teacher';
$dashNavActive = 'students';
$dashNavItems = [
  ['key' => 'dashboard', 'group' => 'General', 'href' => '/teacher/overview.php', 'label' => 'Dashboard', 'icon' => '🏠'],
  ['key' => 'subjects', 'group' => 'Teaching', 'href' => '/teacher/dashboard.php', 'label' => 'Subjects', 'icon' => '🗂'],
  ['key' => 'sections', 'group' => 'Teaching', 'href' => '/teacher/sections.php', 'label' => 'Sections', 'icon' => '👥'],
  ['key' => 'grade', 'group' => 'Teaching', 'href' => '/teacher/grade.php', 'label' => 'Grade', 'icon' => '🖊', 'count' => count(inkwell_teacher_pending_attempts($user['id']))],
  ['key' => 'students', 'group' => 'People', 'href' => '/teacher/students.php', 'label' => 'Students', 'icon' => '🎓', 'count' => count($students)],
  ['key' => 'certificates', 'group' => 'People', 'href' => '/teacher/certificates.php', 'label' => 'Certificates', 'icon' => '📜'],
  ['key' => 'events', 'group' => 'Activity', 'href' => '/teacher/events.php', 'label' => 'Events', 'icon' => '📣', 'count' => count(inkwell_events_by_author($user['id']))],
];

$pageTitle = 'Your students';
include __DIR__ . '/../includes/header.php';
?>
<div class="dash-shell">
  <?php include __DIR__ . '/../includes/dash_nav.php'; ?>
  <main class="admin-main">
  <div class="admin-header-row">
    <h1>Your students</h1>
    <a class="btn" href="/teacher/dashboard.php">← Back to dashboard</a>
  </div>

  <?php if ($notice): ?><div class="exam-result pass"><?php echo htmlspecialchars($notice); ?></div><?php endif; ?>
  <?php if ($error): ?><div class="exam-result fail"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

  <section class="admin-card glass-card">
    <h2>Students enrolled in your subjects (<?php echo count($students); ?>)</h2>
    <p class="admin-sub">Click a student to see their full profile. Star a student to feature them as a top student for your school.</p>
    <?php if (empty($students)): ?>
      <p class="admin-sub">No students enrolled yet.</p>
    <?php else: ?>
      <div class="search-filter">
        <input type="search" class="search-filter-input" data-filter-target="#teacherStudentsTable" placeholder="Search by name, email, or course...">
      </div>
      <div class="admin-table-wrap">
        <table class="admin-table" id="teacherStudentsTable">
          <thead><tr><th>Name</th><th>Email</th><th>Student ID</th><th>Course</th><th>Registered</th><th>Top student</th></tr></thead>
          <tbody>
            <?php foreach ($students as $st): $f = $featuredByStudent[$st['id']] ?? null; ?>
              <tr data-filter-row>
                <td>
                  <button type="button" class="student-cell-btn" data-modal-open="studentProfileModal" data-student-id="<?php echo (int) $st['id']; ?>">
                    <?php if (!empty($st['avatar'])): ?>
                      <img class="student-avatar-img" src="/assets/uploads/<?php echo htmlspecialchars($st['avatar']); ?>" alt="" loading="lazy">
                    <?php else: ?>
                      <span class="student-avatar-placeholder" aria-hidden="true"><?php echo strtoupper(substr($st['name'], 0, 1)); ?></span>
                    <?php endif; ?>
                    <span class="student-cell-name"><?php echo htmlspecialchars($st['name']); ?></span>
                  </button>
                </td>
                <td><?php echo htmlspecialchars($st['email']); ?></td>
                <td><?php echo htmlspecialchars($st['id_number'] ?? '—'); ?></td>
                <td><?php echo htmlspecialchars($st['course'] ?? '—'); ?></td>
                <td><?php echo htmlspecialchars(date('M j, Y', strtotime($st['created_at']))); ?></td>
                <td style="white-space:nowrap;">
                  <?php if ($f): ?>
                    <button type="button" class="feature-star-btn featured" title="Edit top-student details"
                      data-feature-trigger data-modal-open="featureStudentModal"
                      data-student-id="<?php echo (int) $st['id']; ?>"
                      data-featured-id="<?php echo (int) $f['id']; ?>"
                      data-feature-action="update_featured_student"
                      data-note="<?php echo htmlspecialchars($f['note'] ?? ''); ?>"
                      data-description="<?php echo htmlspecialchars($f['description'] ?? ''); ?>"
                      data-accomplishment="<?php echo htmlspecialchars($f['accomplishment'] ?? ''); ?>">★ Edit</button>
                    <form method="post" action="/teacher/students.php" style="display:inline;">
                      <input type="hidden" name="student_id" value="<?php echo (int) $st['id']; ?>">
                      <input type="hidden" name="action" value="unfeature_student">
                      <input type="hidden" name="featured_id" value="<?php echo (int) $f['id']; ?>">
                      <button type="submit" class="feature-star-btn feature-star-btn-icon" title="Remove from top students">✕</button>
                    </form>
                  <?php else: ?>
                    <button type="button" class="feature-star-btn" title="Add to top students"<?php echo $schoolId ? '' : ' disabled'; ?>
                      data-feature-trigger data-modal-open="featureStudentModal"
                      data-student-id="<?php echo (int) $st['id']; ?>"
                      data-feature-action="feature_student">☆ Feature</button>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>
  </main>
</div>
<?php include __DIR__ . '/../includes/student_profile_modal.php'; ?>

<div class="modal-backdrop" id="featureStudentModal">
  <div class="modal">
    <div class="modal-head">
      <h2 id="featureStudentModalTitle">Feature this student</h2>
      <button type="button" data-modal-close aria-label="Close">✕</button>
    </div>
    <form method="post" action="/teacher/students.php" class="admin-form">
      <input type="hidden" name="student_id" id="featureStudentId">
      <input type="hidden" name="featured_id" id="featureFeaturedId">
      <input type="hidden" name="action" id="featureAction" value="feature_student">
      <label for="featureAccomplishment">Accomplishment (short badge)</label>
      <input type="text" id="featureAccomplishment" name="accomplishment" maxlength="150" placeholder="e.g. Dean's Lister, Perfect attendance">
      <label for="featureNote">Highlight quote (optional)</label>
      <input type="text" id="featureNote" name="note" maxlength="255" placeholder="e.g. Top of Batch 2026">
      <label for="featureDescription">Description (optional — shown on the school's public page)</label>
      <textarea id="featureDescription" name="description" rows="4" maxlength="2000" placeholder="What makes this student stand out?"></textarea>
      <button class="btn primary" type="submit">Save</button>
    </form>
  </div>
</div>
<script>
document.addEventListener('click', function (e) {
  const btn = e.target.closest('[data-feature-trigger]');
  if (!btn) return;
  document.getElementById('featureStudentId').value = btn.getAttribute('data-student-id') || '';
  document.getElementById('featureFeaturedId').value = btn.getAttribute('data-featured-id') || '';
  document.getElementById('featureAction').value = btn.getAttribute('data-feature-action') || 'feature_student';
  document.getElementById('featureAccomplishment').value = btn.getAttribute('data-accomplishment') || '';
  document.getElementById('featureNote').value = btn.getAttribute('data-note') || '';
  document.getElementById('featureDescription').value = btn.getAttribute('data-description') || '';
  document.getElementById('featureStudentModalTitle').textContent =
    btn.getAttribute('data-feature-action') === 'update_featured_student' ? 'Edit top student' : 'Feature this student';
});
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
