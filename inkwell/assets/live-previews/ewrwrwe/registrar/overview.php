<?php
// Inside/dashboard page — suppress the marketing topbar (see includes/header.php).
$__hideTopbar = true;
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/schools.php';
require_once __DIR__ . '/../includes/exams_db.php';
require_once __DIR__ . '/../includes/departments.php';
require_once __DIR__ . '/../includes/billing.php';

$user = inkwell_require_role('registrar');
$school = $user['school_id'] ? inkwell_get_school($user['school_id']) : null;

if ($user['status'] !== 'active' || !$school || inkwell_registrar_dashboard_locked($user)) {
  header('Location: /registrar/dashboard.php');
  exit;
}

$subjectColsOk = inkwell_ensure_subject_code_units_columns();
$regColsOk = inkwell_ensure_subject_registrar_columns();
$deptColsOk = inkwell_ensure_department_columns();
$departments = inkwell_list_departments();
$teachers = inkwell_list_school_teachers($school['id'], true);
$subjects = inkwell_registrar_subjects($user['id']);
$joinRequests = inkwell_registrar_pending_join_requests($school['id']);

$dashNavTitle = 'Registrar';
$dashNavActive = 'dashboard';
$dashNavItems = [
  ['key' => 'dashboard', 'group' => 'General', 'href' => '/registrar/overview.php', 'label' => 'Dashboard', 'icon' => '🏠'],
  ['key' => 'subjects', 'group' => 'Academics', 'href' => '/registrar/dashboard.php', 'label' => 'Subjects', 'icon' => '📚', 'count' => count($subjects)],
  ['key' => 'approvals', 'group' => 'Academics', 'href' => '/registrar/dashboard.php#approvals', 'label' => 'Approvals', 'icon' => '✅', 'count' => count($joinRequests)],
  ['key' => 'departments', 'group' => 'Academics', 'href' => '/registrar/dashboard.php#departments', 'label' => 'Departments', 'icon' => '🏷️', 'count' => count($departments)],
  ['key' => 'teachers', 'group' => 'People', 'href' => '/registrar/teachers.php', 'label' => 'Teachers', 'icon' => '🧑‍🏫', 'count' => count($teachers)],
  ['key' => 'deans', 'group' => 'People', 'href' => '/registrar/deans.php', 'label' => 'Deans', 'icon' => '🏫', 'count' => count(inkwell_list_school_deans($school['id']))],
  ['key' => 'students', 'group' => 'People', 'href' => '/registrar/students.php', 'label' => 'Students', 'icon' => '🎓', 'count' => count(inkwell_school_students($school['id']))],
  ['key' => 'reports', 'group' => 'Reports', 'href' => '/registrar/reports.php', 'label' => 'Reports', 'icon' => '📊'],
];

$dashOverviewItems = array_values(array_filter($dashNavItems, function ($it) { return $it['key'] !== 'dashboard'; }));
$dashOverviewGreeting = 'Hi ' . $user['name'] . ' — here\'s ' . htmlspecialchars($school['name']) . ' at a glance.';

$pageTitle = 'Registrar · Dashboard';
include __DIR__ . '/../includes/header.php';
?>
<div class="dash-shell">
  <?php include __DIR__ . '/../includes/dash_nav.php'; ?>
  <main class="admin-main">
    <div class="admin-header-row">
      <h1>Dashboard</h1>
      <a class="btn" href="/logout.php">Log out</a>
    </div>
    <?php include __DIR__ . '/../includes/dash_overview.php'; ?>
  </main>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
