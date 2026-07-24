<?php
// Inside/dashboard page — suppress the marketing topbar (see includes/header.php).
$__hideTopbar = true;
require_once __DIR__ . '/../includes/store.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/exams_db.php';
require_once __DIR__ . '/../includes/schools.php';
require_once __DIR__ . '/../includes/students.php';
require_once __DIR__ . '/../includes/billing.php';
require_once __DIR__ . '/../includes/lesson_progress.php';
$adminUser = inkwell_require_admin();

$notice = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';

  if ($action === 'update_config') {
    $signerName = trim($_POST['signer_name'] ?? '');
    $signerTitle = trim($_POST['signer_title'] ?? '');
    $update = [];
    if ($signerName !== '') $update['signer_name'] = $signerName;
    if ($signerTitle !== '') $update['signer_title'] = $signerTitle;

    if (!empty($_FILES['signature']['name']) && $_FILES['signature']['error'] === UPLOAD_ERR_OK) {
      $allowed = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];
      $tmpPath = $_FILES['signature']['tmp_name'];
      $info = @getimagesize($tmpPath);
      $mime = $info['mime'] ?? '';
      if ($_FILES['signature']['size'] > 2 * 1024 * 1024) {
        $error = 'Signature image must be under 2MB.';
      } elseif (!isset($allowed[$mime])) {
        $error = 'Signature must be a PNG, JPG, or WEBP image.';
      } else {
        if (!is_dir(INKWELL_UPLOADS_DIR)) @mkdir(INKWELL_UPLOADS_DIR, 0775, true);
        $filename = 'signature_' . bin2hex(random_bytes(6)) . '.' . $allowed[$mime];
        if (move_uploaded_file($tmpPath, INKWELL_UPLOADS_DIR . '/' . $filename)) {
          $old = inkwell_get_config()['signature_file'] ?? null;
          if ($old && file_exists(INKWELL_UPLOADS_DIR . '/' . $old)) @unlink(INKWELL_UPLOADS_DIR . '/' . $old);
          $update['signature_file'] = $filename;
        } else {
          $error = 'Could not save the uploaded signature — check that assets/uploads/ is writable.';
        }
      }
    }

    if (!$error) {
      inkwell_save_config($update);
      $notice = 'Settings saved.';
    }
  }

  if ($action === 'change_password') {
    $current = $_POST['current_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    if ($new !== $confirm) {
      $error = 'New password and confirmation do not match.';
    } else {
      $result = inkwell_change_password($adminUser['id'], $current, $new);
      if ($result['ok']) {
        $notice = 'Password updated.';
      } else {
        $error = $result['error'];
      }
    }
  }
}

$config = inkwell_get_config();

$dashNavTitle = 'Admin';
$dashNavActive = 'settings';
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

$pageTitle = 'Admin · Settings';
include __DIR__ . '/../includes/header.php';
?>
<div class="dash-shell">
  <?php include __DIR__ . '/../includes/dash_nav.php'; ?>
  <main class="admin-main">
    <div class="admin-header-row">
      <h1>Settings</h1>
      <a class="btn" href="/admin/logout.php">Log out</a>
    </div>

    <?php if ($notice): ?><div class="exam-result pass"><?php echo htmlspecialchars($notice); ?></div><?php endif; ?>
    <?php if ($error): ?><div class="exam-result fail"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

    <section class="admin-card">
      <h2>Certificate signature</h2>
      <p class="admin-sub">This name, title, and signature image appear on every certificate Inkwell issues.</p>
      <form method="post" action="/admin/index.php" enctype="multipart/form-data" class="admin-form">
        <input type="hidden" name="action" value="update_config">

        <label for="signer_name">Signer name</label>
        <input type="text" id="signer_name" name="signer_name" value="<?php echo htmlspecialchars($config['signer_name']); ?>" maxlength="80">

        <label for="signer_title">Signer title</label>
        <input type="text" id="signer_title" name="signer_title" value="<?php echo htmlspecialchars($config['signer_title']); ?>" maxlength="100">

        <label for="signature">Signature image (PNG, JPG, or WEBP — under 2MB)</label>
        <input type="file" id="signature" name="signature" accept="image/png,image/jpeg,image/webp">

        <?php if (!empty($config['signature_file'])): ?>
          <div class="admin-current-sig">
            <span>Current signature:</span>
            <img src="/assets/uploads/<?php echo htmlspecialchars($config['signature_file']); ?>" alt="Current signature" loading="lazy">
          </div>
        <?php endif; ?>

        <button class="btn primary" type="submit">Save</button>
      </form>
    </section>

    <section class="admin-card">
      <h2>Change password</h2>
      <form method="post" action="/admin/index.php" class="admin-form">
        <input type="hidden" name="action" value="change_password">
        <label for="current_password">Current password</label>
        <input type="password" id="current_password" name="current_password" required>
        <label for="new_password">New password</label>
        <input type="password" id="new_password" name="new_password" required minlength="8">
        <label for="confirm_password">Confirm new password</label>
        <input type="password" id="confirm_password" name="confirm_password" required minlength="8">
        <button class="btn" type="submit">Update password</button>
      </form>
    </section>
  </main>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
