<?php
// Inside/dashboard page — suppress the marketing topbar (see includes/header.php).
$__hideTopbar = true;
require_once __DIR__ . '/includes/auth.php';

if (inkwell_current_user()) {
  header('Location: /index.php');
  exit;
}

$error = '';
$identifier = $_GET['identifier'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $identifier = $_POST['identifier'] ?? '';
  $password = $_POST['password'] ?? '';
  $result = inkwell_unlock_account_with_password($identifier, $password);
  if (!$result['ok']) {
    $error = $result['error'];
  } else {
    header('Location: /index.php');
    exit;
  }
}

$pageTitle = 'Unlock account';
include __DIR__ . '/includes/header.php';
$driveActive = 'account';
$driveCrumbs = [['label' => 'Home', 'href' => '/index.php'], ['label' => 'Log in', 'href' => '/login.php'], ['label' => 'Unlock account']];
include __DIR__ . '/includes/drive_shell_top.php';
?>
  <div class="admin-auth-card admin-auth-card-shell">
    <h1>Unlock your account</h1>
    <p class="admin-sub">Your account was locked. Enter your email/ID number and password again to prove it's you and unlock it — you'll be signed in right after.</p>

    <?php if ($error): ?><div class="exam-result fail"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

    <form method="post" action="/unlock-account.php" class="admin-form">
      <label for="identifier">Email or Student/Teacher ID</label>
      <input type="text" id="identifier" name="identifier" value="<?php echo htmlspecialchars($identifier); ?>" placeholder="you@example.com or 2024-00123" required autofocus autocapitalize="off" autocomplete="username">
      <label for="password">Password</label>
      <input type="password" id="password" name="password" required autocomplete="current-password">
      <button class="btn primary" type="submit">Unlock &amp; log in</button>
    </form>
    <p class="admin-sub">Changed your mind? <a href="/login.php">Back to log in</a></p>
  </div>
<?php include __DIR__ . '/includes/drive_shell_bottom.php'; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>
