<?php
/**
 * Departments (BSEED / BSIT / BSHM / ...) — a manageable list (not a
 * hardcoded enum) so an Admin/Registrar can add more later without a code
 * change. Departments are tagged onto `users` (teacher + dean accounts)
 * and `subjects` (which `exam_categories` inherit via subject_id), so a
 * Dean can be scoped to `(school_id, department_id)` instead of just
 * `school_id` — letting a school have one Dean per department instead of
 * only one Dean total. Students are intentionally NOT tagged with a
 * department.
 *
 * Same self-healing column pattern used elsewhere in this codebase
 * (inkwell_ensure_subject_code_units_columns() etc.) so this works
 * without a manual migration step on hosts that do grant the app's DB
 * user ALTER/CREATE rights, and degrades gracefully (falls back to no
 * department scoping) on hosts that don't — in which case run
 * MIGRATION_ADD_departments.sql once via phpMyAdmin.
 */

require_once __DIR__ . '/db.php';

/** Creates the `departments` table (if missing) and seeds BSEED/BSIT/BSHM the first time. Returns true if the table is usable. */
function inkwell_ensure_departments_table() {
  static $ok = null;
  if ($ok !== null) return $ok;
  $pdo = inkwell_db();
  try {
    $pdo->exec(
      "CREATE TABLE IF NOT EXISTS `departments` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `code` varchar(20) NOT NULL,
        `name` varchar(150) NOT NULL,
        `created_at` datetime NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`),
        UNIQUE KEY `code` (`code`)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );
  } catch (PDOException $e) {
    $ok = false;
    return $ok;
  }
  try {
    $count = (int) $pdo->query('SELECT COUNT(*) FROM departments')->fetchColumn();
    if ($count === 0) {
      $stmt = $pdo->prepare('INSERT INTO departments (code, name) VALUES (?, ?)');
      foreach ([
        ['BSEED', 'Bachelor of Secondary Education'],
        ['BSIT', 'Bachelor of Science in Information Technology'],
        ['BSHM', 'Bachelor of Science in Hospitality Management'],
      ] as $seed) {
        $stmt->execute($seed);
      }
    }
  } catch (PDOException $e) {
    // Non-fatal — table exists but seeding failed (e.g. race with another request); ignore.
  }
  $ok = true;
  return $ok;
}

/**
 * Self-heals `department_id` onto `users` and `subjects`. Returns which
 * of the two actually have the column, so callers can skip department
 * scoping/filtering gracefully instead of crashing when a host doesn't
 * grant ALTER TABLE rights. Run MIGRATION_ADD_departments.sql from
 * phpMyAdmin if that happens.
 */
function inkwell_ensure_department_columns() {
  static $result = null;
  if ($result !== null) return $result;
  if (!inkwell_ensure_departments_table()) {
    $result = ['users' => false, 'subjects' => false];
    return $result;
  }
  $pdo = inkwell_db();
  $result = ['users' => false, 'subjects' => false];
  foreach (['users', 'subjects'] as $table) {
    try {
      $existing = $pdo->query("SHOW COLUMNS FROM {$table}")->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
      continue;
    }
    if (!in_array('department_id', $existing, true)) {
      try {
        $pdo->exec("ALTER TABLE {$table} ADD COLUMN department_id INT(11) DEFAULT NULL AFTER school_id");
      } catch (PDOException $e) {
        // No ALTER privilege on this host — leave $result[$table] = false.
        continue;
      }
    }
    $result[$table] = true;
  }
  return $result;
}

/** All departments, code ascending — used to populate every department dropdown. */
function inkwell_list_departments() {
  if (!inkwell_ensure_departments_table()) return [];
  $pdo = inkwell_db();
  return $pdo->query('SELECT * FROM departments ORDER BY code ASC')->fetchAll();
}

function inkwell_get_department($id) {
  if (!$id || !inkwell_ensure_departments_table()) return null;
  $pdo = inkwell_db();
  $stmt = $pdo->prepare('SELECT * FROM departments WHERE id = ?');
  $stmt->execute([$id]);
  return $stmt->fetch() ?: null;
}

/** Lets a Registrar/Admin add more departments over time beyond the seeded BSEED/BSIT/BSHM. */
function inkwell_create_department($code, $name) {
  if (!inkwell_ensure_departments_table()) {
    return ['ok' => false, 'error' => "This host isn't letting the app create the departments table automatically. Run MIGRATION_ADD_departments.sql once via phpMyAdmin, then try again."];
  }
  $code = strtoupper(trim($code));
  $name = trim($name);
  if ($code === '' || $name === '') {
    return ['ok' => false, 'error' => 'Both a code and a name are required.'];
  }
  $pdo = inkwell_db();
  $stmt = $pdo->prepare('SELECT id FROM departments WHERE code = ?');
  $stmt->execute([$code]);
  if ($stmt->fetch()) {
    return ['ok' => false, 'error' => 'A department with that code already exists.'];
  }
  $stmt = $pdo->prepare('INSERT INTO departments (code, name) VALUES (?, ?)');
  $stmt->execute([$code, $name]);
  return ['ok' => true, 'id' => (int) $pdo->lastInsertId()];
}
