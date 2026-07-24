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

  if ($action === 'add_teacher') {
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    if ($password !== $confirm) {
      $error = 'Passwords do not match.';
    } else {
      $result = inkwell_registrar_create_teacher(
        $user['id'],
        $school['id'],
        $_POST['name'] ?? '',
        $_POST['email'] ?? '',
        $password,
        $_POST['id_number'] ?? '',
        $_POST['course'] ?? '',
        (int) ($_POST['department_id'] ?? 0) ?: null
      );
      if (!$result['ok']) {
        $error = $result['error'];
      } else {
        $notice = 'Teacher account created — they can log in right away.';
      }
    }
  }

  if ($action === 'remove_teacher') {
    inkwell_remove_school_teacher($school['id'], (int) ($_POST['teacher_id'] ?? 0));
    $notice = 'Teacher account disabled.';
  }
}

$teachers = inkwell_list_school_teachers($school['id']);

$dashNavTitle = 'Registrar';
$dashNavActive = 'teachers';
$dashNavItems = [
  ['key' => 'dashboard', 'group' => 'General', 'href' => '/registrar/overview.php', 'label' => 'Dashboard', 'icon' => '🏠'],
  ['key' => 'subjects', 'group' => 'Academics', 'href' => '/registrar/dashboard.php', 'label' => 'Subjects', 'icon' => '📚'],
  ['key' => 'departments', 'group' => 'Academics', 'href' => '/registrar/dashboard.php#departments', 'label' => 'Departments', 'icon' => '🏷️'],
  ['key' => 'teachers', 'group' => 'People', 'href' => '/registrar/teachers.php', 'label' => 'Teachers', 'icon' => '🧑‍🏫', 'count' => count($teachers)],
  ['key' => 'deans', 'group' => 'People', 'href' => '/registrar/deans.php', 'label' => 'Deans', 'icon' => '🏫', 'count' => count(inkwell_list_school_deans($school['id']))],
  ['key' => 'students', 'group' => 'People', 'href' => '/registrar/students.php', 'label' => 'Students', 'icon' => '🎓', 'count' => count(inkwell_school_students($school['id']))],
  ['key' => 'reports', 'group' => 'Reports', 'href' => '/registrar/reports.php', 'label' => 'Reports', 'icon' => '📊'],
];

$pageTitle = 'Registrar · Teachers';
include __DIR__ . '/../includes/header.php';
?>
<div class="dash-shell">
  <?php include __DIR__ . '/../includes/dash_nav.php'; ?>
  <main class="admin-main">
    <div class="admin-header-row">
      <h1>Teachers</h1>
      <a class="btn" href="/logout.php">Log out</a>
    </div>

    <?php if ($notice): ?><div class="exam-result pass"><?php echo htmlspecialchars($notice); ?></div><?php endif; ?>
    <?php if ($error): ?><div class="exam-result fail"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

    <section class="admin-card">
      <h2>Add a teacher account</h2>
      <p class="admin-sub">Teachers you add here are active immediately — no separate admin approval needed, since you're vouching for them.</p>
      <form method="post" action="/registrar/teachers.php" class="admin-form">
        <input type="hidden" name="action" value="add_teacher">
        <div class="form-grid-2">
          <div>
            <label for="t_name">Full name</label>
            <input type="text" id="t_name" name="name" maxlength="100" required>
          </div>
          <div>
            <label for="t_email">Email</label>
            <input type="email" id="t_email" name="email" maxlength="150" required>
          </div>
        </div>
        <div class="form-grid-2">
          <div>
            <label for="t_id_number">Teacher ID</label>
            <input type="text" id="t_id_number" name="id_number" maxlength="50" placeholder="e.g. EMP-00456">
          </div>
          <div>
            <label for="t_course">Position / Subject area (optional)</label>
            <input type="text" id="t_course" name="course" maxlength="150" placeholder="e.g. Full-time faculty">
          </div>
        </div>
        <div class="form-grid-2">
          <div>
            <label for="t_department_id">Department</label>
            <select id="t_department_id" name="department_id" required>
              <option value="">Select a department…</option>
              <?php foreach ($departments as $d): ?>
                <option value="<?php echo (int) $d['id']; ?>"><?php echo htmlspecialchars($d['code']); ?> — <?php echo htmlspecialchars($d['name']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="form-grid-2">
          <div>
            <label for="t_password">Temporary password</label>
            <input type="password" id="t_password" name="password" minlength="8" required>
          </div>
          <div>
            <label for="t_confirm_password">Confirm password</label>
            <input type="password" id="t_confirm_password" name="confirm_password" minlength="8" required>
          </div>
        </div>
        <button class="btn primary" type="submit">Add teacher</button>
      </form>
    </section>

    <section class="admin-card">
      <h2>Teachers at <?php echo htmlspecialchars($school['name']); ?> (<?php echo count($teachers); ?>)</h2>
      <?php if (empty($teachers)): ?>
        <p class="admin-sub">No teachers added yet.</p>
      <?php else: ?>
        <div class="search-filter">
          <input type="search" class="search-filter-input" data-filter-target="#registrarTeachersTable" placeholder="Search by name, email, or department...">
        </div>
        <div class="admin-table-wrap">
          <table class="admin-table" id="registrarTeachersTable">
            <thead><tr><th>Name</th><th>Email</th><th>Department</th><th>Status</th><th></th></tr></thead>
            <tbody>
              <?php
                $deptById = [];
                foreach ($departments as $d) $deptById[(int) $d['id']] = $d['code'];
              ?>
              <?php foreach ($teachers as $t): ?>
                <tr data-filter-row>
                  <td><?php echo htmlspecialchars($t['name']); ?></td>
                  <td><?php echo htmlspecialchars($t['email']); ?></td>
                  <td><?php echo htmlspecialchars($deptById[(int) ($t['department_id'] ?? 0)] ?? '—'); ?></td>
                  <td><span class="badge badge-status-<?php echo $t['status']; ?>"><?php echo ucfirst($t['status']); ?></span></td>
                  <td>
                    <?php if ($t['status'] !== 'disabled'): ?>
                      <form method="post" action="/registrar/teachers.php" style="display:inline;" onsubmit="return confirm('Disable this teacher account?');">
                        <input type="hidden" name="action" value="remove_teacher">
                        <input type="hidden" name="teacher_id" value="<?php echo (int) $t['id']; ?>">
                        <button class="btn" type="submit">Disable</button>
                      </form>
                    <?php else: ?>
                      <span class="admin-sub">—</span>
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
<?php include __DIR__ . '/../includes/footer.php'; ?>
