<?php
/**
 * Aggregate stats for the Registrar's "Reports" page — the real
 * implementation behind the pricing card's "Admin dashboard & reporting"
 * feature. Everything here is scoped to one school (by school_id / by the
 * school's teachers), read-only, and safe to call on every request.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/exams_db.php';
require_once __DIR__ . '/schools.php';

/** People + content counts for the school header cards. Reuses inkwell_school_stats() plus dean count. */
function inkwell_report_overview($schoolId) {
  $pdo = inkwell_db();
  $stats = inkwell_school_stats($schoolId);
  $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = 'dean' AND school_id = ? AND status != 'disabled'");
  $stmt->execute([$schoolId]);
  $deanCount = (int) $stmt->fetchColumn();

  $stmt = $pdo->prepare(
    "SELECT COUNT(*) FROM exam_categories c JOIN users t ON t.id = c.teacher_id WHERE t.school_id = ?"
  );
  $stmt->execute([$schoolId]);
  $examCount = (int) $stmt->fetchColumn();

  return [
    'teacher_count' => (int) ($stats['teacher_count'] ?? 0),
    'dean_count' => $deanCount,
    'student_count' => (int) ($stats['student_count'] ?? 0),
    'subject_count' => (int) ($stats['subject_count'] ?? 0),
    'exam_count' => $examCount,
    'certificate_count' => (int) ($stats['certificate_count'] ?? 0),
  ];
}

/** School-wide exam attempt totals: volume, grading backlog, pass rate, average score. */
function inkwell_report_exam_totals($schoolId) {
  $pdo = inkwell_db();
  $stmt = $pdo->prepare(
    "SELECT
       COUNT(*) AS total_attempts,
       SUM(a.status = 'pending') AS pending_attempts,
       SUM(a.status = 'graded') AS graded_attempts,
       SUM(a.passed = 1) AS passed_count,
       SUM(a.status = 'graded' AND a.passed = 0) AS failed_count,
       AVG(CASE WHEN a.status = 'graded' THEN a.percent END) AS avg_percent
     FROM attempts a
     JOIN exam_categories c ON c.id = a.category_id
     JOIN users t ON t.id = c.teacher_id
     WHERE t.school_id = ?"
  );
  $stmt->execute([$schoolId]);
  $row = $stmt->fetch() ?: [];

  $graded = (int) ($row['graded_attempts'] ?? 0);
  $passed = (int) ($row['passed_count'] ?? 0);

  return [
    'total_attempts' => (int) ($row['total_attempts'] ?? 0),
    'pending_attempts' => (int) ($row['pending_attempts'] ?? 0),
    'graded_attempts' => $graded,
    'passed_count' => $passed,
    'failed_count' => (int) ($row['failed_count'] ?? 0),
    'pass_rate' => $graded > 0 ? round(($passed / $graded) * 100) : null,
    'avg_percent' => $row['avg_percent'] !== null ? round((float) $row['avg_percent']) : null,
  ];
}

/**
 * One row per subject at this school: title, code, teacher, enrolled
 * students, exam count, attempt volume, pass rate, average score.
 * Ordered by most attempts first so the busiest subjects surface on top.
 */
function inkwell_report_subject_breakdown($schoolId) {
  $regCols = inkwell_ensure_subject_registrar_columns();
  if (!$regCols['school_id']) return []; // column not migrated yet on this host
  $pdo = inkwell_db();
  $stmt = $pdo->prepare(
    "SELECT s.id, s.title, s.code, u.name AS teacher_name,
            (SELECT COUNT(*) FROM enrollments e WHERE e.subject_id = s.id AND e.status = 'approved') AS student_count,
            (SELECT COUNT(*) FROM exam_categories c WHERE c.subject_id = s.id) AS exam_count,
            COUNT(a.id) AS attempt_count,
            SUM(a.status = 'graded') AS graded_count,
            SUM(a.passed = 1) AS passed_count,
            AVG(CASE WHEN a.status = 'graded' THEN a.percent END) AS avg_percent
     FROM subjects s
     JOIN users u ON u.id = s.teacher_id
     LEFT JOIN exam_categories c ON c.subject_id = s.id
     LEFT JOIN attempts a ON a.category_id = c.id
     WHERE s.school_id = ?
     GROUP BY s.id, s.title, s.code, u.name
     ORDER BY attempt_count DESC, s.title ASC"
  );
  $stmt->execute([$schoolId]);
  $rows = $stmt->fetchAll();
  foreach ($rows as &$r) {
    $graded = (int) $r['graded_count'];
    $r['attempt_count'] = (int) $r['attempt_count'];
    $r['student_count'] = (int) $r['student_count'];
    $r['exam_count'] = (int) $r['exam_count'];
    $r['pass_rate'] = $graded > 0 ? round(((int) $r['passed_count'] / $graded) * 100) : null;
    $r['avg_percent'] = $r['avg_percent'] !== null ? round((float) $r['avg_percent']) : null;
  }
  unset($r);
  return $rows;
}

/**
 * One row per active teacher at this school: subjects owned, students
 * across those subjects, attempt volume, pass rate. Ordered by attempt
 * volume so the most active teachers surface first.
 */
function inkwell_report_teacher_breakdown($schoolId) {
  $pdo = inkwell_db();
  $stmt = $pdo->prepare(
    "SELECT t.id, t.name,
            (SELECT COUNT(*) FROM subjects s2 WHERE s2.teacher_id = t.id) AS subject_count,
            (SELECT COUNT(*) FROM enrollments e JOIN subjects s3 ON s3.id = e.subject_id WHERE s3.teacher_id = t.id AND e.status = 'approved') AS student_count,
            COUNT(a.id) AS attempt_count,
            SUM(a.status = 'graded') AS graded_count,
            SUM(a.passed = 1) AS passed_count
     FROM users t
     LEFT JOIN exam_categories c ON c.teacher_id = t.id
     LEFT JOIN attempts a ON a.category_id = c.id
     WHERE t.role = 'teacher' AND t.school_id = ? AND t.status != 'disabled'
     GROUP BY t.id, t.name
     ORDER BY attempt_count DESC, t.name ASC"
  );
  $stmt->execute([$schoolId]);
  $rows = $stmt->fetchAll();
  foreach ($rows as &$r) {
    $graded = (int) $r['graded_count'];
    $r['attempt_count'] = (int) $r['attempt_count'];
    $r['student_count'] = (int) $r['student_count'];
    $r['subject_count'] = (int) $r['subject_count'];
    $r['pass_rate'] = $graded > 0 ? round(((int) $r['passed_count'] / $graded) * 100) : null;
  }
  unset($r);
  return $rows;
}

/** Most recent exam attempts across the whole school, newest first. */
function inkwell_report_recent_attempts($schoolId, $limit = 15) {
  $pdo = inkwell_db();
  $stmt = $pdo->prepare(
    "SELECT a.id, a.status, a.percent, a.passed, a.submitted_at, a.graded_at,
            stu.name AS student_name, c.title AS exam_title, t.name AS teacher_name
     FROM attempts a
     JOIN exam_categories c ON c.id = a.category_id
     JOIN users t ON t.id = c.teacher_id
     JOIN users stu ON stu.id = a.student_id
     WHERE t.school_id = ?
     ORDER BY a.submitted_at DESC
     LIMIT " . (int) $limit
  );
  $stmt->execute([$schoolId]);
  return $stmt->fetchAll();
}

/**
 * Plain-text version of the whole report — mirrors inkwell_attempt_text_report()'s
 * pattern in exams_db.php — for the "Download report" button on registrar/reports.php.
 */
function inkwell_report_text($school, $overview, $examTotals, $subjectRows, $teacherRows) {
  $lines = [];
  $lines[] = strtoupper($school['name']) . ' — SCHOOL REPORT';
  $lines[] = 'Generated ' . date('M j, Y g:i A');
  $lines[] = str_repeat('=', 60);
  $lines[] = '';
  $lines[] = 'OVERVIEW';
  $lines[] = "Teachers: {$overview['teacher_count']}   Deans: {$overview['dean_count']}   Students: {$overview['student_count']}";
  $lines[] = "Subjects: {$overview['subject_count']}   Exams: {$overview['exam_count']}   Certificates issued: {$overview['certificate_count']}";
  $lines[] = '';
  $lines[] = 'EXAM ACTIVITY';
  $lines[] = "Total attempts: {$examTotals['total_attempts']}   Graded: {$examTotals['graded_attempts']}   Awaiting grading: {$examTotals['pending_attempts']}";
  $lines[] = 'Pass rate: ' . ($examTotals['pass_rate'] !== null ? $examTotals['pass_rate'] . '%' : 'n/a');
  $lines[] = 'Average score: ' . ($examTotals['avg_percent'] !== null ? $examTotals['avg_percent'] . '%' : 'n/a');
  $lines[] = '';
  $lines[] = 'BY SUBJECT';
  foreach ($subjectRows as $r) {
    $lines[] = sprintf(
      '- %s%s (%s) — %d students, %d exams, %d attempts, pass rate %s, avg %s',
      $r['title'], $r['code'] ? " [{$r['code']}]" : '', $r['teacher_name'],
      $r['student_count'], $r['exam_count'], $r['attempt_count'],
      $r['pass_rate'] !== null ? $r['pass_rate'] . '%' : 'n/a',
      $r['avg_percent'] !== null ? $r['avg_percent'] . '%' : 'n/a'
    );
  }
  if (!$subjectRows) $lines[] = '(no subjects yet)';
  $lines[] = '';
  $lines[] = 'BY TEACHER';
  foreach ($teacherRows as $r) {
    $lines[] = sprintf(
      '- %s — %d subjects, %d students, %d attempts, pass rate %s',
      $r['name'], $r['subject_count'], $r['student_count'], $r['attempt_count'],
      $r['pass_rate'] !== null ? $r['pass_rate'] . '%' : 'n/a'
    );
  }
  if (!$teacherRows) $lines[] = '(no teachers yet)';

  return implode("\n", $lines) . "\n";
}
