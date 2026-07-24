<?php
/**
 * Curriculum builder — lets a registrar define, per (department, year
 * level), which subjects a "regular" student is required to take. This
 * is what actually drives the Enrollment Portal's "+ Add Subjects on
 * Curriculum" button for regular students (see enroll.php); before this
 * existed, that button just added every subject the school offered,
 * regardless of the student's program or year.
 *
 * Same self-healing table pattern used elsewhere in this codebase
 * (inkwell_ensure_departments_table() etc.) — CREATE TABLE IF NOT EXISTS
 * on first use, degrading gracefully (curriculum features simply return
 * empty) on hosts that don't grant CREATE TABLE rights.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/departments.php';

/** Creates the `curriculum_subjects` table (if missing). Returns true if the table is usable. */
function inkwell_ensure_curriculum_table() {
  static $ok = null;
  if ($ok !== null) return $ok;
  $pdo = inkwell_db();
  try {
    $pdo->exec(
      "CREATE TABLE IF NOT EXISTS `curriculum_subjects` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `school_id` int(11) NOT NULL,
        `department_id` int(11) NOT NULL,
        `year_level` varchar(20) NOT NULL COMMENT 'e.g. \"1st Year\" — see inkwell_year_levels()',
        `term` varchar(20) DEFAULT NULL COMMENT 'e.g. \"1st Semester\" — optional, blank = required every term',
        `subject_id` int(11) NOT NULL,
        `created_by` int(11) DEFAULT NULL COMMENT 'registrar user id who added this slot',
        `created_at` datetime NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`),
        UNIQUE KEY `slot_subject` (`school_id`,`department_id`,`year_level`,`term`,`subject_id`),
        KEY `subject_id` (`subject_id`)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );
  } catch (PDOException $e) {
    $ok = false;
    return $ok;
  }
  $ok = true;
  return $ok;
}

/**
 * Every curriculum slot for a school, joined with subject + department
 * info, grouped by department then year level — used to render the
 * registrar's Curriculum Builder overview.
 */
function inkwell_curriculum_overview($schoolId) {
  if (!inkwell_ensure_curriculum_table()) return [];
  $pdo = inkwell_db();
  $stmt = $pdo->prepare(
    "SELECT cs.*, s.title AS subject_title, s.code AS subject_code, d.code AS dept_code, d.name AS dept_name
     FROM curriculum_subjects cs
     JOIN subjects s ON s.id = cs.subject_id
     JOIN departments d ON d.id = cs.department_id
     WHERE cs.school_id = ?
     ORDER BY d.code ASC, FIELD(cs.year_level,'1st Year','2nd Year','3rd Year','4th Year'), s.title ASC"
  );
  $stmt->execute([$schoolId]);
  $rows = $stmt->fetchAll();

  $grouped = [];
  foreach ($rows as $row) {
    $deptKey = $row['department_id'];
    if (!isset($grouped[$deptKey])) {
      $grouped[$deptKey] = ['department_id' => (int) $row['department_id'], 'dept_code' => $row['dept_code'], 'dept_name' => $row['dept_name'], 'years' => []];
    }
    $yl = $row['year_level'];
    if (!isset($grouped[$deptKey]['years'][$yl])) $grouped[$deptKey]['years'][$yl] = [];
    $grouped[$deptKey]['years'][$yl][] = $row;
  }
  return array_values($grouped);
}

/** Subject IDs currently assigned to one (department, year level[, term]) curriculum slot. */
function inkwell_curriculum_slot_subject_ids($schoolId, $departmentId, $yearLevel, $term = null) {
  if (!inkwell_ensure_curriculum_table()) return [];
  $pdo = inkwell_db();
  if ($term) {
    $stmt = $pdo->prepare('SELECT subject_id FROM curriculum_subjects WHERE school_id = ? AND department_id = ? AND year_level = ? AND (term = ? OR term IS NULL)');
    $stmt->execute([$schoolId, $departmentId, $yearLevel, $term]);
  } else {
    $stmt = $pdo->prepare('SELECT subject_id FROM curriculum_subjects WHERE school_id = ? AND department_id = ? AND year_level = ?');
    $stmt->execute([$schoolId, $departmentId, $yearLevel]);
  }
  return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

/** Replaces the whole subject list for one (department, year level, term) slot in a single transaction. */
function inkwell_set_curriculum_slot($schoolId, $departmentId, $yearLevel, $term, $subjectIds, $registrarId) {
  if (!inkwell_ensure_curriculum_table()) return ['ok' => false, 'error' => 'Curriculum builder is unavailable on this host.'];
  $schoolId = (int) $schoolId;
  $departmentId = (int) $departmentId;
  $yearLevel = trim($yearLevel);
  $term = trim((string) $term);
  $term = $term === '' ? null : $term;
  $subjectIds = array_unique(array_map('intval', $subjectIds));

  if ($schoolId <= 0 || $departmentId <= 0 || $yearLevel === '') {
    return ['ok' => false, 'error' => 'Pick a department and year level first.'];
  }

  $pdo = inkwell_db();
  $pdo->beginTransaction();
  try {
    $del = $pdo->prepare('DELETE FROM curriculum_subjects WHERE school_id = ? AND department_id = ? AND year_level = ? AND ' . ($term === null ? 'term IS NULL' : 'term = ?'));
    $term === null ? $del->execute([$schoolId, $departmentId, $yearLevel]) : $del->execute([$schoolId, $departmentId, $yearLevel, $term]);

    if (!empty($subjectIds)) {
      $ins = $pdo->prepare('INSERT INTO curriculum_subjects (school_id, department_id, year_level, term, subject_id, created_by) VALUES (?, ?, ?, ?, ?, ?)');
      foreach ($subjectIds as $sid) {
        if ($sid <= 0) continue;
        $ins->execute([$schoolId, $departmentId, $yearLevel, $term, $sid, $registrarId]);
      }
    }
    $pdo->commit();
    return ['ok' => true, 'count' => count($subjectIds)];
  } catch (PDOException $e) {
    $pdo->rollBack();
    return ['ok' => false, 'error' => 'Could not save curriculum: ' . $e->getMessage()];
  }
}

/**
 * The subjects a *regular* student should see pre-added in the
 * Enrollment Portal, based on their assigned department. Returns each
 * subject plus its curriculum `slot_year_level` / `slot_term` so the
 * front-end can filter to the year/term the student picked in the form.
 * Falls back to an empty array if the student has no department_id set
 * (registrar hasn't assigned one yet) or no curriculum has been built —
 * enroll.php falls back to "every school subject" in that case, same as
 * before this feature existed.
 */
function inkwell_student_curriculum_subjects($schoolId, $departmentId) {
  if (!inkwell_ensure_curriculum_table() || empty($departmentId)) return [];
  $pdo = inkwell_db();
  $stmt = $pdo->prepare(
    "SELECT s.*, u.name AS teacher_name, cs.year_level AS slot_year_level, cs.term AS slot_term
     FROM curriculum_subjects cs
     JOIN subjects s ON s.id = cs.subject_id
     JOIN users u ON u.id = s.teacher_id
     WHERE cs.school_id = ? AND cs.department_id = ? AND u.status = 'active'
     ORDER BY s.title ASC"
  );
  $stmt->execute([$schoolId, $departmentId]);
  return $stmt->fetchAll();
}
