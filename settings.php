<?php
// Inside/dashboard page — suppress the marketing topbar (see includes/header.php).
$__hideTopbar = true;
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/settings.php';

$me = inkwell_require_login();
inkwell_ensure_privacy_columns();
$me = inkwell_get_user($me['id']); // re-fetch so the new column is present even on the very first request

$notice = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_privacy') {
  $result = inkwell_update_privacy_settings($me['id'], !empty($_POST['show_email_public']));
  if ($result['ok']) {
    $notice = 'Privacy settings saved.';
    $me = inkwell_get_user($me['id']);
  } else {
    $error = $result['error'];
  }
}

$pageTitle = 'Settings';
include __DIR__ . '/includes/header.php';
$driveActive = 'settings';
$driveCrumbs = [['label' => 'Home', 'href' => '/index.php'], ['label' => 'Settings']];
$driveTitle = 'Settings';
$driveSubtitle = 'Control how Inkwell looks, and what shows up on your public profile.';
include __DIR__ . '/includes/drive_shell_top.php';
?>
  <div class="settings-page">
    <?php if ($notice): ?><div class="exam-result pass"><?php echo htmlspecialchars($notice); ?></div><?php endif; ?>
    <?php if ($error): ?><div class="exam-result fail"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

    <!-- ================= Appearance ================= -->
    <section class="admin-card glass-card settings-card">
      <h3>Appearance</h3>
      <p class="admin-sub">Applies everywhere on Inkwell, on this device.</p>

      <div class="settings-row">
        <div class="settings-row-copy">
          <strong>Dark mode</strong>
          <span class="admin-sub">Switch between the light and dark look.</span>
        </div>
        <input type="checkbox" class="settings-theme-switch" id="settingsDarkModeToggle" aria-label="Toggle dark mode">
      </div>
    </section>

    <!-- ================= Privacy ================= -->
    <section class="admin-card glass-card settings-card">
      <h3>Privacy</h3>
      <p class="admin-sub">Like Facebook's contact-info privacy — your info is only visible to you in Settings unless you choose to share it.</p>

      <form method="post">
        <input type="hidden" name="action" value="update_privacy">
        <div class="settings-row">
          <div class="settings-row-copy">
            <strong>Show my email on my public profile</strong>
            <span class="admin-sub">
              <?php if (in_array($me['role'], ['teacher', 'dean'], true)): ?>
                When off, only you can see your email in Settings — students and visitors browsing your public teacher/dean profile won't see it.
              <?php else: ?>
                Only relevant for teacher and dean accounts, whose profile can be viewed publicly. Your own email stays private either way.
              <?php endif; ?>
            </span>
          </div>
          <input type="checkbox" class="settings-switch" name="show_email_public" value="1" <?php echo !empty($me['show_email_public']) ? 'checked' : ''; ?> onchange="this.form.submit()" aria-label="Show my email on my public profile">
        </div>
      </form>

      <div class="settings-row" style="border-top:1px solid var(--border-soft); margin-top:14px; padding-top:14px;">
        <div class="settings-row-copy">
          <strong>Your email</strong>
          <span class="admin-sub"><?php echo htmlspecialchars($me['email']); ?> — visible only to you here, and to school staff who work with your account.</span>
        </div>
      </div>
    </section>
  </div>

  <script>
  (function () {
    var toggle = document.getElementById('settingsDarkModeToggle');
    if (!toggle) return;
    var root = document.documentElement;
    function sync() { toggle.checked = root.getAttribute('data-theme') === 'dark'; }
    sync();
    toggle.addEventListener('change', function () {
      var next = toggle.checked ? 'dark' : 'light';
      root.setAttribute('data-theme', next);
      document.cookie = 'inkwell_theme=' + next + ';path=/;max-age=' + (60 * 60 * 24 * 365);
      document.querySelectorAll('.theme-toggle').forEach(function (t) { t.textContent = next === 'dark' ? '◑' : '◐'; });
      window.dispatchEvent(new CustomEvent('inkwell:theme', { detail: next }));
    });
    // Stay in sync if the header's own toggle button is used instead.
    window.addEventListener('inkwell:theme', sync);
  })();
  </script>
<?php include __DIR__ . '/includes/drive_shell_bottom.php'; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>
