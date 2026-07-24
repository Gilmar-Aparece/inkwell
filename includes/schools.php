<?php
/**
 * Schools + registrar-created teacher/dean accounts.
 *
 * Flow:
 *   1. A school + its first Registrar are created together, either:
 *        - publicly, by anyone, through the paid checkout at
 *          /create-school.php — see inkwell_create_school_checkout() in
 *          includes/billing.php. That Registrar starts 'pending' and is
 *          unlocked once payment is confirmed (free plan, an
 *          instant-activate payment method, or an admin approving the
 *          submission in /admin/payments.php); or
 *        - internally by an Admin, via /admin/schools.php, which creates
 *          the school (inkwell_create_school()) and optionally a Registrar
 *          for it (inkwell_admin_create_registrar(), active immediately).
 *   2. Once active, that Registrar can create Teacher and Dean accounts for
 *      their own school — inkwell_registrar_create_teacher() and
 *      inkwell_registrar_create_dean(). Those accounts start 'active'
 *      immediately (no separate admin approval, since the registrar is
 *      vouching for them) and are tagged with school_id + created_by.
 *   3. A Dean is view-only for the school profile (name/mission/logo) and
 *      the President signer — only Admin/Registrar can edit those. The Dean
 *      still manages their own personal Dean signature
 *      (inkwell_update_school_dean_signature()).
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/store.php';
require_once __DIR__ . '/departments.php';

/* ---------------- Schools ---------------- */

/**
 * Resolves a Dean's school via their own users.school_id, NOT
 * schools.dean_id — schools.dean_id only ever points at one "primary"
 * dean per school (kept around for legacy displays like the homepage
 * faculty card), but a school can have several active deans now, one
 * per department. Every dean account already has school_id set at
 * creation time (see inkwell_registrar_create_person()), so this works
 * for all of them, not just the primary one.
 */
function inkwell_get_school_by_dean($deanId) {
  $pdo = inkwell_db();
  $stmt = $pdo->prepare("SELECT s.* FROM schools s JOIN users u ON u.school_id = s.id WHERE u.id = ? AND u.role = 'dean'");
  $stmt->execute([$deanId]);
  return $stmt->fetch() ?: null;
}

function inkwell_get_school($id) {
  $pdo = inkwell_db();
  $stmt = $pdo->prepare('SELECT * FROM schools WHERE id = ?');
  $stmt->execute([$id]);
  return $stmt->fetch() ?: null;
}

function inkwell_list_schools() {
  $pdo = inkwell_db();
  return $pdo->query('SELECT s.*, u.name AS dean_name, u.email AS dean_email FROM schools s LEFT JOIN users u ON u.id = s.dean_id ORDER BY s.name ASC')->fetchAll();
}

/**
 * Schools with aggregate activity stats — teachers, students who picked
 * that school at registration, and certificates issued by the school's
 * teachers. Ordered by overall activity so the busiest schools come
 * first. Used for the homepage "Top schools" section and the admin
 * monitoring view (schools can exist with no dean assigned yet, so this
 * uses a LEFT JOIN). Schools are created/edited only by an Admin, via
 * inkwell_create_school() / inkwell_update_school().
 */
function inkwell_list_schools_with_stats() {
  $pdo = inkwell_db();
  $stmt = $pdo->query(
    "SELECT s.*, u.name AS dean_name, u.email AS dean_email,
            (SELECT COUNT(*) FROM users t WHERE t.role = 'teacher' AND t.school_id = s.id AND t.status != 'disabled') AS teacher_count,
            (SELECT COUNT(*) FROM users st WHERE st.role = 'student' AND st.school_id = s.id) AS student_count,
            (SELECT COUNT(*) FROM certificates c JOIN users tt ON tt.id = c.teacher_id WHERE tt.school_id = s.id) AS certificate_count
     FROM schools s
     LEFT JOIN users u ON u.id = s.dean_id
     ORDER BY (
       (SELECT COUNT(*) FROM users t WHERE t.role = 'teacher' AND t.school_id = s.id AND t.status != 'disabled')
       + (SELECT COUNT(*) FROM users st WHERE st.role = 'student' AND st.school_id = s.id)
       + (SELECT COUNT(*) FROM certificates c JOIN users tt ON tt.id = c.teacher_id WHERE tt.school_id = s.id)
     ) DESC, s.name ASC"
  );
  return $stmt->fetchAll();
}

/**
 * Single school's stat counts — used on the dean dashboard. Pass
 * $departmentId to scope teacher/subject/certificate counts to just that
 * department (a department-scoped Dean's own numbers); student_count
 * always stays school-wide, since students aren't tagged with a
 * department. Falls back to school-wide counts everywhere if the
 * department_id columns aren't available on this host yet.
 */
function inkwell_school_stats($schoolId, $departmentId = null) {
  $deptCols = inkwell_ensure_department_columns();
  $pdo = inkwell_db();
  $teacherDeptClause = ($departmentId && $deptCols['users']) ? ' AND department_id = ?' : '';
  $subjectDeptClause = ($departmentId && $deptCols['subjects']) ? ' AND sub.department_id = ?' : '';
  $certDeptClause = ($departmentId && $deptCols['users']) ? ' AND t.department_id = ?' : '';
  $stmt = $pdo->prepare(
    "SELECT
       (SELECT COUNT(*) FROM users WHERE role = 'teacher' AND school_id = ? AND status != 'disabled'{$teacherDeptClause}) AS teacher_count,
       (SELECT COUNT(*) FROM users WHERE role = 'student' AND school_id = ?) AS student_count,
       (SELECT COUNT(*) FROM subjects sub JOIN users t ON t.id = sub.teacher_id WHERE t.school_id = ?{$subjectDeptClause}) AS subject_count,
       (SELECT COUNT(*) FROM certificates c JOIN users t ON t.id = c.teacher_id WHERE t.school_id = ?{$certDeptClause}) AS certificate_count"
  );
  $params = [$schoolId];
  if ($teacherDeptClause) $params[] = $departmentId;
  $params[] = $schoolId;
  $params[] = $schoolId;
  if ($subjectDeptClause) $params[] = $departmentId;
  $params[] = $schoolId;
  if ($certDeptClause) $params[] = $departmentId;
  $stmt->execute($params);
  return $stmt->fetch();
}

/** Students who picked this school at registration — read-only roster for the dean to monitor (students self-register; deans don't manage them directly). */
function inkwell_school_students($schoolId) {
  $pdo = inkwell_db();
  $stmt = $pdo->prepare("SELECT * FROM users WHERE role = 'student' AND school_id = ? ORDER BY created_at DESC");
  $stmt->execute([$schoolId]);
  return $stmt->fetchAll();
}

/** Handles the logo upload (reuses the same uploads dir as the signature). Returns filename or null. */
function inkwell_handle_logo_upload($fileField = 'logo') {
  if (empty($_FILES[$fileField]['name'])) return ['ok' => true, 'filename' => null];

  $err = $_FILES[$fileField]['error'];
  if ($err !== UPLOAD_ERR_OK) {
    $messages = [
      UPLOAD_ERR_INI_SIZE => 'That image is too large for this server\'s upload limit. Try a smaller image (under 2MB, ideally under 1MB).',
      UPLOAD_ERR_FORM_SIZE => 'That image is too large.',
      UPLOAD_ERR_PARTIAL => 'The upload was interrupted — please try again.',
      UPLOAD_ERR_NO_TMP_DIR => 'Server is missing a temp folder for uploads — contact the site admin.',
      UPLOAD_ERR_CANT_WRITE => 'Could not write the file to disk — contact the site admin.',
    ];
    return ['ok' => false, 'error' => $messages[$err] ?? 'Upload failed (error code ' . $err . ').'];
  }

  $allowed = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];
  $tmpPath = $_FILES[$fileField]['tmp_name'];
  $info = @getimagesize($tmpPath);
  $mime = $info['mime'] ?? '';

  if ($_FILES[$fileField]['size'] > 2 * 1024 * 1024) {
    return ['ok' => false, 'error' => 'Image must be under 2MB.'];
  }
  if (!isset($allowed[$mime])) {
    return ['ok' => false, 'error' => 'Image must be a PNG, JPG, or WEBP file.'];
  }
  if (!is_dir(INKWELL_UPLOADS_DIR)) @mkdir(INKWELL_UPLOADS_DIR, 0775, true);
  $filename = 'upload_' . bin2hex(random_bytes(6)) . '.' . $allowed[$mime];
  if (!move_uploaded_file($tmpPath, INKWELL_UPLOADS_DIR . '/' . $filename)) {
    return ['ok' => false, 'error' => 'Could not save the uploaded image — check that assets/uploads/ is writable.'];
  }
  return ['ok' => true, 'filename' => $filename];
}

function inkwell_delete_upload($filename) {
  if ($filename && file_exists(INKWELL_UPLOADS_DIR . '/' . $filename)) @unlink(INKWELL_UPLOADS_DIR . '/' . $filename);
}

/** Admin creates a new school (no dean required yet — one gets assigned later when a registrar creates a Dean account for it). */
function inkwell_create_school($name, $mission = '', $logoFileField = 'logo') {
  $name = trim($name);
  $mission = trim($mission);
  if ($name === '') return ['ok' => false, 'error' => 'School name is required.'];

  $upload = inkwell_handle_logo_upload($logoFileField);
  if (!$upload['ok']) return ['ok' => false, 'error' => $upload['error']];

  $pdo = inkwell_db();
  $stmt = $pdo->prepare('INSERT INTO schools (dean_id, name, mission, logo) VALUES (NULL, ?, ?, ?)');
  $stmt->execute([$name, $mission !== '' ? $mission : null, $upload['filename']]);
  return ['ok' => true, 'id' => (int) $pdo->lastInsertId()];
}

/**
 * Admin creates a Registrar account directly for an existing school —
 * skips the payment/approval wait entirely, since the admin creating it
 * here already *is* the approval. Status is 'active' immediately, unlike
 * the /create-school.php checkout's Registrar, which starts 'pending'
 * until payment is confirmed (see inkwell_create_school_checkout() in
 * includes/billing.php).
 */
function inkwell_admin_create_registrar($adminId, $schoolId, $name, $email, $password, $idNumber = '', $course = '') {
  $school = inkwell_get_school($schoolId);
  if (!$school) return ['ok' => false, 'error' => 'School not found.'];

  $name = trim($name);
  $email = strtolower(trim($email));
  $idNumber = trim($idNumber);
  $course = trim($course);

  if ($name === '' || $email === '' || $password === '') {
    return ['ok' => false, 'error' => 'Name, email, and password are required.'];
  }
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    return ['ok' => false, 'error' => 'Enter a valid email address.'];
  }
  if (strlen($password) < 8) {
    return ['ok' => false, 'error' => 'Password must be at least 8 characters.'];
  }

  $pdo = inkwell_db();
  $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
  $stmt->execute([$email]);
  if ($stmt->fetch()) {
    return ['ok' => false, 'error' => 'An account with that email already exists.'];
  }

  $stmt = $pdo->prepare('INSERT INTO users (role, name, email, password_hash, status, id_number, course, school_id, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
  $stmt->execute(['registrar', $name, $email, password_hash($password, PASSWORD_DEFAULT), 'active', $idNumber, $course, $schoolId, $adminId]);

  return ['ok' => true, 'id' => (int) $pdo->lastInsertId()];
}

/** Updates the school name, mission, and/or logo. Callable by Admin or Registrar only — the Dean is view-only for this. Pass $logoFileField = null to leave the logo untouched. */
function inkwell_update_school($schoolId, $name, $mission = '', $logoFileField = 'logo') {
  $school = inkwell_get_school($schoolId);
  if (!$school) return ['ok' => false, 'error' => 'School not found.'];

  $name = trim($name);
  $mission = trim($mission);
  if ($name === '') return ['ok' => false, 'error' => 'School name is required.'];

  $newLogo = $school['logo'];
  if ($logoFileField !== null) {
    $upload = inkwell_handle_logo_upload($logoFileField);
    if (!$upload['ok']) return ['ok' => false, 'error' => $upload['error']];
    if ($upload['filename']) {
      inkwell_delete_upload($school['logo']);
      $newLogo = $upload['filename'];
    }
  }

  $pdo = inkwell_db();
  $stmt = $pdo->prepare('UPDATE schools SET name = ?, mission = ?, logo = ? WHERE id = ?');
  $stmt->execute([$name, $mission !== '' ? $mission : null, $newLogo, $schoolId]);
  return ['ok' => true];
}

/**
 * Updates the certificate signer (school principal / president) shown at
 * the bottom of certificates issued under this school's exams. Callable by
 * Admin or Registrar only — moved off the Dean, who now only manages their
 * own personal Dean signature via inkwell_update_school_dean_signature().
 * Pass $signatureFileField = null to leave the signature image untouched,
 * or a $_FILES field name to replace it.
 */
function inkwell_update_school_signer($schoolId, $name, $title, $signatureFileField = null) {
  $school = inkwell_get_school($schoolId);
  if (!$school) return ['ok' => false, 'error' => 'School not found.'];

  $name = trim($name);
  $title = trim($title);

  $newSignature = $school['signer_signature'];
  if ($signatureFileField !== null) {
    $upload = inkwell_handle_logo_upload($signatureFileField);
    if (!$upload['ok']) return ['ok' => false, 'error' => $upload['error']];
    if ($upload['filename']) {
      inkwell_delete_upload($school['signer_signature']);
      $newSignature = $upload['filename'];
    }
  }

  $pdo = inkwell_db();
  $stmt = $pdo->prepare('UPDATE schools SET signer_name = ?, signer_title = ?, signer_signature = ? WHERE id = ?');
  $stmt->execute([$name !== '' ? $name : null, $title !== '' ? $title : null, $newSignature, $schoolId]);
  return ['ok' => true];
}

/**
 * Updates the Dean's own signature block shown on certificates, separate
 * from inkwell_update_school_signer() (which sets the President/Principal
 * fields). The dean's *name* always comes live from their account
 * (users.name) — only the title text and signature image live here. Pass
 * $signatureFileField = null to leave the signature image untouched.
 */
function inkwell_update_school_dean_signature($schoolId, $title, $signatureFileField = null) {
  $pdo = inkwell_db();
  try {
    $existing = $pdo->query('SHOW COLUMNS FROM schools')->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('dean_signature', $existing, true)) {
      $pdo->exec('ALTER TABLE schools ADD COLUMN dean_signer_title VARCHAR(150) DEFAULT NULL');
      $pdo->exec('ALTER TABLE schools ADD COLUMN dean_signature VARCHAR(255) DEFAULT NULL');
    }
  } catch (PDOException $e) {
    return ['ok' => false, 'error' => "This host isn't letting the app add the dean-signature columns automatically. Run MIGRATION_ADD_dean_signature.sql once via phpMyAdmin, then try again."];
  }

  $school = inkwell_get_school($schoolId);
  if (!$school) return ['ok' => false, 'error' => 'School not found.'];

  $title = trim($title);
  $newSignature = $school['dean_signature'] ?? null;
  if ($signatureFileField !== null) {
    $upload = inkwell_handle_logo_upload($signatureFileField);
    if (!$upload['ok']) return ['ok' => false, 'error' => $upload['error']];
    if ($upload['filename']) {
      inkwell_delete_upload($school['dean_signature'] ?? null);
      $newSignature = $upload['filename'];
    }
  }

  $stmt = $pdo->prepare('UPDATE schools SET dean_signer_title = ?, dean_signature = ? WHERE id = ?');
  $stmt->execute([$title !== '' ? $title : null, $newSignature, $schoolId]);
  return ['ok' => true];
}

/* ---------------- Registrar-managed teachers & deans ---------------- */

/**
 * Pass $activeOnly = true to exclude disabled accounts — used for the
 * public "faculty" preview on the registration page. The dean dashboard
 * calls this with defaults so it still sees disabled teachers too. Pass
 * $departmentId to scope to just that department (a department-scoped
 * Dean's own teacher list) — ignored if department_id isn't available on
 * this host yet.
 */
function inkwell_list_school_teachers($schoolId, $activeOnly = false, $departmentId = null) {
  $deptCols = inkwell_ensure_department_columns();
  $pdo = inkwell_db();
  $sql = "SELECT * FROM users WHERE role = 'teacher' AND school_id = ?" . ($activeOnly ? " AND status = 'active'" : '');
  $params = [$schoolId];
  if ($departmentId && $deptCols['users']) {
    $sql .= ' AND department_id = ?';
    $params[] = $departmentId;
  }
  $sql .= ' ORDER BY created_at DESC';
  $stmt = $pdo->prepare($sql);
  $stmt->execute($params);
  return $stmt->fetchAll();
}

/**
 * Renders the horizontally-swipeable "Faculty & Dean" carousel for a
 * school page: the dean's card first, then one card per active teacher.
 * Teacher cards reuse the existing teacher-profile popup (same
 * data-modal-open="teacherProfileModal" trigger as the old plain list);
 * the dean card is a simple badge since there's no separate dean-profile
 * popup yet.
 */
function inkwell_render_faculty_dean_swipe($faculty, $dean) {
  if (empty($faculty) && !$dean) {
    return '<p class="admin-sub">No faculty listed yet.</p>';
  }
  ob_start();
  ?>
  <div class="school-swipe-row faculty-swipe-row">
    <?php if ($dean): ?>
      <div class="school-swipe-card faculty-swipe-card dean-card">
        <span class="faculty-swipe-avatar dean-avatar">
          <?php if (!empty($dean['avatar'])): ?>
            <img src="/assets/uploads/<?php echo htmlspecialchars($dean['avatar']); ?>" alt="" loading="lazy">
          <?php else: ?>
            <?php echo strtoupper(substr($dean['name'], 0, 1)); ?>
          <?php endif; ?>
        </span>
        <span class="faculty-swipe-name"><?php echo htmlspecialchars($dean['name']); ?></span>
        <span class="post-role-chip role-dean">Dean</span>
      </div>
    <?php endif; ?>
    <?php foreach ($faculty as $f): ?>
      <button type="button" class="school-swipe-card faculty-swipe-card" data-teacher-id="<?php echo (int) $f['id']; ?>" data-modal-open="teacherProfileModal">
        <span class="faculty-swipe-avatar">
          <?php if (!empty($f['avatar'])): ?>
            <img src="/assets/uploads/<?php echo htmlspecialchars($f['avatar']); ?>" alt="" loading="lazy">
          <?php else: ?>
            <?php echo strtoupper(substr($f['name'], 0, 1)); ?>
          <?php endif; ?>
        </span>
        <span class="faculty-swipe-name"><?php echo htmlspecialchars($f['name']); ?></span>
        <span class="post-role-chip role-teacher">Teacher</span>
        <?php if (!empty($f['course'])): ?><span class="faculty-swipe-course"><?php echo htmlspecialchars($f['course']); ?></span><?php endif; ?>
      </button>
    <?php endforeach; ?>
  </div>
  <?php
  return ob_get_clean();
}

/**
 * Groups a school's active Deans + teachers by department — one entry per
 * department that actually has someone in it (Dean and/or teachers),
 * ordered by department code, followed by a trailing "unassigned" bucket
 * for legacy accounts that predate department_id (or hosts where that
 * column isn't available). Used to render the department-grouped Faculty
 * & Dean sections on school.php / my-school.php. Returns [] if this host
 * has no department_id columns (falls back to the old flat swipe row).
 */
function inkwell_school_faculty_by_department($schoolId) {
  $deptCols = inkwell_ensure_department_columns();
  if (!$deptCols['users']) return [];

  $departments = inkwell_list_departments();
  $allDeans = array_values(array_filter(inkwell_list_school_deans($schoolId), function ($d) { return $d['status'] !== 'disabled'; }));
  $allTeachers = inkwell_list_school_teachers($schoolId, true);

  $groups = [];
  foreach ($departments as $dept) {
    $deptId = (int) $dept['id'];
    $deans = array_values(array_filter($allDeans, function ($d) use ($deptId) { return (int) ($d['department_id'] ?? 0) === $deptId; }));
    $teachers = array_values(array_filter($allTeachers, function ($t) use ($deptId) { return (int) ($t['department_id'] ?? 0) === $deptId; }));
    if (empty($deans) && empty($teachers)) continue;
    $groups[] = ['department' => $dept, 'deans' => $deans, 'teachers' => $teachers];
  }

  $unassignedDeans = array_values(array_filter($allDeans, function ($d) { return empty($d['department_id']); }));
  $unassignedTeachers = array_values(array_filter($allTeachers, function ($t) { return empty($t['department_id']); }));
  if (!empty($unassignedDeans) || !empty($unassignedTeachers)) {
    $groups[] = ['department' => null, 'deans' => $unassignedDeans, 'teachers' => $unassignedTeachers];
  }

  return $groups;
}

/**
 * Renders one swipeable "Faculty & Dean" row per department (Dean card(s)
 * first, then that department's teachers), each in its own labeled
 * sub-section — the department-scoped version of
 * inkwell_render_faculty_dean_swipe(). Both Dean and teacher cards are
 * clickable now (they open the same faculty-profile popup, generalized to
 * show a department overview for Deans), and cards inside the same row
 * can be swiped/arrowed between once the popup is open (see
 * assets/js/teacher-profile.js).
 */
function inkwell_render_department_faculty_groups($groups) {
  if (empty($groups)) {
    return '<p class="admin-sub">No faculty listed yet.</p>';
  }
  ob_start();
  foreach ($groups as $g):
    $dept = $g['department'];
    $count = count($g['deans']) + count($g['teachers']);
    ?>
    <div class="dept-faculty-group">
      <div class="dept-faculty-head">
        <h3><?php echo $dept ? htmlspecialchars($dept['code']) . ' <span class="admin-sub">— ' . htmlspecialchars($dept['name']) . '</span>' : 'General faculty'; ?></h3>
        <span class="admin-sub"><?php echo (int) $count; ?> <?php echo $count === 1 ? 'person' : 'people'; ?></span>
      </div>
      <div class="school-swipe-row faculty-swipe-row">
        <?php foreach ($g['deans'] as $dean): ?>
          <button type="button" class="school-swipe-card faculty-swipe-card dean-card dean-card-clickable"
            data-person-id="<?php echo (int) $dean['id']; ?>" data-person-role="dean" data-person-name="<?php echo htmlspecialchars($dean['name']); ?>"
            data-modal-open="teacherProfileModal">
            <span class="faculty-swipe-avatar dean-avatar">
              <?php if (!empty($dean['avatar'])): ?>
                <img src="/assets/uploads/<?php echo htmlspecialchars($dean['avatar']); ?>" alt="" loading="lazy">
              <?php else: ?>
                <?php echo strtoupper(substr($dean['name'], 0, 1)); ?>
              <?php endif; ?>
            </span>
            <span class="faculty-swipe-name"><?php echo htmlspecialchars($dean['name']); ?></span>
            <span class="post-role-chip role-dean">Dean</span>
          </button>
        <?php endforeach; ?>
        <?php foreach ($g['teachers'] as $f): ?>
          <button type="button" class="school-swipe-card faculty-swipe-card"
            data-person-id="<?php echo (int) $f['id']; ?>" data-person-role="teacher" data-person-name="<?php echo htmlspecialchars($f['name']); ?>"
            data-teacher-id="<?php echo (int) $f['id']; ?>" data-modal-open="teacherProfileModal">
            <span class="faculty-swipe-avatar">
              <?php if (!empty($f['avatar'])): ?>
                <img src="/assets/uploads/<?php echo htmlspecialchars($f['avatar']); ?>" alt="" loading="lazy">
              <?php else: ?>
                <?php echo strtoupper(substr($f['name'], 0, 1)); ?>
              <?php endif; ?>
            </span>
            <span class="faculty-swipe-name"><?php echo htmlspecialchars($f['name']); ?></span>
            <span class="post-role-chip role-teacher">Teacher</span>
            <?php if (!empty($f['course'])): ?><span class="faculty-swipe-course"><?php echo htmlspecialchars($f['course']); ?></span><?php endif; ?>
          </button>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endforeach;
  return ob_get_clean();
}

/**
 * Grid version of inkwell_render_faculty_dean_swipe() — same cards, same
 * teacher-profile popup trigger, but laid out in a wrapping grid instead
 * of a horizontal swipe row. Used on the dedicated school-faculty.php
 * page where every person gets their own separate, fully-visible card
 * instead of being tucked into a scroller.
 */
function inkwell_render_faculty_dean_grid($faculty, $dean) {
  if (empty($faculty) && !$dean) {
    return '<p class="admin-sub">No faculty listed yet.</p>';
  }
  ob_start();
  ?>
  <div class="faculty-grid-row">
    <?php if ($dean): ?>
      <div class="faculty-grid-card dean-card">
        <span class="faculty-swipe-avatar dean-avatar">
          <?php if (!empty($dean['avatar'])): ?>
            <img src="/assets/uploads/<?php echo htmlspecialchars($dean['avatar']); ?>" alt="" loading="lazy">
          <?php else: ?>
            <?php echo strtoupper(substr($dean['name'], 0, 1)); ?>
          <?php endif; ?>
        </span>
        <span class="faculty-swipe-name"><?php echo htmlspecialchars($dean['name']); ?></span>
        <span class="post-role-chip role-dean">Dean</span>
      </div>
    <?php endif; ?>
    <?php foreach ($faculty as $f): ?>
      <button type="button" class="faculty-grid-card" data-teacher-id="<?php echo (int) $f['id']; ?>" data-modal-open="teacherProfileModal">
        <span class="faculty-swipe-avatar">
          <?php if (!empty($f['avatar'])): ?>
            <img src="/assets/uploads/<?php echo htmlspecialchars($f['avatar']); ?>" alt="" loading="lazy">
          <?php else: ?>
            <?php echo strtoupper(substr($f['name'], 0, 1)); ?>
          <?php endif; ?>
        </span>
        <span class="faculty-swipe-name"><?php echo htmlspecialchars($f['name']); ?></span>
        <span class="post-role-chip role-teacher">Teacher</span>
        <?php if (!empty($f['course'])): ?><span class="faculty-swipe-course"><?php echo htmlspecialchars($f['course']); ?></span><?php endif; ?>
      </button>
    <?php endforeach; ?>
  </div>
  <?php
  return ob_get_clean();
}

/**
 * Grid version of inkwell_render_department_faculty_groups() — one
 * heading + wrapping grid per department, for the dedicated
 * school-faculty.php page (as opposed to the horizontal swipe row used
 * in the compact preview on my-school.php / school.php).
 */
function inkwell_render_department_faculty_grid_groups($groups) {
  if (empty($groups)) {
    return '<p class="admin-sub">No faculty listed yet.</p>';
  }
  ob_start();
  foreach ($groups as $g):
    $dept = $g['department'];
    $count = count($g['deans']) + count($g['teachers']);
    ?>
    <div class="dept-faculty-group">
      <div class="dept-faculty-head">
        <h3><?php echo $dept ? htmlspecialchars($dept['code']) . ' <span class="admin-sub">— ' . htmlspecialchars($dept['name']) . '</span>' : 'General faculty'; ?></h3>
        <span class="admin-sub"><?php echo (int) $count; ?> <?php echo $count === 1 ? 'person' : 'people'; ?></span>
      </div>
      <div class="faculty-grid-row">
        <?php foreach ($g['deans'] as $dean): ?>
          <button type="button" class="faculty-grid-card dean-card dean-card-clickable"
            data-person-id="<?php echo (int) $dean['id']; ?>" data-person-role="dean" data-person-name="<?php echo htmlspecialchars($dean['name']); ?>"
            data-modal-open="teacherProfileModal">
            <span class="faculty-swipe-avatar dean-avatar">
              <?php if (!empty($dean['avatar'])): ?>
                <img src="/assets/uploads/<?php echo htmlspecialchars($dean['avatar']); ?>" alt="" loading="lazy">
              <?php else: ?>
                <?php echo strtoupper(substr($dean['name'], 0, 1)); ?>
              <?php endif; ?>
            </span>
            <span class="faculty-swipe-name"><?php echo htmlspecialchars($dean['name']); ?></span>
            <span class="post-role-chip role-dean">Dean</span>
          </button>
        <?php endforeach; ?>
        <?php foreach ($g['teachers'] as $f): ?>
          <button type="button" class="faculty-grid-card"
            data-person-id="<?php echo (int) $f['id']; ?>" data-person-role="teacher" data-person-name="<?php echo htmlspecialchars($f['name']); ?>"
            data-teacher-id="<?php echo (int) $f['id']; ?>" data-modal-open="teacherProfileModal">
            <span class="faculty-swipe-avatar">
              <?php if (!empty($f['avatar'])): ?>
                <img src="/assets/uploads/<?php echo htmlspecialchars($f['avatar']); ?>" alt="" loading="lazy">
              <?php else: ?>
                <?php echo strtoupper(substr($f['name'], 0, 1)); ?>
              <?php endif; ?>
            </span>
            <span class="faculty-swipe-name"><?php echo htmlspecialchars($f['name']); ?></span>
            <span class="post-role-chip role-teacher">Teacher</span>
            <?php if (!empty($f['course'])): ?><span class="faculty-swipe-course"><?php echo htmlspecialchars($f['course']); ?></span><?php endif; ?>
          </button>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endforeach;
  return ob_get_clean();
}

/**
 * Renders each department as its OWN separate card (not nested inside
 * one big "Faculty & Dean" box) — used on the dedicated
 * school-faculty.php page. Each department gets a rotating accent color
 * and icon so departments read as distinct sections at a glance, with
 * that department's Dean(s) + teachers in a wrapping grid inside.
 */
function inkwell_render_department_faculty_cards($groups) {
  if (empty($groups)) {
    return '<p class="admin-sub">No faculty listed yet.</p>';
  }
  $accents = ['nib', 'clay', 'pine', 'nib-glow'];
  $icons = ['🏛️', '💻', '🍳', '🎨', '📐', '🔬'];
  ob_start();
  foreach ($groups as $i => $g):
    $dept = $g['department'];
    $count = count($g['deans']) + count($g['teachers']);
    $accent = $accents[$i % count($accents)];
    $icon = $icons[$i % count($icons)];
    ?>
    <section class="dept-card dept-card-<?php echo $accent; ?>">
      <div class="dept-card-head">
        <span class="dept-card-icon" aria-hidden="true"><?php echo $icon; ?></span>
        <div class="dept-card-head-text">
          <h2><?php echo $dept ? htmlspecialchars($dept['code']) : 'General faculty'; ?></h2>
          <?php if ($dept): ?><span class="admin-sub"><?php echo htmlspecialchars($dept['name']); ?></span><?php endif; ?>
        </div>
        <span class="dept-card-count"><?php echo (int) $count; ?> <?php echo $count === 1 ? 'person' : 'people'; ?></span>
      </div>
      <div class="faculty-grid-row">
        <?php foreach ($g['deans'] as $dean): ?>
          <button type="button" class="faculty-grid-card dean-card dean-card-clickable"
            data-person-id="<?php echo (int) $dean['id']; ?>" data-person-role="dean" data-person-name="<?php echo htmlspecialchars($dean['name']); ?>"
            data-modal-open="teacherProfileModal">
            <span class="faculty-swipe-avatar dean-avatar">
              <?php if (!empty($dean['avatar'])): ?>
                <img src="/assets/uploads/<?php echo htmlspecialchars($dean['avatar']); ?>" alt="" loading="lazy">
              <?php else: ?>
                <?php echo strtoupper(substr($dean['name'], 0, 1)); ?>
              <?php endif; ?>
            </span>
            <span class="faculty-swipe-name"><?php echo htmlspecialchars($dean['name']); ?></span>
            <span class="post-role-chip role-dean">Dean</span>
          </button>
        <?php endforeach; ?>
        <?php foreach ($g['teachers'] as $f): ?>
          <button type="button" class="faculty-grid-card"
            data-person-id="<?php echo (int) $f['id']; ?>" data-person-role="teacher" data-person-name="<?php echo htmlspecialchars($f['name']); ?>"
            data-teacher-id="<?php echo (int) $f['id']; ?>" data-modal-open="teacherProfileModal">
            <span class="faculty-swipe-avatar">
              <?php if (!empty($f['avatar'])): ?>
                <img src="/assets/uploads/<?php echo htmlspecialchars($f['avatar']); ?>" alt="" loading="lazy">
              <?php else: ?>
                <?php echo strtoupper(substr($f['name'], 0, 1)); ?>
              <?php endif; ?>
            </span>
            <span class="faculty-swipe-name"><?php echo htmlspecialchars($f['name']); ?></span>
            <span class="post-role-chip role-teacher">Teacher</span>
            <?php if (!empty($f['course'])): ?><span class="faculty-swipe-course"><?php echo htmlspecialchars($f['course']); ?></span><?php endif; ?>
          </button>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endforeach;
  return ob_get_clean();
}

/**
 * Registrar adds a Teacher account for their own school. These accounts
 * start 'active' immediately (the registrar is the approval), unlike
 * self-registered teachers, which no longer happens — teacher accounts
 * are only ever created this way now. $departmentId tags which
 * department (BSEED/BSIT/BSHM/...) this teacher belongs to, so a
 * department-scoped Dean sees them.
 */
function inkwell_registrar_create_teacher($registrarId, $schoolId, $name, $email, $password, $idNumber = '', $course = '', $departmentId = null) {
  return inkwell_registrar_create_person('teacher', $registrarId, $schoolId, $name, $email, $password, $idNumber, $course, $departmentId);
}

/**
 * Registrar adds a Dean account for their own school, scoped to one
 * department. A school can now have several active deans at once — one
 * per department — instead of just one. This only fails if that
 * *specific* department at this school already has an active dean;
 * disable/remove that one first via inkwell_remove_school_dean() to
 * replace them. If the school doesn't have a "primary" dean yet
 * (schools.dean_id), this new dean is set as that primary — purely for
 * legacy single-dean displays like the homepage faculty card, which only
 * ever show one dean per school.
 */
function inkwell_registrar_create_dean($registrarId, $schoolId, $name, $email, $password, $idNumber = '', $course = '', $departmentId = null) {
  $school = inkwell_get_school($schoolId);
  if (!$school) return ['ok' => false, 'error' => 'School not found.'];

  $deptCols = inkwell_ensure_department_columns();
  if ($departmentId && $deptCols['users']) {
    $pdo = inkwell_db();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE role = 'dean' AND school_id = ? AND department_id = ? AND status != 'disabled'");
    $stmt->execute([$schoolId, $departmentId]);
    $existing = $stmt->fetch();
    if ($existing) {
      $dept = inkwell_get_department($departmentId);
      $deptLabel = $dept ? $dept['code'] : 'this department';
      return ['ok' => false, 'error' => "This school already has a Dean for {$deptLabel} ({$existing['name']}). Remove them first if you want to replace them."];
    }
  }

  $result = inkwell_registrar_create_person('dean', $registrarId, $schoolId, $name, $email, $password, $idNumber, $course, $departmentId);
  if (!$result['ok']) return $result;

  if (empty($school['dean_id'])) {
    $pdo = inkwell_db();
    $stmt = $pdo->prepare('UPDATE schools SET dean_id = ? WHERE id = ?');
    $stmt->execute([$result['id'], $schoolId]);
  }

  return $result;
}

/** Shared insert logic behind inkwell_registrar_create_teacher() / inkwell_registrar_create_dean(). $departmentId is only stored when the column is available on this host (see inkwell_ensure_department_columns()). */
function inkwell_registrar_create_person($role, $registrarId, $schoolId, $name, $email, $password, $idNumber = '', $course = '', $departmentId = null) {
  $name = trim($name);
  $email = strtolower(trim($email));
  $idNumber = trim($idNumber);
  $course = trim($course);
  $deptCols = inkwell_ensure_department_columns();

  if ($name === '' || $email === '' || $password === '') {
    return ['ok' => false, 'error' => 'Name, email, and password are required.'];
  }
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    return ['ok' => false, 'error' => 'Enter a valid email address.'];
  }
  if (strlen($password) < 8) {
    return ['ok' => false, 'error' => 'Password must be at least 8 characters.'];
  }

  $pdo = inkwell_db();
  $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
  $stmt->execute([$email]);
  if ($stmt->fetch()) {
    return ['ok' => false, 'error' => 'An account with that email already exists.'];
  }

  $fields = ['role', 'name', 'email', 'password_hash', 'status', 'id_number', 'course', 'school_id', 'created_by'];
  $placeholders = array_fill(0, 9, '?');
  $values = [$role, $name, $email, password_hash($password, PASSWORD_DEFAULT), 'active', $idNumber, $course, $schoolId, $registrarId];
  if ($deptCols['users'] && $departmentId) {
    $fields[] = 'department_id';
    $placeholders[] = '?';
    $values[] = $departmentId;
  }
  $stmt = $pdo->prepare('INSERT INTO users (' . implode(', ', $fields) . ') VALUES (' . implode(', ', $placeholders) . ')');
  $stmt->execute($values);

  return ['ok' => true, 'id' => (int) $pdo->lastInsertId()];
}

/** Lists Dean accounts at one school — used by the registrar dean page. Pass $departmentId to scope to just that department (up to one active dean per department, but keeps history of disabled ones per department). */
function inkwell_list_school_deans($schoolId, $departmentId = null) {
  $deptCols = inkwell_ensure_department_columns();
  $pdo = inkwell_db();
  $sql = "SELECT * FROM users WHERE role = 'dean' AND school_id = ?";
  $params = [$schoolId];
  if ($departmentId && $deptCols['users']) {
    $sql .= ' AND department_id = ?';
    $params[] = $departmentId;
  }
  $sql .= ' ORDER BY created_at DESC';
  $stmt = $pdo->prepare($sql);
  $stmt->execute($params);
  return $stmt->fetchAll();
}

/** Disables a Dean account. Also clears schools.dean_id, but only if it was pointing at this exact dean — other departments' deans are untouched — so the registrar can add a replacement for that department (or a new primary gets picked up next time one is created). */
function inkwell_remove_school_dean($schoolId, $deanId) {
  $pdo = inkwell_db();
  $stmt = $pdo->prepare("UPDATE users SET status = 'disabled' WHERE id = ? AND school_id = ? AND role = 'dean'");
  $stmt->execute([$deanId, $schoolId]);
  $stmt = $pdo->prepare('UPDATE schools SET dean_id = NULL WHERE id = ? AND dean_id = ?');
  return $stmt->execute([$schoolId, $deanId]);
}

/** Lets an already-approved teacher attach themselves to a school from
 * their account page, since register.php left the field optional and
 * there was previously no way to fix that afterwards. */
function inkwell_teacher_join_school($teacherId, $schoolId) {
  $pdo = inkwell_db();
  $school = inkwell_get_school($schoolId);
  if (!$school) {
    return ['ok' => false, 'error' => 'That school could not be found.'];
  }
  $stmt = $pdo->prepare("UPDATE users SET school_id = ? WHERE id = ? AND role = 'teacher'");
  $stmt->execute([$schoolId, $teacherId]);
  return ['ok' => true];
}

/** Generic join-school for any role that doesn't yet belong to one (teacher or student). Deans always have exactly one school (their own) and are excluded. */
function inkwell_user_join_school($userId, $schoolId) {
  $pdo = inkwell_db();
  $school = inkwell_get_school($schoolId);
  if (!$school) {
    return ['ok' => false, 'error' => 'That school could not be found.'];
  }
  $stmt = $pdo->prepare("UPDATE users SET school_id = ? WHERE id = ? AND role IN ('teacher','student')");
  $stmt->execute([$schoolId, $userId]);
  return ['ok' => true];
}

function inkwell_remove_school_teacher($schoolId, $teacherId) {
  $pdo = inkwell_db();
  $stmt = $pdo->prepare("UPDATE users SET status = 'disabled' WHERE id = ? AND school_id = ? AND role = 'teacher'");
  return $stmt->execute([$teacherId, $schoolId]);
}
