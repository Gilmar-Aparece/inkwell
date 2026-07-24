<?php
// Inside/dashboard page — suppress the marketing topbar (see includes/header.php).
$__hideTopbar = true;
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/schools.php';
require_once __DIR__ . '/../includes/students.php';
require_once __DIR__ . '/../includes/departments.php';

$user = inkwell_require_role('dean');
$school = inkwell_get_school_by_dean($user['id']);

$dashNavTitle = 'Dean';
$dashNavActive = 'dashboard';
$dashNavItems = [
  ['key' => 'dashboard', 'group' => 'General', 'href' => '/dean/overview.php', 'label' => 'Dashboard', 'icon' => '🏠'],
  ['key' => 'school', 'group' => 'School', 'href' => '/dean/dashboard.php', 'label' => 'School profile', 'icon' => '🏫'],
  ['key' => 'signer', 'group' => 'Certificates', 'href' => '/dean/signer.php', 'label' => 'Certificate signer', 'icon' => '✍️'],
  ['key' => 'certificates', 'group' => 'Certificates', 'href' => '/dean/certificates.php', 'label' => 'Issue certificate', 'icon' => '📜'],
  ['key' => 'teachers', 'group' => 'People', 'href' => '/dean/teachers.php', 'label' => 'Teachers', 'icon' => '🧑‍🏫', 'count' => $school ? count(inkwell_list_school_teachers($school['id'], false, $user['department_id'] ?? null)) : 0],
  ['key' => 'students', 'group' => 'People', 'href' => '/dean/students.php', 'label' => 'Students', 'icon' => '🎓', 'count' => $school ? count(inkwell_school_students($school['id'])) : 0],
  ['key' => 'events', 'group' => 'Activity', 'href' => '/dean/events.php', 'label' => 'Events', 'icon' => '📣'],
];

$pageTitle = 'Dean · Dashboard';
include __DIR__ . '/../includes/header.php';
?>
<?php if ($user['status'] !== 'active' || !$school): ?>
  <main class="admin-main">
    <div class="admin-header-row">
      <h1>Dean dashboard</h1>
      <a class="btn" href="/logout.php">Log out</a>
    </div>
    <p class="admin-sub">Your account is waiting on admin approval, or isn't linked to a school yet.</p>
  </main>
<?php else: ?>
  <div class="dash-shell">
    <?php include __DIR__ . '/../includes/dash_nav.php'; ?>
    <main class="admin-main">
      <div class="admin-header-row">
        <h1>Dashboard</h1>
        <a class="btn" href="/logout.php">Log out</a>
      </div>
      <?php
        $dashOverviewItems = array_values(array_filter($dashNavItems, function ($it) { return $it['key'] !== 'dashboard'; }));
        $dashOverviewGreeting = 'Hi ' . $user['name'] . ' — here\'s ' . htmlspecialchars($school['name']) . ' at a glance.';
        include __DIR__ . '/../includes/dash_overview.php';
      ?>
    </main>
  </div>
<?php endif; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
