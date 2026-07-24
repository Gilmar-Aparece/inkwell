<?php
/**
 * Extended student profile fields — the address/guardian/prior-school
 * details a registrar can capture beyond the basics on `users`
 * (name/email/id_number/course/department_id). Kept in a separate
 * one-row-per-student table instead of bolting 15+ columns onto `users`,
 * since only registrars touch these and most other queries never need
 * them.
 *
 * Same self-healing table pattern used elsewhere (inkwell_ensure_departments_table()
 * etc.) — CREATE TABLE IF NOT EXISTS on first use.
 */

require_once __DIR__ . '/db.php';

/** Creates the `student_profiles` table (if missing). Returns true if the table is usable. */
function inkwell_ensure_student_profiles_table() {
  static $ok = null;
  if ($ok !== null) return $ok;
  $pdo = inkwell_db();
  try {
    $pdo->exec(
      "CREATE TABLE IF NOT EXISTS `student_profiles` (
        `user_id` int(11) NOT NULL,
        `birth_date` date DEFAULT NULL,
        `sex` varchar(20) DEFAULT NULL,
        `civil_status` varchar(30) DEFAULT NULL,
        `nationality` varchar(100) DEFAULT NULL,
        `religion` varchar(100) DEFAULT NULL,
        `lrn_number` varchar(20) DEFAULT NULL COMMENT 'Learner Reference Number (DepEd)',
        `street` varchar(150) DEFAULT NULL,
        `barangay` varchar(100) DEFAULT NULL,
        `city` varchar(100) DEFAULT NULL,
        `province` varchar(100) DEFAULT NULL,
        `contact_number` varchar(30) DEFAULT NULL,
        `guardian_name` varchar(150) DEFAULT NULL,
        `guardian_relationship` varchar(50) DEFAULT NULL,
        `guardian_contact` varchar(30) DEFAULT NULL,
        `elementary_school` varchar(150) DEFAULT NULL,
        `elementary_years` varchar(50) DEFAULT NULL COMMENT 'e.g. \"2012-2018\"',
        `high_school` varchar(150) DEFAULT NULL,
        `high_school_years` varchar(50) DEFAULT NULL,
        `senior_high_school` varchar(150) DEFAULT NULL,
        `senior_high_years` varchar(50) DEFAULT NULL,
        `degree_completed` varchar(150) DEFAULT NULL COMMENT 'Prior degree, if any (e.g. shifting/transferee students)',
        `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
        `updated_by` int(11) DEFAULT NULL COMMENT 'registrar user id who last saved this',
        PRIMARY KEY (`user_id`)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );
  } catch (PDOException $e) {
    $ok = false;
    return $ok;
  }
  $ok = true;
  return $ok;
}

/** The full set of editable field keys, in display order — used by both the form and the save handler so they can't drift apart. */
function inkwell_student_profile_field_keys() {
  return [
    'birth_date', 'sex', 'civil_status', 'nationality', 'religion', 'lrn_number',
    'street', 'barangay', 'city', 'province', 'contact_number',
    'guardian_name', 'guardian_relationship', 'guardian_contact',
    'elementary_school', 'elementary_years', 'high_school', 'high_school_years',
    'senior_high_school', 'senior_high_years', 'degree_completed',
  ];
}

/** Extended profile row for one student, or all-null defaults if none saved yet. */
function inkwell_get_student_profile_fields($userId) {
  if (!inkwell_ensure_student_profiles_table()) return null;
  $pdo = inkwell_db();
  $stmt = $pdo->prepare('SELECT * FROM student_profiles WHERE user_id = ?');
  $stmt->execute([$userId]);
  $row = $stmt->fetch();
  if ($row) return $row;
  $blank = array_fill_keys(inkwell_student_profile_field_keys(), null);
  $blank['user_id'] = (int) $userId;
  return $blank;
}

/**
 * Saves the extended profile fields for a student. Scoped the same way
 * as inkwell_update_student_info(): $schoolId must be the registrar's
 * own school_id, checked against the student's school_id.
 */
function inkwell_save_student_profile_fields($studentId, $schoolId, array $fields, $registrarId) {
  if (!inkwell_ensure_student_profiles_table()) return ['ok' => false, 'error' => 'Extended profiles are unavailable on this host.'];

  $pdo = inkwell_db();
  $stmt = $pdo->prepare("SELECT id, school_id FROM users WHERE id = ? AND role = 'student'");
  $stmt->execute([$studentId]);
  $student = $stmt->fetch();
  if (!$student) return ['ok' => false, 'error' => 'Student not found.'];
  if (empty($schoolId) || (int) $student['school_id'] !== (int) $schoolId) {
    return ['ok' => false, 'error' => 'That student is not part of your school.'];
  }

  $keys = inkwell_student_profile_field_keys();
  $values = [];
  foreach ($keys as $k) {
    $v = trim((string) ($fields[$k] ?? ''));
    if ($k === 'birth_date') {
      $values[$k] = ($v !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) ? $v : null;
    } else {
      $values[$k] = $v === '' ? null : mb_substr($v, 0, 150);
    }
  }

  $cols = implode(', ', $keys);
  $placeholders = implode(', ', array_fill(0, count($keys), '?'));
  $updates = implode(', ', array_map(function ($k) { return "$k = VALUES($k)"; }, $keys));

  $sql = "INSERT INTO student_profiles (user_id, $cols, updated_by) VALUES (?, $placeholders, ?)
          ON DUPLICATE KEY UPDATE $updates, updated_by = VALUES(updated_by)";
  $stmt = $pdo->prepare($sql);
  $stmt->execute(array_merge([$studentId], array_values($values), [$registrarId]));

  return ['ok' => true];
}
