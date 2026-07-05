<?php
require_once __DIR__ . '/../includes/store.php';
inkwell_ensure_admin_account();

if (inkwell_is_admin()) {
  header('Location: /admin/index.php');
  exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $password = $_POST['password'] ?? '';
  if (inkwell_verify_admin_password($password)) {
    inkwell_admin_login();
    header('Location: /admin/index.php');
    exit;
  }
  $error = 'Incorrect password.';
}

$pageTitle = 'Admin login';
include __DIR__ . '/../includes/header.php';
?>
<main class="admin-main">
  <div class="admin-auth-card">
    <h1>Admin login</h1>
    <p class="admin-sub">Manage certificates and the certificate signature.</p>
    <?php if ($error): ?><div class="exam-result fail"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
    <form method="post" action="/admin/login.php" class="admin-form">
      <label for="password">Password</label>
      <input type="password" id="password" name="password" required autofocus>
      <button class="btn primary" type="submit">Log in</button>
    </form>
  </div>
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?>
