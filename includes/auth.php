<?php
/**
 * Account registration + login. Separate from the admin auth in store.php.
 *
 * Only students self-register on the public form (/register.php) now —
 * status is always 'active' immediately after registering.
 *
 * Registrar accounts are no longer self-registered here. Creating a
 * school (and its first Registrar) now happens through the paid checkout
 * at /create-school.php, tied to a pricing plan — see
 * inkwell_create_school_checkout() in includes/billing.php. That account
 * starts 'pending' and is unlocked automatically once payment is
 * confirmed (free plan, an instant-activate payment method, or an admin
 * approving the submission in /admin/payments.php).
 *
 * Teacher and Dean accounts are still not self-registered — an approved
 * Registrar creates them directly (see inkwell_registrar_create_teacher()
 * / inkwell_registrar_create_dean() in includes/schools.php), active
 * immediately. Admin accounts are created through a separate, unlisted
 * path (see admin/register-not-available-now-and-forever.php).
 */

require_once __DIR__ . '/db.php';

function inkwell_auth_session_start() {
  if (session_status() !== PHP_SESSION_ACTIVE) session_start();
}

/* ---------------- Flash messages (post/redirect/get feedback) ----------------
 * Lets a page set a one-time success/error message right before redirecting,
 * so the *next* request (after the browser follows the redirect) can show
 * "Saved." / "Something went wrong" etc. without the message reappearing on
 * refresh and without resubmitting the form.
 */
function inkwell_flash_set($type, $message) {
  inkwell_auth_session_start();
  $_SESSION['inkwell_flash'] = ['type' => $type, 'message' => $message];
}

/** Reads and clears the pending flash message, if any. Returns ['type' => 'notice'|'error', 'message' => string] or null. */
function inkwell_flash_get() {
  inkwell_auth_session_start();
  if (empty($_SESSION['inkwell_flash'])) return null;
  $flash = $_SESSION['inkwell_flash'];
  unset($_SESSION['inkwell_flash']);
  return $flash;
}

/* ---------------- Registration / login ---------------- */

/**
 * $schoolId is the school picked on the registration form — only used for
 * role=student (passing null/'' registers them with no school attached).
 * role=admin never has a school. Registrar accounts are no longer created
 * through this function at all — see inkwell_create_school_checkout() in
 * includes/billing.php.
 */
function inkwell_register_user($role, $name, $email, $password, $idNumber = '', $course = '', $schoolId = null) {
  require_once __DIR__ . '/schools.php';
  // Public self-registration only ever allows student — this function is
  // also reused internally for admin bootstrap, so 'admin' is still
  // accepted here, but register.php itself never posts that role.
  $role = $role === 'admin' ? 'admin' : 'student';
  $name = trim($name);
  $email = strtolower(trim($email));
  $idNumber = trim($idNumber);
  $course = trim($course);

  $schoolId = (!empty($schoolId) && $role === 'student') ? (int) $schoolId : null;
  if ($schoolId) {
    $pdo = inkwell_db();
    $check = $pdo->prepare('SELECT id FROM schools WHERE id = ?');
    $check->execute([$schoolId]);
    if (!$check->fetch()) $schoolId = null; // silently ignore an invalid/stale school id
  }

  if ($name === '' || $email === '' || $password === '') {
    return ['ok' => false, 'error' => 'All fields are required.'];
  }
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    return ['ok' => false, 'error' => 'Enter a valid email address.'];
  }
  if (strlen($password) < 8) {
    return ['ok' => false, 'error' => 'Password must be at least 8 characters.'];
  }
  // Admins don't have an ID number / course / school — skip those checks.
  if ($role !== 'admin') {
    if ($idNumber === '') {
      return ['ok' => false, 'error' => 'Enter your Student ID.'];
    }
    if ($course === '') {
      return ['ok' => false, 'error' => 'Enter your course / program.'];
    }
  } else {
    $idNumber = '';
    $course = '';
  }

  $pdo = inkwell_db();
  $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
  $stmt->execute([$email]);
  if ($stmt->fetch()) {
    return ['ok' => false, 'error' => 'An account with that email already exists.'];
  }

  // Admin registrations need approval from an existing admin too — EXCEPT
  // the very first one ever, which auto-activates so there's someone to
  // approve everyone else (bootstrap).
  $status = $role === 'admin' ? (inkwell_admin_exists() ? 'pending' : 'active') : 'active';
  $stmt = $pdo->prepare('INSERT INTO users (role, name, email, password_hash, status, id_number, course, school_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
  $stmt->execute([$role, $name, $email, password_hash($password, PASSWORD_DEFAULT), $status, $idNumber, $course, $schoolId]);

  return ['ok' => true, 'id' => (int) $pdo->lastInsertId(), 'role' => $role, 'status' => $status];
}

/**
 * Logs in with either an email address or an ID number (student/teacher/dean ID)
 * in the same field, so already-registered students can sign in with just
 * their ID number instead of having to remember/type their email.
 */
function inkwell_login_user($identifier, $password) {
  $identifier = trim($identifier);
  $pdo = inkwell_db();

  if (strpos($identifier, '@') !== false) {
    // Looks like an email — match on email only.
    $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ?');
    $stmt->execute([strtolower($identifier)]);
  } else {
    // Otherwise treat it as an ID number.
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id_number = ? AND id_number IS NOT NULL AND id_number != \'\' LIMIT 1');
    $stmt->execute([$identifier]);
  }
  $user = $stmt->fetch();

  if (!$user || !password_verify($password, $user['password_hash'])) {
    return ['ok' => false, 'error' => 'Incorrect email/ID number or password.'];
  }
  if ($user['status'] === 'disabled') {
    return ['ok' => false, 'error' => 'This account has been disabled. Contact the admin.'];
  }

  inkwell_auth_session_start();
  $_SESSION['inkwell_user_id'] = (int) $user['id'];
  return ['ok' => true, 'user' => $user];
}

function inkwell_logout_user() {
  inkwell_auth_session_start();
  unset($_SESSION['inkwell_user_id']);
}

/* ---------------- Current user ---------------- */

function inkwell_current_user() {
  inkwell_auth_session_start();
  if (empty($_SESSION['inkwell_user_id'])) return null;
  static $cached = null;
  if ($cached !== null) return $cached;
  $pdo = inkwell_db();
  $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
  $stmt->execute([$_SESSION['inkwell_user_id']]);
  $user = $stmt->fetch();
  if (!$user || $user['status'] === 'disabled') {
    inkwell_logout_user();
    return null;
  }

  // Lazy expiry sync: no cron on InfinityFree, so flip plan_status ->
  // 'expired' the first time anyone loads the account after the date has
  // passed. Keeps plan_id (so "renew" pre-selects the right plan) but
  // revokes exam access via inkwell_user_has_exam_access() immediately.
  if ($user['plan_status'] === 'active' && !empty($user['plan_expires_at']) && strtotime($user['plan_expires_at']) < time()) {
    $upd = $pdo->prepare("UPDATE users SET plan_status = 'expired' WHERE id = ?");
    $upd->execute([$user['id']]);
    $user['plan_status'] = 'expired';
  }

  $cached = $user;
  return $user;
}

function inkwell_require_login($redirectTo = null) {
  $user = inkwell_current_user();
  if (!$user) {
    $back = $redirectTo ?? ($_SERVER['REQUEST_URI'] ?? '/index.php');
    header('Location: /login.php?next=' . urlencode($back));
    exit;
  }
  return $user;
}

function inkwell_require_role($role) {
  $user = inkwell_require_login();
  $allowed = is_array($role) ? $role : [$role];
  if (!in_array($user['role'], $allowed, true)) {
    http_response_code(403);
    die('This page is only available to ' . htmlspecialchars(implode(' or ', $allowed)) . ' accounts.');
  }
  return $user;
}

/** Teacher must be role=teacher AND approved (status=active) to manage exams. */
function inkwell_require_approved_teacher() {
  $user = inkwell_require_role('teacher');
  if ($user['status'] !== 'active') {
    http_response_code(403);
    // Handled in teacher/dashboard.php with a friendlier message instead of dying here.
  }
  return $user;
}

/* ---------------- Teacher / dean directory / admin approval ---------------- */

function inkwell_list_teachers() {
  $pdo = inkwell_db();
  return $pdo->query("SELECT * FROM users WHERE role = 'teacher' ORDER BY status = 'pending' DESC, created_at DESC")->fetchAll();
}

/** Approved teachers only — used to populate "take this exam under a teacher" pickers. */
function inkwell_list_approved_teachers() {
  $pdo = inkwell_db();
  return $pdo->query("SELECT id, name, email FROM users WHERE role = 'teacher' AND status = 'active' ORDER BY name ASC")->fetchAll();
}

function inkwell_list_deans() {
  $pdo = inkwell_db();
  return $pdo->query("SELECT * FROM users WHERE role = 'dean' ORDER BY status = 'pending' DESC, created_at DESC")->fetchAll();
}

function inkwell_list_registrars() {
  $pdo = inkwell_db();
  return $pdo->query("SELECT * FROM users WHERE role = 'registrar' ORDER BY status = 'pending' DESC, created_at DESC")->fetchAll();
}

/** Active registrar accounts at one school — used to show who's allowed to manage that school's subjects. */
function inkwell_list_school_registrars($schoolId) {
  $pdo = inkwell_db();
  $stmt = $pdo->prepare("SELECT * FROM users WHERE role = 'registrar' AND status = 'active' AND school_id = ? ORDER BY name ASC");
  $stmt->execute([$schoolId]);
  return $stmt->fetchAll();
}

function inkwell_get_user($id) {
  $pdo = inkwell_db();
  $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
  $stmt->execute([$id]);
  return $stmt->fetch() ?: null;
}

/** Updates the fields a user can edit about themselves on their own profile page (name, short bio). Requires the `bio` column — see MIGRATION_ADD_profile_bio.sql. */
function inkwell_update_profile_details($userId, $name, $bio) {
  $name = trim($name);
  $bio = trim($bio);
  if ($name === '') return ['ok' => false, 'error' => 'Name cannot be empty.'];
  if (strlen($name) > 100) return ['ok' => false, 'error' => 'Name is too long.'];
  if (strlen($bio) > 160) return ['ok' => false, 'error' => 'Bio must be 160 characters or fewer.'];
  $pdo = inkwell_db();
  try {
    $stmt = $pdo->prepare('UPDATE users SET name = ?, bio = ? WHERE id = ?');
    $stmt->execute([$name, $bio !== '' ? $bio : null, $userId]);
  } catch (PDOException $e) {
    // `bio` column not migrated yet — still save the name so the form isn't a dead end.
    $stmt = $pdo->prepare('UPDATE users SET name = ? WHERE id = ?');
    $stmt->execute([$name, $userId]);
    return ['ok' => true, 'warning' => 'Name saved, but the bio field needs a one-time database update — run MIGRATION_ADD_profile_bio.sql.'];
  }
  return ['ok' => true];
}

/** Works for any approvable role ('teacher', 'dean', or 'admin'). */
function inkwell_set_user_status($id, $status, $role = 'teacher') {
  $role = in_array($role, ['teacher', 'dean', 'admin', 'registrar'], true) ? $role : 'teacher';
  $pdo = inkwell_db();
  $stmt = $pdo->prepare('UPDATE users SET status = ? WHERE id = ? AND role = ?');
  return $stmt->execute([$status, $id, $role]);
}

/**
 * Permanently removes an account (teacher/dean/registrar/admin) rather than
 * just disabling it. Unlike disable, this can't be undone.
 * Deans are special-cased: the `schools` table has an ON DELETE CASCADE
 * foreign key on dean_id, so deleting a dean who still has a school would
 * silently wipe that whole school (teachers, students, certificates). We
 * block that and ask the admin to reassign or delete the school first.
 */
function inkwell_delete_user($id, $role) {
  $role = in_array($role, ['teacher', 'dean', 'admin', 'registrar'], true) ? $role : 'teacher';
  $pdo = inkwell_db();

  if ($role === 'dean') {
    $stmt = $pdo->prepare('SELECT id, name FROM schools WHERE dean_id = ? LIMIT 1');
    $stmt->execute([$id]);
    $school = $stmt->fetch();
    if ($school) {
      return ['ok' => false, 'error' => 'This dean still runs "' . $school['name'] . '" — reassign or delete that school first, then delete the account.'];
    }
  }

  $stmt = $pdo->prepare('DELETE FROM users WHERE id = ? AND role = ?');
  $stmt->execute([$id, $role]);
  return ['ok' => $stmt->rowCount() > 0];
}

/** Kept for backward compatibility with existing calls. */
function inkwell_delete_teacher($id) {
  return inkwell_delete_user($id, 'teacher');
}

/** True once at least one active admin account exists (used to bootstrap the very first one). */
function inkwell_admin_exists() {
  $pdo = inkwell_db();
  return (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin' AND status = 'active'")->fetchColumn() > 0;
}

/** All admin accounts, pending ones first — for admin/admins.php. */
function inkwell_list_admins() {
  $pdo = inkwell_db();
  return $pdo->query("SELECT * FROM users WHERE role = 'admin' ORDER BY status = 'pending' DESC, created_at DESC")->fetchAll();
}

/** Lets any logged-in user (admin included) change their own password after confirming the current one. */
function inkwell_change_password($userId, $currentPassword, $newPassword) {
  $pdo = inkwell_db();
  $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = ?');
  $stmt->execute([$userId]);
  $hash = $stmt->fetchColumn();
  if (!$hash || !password_verify($currentPassword, $hash)) {
    return ['ok' => false, 'error' => 'Current password is incorrect.'];
  }
  if (strlen($newPassword) < 8) {
    return ['ok' => false, 'error' => 'New password must be at least 8 characters.'];
  }
  $stmt = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
  $stmt->execute([password_hash($newPassword, PASSWORD_DEFAULT), $userId]);
  return ['ok' => true];
}

/** Kept for backward compatibility with existing calls. */
function inkwell_set_teacher_status($id, $status) {
  return inkwell_set_user_status($id, $status, 'teacher');
}

/**
 * Lets a teacher set who signs the certificates issued for exams they
 * teach — a school principal / president, or themselves. Overrides the
 * teacher's school-wide signer (if any) and the global admin default.
 * Pass empty strings to clear back to those defaults.
 */
function inkwell_update_teacher_signer($teacherId, $name, $title) {
  $name = trim($name);
  $title = trim($title);
  $pdo = inkwell_db();
  $stmt = $pdo->prepare("UPDATE users SET signer_name = ?, signer_title = ? WHERE id = ? AND role = 'teacher'");
  return $stmt->execute([$name !== '' ? $name : null, $title !== '' ? $title : null, $teacherId]);
}

/** Dean must be role=dean AND approved (status=active) to manage their school. */
function inkwell_require_approved_dean() {
  $user = inkwell_require_role('dean');
  if ($user['status'] !== 'active') {
    http_response_code(403);
    // Handled in dean/dashboard.php with a friendlier message instead of dying here.
  }
  return $user;
}

/** Registrar must be role=registrar AND approved (status=active) to create/manage subjects. */
function inkwell_require_approved_registrar() {
  $user = inkwell_require_role('registrar');
  if ($user['status'] !== 'active') {
    http_response_code(403);
    // Handled in registrar/dashboard.php with a friendlier message instead of dying here.
  }
  return $user;
}

/** Remembers the last lesson a logged-in user viewed, for the "Continue lesson" nav link. Fails silently if the migration hasn't been run yet. */
function inkwell_update_last_lesson($userId, $cat, $slug) {
  try {
    $pdo = inkwell_db();
    $stmt = $pdo->prepare('UPDATE users SET last_lesson_cat = ?, last_lesson_slug = ?, last_lesson_at = NOW() WHERE id = ?');
    $stmt->execute([$cat, $slug, $userId]);
  } catch (PDOException $e) {
    // MIGRATION_ADD_last_lesson.sql not run yet — just skip silently.
  }
}
