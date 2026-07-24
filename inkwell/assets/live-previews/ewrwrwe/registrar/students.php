<?php
// Inside/dashboard page — suppress the marketing topbar (see includes/header.php).
$__hideTopbar = true;
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/schools.php';
require_once __DIR__ . '/../includes/students.php';
require_once __DIR__ . '/../includes/billing.php';

$user = inkwell_require_role('registrar');
$school = $user['school_id'] ? inkwell_get_school($user['school_id']) : null;

if ($user['status'] !== 'active' || !$school || inkwell_registrar_dashboard_locked($user)) {
  header('Location: /registrar/dashboard.php');
  exit;
}

$notice = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';
  $studentId = (int) ($_POST['student_id'] ?? 0);

  if ($action === 'update_student') {
    $result = inkwell_update_student_info(
      $studentId,
      $school['id'],
      $_POST['name'] ?? '',
      $_POST['email'] ?? '',
      $_POST['id_number'] ?? '',
      $_POST['course'] ?? ''
    );
    if ($result['ok']) {
      $notice = 'Student info updated.';
    } else {
      $error = $result['error'];
    }
  }
}

$students = inkwell_school_students($school['id']);

$dashNavTitle = 'Registrar';
$dashNavActive = 'students';
$dashNavItems = [
  ['key' => 'dashboard', 'group' => 'General', 'href' => '/registrar/overview.php', 'label' => 'Dashboard', 'icon' => '🏠'],
  ['key' => 'subjects', 'group' => 'Academics', 'href' => '/registrar/dashboard.php', 'label' => 'Subjects', 'icon' => '📚'],
  ['key' => 'departments', 'group' => 'Academics', 'href' => '/registrar/dashboard.php#departments', 'label' => 'Departments', 'icon' => '🏷️'],
  ['key' => 'teachers', 'group' => 'People', 'href' => '/registrar/teachers.php', 'label' => 'Teachers', 'icon' => '🧑‍🏫', 'count' => count(inkwell_list_school_teachers($school['id']))],
  ['key' => 'deans', 'group' => 'People', 'href' => '/registrar/deans.php', 'label' => 'Deans', 'icon' => '🏫', 'count' => count(inkwell_list_school_deans($school['id']))],
  ['key' => 'students', 'group' => 'People', 'href' => '/registrar/students.php', 'label' => 'Students', 'icon' => '🎓', 'count' => count($students)],
  ['key' => 'reports', 'group' => 'Reports', 'href' => '/registrar/reports.php', 'label' => 'Reports', 'icon' => '📊'],
];

$pageTitle = 'Registrar · Students';
include __DIR__ . '/../includes/header.php';
?>
<div class="dash-shell">
  <?php include __DIR__ . '/../includes/dash_nav.php'; ?>
  <main class="admin-main">
    <div class="admin-header-row">
      <h1>Students</h1>
      <a class="btn" href="/logout.php">Log out</a>
    </div>

    <?php if ($notice): ?><div class="exam-result pass"><?php echo htmlspecialchars($notice); ?></div><?php endif; ?>
    <?php if ($error): ?><div class="exam-result fail"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

    <section class="admin-card">
      <h2>Students at <?php echo htmlspecialchars($school['name']); ?> (<?php echo count($students); ?>)</h2>
      <p class="admin-sub">Students who picked this school when they registered. Click a name to view their profile, or Edit to update their info.</p>
      <?php if (empty($students)): ?>
        <p class="admin-sub">No students have picked this school yet.</p>
      <?php else: ?>
        <div class="search-filter">
          <input type="search" class="search-filter-input" data-filter-target="#registrarStudentsTable" placeholder="Search by name, email, ID, or course...">
        </div>
        <div class="admin-table-wrap">
          <table class="admin-table" id="registrarStudentsTable" data-paginate="20">
            <thead><tr><th>Name</th><th>Email</th><th>Student ID</th><th>Course</th><th>Registered</th><th></th></tr></thead>
            <tbody>
              <?php foreach ($students as $st): ?>
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
                  <td><?php echo htmlspecialchars($st['id_number'] ?? '—') ?: '—'; ?></td>
                  <td><?php echo htmlspecialchars($st['course'] ?? '—') ?: '—'; ?></td>
                  <td><?php echo htmlspecialchars(date('M j, Y', strtotime($st['created_at']))); ?></td>
                  <td>
                    <button type="button" class="btn" data-edit-student-trigger data-modal-open="editStudentModal"
                      data-student-id="<?php echo (int) $st['id']; ?>"
                      data-name="<?php echo htmlspecialchars($st['name']); ?>"
                      data-email="<?php echo htmlspecialchars($st['email']); ?>"
                      data-id-number="<?php echo htmlspecialchars($st['id_number'] ?? ''); ?>"
                      data-course="<?php echo htmlspecialchars($st['course'] ?? ''); ?>">Edit</button>
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

<div class="modal-backdrop" id="editStudentModal">
  <div class="modal">
    <div class="modal-head">
      <h2>Edit student — <span id="editStudentName"></span></h2>
      <button type="button" data-modal-close aria-label="Close">✕</button>
    </div>
    <form method="post" action="/registrar/students.php" class="admin-form">
      <input type="hidden" name="action" value="update_student">
      <input type="hidden" name="student_id" id="editStudentId">
      <label for="editName">Name</label>
      <input type="text" id="editName" name="name" maxlength="150" required>
      <label for="editEmail">Email</label>
      <input type="email" id="editEmail" name="email" maxlength="190" required>
      <div class="form-grid-2">
        <div>
          <label for="editIdNumber">Student ID</label>
          <input type="text" id="editIdNumber" name="id_number" maxlength="50">
        </div>
        <div>
          <label for="editCourse">Course / Program</label>
          <input type="text" id="editCourse" name="course" maxlength="150">
        </div>
      </div>
      <button class="btn primary" type="submit">Save changes</button>
    </form>
  </div>
</div>
<script>
document.addEventListener('click', function (e) {
  const btn = e.target.closest('[data-edit-student-trigger]');
  if (!btn) return;
  document.getElementById('editStudentId').value = btn.getAttribute('data-student-id') || '';
  document.getElementById('editStudentName').textContent = btn.getAttribute('data-name') || '';
  document.getElementById('editName').value = btn.getAttribute('data-name') || '';
  document.getElementById('editEmail').value = btn.getAttribute('data-email') || '';
  document.getElementById('editIdNumber').value = btn.getAttribute('data-id-number') || '';
  document.getElementById('editCourse').value = btn.getAttribute('data-course') || '';
});
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
