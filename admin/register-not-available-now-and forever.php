<?php
require_once __DIR__ . '/../includes/auth.php';

$notice = '';
$error = '';
$firstAdmin = !inkwell_admin_exists();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $name = $_POST['name'] ?? '';
  $email = $_POST['email'] ?? '';
  $password = $_POST['password'] ?? '';
  $confirm = $_POST['confirm_password'] ?? '';

  if ($password !== $confirm) {
    $error = 'Password and confirmation do not match.';
  } else {
    $result = inkwell_register_user('admin', $name, $email, $password);
    if (!$result['ok']) {
      $error = $result['error'];
    } elseif ($result['status'] === 'active') {
      // First-ever admin — log them straight in.
      inkwell_login_user($email, $password);
      header('Location: /admin/index.php');
      exit;
    } else {
      $notice = 'Admin account created. An existing admin needs to approve it in Admin → Admins before you can log in.';
    }
  }
}

$pageTitle = 'Admin registration';
include __DIR__ . '/../includes/header.php';
?>
<main class="admin-main">
  <div class="admin-auth-card">
    <h1>Register an admin account</h1>
    <?php if ($firstAdmin): ?>
      <p class="admin-sub">No admin exists yet — the account you create here becomes the first admin and activates immediately.</p>
    <?php else: ?>
      <p class="admin-sub">An existing admin will need to approve this account (Admin → Admins) before it can log in.</p>
    <?php endif; ?>
    <?php if ($notice): ?><div class="exam-result pass"><?php echo htmlspecialchars($notice); ?></div><?php endif; ?>
    <?php if ($error): ?><div class="exam-result fail"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
    <?php if (!$notice): ?>
      <form method="post" action="/admin/register.php" class="admin-form">
        <label for="name">Name</label>
        <input type="text" id="name" name="name" required>
        <label for="email">Email</label>
        <input type="email" id="email" name="email" required>
        <label for="password">Password</label>
        <input type="password" id="password" name="password" required minlength="8">
        <label for="confirm_password">Confirm password</label>
        <input type="password" id="confirm_password" name="confirm_password" required minlength="8">
        <button class="btn primary" type="submit">Register</button>
      </form>
    <?php endif; ?>
    <p class="admin-sub" style="margin-top:14px;">Already have an account? <a href="/admin/login.php">Log in</a>.</p>
  </div>
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?>
