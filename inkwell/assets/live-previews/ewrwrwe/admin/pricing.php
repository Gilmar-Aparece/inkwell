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

  if ($action === 'create_plan') {
    $result = inkwell_create_plan($_POST);
    if (!$result['ok']) { $error = $result['error']; } else { $notice = 'Plan created.'; }
  }

  if ($action === 'update_plan') {
    $result = inkwell_update_plan((int) ($_POST['plan_id'] ?? 0), $_POST);
    if (!$result['ok']) { $error = $result['error']; } else { $notice = 'Plan updated.'; }
  }

  if ($action === 'toggle_plan') {
    inkwell_toggle_plan((int) ($_POST['plan_id'] ?? 0));
    $notice = 'Plan visibility updated.';
  }

  if ($action === 'delete_plan') {
    inkwell_delete_plan((int) ($_POST['plan_id'] ?? 0));
    $notice = 'Plan deleted.';
  }
}

$plans = inkwell_list_plans();

$dashNavTitle = 'Admin';
$dashNavActive = 'pricing';
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
  ['key' => 'pricing', 'group' => 'Billing', 'href' => '/admin/pricing.php', 'label' => 'Pricing', 'icon' => '💳', 'count' => count($plans)],
  ['key' => 'payment-methods', 'group' => 'Billing', 'href' => '/admin/payment-methods.php', 'label' => 'Payment methods', 'icon' => '🏦'],
  ['key' => 'payments', 'group' => 'Billing', 'href' => '/admin/payments.php', 'label' => 'Payments', 'icon' => '🧾', 'count' => inkwell_count_pending_payment_submissions()],
];

$pageTitle = 'Admin · Pricing';
include __DIR__ . '/../includes/header.php';
?>
<div class="dash-shell">
  <?php include __DIR__ . '/../includes/dash_nav.php'; ?>
  <main class="admin-main">
    <div class="admin-header-row">
      <h1>Pricing plans</h1>
      <a class="btn" href="/admin/logout.php">Log out</a>
    </div>
    <p class="admin-sub">These plans appear on the public landing page's pricing section and on My Billing for signed-in users. Set price to 0 for a free plan that activates instantly with no payment step.</p>

    <?php if ($notice): ?><div class="exam-result pass"><?php echo htmlspecialchars($notice); ?></div><?php endif; ?>
    <?php if ($error): ?><div class="exam-result fail"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

    <section class="admin-card glass-card">
      <h2>Add a plan</h2>
      <form method="post" action="/admin/pricing.php" class="admin-form">
        <input type="hidden" name="action" value="create_plan">
        <label for="name">Plan name</label>
        <input type="text" id="name" name="name" maxlength="100" required placeholder="e.g. Pro Learner">

        <label for="audience">Who is this for?</label>
        <select id="audience" name="audience">
          <option value="both">Students &amp; Schools</option>
          <option value="student">Students</option>
          <option value="school">Schools</option>
        </select>

        <label for="price">Monthly price (PHP)</label>
        <input type="number" id="price" name="price" min="0" step="0.01" value="0" required>

        <label for="price_yearly">Yearly price (PHP) — optional, leave blank to default to monthly × 12</label>
        <input type="number" id="price_yearly" name="price_yearly" min="0" step="0.01" placeholder="e.g. 1990 for a discounted annual price">

        <label for="billing_period">Billing period label (shown on the free/forever badge)</label>
        <input type="text" id="billing_period" name="billing_period" maxlength="20" value="month" placeholder="month, year, forever">

        <label class="admin-checkbox-label">
          <input type="checkbox" name="unlocks_exams" value="1" checked>
          Unlocks certification exams &amp; certificates
        </label>

        <label class="admin-checkbox-label">
          <input type="checkbox" name="unlocks_all_lessons" value="1" checked>
          Unlocks every lesson in every track (not just the free preview)
        </label>

        <label for="badge">Badge (optional)</label>
        <input type="text" id="badge" name="badge" maxlength="40" placeholder="e.g. Most popular">

        <label for="description">Short description</label>
        <input type="text" id="description" name="description" maxlength="255" placeholder="One line shown under the plan name">

        <label for="features">Features (one per line)</label>
        <textarea id="features" name="features" rows="5" placeholder="All lesson tracks&#10;Certification exams&#10;Priority support"></textarea>

        <label for="sort_order">Sort order</label>
        <input type="number" id="sort_order" name="sort_order" value="0">

        <button class="btn primary" type="submit">Add plan</button>
      </form>
    </section>

    <section class="admin-card glass-card">
      <h2>All plans</h2>
      <?php if (!$plans): ?>
        <p class="admin-sub">No plans yet — add one above.</p>
      <?php endif; ?>
      <div class="plan-admin-list">
        <?php foreach ($plans as $plan): ?>
          <details class="plan-admin-row<?php echo $plan['is_active'] ? '' : ' inactive'; ?>">
            <summary>
              <span class="plan-admin-name"><?php echo htmlspecialchars($plan['name']); ?></span>
              <span class="plan-admin-price">₱<?php echo number_format((float) $plan['price'], 2); ?>/mo<?php if (!empty($plan['price_yearly'])): ?> · ₱<?php echo number_format((float) $plan['price_yearly'], 2); ?>/yr<?php endif; ?></span>
              <span class="plan-admin-audience"><?php echo htmlspecialchars(ucfirst($plan['audience'])); ?></span>
              <?php if (!empty($plan['unlocks_exams'])): ?><span class="badge-status-active">Unlocks exams</span><?php endif; ?>
              <?php if (!empty($plan['unlocks_all_lessons'])): ?><span class="badge-status-active">Unlocks all lessons</span><?php endif; ?>
              <?php if (!$plan['is_active']): ?><span class="plan-admin-hidden">Hidden</span><?php endif; ?>
            </summary>
            <form method="post" action="/admin/pricing.php" class="admin-form">
              <input type="hidden" name="action" value="update_plan">
              <input type="hidden" name="plan_id" value="<?php echo (int) $plan['id']; ?>">
              <label>Plan name</label>
              <input type="text" name="name" maxlength="100" required value="<?php echo htmlspecialchars($plan['name']); ?>">
              <label>Who is this for?</label>
              <select name="audience">
                <option value="both" <?php echo $plan['audience'] === 'both' ? 'selected' : ''; ?>>Students &amp; Schools</option>
                <option value="student" <?php echo $plan['audience'] === 'student' ? 'selected' : ''; ?>>Students</option>
                <option value="school" <?php echo $plan['audience'] === 'school' ? 'selected' : ''; ?>>Schools</option>
              </select>
              <label>Monthly price (PHP)</label>
              <input type="number" name="price" min="0" step="0.01" value="<?php echo htmlspecialchars($plan['price']); ?>" required>
              <label>Yearly price (PHP) — blank defaults to monthly × 12</label>
              <input type="number" name="price_yearly" min="0" step="0.01" value="<?php echo htmlspecialchars($plan['price_yearly'] ?? ''); ?>" placeholder="e.g. 1990">
              <label>Billing period label</label>
              <input type="text" name="billing_period" maxlength="20" value="<?php echo htmlspecialchars($plan['billing_period']); ?>">
              <label class="admin-checkbox-label">
                <input type="checkbox" name="unlocks_exams" value="1" <?php echo !empty($plan['unlocks_exams']) ? 'checked' : ''; ?>>
                Unlocks certification exams &amp; certificates
              </label>
              <label class="admin-checkbox-label">
                <input type="checkbox" name="unlocks_all_lessons" value="1" <?php echo !empty($plan['unlocks_all_lessons']) ? 'checked' : ''; ?>>
                Unlocks every lesson in every track (not just the free preview)
              </label>
              <label>Badge</label>
              <input type="text" name="badge" maxlength="40" value="<?php echo htmlspecialchars($plan['badge'] ?? ''); ?>">
              <label>Short description</label>
              <input type="text" name="description" maxlength="255" value="<?php echo htmlspecialchars($plan['description'] ?? ''); ?>">
              <label>Features (one per line)</label>
              <textarea name="features" rows="5"><?php echo htmlspecialchars($plan['features'] ?? ''); ?></textarea>
              <label>Sort order</label>
              <input type="number" name="sort_order" value="<?php echo (int) $plan['sort_order']; ?>">
              <div class="plan-admin-actions">
                <button class="btn primary" type="submit">Save changes</button>
              </div>
            </form>
            <div class="plan-admin-actions">
              <form method="post" action="/admin/pricing.php" onsubmit="return confirm('Hide/show this plan on the public pricing page?');">
                <input type="hidden" name="action" value="toggle_plan">
                <input type="hidden" name="plan_id" value="<?php echo (int) $plan['id']; ?>">
                <button class="btn" type="submit"><?php echo $plan['is_active'] ? 'Hide from pricing page' : 'Show on pricing page'; ?></button>
              </form>
              <form method="post" action="/admin/pricing.php" onsubmit="return confirm('Delete this plan permanently?');">
                <input type="hidden" name="action" value="delete_plan">
                <input type="hidden" name="plan_id" value="<?php echo (int) $plan['id']; ?>">
                <button class="btn danger" type="submit">Delete</button>
              </form>
            </div>
          </details>
        <?php endforeach; ?>
      </div>
    </section>
  </main>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
