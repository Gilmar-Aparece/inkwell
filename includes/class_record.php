<?php
/**
 * E-Class Record — point-based gradebook that mirrors the school's Excel
 * template (BIT International College E-CLASS RECORD): Quizzes, Performance
 * Task, Attendance, Major Exam, and Essay sections, each converted from raw
 * item scores into a section point-total the teacher sets themselves
 * (instead of the Excel sheet's fixed 20/40/10/30 percent split).
 *
 * One record = one subject + one term (Prelim/Midterm/Final, reusing the
 * same term keys as inkwell_class_record_terms() in exams_db.php). A
 * teacher can add as many items as they want per section (Quiz 1, Quiz 2,
 * Essay 1...) and every student's row grows a new column automatically.
 *
 * Same self-healing table pattern used elsewhere (see includes/sections.php,
 * includes/departments.php) — CREATE TABLE IF NOT EXISTS at runtime; if the
 * host blocks that, the manual fallback lives in MIGRATION_ADD_class_record.sql.
 */

require_once __DIR__ . '/db.php';

/** Ordered section keys => display labels + the letter code shown in the Excel header (P/E/A/M/S). */
function inkwell_erecord_sections() {
  return [
    'quiz'        => 'Quizzes',
    'pt'          => 'Performance Task',
    'attendance'  => 'Attendance',
    'major_exam'  => 'Major Exam',
    'essay'       => 'Essay',
  ];
}

function inkwell_ensure_erecord_tables() {
  static $ok = null;
  if ($ok !== null) return $ok;
  $pdo = inkwell_db();
  try {
    $pdo->exec(
      "CREATE TABLE IF NOT EXISTS `erecord_config` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `subject_id` int(11) NOT NULL,
        `term` varchar(20) NOT NULL DEFAULT 'prelim',
        `instructor_name` varchar(150) DEFAULT NULL COMMENT 'defaults to the subject teacher, editable override',
        `time_schedule` varchar(150) DEFAULT NULL,
        `school_attended` varchar(150) DEFAULT NULL COMMENT 'school/university the instructor attended, shown in the record header',
        `quiz_points` decimal(6,2) NOT NULL DEFAULT 10.00,
        `pt_points` decimal(6,2) NOT NULL DEFAULT 10.00,
        `attendance_points` decimal(6,2) NOT NULL DEFAULT 5.00,
        `major_exam_points` decimal(6,2) NOT NULL DEFAULT 15.00,
        `essay_points` decimal(6,2) NOT NULL DEFAULT 10.00,
        `created_at` datetime NOT NULL DEFAULT current_timestamp(),
        `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
        PRIMARY KEY (`id`),
        UNIQUE KEY `subject_term` (`subject_id`, `term`)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );
    $pdo->exec(
      "CREATE TABLE IF NOT EXISTS `erecord_items` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `config_id` int(11) NOT NULL,
        `section` enum('quiz','pt','attendance','major_exam','essay') NOT NULL,
        `label` varchar(100) NOT NULL,
        `max_score` decimal(8,2) NOT NULL DEFAULT 100.00 COMMENT 'HPS — highest possible score for this one item',
        `sort_order` int(11) NOT NULL DEFAULT 0,
        `created_at` datetime NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`),
        KEY `config_id` (`config_id`),
        KEY `section` (`section`)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );
    $pdo->exec(
      "CREATE TABLE IF NOT EXISTS `erecord_scores` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `item_id` int(11) NOT NULL,
        `student_id` int(11) NOT NULL,
        `score` decimal(8,2) DEFAULT NULL,
        `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
        PRIMARY KEY (`id`),
        UNIQUE KEY `item_student` (`item_id`, `student_id`)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );
    $pdo->exec(
      "CREATE TABLE IF NOT EXISTS `erecord_overrides` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `config_id` int(11) NOT NULL,
        `student_id` int(11) NOT NULL,
        `fr` decimal(6,2) DEFAULT NULL COMMENT 'manual Final Rating override, blank = use computed total',
        `final_grade` decimal(6,2) DEFAULT NULL COMMENT 'manual Final Grade override, blank = use computed total',
        `remarks` varchar(50) DEFAULT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `config_student` (`config_id`, `student_id`)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );
  } catch (PDOException $e) {
    $ok = false;
    return $ok;
  }
  $ok = true;
  return $ok;
}

/** Self-heals the `school_attended` column onto erecord_config for hosts that
 *  already had the table before this field existed — same SHOW COLUMNS +
 *  ALTER TABLE pattern as inkwell_ensure_erecord_r_override_columns().
 */
function inkwell_ensure_erecord_school_attended_column() {
  static $ok = null;
  if ($ok !== null) return $ok;
  if (!inkwell_ensure_erecord_tables()) {
    $ok = false;
    return $ok;
  }
  $pdo = inkwell_db();
  try {
    $existing = $pdo->query('SHOW COLUMNS FROM erecord_config')->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('school_attended', $existing, true)) {
      $pdo->exec("ALTER TABLE erecord_config ADD COLUMN `school_attended` varchar(150) DEFAULT NULL COMMENT 'school/university the instructor attended, shown in the record header'");
    }
  } catch (PDOException $e) {
    $ok = false;
    return $ok;
  }
  $ok = true;
  return $ok;
}

/** Read-only lookup — unlike inkwell_erecord_get_or_create_config(), never
 *  creates a row. Used by grade-viewing pages (student/registrar) that
 *  should show "not recorded yet" instead of silently creating an empty
 *  class record just because someone looked at it.
 */
function inkwell_erecord_get_config($subjectId, $term) {
  if (!inkwell_ensure_erecord_tables()) return null;
  inkwell_ensure_erecord_school_attended_column();
  $pdo = inkwell_db();
  $stmt = $pdo->prepare('SELECT * FROM erecord_config WHERE subject_id = ? AND term = ?');
  $stmt->execute([$subjectId, $term]);
  return $stmt->fetch() ?: null;
}

/** Fetches (or creates with default points) the config row for a subject+term. */
function inkwell_erecord_get_or_create_config($subjectId, $term) {
  if (!inkwell_ensure_erecord_tables()) return null;
  inkwell_ensure_erecord_school_attended_column();
  $pdo = inkwell_db();
  $stmt = $pdo->prepare('SELECT * FROM erecord_config WHERE subject_id = ? AND term = ?');
  $stmt->execute([$subjectId, $term]);
  $row = $stmt->fetch();
  if ($row) return $row;

  $subjStmt = $pdo->prepare('SELECT sub.*, u.name AS teacher_name FROM subjects sub JOIN users u ON u.id = sub.teacher_id WHERE sub.id = ?');
  $subjStmt->execute([$subjectId]);
  $subject = $subjStmt->fetch();

  $ins = $pdo->prepare('INSERT INTO erecord_config (subject_id, term, instructor_name) VALUES (?, ?, ?)');
  $ins->execute([$subjectId, $term, $subject['teacher_name'] ?? null]);
  $stmt->execute([$subjectId, $term]);
  return $stmt->fetch();
}

function inkwell_erecord_save_header($configId, $instructorName, $timeSchedule, $schoolAttended = null) {
  if (!inkwell_ensure_erecord_tables()) return false;
  inkwell_ensure_erecord_school_attended_column();
  $pdo = inkwell_db();
  $stmt = $pdo->prepare('UPDATE erecord_config SET instructor_name = ?, time_schedule = ?, school_attended = ? WHERE id = ?');
  return $stmt->execute([trim($instructorName), trim($timeSchedule), trim((string) $schoolAttended), $configId]);
}

/** $points = ['quiz'=>10, 'pt'=>10, 'attendance'=>5, 'major_exam'=>15, 'essay'=>10] — any subset. */
function inkwell_erecord_save_points($configId, $points) {
  if (!inkwell_ensure_erecord_tables()) return false;
  $map = ['quiz' => 'quiz_points', 'pt' => 'pt_points', 'attendance' => 'attendance_points', 'major_exam' => 'major_exam_points', 'essay' => 'essay_points'];
  $sets = [];
  $vals = [];
  foreach ($points as $key => $val) {
    if (!isset($map[$key])) continue;
    $sets[] = $map[$key] . ' = ?';
    $vals[] = max(0, (float) $val);
  }
  if (empty($sets)) return false;
  $vals[] = $configId;
  $pdo = inkwell_db();
  $stmt = $pdo->prepare('UPDATE erecord_config SET ' . implode(', ', $sets) . ' WHERE id = ?');
  return $stmt->execute($vals);
}

/** All items for a config, grouped by section, in sort order. */
function inkwell_erecord_items($configId) {
  if (!inkwell_ensure_erecord_tables()) return [];
  $pdo = inkwell_db();
  $stmt = $pdo->prepare('SELECT * FROM erecord_items WHERE config_id = ? ORDER BY section, sort_order ASC, id ASC');
  $stmt->execute([$configId]);
  $rows = $stmt->fetchAll();
  $grouped = array_fill_keys(array_keys(inkwell_erecord_sections()), []);
  foreach ($rows as $r) {
    $grouped[$r['section']][] = $r;
  }
  return $grouped;
}

/** Adds a new item column (e.g. "Quiz 3", HPS 10) — this is what makes a new exam auto-appear as a column. */
function inkwell_erecord_add_item($configId, $section, $label, $maxScore) {
  if (!inkwell_ensure_erecord_tables()) return ['ok' => false, 'error' => 'Class Record tables are not available on this host.'];
  $sections = inkwell_erecord_sections();
  if (!isset($sections[$section])) return ['ok' => false, 'error' => 'Unknown section.'];
  $label = trim($label);
  if ($label === '') $label = $sections[$section] . ' ' . (rand(1, 999));
  $maxScore = max(0.01, (float) $maxScore);
  $pdo = inkwell_db();
  $ord = $pdo->prepare('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM erecord_items WHERE config_id = ? AND section = ?');
  $ord->execute([$configId, $section]);
  $nextOrder = (int) $ord->fetchColumn();
  $stmt = $pdo->prepare('INSERT INTO erecord_items (config_id, section, label, max_score, sort_order) VALUES (?, ?, ?, ?, ?)');
  $stmt->execute([$configId, $section, $label, $maxScore, $nextOrder]);
  return ['ok' => true, 'id' => (int) $pdo->lastInsertId()];
}

function inkwell_erecord_delete_item($itemId) {
  if (!inkwell_ensure_erecord_tables()) return false;
  $pdo = inkwell_db();
  $pdo->prepare('DELETE FROM erecord_scores WHERE item_id = ?')->execute([$itemId]);
  return $pdo->prepare('DELETE FROM erecord_items WHERE id = ?')->execute([$itemId]);
}

/** All scores for a config's items, keyed "studentId:itemId" => score (float|null). */
function inkwell_erecord_scores($configId) {
  if (!inkwell_ensure_erecord_tables()) return [];
  $pdo = inkwell_db();
  $stmt = $pdo->prepare(
    'SELECT s.student_id, s.item_id, s.score FROM erecord_scores s
     JOIN erecord_items i ON i.id = s.item_id WHERE i.config_id = ?'
  );
  $stmt->execute([$configId]);
  $out = [];
  foreach ($stmt->fetchAll() as $r) {
    $out[$r['student_id'] . ':' . $r['item_id']] = $r['score'] === null ? null : (float) $r['score'];
  }
  return $out;
}

function inkwell_erecord_save_score($itemId, $studentId, $score) {
  if (!inkwell_ensure_erecord_tables()) return false;
  $pdo = inkwell_db();
  if ($score === '' || $score === null) {
    $stmt = $pdo->prepare('DELETE FROM erecord_scores WHERE item_id = ? AND student_id = ?');
    return $stmt->execute([$itemId, $studentId]);
  }
  $stmt = $pdo->prepare(
    'INSERT INTO erecord_scores (item_id, student_id, score) VALUES (?, ?, ?)
     ON DUPLICATE KEY UPDATE score = VALUES(score)'
  );
  return $stmt->execute([$itemId, $studentId, (float) $score]);
}

/** Self-heals the five per-section "R override" columns onto erecord_overrides
 *  (quiz_r, pt_r, attendance_r, major_exam_r, essay_r) — same SHOW COLUMNS +
 *  ALTER TABLE pattern used by includes/sections.php. Lets a teacher type a
 *  manual R value straight into the table even when no items exist yet,
 *  instead of only being able to override the row-level FR/Final Grade.
 */
function inkwell_ensure_erecord_r_override_columns() {
  static $ok = null;
  if ($ok !== null) return $ok;
  if (!inkwell_ensure_erecord_tables()) {
    $ok = false;
    return $ok;
  }
  $pdo = inkwell_db();
  try {
    $existing = $pdo->query('SHOW COLUMNS FROM erecord_overrides')->fetchAll(PDO::FETCH_COLUMN);
  } catch (PDOException $e) {
    $ok = false;
    return $ok;
  }
  foreach (['quiz_r', 'pt_r', 'attendance_r', 'major_exam_r', 'essay_r'] as $col) {
    if (!in_array($col, $existing, true)) {
      try {
        $pdo->exec("ALTER TABLE erecord_overrides ADD COLUMN `$col` decimal(6,2) DEFAULT NULL COMMENT 'manual R override for this section, blank = use computed'");
      } catch (PDOException $e) {
        $ok = false;
        return $ok;
      }
    }
  }
  $ok = true;
  return $ok;
}

function inkwell_erecord_overrides($configId) {
  if (!inkwell_ensure_erecord_tables()) return [];
  inkwell_ensure_erecord_r_override_columns();
  $pdo = inkwell_db();
  $stmt = $pdo->prepare('SELECT * FROM erecord_overrides WHERE config_id = ?');
  $stmt->execute([$configId]);
  $out = [];
  foreach ($stmt->fetchAll() as $r) $out[(int) $r['student_id']] = $r;
  return $out;
}

/** $sectionR = ['quiz'=>val, 'pt'=>val, ...] — any subset, blank/null clears back to computed. */
function inkwell_erecord_save_override($configId, $studentId, $fr, $finalGrade, $remarks, $sectionR = []) {
  if (!inkwell_ensure_erecord_tables()) return false;
  inkwell_ensure_erecord_r_override_columns();
  $pdo = inkwell_db();

  $cols = ['config_id', 'student_id', 'fr', 'final_grade', 'remarks'];
  $vals = [
    $configId, $studentId,
    ($fr === '' || $fr === null) ? null : (float) $fr,
    ($finalGrade === '' || $finalGrade === null) ? null : (float) $finalGrade,
    ($remarks === '') ? null : $remarks,
  ];
  $rColMap = ['quiz' => 'quiz_r', 'pt' => 'pt_r', 'attendance' => 'attendance_r', 'major_exam' => 'major_exam_r', 'essay' => 'essay_r'];
  foreach ($rColMap as $section => $col) {
    if (array_key_exists($section, $sectionR)) {
      $cols[] = $col;
      $v = $sectionR[$section];
      $vals[] = ($v === '' || $v === null) ? null : (float) $v;
    }
  }

  $placeholders = implode(', ', array_fill(0, count($cols), '?'));
  $colList = implode(', ', array_map(fn($c) => "`$c`", $cols));
  $updateCols = array_slice($cols, 2); // everything except config_id/student_id
  $updateSql = implode(', ', array_map(fn($c) => "`$c` = VALUES(`$c`)", $updateCols));

  $stmt = $pdo->prepare(
    "INSERT INTO erecord_overrides ($colList) VALUES ($placeholders)
     ON DUPLICATE KEY UPDATE $updateSql"
  );
  return $stmt->execute($vals);
}

/** Roster for a subject — approved-enrolled students, alphabetical (same convention as inkwell_section_students). */
function inkwell_erecord_roster($subjectId) {
  $pdo = inkwell_db();
  $stmt = $pdo->prepare(
    "SELECT u.* FROM enrollments e JOIN users u ON u.id = e.student_id
     WHERE e.subject_id = ? AND e.status = 'approved' ORDER BY u.name ASC"
  );
  $stmt->execute([$subjectId]);
  return $stmt->fetchAll();
}

/**
 * Turns items + scores into the per-student, per-section T (raw total) /
 * HPS (highest possible score) / R (points earned) numbers, plus row
 * Total, exactly mirroring the Excel sheet's Quizzes/PT/Attendance/Major
 * exam block layout — just with a teacher-set point cap per section
 * instead of a fixed percent.
 */
function inkwell_erecord_compute($config, $items, $scores, $students, $overrides = []) {
  $sectionPointsMap = [
    'quiz' => (float) $config['quiz_points'],
    'pt' => (float) $config['pt_points'],
    'attendance' => (float) $config['attendance_points'],
    'major_exam' => (float) $config['major_exam_points'],
    'essay' => (float) $config['essay_points'],
  ];
  $rColMap = ['quiz' => 'quiz_r', 'pt' => 'pt_r', 'attendance' => 'attendance_r', 'major_exam' => 'major_exam_r', 'essay' => 'essay_r'];

  $rows = [];
  foreach ($students as $student) {
    $stuId = (int) $student['id'];
    $ov = $overrides[$stuId] ?? null;
    $sectionResults = [];
    $total = 0.0;

    foreach ($items as $section => $sectionItems) {
      $hps = 0.0;
      $t = 0.0;
      $any = false;
      foreach ($sectionItems as $item) {
        $hps += (float) $item['max_score'];
        $key = $stuId . ':' . $item['id'];
        if (array_key_exists($key, $scores) && $scores[$key] !== null) {
          $t += $scores[$key];
          $any = true;
        }
      }
      $r = ($hps > 0 && $any) ? round(($t / $hps) * $sectionPointsMap[$section], 2) : 0.0;

      $rOverridden = false;
      $rCol = $rColMap[$section];
      if ($ov && isset($ov[$rCol]) && $ov[$rCol] !== null) {
        $r = (float) $ov[$rCol];
        $rOverridden = true;
      }

      $sectionResults[$section] = ['t' => $t, 'hps' => $hps, 'r' => $r, 'has_scores' => $any, 'r_overridden' => $rOverridden];
      $total += $r;
    }

    $rows[$stuId] = [
      'student' => $student,
      'sections' => $sectionResults,
      'total' => round($total, 2),
    ];
  }
  return $rows;
}

/** Sum of every section's max points — the "out of" ceiling shown next to Total. */
function inkwell_erecord_max_total($config) {
  return round(
    (float) $config['quiz_points'] + (float) $config['pt_points'] + (float) $config['attendance_points']
    + (float) $config['major_exam_points'] + (float) $config['essay_points'],
    2
  );
}

/**
 * One student's grade row for one subject, across all three terms —
 * used by the student "My Grades" page and the registrar grade-viewer.
 * Never creates a class record; a term with no config yet just comes back
 * with recorded = false. Returns:
 *   ['prelim' => [...], 'midterm' => [...], 'final' => [...]]
 * each either ['recorded' => false] or
 *   ['recorded' => true, 'total' => .., 'max_total' => .., 'fr' => .., 'final_grade' => .., 'remarks' => ..]
 */
function inkwell_erecord_student_subject_summary($subjectId, $student) {
  $out = [];
  foreach (array_keys(inkwell_class_record_terms()) as $term) {
    $config = inkwell_erecord_get_config($subjectId, $term);
    if (!$config) {
      $out[$term] = ['recorded' => false];
      continue;
    }
    $items = inkwell_erecord_items($config['id']);
    $scores = inkwell_erecord_scores($config['id']);
    $overrides = inkwell_erecord_overrides($config['id']);
    $computed = inkwell_erecord_compute($config, $items, $scores, [$student], $overrides);
    $stuId = (int) $student['id'];
    $row = $computed[$stuId] ?? ['total' => 0];
    $ov = $overrides[$stuId] ?? [];
    $out[$term] = [
      'recorded' => true,
      'total' => $row['total'],
      'max_total' => inkwell_erecord_max_total($config),
      'fr' => ($ov['fr'] ?? '') !== '' && $ov['fr'] !== null ? (float) $ov['fr'] : $row['total'],
      'final_grade' => ($ov['final_grade'] ?? '') !== '' && $ov['final_grade'] !== null ? (float) $ov['final_grade'] : $row['total'],
      'remarks' => $ov['remarks'] ?? '',
    ];
  }
  return $out;
}
