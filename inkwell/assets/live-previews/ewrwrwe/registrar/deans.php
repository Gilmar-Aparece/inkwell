<?php
// Inside/dashboard page — suppress the marketing topbar (see includes/header.php).
$__hideTopbar = true;
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/schools.php';
require_once __DIR__ . '/../includes/departments.php';
require_once __DIR__ . '/../includes/billing.php';

$user = inkwell_require_role('registrar');
$school = $user['school_id'] ? inkwell_get_school($user['school_id']) : null;
if ($user['status'] !== 'active' || !$school || inkwell_registrar_dashboard_locked($user)) {
  header('Location: /registrar/dashboard.php');
  exit;
}

$notice = '';
$error = '';
$departments = inkwell_list_departments();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';

  if ($action === 'add_dean') {
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    $departmentId = (int) ($_POST['department_id'] ?? 0);
    if (!$departmentId) {
      $error = 'Pick which department this dean oversees.';
    } elseif ($password !== $confirm) {
      $error = 'Passwords do not match.';
    } else {
      $result = inkwell_registrar_create_dean(
        $user['id'],
        $school['id'],
        $_POST['name'] ?? '',
        $_POST['email'] ?? '',
        $password,
        $_POST['id_number'] ?? '',
        $_POST['course'] ?? '',
        $departmentId
      );
      if (!$result['ok']) {
        $error = $result['error'];
      } else {
        $notice = 'Dean account created — they can log in right away.';
      }
    }
  }

  if ($action === 'remove_dean') {
    inkwell_remove_school_dean($school['id'], (int) ($_POST['dean_id'] ?? 0));
    $notice = 'Dean account disabled. You can now add a replacement for that department.';
  }
}

// One row per department: the active dean there (if any) plus how many
// past (disabled) dean accounts that department has had.
$deansByDept = [];
$allDeans = inkwell_list_school_deans($school['id']);
foreach ($departments as $d) {
  $deptId = (int) $d['id'];
  $active = null;
  $past = [];
  foreach ($allDeans as $dean) {
    if ((int) ($dean['department_id'] ?? 0) !== $deptId) continue;
    if ($dean['status'] !== 'disabled' && !$active) {
      $active = $dean;
    } else {
      $past[] = $dean;
    }
  }
  $deansByDept[$deptId] = ['department' => $d, 'active' => $active, 'past' => $past];
}
// Deans created before department_id existed (or on a host without the
// column) fall outside every department bucket — show them separately so
// they aren't silently hidden.
$unassignedDeans = array_filter($allDeans, function ($d) { return empty($d['department_id']); });

$dashNavTitle = 'Registrar';
$dashNavActive = 'deans';
$dashNavItems = [
  ['key' => 'dashboard', 'group' => 'General', 'href' => '/registrar/overview.php', 'label' => 'Dashboard', 'icon' => '🏠'],
  ['key' => 'subjects', 'group' => 'Academics', 'href' => '/registrar/dashboard.php', 'label' => 'Subjects', 'icon' => '📚'],
  ['key' => 'departments', 'group' => 'Academics', 'href' => '/registrar/dashboard.php#departments', 'label' => 'Departments', 'icon' => '🏷️'],
  ['key' => 'teachers', 'group' => 'People', 'href' => '/registrar/teachers.php', 'label' => 'Teachers', 'icon' => '🧑‍🏫', 'count' => count(inkwell_list_school_teachers($school['id']))],
  ['key' => 'deans', 'group' => 'People', 'href' => '/registrar/deans.php', 'label' => 'Deans', 'icon' => '🏫', 'count' => count($allDeans)],
  ['key' => 'students', 'group' => 'People', 'href' => '/registrar/students.php', 'label' => 'Students', 'icon' => '🎓', 'count' => count(inkwell_school_students($school['id']))],
  ['key' => 'reports', 'group' => 'Reports', 'href' => '/registrar/reports.php', 'label' => 'Reports', 'icon' => '📊'],
];

$pageTitle = 'Registrar · Deans';
include __DIR__ . '/../includes/header.php';
?>
<div class="dash-shell">
  <?php include __DIR__ . '/../includes/dash_nav.php'; ?>
  <main class="admin-main">
    <div class="admin-header-row">
      <h1>Deans</h1>
      <a class="btn" href="/logout.php">Log out</a>
    </div>
    <p class="admin-sub">One dean per department — <?php echo htmlspecialchars($school['name']); ?> can have up to <?php echo count($departments); ?> active deans at once, each scoped to their own department's teachers, subjects, and exam results.</p>

    <?php if ($notice): ?><div class="exam-result pass"><?php echo htmlspecialchars($notice); ?></div><?php endif; ?>
    <?php if ($error): ?><div class="exam-result fail"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

    <?php foreach ($deansByDept as $deptId => $row): ?>
      <?php $dept = $row['department']; $activeDean = $row['active']; ?>
      <section class="admin-card">
        <h2><?php echo htmlspecialchars($dept['code']); ?> <span class="admin-sub">— <?php echo htmlspecialchars($dept['name']); ?></span></h2>

        <?php if ($activeDean): ?>
          <div class="admin-table-wrap">
            <table class="admin-table">
              <thead><tr><th>Name</th><th>Email</th><th>Dean ID</th><th>Status</th><th></th></tr></thead>
              <tbody>
                <tr>
                  <td><?php echo htmlspecialchars($activeDean['name']); ?></td>
                  <td><?php echo htmlspecialchars($activeDean['email']); ?></td>
                  <td><?php echo htmlspecialchars($activeDean['id_number'] ?? '—') ?: '—'; ?></td>
                  <td><span class="badge badge-status-<?php echo $activeDean['status']; ?>"><?php echo ucfirst($activeDean['status']); ?></span></td>
                  <td>
                    <form method="post" action="/registrar/deans.php" onsubmit="return confirm('Disable this dean account? You will be able to add a replacement for this department afterward.');">
                      <input type="hidden" name="action" value="remove_dean">
                      <input type="hidden" name="dean_id" value="<?php echo (int) $activeDean['id']; ?>">
                      <button class="btn" type="submit">Disable</button>
                    </form>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <p class="admin-sub">No active dean for <?php echo htmlspecialchars($dept['code']); ?> yet. They'll be active immediately — no separate admin approval needed, since you're vouching for them.</p>
          <form method="post" action="/registrar/deans.php" class="admin-form">
            <input type="hidden" name="action" value="add_dean">
            <input type="hidden" name="department_id" value="<?php echo (int) $deptId; ?>">
            <div class="form-grid-2">
              <div>
                <label for="d_name_<?php echo (int) $deptId; ?>">Full name</label>
                <input type="text" id="d_name_<?php echo (int) $deptId; ?>" name="name" maxlength="100" required>
              </div>
              <div>
                <label for="d_email_<?php echo (int) $deptId; ?>">Email</label>
                <input type="email" id="d_email_<?php echo (int) $deptId; ?>" name="email" maxlength="150" required>
              </div>
            </div>
            <div class="form-grid-2">
              <div>
                <label for="d_id_number_<?php echo (int) $deptId; ?>">Dean ID</label>
                <input type="text" id="d_id_number_<?php echo (int) $deptId; ?>" name="id_number" maxlength="50" placeholder="e.g. DEAN-0012">
              </div>
              <div>
                <label for="d_course_<?php echo (int) $deptId; ?>">Position (optional)</label>
                <input type="text" id="d_course_<?php echo (int) $deptId; ?>" name="course" maxlength="150" placeholder="e.g. Dean of <?php echo htmlspecialchars($dept['code']); ?>">
              </div>
            </div>
            <div class="form-grid-2">
              <div>
                <label for="d_password_<?php echo (int) $deptId; ?>">Temporary password</label>
                <input type="password" id="d_password_<?php echo (int) $deptId; ?>" name="password" minlength="8" required>
              </div>
              <div>
                <label for="d_confirm_password_<?php echo (int) $deptId; ?>">Confirm password</label>
                <input type="password" id="d_confirm_password_<?php echo (int) $deptId; ?>" name="confirm_password" minlength="8" required>
              </div>
            </div>
            <button class="btn primary" type="submit">Add dean for <?php echo htmlspecialchars($dept['code']); ?></button>
          </form>
        <?php endif; ?>

        <?php if (!empty($row['past'])): ?>
          <details style="margin-top:12px;">
            <summary class="admin-sub">Past dean accounts (<?php echo count($row['past']); ?>)</summary>
            <div class="admin-table-wrap" style="margin-top:8px;">
              <table class="admin-table">
                <thead><tr><th>Name</th><th>Email</th><th>Status</th></tr></thead>
                <tbody>
                  <?php foreach ($row['past'] as $d): ?>
                    <tr>
                      <td><?php echo htmlspecialchars($d['name']); ?></td>
                      <td><?php echo htmlspecialchars($d['email']); ?></td>
                      <td><span class="badge badge-status-<?php echo $d['status']; ?>"><?php echo ucfirst($d['status']); ?></span></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </details>
        <?php endif; ?>
      </section>
    <?php endforeach; ?>

    <?php if (!empty($unassignedDeans)): ?>
      <section class="admin-card">
        <h2>Not tied to a department</h2>
        <p class="admin-sub">These dean accounts were created before department scoping — assign them a department by removing and re-adding, or leave them as-is if you're not using department-scoped deans on this host yet.</p>
        <div class="admin-table-wrap">
          <table class="admin-table">
            <thead><tr><th>Name</th><th>Email</th><th>Status</th></tr></thead>
            <tbody>
              <?php foreach ($unassignedDeans as $d): ?>
                <tr>
                  <td><?php echo htmlspecialchars($d['name']); ?></td>
                  <td><?php echo htmlspecialchars($d['email']); ?></td>
                  <td><span class="badge badge-status-<?php echo $d['status']; ?>"><?php echo ucfirst($d['status']); ?></span></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>
    <?php endif; ?>
  </main>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
