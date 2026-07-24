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

$notice = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';

  if ($action === 'approve_teacher') {
    inkwell_set_teacher_status((int) ($_POST['teacher_id'] ?? 0), 'active');
    $notice = 'Teacher approved — they can now add exams.';
  }

  if ($action === 'revoke_teacher') {
    inkwell_set_teacher_status((int) ($_POST['teacher_id'] ?? 0), 'pending');
    $notice = 'Teacher permission revoked — they can no longer add new exams.';
  }

  if ($action === 'disable_teacher') {
    inkwell_set_teacher_status((int) ($_POST['teacher_id'] ?? 0), 'disabled');
    $notice = 'Teacher account disabled.';
  }

  if ($action === 'delete_teacher') {
    $result = inkwell_delete_user((int) ($_POST['teacher_id'] ?? 0), 'teacher');
    if ($result['ok']) {
      $notice = 'Teacher account deleted.';
    } else {
      $error = $result['error'] ?? 'Could not delete that account.';
    }
  }
}

$teachers = inkwell_list_teachers();

if (($_GET['export'] ?? '') === 'csv') {
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename="teacher-records-' . date('Y-m-d') . '.csv"');
  $out = fopen('php://output', 'w');
  fputcsv($out, ['Name', 'Email', 'School', 'Status']);
  foreach ($teachers as $t) {
    $tSchool = $t['school_id'] ? inkwell_get_school($t['school_id']) : null;
    fputcsv($out, [
      $t['name'],
      $t['email'],
      $tSchool ? $tSchool['name'] : '',
      ucfirst($t['status']),
    ]);
  }
  fclose($out);
  exit;
}

$dashNavTitle = 'Admin';
$dashNavActive = 'teachers';
$dashNavItems = [
  ['key' => 'dashboard', 'group' => 'General', 'href' => '/admin/dashboard.php', 'label' => 'Dashboard', 'icon' => '🏠'],
  ['key' => 'settings', 'group' => 'General', 'href' => '/admin/index.php', 'label' => 'Settings', 'icon' => '⚙️'],
  ['key' => 'exams', 'group' => 'Academics', 'href' => '/admin/exams.php', 'label' => 'Exams', 'icon' => '🗂'],
  ['key' => 'lessons', 'group' => 'Academics', 'href' => '/admin/lessons.php', 'label' => 'Lessons', 'icon' => '📘'],
  ['key' => 'teachers', 'group' => 'People', 'href' => '/admin/teachers.php', 'label' => 'Teachers', 'icon' => '🧑‍🏫', 'count' => count($teachers)],
  ['key' => 'deans', 'group' => 'People', 'href' => '/admin/deans.php', 'label' => 'Deans', 'icon' => '🎓', 'count' => count(inkwell_list_deans())],
  ['key' => 'registrars', 'group' => 'People', 'href' => '/admin/registrars.php', 'label' => 'Registrars', 'icon' => '🗂️', 'count' => count(inkwell_list_registrars())],
  ['key' => 'admins', 'group' => 'People', 'href' => '/admin/admins.php', 'label' => 'Admins', 'icon' => '🛡️', 'count' => count(inkwell_list_admins())],
  ['key' => 'students', 'group' => 'People', 'href' => '/admin/students.php', 'label' => 'Students', 'icon' => '🧑‍🎓', 'count' => count(inkwell_list_all_students())],
  ['key' => 'schools', 'group' => 'Schools & Recognition', 'href' => '/admin/schools.php', 'label' => 'Schools', 'icon' => '🏫', 'count' => count(inkwell_list_schools_with_stats())],
  ['key' => 'certificates', 'group' => 'Schools & Recognition', 'href' => '/admin/certificates.php', 'label' => 'Certificates', 'icon' => '📜'],
  ['key' => 'top-learners', 'group' => 'Schools & Recognition', 'href' => '/admin/top-learners.php', 'label' => 'Top learners', 'icon' => '⭐', 'count' => count(inkwell_top_learner_ids())],
  ['key' => 'lesson-progress', 'group' => 'Schools & Recognition', 'href' => '/admin/lesson-progress.php', 'label' => 'Lesson progress', 'icon' => '📈', 'count' => count(inkwell_lesson_progress_overview())],
  ['key' => 'billing-dashboard', 'group' => 'Billing', 'href' => '/admin/billing-dashboard.php', 'label' => 'Dashboard', 'icon' => '📊'],
  ['key' => 'pricing', 'group' => 'Billing', 'href' => '/admin/pricing.php', 'label' => 'Pricing', 'icon' => '💳', 'count' => count(inkwell_list_plans())],
  ['key' => 'payment-methods', 'group' => 'Billing', 'href' => '/admin/payment-methods.php', 'label' => 'Payment methods', 'icon' => '🏦'],
  ['key' => 'payments', 'group' => 'Billing', 'href' => '/admin/payments.php', 'label' => 'Payments', 'icon' => '🧾', 'count' => inkwell_count_pending_payment_submissions()],
];

$pageTitle = 'Admin · Teachers';
include __DIR__ . '/../includes/header.php';
?>
<div class="dash-shell">
  <?php include __DIR__ . '/../includes/dash_nav.php'; ?>
  <main class="admin-main">
    <div class="admin-header-row">
      <h1>Teacher accounts</h1>
      <a class="btn" href="/admin/logout.php">Log out</a>
    </div>

    <?php if ($notice): ?><div class="exam-result pass"><?php echo htmlspecialchars($notice); ?></div><?php endif; ?>
    <?php if ($error): ?><div class="exam-result fail"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

    <section class="admin-card">
      <h2>Teacher accounts (<?php echo count($teachers); ?>)</h2>
      <p class="admin-sub">Teachers are created by an approved Registrar for their school now — they're active immediately. This is a monitoring view; revoke or disable an account if needed.</p>
      <?php if (empty($teachers)): ?>
        <p class="admin-sub">No teacher accounts have registered yet.</p>
      <?php else: ?>
        <div class="search-filter" style="display:flex; align-items:center; gap:10px; justify-content:space-between; flex-wrap:wrap;">
          <input type="search" class="search-filter-input" data-filter-target="#teacherAccountsTable" placeholder="Search by name or email...">
          <a class="btn" href="/admin/teachers.php?export=csv">⬇ Download CSV</a>
        </div>
        <div class="admin-table-wrap">
          <table class="admin-table" id="teacherAccountsTable">
            <thead><tr><th>Name</th><th>Email</th><th>School</th><th>Status</th><th></th></tr></thead>
            <tbody>
              <?php foreach ($teachers as $t): ?>
                <tr data-filter-row>
                  <td><?php echo htmlspecialchars($t['name']); ?></td>
                  <td><?php echo htmlspecialchars($t['email']); ?></td>
                  <td><?php $tSchool = $t['school_id'] ? inkwell_get_school($t['school_id']) : null; ?><?php echo $tSchool ? htmlspecialchars($tSchool['name']) : '<span class="admin-sub">—</span>'; ?></td>
                  <td><?php echo htmlspecialchars(ucfirst($t['status'])); ?></td>
                  <td>
                    <form method="post" action="/admin/teachers.php" style="display:inline;">
                      <input type="hidden" name="teacher_id" value="<?php echo (int) $t['id']; ?>">
                      <?php if ($t['status'] === 'active'): ?>
                        <input type="hidden" name="action" value="revoke_teacher">
                        <button class="btn" type="submit">Revoke</button>
                      <?php else: ?>
                        <input type="hidden" name="action" value="approve_teacher">
                        <button class="btn primary" type="submit">Approve</button>
                      <?php endif; ?>
                    </form>
                    <?php if ($t['status'] !== 'disabled'): ?>
                      <form method="post" action="/admin/teachers.php" style="display:inline;" onsubmit="return confirm('Disable this teacher account entirely?');">
                        <input type="hidden" name="teacher_id" value="<?php echo (int) $t['id']; ?>">
                        <input type="hidden" name="action" value="disable_teacher">
                        <button class="btn" type="submit">Disable</button>
                      </form>
                    <?php endif; ?>
                    <form method="post" action="/admin/teachers.php" style="display:inline;" onsubmit="return confirm('Permanently delete this teacher account? This can\'t be undone.');">
                      <input type="hidden" name="teacher_id" value="<?php echo (int) $t['id']; ?>">
                      <input type="hidden" name="action" value="delete_teacher">
                      <button class="btn danger" type="submit">Delete</button>
                    </form>
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
