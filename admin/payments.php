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
  $subId = (int) ($_POST['submission_id'] ?? 0);

  if ($action === 'approve') {
    $result = inkwell_review_payment_submission($subId, true, $_POST['admin_note'] ?? '');
    $notice = $result['ok'] ? 'Payment approved — plan activated on the user\'s account.' : $result['error'];
  }

  if ($action === 'reject') {
    $result = inkwell_review_payment_submission($subId, false, $_POST['admin_note'] ?? '');
    $notice = $result['ok'] ? 'Payment rejected.' : $result['error'];
  }
}

$filter = in_array($_GET['status'] ?? '', ['pending', 'approved', 'rejected'], true) ? $_GET['status'] : 'pending';
$submissions = inkwell_list_payment_submissions($filter);

$dashNavTitle = 'Admin';
$dashNavActive = 'payments';
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
  ['key' => 'schools', 'group' => 'Schools & Recognition', 'href' => '/admin/schools.php', 'label' => 'Schools', 'icon' => '🏫', 'count' => count(inkwell_list_schools_with_stats())],
  ['key' => 'certificates', 'group' => 'Schools & Recognition', 'href' => '/admin/certificates.php', 'label' => 'Certificates', 'icon' => '📜'],
  ['key' => 'top-learners', 'group' => 'Schools & Recognition', 'href' => '/admin/top-learners.php', 'label' => 'Top learners', 'icon' => '⭐', 'count' => count(inkwell_top_learner_ids())],
  ['key' => 'lesson-progress', 'group' => 'Schools & Recognition', 'href' => '/admin/lesson-progress.php', 'label' => 'Lesson progress', 'icon' => '📈', 'count' => count(inkwell_lesson_progress_overview())],
  ['key' => 'billing-dashboard', 'group' => 'Billing', 'href' => '/admin/billing-dashboard.php', 'label' => 'Dashboard', 'icon' => '📊'],
  ['key' => 'pricing', 'group' => 'Billing', 'href' => '/admin/pricing.php', 'label' => 'Pricing', 'icon' => '💳', 'count' => count(inkwell_list_plans())],
  ['key' => 'payment-methods', 'group' => 'Billing', 'href' => '/admin/payment-methods.php', 'label' => 'Payment methods', 'icon' => '🏦'],
  ['key' => 'payments', 'group' => 'Billing', 'href' => '/admin/payments.php', 'label' => 'Payments', 'icon' => '🧾', 'count' => inkwell_count_pending_payment_submissions()],
];

$pageTitle = 'Admin · Payments';
include __DIR__ . '/../includes/header.php';
?>
<div class="dash-shell">
  <?php include __DIR__ . '/../includes/dash_nav.php'; ?>
  <main class="admin-main">
    <div class="admin-header-row">
      <h1>Payments</h1>
      <a class="btn" href="/admin/logout.php">Log out</a>
    </div>
    <p class="admin-sub">Review proof of payment submitted by users, then approve to activate their plan or reject with a note.</p>

    <?php if ($notice): ?><div class="exam-result pass"><?php echo htmlspecialchars($notice); ?></div><?php endif; ?>
    <?php if ($error): ?><div class="exam-result fail"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

    <div class="payment-filter-tabs">
      <a class="btn<?php echo $filter === 'pending' ? ' primary' : ''; ?>" href="/admin/payments.php?status=pending">Pending</a>
      <a class="btn<?php echo $filter === 'approved' ? ' primary' : ''; ?>" href="/admin/payments.php?status=approved">Approved</a>
      <a class="btn<?php echo $filter === 'rejected' ? ' primary' : ''; ?>" href="/admin/payments.php?status=rejected">Rejected</a>
    </div>

    <section class="admin-card glass-card">
      <?php if (!$submissions): ?>
        <p class="admin-sub">No <?php echo htmlspecialchars($filter); ?> submissions.</p>
      <?php endif; ?>
      <div class="plan-admin-list">
        <?php foreach ($submissions as $s): ?>
          <details class="plan-admin-row" <?php echo $filter === 'pending' ? 'open' : ''; ?>>
            <summary>
              <span class="plan-admin-name"><?php echo htmlspecialchars($s['user_name']); ?> <span class="plan-admin-audience">(<?php echo htmlspecialchars(ucfirst($s['user_role'])); ?>)</span></span>
              <span class="plan-admin-price"><?php echo htmlspecialchars($s['plan_name']); ?> · ₱<?php echo number_format((float) $s['amount'], 2); ?> / <?php echo htmlspecialchars(($s['billing_cycle'] ?? 'month') === 'year' ? 'yr' : 'mo'); ?></span>
              <span class="plan-admin-audience"><?php echo htmlspecialchars($s['method_label'] ?? '—'); ?></span>
              <span class="payment-status-badge status-<?php echo htmlspecialchars($s['status']); ?>"><?php echo htmlspecialchars(ucfirst($s['status'])); ?></span>
            </summary>
            <div class="payment-submission-detail">
              <p><strong>Email:</strong> <?php echo htmlspecialchars($s['user_email']); ?></p>
              <p><strong>Reference no.:</strong> <?php echo htmlspecialchars($s['reference_no'] ?: '—'); ?></p>
              <?php if (!empty($s['sender_number'])): ?><p><strong>Sender number:</strong> <?php echo htmlspecialchars($s['sender_number']); ?></p><?php endif; ?>
              <?php if (!empty($s['payment_date'])): ?><p><strong>Payment date:</strong> <?php echo date('M j, Y', strtotime($s['payment_date'])); ?></p><?php endif; ?>
              <p><strong>Submitted:</strong> <?php echo date('M j, Y g:i A', strtotime($s['created_at'])); ?></p>
              <?php if (!empty($s['proof_image'])): ?>
                <p><strong>Proof:</strong><br>
                  <a href="/assets/uploads/<?php echo htmlspecialchars($s['proof_image']); ?>" target="_blank" rel="noopener">
                    <img class="payment-proof-preview" src="/assets/uploads/<?php echo htmlspecialchars($s['proof_image']); ?>" alt="Payment proof">
                  </a>
                </p>
              <?php endif; ?>
              <?php if ($s['admin_note']): ?><p><strong>Admin note:</strong> <?php echo htmlspecialchars($s['admin_note']); ?></p><?php endif; ?>
            </div>
            <?php if ($s['status'] === 'pending'): ?>
              <form method="post" action="/admin/payments.php?status=pending" class="admin-form">
                <input type="hidden" name="submission_id" value="<?php echo (int) $s['id']; ?>">
                <label>Note (optional, shown to the user)</label>
                <textarea name="admin_note" rows="2"></textarea>
                <div class="plan-admin-actions">
                  <button class="btn primary" type="submit" name="action" value="approve">Approve &amp; activate</button>
                  <button class="btn danger" type="submit" name="action" value="reject">Reject</button>
                </div>
              </form>
            <?php endif; ?>
          </details>
        <?php endforeach; ?>
      </div>
    </section>
  </main>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
