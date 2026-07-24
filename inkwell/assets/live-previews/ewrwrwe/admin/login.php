<?php
require_once __DIR__ . '/../includes/auth.php';

$existing = inkwell_current_user();
if ($existing && $existing['role'] === 'admin' && $existing['status'] === 'active') {
  header('Location: /admin/index.php');
  exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $result = inkwell_login_user($_POST['email'] ?? '', $_POST['password'] ?? '');
  if (!$result['ok']) {
    $error = $result['error'];
  } elseif ($result['user']['role'] !== 'admin') {
    inkwell_logout_user();
    $error = 'That account is not an admin account.';
  } elseif ($result['user']['status'] === 'pending') {
    inkwell_logout_user();
    $error = 'Your admin account is waiting for approval from another admin.';
  } elseif ($result['user']['status'] !== 'active') {
    inkwell_logout_user();
    $error = 'This admin account has been disabled.';
  } else {
    header('Location: /admin/index.php');
    exit;
  }
}

$pageTitle = 'Admin login';
include __DIR__ . '/../includes/header.php';
?>
<main class="admin-main">
  <div class="admin-auth-card">
    <h1>Admin login</h1>
    <p class="admin-sub">Manage exams, schools, lessons, top learners, and certificates.</p>
    <?php if ($error): ?><div class="exam-result fail"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
    <form method="post" action="/admin/login.php" class="admin-form">
      <label for="email">Email</label>
      <input type="email" id="email" name="email" required autofocus>
      <label for="password">Password</label>
      <input type="password" id="password" name="password" required>
      <button class="btn primary" type="submit">Log in</button>
    </form>
    <p class="admin-sub" style="margin-top:14px;">No admin account yet? <a href="/admin/register.php">Register one</a> — an existing admin will need to approve it first (the very first admin account activates immediately).</p>
  </div>
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?>
