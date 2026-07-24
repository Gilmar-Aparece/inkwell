<?php
// Inside/dashboard page — suppress the marketing topbar (see includes/header.php).
$__hideTopbar = true;
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/schools.php';

if (inkwell_current_user()) {
  header('Location: /index.php');
  exit;
}

$schools = inkwell_list_schools();

$error = '';
$created = null; // full account row on success
$name = '';
$email = '';
$idNumber = '';
$course = '';
$schoolId = $_POST['school_id'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $name = trim($_POST['name'] ?? '');
  $email = trim($_POST['email'] ?? '');
  $password = $_POST['password'] ?? '';
  $confirm = $_POST['confirm_password'] ?? '';
  $idNumber = trim($_POST['id_number'] ?? '');
  $course = trim($_POST['course'] ?? '');
  $schoolId = trim($_POST['school_id'] ?? '');

  if ($password !== $confirm) {
    $error = 'Passwords do not match.';
  } else {
    $result = inkwell_register_user('student', $name, $email, $password, $idNumber, $course, $schoolId !== '' ? (int) $schoolId : null);
    if (!$result['ok']) {
      $error = $result['error'];
    } else {
      $created = inkwell_get_user($result['id']);
    }
  }
}

$pageTitle = 'Create an account';
include __DIR__ . '/includes/header.php';
$driveActive = 'account';
$driveCrumbs = [['label' => 'Home', 'href' => '/index.php'], ['label' => 'Register']];
include __DIR__ . '/includes/drive_shell_top.php';
?>
  <div class="admin-auth-card admin-auth-card-shell glass-card auth-card-wide<?php echo $created ? ' auth-card-success' : ''; ?>">
    <?php if (!$created): ?>
      <div class="auth-card-head">
        <span class="auth-card-icon" aria-hidden="true"><span class="nib-dot"></span></span>
        <h1>Create an account</h1>
        <p class="admin-sub">Students can start taking exams right away. Teacher, Dean, and Registrar accounts are created by your school, not here — ask them for your login once you're added. Looking to set up a school of your own? <a href="/index.php#pricing">See school plans</a>.</p>
      </div>

      <?php if ($error): ?><div class="exam-result fail"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

      <form method="post" action="/register.php" class="admin-form" novalidate>
        <div class="form-grid-2">
          <div>
            <label for="name">Full name</label>
            <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($name); ?>" maxlength="100" required>
          </div>
          <div>
            <label for="email">Email</label>
            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" maxlength="150" required>
          </div>
        </div>

        <div class="form-grid-2">
          <div>
            <label for="id_number">Student ID</label>
            <input type="text" id="id_number" name="id_number" value="<?php echo htmlspecialchars($idNumber); ?>" maxlength="50" placeholder="e.g. 2024-00123" required>
          </div>
          <div>
            <label for="course">Course / Program</label>
            <input type="text" id="course" name="course" maxlength="150" placeholder="e.g. BS Computer Science" value="<?php echo htmlspecialchars($course); ?>" required>
          </div>
        </div>

        <div class="form-grid-2">
          <div style="grid-column: 1 / -1;">
            <label for="school_id">School (optional)</label>
            <select id="school_id" name="school_id">
              <option value=""<?php echo $schoolId === '' ? ' selected' : ''; ?>>No school / independent</option>
              <?php foreach ($schools as $s): ?>
                <option value="<?php echo (int) $s['id']; ?>"<?php echo (string) $schoolId === (string) $s['id'] ? ' selected' : ''; ?>><?php echo htmlspecialchars($s['name']); ?></option>
              <?php endforeach; ?>
            </select>
            <?php if (empty($schools)): ?>
              <small class="admin-sub">No schools have been set up yet — you can register without one and join later.</small>
            <?php endif; ?>
          </div>
        </div>

        <div class="form-grid-2">
          <div>
            <label for="password">Password</label>
            <input type="password" id="password" name="password" minlength="8" required>
          </div>
          <div>
            <label for="confirm_password">Confirm password</label>
            <input type="password" id="confirm_password" name="confirm_password" minlength="8" required>
          </div>
        </div>

        <button class="btn primary" type="submit">Create account</button>
      </form>
      <p class="admin-sub">Already have an account? <a href="/login.php">Log in</a></p>
    <?php else: ?>
      <div class="auth-success-icon" aria-hidden="true">✓</div>
      <h1>Account created</h1>
      <p class="admin-sub">Welcome, <?php echo htmlspecialchars($created['name']); ?>. You're all set to log in and start taking exams.</p>

      <div class="account-info-grid">
        <div class="account-info-row"><span>Account ID</span><strong>#<?php echo str_pad($created['id'], 5, '0', STR_PAD_LEFT); ?></strong></div>
        <div class="account-info-row"><span>Full name</span><strong><?php echo htmlspecialchars($created['name']); ?></strong></div>
        <div class="account-info-row"><span>Email</span><strong><?php echo htmlspecialchars($created['email']); ?></strong></div>
        <div class="account-info-row"><span>Student ID</span><strong><?php echo htmlspecialchars($created['id_number'] ?? '—'); ?></strong></div>
        <div class="account-info-row"><span>Course</span><strong><?php echo htmlspecialchars($created['course'] ?? '—'); ?></strong></div>
        <?php if (!empty($created['school_id'])): $createdSchool = inkwell_get_school($created['school_id']); ?>
          <div class="account-info-row"><span>School</span><strong><?php echo $createdSchool ? htmlspecialchars($createdSchool['name']) : '—'; ?></strong></div>
        <?php endif; ?>
        <div class="account-info-row"><span>Role</span><strong class="badge badge-<?php echo $created['role']; ?>"><?php echo ucfirst($created['role']); ?></strong></div>
        <div class="account-info-row"><span>Status</span><strong class="badge badge-status-<?php echo $created['status']; ?>"><?php echo ucfirst($created['status']); ?></strong></div>
        <div class="account-info-row"><span>Registered</span><strong><?php echo htmlspecialchars(date('F j, Y g:i A', strtotime($created['created_at']))); ?></strong></div>
      </div>

      <a href="/login.php?registered=1" class="btn primary auth-continue-btn">Continue to log in →</a>
    <?php endif; ?>
  </div>
<?php include __DIR__ . '/includes/drive_shell_bottom.php'; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>
