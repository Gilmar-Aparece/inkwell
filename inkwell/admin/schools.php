<?php
// Inside/dashboard page — suppress the marketing topbar (see includes/header.php).
$__hideTopbar = true;
require_once __DIR__ . '/../includes/store.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/schools.php';
require_once __DIR__ . '/../includes/students.php';
require_once __DIR__ . '/../includes/billing.php';
require_once __DIR__ . '/../includes/lesson_progress.php';
inkwell_require_admin();
$me = inkwell_current_user();

$notice = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';

  if ($action === 'create_school') {
    $result = inkwell_create_school($_POST['school_name'] ?? '', $_POST['mission'] ?? '', 'logo');
    if (!$result['ok']) {
      $error = $result['error'];
    } else {
      $schoolId = $result['id'];
      $notice = 'School created.';

      $regName = trim($_POST['registrar_name'] ?? '');
      $regEmail = trim($_POST['registrar_email'] ?? '');
      // Registrar fields are optional — only attempt account creation if at least the name was filled in.
      if ($regName !== '') {
        $regResult = inkwell_admin_create_registrar(
          $me['id'],
          $schoolId,
          $regName,
          $regEmail,
          $_POST['registrar_password'] ?? '',
          $_POST['registrar_id_number'] ?? '',
          $_POST['registrar_course'] ?? ''
        );
        if (!$regResult['ok']) {
          $notice = 'School created, but the Registrar account could not be created: ' . $regResult['error'] . ' You can add one from the school\'s page instead.';
        } else {
          $notice = 'School and Registrar account created — ' . $regName . ' can log in right away with the email/password you set.';
        }
      } else {
        $notice .= ' Once a Registrar for it is approved, they can add Teacher and Dean accounts.';
      }
    }
  }

  if ($action === 'update_school') {
    $schoolId = (int) ($_POST['school_id'] ?? 0);
    $hasNewFile = !empty($_FILES['logo']['name']);
    $result = inkwell_update_school($schoolId, $_POST['school_name'] ?? '', $_POST['mission'] ?? '', $hasNewFile ? 'logo' : null);
    if (!$result['ok']) {
      $error = $result['error'];
    } else {
      $notice = 'School profile updated.';
    }
  }
}

$schoolsOverview = inkwell_list_schools_with_stats();

$dashNavTitle = 'Admin';
$dashNavActive = 'schools';
$dashNavItems = [
  ['key' => 'dashboard', 'group' => 'General', 'href' => '/admin/dashboard.php', 'label' => 'Dashboard', 'icon' => '🏠'],
  ['key' => 'settings', 'group' => 'General', 'href' => '/admin/index.php', 'label' => 'Settings', 'icon' => '⚙️'],
  ['key' => 'exams', 'group' => 'Academics', 'href' => '/admin/exams.php', 'label' => 'Exams', 'icon' => '🗂'],
  ['key' => 'lessons', 'group' => 'Academics', 'href' => '/admin/lessons.php', 'label' => 'Lessons', 'icon' => '📘'],
  ['key' => 'teachers', 'group' => 'People', 'href' => '/admin/teachers.php', 'label' => 'Teachers', 'icon' => '🧑‍🏫', 'count' => count(inkwell_list_teachers())],
  ['key' => 'deans', 'group' => 'People', 'href' => '/admin/deans.php', 'label' => 'Deans', 'icon' => '🎓', 'count' => count(inkwell_list_deans())],
  ['key' => 'registrars', 'group' => 'People', 'href' => '/admin/registrars.php', 'label' => 'Registrars', 'icon' => '🗂️', 'count' => count(inkwell_list_registrars())],
  ['key' => 'admins', 'group' => 'People', 'href' => '/admin/admins.php', 'label' => 'Admins', 'icon' => '🛡️', 'count' => count(inkwell_list_admins())],
  ['key' => 'students', 'group' => 'People', 'href' => '/admin/students.php', 'label' => 'Students', 'icon' => '🧑‍🎓', 'count' => count(inkwell_list_all_students())],
  ['key' => 'schools', 'group' => 'Schools & Recognition', 'href' => '/admin/schools.php', 'label' => 'Schools', 'icon' => '🏫', 'count' => count($schoolsOverview)],
  ['key' => 'certificates', 'group' => 'Schools & Recognition', 'href' => '/admin/certificates.php', 'label' => 'Certificates', 'icon' => '📜'],
  ['key' => 'top-learners', 'group' => 'Schools & Recognition', 'href' => '/admin/top-learners.php', 'label' => 'Top learners', 'icon' => '⭐', 'count' => count(inkwell_top_learner_ids())],
  ['key' => 'lesson-progress', 'group' => 'Schools & Recognition', 'href' => '/admin/lesson-progress.php', 'label' => 'Lesson progress', 'icon' => '📈', 'count' => count(inkwell_lesson_progress_overview())],
  ['key' => 'billing-dashboard', 'group' => 'Billing', 'href' => '/admin/billing-dashboard.php', 'label' => 'Dashboard', 'icon' => '📊'],
  ['key' => 'pricing', 'group' => 'Billing', 'href' => '/admin/pricing.php', 'label' => 'Pricing', 'icon' => '💳', 'count' => count(inkwell_list_plans())],
  ['key' => 'payment-methods', 'group' => 'Billing', 'href' => '/admin/payment-methods.php', 'label' => 'Payment methods', 'icon' => '🏦'],
  ['key' => 'payments', 'group' => 'Billing', 'href' => '/admin/payments.php', 'label' => 'Payments', 'icon' => '🧾', 'count' => inkwell_count_pending_payment_submissions()],
];

$pageTitle = 'Admin · Schools';
include __DIR__ . '/../includes/header.php';
?>
<div class="dash-shell">
  <?php include __DIR__ . '/../includes/dash_nav.php'; ?>
  <main class="admin-main">
    <div class="admin-header-row">
      <h1>Schools</h1>
      <a class="btn" href="/admin/logout.php">Log out</a>
    </div>

    <?php if ($notice): ?><div class="exam-result pass"><?php echo htmlspecialchars($notice); ?></div><?php endif; ?>
    <?php if ($error): ?><div class="exam-result fail"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

    <section class="admin-card glass-card">
      <h2>Create a school</h2>
      <p class="admin-sub">Only an admin can create a school. Optionally add its first Registrar in the same step — they'll be able to log in right away, no separate approval needed.</p>
      <form method="post" action="/admin/schools.php" enctype="multipart/form-data" class="admin-form">
        <input type="hidden" name="action" value="create_school">
        <label for="school_name">School name</label>
        <input type="text" id="school_name" name="school_name" maxlength="150" required>
        <label for="mission">Mission statement (optional)</label>
        <textarea id="mission" name="mission" rows="3" maxlength="600" placeholder="What this school stands for — shown to visitors on the homepage."></textarea>
        <label for="logo">Logo (PNG, JPG, or WEBP — under 2MB)</label>
        <input type="file" id="logo" name="logo" accept="image/png,image/jpeg,image/webp">

        <div class="create-school-registrar-toggle">
          <label class="admin-checkbox-row">
            <input type="checkbox" id="addRegistrarToggle">
            <span>Add a Registrar for this school now</span>
          </label>
        </div>

        <div id="registrarFields" class="create-school-registrar-fields" style="display:none;">
          <div class="form-grid-2">
            <div>
              <label for="registrar_name">Registrar full name</label>
              <input type="text" id="registrar_name" name="registrar_name" maxlength="100">
            </div>
            <div>
              <label for="registrar_email">Registrar email</label>
              <input type="email" id="registrar_email" name="registrar_email" maxlength="150">
            </div>
          </div>
          <div class="form-grid-2">
            <div>
              <label for="registrar_id_number">Registrar ID</label>
              <input type="text" id="registrar_id_number" name="registrar_id_number" maxlength="50" placeholder="e.g. REG-0012">
            </div>
            <div>
              <label for="registrar_course">Office / Department</label>
              <input type="text" id="registrar_course" name="registrar_course" maxlength="150" placeholder="e.g. Registrar's Office">
            </div>
          </div>
          <label for="registrar_password">Registrar password</label>
          <input type="password" id="registrar_password" name="registrar_password" minlength="8" placeholder="At least 8 characters">
          <p class="admin-sub">Share this password with them directly — it won't be shown again after you submit.</p>
        </div>

        <button class="btn primary" type="submit">Create school</button>
      </form>
    </section>

    <section class="admin-card">
      <h2>Schools (<?php echo count($schoolsOverview); ?>)</h2>
      <p class="admin-sub">Who the dean is (if one's been added yet), how many teachers and students it has, and how many certificates it has issued.</p>
      <?php if (empty($schoolsOverview)): ?>
        <p class="admin-sub">No schools have been set up yet — create one above.</p>
      <?php else: ?>
        <div class="search-filter">
          <input type="search" class="search-filter-input" data-filter-target="#schoolsOverviewTable" placeholder="Search by school or dean name...">
        </div>
        <div class="admin-table-wrap">
          <table class="admin-table" id="schoolsOverviewTable">
            <thead><tr><th>School</th><th>Dean</th><th>Teachers</th><th>Students</th><th>Certificates</th><th>Created</th><th></th></tr></thead>
            <tbody>
              <?php foreach ($schoolsOverview as $sch): ?>
                <tr data-filter-row>
                  <td><a class="school-link" href="/school.php?id=<?php echo (int) $sch['id']; ?>"><?php echo htmlspecialchars($sch['name']); ?> →</a></td>
                  <td><?php echo $sch['dean_name'] ? htmlspecialchars($sch['dean_name']) . ' <span class="admin-sub">(' . htmlspecialchars($sch['dean_email']) . ')</span>' : '<span class="admin-sub">No dean yet</span>'; ?></td>
                  <td><?php echo (int) $sch['teacher_count']; ?></td>
                  <td><?php echo (int) $sch['student_count']; ?></td>
                  <td><?php echo (int) $sch['certificate_count']; ?></td>
                  <td><?php echo htmlspecialchars(date('M j, Y', strtotime($sch['created_at']))); ?></td>
                  <td>
                    <a class="btn btn-sm" href="/school.php?id=<?php echo (int) $sch['id']; ?>">View →</a>
                    <button type="button" class="btn btn-sm" data-edit-school-trigger data-modal-open="editSchoolModal"
                      data-school-id="<?php echo (int) $sch['id']; ?>"
                      data-name="<?php echo htmlspecialchars($sch['name']); ?>"
                      data-mission="<?php echo htmlspecialchars($sch['mission'] ?? ''); ?>">Edit</button>
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

<div class="modal-backdrop" id="editSchoolModal">
  <div class="modal">
    <div class="modal-head">
      <h2>Edit school — <span id="editSchoolName"></span></h2>
      <button type="button" data-modal-close aria-label="Close">✕</button>
    </div>
    <form method="post" action="/admin/schools.php" enctype="multipart/form-data" class="admin-form">
      <input type="hidden" name="action" value="update_school">
      <input type="hidden" name="school_id" id="editSchoolId">
      <label for="editSchoolNameInput">School name</label>
      <input type="text" id="editSchoolNameInput" name="school_name" maxlength="150" required>
      <label for="editSchoolMission">Mission statement (optional)</label>
      <textarea id="editSchoolMission" name="mission" rows="3" maxlength="600"></textarea>
      <label for="editSchoolLogo">Replace logo (leave empty to keep the current one)</label>
      <input type="file" id="editSchoolLogo" name="logo" accept="image/png,image/jpeg,image/webp">
      <button class="btn primary" type="submit">Save changes</button>
    </form>
  </div>
</div>
<script>
document.addEventListener('click', function (e) {
  const btn = e.target.closest('[data-edit-school-trigger]');
  if (!btn) return;
  document.getElementById('editSchoolId').value = btn.getAttribute('data-school-id') || '';
  document.getElementById('editSchoolName').textContent = btn.getAttribute('data-name') || '';
  document.getElementById('editSchoolNameInput').value = btn.getAttribute('data-name') || '';
  document.getElementById('editSchoolMission').value = btn.getAttribute('data-mission') || '';
});

(function () {
  const toggle = document.getElementById('addRegistrarToggle');
  const panel = document.getElementById('registrarFields');
  if (!toggle || !panel) return;
  const requiredWhenOn = panel.querySelectorAll('#registrar_name, #registrar_email, #registrar_password');
  toggle.addEventListener('change', function () {
    panel.style.display = toggle.checked ? '' : 'none';
    requiredWhenOn.forEach((inp) => { inp.required = toggle.checked; });
  });
})();
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
