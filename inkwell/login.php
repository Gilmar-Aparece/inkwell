<?php
// Inside/dashboard page — suppress the marketing topbar (see includes/header.php).
$__hideTopbar = true;
require_once __DIR__ . '/includes/auth.php';

$next = $_GET['next'] ?? '/index.php';
if (!is_string($next) || $next === '' || $next[0] !== '/') $next = '/index.php';

if (inkwell_current_user()) {
  header('Location: ' . $next);
  exit;
}

$error = '';
$locked = false;
$lockedIdentifier = '';
$registered = isset($_GET['registered']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $identifier = $_POST['identifier'] ?? '';
  $password = $_POST['password'] ?? '';
  $result = inkwell_login_user($identifier, $password);
  if (!$result['ok']) {
    if (!empty($result['locked'])) {
      $locked = true;
      $lockedIdentifier = $identifier;
    } else {
      $error = $result['error'];
    }
  } else {
    $dest = $_POST['next'] ?? $next;
    if (!is_string($dest) || $dest === '' || $dest[0] !== '/') $dest = '/index.php';
    header('Location: ' . $dest);
    exit;
  }
}

$pageTitle = 'Log in';
include __DIR__ . '/includes/header.php';
$driveActive = 'account';
$driveCrumbs = [['label' => 'Home', 'href' => '/index.php'], ['label' => 'Log in']];
include __DIR__ . '/includes/drive_shell_top.php';
?>
  <div class="admin-auth-card admin-auth-card-shell">
    <h1>Log in</h1>
    <p class="admin-sub">For student and teacher accounts. Managing the site itself? Use the <a href="/admin/login.php">admin login</a>.</p>

    <?php if ($registered): ?><div class="exam-result pass">Account created — log in below.</div><?php endif; ?>
    <?php if ($error): ?><div class="exam-result fail"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
    <?php if ($locked): ?>
      <div class="exam-result fail">This account is locked. <a href="/unlock-account.php?identifier=<?php echo urlencode($lockedIdentifier); ?>">Unlock it →</a></div>
    <?php endif; ?>

    <form method="post" action="/login.php" class="admin-form">
      <input type="hidden" name="next" value="<?php echo htmlspecialchars($next); ?>">
      <label for="identifier">Email or Student/Teacher ID</label>
      <input type="text" id="identifier" name="identifier" value="<?php echo htmlspecialchars($lockedIdentifier); ?>" placeholder="you@example.com or 2024-00123" required autofocus autocapitalize="off" autocomplete="username">
      <label for="password">Password</label>
      <input type="password" id="password" name="password" required>
      <button class="btn primary" type="submit">Log in</button>
    </form>
    <p class="admin-sub">No account yet? <a href="/register.php">Register</a></p>
  </div>
<?php include __DIR__ . '/includes/drive_shell_bottom.php'; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>
