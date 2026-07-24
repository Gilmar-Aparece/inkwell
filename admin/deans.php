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

  if ($action === 'approve_dean') {
    inkwell_set_user_status((int) ($_POST['dean_id'] ?? 0), 'active', 'dean');
    $notice = 'Dean approved — they can now set up a school and add teachers.';
  }

  if ($action === 'revoke_dean') {
    inkwell_set_user_status((int) ($_POST['dean_id'] ?? 0), 'pending', 'dean');
    $notice = 'Dean permission revoked.';
  }

  if ($action === 'disable_dean') {
    inkwell_set_user_status((int) ($_POST['dean_id'] ?? 0), 'disabled', 'dean');
    $notice = 'Dean account disabled.';
  }

  if ($action === 'delete_dean') {
    $result = inkwell_delete_user((int) ($_POST['dean_id'] ?? 0), 'dean');
    if ($result['ok']) {
      $notice = 'Dean account deleted.';
    } else {
      $error = $result['error'] ?? 'Could not delete that account.';
    }
  }
}

$deans = inkwell_list_deans();

$dashNavTitle = 'Admin';
$dashNavActive = 'deans';
$dashNavItems = [
  ['key' => 'dashboard', 'group' => 'General', 'href' => '/admin/dashboard.php', 'label' => 'Dashboard', 'icon' => '🏠'],
  ['key' => 'settings', 'group' => 'General', 'href' => '/admin/index.php', 'label' => 'Settings', 'icon' => '⚙️'],
  ['key' => 'exams', 'group' => 'Academics', 'href' => '/admin/exams.php', 'label' => 'Exams', 'icon' => '🗂'],
  ['key' => 'lessons', 'group' => 'Academics', 'href' => '/admin/lessons.php', 'label' => 'Lessons', 'icon' => '📘'],
  ['key' => 'teachers', 'group' => 'People', 'href' => '/admin/teachers.php', 'label' => 'Teachers', 'icon' => '🧑‍🏫', 'count' => count(inkwell_list_teachers())],
  ['key' => 'deans', 'group' => 'People', 'href' => '/admin/deans.php', 'label' => 'Deans', 'icon' => '🎓', 'count' => count($deans)],
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

$pageTitle = 'Admin · Deans';
include __DIR__ . '/../includes/header.php';
?>
<div class="dash-shell">
  <?php include __DIR__ . '/../includes/dash_nav.php'; ?>
  <main class="admin-main">
    <div class="admin-header-row">
      <h1>Dean accounts</h1>
      <a class="btn" href="/admin/logout.php">Log out</a>
    </div>

    <?php if ($notice): ?><div class="exam-result pass"><?php echo htmlspecialchars($notice); ?></div><?php endif; ?>
    <?php if ($error): ?><div class="exam-result fail"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

    <section class="admin-card">
      <h2>Dean accounts (<?php echo count($deans); ?>)</h2>
      <p class="admin-sub">Deans are created by an approved Registrar for their school now — they're active immediately, so you shouldn't see pending ones here anymore (any "Pending" rows below are leftover from before this change). Disable a dean to remove their access.</p>
      <?php if (empty($deans)): ?>
        <p class="admin-sub">No dean accounts have registered yet.</p>
      <?php else: ?>
        <div class="search-filter">
          <input type="search" class="search-filter-input" data-filter-target="#deanAccountsTable" placeholder="Search by name or email...">
        </div>
        <div class="admin-table-wrap">
          <table class="admin-table" id="deanAccountsTable">
            <thead><tr><th>Name</th><th>Email</th><th>School</th><th>Status</th><th></th></tr></thead>
            <tbody>
              <?php foreach ($deans as $d): ?>
                <tr data-filter-row>
                  <td><?php echo htmlspecialchars($d['name']); ?></td>
                  <td><?php echo htmlspecialchars($d['email']); ?></td>
                  <td><?php $deanSchool = $d['school_id'] ? inkwell_get_school($d['school_id']) : null; ?><?php echo $deanSchool ? htmlspecialchars($deanSchool['name']) : '<span class="admin-sub">—</span>'; ?></td>
                  <td><?php echo htmlspecialchars(ucfirst($d['status'])); ?></td>
                  <td>
                    <form method="post" action="/admin/deans.php" style="display:inline;">
                      <input type="hidden" name="dean_id" value="<?php echo (int) $d['id']; ?>">
                      <?php if ($d['status'] === 'active'): ?>
                        <input type="hidden" name="action" value="revoke_dean">
                        <button class="btn" type="submit">Revoke</button>
                      <?php else: ?>
                        <input type="hidden" name="action" value="approve_dean">
                        <button class="btn primary" type="submit">Approve</button>
                      <?php endif; ?>
                    </form>
                    <?php if ($d['status'] !== 'disabled'): ?>
                      <form method="post" action="/admin/deans.php" style="display:inline;" onsubmit="return confirm('Disable this dean account entirely?');">
                        <input type="hidden" name="dean_id" value="<?php echo (int) $d['id']; ?>">
                        <input type="hidden" name="action" value="disable_dean">
                        <button class="btn" type="submit">Disable</button>
                      </form>
                    <?php endif; ?>
                    <form method="post" action="/admin/deans.php" style="display:inline;" onsubmit="return confirm('Permanently delete this dean account? This can\'t be undone.');">
                      <input type="hidden" name="dean_id" value="<?php echo (int) $d['id']; ?>">
                      <input type="hidden" name="action" value="delete_dean">
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
