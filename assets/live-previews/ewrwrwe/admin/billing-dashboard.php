<?php
// Inside/dashboard page — suppress the marketing topbar (see includes/header.php).
$__hideTopbar = true;
require_once __DIR__ . '/../includes/store.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/schools.php';
require_once __DIR__ . '/../includes/students.php';
require_once __DIR__ . '/../includes/billing.php';
require_once __DIR__ . '/../includes/lesson_progress.php';
require_once __DIR__ . '/../includes/charts.php';
inkwell_require_admin();

$notice = '';
$error = '';

// Quick payment-method on/off toggle, right from the dashboard, so the
// admin doesn't have to leave to turn a method on/off.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_method') {
  inkwell_toggle_payment_method((int) ($_POST['method_id'] ?? 0));
  header('Location: /admin/billing-dashboard.php');
  exit;
}

$stats = inkwell_billing_dashboard_stats(6);
$methods = inkwell_list_payment_methods();
$recentTransactions = inkwell_list_recent_payment_submissions(8);

$dashNavTitle = 'Admin';
$dashNavActive = 'billing-dashboard';
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

$pageTitle = 'Admin · Billing dashboard';
include __DIR__ . '/../includes/header.php';

$planRows = [];
foreach ($stats['plan_distribution'] as $name => $count) { $planRows[] = ['label' => $name, 'value' => $count]; }

$methodRows = [];
foreach ($stats['method_breakdown'] as $m) { $methodRows[] = ['label' => $m['label'], 'value' => (float) $m['rev']]; }

$cycleTotal = $stats['cycle_split']['month'] + $stats['cycle_split']['year'];
$monthPct = $cycleTotal > 0 ? round(($stats['cycle_split']['month'] / $cycleTotal) * 100) : 0;
$yearPct = $cycleTotal > 0 ? 100 - $monthPct : 0;
?>
<div class="dash-shell">
  <?php include __DIR__ . '/../includes/dash_nav.php'; ?>
  <main class="admin-main">
    <div class="admin-header-row">
      <h1>Billing dashboard</h1>
      <a class="btn" href="/admin/logout.php">Log out</a>
    </div>
    <p class="admin-sub">Revenue, subscribers, and payment activity at a glance. Manage plans in <a href="/admin/pricing.php">Pricing</a>, review proof of payment in <a href="/admin/payments.php">Payments</a>, and turn payment options on/off below.</p>

    <?php if ($notice): ?><div class="exam-result pass"><?php echo htmlspecialchars($notice); ?></div><?php endif; ?>
    <?php if ($error): ?><div class="exam-result fail"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

    <section class="kpi-grid">
      <div class="kpi-card glass-card">
        <span class="kpi-label">Total revenue</span>
        <span class="kpi-value">₱<?php echo number_format($stats['total_revenue'], 2); ?></span>
        <span class="kpi-sub">All-time, approved payments</span>
      </div>
      <div class="kpi-card glass-card">
        <span class="kpi-label">This month</span>
        <span class="kpi-value">₱<?php echo number_format($stats['revenue_this_month'], 2); ?></span>
        <span class="kpi-sub">Approved since <?php echo date('M j'); ?></span>
      </div>
      <div class="kpi-card glass-card">
        <span class="kpi-label">Pending review</span>
        <span class="kpi-value">₱<?php echo number_format($stats['pending_revenue'], 2); ?></span>
        <span class="kpi-sub"><?php echo (int) $stats['pending_count']; ?> submission<?php echo $stats['pending_count'] === 1 ? '' : 's'; ?> waiting — <a href="/admin/payments.php?status=pending">review →</a></span>
      </div>
      <div class="kpi-card glass-card">
        <span class="kpi-label">Active paid subscribers</span>
        <span class="kpi-value"><?php echo (int) $stats['active_subscribers']; ?></span>
        <span class="kpi-sub"><?php echo (int) $stats['free_users']; ?> on free plans</span>
      </div>
      <div class="kpi-card glass-card">
        <span class="kpi-label">Renewing soon</span>
        <span class="kpi-value"><?php echo (int) $stats['expiring_soon']; ?></span>
        <span class="kpi-sub">Within 5 days</span>
      </div>
      <div class="kpi-card glass-card">
        <span class="kpi-label">Expired</span>
        <span class="kpi-value"><?php echo (int) $stats['expired_count']; ?></span>
        <span class="kpi-sub">Exam access locked</span>
      </div>
    </section>

    <section class="admin-card glass-card">
      <h2>Revenue — last 6 months</h2>
      <?php echo inkwell_svg_bar_chart($stats['revenue_trend'], ['prefix' => '₱']); ?>
    </section>

    <div class="dash-two-col">
      <section class="admin-card glass-card">
        <h2>Active subscribers by plan</h2>
        <?php echo inkwell_hbar_list($planRows, ['color' => 'var(--nib)']); ?>
      </section>

      <section class="admin-card glass-card">
        <h2>Revenue by payment method</h2>
        <?php echo inkwell_hbar_list($methodRows, ['prefix' => '₱', 'color' => 'var(--pine)']); ?>
      </section>
    </div>

    <section class="admin-card glass-card">
      <h2>Monthly vs. yearly billing</h2>
      <?php if ($cycleTotal <= 0): ?>
        <p class="admin-sub">No approved payments yet.</p>
      <?php else: ?>
        <div class="cycle-split-bar">
          <span class="cycle-split-month" style="width:<?php echo $monthPct; ?>%;" title="Monthly: ₱<?php echo number_format($stats['cycle_split']['month'], 2); ?>"></span>
          <span class="cycle-split-year" style="width:<?php echo $yearPct; ?>%;" title="Yearly: ₱<?php echo number_format($stats['cycle_split']['year'], 2); ?>"></span>
        </div>
        <div class="cycle-split-legend">
          <span><i class="cycle-dot cycle-dot-month"></i> Monthly — ₱<?php echo number_format($stats['cycle_split']['month'], 2); ?> (<?php echo $monthPct; ?>%)</span>
          <span><i class="cycle-dot cycle-dot-year"></i> Yearly — ₱<?php echo number_format($stats['cycle_split']['year'], 2); ?> (<?php echo $yearPct; ?>%)</span>
        </div>
      <?php endif; ?>
    </section>

    <section class="admin-card glass-card">
      <div class="admin-header-row" style="margin-bottom:0;">
        <h2>Recent transactions</h2>
        <a class="btn" href="/admin/payments.php">View all →</a>
      </div>
      <?php if (!$recentTransactions): ?>
        <p class="admin-sub">No transactions yet.</p>
      <?php else: ?>
        <div class="plan-admin-list">
          <?php foreach ($recentTransactions as $s): ?>
            <div class="plan-admin-row payment-history-row">
              <span class="plan-admin-name"><?php echo htmlspecialchars($s['user_name']); ?></span>
              <span class="plan-admin-price"><?php echo htmlspecialchars($s['plan_name']); ?> · ₱<?php echo number_format((float) $s['amount'], 2); ?> / <?php echo htmlspecialchars(($s['billing_cycle'] ?? 'month') === 'year' ? 'yr' : 'mo'); ?></span>
              <span class="plan-admin-audience"><?php echo htmlspecialchars($s['method_label'] ?? '—'); ?></span>
              <span class="payment-status-badge status-<?php echo htmlspecialchars($s['status']); ?>"><?php echo htmlspecialchars(ucfirst($s['status'])); ?></span>
              <span class="plan-admin-audience"><?php echo date('M j, Y', strtotime($s['created_at'])); ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>

    <section class="admin-card glass-card">
      <div class="admin-header-row" style="margin-bottom:0;">
        <h2>Payment methods accepted</h2>
        <a class="btn" href="/admin/payment-methods.php">Manage / add →</a>
      </div>
      <p class="admin-sub">Turn a method on or off at checkout without leaving this page.</p>
      <?php if (!$methods): ?>
        <p class="admin-sub">None yet — <a href="/admin/payment-methods.php">add one</a>.</p>
      <?php else: ?>
        <div class="method-toggle-list">
          <?php foreach ($methods as $m): ?>
            <form method="post" action="/admin/billing-dashboard.php" class="method-toggle-row<?php echo $m['is_active'] ? '' : ' inactive'; ?>">
              <input type="hidden" name="action" value="toggle_method">
              <input type="hidden" name="method_id" value="<?php echo (int) $m['id']; ?>">
              <?php if (!empty($m['qr_image'])): ?><img class="payment-method-thumb" src="/assets/uploads/<?php echo htmlspecialchars($m['qr_image']); ?>" alt=""><?php endif; ?>
              <span class="plan-admin-name"><?php echo htmlspecialchars($m['label']); ?></span>
              <span class="plan-admin-audience"><?php echo htmlspecialchars(ucfirst($m['type'])); ?></span>
              <button class="btn method-toggle-btn<?php echo $m['is_active'] ? ' primary' : ''; ?>" type="submit">
                <?php echo $m['is_active'] ? 'On — tap to hide' : 'Off — tap to show'; ?>
              </button>
            </form>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>
  </main>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
