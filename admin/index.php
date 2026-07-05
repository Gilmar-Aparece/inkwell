<?php
require_once __DIR__ . '/../includes/store.php';
inkwell_require_admin();

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
    if (!inkwell_verify_admin_password($current)) {
      $error = 'Current password is incorrect.';
    } elseif (strlen($new) < 8) {
      $error = 'New password must be at least 8 characters.';
    } elseif ($new !== $confirm) {
      $error = 'New password and confirmation do not match.';
    } else {
      inkwell_set_admin_password($new);
      $notice = 'Password updated.';
    }
  }
}

$config = inkwell_get_config();
$certificates = array_reverse(inkwell_get_certificates());
$pageTitle = 'Admin dashboard';
include __DIR__ . '/../includes/header.php';
?>
<main class="admin-main">
  <div class="admin-header-row">
    <h1>Admin dashboard</h1>
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
          <img src="/assets/uploads/<?php echo htmlspecialchars($config['signature_file']); ?>" alt="Current signature">
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

  <section class="admin-card">
    <h2>Issued certificates (<?php echo count($certificates); ?>)</h2>
    <?php if (empty($certificates)): ?>
      <p class="admin-sub">No certificates have been issued yet.</p>
    <?php else: ?>
      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead><tr><th>Name</th><th>Language</th><th>Score</th><th>Date</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($certificates as $cert): ?>
              <tr>
                <td><?php echo htmlspecialchars($cert['name']); ?></td>
                <td><?php echo htmlspecialchars($cert['label']); ?></td>
                <td><?php echo (int) $cert['percent']; ?>%</td>
                <td><?php echo htmlspecialchars($cert['issued_at']); ?></td>
                <td><a href="/certificate.php?id=<?php echo urlencode($cert['id']); ?>" target="_blank">View →</a></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?>
