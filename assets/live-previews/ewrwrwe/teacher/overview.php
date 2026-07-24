<?php
// Inside/dashboard page — suppress the marketing topbar (see includes/header.php).
$__hideTopbar = true;
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/exams_db.php';
require_once __DIR__ . '/../includes/events.php';
require_once __DIR__ . '/../includes/students.php';

$user = inkwell_require_role('teacher');
$isApproved = $user['status'] === 'active';

if (!$isApproved) {
  header('Location: /teacher/dashboard.php');
  exit;
}

$pendingCount = count(inkwell_teacher_pending_attempts($user['id']));
$eventCount = count(inkwell_events_by_author($user['id']));
$studentCount = count(inkwell_teacher_students($user['id']));

$dashNavTitle = 'Teacher';
$dashNavActive = 'dashboard';
$dashNavItems = [
  ['key' => 'dashboard', 'group' => 'General', 'href' => '/teacher/overview.php', 'label' => 'Dashboard', 'icon' => '🏠'],
  ['key' => 'subjects', 'group' => 'Teaching', 'href' => '/teacher/dashboard.php', 'label' => 'Subjects', 'icon' => '🗂'],
  ['key' => 'sections', 'group' => 'Teaching', 'href' => '/teacher/sections.php', 'label' => 'Sections', 'icon' => '👥'],
  ['key' => 'grade', 'group' => 'Teaching', 'href' => '/teacher/grade.php', 'label' => 'Grade', 'icon' => '🖊', 'count' => $pendingCount],
  ['key' => 'students', 'group' => 'People', 'href' => '/teacher/students.php', 'label' => 'Students', 'icon' => '🎓', 'count' => $studentCount],
  ['key' => 'certificates', 'group' => 'People', 'href' => '/teacher/certificates.php', 'label' => 'Certificates', 'icon' => '📜'],
  ['key' => 'events', 'group' => 'Activity', 'href' => '/teacher/events.php', 'label' => 'Events', 'icon' => '📣', 'count' => $eventCount],
];

$dashOverviewItems = array_values(array_filter($dashNavItems, function ($it) { return $it['key'] !== 'dashboard'; }));
$dashOverviewGreeting = 'Hi ' . $user['name'] . ' — here\'s your teaching workspace at a glance.';

$pageTitle = 'Teacher · Dashboard';
include __DIR__ . '/../includes/header.php';
?>
<div class="dash-shell">
  <?php include __DIR__ . '/../includes/dash_nav.php'; ?>
  <main class="admin-main">
    <div class="admin-header-row">
      <h1>Dashboard</h1>
      <a class="btn" href="/account.php">My account</a>
    </div>
    <?php include __DIR__ . '/../includes/dash_overview.php'; ?>
  </main>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
