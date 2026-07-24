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

  if ($action === 'create_method') {
    $result = inkwell_create_payment_method($_POST);
    if (!$result['ok']) { $error = $result['error']; } else { $notice = 'Payment method added.'; }
  }

  if ($action === 'update_method') {
    $result = inkwell_update_payment_method((int) ($_POST['method_id'] ?? 0), $_POST);
    if (!$result['ok']) { $error = $result['error']; } else { $notice = 'Payment method updated.'; }
  }

  if ($action === 'toggle_method') {
    inkwell_toggle_payment_method((int) ($_POST['method_id'] ?? 0));
    $notice = 'Availability updated.';
  }

  if ($action === 'delete_method') {
    inkwell_delete_payment_method((int) ($_POST['method_id'] ?? 0));
    $notice = 'Payment method deleted.';
  }
}

$methods = inkwell_list_payment_methods();

$dashNavTitle = 'Admin';
$dashNavActive = 'payment-methods';
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

$pageTitle = 'Admin · Payment methods';
include __DIR__ . '/../includes/header.php';
?>
<div class="dash-shell">
  <?php include __DIR__ . '/../includes/dash_nav.php'; ?>
  <main class="admin-main">
    <div class="admin-header-row">
      <h1>Payment methods</h1>
      <a class="btn" href="/admin/logout.php">Log out</a>
    </div>
    <p class="admin-sub">InfinityFree's free hosting can't run a real card-processing API, so by default these are shown to the user as "pay to this account, then upload your proof" — an admin then approves it under Payments. Turn on <strong>Instant activation</strong> for a method (e.g. a GCash number/QR you check yourself) to skip that wait and activate the plan the moment the user submits.</p>

    <?php if ($notice): ?><div class="exam-result pass"><?php echo htmlspecialchars($notice); ?></div><?php endif; ?>
    <?php if ($error): ?><div class="exam-result fail"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

    <section class="admin-card glass-card">
      <h2>Add a payment method</h2>
      <form method="post" action="/admin/payment-methods.php" enctype="multipart/form-data" class="admin-form">
        <input type="hidden" name="action" value="create_method">
        <label for="type">Type</label>
        <select id="type" name="type">
          <option value="gcash">GCash</option>
          <option value="paypal">PayPal</option>
          <option value="card">Credit / debit card</option>
          <option value="bank">Bank transfer</option>
          <option value="other">Other</option>
        </select>
        <label for="label">Display label</label>
        <input type="text" id="label" name="label" maxlength="100" required placeholder="e.g. GCash">
        <label for="account_name">Account name (optional)</label>
        <input type="text" id="account_name" name="account_name" maxlength="150">
        <label for="account_number">Account / mobile number, email, or link (optional)</label>
        <input type="text" id="account_number" name="account_number" maxlength="150">
        <label for="instructions">Instructions shown to the user</label>
        <textarea id="instructions" name="instructions" rows="3" placeholder="e.g. Send the exact amount, then upload your receipt screenshot."></textarea>
        <label for="qr_image">QR code / logo image (optional, PNG/JPG/WEBP under 2MB)</label>
        <input type="file" id="qr_image" name="qr_image" accept="image/png,image/jpeg,image/webp">
        <label class="admin-checkbox-label"><input type="checkbox" name="auto_activate" value="1"> ⚡ Instant activation — skip manual review, activate the plan as soon as the user submits</label>
        <label for="sort_order">Sort order</label>
        <input type="number" id="sort_order" name="sort_order" value="0">
        <button class="btn primary" type="submit">Add payment method</button>
      </form>
    </section>

    <section class="admin-card glass-card">
      <h2>All payment methods</h2>
      <?php if (!$methods): ?><p class="admin-sub">None yet — add one above.</p><?php endif; ?>
      <div class="plan-admin-list">
        <?php foreach ($methods as $m): ?>
          <details class="plan-admin-row<?php echo $m['is_active'] ? '' : ' inactive'; ?>">
            <summary>
              <?php if (!empty($m['qr_image'])): ?><img class="payment-method-thumb" src="/assets/uploads/<?php echo htmlspecialchars($m['qr_image']); ?>" alt=""><?php endif; ?>
              <span class="plan-admin-name"><?php echo htmlspecialchars($m['label']); ?></span>
              <span class="plan-admin-audience"><?php echo htmlspecialchars(ucfirst($m['type'])); ?></span>
              <?php if (!empty($m['auto_activate'])): ?><span class="payment-method-instant-badge">⚡ Instant</span><?php endif; ?>
              <?php if (!$m['is_active']): ?><span class="plan-admin-hidden">Hidden</span><?php endif; ?>
            </summary>
            <form method="post" action="/admin/payment-methods.php" enctype="multipart/form-data" class="admin-form">
              <input type="hidden" name="action" value="update_method">
              <input type="hidden" name="method_id" value="<?php echo (int) $m['id']; ?>">
              <label>Type</label>
              <select name="type">
                <?php foreach (['gcash' => 'GCash', 'paypal' => 'PayPal', 'card' => 'Credit / debit card', 'bank' => 'Bank transfer', 'other' => 'Other'] as $val => $lbl): ?>
                  <option value="<?php echo $val; ?>" <?php echo $m['type'] === $val ? 'selected' : ''; ?>><?php echo $lbl; ?></option>
                <?php endforeach; ?>
              </select>
              <label>Display label</label>
              <input type="text" name="label" maxlength="100" required value="<?php echo htmlspecialchars($m['label']); ?>">
              <label>Account name</label>
              <input type="text" name="account_name" maxlength="150" value="<?php echo htmlspecialchars($m['account_name'] ?? ''); ?>">
              <label>Account / mobile number, email, or link</label>
              <input type="text" name="account_number" maxlength="150" value="<?php echo htmlspecialchars($m['account_number'] ?? ''); ?>">
              <label>Instructions</label>
              <textarea name="instructions" rows="3"><?php echo htmlspecialchars($m['instructions'] ?? ''); ?></textarea>
              <label>Replace QR code / logo image</label>
              <input type="file" name="qr_image" accept="image/png,image/jpeg,image/webp">
              <label class="admin-checkbox-label"><input type="checkbox" name="auto_activate" value="1" <?php echo !empty($m['auto_activate']) ? 'checked' : ''; ?>> ⚡ Instant activation — skip manual review, activate the plan as soon as the user submits</label>
              <label>Sort order</label>
              <input type="number" name="sort_order" value="<?php echo (int) $m['sort_order']; ?>">
              <div class="plan-admin-actions">
                <button class="btn primary" type="submit">Save changes</button>
              </div>
            </form>
            <div class="plan-admin-actions">
              <form method="post" action="/admin/payment-methods.php" onsubmit="return confirm('Hide/show this on the checkout page?');">
                <input type="hidden" name="action" value="toggle_method">
                <input type="hidden" name="method_id" value="<?php echo (int) $m['id']; ?>">
                <button class="btn" type="submit"><?php echo $m['is_active'] ? 'Hide from checkout' : 'Show at checkout'; ?></button>
              </form>
              <form method="post" action="/admin/payment-methods.php" onsubmit="return confirm('Delete this payment method permanently?');">
                <input type="hidden" name="action" value="delete_method">
                <input type="hidden" name="method_id" value="<?php echo (int) $m['id']; ?>">
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
