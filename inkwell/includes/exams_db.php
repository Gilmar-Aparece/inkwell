<?php
/**
 * Subjects (classes), exams within them (teacher-, admin-, or
 * self-study-owned — see owner_type), questions (mcq / code / essay),
 * student attempts + manual grading, and certificates issued to
 * logged-in students. Self-study (per-language) exams are seeded once
 * from data/exams.php on first use (inkwell_ensure_selfstudy_seeded)
 * and are fully admin-editable from there on. Complements
 * data-store/certificates.json (older certs issued before accounts
 * existed).
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/store.php';
require_once __DIR__ . '/code_exec.php';
require_once __DIR__ . '/departments.php';
require_once __DIR__ . '/sections.php';

/* ================= Subjects ("classes") ================= */

/**
 * Self-heals `code` + `units` onto `subjects` (same pattern as
 * inkwell_ensure_enrollment_term_columns) so the Certificate of
 * Registration has somewhere to read a subject code / unit count from
 * without a manual migration step first.
 *
 * Some shared hosts (e.g. InfinityFree) don't grant the app's DB user
 * ALTER TABLE rights, so the ALTER below can silently fail. Rather than
 * assuming it worked and blowing up on the next INSERT/UPDATE, this
 * returns which of the two columns actually exist afterward, so
 * inkwell_create_subject()/inkwell_update_subject() can leave them out
 * of the query instead of crashing when they're missing. Run
 * MIGRATION_ADD_subject_code_units.sql from phpMyAdmin if that happens
 * — phpMyAdmin's own login normally has ALTER rights even when the
 * app's DB user doesn't.
 */
function inkwell_ensure_subject_code_units_columns() {
  static $result = null;
  if ($result !== null) return $result;
  $pdo = inkwell_db();
  $columns = [
    'code' => "ALTER TABLE subjects ADD COLUMN code VARCHAR(20) DEFAULT NULL COMMENT 'e.g. \"SE101\" — shown on the student COR' AFTER title",
    'units' => "ALTER TABLE subjects ADD COLUMN units INT(11) NOT NULL DEFAULT 3 COMMENT 'Credit units — shown on the student COR' AFTER code",
  ];
  try {
    $existing = $pdo->query('SHOW COLUMNS FROM subjects')->fetchAll(PDO::FETCH_COLUMN);
  } catch (PDOException $e) {
    $result = ['code' => false, 'units' => false];
    return $result;
  }
  foreach ($columns as $name => $sql) {
    if (in_array($name, $existing, true)) continue;
    try {
      $pdo->exec($sql);
      $existing[] = $name;
    } catch (PDOException $e) {
      // No ALTER privilege on this host, or added concurrently — either
      // way, leave it out of $existing so callers know not to use it.
    }
  }
  $result = ['code' => in_array('code', $existing, true), 'units' => in_array('units', $existing, true)];
  return $result;
}

/**
 * Self-heals `created_by` (the registrar who made the subject), `term`,
 * `academic_year`, and `school_id` onto `subjects` — same pattern as
 * inkwell_ensure_subject_code_units_columns() above. Run
 * MIGRATION_ADD_registrar_role.sql from phpMyAdmin if the ALTERs below
 * silently fail (no ALTER privilege on this host).
 */
function inkwell_ensure_subject_registrar_columns() {
  static $result = null;
  if ($result !== null) return $result;
  $pdo = inkwell_db();
  $columns = [
    'created_by' => "ALTER TABLE subjects ADD COLUMN created_by INT(11) DEFAULT NULL COMMENT 'registrar user id who created this subject' AFTER teacher_id",
    'term' => "ALTER TABLE subjects ADD COLUMN term VARCHAR(20) DEFAULT NULL COMMENT 'e.g. \"1st Semester\" — set by the registrar' AFTER units",
    'academic_year' => "ALTER TABLE subjects ADD COLUMN academic_year VARCHAR(20) DEFAULT NULL COMMENT 'e.g. \"2026-2027\"' AFTER term",
    'school_id' => "ALTER TABLE subjects ADD COLUMN school_id INT(11) DEFAULT NULL AFTER academic_year",
  ];
  try {
    $existing = $pdo->query('SHOW COLUMNS FROM subjects')->fetchAll(PDO::FETCH_COLUMN);
  } catch (PDOException $e) {
    $result = ['created_by' => false, 'term' => false, 'academic_year' => false, 'school_id' => false];
    return $result;
  }
  foreach ($columns as $name => $sql) {
    if (in_array($name, $existing, true)) continue;
    try {
      $pdo->exec($sql);
      $existing[] = $name;
    } catch (PDOException $e) {
      // No ALTER privilege on this host, or added concurrently.
    }
  }
  $result = [
    'created_by' => in_array('created_by', $existing, true),
    'term' => in_array('term', $existing, true),
    'academic_year' => in_array('academic_year', $existing, true),
    'school_id' => in_array('school_id', $existing, true),
  ];
  return $result;
}

function inkwell_teacher_subjects($teacherId) {
  inkwell_ensure_subject_code_units_columns();
  $__hasSectionCol = function_exists('inkwell_ensure_subject_section_column') && inkwell_ensure_subject_section_column();
  if ($__hasSectionCol && function_exists('inkwell_ensure_section_year_level_column')) {
    inkwell_ensure_section_year_level_column();
  }
  $pdo = inkwell_db();
  $sectionSelect = $__hasSectionCol ? ', sec.name AS section_name, sec.year_level AS section_year_level' : '';
  $sectionJoin = $__hasSectionCol ? 'LEFT JOIN sections sec ON sec.id = s.section_id' : '';
  $stmt = $pdo->prepare(
    "SELECT s.*,
            (SELECT COUNT(*) FROM exam_categories c WHERE c.subject_id = s.id) AS exam_count,
            (SELECT COUNT(*) FROM enrollments e WHERE e.subject_id = s.id) AS student_count
            $sectionSelect
     FROM subjects s $sectionJoin WHERE s.teacher_id = ? ORDER BY s.created_at DESC"
  );
  $stmt->execute([$teacherId]);
  return $stmt->fetchAll();
}

/** All subjects from approved teachers, for the public browse page. */
function inkwell_all_subjects() {
  inkwell_ensure_subject_code_units_columns();
  $pdo = inkwell_db();
  return $pdo->query(
    "SELECT s.*, u.name AS teacher_name,
            (SELECT COUNT(*) FROM exam_categories c WHERE c.subject_id = s.id) AS exam_count,
            (SELECT COUNT(*) FROM enrollments e WHERE e.subject_id = s.id) AS student_count
     FROM subjects s
     JOIN users u ON u.id = s.teacher_id
     WHERE u.status = 'active'
     ORDER BY s.created_at DESC"
  )->fetchAll();
}

/** Subjects taught at one school (by its active teachers) — shown on the public school page so visiting students know what they can join there. */
function inkwell_school_subjects($schoolId) {
  inkwell_ensure_subject_code_units_columns();
  $pdo = inkwell_db();
  $stmt = $pdo->prepare(
    "SELECT s.*, u.name AS teacher_name,
            (SELECT COUNT(*) FROM exam_categories c WHERE c.subject_id = s.id) AS exam_count,
            (SELECT COUNT(*) FROM enrollments e WHERE e.subject_id = s.id) AS student_count
     FROM subjects s
     JOIN users u ON u.id = s.teacher_id
     WHERE u.status = 'active' AND u.school_id = ?
     ORDER BY s.created_at DESC"
  );
  $stmt->execute([$schoolId]);
  return $stmt->fetchAll();
}

function inkwell_get_subject($id) {
  inkwell_ensure_subject_code_units_columns();
  $pdo = inkwell_db();
  $stmt = $pdo->prepare(
    "SELECT s.*, u.name AS teacher_name, u.status AS teacher_status
     FROM subjects s JOIN users u ON u.id = s.teacher_id WHERE s.id = ?"
  );
  $stmt->execute([$id]);
  return $stmt->fetch() ?: null;
}

function inkwell_create_subject($teacherId, $title, $description, $code = '', $units = 3) {
  $cols = inkwell_ensure_subject_code_units_columns();
  $pdo = inkwell_db();
  $fields = ['teacher_id', 'title', 'description'];
  $placeholders = ['?', '?', '?'];
  $values = [$teacherId, $title, $description];
  if ($cols['code']) {
    $fields[] = 'code';
    $placeholders[] = '?';
    $values[] = $code !== '' ? $code : null;
  }
  if ($cols['units']) {
    $fields[] = 'units';
    $placeholders[] = '?';
    $values[] = max(1, (int) $units);
  }
  $stmt = $pdo->prepare('INSERT INTO subjects (' . implode(', ', $fields) . ') VALUES (' . implode(', ', $placeholders) . ')');
  $stmt->execute($values);
  return (int) $pdo->lastInsertId();
}

function inkwell_update_subject($id, $title, $description, $code = '', $units = 3) {
  $cols = inkwell_ensure_subject_code_units_columns();
  $pdo = inkwell_db();
  $sets = ['title = ?', 'description = ?'];
  $values = [$title, $description];
  if ($cols['code']) {
    $sets[] = 'code = ?';
    $values[] = $code !== '' ? $code : null;
  }
  if ($cols['units']) {
    $sets[] = 'units = ?';
    $values[] = max(1, (int) $units);
  }
  $values[] = $id;
  $stmt = $pdo->prepare('UPDATE subjects SET ' . implode(', ', $sets) . ' WHERE id = ?');
  return $stmt->execute($values);
}

function inkwell_delete_subject($id) {
  $pdo = inkwell_db();
  $stmt = $pdo->prepare('DELETE FROM subjects WHERE id = ?');
  return $stmt->execute([$id]);
}

/* ---------------- Registrar-owned subjects (per semester) ----------------
 * Only a registrar can create a subject. They pick which active teacher
 * at their school it's assigned to; that teacher then sees it on their
 * dashboard (inkwell_teacher_subjects() already filters by teacher_id,
 * which now means "assigned teacher" instead of "subject creator").
 */
/**
 * $departmentId tags which department this subject belongs to (so its
 * exams inherit the same department via subject_id, for a Dean's
 * department-scoped exam picker). Pass null to auto-default to the
 * assigned teacher's own department — the usual case, since a registrar
 * is normally assigning the subject to a teacher already in a department.
 */
function inkwell_create_subject_for_registrar($registrarId, $teacherId, $schoolId, $title, $description, $code = '', $units = 3, $term = '', $academicYear = '', $departmentId = null) {
  $cols = inkwell_ensure_subject_code_units_columns();
  $regCols = inkwell_ensure_subject_registrar_columns();
  $deptCols = inkwell_ensure_department_columns();
  $pdo = inkwell_db();

  if ($deptCols['subjects'] && $deptCols['users'] && !$departmentId) {
    $stmt = $pdo->prepare('SELECT department_id FROM users WHERE id = ?');
    $stmt->execute([$teacherId]);
    $teacherDept = $stmt->fetchColumn();
    if ($teacherDept) $departmentId = (int) $teacherDept;
  }

  $fields = ['teacher_id', 'title', 'description'];
  $placeholders = ['?', '?', '?'];
  $values = [$teacherId, $title, $description];
  if ($cols['code']) { $fields[] = 'code'; $placeholders[] = '?'; $values[] = $code !== '' ? $code : null; }
  if ($cols['units']) { $fields[] = 'units'; $placeholders[] = '?'; $values[] = max(1, (int) $units); }
  if ($regCols['created_by']) { $fields[] = 'created_by'; $placeholders[] = '?'; $values[] = $registrarId; }
  if ($regCols['term']) { $fields[] = 'term'; $placeholders[] = '?'; $values[] = $term !== '' ? $term : null; }
  if ($regCols['academic_year']) { $fields[] = 'academic_year'; $placeholders[] = '?'; $values[] = $academicYear !== '' ? $academicYear : null; }
  if ($regCols['school_id']) { $fields[] = 'school_id'; $placeholders[] = '?'; $values[] = $schoolId ?: null; }
  if ($deptCols['subjects'] && $departmentId) { $fields[] = 'department_id'; $placeholders[] = '?'; $values[] = $departmentId; }
  $stmt = $pdo->prepare('INSERT INTO subjects (' . implode(', ', $fields) . ') VALUES (' . implode(', ', $placeholders) . ')');
  $stmt->execute($values);
  return (int) $pdo->lastInsertId();
}

/** Subjects this registrar has created, newest first. */
function inkwell_registrar_subjects($registrarId) {
  inkwell_ensure_subject_code_units_columns();
  $regCols = inkwell_ensure_subject_registrar_columns();
  $pdo = inkwell_db();
  if (!$regCols['created_by']) {
    // Column not migrated yet on this host — nothing to show until it exists.
    return [];
  }
  $stmt = $pdo->prepare(
    "SELECT s.*, u.name AS teacher_name, u.status AS teacher_status,
            (SELECT COUNT(*) FROM exam_categories c WHERE c.subject_id = s.id) AS exam_count,
            (SELECT COUNT(*) FROM enrollments e WHERE e.subject_id = s.id) AS student_count
     FROM subjects s JOIN users u ON u.id = s.teacher_id
     WHERE s.created_by = ? ORDER BY s.created_at DESC"
  );
  $stmt->execute([$registrarId]);
  return $stmt->fetchAll();
}

/** Re-assigns which teacher a subject belongs to. Only meant to be called after checking the subject was created by the acting registrar. */
function inkwell_reassign_subject_teacher($subjectId, $teacherId) {
  $pdo = inkwell_db();
  $stmt = $pdo->prepare('UPDATE subjects SET teacher_id = ? WHERE id = ?');
  return $stmt->execute([$teacherId, $subjectId]);
}

/** Updates the term/academic year on an existing subject (still assigned to whichever teacher it already has). */
function inkwell_update_subject_term($subjectId, $term, $academicYear) {
  $regCols = inkwell_ensure_subject_registrar_columns();
  if (!$regCols['term'] || !$regCols['academic_year']) return false;
  $pdo = inkwell_db();
  $stmt = $pdo->prepare('UPDATE subjects SET term = ?, academic_year = ? WHERE id = ?');
  return $stmt->execute([$term !== '' ? $term : null, $academicYear !== '' ? $academicYear : null, $subjectId]);
}

/* ================= Exams (live inside a subject) ================= */

function inkwell_subject_exams($subjectId) {
  $pdo = inkwell_db();
  $stmt = $pdo->prepare(
    "SELECT c.*, (SELECT COUNT(*) FROM exam_questions q WHERE q.category_id = c.id) AS question_count
     FROM exam_categories c WHERE c.subject_id = ? ORDER BY c.created_at ASC"
  );
  $stmt->execute([$subjectId]);
  return $stmt->fetchAll();
}

function inkwell_teacher_categories($teacherId) {
  inkwell_ensure_exam_schedule_columns();
  $pdo = inkwell_db();
  $stmt = $pdo->prepare('SELECT * FROM exam_categories WHERE teacher_id = ? ORDER BY created_at DESC');
  $stmt->execute([$teacherId]);
  return $stmt->fetchAll();
}

/**
 * Every teacher-owned exam within a school (across all its teachers) —
 * newest first. Used by the dean's "attach an exam" picker on
 * /dean/events.php. An exam has no department of its own — it inherits
 * one from its subject (subjects.department_id) via subject_id, so pass
 * $departmentId to scope this to just a department-scoped Dean's own
 * exams. Falls back to school-wide if department_id isn't available on
 * this host yet, or if the exam has no subject attached.
 */
function inkwell_school_exam_categories($schoolId, $departmentId = null) {
  inkwell_ensure_exam_schedule_columns();
  $deptCols = inkwell_ensure_department_columns();
  $pdo = inkwell_db();
  $sql = "SELECT c.*, u.name AS teacher_name
     FROM exam_categories c
     JOIN users u ON u.id = c.teacher_id";
  $params = [];
  if ($departmentId && $deptCols['subjects']) {
    $sql .= " LEFT JOIN subjects s ON s.id = c.subject_id
     WHERE u.school_id = ? AND c.owner_type = 'teacher' AND s.department_id = ?";
    $params = [$schoolId, $departmentId];
  } else {
    $sql .= " WHERE u.school_id = ? AND c.owner_type = 'teacher'";
    $params = [$schoolId];
  }
  $sql .= ' ORDER BY c.created_at DESC';
  $stmt = $pdo->prepare($sql);
  $stmt->execute($params);
  return $stmt->fetchAll();
}

function inkwell_get_teacher_category($id) {
  inkwell_ensure_exam_schedule_columns();
  $pdo = inkwell_db();
  $stmt = $pdo->prepare(
    "SELECT c.*, u.name AS teacher_name, u.status AS teacher_status, s.title AS subject_title
     FROM exam_categories c
     LEFT JOIN users u ON u.id = c.teacher_id
     LEFT JOIN subjects s ON s.id = c.subject_id
     WHERE c.id = ?"
  );
  $stmt->execute([$id]);
  return $stmt->fetch() ?: null;
}

/**
 * Self-heals `is_enabled` / `available_from` / `available_until` onto
 * `exam_categories` if they aren't there yet (same pattern as
 * inkwell_ensure_certificate_columns) — teacher-controlled exam
 * scheduling works even without running a manual migration first.
 */
function inkwell_ensure_exam_schedule_columns() {
  static $checked = false;
  if ($checked) return;
  $checked = true;
  $pdo = inkwell_db();
  $columns = [
    'is_enabled' => "ALTER TABLE exam_categories ADD COLUMN is_enabled TINYINT(1) NOT NULL DEFAULT 1",
    'available_from' => "ALTER TABLE exam_categories ADD COLUMN available_from DATETIME DEFAULT NULL",
    'available_until' => "ALTER TABLE exam_categories ADD COLUMN available_until DATETIME DEFAULT NULL",
    'time_limit_minutes' => "ALTER TABLE exam_categories ADD COLUMN time_limit_minutes INT DEFAULT NULL COMMENT 'Optional: minutes allowed once a student starts the exam - NULL/0 = no timer'",
  ];
  try {
    $existing = $pdo->query('SHOW COLUMNS FROM exam_categories')->fetchAll(PDO::FETCH_COLUMN);
  } catch (PDOException $e) {
    return; // table itself missing — nothing we can fix here
  }
  foreach ($columns as $name => $sql) {
    if (in_array($name, $existing, true)) continue;
    try {
      $pdo->exec($sql);
    } catch (PDOException $e) {
      // ignore — another request likely added it concurrently, or no ALTER privilege
    }
  }
}

/**
 * Self-heals the output auto-grading columns onto `exam_questions` /
 * `attempt_answers` (same self-healing pattern as
 * inkwell_ensure_exam_schedule_columns) — a teacher can tick "auto-grade by
 * matching output" on a code question without anyone having to run the
 * migration SQL by hand first.
 */
function inkwell_ensure_code_autograde_columns() {
  static $checked = false;
  if ($checked) return;
  $checked = true;
  $pdo = inkwell_db();

  try {
    $qCols = $pdo->query('SHOW COLUMNS FROM exam_questions')->fetchAll(PDO::FETCH_COLUMN);
    $qNeeded = [
      'auto_grade_output' => "ALTER TABLE exam_questions ADD COLUMN auto_grade_output TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Code questions only: run the student code and compare its output to expected_output instead of manual grading'",
      'expected_output'   => "ALTER TABLE exam_questions ADD COLUMN expected_output MEDIUMTEXT DEFAULT NULL",
    ];
    foreach ($qNeeded as $name => $sql) {
      if (in_array($name, $qCols, true)) continue;
      try { $pdo->exec($sql); } catch (PDOException $e) { /* concurrent request, or no ALTER privilege */ }
    }
  } catch (PDOException $e) { /* table missing — nothing to fix here */ }

  try {
    $aCols = $pdo->query('SHOW COLUMNS FROM attempt_answers')->fetchAll(PDO::FETCH_COLUMN);
    $aNeeded = [
      'autograded' => "ALTER TABLE attempt_answers ADD COLUMN autograded TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = this code answer was auto-graded by matching program output, no manual grading needed'",
      'run_output' => "ALTER TABLE attempt_answers ADD COLUMN run_output MEDIUMTEXT DEFAULT NULL COMMENT 'What the student code actually printed when auto-run for grading'",
    ];
    foreach ($aNeeded as $name => $sql) {
      if (in_array($name, $aCols, true)) continue;
      try { $pdo->exec($sql); } catch (PDOException $e) { /* concurrent request, or no ALTER privilege */ }
    }
  } catch (PDOException $e) { /* table missing — nothing to fix here */ }
}

/**
 * Effective open/closed status for a student trying to take $category right
 * now. The teacher's manual on/off switch always wins; otherwise, if a
 * from/until window is set, "now" is checked against it. Once "until" has
 * passed the exam reads as closed automatically — no cron job needed, it's
 * just evaluated fresh on every request — and stays that way unless the
 * teacher edits or clears the schedule (or flips it back open manually).
 */
function inkwell_exam_schedule_status($category) {
  inkwell_ensure_exam_schedule_columns();
  if (empty((int) ($category['is_enabled'] ?? 1))) {
    return ['open' => false, 'reason' => 'disabled'];
  }
  $now = new DateTime();
  if (!empty($category['available_from'])) {
    $from = new DateTime($category['available_from']);
    if ($now < $from) return ['open' => false, 'reason' => 'not_yet', 'at' => $category['available_from']];
  }
  if (!empty($category['available_until'])) {
    $until = new DateTime($category['available_until']);
    if ($now > $until) return ['open' => false, 'reason' => 'ended', 'at' => $category['available_until']];
  }
  return ['open' => true, 'reason' => 'open'];
}

/**
 * Updates the manual on/off switch and optional schedule window for one of
 * $teacherId's own exams. Pass empty strings/null to clear a date. Scoped to
 * teacher_id so a teacher can never touch another teacher's exam this way.
 */
function inkwell_update_exam_schedule($categoryId, $teacherId, $isEnabled, $availableFrom, $availableUntil, $timeLimitMinutes = null) {
  inkwell_ensure_exam_schedule_columns();
  $pdo = inkwell_db();
  $stmt = $pdo->prepare('UPDATE exam_categories SET is_enabled = ?, available_from = ?, available_until = ?, time_limit_minutes = ? WHERE id = ? AND teacher_id = ?');
  return $stmt->execute([
    $isEnabled ? 1 : 0,
    $availableFrom !== '' ? $availableFrom : null,
    $availableUntil !== '' ? $availableUntil : null,
    ($timeLimitMinutes !== null && (int) $timeLimitMinutes > 0) ? (int) $timeLimitMinutes : null,
    $categoryId,
    $teacherId,
  ]);
}

/**
 * Self-heals `kind` onto `exam_categories` — distinguishes an Exam from a
 * Project so a subject's assessments can be split/tracked separately on
 * the Class Record. Defaults existing rows to 'exam' so nothing already
 * created silently disappears from either view.
 */
function inkwell_ensure_category_kind_column() {
  static $ok = null;
  if ($ok !== null) return $ok;
  $pdo = inkwell_db();
  try {
    $existing = $pdo->query('SHOW COLUMNS FROM exam_categories')->fetchAll(PDO::FETCH_COLUMN);
  } catch (PDOException $e) {
    $ok = false;
    return $ok;
  }
  if (!in_array('kind', $existing, true)) {
    try {
      $pdo->exec("ALTER TABLE exam_categories ADD COLUMN kind ENUM('exam','project') NOT NULL DEFAULT 'exam' AFTER purpose");
    } catch (PDOException $e) {
      $ok = false;
      return $ok;
    }
  }
  $ok = true;
  return $ok;
}

/**
 * College class record: every exam/project is tagged to a term (Prelim /
 * Midterm / Final). The Class Record page averages each term's graded
 * assessments into a Term Grade, then averages the three Term Grades into
 * a Final Grade — see inkwell_class_record_compute().
 */
function inkwell_ensure_category_term_column() {
  static $ok = null;
  if ($ok !== null) return $ok;
  $pdo = inkwell_db();
  try {
    $existing = $pdo->query('SHOW COLUMNS FROM exam_categories')->fetchAll(PDO::FETCH_COLUMN);
  } catch (PDOException $e) {
    $ok = false;
    return $ok;
  }
  if (!in_array('term', $existing, true)) {
    try {
      $pdo->exec("ALTER TABLE exam_categories ADD COLUMN term ENUM('prelim','midterm','final') NOT NULL DEFAULT 'prelim' AFTER kind");
    } catch (PDOException $e) {
      $ok = false;
      return $ok;
    }
  }
  $ok = true;
  return $ok;
}

/** Ordered term keys => display labels, used anywhere the three terms are rendered. */
function inkwell_class_record_terms() {
  return ['prelim' => 'Prelim', 'midterm' => 'Midterm', 'final' => 'Final'];
}

/**
 * $purpose is 'cert' (issues a certificate on pass) or 'grade' (just a graded score, no certificate).
 * $maxAttempts is null/0 for unlimited attempts per student, or 1 to allow only a single attempt.
 * $kind is 'exam' or 'project' — which bucket it shows up under on the Class Record.
 * $term is 'prelim', 'midterm', or 'final' — which term it counts toward on the Class Record.
 */
function inkwell_create_teacher_exam($teacherId, $subjectId, $title, $description, $passScore, $purpose = 'cert', $maxAttempts = null, $kind = 'exam', $term = 'prelim') {
  $purpose = in_array($purpose, ['cert', 'grade'], true) ? $purpose : 'cert';
  $maxAttempts = ((int) $maxAttempts === 1) ? 1 : null;
  $hasKind = inkwell_ensure_category_kind_column();
  $kind = in_array($kind, ['exam', 'project'], true) ? $kind : 'exam';
  $hasTerm = inkwell_ensure_category_term_column();
  $term = array_key_exists($term, inkwell_class_record_terms()) ? $term : 'prelim';
  $pdo = inkwell_db();
  if ($hasKind && $hasTerm) {
    $stmt = $pdo->prepare("INSERT INTO exam_categories (teacher_id, subject_id, title, description, pass_score, owner_type, purpose, max_attempts, kind, term) VALUES (?, ?, ?, ?, ?, 'teacher', ?, ?, ?, ?)");
    $stmt->execute([$teacherId, $subjectId, $title, $description, $passScore, $purpose, $maxAttempts, $kind, $term]);
  } elseif ($hasKind) {
    $stmt = $pdo->prepare("INSERT INTO exam_categories (teacher_id, subject_id, title, description, pass_score, owner_type, purpose, max_attempts, kind) VALUES (?, ?, ?, ?, ?, 'teacher', ?, ?, ?)");
    $stmt->execute([$teacherId, $subjectId, $title, $description, $passScore, $purpose, $maxAttempts, $kind]);
  } else {
    $stmt = $pdo->prepare("INSERT INTO exam_categories (teacher_id, subject_id, title, description, pass_score, owner_type, purpose, max_attempts) VALUES (?, ?, ?, ?, ?, 'teacher', ?, ?)");
    $stmt->execute([$teacherId, $subjectId, $title, $description, $passScore, $purpose, $maxAttempts]);
  }
  return (int) $pdo->lastInsertId();
}

/** How many attempts (any status) a given student has already made on this exam. */
function inkwell_student_attempt_count($studentId, $categoryId) {
  $pdo = inkwell_db();
  $stmt = $pdo->prepare('SELECT COUNT(*) FROM attempts WHERE student_id = ? AND category_id = ?');
  $stmt->execute([$studentId, $categoryId]);
  return (int) $stmt->fetchColumn();
}

/** Most recent attempt a student made on this exam, or null if none yet. */
function inkwell_student_latest_attempt($studentId, $categoryId) {
  $pdo = inkwell_db();
  $stmt = $pdo->prepare('SELECT * FROM attempts WHERE student_id = ? AND category_id = ? ORDER BY submitted_at DESC LIMIT 1');
  $stmt->execute([$studentId, $categoryId]);
  return $stmt->fetch() ?: null;
}

/** Admin-authored certification exam — no teacher, no subject, always purpose = 'cert'. */
function inkwell_create_admin_exam($title, $description, $passScore) {
  $pdo = inkwell_db();
  $stmt = $pdo->prepare("INSERT INTO exam_categories (teacher_id, subject_id, title, description, pass_score, owner_type, purpose) VALUES (NULL, NULL, ?, ?, ?, 'admin', 'cert')");
  $stmt->execute([$title, $description, $passScore]);
  return (int) $pdo->lastInsertId();
}

function inkwell_update_teacher_category($id, $title, $description, $passScore, $purpose = null) {
  $pdo = inkwell_db();
  if ($purpose !== null && in_array($purpose, ['cert', 'grade'], true)) {
    $stmt = $pdo->prepare('UPDATE exam_categories SET title = ?, description = ?, pass_score = ?, purpose = ? WHERE id = ?');
    return $stmt->execute([$title, $description, $passScore, $purpose, $id]);
  }
  $stmt = $pdo->prepare('UPDATE exam_categories SET title = ?, description = ?, pass_score = ? WHERE id = ?');
  return $stmt->execute([$title, $description, $passScore, $id]);
}

function inkwell_delete_teacher_category($id) {
  $pdo = inkwell_db();
  $stmt = $pdo->prepare('DELETE FROM exam_categories WHERE id = ?');
  return $stmt->execute([$id]);
}

/**
 * Every exam from every teacher, regardless of subject or teacher status —
 * used by the admin "Manage exam questions" page so an admin can open and
 * expand (add/delete questions on) ANY exam, not just ones they authored.
 */
function inkwell_all_exam_categories() {
  $pdo = inkwell_db();
  return $pdo->query(
    "SELECT c.*, COALESCE(u.name, 'Admin') AS teacher_name, s.title AS subject_title,
            (SELECT COUNT(*) FROM exam_questions q WHERE q.category_id = c.id) AS question_count
     FROM exam_categories c
     LEFT JOIN users u ON u.id = c.teacher_id
     LEFT JOIN subjects s ON s.id = c.subject_id
     ORDER BY s.title ASC, c.created_at ASC"
  )->fetchAll();
}

/** Admin-authored certification exams only (owner_type = 'admin'), for the admin dashboard. */
function inkwell_admin_exam_categories() {
  $pdo = inkwell_db();
  return $pdo->query(
    "SELECT c.*, (SELECT COUNT(*) FROM exam_questions q WHERE q.category_id = c.id) AS question_count
     FROM exam_categories c
     WHERE c.owner_type = 'admin'
     ORDER BY c.created_at DESC"
  )->fetchAll();
}

/* ================= Self-study exams (owner_type = 'selfstudy') =================
 * One exam per language (html, css, js, ...), no teacher/subject attached —
 * open to any logged-in student, same as an admin exam, but listed under
 * "Self-study exams" and reachable at /exam.php?cat=<language_key> to keep
 * old links working. Admin can add new languages and edit/add/delete
 * questions on these the same way as any other exam (admin/category.php).
 */

/** Seeds the built-in language exams (from data/exams.php) into the DB the first
 *  time this runs. Safe to call on every request — it's a no-op once seeded. */
function inkwell_ensure_selfstudy_seeded() {
  static $checked = false;
  if ($checked) return;
  $checked = true;

  $pdo = inkwell_db();
  $count = (int) $pdo->query("SELECT COUNT(*) FROM exam_categories WHERE owner_type = 'selfstudy'")->fetchColumn();
  if ($count > 0) return;

  require_once __DIR__ . '/../data/exams.php';
  $builtIn = inkwell_exams();

  $insertCat = $pdo->prepare(
    "INSERT INTO exam_categories (teacher_id, subject_id, title, description, pass_score, owner_type, language_key, purpose)
     VALUES (NULL, NULL, ?, NULL, ?, 'selfstudy', ?, 'cert')"
  );
  $insertQ = $pdo->prepare(
    'INSERT INTO exam_questions
       (category_id, qtype, question, option_a, option_b, option_c, option_d, correct_index, code_language, code_starter, max_points, sort_order)
     VALUES (?, "mcq", ?, ?, ?, ?, ?, ?, NULL, NULL, 1, ?)'
  );

  foreach ($builtIn as $langKey => $exam) {
    // Skip if this language_key already exists (e.g. a previous partial run,
    // or a race with a concurrent request) instead of double-inserting.
    $exists = $pdo->prepare('SELECT id FROM exam_categories WHERE language_key = ?');
    $exists->execute([$langKey]);
    $catId = $exists->fetchColumn();
    if (!$catId) {
      $insertCat->execute([$exam['title'], (int) $exam['passScore'], $langKey]);
      $catId = (int) $pdo->lastInsertId();
    }
    foreach ($exam['questions'] as $i => $q) {
      $insertQ->execute([$catId, $q['q'], $q['options'][0], $q['options'][1], $q['options'][2], $q['options'][3], (int) $q['correct'], $i]);
    }
  }
}

/** All self-study (per-language) exams, for the public Exams page and admin panel. */
function inkwell_selfstudy_exam_categories() {
  inkwell_ensure_selfstudy_seeded();
  $pdo = inkwell_db();
  return $pdo->query(
    "SELECT c.*, (SELECT COUNT(*) FROM exam_questions q WHERE q.category_id = c.id) AS question_count
     FROM exam_categories c
     WHERE c.owner_type = 'selfstudy'
     ORDER BY c.created_at ASC"
  )->fetchAll();
}

/** Looks up a self-study exam by its language key (e.g. 'css'), for /exam.php?cat=css. */
function inkwell_get_selfstudy_category_by_key($languageKey) {
  inkwell_ensure_selfstudy_seeded();
  $pdo = inkwell_db();
  $stmt = $pdo->prepare("SELECT * FROM exam_categories WHERE owner_type = 'selfstudy' AND language_key = ?");
  $stmt->execute([$languageKey]);
  return $stmt->fetch() ?: null;
}

/** Small icon per built-in language key, purely decorative (falls back to a generic book). */
function inkwell_exam_icon($languageKey) {
  $icons = [
    'html' => '🌐', 'css' => '🎨', 'js' => '✨', 'php' => '🐘',
    'c' => '🔧', 'cpp' => '➕', 'java' => '☕', 'python' => '🐍', 'csharp' => '#️⃣',
  ];
  return $icons[$languageKey] ?? '📘';
}

/**
 * Renders a list of exams as compact rows with a "⋯" menu (à la Notion's
 * board-list rows) instead of a wide table — each row's only action lives
 * behind the kebab button, which keeps the list scannable and works well
 * on narrow screens.
 * $hrefBuilder receives one $exam row and returns the "Take exam" URL.
 * Shared by exams.php, self-study-exams.php and official-certification-exams.php.
 */
function inkwell_render_exam_list($exams, $hrefBuilder) {
  if (empty($exams)) {
    echo '<p class="admin-sub">Nothing here yet.</p>';
    return;
  }
  echo '<div class="exam-list">';
  foreach ($exams as $ex) {
    $hasQuestions = (int) $ex['question_count'] > 0;
    $schedule = inkwell_exam_schedule_status($ex);
    $icon = inkwell_exam_icon($ex['language_key'] ?? '');
    echo '<div class="exam-row">';
    echo '  <span class="exam-row-icon" aria-hidden="true">' . $icon . '</span>';
    echo '  <div class="exam-row-body">';
    echo '    <div class="exam-row-title">' . htmlspecialchars($ex['title']);
    if (!$schedule['open']) {
      $label = $schedule['reason'] === 'not_yet'
        ? 'Opens ' . htmlspecialchars(date('M j, g:i A', strtotime($schedule['at'])))
        : 'Closed';
      echo ' <span class="note-type-badge" style="background:#EF444426; color:#EF4444;">' . $label . '</span>';
    }
    echo '    </div>';
    echo '    <div class="exam-row-sub">Pass score ' . (int) $ex['pass_score'] . '%</div>';
    echo '  </div>';
    echo '  <div class="exam-row-menu-wrap">';
    echo '    <button type="button" class="exam-kebab-btn" data-exam-menu-toggle aria-haspopup="true" aria-expanded="false" aria-label="Exam actions">⋯</button>';
    echo '    <div class="exam-kebab-menu" role="menu">';
    if ($hasQuestions && $schedule['open']) {
      echo '      <a class="exam-kebab-item" role="menuitem" href="' . htmlspecialchars($hrefBuilder($ex)) . '"><span class="exam-kebab-item-icon">▶</span> Take exam</a>';
    } elseif (!$hasQuestions) {
      echo '      <span class="exam-kebab-item disabled" role="menuitem" aria-disabled="true"><span class="exam-kebab-item-icon">–</span> No questions yet</span>';
    } else {
      echo '      <span class="exam-kebab-item disabled" role="menuitem" aria-disabled="true"><span class="exam-kebab-item-icon">–</span> Not open right now</span>';
    }
    echo '    </div>';
    echo '  </div>';
    echo '</div>';
  }
  echo '</div>';
}

/**
 * The little "⋯" menu JS + teacher profile modal include, shared by any page
 * that uses inkwell_render_exam_list() (exams.php, self-study-exams.php,
 * official-certification-exams.php).
 */
function inkwell_exam_list_scripts() {
  ?>
<script>
(function () {
  function closeAllMenus(except) {
    document.querySelectorAll('.exam-kebab-menu.open').forEach(function (menu) {
      if (menu === except) return;
      menu.classList.remove('open');
      var btn = menu.previousElementSibling;
      if (btn) btn.setAttribute('aria-expanded', 'false');
    });
  }
  document.addEventListener('click', function (e) {
    var toggle = e.target.closest('[data-exam-menu-toggle]');
    if (toggle) {
      e.stopPropagation();
      var menu = toggle.nextElementSibling;
      var willOpen = !menu.classList.contains('open');
      closeAllMenus(willOpen ? menu : null);
      menu.classList.toggle('open', willOpen);
      toggle.setAttribute('aria-expanded', String(willOpen));
      return;
    }
    if (!e.target.closest('.exam-kebab-menu')) closeAllMenus();
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeAllMenus();
  });
})();
</script>
  <?php
}

/** True if $languageKey is already used by a self-study exam (for the "new exam" form). */
function inkwell_selfstudy_key_taken($languageKey) {
  $pdo = inkwell_db();
  $stmt = $pdo->prepare('SELECT 1 FROM exam_categories WHERE language_key = ?');
  $stmt->execute([$languageKey]);
  return (bool) $stmt->fetchColumn();
}

/** Admin creates a new self-study (per-language) certification exam. */
function inkwell_create_selfstudy_exam($languageKey, $title, $description, $passScore) {
  $pdo = inkwell_db();
  $stmt = $pdo->prepare(
    "INSERT INTO exam_categories (teacher_id, subject_id, title, description, pass_score, owner_type, language_key, purpose)
     VALUES (NULL, NULL, ?, ?, ?, 'selfstudy', ?, 'cert')"
  );
  $stmt->execute([$title, $description, $passScore, $languageKey]);
  return (int) $pdo->lastInsertId();
}

/* ================= Enrollments (student REQUESTS to join a subject, teacher approves) ================= */

/** Student asks to join a subject. Creates a 'pending' row; safe to call repeatedly. */
/**
 * Self-heals `term` + `academic_year` onto `enrollments` (same pattern as
 * inkwell_ensure_exam_schedule_columns) so the Enrollment Portal's term/year
 * pickers have somewhere to persist without a manual migration step first.
 */
function inkwell_ensure_enrollment_term_columns() {
  static $checked = false;
  if ($checked) return;
  $checked = true;
  $pdo = inkwell_db();
  $columns = [
    'term' => "ALTER TABLE enrollments ADD COLUMN term VARCHAR(20) DEFAULT NULL COMMENT 'e.g. \"1st Semester\" — set by the student in the Enrollment Portal'",
    'academic_year' => "ALTER TABLE enrollments ADD COLUMN academic_year VARCHAR(20) DEFAULT NULL COMMENT 'e.g. \"2026-2027\"'",
    'year_level' => "ALTER TABLE enrollments ADD COLUMN year_level VARCHAR(20) DEFAULT NULL COMMENT 'e.g. \"1st Year\" — set by the student in the Enrollment Portal'",
  ];
  try {
    $existing = $pdo->query('SHOW COLUMNS FROM enrollments')->fetchAll(PDO::FETCH_COLUMN);
  } catch (PDOException $e) {
    return;
  }
  foreach ($columns as $name => $sql) {
    if (in_array($name, $existing, true)) continue;
    try {
      $pdo->exec($sql);
    } catch (PDOException $e) {
      // ignore — added concurrently, or no ALTER privilege
    }
  }
}

function inkwell_request_enrollment($studentId, $subjectId, $term = null, $academicYear = null, $yearLevel = null) {
  inkwell_ensure_enrollment_term_columns();
  $pdo = inkwell_db();
  $stmt = $pdo->prepare("INSERT IGNORE INTO enrollments (student_id, subject_id, status, term, academic_year, year_level) VALUES (?, ?, 'pending', ?, ?, ?)");
  return $stmt->execute([$studentId, $subjectId, $term ?: null, $academicYear ?: null, $yearLevel ?: null]);
}

function inkwell_approve_enrollment($enrollmentId) {
  $pdo = inkwell_db();
  $stmt = $pdo->prepare("UPDATE enrollments SET status = 'approved', decided_at = NOW() WHERE id = ?");
  return $stmt->execute([$enrollmentId]);
}

function inkwell_reject_enrollment($enrollmentId) {
  $pdo = inkwell_db();
  $stmt = $pdo->prepare('DELETE FROM enrollments WHERE id = ?');
  return $stmt->execute([$enrollmentId]);
}

function inkwell_unenroll_student($studentId, $subjectId) {
  $pdo = inkwell_db();
  $stmt = $pdo->prepare('DELETE FROM enrollments WHERE student_id = ? AND subject_id = ?');
  return $stmt->execute([$studentId, $subjectId]);
}

/** True only once the teacher has approved the request. */
function inkwell_is_enrolled($studentId, $subjectId) {
  $pdo = inkwell_db();
  $stmt = $pdo->prepare("SELECT 1 FROM enrollments WHERE student_id = ? AND subject_id = ? AND status = 'approved'");
  $stmt->execute([$studentId, $subjectId]);
  return (bool) $stmt->fetchColumn();
}

/** Student has a request in flight (pending) for this subject. */
function inkwell_has_pending_request($studentId, $subjectId) {
  $pdo = inkwell_db();
  $stmt = $pdo->prepare("SELECT 1 FROM enrollments WHERE student_id = ? AND subject_id = ? AND status = 'pending'");
  $stmt->execute([$studentId, $subjectId]);
  return (bool) $stmt->fetchColumn();
}

/** Subjects a given student is approved into — for exams.php's "your classes" list. */
function inkwell_student_enrolled_subjects($studentId) {
  inkwell_ensure_enrollment_term_columns();
  inkwell_ensure_subject_code_units_columns();
  $pdo = inkwell_db();
  $stmt = $pdo->prepare(
    "SELECT s.*, u.name AS teacher_name, e.term AS term, e.academic_year AS academic_year, e.year_level AS year_level,
            (SELECT COUNT(*) FROM exam_categories c WHERE c.subject_id = s.id) AS exam_count
     FROM enrollments e
     JOIN subjects s ON s.id = e.subject_id
     JOIN users u ON u.id = s.teacher_id
     WHERE e.student_id = ? AND e.status = 'approved' AND u.status = 'active'
     ORDER BY e.enrolled_at DESC"
  );
  $stmt->execute([$studentId]);
  return $stmt->fetchAll();
}

/** Subjects a given student has requested but isn't approved into yet — for the enrollment portal's "awaiting approval" list. */
function inkwell_student_pending_subjects($studentId) {
  inkwell_ensure_enrollment_term_columns();
  $pdo = inkwell_db();
  $stmt = $pdo->prepare(
    "SELECT s.*, u.name AS teacher_name, e.enrolled_at AS requested_at, e.term AS term, e.academic_year AS academic_year, e.year_level AS year_level
     FROM enrollments e
     JOIN subjects s ON s.id = e.subject_id
     JOIN users u ON u.id = s.teacher_id
     WHERE e.student_id = ? AND e.status = 'pending'
     ORDER BY e.enrolled_at DESC"
  );
  $stmt->execute([$studentId]);
  return $stmt->fetchAll();
}

function inkwell_enrollment_count($subjectId) {
  $pdo = inkwell_db();
  $stmt = $pdo->prepare("SELECT COUNT(*) FROM enrollments WHERE subject_id = ? AND status = 'approved'");
  $stmt->execute([$subjectId]);
  return (int) $stmt->fetchColumn();
}

/** Pending join requests across every subject owned by this teacher, for the approval panel. */
function inkwell_teacher_pending_join_requests($teacherId) {
  $pdo = inkwell_db();
  $stmt = $pdo->prepare(
    "SELECT e.*, u.name AS student_name, u.email AS student_email, s.title AS subject_title
     FROM enrollments e
     JOIN subjects s ON s.id = e.subject_id
     JOIN users u ON u.id = e.student_id
     WHERE s.teacher_id = ? AND e.status = 'pending'
     ORDER BY e.enrolled_at ASC"
  );
  $stmt->execute([$teacherId]);
  return $stmt->fetchAll();
}

/**
 * Pending join requests across EVERY subject taught within this school
 * (any teacher), for the registrar's approval panel — a registrar can
 * approve on behalf of any teacher at their school, not just their own
 * subjects, since teachers report up to the registrar's office.
 */
function inkwell_registrar_pending_join_requests($schoolId) {
  $pdo = inkwell_db();
  $stmt = $pdo->prepare(
    "SELECT e.*, u.name AS student_name, u.email AS student_email, s.title AS subject_title, t.name AS teacher_name
     FROM enrollments e
     JOIN subjects s ON s.id = e.subject_id
     JOIN users t ON t.id = s.teacher_id
     JOIN users u ON u.id = e.student_id
     WHERE t.school_id = ? AND e.status = 'pending'
     ORDER BY e.enrolled_at ASC"
  );
  $stmt->execute([$schoolId]);
  return $stmt->fetchAll();
}

/** Authorization check: true if this enrollment request's subject is taught by a teacher at $schoolId. */
function inkwell_enrollment_in_school($enrollmentId, $schoolId) {
  if (!$enrollmentId || !$schoolId) return false;
  $pdo = inkwell_db();
  $stmt = $pdo->prepare(
    "SELECT 1 FROM enrollments e
     JOIN subjects s ON s.id = e.subject_id
     JOIN users t ON t.id = s.teacher_id
     WHERE e.id = ? AND t.school_id = ? LIMIT 1"
  );
  $stmt->execute([$enrollmentId, $schoolId]);
  return (bool) $stmt->fetchColumn();
}

/* ================= Questions ================= */

function inkwell_get_teacher_questions($categoryId) {
  inkwell_ensure_code_autograde_columns();
  $pdo = inkwell_db();
  $stmt = $pdo->prepare('SELECT * FROM exam_questions WHERE category_id = ? ORDER BY sort_order ASC, id ASC');
  $stmt->execute([$categoryId]);
  return $stmt->fetchAll();
}

function inkwell_get_question($id) {
  inkwell_ensure_code_autograde_columns();
  $pdo = inkwell_db();
  $stmt = $pdo->prepare('SELECT * FROM exam_questions WHERE id = ?');
  $stmt->execute([$id]);
  return $stmt->fetch() ?: null;
}

/** $data: question, qtype, options[4]|null, correct_index|null, code_language|null, code_starter|null, max_points, auto_grade_output|null, expected_output|null */
function inkwell_add_teacher_question($categoryId, $data) {
  inkwell_ensure_code_autograde_columns();
  $pdo = inkwell_db();
  $stmt = $pdo->prepare('SELECT COALESCE(MAX(sort_order), -1) + 1 FROM exam_questions WHERE category_id = ?');
  $stmt->execute([$categoryId]);
  $nextOrder = (int) $stmt->fetchColumn();

  $opts = $data['options'] ?? [null, null, null, null];
  $stmt = $pdo->prepare(
    'INSERT INTO exam_questions
       (category_id, qtype, question, option_a, option_b, option_c, option_d, correct_index, code_language, code_starter, max_points, sort_order, auto_grade_output, expected_output)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
  );
  return $stmt->execute([
    $categoryId,
    $data['qtype'],
    $data['question'],
    $opts[0] ?? null, $opts[1] ?? null, $opts[2] ?? null, $opts[3] ?? null,
    $data['correct_index'] ?? null,
    $data['code_language'] ?? null,
    $data['code_starter'] ?? null,
    $data['max_points'] ?? 1,
    $nextOrder,
    !empty($data['auto_grade_output']) ? 1 : 0,
    $data['expected_output'] ?? null,
  ]);
}

function inkwell_delete_teacher_question($id) {
  $pdo = inkwell_db();
  $stmt = $pdo->prepare('DELETE FROM exam_questions WHERE id = ?');
  return $stmt->execute([$id]);
}

/** True if every question in the exam is mcq (safe to auto-grade instantly). */
function inkwell_exam_is_auto_gradable($categoryId) {
  $pdo = inkwell_db();
  $stmt = $pdo->prepare("SELECT COUNT(*) FROM exam_questions WHERE category_id = ? AND qtype != 'mcq'");
  $stmt->execute([$categoryId]);
  return ((int) $stmt->fetchColumn()) === 0;
}

/* ================= Attempts + manual grading ================= */

/**
 * Submits an attempt. $answers is keyed by question id:
 *   mcq   => (int) selected option index
 *   code/essay => (string) free text
 * Auto-grades mcq questions immediately; code/essay are left ungraded
 * (points_awarded = NULL) until a teacher scores them.
 * Returns the attempt row (fresh from DB) plus 'auto_complete' => bool
 * (true if every question was mcq, so grading is already final).
 */
function inkwell_submit_attempt($studentId, $categoryId, $questions, $answers) {
  inkwell_ensure_code_autograde_columns();
  $pdo = inkwell_db();
  $pdo->beginTransaction();

  $autoPoints = 0;
  $totalPoints = 0;
  $needsManual = false;

  $stmtAttempt = $pdo->prepare('INSERT INTO attempts (student_id, category_id, status, total_points) VALUES (?, ?, ?, ?)');
  $stmtAttempt->execute([$studentId, $categoryId, 'pending', 0]);
  $attemptId = (int) $pdo->lastInsertId();

  $stmtAnswer = $pdo->prepare(
    'INSERT INTO attempt_answers (attempt_id, question_id, qtype, selected_index, text_answer, is_correct, points_awarded, max_points, autograded, run_output)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
  );

  foreach ($questions as $q) {
    $qid = (int) $q['id'];
    $maxPoints = max(1, (int) $q['max_points']);
    $totalPoints += $maxPoints;
    $canAutoGradeCode = $q['qtype'] === 'code'
      && !empty($q['auto_grade_output'])
      && trim((string) ($q['expected_output'] ?? '')) !== '';

    if ($q['qtype'] === 'mcq') {
      $selected = isset($answers[$qid]) ? (int) $answers[$qid] : null;
      $isCorrect = $selected !== null && $selected === (int) $q['correct_index'];
      $points = $isCorrect ? $maxPoints : 0;
      $autoPoints += $points;
      $stmtAnswer->execute([$attemptId, $qid, 'mcq', $selected, null, $isCorrect ? 1 : 0, $points, $maxPoints, 0, null]);
    } elseif ($canAutoGradeCode) {
      $text = isset($answers[$qid]) ? (string) $answers[$qid] : '';
      $check = inkwell_check_code_output($q['code_language'], $text, $q['expected_output']);
      if ($check['ok']) {
        // Ran successfully — grade it now, same as an mcq question.
        $isCorrect = $check['match'];
        $points = $isCorrect ? $maxPoints : 0;
        $autoPoints += $points;
        $stmtAnswer->execute([$attemptId, $qid, 'code', null, $text, $isCorrect ? 1 : 0, $points, $maxPoints, 1, $check['output']]);
      } else {
        // Run service unavailable right now — never guess; fall back to manual grading.
        $needsManual = true;
        $stmtAnswer->execute([$attemptId, $qid, 'code', null, $text, null, null, $maxPoints, 0, $check['error']]);
      }
    } else {
      $needsManual = true;
      $text = isset($answers[$qid]) ? (string) $answers[$qid] : '';
      $stmtAnswer->execute([$attemptId, $qid, $q['qtype'], null, $text, null, null, $maxPoints, 0, null]);
    }
  }

  if (!$needsManual) {
    $percent = $totalPoints > 0 ? (int) round(($autoPoints / $totalPoints) * 100) : 0;
    $stmtPass = $pdo->prepare('SELECT pass_score FROM exam_categories WHERE id = ?');
    $stmtPass->execute([$categoryId]);
    $passScore = (int) $stmtPass->fetchColumn();
    $passed = $percent >= $passScore;
    $stmt = $pdo->prepare("UPDATE attempts SET status = 'graded', auto_points = ?, total_points = ?, percent = ?, passed = ?, graded_at = NOW() WHERE id = ?");
    $stmt->execute([$autoPoints, $totalPoints, $percent, $passed ? 1 : 0, $attemptId]);
  } else {
    $stmt = $pdo->prepare('UPDATE attempts SET auto_points = ?, total_points = ? WHERE id = ?');
    $stmt->execute([$autoPoints, $totalPoints, $attemptId]);
  }

  $pdo->commit();

  $stmt = $pdo->prepare('SELECT * FROM attempts WHERE id = ?');
  $stmt->execute([$attemptId]);
  $attempt = $stmt->fetch();
  $attempt['auto_complete'] = !$needsManual;
  return $attempt;
}

function inkwell_get_attempt($id) {
  $pdo = inkwell_db();
  $stmt = $pdo->prepare(
    "SELECT a.*, u.name AS student_name, c.title AS exam_title, c.pass_score, c.teacher_id, c.teacher_id AS cat_teacher_id, c.purpose,
            t.name AS teacher_name
     FROM attempts a
     JOIN users u ON u.id = a.student_id
     JOIN exam_categories c ON c.id = a.category_id
     LEFT JOIN users t ON t.id = c.teacher_id
     WHERE a.id = ?"
  );
  $stmt->execute([$id]);
  return $stmt->fetch() ?: null;
}

function inkwell_attempt_answers($attemptId) {
  inkwell_ensure_code_autograde_columns();
  $pdo = inkwell_db();
  $stmt = $pdo->prepare(
    "SELECT aa.*, q.question, q.code_language, q.max_points AS q_max_points,
            q.option_a, q.option_b, q.option_c, q.option_d, q.correct_index, q.sort_order,
            q.auto_grade_output, q.expected_output
     FROM attempt_answers aa JOIN exam_questions q ON q.id = aa.question_id
     WHERE aa.attempt_id = ? ORDER BY q.sort_order ASC, aa.id ASC"
  );
  $stmt->execute([$attemptId]);
  return $stmt->fetchAll();
}

/** Pending attempts (needing manual grading) across all of a teacher's exams. */
function inkwell_teacher_pending_attempts($teacherId) {
  $pdo = inkwell_db();
  $stmt = $pdo->prepare(
    "SELECT a.*, u.name AS student_name, c.title AS exam_title
     FROM attempts a
     JOIN exam_categories c ON c.id = a.category_id
     JOIN users u ON u.id = a.student_id
     WHERE c.teacher_id = ? AND a.status = 'pending'
     ORDER BY a.submitted_at ASC"
  );
  $stmt->execute([$teacherId]);
  return $stmt->fetchAll();
}

function inkwell_teacher_graded_attempts($teacherId, $limit = 50) {
  $pdo = inkwell_db();
  $stmt = $pdo->prepare(
    "SELECT a.*, u.name AS student_name, c.title AS exam_title
     FROM attempts a
     JOIN exam_categories c ON c.id = a.category_id
     JOIN users u ON u.id = a.student_id
     WHERE c.teacher_id = ? AND a.status = 'graded'
     ORDER BY a.graded_at DESC LIMIT " . (int) $limit
  );
  $stmt->execute([$teacherId]);
  return $stmt->fetchAll();
}

/** All attempts (any status) a student has made, most recent first. */
function inkwell_student_attempts($studentId) {
  $pdo = inkwell_db();
  $stmt = $pdo->prepare(
    "SELECT a.*, c.title AS exam_title, u.name AS teacher_name
     FROM attempts a
     JOIN exam_categories c ON c.id = a.category_id
     JOIN users u ON u.id = c.teacher_id
     WHERE a.student_id = ?
     ORDER BY a.submitted_at DESC"
  );
  $stmt->execute([$studentId]);
  return $stmt->fetchAll();
}

/**
 * Finalizes manual grading for one attempt. $points is [answer_id => int points],
 * $feedback is [answer_id => string]. Only touches non-mcq answers.
 * Issues a certificate automatically if the final percent clears pass_score.
 */
function inkwell_grade_attempt($attemptId, $points, $feedback) {
  $pdo = inkwell_db();
  $attempt = inkwell_get_attempt($attemptId);
  if (!$attempt) return null;

  $answers = inkwell_attempt_answers($attemptId);
  $manualPoints = 0;

  $stmtUpd = $pdo->prepare('UPDATE attempt_answers SET points_awarded = ?, feedback = ? WHERE id = ?');
  foreach ($answers as $ans) {
    if ($ans['qtype'] === 'mcq' || !empty($ans['autograded'])) {
      continue; // already scored at submit time (mcq, or code auto-graded by output), folded into auto_points
    }
    $maxP = (int) $ans['max_points'];
    $awarded = isset($points[$ans['id']]) ? max(0, min($maxP, (int) $points[$ans['id']])) : 0;
    $fb = trim($feedback[$ans['id']] ?? '');
    $stmtUpd->execute([$awarded, $fb !== '' ? $fb : null, $ans['id']]);
    $manualPoints += $awarded;
  }

  $totalPoints = (int) $attempt['total_points'];
  $finalPoints = (int) $attempt['auto_points'] + $manualPoints;
  $percent = $totalPoints > 0 ? (int) round(($finalPoints / $totalPoints) * 100) : 0;
  $passed = $percent >= (int) $attempt['pass_score'];

  $certificateId = null;
  if ($passed && $attempt['purpose'] === 'cert') {
    $cert = inkwell_db_add_certificate(
      $attempt['student_id'], $attempt['student_name'], 'teacher', null, $attempt['category_id'],
      $attempt['exam_title'], $attempt['teacher_id'], null, $finalPoints, $totalPoints
    );
    $certificateId = $cert['id'];
  }

  $stmt = $pdo->prepare(
    "UPDATE attempts SET status = 'graded', manual_points = ?, percent = ?, passed = ?, certificate_id = ?, graded_at = NOW() WHERE id = ?"
  );
  $stmt->execute([$manualPoints, $percent, $passed ? 1 : 0, $certificateId, $attemptId]);

  return ['percent' => $percent, 'passed' => $passed, 'certificate_id' => $certificateId];
}

/**
 * Plain-text score report for one attempt — question by question, with the
 * student's answer next to the correct answer (mcq) or the teacher's
 * points/feedback (code/essay), used by results.php for the "Download as
 * text" button (student and teacher can both download the same report).
 */
function inkwell_attempt_text_report($attempt, $answers) {
  $codeLangs = ['javascript' => 'JavaScript', 'python' => 'Python', 'php' => 'PHP', 'java' => 'Java', 'csharp' => 'C#', 'cpp' => 'C++', 'html' => 'HTML/CSS', 'sql' => 'SQL'];

  $lines = [];
  $lines[] = 'INKWELL — EXAM RESULT';
  $lines[] = str_repeat('=', 60);
  $lines[] = 'Exam:      ' . $attempt['exam_title'];
  $lines[] = 'Student:   ' . $attempt['student_name'];
  if (!empty($attempt['teacher_id'])) {
    $lines[] = 'Teacher:   ' . ($attempt['teacher_name'] ?? ('#' . $attempt['teacher_id']));
  }
  $lines[] = 'Submitted: ' . date('F j, Y g:i A', strtotime($attempt['submitted_at']));
  if ($attempt['status'] === 'graded') {
    $lines[] = 'Graded:    ' . date('F j, Y g:i A', strtotime($attempt['graded_at']));
  }
  $lines[] = str_repeat('-', 60);

  if ($attempt['status'] === 'graded') {
    $finalPoints = (int) $attempt['auto_points'] + (int) $attempt['manual_points'];
    $lines[] = 'SCORE:     ' . $finalPoints . ' / ' . (int) $attempt['total_points'] . ' points (' . (int) $attempt['percent'] . '%)';
    $lines[] = 'PASS SCORE REQUIRED: ' . (int) $attempt['pass_score'] . '%';
    $lines[] = 'RESULT:    ' . ($attempt['passed'] ? 'PASSED' : 'NOT PASSED');
  } else {
    $lines[] = 'SCORE:     Not finalized yet — this exam has questions still awaiting teacher grading.';
  }
  $lines[] = str_repeat('=', 60);
  $lines[] = '';

  $qNum = 0;
  foreach ($answers as $ans) {
    $qNum++;
    $lines[] = 'Q' . $qNum . '. ' . $ans['question'];

    if ($ans['qtype'] === 'mcq') {
      $opts = [$ans['option_a'], $ans['option_b'], $ans['option_c'], $ans['option_d']];
      foreach ($opts as $i => $opt) {
        if ($opt === null || $opt === '') continue;
        $marker = '   ';
        if ((int) $ans['correct_index'] === $i) $marker = ' * '; // correct answer
        $picked = ($ans['selected_index'] !== null && (int) $ans['selected_index'] === $i) ? ' <- your answer' : '';
        $lines[] = $marker . chr(65 + $i) . ') ' . $opt . $picked;
      }
      $lines[] = 'Result: ' . ($ans['is_correct'] ? 'Correct' : 'Incorrect') . ' (' . (int) $ans['points_awarded'] . '/' . (int) $ans['max_points'] . ' pts) — (*) marks the correct answer.';
    } elseif ($ans['qtype'] === 'code' && !empty($ans['autograded'])) {
      $lines[] = 'Code answer (' . ($codeLangs[$ans['code_language']] ?? $ans['code_language']) . '):';
      $lines[] = '  ' . str_replace("\n", "\n  ", trim((string) $ans['text_answer']) ?: '(no answer submitted)');
      $lines[] = 'Program output: ' . (trim((string) $ans['run_output']) !== '' ? trim((string) $ans['run_output']) : '(no output)');
      $lines[] = 'Expected output: ' . trim((string) $ans['expected_output']);
      $lines[] = 'Result: ' . ($ans['is_correct'] ? 'Correct' : 'Incorrect') . ' (' . (int) $ans['points_awarded'] . '/' . (int) $ans['max_points'] . ' pts) — auto-graded by matching output.';
    } else {
      $label = $ans['qtype'] === 'code' ? 'Code answer (' . ($codeLangs[$ans['code_language']] ?? $ans['code_language']) . ')' : 'Essay answer';
      $lines[] = $label . ':';
      $lines[] = '  ' . str_replace("\n", "\n  ", trim((string) $ans['text_answer']) ?: '(no answer submitted)');
      if ($ans['points_awarded'] !== null) {
        $lines[] = 'Score: ' . (int) $ans['points_awarded'] . '/' . (int) $ans['max_points'] . ' pts';
      } else {
        $lines[] = 'Score: not graded yet';
      }
      if (!empty($ans['feedback'])) {
        $lines[] = 'Teacher feedback / correction: ' . $ans['feedback'];
      }
    }
    $lines[] = '';
  }

  $lines[] = str_repeat('-', 60);
  $lines[] = 'Generated by Inkwell on ' . date('F j, Y g:i A');
  return implode("\n", $lines);
}

/* ================= Certificates (DB-backed, for logged-in students) ================= */

function inkwell_db_add_certificate($studentId, $studentName, $categoryType, $categoryKey, $categoryId, $label, $teacherId, $teacherName, $score, $total) {
  $pdo = inkwell_db();
  $id = bin2hex(random_bytes(8));
  $percent = $total > 0 ? (int) round(($score / $total) * 100) : 0;

  if ($teacherId && !$teacherName) {
    $stmt = $pdo->prepare('SELECT name FROM users WHERE id = ?');
    $stmt->execute([$teacherId]);
    $teacherName = $stmt->fetchColumn() ?: null;
  }

  $stmt = $pdo->prepare(
    'INSERT INTO certificates (id, student_id, student_name, category_type, category_key, category_id, label, teacher_id, teacher_name, score, total, percent, issued_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE())'
  );
  $stmt->execute([$id, $studentId, $studentName, $categoryType, $categoryKey, $categoryId, $label, $teacherId, $teacherName, $score, $total, $percent]);
  return [
    'id' => $id, 'name' => $studentName, 'category_type' => $categoryType, 'category_key' => $categoryKey,
    'category_id' => $categoryId, 'label' => $label, 'teacher_id' => $teacherId, 'teacher_name' => $teacherName,
    'score' => $score, 'total' => $total, 'percent' => $percent, 'issued_at' => date('Y-m-d'),
  ];
}

/**
 * Self-healing: adds the manual-certificate and certificate-customization
 * columns to `certificates` if they aren't there yet, so issuing and
 * designing certificates works even if the MIGRATION_ADD_manual_certificates.sql
 * / MIGRATION_ADD_cert_customization.sql files were never manually run.
 * Each ALTER is attempted independently and "column already exists"
 * errors are silently ignored, so this is safe to call on every request.
 */
function inkwell_ensure_certificate_columns() {
  static $checked = false;
  if ($checked) return;
  $checked = true;
  $pdo = inkwell_db();
  $columns = [
    'source' => "ALTER TABLE certificates ADD COLUMN source ENUM('exam','manual') NOT NULL DEFAULT 'exam'",
    'issued_by_name' => "ALTER TABLE certificates ADD COLUMN issued_by_name VARCHAR(100) DEFAULT NULL",
    'issued_by_role' => "ALTER TABLE certificates ADD COLUMN issued_by_role VARCHAR(20) DEFAULT NULL",
    'custom_message' => "ALTER TABLE certificates ADD COLUMN custom_message VARCHAR(255) DEFAULT NULL",
    'accent_color' => "ALTER TABLE certificates ADD COLUMN accent_color VARCHAR(9) DEFAULT NULL",
    'issuer_school_id' => "ALTER TABLE certificates ADD COLUMN issuer_school_id INT(11) DEFAULT NULL",
    'template' => "ALTER TABLE certificates ADD COLUMN template VARCHAR(20) DEFAULT NULL",
    'font_choice' => "ALTER TABLE certificates ADD COLUMN font_choice VARCHAR(20) DEFAULT NULL",
    'bg_style' => "ALTER TABLE certificates ADD COLUMN bg_style VARCHAR(20) DEFAULT NULL",
    'title_text' => "ALTER TABLE certificates ADD COLUMN title_text VARCHAR(150) DEFAULT NULL",
    'seal_label' => "ALTER TABLE certificates ADD COLUMN seal_label VARCHAR(60) DEFAULT NULL",
    'signer_name_override' => "ALTER TABLE certificates ADD COLUMN signer_name_override VARCHAR(100) DEFAULT NULL",
    'signer_title_override' => "ALTER TABLE certificates ADD COLUMN signer_title_override VARCHAR(150) DEFAULT NULL",
    'signer_signature_override' => "ALTER TABLE certificates ADD COLUMN signer_signature_override VARCHAR(255) DEFAULT NULL",
  ];
  try {
    $existing = $pdo->query('SHOW COLUMNS FROM certificates')->fetchAll(PDO::FETCH_COLUMN);
  } catch (PDOException $e) {
    return; // certificates table itself missing — nothing we can fix here
  }
  foreach ($columns as $name => $sql) {
    if (in_array($name, $existing, true)) continue;
    try {
      $pdo->exec($sql);
    } catch (PDOException $e) {
      // ignore — another request likely added it concurrently, or no ALTER privilege
    }
  }
}

/**
 * Issues a certificate directly (not tied to passing an exam) — used by
 * the dean/teacher "Issue a certificate" tool. $issuedByRole is 'dean' or
 * 'teacher'; when it's 'teacher', $issuedById also becomes teacher_id so
 * the existing signer-lookup logic (inkwell_get_cert_signer) picks up the
 * teacher's own signer automatically. Deans have no teacher_id, so their
 * school is stored in issuer_school_id instead, for the same purpose.
 *
 * $design (all optional) lets the issuer customize the look of this one
 * certificate: template ('classic'|'modern'|'minimal'), font_choice
 * ('default'|'serif'|'sans'), bg_style ('solid'|'dots'|'gradient'),
 * title_text (overrides "Inkwell Certifications"), seal_label (overrides
 * the text inside the seal, defaults to $label), signer_name /
 * signer_title (overrides the default signer for just this certificate).
 */
function inkwell_db_add_manual_certificate($studentId, $studentName, $label, $issuedById, $issuedByName, $issuedByRole, $customMessage = '', $accentColor = '', $schoolId = null, array $design = []) {
  inkwell_ensure_certificate_columns();
  $pdo = inkwell_db();
  $id = bin2hex(random_bytes(8));
  $teacherId = $issuedByRole === 'teacher' ? $issuedById : null;
  $teacherName = $issuedByRole === 'teacher' ? $issuedByName : null;
  $accentColor = $accentColor !== '' ? $accentColor : null;
  $customMessage = $customMessage !== '' ? $customMessage : null;

  $template = trim($design['template'] ?? '') ?: null;
  $fontChoice = trim($design['font_choice'] ?? '') ?: null;
  $bgStyle = trim($design['bg_style'] ?? '') ?: null;
  $titleText = trim($design['title_text'] ?? '') ?: null;
  $sealLabel = trim($design['seal_label'] ?? '') ?: null;
  $signerName = trim($design['signer_name'] ?? '') ?: null;
  $signerTitle = trim($design['signer_title'] ?? '') ?: null;

  $stmt = $pdo->prepare(
    'INSERT INTO certificates
       (id, student_id, student_name, category_type, category_key, category_id, label, teacher_id, teacher_name, score, total, percent, issued_at, source, issued_by_name, issued_by_role, custom_message, accent_color, issuer_school_id, template, font_choice, bg_style, title_text, seal_label, signer_name_override, signer_title_override)
     VALUES (?, ?, ?, ?, NULL, NULL, ?, ?, ?, 100, 100, 100, CURDATE(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
  );
  $stmt->execute([
    $id, $studentId, $studentName, 'teacher', $label, $teacherId, $teacherName,
    'manual', $issuedByName, $issuedByRole, $customMessage, $accentColor, $schoolId,
    $template, $fontChoice, $bgStyle, $titleText, $sealLabel, $signerName, $signerTitle,
  ]);
  return ['ok' => true, 'id' => $id];
}

/** All certificates (exam + manually issued) for students the given teacher teaches. */
function inkwell_certificates_for_teacher($teacherId) {
  $pdo = inkwell_db();
  $stmt = $pdo->prepare(
    "SELECT DISTINCT c.* FROM certificates c
     WHERE c.teacher_id = ?
     ORDER BY c.issued_at DESC, c.id DESC"
  );
  $stmt->execute([$teacherId]);
  return $stmt->fetchAll();
}

/** All certificates (exam + manually issued) for students belonging to the given school. */
function inkwell_certificates_for_school($schoolId) {
  $pdo = inkwell_db();
  $stmt = $pdo->prepare(
    "SELECT DISTINCT c.* FROM certificates c
     JOIN users u ON u.id = c.student_id
     WHERE u.school_id = ?
     ORDER BY c.issued_at DESC, c.id DESC"
  );
  $stmt->execute([$schoolId]);
  return $stmt->fetchAll();
}

function inkwell_db_find_certificate($id) {
  $pdo = inkwell_db();
  $stmt = $pdo->prepare('SELECT * FROM certificates WHERE id = ?');
  $stmt->execute([$id]);
  return $stmt->fetch() ?: null;
}

function inkwell_db_all_certificates() {
  $pdo = inkwell_db();
  return $pdo->query('SELECT * FROM certificates ORDER BY issued_at DESC, id DESC')->fetchAll();
}

function inkwell_student_certificates($studentId) {
  $pdo = inkwell_db();
  $stmt = $pdo->prepare('SELECT * FROM certificates WHERE student_id = ? ORDER BY issued_at DESC, id DESC');
  $stmt->execute([$studentId]);
  return $stmt->fetchAll();
}

/**
 * Resolves who "signs" a given certificate — a school principal/president
 * set by a dean, a teacher's own signer, or the global admin default.
 * Precedence (most specific wins): teacher's personal signer > their
 * school's signer > the global config set in admin/index.php.
 * Returns ['name' => ..., 'title' => ..., 'signature_file' => ...|null].
 */
function inkwell_get_cert_signer($cert) {
  // A signer set specifically for this certificate (at issue time) beats
  // every other default.
  if (!empty($cert['signer_name_override'])) {
    return [
      'name' => $cert['signer_name_override'],
      'title' => $cert['signer_title_override'] ?: 'Signing Authority',
      'signature_file' => $cert['signer_signature_override'] ?? null,
    ];
  }

  $config = inkwell_get_config();
  $fallback = [
    'name' => $config['signer_name'],
    'title' => $config['signer_title'],
    'signature_file' => $config['signature_file'],
  ];

  $teacherId = $cert['teacher_id'] ?? null;
  if (!$teacherId) {
    // Dean-issued manual certs carry issuer_school_id instead of a teacher_id.
    $schoolId = $cert['issuer_school_id'] ?? null;
    if (!$schoolId) return $fallback;
    $pdo = inkwell_db();
    $stmt = $pdo->prepare('SELECT signer_name, signer_title, signer_signature FROM schools WHERE id = ?');
    $stmt->execute([$schoolId]);
    $school = $stmt->fetch();
    if ($school && !empty($school['signer_name'])) {
      return [
        'name' => $school['signer_name'],
        'title' => $school['signer_title'] ?: 'School Principal',
        'signature_file' => $school['signer_signature'],
      ];
    }
    return $fallback;
  }

  $pdo = inkwell_db();
  $stmt = $pdo->prepare('SELECT signer_name, signer_title, school_id FROM users WHERE id = ? AND role = ?');
  $stmt->execute([$teacherId, 'teacher']);
  $teacher = $stmt->fetch();
  if (!$teacher) return $fallback;

  // A teacher's own signer (if they set one) takes priority.
  if (!empty($teacher['signer_name'])) {
    return ['name' => $teacher['signer_name'], 'title' => $teacher['signer_title'] ?: 'Teacher', 'signature_file' => null];
  }

  // Otherwise fall back to the school's signer, if the teacher belongs to one.
  if (!empty($teacher['school_id'])) {
    $stmt = $pdo->prepare('SELECT signer_name, signer_title, signer_signature FROM schools WHERE id = ?');
    $stmt->execute([$teacher['school_id']]);
    $school = $stmt->fetch();
    if ($school && !empty($school['signer_name'])) {
      return [
        'name' => $school['signer_name'],
        'title' => $school['signer_title'] ?: 'School Principal',
        'signature_file' => $school['signer_signature'],
      ];
    }
  }

  return $fallback;
}

/** Self-healing: adds the dean-signature columns to `schools` if this DB predates them (see MIGRATION_ADD_dean_signature.sql). No-ops quietly on hosts that block DDL — callers degrade gracefully either way. */
function inkwell_ensure_school_signer_columns() {
  static $checked = false;
  if ($checked) return;
  $checked = true;
  $pdo = inkwell_db();
  try {
    $existing = $pdo->query('SHOW COLUMNS FROM schools')->fetchAll(PDO::FETCH_COLUMN);
  } catch (PDOException $e) {
    return;
  }
  $columns = [
    'dean_signer_title' => "ALTER TABLE schools ADD COLUMN dean_signer_title VARCHAR(150) DEFAULT NULL",
    'dean_signature' => "ALTER TABLE schools ADD COLUMN dean_signature VARCHAR(255) DEFAULT NULL",
  ];
  foreach ($columns as $name => $sql) {
    if (in_array($name, $existing, true)) continue;
    try { $pdo->exec($sql); } catch (PDOException $e) {}
  }
}

/**
 * Resolves which school logo (if any) should appear on a certificate —
 * mirrors inkwell_get_cert_signer()'s precedence: the teacher's own
 * school, or the issuer_school_id recorded on dean-issued certs. Returns
 * a filename (relative to /assets/uploads/) or null when there's no
 * school logo to show, so certificate.php falls back to the Inkwell
 * wordmark.
 */
function inkwell_get_cert_school_logo($cert) {
  $pdo = inkwell_db();
  $schoolId = null;

  $teacherId = $cert['teacher_id'] ?? null;
  if ($teacherId) {
    $stmt = $pdo->prepare('SELECT school_id FROM users WHERE id = ? AND role = ?');
    $stmt->execute([$teacherId, 'teacher']);
    $teacher = $stmt->fetch();
    $schoolId = $teacher['school_id'] ?? null;
  }
  if (!$schoolId) {
    $schoolId = $cert['issuer_school_id'] ?? null;
  }
  if (!$schoolId) return null;

  $stmt = $pdo->prepare('SELECT logo FROM schools WHERE id = ?');
  $stmt->execute([$schoolId]);
  $school = $stmt->fetch();
  return $school['logo'] ?? null;
}

/**
 * Looks up a school's Dean as a signer row (name always pulled live from
 * the dean's account; title defaults to "Dean"). Returns null if the
 * school or its dean can't be found. Shared by inkwell_get_cert_signers()
 * for both the "school only" and "teacher + dean" lineups below.
 */
function inkwell_school_dean_signer_row($schoolId) {
  if (!$schoolId) return null;
  $pdo = inkwell_db();
  inkwell_ensure_school_signer_columns();
  $baseSql = 'SELECT s.signer_name, s.signer_title, s.signer_signature, %s u.name AS dean_name
               FROM schools s JOIN users u ON u.id = s.dean_id WHERE s.id = ?';
  try {
    $stmt = $pdo->prepare(sprintf($baseSql, 's.dean_signer_title, s.dean_signature,'));
    $stmt->execute([$schoolId]);
    $school = $stmt->fetch();
  } catch (PDOException $e) {
    // dean_signer_title/dean_signature not migrated on this DB yet — degrade gracefully.
    $stmt = $pdo->prepare(sprintf($baseSql, ''));
    $stmt->execute([$schoolId]);
    $school = $stmt->fetch();
    if ($school) { $school['dean_signer_title'] = null; $school['dean_signature'] = null; }
  }
  return $school ?: null;
}

/**
 * Resolves the full signer lineup for a certificate. Returns an array of
 * ONE OR TWO signers:
 *  - A per-cert manual override always wins alone (the issuer explicitly
 *    chose exactly who should sign).
 *  - A teacher with their own personal signer set now co-signs WITH their
 *    school's Dean (teacher first, dean second) — a teacher's cert always
 *    carries their school's authority alongside their own.
 *  - No personal teacher signer, but a school is involved: the school's
 *    Dean, plus a President/Principal if the school set one.
 *  - Otherwise: the single global admin default signer.
 */
function inkwell_get_cert_signers($cert) {
  if (!empty($cert['signer_name_override'])) {
    return [[
      'name' => $cert['signer_name_override'],
      'title' => $cert['signer_title_override'] ?: 'Signing Authority',
      'signature_file' => $cert['signer_signature_override'] ?? null,
    ]];
  }

  $pdo = inkwell_db();
  $teacherId = $cert['teacher_id'] ?? null;
  $schoolId = null;

  if ($teacherId) {
    $stmt = $pdo->prepare('SELECT signer_name, signer_title, school_id FROM users WHERE id = ? AND role = ?');
    $stmt->execute([$teacherId, 'teacher']);
    $teacher = $stmt->fetch();
    $schoolId = $teacher['school_id'] ?? null;
    // A teacher's own personal signer co-signs alongside their school's
    // Dean (when there is one) — the cert then carries both names.
    if ($teacher && !empty($teacher['signer_name'])) {
      $signers = [[
        'name' => $teacher['signer_name'],
        'title' => $teacher['signer_title'] ?: 'Teacher',
        'signature_file' => null,
      ]];
      $dean = inkwell_school_dean_signer_row($schoolId);
      if ($dean) {
        $signers[] = [
          'name' => $dean['dean_name'],
          'title' => $dean['dean_signer_title'] ?: 'Dean',
          'signature_file' => $dean['dean_signature'],
        ];
      }
      return $signers;
    }
  }
  if (!$schoolId) $schoolId = $cert['issuer_school_id'] ?? null;

  $school = inkwell_school_dean_signer_row($schoolId);
  if ($school) {
    $signers = [[
      'name' => $school['dean_name'],
      'title' => $school['dean_signer_title'] ?: 'Dean',
      'signature_file' => $school['dean_signature'],
    ]];
    if (!empty($school['signer_name'])) {
      $signers[] = [
        'name' => $school['signer_name'],
        'title' => $school['signer_title'] ?: 'President',
        'signature_file' => $school['signer_signature'],
      ];
    }
    return $signers;
  }

  $config = inkwell_get_config();
  return [[
    'name' => $config['signer_name'],
    'title' => $config['signer_title'],
    'signature_file' => $config['signature_file'],
  ]];
}
