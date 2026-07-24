<?php
/**
 * Sections — a teacher-named group of students taking the same block of
 * subjects together (Philippine-style, e.g. "BSIT-1A" or just "Section A").
 * A teacher creates a section; other teachers can request to join/teach in
 * it, and the creator (the adviser) approves or declines. A teacher's own
 * subjects can be tagged onto a section they belong to. Students don't
 * "join" a section directly — their section is whichever section a subject
 * they're enrolled in belongs to, and My Section (my-section.php) lists
 * every subject under that section so they can see/join the rest of it.
 *
 * Same self-healing table/column pattern used elsewhere in this codebase
 * (see includes/departments.php) — works without a manual migration step
 * on hosts that grant the app's DB user CREATE/ALTER rights, and degrades
 * gracefully (features just stay hidden) on hosts that don't.
 */

require_once __DIR__ . '/db.php';

/** Creates the `sections` table if missing. Returns true if usable. */
function inkwell_ensure_sections_table() {
  static $ok = null;
  if ($ok !== null) return $ok;
  $pdo = inkwell_db();
  try {
    $pdo->exec(
      "CREATE TABLE IF NOT EXISTS `sections` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `teacher_id` int(11) NOT NULL COMMENT 'creator / adviser',
        `school_id` int(11) DEFAULT NULL,
        `name` varchar(100) NOT NULL COMMENT 'teacher-chosen, e.g. \"Section A\"',
        `term` varchar(20) DEFAULT NULL,
        `academic_year` varchar(20) DEFAULT NULL,
        `created_at` datetime NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`),
        KEY `teacher_id` (`teacher_id`),
        KEY `school_id` (`school_id`)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );
  } catch (PDOException $e) {
    $ok = false;
    return $ok;
  }
  $ok = true;
  return $ok;
}

/** Creates the `section_teachers` join/approval table if missing. */
function inkwell_ensure_section_teachers_table() {
  static $ok = null;
  if ($ok !== null) return $ok;
  if (!inkwell_ensure_sections_table()) {
    $ok = false;
    return $ok;
  }
  $pdo = inkwell_db();
  try {
    $pdo->exec(
      "CREATE TABLE IF NOT EXISTS `section_teachers` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `section_id` int(11) NOT NULL,
        `teacher_id` int(11) NOT NULL,
        `status` varchar(20) NOT NULL DEFAULT 'pending' COMMENT 'pending | approved',
        `requested_at` datetime NOT NULL DEFAULT current_timestamp(),
        `decided_at` datetime DEFAULT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `section_teacher` (`section_id`, `teacher_id`)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );
  } catch (PDOException $e) {
    $ok = false;
    return $ok;
  }
  $ok = true;
  return $ok;
}

/** Self-heals `year_level` onto `sections` (e.g. "1st Year" .. "4th Year"). */
function inkwell_ensure_section_year_level_column() {
  static $ok = null;
  if ($ok !== null) return $ok;
  if (!inkwell_ensure_sections_table()) {
    $ok = false;
    return $ok;
  }
  $pdo = inkwell_db();
  try {
    $existing = $pdo->query('SHOW COLUMNS FROM sections')->fetchAll(PDO::FETCH_COLUMN);
  } catch (PDOException $e) {
    $ok = false;
    return $ok;
  }
  if (!in_array('year_level', $existing, true)) {
    try {
      $pdo->exec("ALTER TABLE sections ADD COLUMN year_level VARCHAR(20) DEFAULT NULL COMMENT 'e.g. \"1st Year\"' AFTER name");
    } catch (PDOException $e) {
      $ok = false;
      return $ok;
    }
  }
  $ok = true;
  return $ok;
}

/** Self-heals `section_id` onto `subjects` so a subject can be tagged to a section. */
function inkwell_ensure_subject_section_column() {
  static $ok = null;
  if ($ok !== null) return $ok;
  $pdo = inkwell_db();
  try {
    $existing = $pdo->query('SHOW COLUMNS FROM subjects')->fetchAll(PDO::FETCH_COLUMN);
  } catch (PDOException $e) {
    $ok = false;
    return $ok;
  }
  if (!in_array('section_id', $existing, true)) {
    try {
      $pdo->exec("ALTER TABLE subjects ADD COLUMN section_id INT(11) DEFAULT NULL AFTER units");
    } catch (PDOException $e) {
      $ok = false;
      return $ok;
    }
  }
  $ok = true;
  return $ok;
}

/** Creates a section owned/advised by this teacher. */
function inkwell_create_section($teacherId, $schoolId, $name, $term = '', $academicYear = '', $yearLevel = '') {
  if (!inkwell_ensure_sections_table()) {
    return ['ok' => false, 'error' => "This host isn't letting the app create the sections table automatically. Contact support."];
  }
  $hasYearLevel = inkwell_ensure_section_year_level_column();
  $name = trim($name);
  if ($name === '') {
    return ['ok' => false, 'error' => 'Give the section a name.'];
  }
  $pdo = inkwell_db();
  $fields = ['teacher_id', 'school_id', 'name', 'term', 'academic_year'];
  $placeholders = ['?', '?', '?', '?', '?'];
  $values = [$teacherId, $schoolId ?: null, $name, $term !== '' ? $term : null, $academicYear !== '' ? $academicYear : null];
  if ($hasYearLevel) {
    $fields[] = 'year_level';
    $placeholders[] = '?';
    $values[] = $yearLevel !== '' ? $yearLevel : null;
  }
  $stmt = $pdo->prepare('INSERT INTO sections (' . implode(', ', $fields) . ') VALUES (' . implode(', ', $placeholders) . ')');
  $stmt->execute($values);
  return ['ok' => true, 'id' => (int) $pdo->lastInsertId()];
}

function inkwell_get_section($id) {
  if (!inkwell_ensure_sections_table()) return null;
  inkwell_ensure_section_year_level_column();
  $pdo = inkwell_db();
  $stmt = $pdo->prepare('SELECT * FROM sections WHERE id = ?');
  $stmt->execute([$id]);
  return $stmt->fetch() ?: null;
}

/** Sections this teacher created (is the adviser of). */
function inkwell_teacher_owned_sections($teacherId) {
  if (!inkwell_ensure_sections_table()) return [];
  inkwell_ensure_subject_section_column();
  inkwell_ensure_section_year_level_column();
  $pdo = inkwell_db();
  $stmt = $pdo->prepare(
    "SELECT s.*, (SELECT COUNT(*) FROM subjects sub WHERE sub.section_id = s.id) AS subject_count
     FROM sections s WHERE s.teacher_id = ? ORDER BY s.created_at DESC"
  );
  $stmt->execute([$teacherId]);
  return $stmt->fetchAll();
}

/** Sections this teacher was approved into (not the owner). */
function inkwell_teacher_member_sections($teacherId) {
  if (!inkwell_ensure_section_teachers_table()) return [];
  inkwell_ensure_subject_section_column();
  inkwell_ensure_section_year_level_column();
  $pdo = inkwell_db();
  $stmt = $pdo->prepare(
    "SELECT s.*, u.name AS adviser_name,
            (SELECT COUNT(*) FROM subjects sub WHERE sub.section_id = s.id) AS subject_count
     FROM section_teachers st
     JOIN sections s ON s.id = st.section_id
     JOIN users u ON u.id = s.teacher_id
     WHERE st.teacher_id = ? AND st.status = 'approved'
     ORDER BY s.created_at DESC"
  );
  $stmt->execute([$teacherId]);
  return $stmt->fetchAll();
}

/** Every section this teacher can tag a subject to — owned + approved member sections. */
function inkwell_teacher_all_sections($teacherId) {
  $owned = inkwell_teacher_owned_sections($teacherId);
  $member = inkwell_teacher_member_sections($teacherId);
  return array_merge($owned, $member);
}

/** Sections at this teacher's school they don't already belong to (owner or approved/pending member) — for the "join a section" browse list. */
function inkwell_school_sections_to_join($schoolId, $teacherId) {
  if (!inkwell_ensure_section_teachers_table() || !$schoolId) return [];
  inkwell_ensure_subject_section_column();
  inkwell_ensure_section_year_level_column();
  $pdo = inkwell_db();
  $stmt = $pdo->prepare(
    "SELECT s.*, u.name AS adviser_name,
            (SELECT COUNT(*) FROM subjects sub WHERE sub.section_id = s.id) AS subject_count,
            (SELECT status FROM section_teachers st WHERE st.section_id = s.id AND st.teacher_id = ?) AS my_status
     FROM sections s
     JOIN users u ON u.id = s.teacher_id
     WHERE s.school_id = ? AND s.teacher_id != ?
     ORDER BY s.created_at DESC"
  );
  $stmt->execute([$teacherId, $schoolId, $teacherId]);
  return $stmt->fetchAll();
}

/** Teacher requests to join/teach in a section. Creates a 'pending' row; safe to call repeatedly. */
function inkwell_request_join_section($teacherId, $sectionId) {
  if (!inkwell_ensure_section_teachers_table()) return false;
  $pdo = inkwell_db();
  $stmt = $pdo->prepare("INSERT IGNORE INTO section_teachers (section_id, teacher_id, status) VALUES (?, ?, 'pending')");
  return $stmt->execute([$sectionId, $teacherId]);
}

/** Pending teacher join-requests across every section this teacher owns — for their approval panel. */
function inkwell_teacher_pending_section_requests($teacherId) {
  if (!inkwell_ensure_section_teachers_table()) return [];
  $pdo = inkwell_db();
  $stmt = $pdo->prepare(
    "SELECT st.*, s.name AS section_name, u.name AS teacher_name
     FROM section_teachers st
     JOIN sections s ON s.id = st.section_id
     JOIN users u ON u.id = st.teacher_id
     WHERE s.teacher_id = ? AND st.status = 'pending'
     ORDER BY st.requested_at ASC"
  );
  $stmt->execute([$teacherId]);
  return $stmt->fetchAll();
}

function inkwell_approve_section_request($requestId) {
  $pdo = inkwell_db();
  $stmt = $pdo->prepare("UPDATE section_teachers SET status = 'approved', decided_at = NOW() WHERE id = ?");
  return $stmt->execute([$requestId]);
}

function inkwell_reject_section_request($requestId) {
  $pdo = inkwell_db();
  $stmt = $pdo->prepare('DELETE FROM section_teachers WHERE id = ?');
  return $stmt->execute([$requestId]);
}

/** Approved teachers (plus the adviser) attached to a section — for display. */
function inkwell_section_teacher_list($sectionId) {
  $section = inkwell_get_section($sectionId);
  if (!$section) return [];
  $pdo = inkwell_db();
  $list = [];
  $stmt = $pdo->prepare('SELECT id, name FROM users WHERE id = ?');
  $stmt->execute([$section['teacher_id']]);
  if ($adviser = $stmt->fetch()) {
    $list[] = ['id' => $adviser['id'], 'name' => $adviser['name'], 'is_adviser' => true];
  }
  if (inkwell_ensure_section_teachers_table()) {
    $stmt = $pdo->prepare(
      "SELECT u.id, u.name FROM section_teachers st JOIN users u ON u.id = st.teacher_id
       WHERE st.section_id = ? AND st.status = 'approved' ORDER BY u.name ASC"
    );
    $stmt->execute([$sectionId]);
    foreach ($stmt->fetchAll() as $t) {
      $list[] = ['id' => $t['id'], 'name' => $t['name'], 'is_adviser' => false];
    }
  }
  return $list;
}

/** Every subject tagged to this section. */
function inkwell_section_subjects($sectionId) {
  if (!inkwell_ensure_subject_section_column()) return [];
  inkwell_ensure_section_year_level_column();
  $pdo = inkwell_db();
  $stmt = $pdo->prepare(
    "SELECT s.*, u.name AS teacher_name,
            (SELECT COUNT(*) FROM exam_categories c WHERE c.subject_id = s.id) AS exam_count
     FROM subjects s JOIN users u ON u.id = s.teacher_id
     WHERE s.section_id = ? ORDER BY s.title ASC"
  );
  $stmt->execute([$sectionId]);
  return $stmt->fetchAll();
}

/**
 * Every student approved-enrolled in a subject tagged to this section —
 * i.e. the section's roster, derived the same way inkwell_student_sections()
 * derives a student's section (there's no separate "section membership"
 * table; a student is "in" a section by way of the subjects they're in).
 */
function inkwell_section_students($sectionId) {
  if (!inkwell_ensure_subject_section_column()) return [];
  $pdo = inkwell_db();
  $stmt = $pdo->prepare(
    "SELECT DISTINCT u.id, u.name, u.email, u.avatar
     FROM enrollments e
     JOIN subjects sub ON sub.id = e.subject_id
     JOIN users u ON u.id = e.student_id
     WHERE sub.section_id = ? AND e.status = 'approved'
     ORDER BY u.name ASC"
  );
  $stmt->execute([$sectionId]);
  return $stmt->fetchAll();
}

/**
 * Class Record data for one section, scoped to the given teacher's own
 * subjects within it (a section can have several teachers, each only
 * sees/exports their own gradebook for it). Shape:
 * [
 *   'subjects'  => [subject rows, each subject_id keyed],
 *   'students'  => [student rows — the section roster],
 *   'assessments_by_subject' => [subject_id => [exam_categories rows]],
 *   'latest_attempts' => ["studentId:categoryId" => attempt row],
 * ]
 * Every assessment (exam or project — see `kind`) is scored using the
 * student's most recent attempt, same convention as
 * inkwell_student_latest_attempt() uses elsewhere in the app.
 */
function inkwell_section_class_record($sectionId, $teacherId) {
  if (!inkwell_ensure_subject_section_column()) {
    return ['subjects' => [], 'students' => [], 'assessments_by_subject' => [], 'latest_attempts' => []];
  }
  inkwell_ensure_category_kind_column();
  inkwell_ensure_category_term_column();
  $pdo = inkwell_db();

  $stmt = $pdo->prepare('SELECT * FROM subjects WHERE section_id = ? AND teacher_id = ? ORDER BY title ASC');
  $stmt->execute([$sectionId, $teacherId]);
  $subjects = $stmt->fetchAll();

  $students = inkwell_section_students($sectionId);

  $assessmentsBySubject = [];
  $latestAttempts = [];

  if (!empty($subjects)) {
    $subjectIds = array_map('intval', array_column($subjects, 'id'));
    $subjPh = implode(',', array_fill(0, count($subjectIds), '?'));
    $stmt = $pdo->prepare("SELECT * FROM exam_categories WHERE subject_id IN ($subjPh) ORDER BY created_at ASC");
    $stmt->execute($subjectIds);
    $assessments = $stmt->fetchAll();
    foreach ($assessments as $asm) {
      $assessmentsBySubject[(int) $asm['subject_id']][] = $asm;
    }

    if (!empty($assessments) && !empty($students)) {
      $catIds = array_map('intval', array_column($assessments, 'id'));
      $studentIds = array_map('intval', array_column($students, 'id'));
      $catPh = implode(',', array_fill(0, count($catIds), '?'));
      $stuPh = implode(',', array_fill(0, count($studentIds), '?'));
      $stmt = $pdo->prepare(
        "SELECT * FROM attempts WHERE category_id IN ($catPh) AND student_id IN ($stuPh) ORDER BY submitted_at ASC"
      );
      $stmt->execute(array_merge($catIds, $studentIds));
      // Ordered oldest -> newest, so the last write for a given pair wins,
      // leaving each student+assessment's MOST RECENT attempt.
      foreach ($stmt->fetchAll() as $a) {
        $latestAttempts[$a['student_id'] . ':' . $a['category_id']] = $a;
      }
    }
  }

  return [
    'subjects' => $subjects,
    'students' => $students,
    'assessments_by_subject' => $assessmentsBySubject,
    'latest_attempts' => $latestAttempts,
  ];
}

/**
 * Turns the raw inkwell_section_class_record() shape into per-subject
 * score tables + averages, ready for either the Class Record page or the
 * Excel export to render — kept in one place so both stay in sync.
 * Only graded attempts count toward an average; a pending/missing
 * assessment is skipped rather than treated as a zero.
 */
function inkwell_class_record_compute($record) {
  $perSubject = [];
  $subjectAverages = []; // studentId => [subjectId => finalGrade]
  $terms = array_keys(inkwell_class_record_terms());

  foreach ($record['subjects'] as $subj) {
    $sid = (int) $subj['id'];
    $assessments = $record['assessments_by_subject'][$sid] ?? [];
    $scores = []; // studentId => [catId => ['percent'=>, 'status'=>]]
    $termGrades = []; // studentId => [term => avg or null]
    $finalGrades = []; // studentId => avg-of-terms or null

    // Split this subject's assessments into their three terms up front so
    // the table can render three grouped column-blocks (Prelim/Midterm/Final).
    $assessmentsByTerm = array_fill_keys($terms, []);
    foreach ($assessments as $asm) {
      $t = in_array($asm['term'] ?? 'prelim', $terms, true) ? $asm['term'] : 'prelim';
      $assessmentsByTerm[$t][] = $asm;
    }

    foreach ($record['students'] as $student) {
      $stuId = (int) $student['id'];
      $rowScores = [];
      $rowTermGrades = [];
      $termGradeVals = [];

      foreach ($terms as $t) {
        $gradedPercents = [];
        foreach ($assessmentsByTerm[$t] as $asm) {
          $catId = (int) $asm['id'];
          $attempt = $record['latest_attempts'][$stuId . ':' . $catId] ?? null;
          if (!$attempt) {
            $rowScores[$catId] = ['percent' => null, 'status' => 'none'];
          } elseif ($attempt['status'] !== 'graded') {
            $rowScores[$catId] = ['percent' => null, 'status' => 'pending'];
          } else {
            $pct = (int) $attempt['percent'];
            $rowScores[$catId] = ['percent' => $pct, 'status' => 'graded'];
            $gradedPercents[] = $pct;
          }
        }
        $termGrade = !empty($gradedPercents) ? round(array_sum($gradedPercents) / count($gradedPercents), 1) : null;
        $rowTermGrades[$t] = $termGrade;
        if ($termGrade !== null) $termGradeVals[] = $termGrade;
      }

      $scores[$stuId] = $rowScores;
      $termGrades[$stuId] = $rowTermGrades;
      $finalGrades[$stuId] = !empty($termGradeVals) ? round(array_sum($termGradeVals) / count($termGradeVals), 1) : null;
      $subjectAverages[$stuId][$sid] = $finalGrades[$stuId];
    }

    $perSubject[$sid] = [
      'subject' => $subj,
      'assessments' => $assessments,
      'assessments_by_term' => $assessmentsByTerm,
      'scores' => $scores,
      'term_grades' => $termGrades,
      'averages' => $finalGrades, // kept for backward compatibility with callers expecting per-subject averages
    ];
  }

  $overallAverages = [];
  foreach ($record['students'] as $student) {
    $stuId = (int) $student['id'];
    $vals = array_filter($subjectAverages[$stuId] ?? [], function ($v) { return $v !== null; });
    $overallAverages[$stuId] = !empty($vals) ? round(array_sum($vals) / count($vals), 1) : null;
  }

  return [
    'per_subject' => $perSubject,
    'overall_averages' => $overallAverages,
  ];
}

/**
 * Tags one of a teacher's own subjects onto a section they belong to
 * (owner or approved member). Pass a null/0 $sectionId to un-tag it.
 */
function inkwell_set_subject_section($subjectId, $teacherId, $sectionId) {
  if (!inkwell_ensure_subject_section_column()) {
    return ['ok' => false, 'error' => "This host isn't letting the app tag subjects to a section automatically. Contact support."];
  }
  $pdo = inkwell_db();
  $stmt = $pdo->prepare('SELECT teacher_id FROM subjects WHERE id = ?');
  $stmt->execute([$subjectId]);
  $subject = $stmt->fetch();
  if (!$subject || (int) $subject['teacher_id'] !== (int) $teacherId) {
    return ['ok' => false, 'error' => "That subject isn't yours."];
  }
  if ($sectionId) {
    $allowed = array_column(inkwell_teacher_all_sections($teacherId), 'id');
    if (!in_array((int) $sectionId, array_map('intval', $allowed), true)) {
      return ['ok' => false, 'error' => "You don't belong to that section."];
    }
  } else {
    $sectionId = null;
  }
  $stmt = $pdo->prepare('UPDATE subjects SET section_id = ? WHERE id = ?');
  $stmt->execute([$sectionId, $subjectId]);
  return ['ok' => true];
}

/**
 * A student's section(s) — derived from whichever section(s) the subjects
 * they're APPROVED-enrolled in belong to. Most students will have exactly
 * one, but this returns all in case a student is enrolled across sections.
 */
function inkwell_student_sections($studentId) {
  if (!inkwell_ensure_subject_section_column() || !inkwell_ensure_sections_table()) return [];
  inkwell_ensure_section_year_level_column();
  $pdo = inkwell_db();
  $stmt = $pdo->prepare(
    "SELECT DISTINCT s.*, u.name AS adviser_name
     FROM enrollments e
     JOIN subjects sub ON sub.id = e.subject_id
     JOIN sections s ON s.id = sub.section_id
     JOIN users u ON u.id = s.teacher_id
     WHERE e.student_id = ? AND e.status = 'approved'
     ORDER BY s.name ASC"
  );
  $stmt->execute([$studentId]);
  return $stmt->fetchAll();
}
