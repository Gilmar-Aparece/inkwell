<?php
// Inside/dashboard page — suppress the marketing topbar (see includes/header.php).
$__hideTopbar = true;
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/schools.php';
require_once __DIR__ . '/../includes/students.php';

$user = inkwell_require_role('dean');
$school = inkwell_get_school_by_dean($user['id']);
if ($user['status'] !== 'active' || !$school) {
  header('Location: /dean/dashboard.php');
  exit;
}

$notice = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';
  $studentId = (int) ($_POST['student_id'] ?? 0);

  if ($action === 'feature_student') {
    $result = inkwell_add_featured_student($school['id'], $studentId, $user['id'], $_POST['note'] ?? '', $_POST['description'] ?? '', $_POST['accomplishment'] ?? '');
    $notice = $result['ok'] ? 'Added to top students.' : $result['error'];
  }

  if ($action === 'update_featured_student') {
    inkwell_update_featured_student((int) ($_POST['featured_id'] ?? 0), $school['id'], $_POST['note'] ?? '', $_POST['description'] ?? '', $_POST['accomplishment'] ?? '');
    $notice = 'Top student updated.';
  }

  if ($action === 'unfeature_student') {
    inkwell_remove_featured_student((int) ($_POST['featured_id'] ?? 0), $school['id']);
    $notice = 'Removed from top students.';
  }
}

$students = inkwell_school_students($school['id']);
$featuredByStudent = [];
foreach (inkwell_featured_students($school['id']) as $f) { $featuredByStudent[$f['student_id']] = $f; }

$dashNavTitle = 'Dean';
$dashNavActive = 'students';
$dashNavItems = [
  ['key' => 'dashboard', 'group' => 'General', 'href' => '/dean/overview.php', 'label' => 'Dashboard', 'icon' => '🏠'],
  ['key' => 'school', 'group' => 'School', 'href' => '/dean/dashboard.php', 'label' => 'School profile', 'icon' => '🏫'],
  ['key' => 'signer', 'group' => 'Certificates', 'href' => '/dean/signer.php', 'label' => 'Certificate signer', 'icon' => '✍️'],
  ['key' => 'certificates', 'group' => 'Certificates', 'href' => '/dean/certificates.php', 'label' => 'Issue certificate', 'icon' => '📜'],
  ['key' => 'teachers', 'group' => 'People', 'href' => '/dean/teachers.php', 'label' => 'Teachers', 'icon' => '🧑‍🏫', 'count' => count(inkwell_list_school_teachers($school['id'], false, $user['department_id'] ?? null))],
  ['key' => 'students', 'group' => 'People', 'href' => '/dean/students.php', 'label' => 'Students', 'icon' => '🎓', 'count' => count($students)],
  ['key' => 'events', 'group' => 'Activity', 'href' => '/dean/events.php', 'label' => 'Events', 'icon' => '📣'],
];

$pageTitle = 'Dean · Students';
include __DIR__ . '/../includes/header.php';
?>
<div class="dash-shell">
  <?php include __DIR__ . '/../includes/dash_nav.php'; ?>
  <main class="admin-main">
    <div class="admin-header-row">
      <h1>Students</h1>
      <a class="btn" href="/logout.php">Log out</a>
    </div>

    <section class="admin-card">
      <h2>Students at <?php echo htmlspecialchars($school['name']); ?> (<?php echo count($students); ?>)</h2>
      <p class="admin-sub">Students who picked this school when they registered — a read-only view so you can monitor enrollment. Students self-register and aren't added by the dean.</p>
      <?php if ($notice): ?><div class="exam-result pass"><?php echo htmlspecialchars($notice); ?></div><?php endif; ?>
      <?php if (empty($students)): ?>
        <p class="admin-sub">No students have picked this school yet.</p>
      <?php else: ?>
        <div class="search-filter">
          <input type="search" class="search-filter-input" data-filter-target="#deanStudentsTable" placeholder="Search by name, email, or course...">
        </div>
        <div class="admin-table-wrap">
          <table class="admin-table" id="deanStudentsTable">
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
                      <form method="post" action="/dean/students.php" style="display:inline;">
                        <input type="hidden" name="student_id" value="<?php echo (int) $st['id']; ?>">
                        <input type="hidden" name="action" value="unfeature_student">
                        <input type="hidden" name="featured_id" value="<?php echo (int) $f['id']; ?>">
                        <button type="submit" class="feature-star-btn" title="Remove from top students">✕</button>
                      </form>
                    <?php else: ?>
                      <button type="button" class="feature-star-btn" title="Add to top students"
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
    <form method="post" action="/dean/students.php" class="admin-form">
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
