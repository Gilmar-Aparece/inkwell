<?php
// Inside/dashboard page — suppress the marketing topbar (see includes/header.php).
$__hideTopbar = true;
require_once __DIR__ . '/../includes/store.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/schools.php';
require_once __DIR__ . '/../includes/students.php';
require_once __DIR__ . '/../includes/billing.php';
require_once __DIR__ . '/../includes/lesson_progress.php';
$me = inkwell_require_admin();

$notice = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';
  $targetId = (int) ($_POST['admin_id'] ?? 0);

  if ($targetId === (int) $me['id'] && in_array($action, ['revoke_admin', 'disable_admin', 'delete_admin'], true)) {
    $error = "You can't revoke, disable, or delete your own account.";
  } elseif ($action === 'approve_admin') {
    inkwell_set_user_status($targetId, 'active', 'admin');
    $notice = 'Admin approved — they can now log in.';
  } elseif ($action === 'revoke_admin') {
    inkwell_set_user_status($targetId, 'pending', 'admin');
    $notice = 'Admin access revoked.';
  } elseif ($action === 'disable_admin') {
    inkwell_set_user_status($targetId, 'disabled', 'admin');
    $notice = 'Admin account disabled.';
  } elseif ($action === 'delete_admin') {
    $result = inkwell_delete_user($targetId, 'admin');
    if ($result['ok']) {
      $notice = 'Admin account deleted.';
    } else {
      $error = $result['error'] ?? 'Could not delete that account.';
    }
  }
}

$admins = inkwell_list_admins();

$dashNavTitle = 'Admin';
$dashNavActive = 'admins';
$dashNavItems = [
  ['key' => 'dashboard', 'group' => 'General', 'href' => '/admin/dashboard.php', 'label' => 'Dashboard', 'icon' => '🏠'],
  ['key' => 'settings', 'group' => 'General', 'href' => '/admin/index.php', 'label' => 'Settings', 'icon' => '⚙️'],
  ['key' => 'exams', 'group' => 'Academics', 'href' => '/admin/exams.php', 'label' => 'Exams', 'icon' => '🗂'],
  ['key' => 'lessons', 'group' => 'Academics', 'href' => '/admin/lessons.php', 'label' => 'Lessons', 'icon' => '📘'],
  ['key' => 'teachers', 'group' => 'People', 'href' => '/admin/teachers.php', 'label' => 'Teachers', 'icon' => '🧑‍🏫', 'count' => count(inkwell_list_teachers())],
  ['key' => 'deans', 'group' => 'People', 'href' => '/admin/deans.php', 'label' => 'Deans', 'icon' => '🎓', 'count' => count(inkwell_list_deans())],
  ['key' => 'registrars', 'group' => 'People', 'href' => '/admin/registrars.php', 'label' => 'Registrars', 'icon' => '🗂️', 'count' => count(inkwell_list_registrars())],
  ['key' => 'admins', 'group' => 'People', 'href' => '/admin/admins.php', 'label' => 'Admins', 'icon' => '🛡️', 'count' => count($admins)],
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

$pageTitle = 'Admin · Admins';
include __DIR__ . '/../includes/header.php';
?>
<div class="dash-shell">
  <?php include __DIR__ . '/../includes/dash_nav.php'; ?>
  <main class="admin-main">
    <div class="admin-header-row">
      <h1>Admin accounts</h1>
      <a class="btn" href="/admin/logout.php">Log out</a>
    </div>

    <?php if ($notice): ?><div class="exam-result pass"><?php echo htmlspecialchars($notice); ?></div><?php endif; ?>
    <?php if ($error): ?><div class="exam-result fail"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

    <section class="admin-card">
      <h2>Admin accounts (<?php echo count($admins); ?>)</h2>
      <p class="admin-sub">New admin registrations (from /admin/register.php) start pending — approve one here to let them log in. You can't revoke or disable your own account.</p>
      <?php if (empty($admins)): ?>
        <p class="admin-sub">No admin accounts yet.</p>
      <?php else: ?>
        <div class="search-filter">
          <input type="search" class="search-filter-input" data-filter-target="#adminAccountsTable" placeholder="Search by name or email...">
        </div>
        <div class="admin-table-wrap">
          <table class="admin-table" id="adminAccountsTable">
            <thead><tr><th>Name</th><th>Email</th><th>Status</th><th>Registered</th><th></th></tr></thead>
            <tbody>
              <?php foreach ($admins as $a): $isSelf = (int) $a['id'] === (int) $me['id']; ?>
                <tr data-filter-row>
                  <td><?php echo htmlspecialchars($a['name']); ?><?php echo $isSelf ? ' <span class="admin-sub" style="display:inline;">(you)</span>' : ''; ?></td>
                  <td><?php echo htmlspecialchars($a['email']); ?></td>
                  <td><?php echo htmlspecialchars(ucfirst($a['status'])); ?></td>
                  <td><?php echo htmlspecialchars(date('M j, Y', strtotime($a['created_at']))); ?></td>
                  <td style="white-space:nowrap;">
                    <?php if (!$isSelf): ?>
                      <form method="post" action="/admin/admins.php" style="display:inline;">
                        <input type="hidden" name="admin_id" value="<?php echo (int) $a['id']; ?>">
                        <?php if ($a['status'] === 'active'): ?>
                          <input type="hidden" name="action" value="revoke_admin">
                          <button class="btn" type="submit">Revoke</button>
                        <?php else: ?>
                          <input type="hidden" name="action" value="approve_admin">
                          <button class="btn primary" type="submit">Approve</button>
                        <?php endif; ?>
                      </form>
                      <?php if ($a['status'] !== 'disabled'): ?>
                        <form method="post" action="/admin/admins.php" style="display:inline;" onsubmit="return confirm('Disable this admin account entirely?');">
                          <input type="hidden" name="admin_id" value="<?php echo (int) $a['id']; ?>">
                          <input type="hidden" name="action" value="disable_admin">
                          <button class="btn" type="submit">Disable</button>
                        </form>
                      <?php endif; ?>
                      <form method="post" action="/admin/admins.php" style="display:inline;" onsubmit="return confirm('Permanently delete this admin account? This can\'t be undone.');">
                        <input type="hidden" name="admin_id" value="<?php echo (int) $a['id']; ?>">
                        <input type="hidden" name="action" value="delete_admin">
                        <button class="btn danger" type="submit">Delete</button>
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
