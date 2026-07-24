<?php
// Inside/dashboard page — suppress the marketing topbar (see includes/header.php).
$__hideTopbar = true;
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/schools.php';
require_once __DIR__ . '/../includes/departments.php';

$user = inkwell_require_role('dean');
$school = inkwell_get_school_by_dean($user['id']);
if ($user['status'] !== 'active' || !$school) {
  header('Location: /dean/dashboard.php');
  exit;
}

$myDepartment = !empty($user['department_id']) ? inkwell_get_department($user['department_id']) : null;
$teachers = inkwell_list_school_teachers($school['id'], false, $user['department_id'] ?? null);

$dashNavTitle = 'Dean';
$dashNavActive = 'teachers';
$dashNavItems = [
  ['key' => 'dashboard', 'group' => 'General', 'href' => '/dean/overview.php', 'label' => 'Dashboard', 'icon' => '🏠'],
  ['key' => 'school', 'group' => 'School', 'href' => '/dean/dashboard.php', 'label' => 'School profile', 'icon' => '🏫'],
  ['key' => 'signer', 'group' => 'Certificates', 'href' => '/dean/signer.php', 'label' => 'Certificate signer', 'icon' => '✍️'],
  ['key' => 'certificates', 'group' => 'Certificates', 'href' => '/dean/certificates.php', 'label' => 'Issue certificate', 'icon' => '📜'],
  ['key' => 'teachers', 'group' => 'People', 'href' => '/dean/teachers.php', 'label' => 'Teachers', 'icon' => '🧑‍🏫', 'count' => count($teachers)],
  ['key' => 'students', 'group' => 'People', 'href' => '/dean/students.php', 'label' => 'Students', 'icon' => '🎓', 'count' => count(inkwell_school_students($school['id']))],
  ['key' => 'events', 'group' => 'Activity', 'href' => '/dean/events.php', 'label' => 'Events', 'icon' => '📣'],
];

$pageTitle = 'Dean · Teachers';
include __DIR__ . '/../includes/header.php';
?>
<div class="dash-shell">
  <?php include __DIR__ . '/../includes/dash_nav.php'; ?>
  <main class="admin-main">
    <div class="admin-header-row">
      <h1>Teachers</h1>
      <a class="btn" href="/logout.php">Log out</a>
    </div>

    <section class="admin-card">
      <h2>Teachers at <?php echo htmlspecialchars($school['name']); ?><?php echo $myDepartment ? ' — ' . htmlspecialchars($myDepartment['code']) : ''; ?> (<?php echo count($teachers); ?>)</h2>
      <p class="admin-sub">View-only<?php echo $myDepartment ? ', scoped to your department (' . htmlspecialchars($myDepartment['code']) . ')' : ''; ?> — your Registrar adds and removes teacher accounts for this school.</p>
      <?php if (empty($teachers)): ?>
        <p class="admin-sub">No teachers added yet.</p>
      <?php else: ?>
        <div class="search-filter">
          <input type="search" class="search-filter-input" data-filter-target="#deanTeachersTable" placeholder="Search by name or email...">
        </div>
        <div class="admin-table-wrap">
          <table class="admin-table" id="deanTeachersTable">
            <thead><tr><th>Name</th><th>Email</th><th>Status</th></tr></thead>
            <tbody>
              <?php foreach ($teachers as $t): ?>
                <tr data-filter-row>
                  <td><?php echo htmlspecialchars($t['name']); ?></td>
                  <td><?php echo htmlspecialchars($t['email']); ?></td>
                  <td><span class="badge badge-status-<?php echo $t['status']; ?>"><?php echo ucfirst($t['status']); ?></span></td>
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
