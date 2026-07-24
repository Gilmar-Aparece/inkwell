<?php
/**
 * Student roster + profile helpers shared by the dean and teacher
 * dashboards: clickable student profile popups, avatar uploads, and the
 * "top students" showcase that teachers/deans curate per school.
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/schools.php';

/* ---------------- Avatars ---------------- */

/**
 * Saves a new avatar for a user from an uploaded file field, deleting the
 * old one. Reuses the same validated upload handler as school logos
 * (PNG/JPG/WEBP, under 2MB). Any logged-in role (student, teacher, dean)
 * can set a profile photo via account.php.
 */
function inkwell_update_user_avatar($userId, $fileField = 'avatar') {
  $upload = inkwell_handle_logo_upload($fileField);
  if (!$upload['ok']) return ['ok' => false, 'error' => $upload['error']];
  if (!$upload['filename']) return ['ok' => false, 'error' => 'Choose an image to upload.'];

  $pdo = inkwell_db();
  $stmt = $pdo->prepare('SELECT avatar FROM users WHERE id = ?');
  $stmt->execute([$userId]);
  $old = $stmt->fetchColumn();

  $stmt = $pdo->prepare('UPDATE users SET avatar = ? WHERE id = ?');
  $stmt->execute([$upload['filename'], $userId]);
  if ($old) inkwell_delete_upload($old);

  return ['ok' => true, 'filename' => $upload['filename']];
}

/** Clears a user's profile photo, falling back to the initial-letter placeholder. */
function inkwell_remove_user_avatar($userId) {
  $pdo = inkwell_db();
  $stmt = $pdo->prepare('SELECT avatar FROM users WHERE id = ?');
  $stmt->execute([$userId]);
  $old = $stmt->fetchColumn();

  $stmt = $pdo->prepare('UPDATE users SET avatar = NULL WHERE id = ?');
  $stmt->execute([$userId]);
  if ($old) inkwell_delete_upload($old);

  return ['ok' => true];
}

/* ---------------- Rosters ---------------- */

/** Every student enrolled (approved) in any of this teacher's subjects, deduped. */
function inkwell_teacher_students($teacherId) {
  $pdo = inkwell_db();
  $stmt = $pdo->prepare(
    "SELECT DISTINCT u.* FROM users u
     JOIN enrollments e ON e.student_id = u.id AND e.status = 'approved'
     JOIN subjects s ON s.id = e.subject_id
     WHERE s.teacher_id = ? AND u.role = 'student'
     ORDER BY u.created_at DESC"
  );
  $stmt->execute([$teacherId]);
  return $stmt->fetchAll();
}

/** True if this student is enrolled in one of the teacher's subjects. */
function inkwell_is_teacher_student($teacherId, $studentId) {
  $pdo = inkwell_db();
  $stmt = $pdo->prepare(
    "SELECT 1 FROM enrollments e
     JOIN subjects s ON s.id = e.subject_id
     WHERE s.teacher_id = ? AND e.student_id = ? AND e.status = 'approved' LIMIT 1"
  );
  $stmt->execute([$teacherId, $studentId]);
  return (bool) $stmt->fetchColumn();
}

/**
 * Access check for the profile popup: a teacher can view any student
 * enrolled in one of their subjects; a dean can view any student who
 * picked their school.
 */
function inkwell_viewer_can_see_student($viewer, $student) {
  if (!$viewer || !$student || $student['role'] !== 'student') return false;
  if ($viewer['role'] === 'teacher') return inkwell_is_teacher_student($viewer['id'], $student['id']);
  if ($viewer['role'] === 'dean') {
    $school = inkwell_get_school_by_dean($viewer['id']);
    return $school && (int) $student['school_id'] === (int) $school['id'];
  }
  if ($viewer['role'] === 'registrar') {
    return !empty($viewer['school_id']) && (int) $student['school_id'] === (int) $viewer['school_id'];
  }
  return false;
}

/** Full profile bundle for the student detail popup. */
function inkwell_get_student_profile($studentId) {
  require_once __DIR__ . '/exams_db.php';
  $pdo = inkwell_db();
  $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'student'");
  $stmt->execute([$studentId]);
  $student = $stmt->fetch();
  if (!$student) return null;

  $school = $student['school_id'] ? inkwell_get_school($student['school_id']) : null;
  $subjects = inkwell_student_enrolled_subjects($studentId);
  $attempts = inkwell_student_attempts($studentId);
  $certificates = inkwell_student_certificates($studentId);
  $graded = array_filter($attempts, function ($a) { return $a['status'] === 'graded'; });
  $passed = array_filter($graded, function ($a) { return (bool) $a['passed']; });

  return [
    'student' => $student,
    'school' => $school,
    'subjects' => $subjects,
    'attempts' => $attempts,
    'certificates' => $certificates,
    'stats' => [
      'subject_count' => count($subjects),
      'attempt_count' => count($attempts),
      'passed_count' => count($passed),
      'certificate_count' => count($certificates),
    ],
  ];
}

/* ---------------- Teacher profile popup ---------------- */

/**
 * Public profile bundle for the faculty-info popup shown when someone
 * clicks a teacher's or dean's card (the "Browse & join classes" cards,
 * or the department-grouped Faculty & Dean rows on school.php /
 * my-school.php). Unlike the student profile popup this is intentionally
 * public (no login required) since the info it lists is already visible
 * to anyone browsing those pages. Pass $role = 'dean' for a Dean account
 * — instead of subjects taught, it returns the department they oversee
 * and the teachers in it.
 */
function inkwell_get_teacher_profile($teacherId, $role = 'teacher') {
  require_once __DIR__ . '/exams_db.php';
  require_once __DIR__ . '/departments.php';
  require_once __DIR__ . '/schools.php';
  $role = $role === 'dean' ? 'dean' : 'teacher';

  $pdo = inkwell_db();
  $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ? AND role = ? AND status = ?');
  $stmt->execute([$teacherId, $role, 'active']);
  $person = $stmt->fetch();
  if (!$person) return null;

  $school = $person['school_id'] ? inkwell_get_school($person['school_id']) : null;
  $department = !empty($person['department_id']) ? inkwell_get_department($person['department_id']) : null;

  if ($role === 'dean') {
    $teachers = $person['school_id']
      ? inkwell_list_school_teachers($person['school_id'], true, $department ? $department['id'] : null)
      : [];
    $stats = $person['school_id']
      ? inkwell_school_stats($person['school_id'], $department ? $department['id'] : null)
      : ['teacher_count' => 0, 'subject_count' => 0, 'student_count' => 0];

    return [
      'teacher' => $person,
      'role' => 'dean',
      'school' => $school,
      'department' => $department,
      'teachers' => $teachers,
      'stats' => [
        'teacher_count' => (int) $stats['teacher_count'],
        'subject_count' => (int) $stats['subject_count'],
        'student_count' => (int) $stats['student_count'],
      ],
    ];
  }

  $subjects = inkwell_teacher_subjects($teacherId);

  return [
    'teacher' => $person,
    'role' => 'teacher',
    'school' => $school,
    'department' => $department,
    'subjects' => $subjects,
    'stats' => [
      'subject_count' => count($subjects),
      'exam_count' => array_sum(array_column($subjects, 'exam_count')),
      'student_count' => array_sum(array_column($subjects, 'student_count')),
    ],
  ];
}

/* ---------------- Featured / "top" students ---------------- */

/** Top students a dean or teacher has featured for a school, newest first. */
function inkwell_featured_students($schoolId) {
  $pdo = inkwell_db();
  $stmt = $pdo->prepare(
    "SELECT f.*, u.name, u.email, u.avatar, u.course, u.id_number, a.name AS added_by_name, a.role AS added_by_role
     FROM featured_students f
     JOIN users u ON u.id = f.student_id
     LEFT JOIN users a ON a.id = f.added_by
     WHERE f.school_id = ?
     ORDER BY f.created_at DESC"
  );
  $stmt->execute([$schoolId]);
  return $stmt->fetchAll();
}

function inkwell_is_featured_student($schoolId, $studentId) {
  $pdo = inkwell_db();
  $stmt = $pdo->prepare('SELECT id FROM featured_students WHERE school_id = ? AND student_id = ?');
  $stmt->execute([$schoolId, $studentId]);
  return (int) ($stmt->fetchColumn() ?: 0);
}

/**
 * Adds a student to the school's top-students showcase.
 * $note is a short quote-style highlight (e.g. "Top of Batch 2026").
 * $description is a longer write-up of why they're featured.
 * $accomplishment is a short badge/label (e.g. "Dean's Lister", "Perfect attendance").
 */
function inkwell_add_featured_student($schoolId, $studentId, $addedBy, $note = '', $description = '', $accomplishment = '') {
  $note = trim($note);
  $description = trim($description);
  $accomplishment = trim($accomplishment);
  if (inkwell_is_featured_student($schoolId, $studentId)) return ['ok' => false, 'error' => 'Already featured.'];
  $pdo = inkwell_db();
  try {
    $stmt = $pdo->prepare('INSERT INTO featured_students (school_id, student_id, added_by, note, description, accomplishment) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute([
      $schoolId, $studentId, $addedBy,
      $note !== '' ? $note : null,
      $description !== '' ? $description : null,
      $accomplishment !== '' ? $accomplishment : null,
    ]);
  } catch (PDOException $e) {
    // Most likely cause: MIGRATION_ADD_featured_students.sql / MIGRATION_ADD_featured_student_details.sql
    // haven't been run yet against this database, so the table/columns don't exist.
    return ['ok' => false, 'error' => 'Could not save (database not set up for this feature yet — run MIGRATION_ADD_featured_students.sql and MIGRATION_ADD_featured_student_details.sql).'];
  }
  return ['ok' => true, 'id' => (int) $pdo->lastInsertId()];
}

/** Edits the note/description/accomplishment on an already-featured student. */
function inkwell_update_featured_student($id, $schoolId, $note = '', $description = '', $accomplishment = '') {
  $note = trim($note);
  $description = trim($description);
  $accomplishment = trim($accomplishment);
  $pdo = inkwell_db();
  $stmt = $pdo->prepare('UPDATE featured_students SET note = ?, description = ?, accomplishment = ? WHERE id = ? AND school_id = ?');
  $stmt->execute([
    $note !== '' ? $note : null,
    $description !== '' ? $description : null,
    $accomplishment !== '' ? $accomplishment : null,
    $id, $schoolId,
  ]);
  return ['ok' => true];
}

function inkwell_remove_featured_student($id, $schoolId) {
  $pdo = inkwell_db();
  $stmt = $pdo->prepare('DELETE FROM featured_students WHERE id = ? AND school_id = ?');
  $stmt->execute([$id, $schoolId]);
}

/* ---------------- Admin-curated "top learners" (site-wide) ---------------- */
/**
 * Unlike featured_students (per-school, curated by a teacher/dean), this is
 * a single site-wide list an admin curates from ANY registered student —
 * shown as the "Learners online" chips on the public lessons page. Stored
 * as a small JSON file (student ids, in order) via includes/store.php.
 */

function inkwell_top_learner_ids() {
  require_once __DIR__ . '/store.php';
  $ids = inkwell_read_json('top_learners.json', []);
  return array_values(array_unique(array_map('intval', is_array($ids) ? $ids : [])));
}

function inkwell_save_top_learner_ids(array $ids) {
  require_once __DIR__ . '/store.php';
  $ids = array_values(array_unique(array_map('intval', $ids)));
  return inkwell_write_json('top_learners.json', $ids);
}

/** Admin-picked top learners, in the order the admin set them. */
function inkwell_top_learners($limit = 8) {
  $ids = inkwell_top_learner_ids();
  if (empty($ids)) return [];
  $pdo = inkwell_db();
  $placeholders = implode(',', array_fill(0, count($ids), '?'));
  $stmt = $pdo->prepare("SELECT id, name, email, avatar, course, school_id FROM users WHERE role = 'student' AND id IN ($placeholders)");
  $stmt->execute($ids);
  $byId = [];
  foreach ($stmt->fetchAll() as $row) { $byId[(int) $row['id']] = $row; }
  $ordered = [];
  foreach ($ids as $id) {
    if (isset($byId[$id])) $ordered[] = $byId[$id];
    if (count($ordered) >= $limit) break;
  }
  return $ordered;
}

function inkwell_toggle_top_learner($studentId) {
  $ids = inkwell_top_learner_ids();
  $studentId = (int) $studentId;
  if (in_array($studentId, $ids, true)) {
    $ids = array_values(array_diff($ids, [$studentId]));
  } else {
    $ids[] = $studentId;
  }
  inkwell_save_top_learner_ids($ids);
  return $ids;
}

/* ---------------- Registrar: edit student info ---------------- */

/**
 * Updates a student's editable profile fields (name, email, ID number,
 * course/program). Scoped so a registrar can only edit students who
 * belong to their own school — $schoolId is the registrar's school_id,
 * checked against the student's school_id before anything is written.
 */
function inkwell_update_student_info($studentId, $schoolId, $name, $email, $idNumber, $course, $departmentId = null) {
  $name = trim($name);
  $email = strtolower(trim($email));
  $idNumber = trim($idNumber);
  $course = trim($course);

  $pdo = inkwell_db();
  $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'student'");
  $stmt->execute([$studentId]);
  $student = $stmt->fetch();
  if (!$student) return ['ok' => false, 'error' => 'Student not found.'];
  if (empty($schoolId) || (int) $student['school_id'] !== (int) $schoolId) {
    return ['ok' => false, 'error' => 'That student is not part of your school.'];
  }

  if ($name === '' || $email === '') return ['ok' => false, 'error' => 'Name and email are required.'];
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return ['ok' => false, 'error' => 'Enter a valid email address.'];

  $check = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id != ?');
  $check->execute([$email, $studentId]);
  if ($check->fetch()) return ['ok' => false, 'error' => 'Another account already uses that email.'];

  if ($idNumber !== '') {
    $check = $pdo->prepare('SELECT id FROM users WHERE id_number = ? AND id_number != \'\' AND id != ?');
    $check->execute([$idNumber, $studentId]);
    if ($check->fetch()) return ['ok' => false, 'error' => 'Another account already uses that ID number.'];
  }

  require_once __DIR__ . '/departments.php';
  $deptCols = inkwell_ensure_department_columns();
  if ($deptCols['users']) {
    $departmentId = $departmentId !== null && $departmentId !== '' ? (int) $departmentId : null;
    $stmt = $pdo->prepare('UPDATE users SET name = ?, email = ?, id_number = ?, course = ?, department_id = ? WHERE id = ?');
    $stmt->execute([$name, $email, $idNumber, $course, $departmentId, $studentId]);
  } else {
    $stmt = $pdo->prepare('UPDATE users SET name = ?, email = ?, id_number = ?, course = ? WHERE id = ?');
    $stmt->execute([$name, $email, $idNumber, $course, $studentId]);
  }
  return ['ok' => true];
}

/* ---------------- Admin: all students + notes ---------------- */

/** Every student account, with their school name, for the admin roster. */
function inkwell_list_all_students() {
  $pdo = inkwell_db();
  return $pdo->query(
    "SELECT u.*, s.name AS school_name,
       (SELECT COUNT(*) FROM student_notes n WHERE n.student_id = u.id) AS note_count
     FROM users u
     LEFT JOIN schools s ON s.id = u.school_id
     WHERE u.role = 'student'
     ORDER BY u.created_at DESC"
  )->fetchAll();
}

/** All notes an admin has left on a student, newest first. */
function inkwell_student_notes($studentId) {
  $pdo = inkwell_db();
  $stmt = $pdo->prepare('SELECT * FROM student_notes WHERE student_id = ? ORDER BY created_at DESC');
  $stmt->execute([$studentId]);
  return $stmt->fetchAll();
}

function inkwell_add_student_note($studentId, $body) {
  $body = trim($body);
  if ($body === '') return ['ok' => false, 'error' => 'Note can\'t be empty.'];
  $pdo = inkwell_db();
  $stmt = $pdo->prepare('INSERT INTO student_notes (student_id, body) VALUES (?, ?)');
  $stmt->execute([$studentId, $body]);
  return ['ok' => true, 'id' => (int) $pdo->lastInsertId()];
}

function inkwell_delete_student_note($id) {
  $pdo = inkwell_db();
  $stmt = $pdo->prepare('DELETE FROM student_notes WHERE id = ?');
  $stmt->execute([$id]);
}
