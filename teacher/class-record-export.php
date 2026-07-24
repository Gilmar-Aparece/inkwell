<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/exams_db.php';
require_once __DIR__ . '/../includes/sections.php';
require_once __DIR__ . '/../includes/xlsx_writer.php';

$user = inkwell_require_role('teacher');
if ($user['status'] !== 'active') {
  http_response_code(403);
  die('Not available.');
}

$sectionId = (int) ($_GET['section_id'] ?? 0);
$section = inkwell_get_section($sectionId);
if (!$section) {
  http_response_code(404);
  die('Section not found.');
}

$isAdviser = (int) $section['teacher_id'] === (int) $user['id'];
$myMemberSectionIds = array_map('intval', array_column(inkwell_teacher_member_sections($user['id']), 'id'));
$belongs = $isAdviser || in_array($sectionId, $myMemberSectionIds, true);
if (!$belongs) {
  http_response_code(404);
  die('Section not found.');
}

$record = inkwell_section_class_record($sectionId, $user['id']);
$summary = inkwell_class_record_compute($record);

$sheets = [];

// Summary sheet (only worth it with more than one subject, but harmless with one).
if (!empty($record['subjects']) && !empty($record['students'])) {
  $summaryRows = [];
  $header = ['Student'];
  foreach ($record['subjects'] as $subj) $header[] = $subj['title'];
  $header[] = 'Overall Final Grade';
  $summaryRows[] = $header;

  foreach ($record['students'] as $student) {
    $stuId = (int) $student['id'];
    $row = [$student['name']];
    foreach ($record['subjects'] as $subj) {
      $sid = (int) $subj['id'];
      $avg = $summary['per_subject'][$sid]['averages'][$stuId] ?? null;
      $row[] = $avg !== null ? $avg : '—';
    }
    $overall = $summary['overall_averages'][$stuId] ?? null;
    $row[] = $overall !== null ? $overall : '—';
    $summaryRows[] = $row;
  }
  $sheets[] = ['name' => 'Summary', 'rows' => $summaryRows];
}

// One sheet per subject: columns grouped by term (Prelim/Midterm/Final),
// each group followed by that term's Term Grade, plus a trailing Final
// Grade column (the average of the three term grades).
$__terms = inkwell_class_record_terms();
foreach ($record['subjects'] as $subj) {
  $sid = (int) $subj['id'];
  $subjData = $summary['per_subject'][$sid];
  $assessments = $subjData['assessments'];
  $assessmentsByTerm = $subjData['assessments_by_term'];

  $rows = [];
  $header = ['Student'];
  foreach ($__terms as $termKey => $termLabel) {
    foreach ($assessmentsByTerm[$termKey] as $asm) {
      $kindLabel = ($asm['kind'] ?? 'exam') === 'project' ? 'Project' : 'Exam';
      $header[] = $termLabel . ': ' . $asm['title'] . ' (' . $kindLabel . ')';
    }
    if (!empty($assessmentsByTerm[$termKey])) $header[] = $termLabel . ' Grade';
  }
  $header[] = 'Final Grade';
  $rows[] = $header;

  foreach ($record['students'] as $student) {
    $stuId = (int) $student['id'];
    $row = [$student['name']];
    foreach ($__terms as $termKey => $termLabel) {
      foreach ($assessmentsByTerm[$termKey] as $asm) {
        $catId = (int) $asm['id'];
        $cell = $subjData['scores'][$stuId][$catId] ?? ['percent' => null, 'status' => 'none'];
        if ($cell['status'] === 'graded') {
          $row[] = (int) $cell['percent'];
        } elseif ($cell['status'] === 'pending') {
          $row[] = 'Pending';
        } else {
          $row[] = '—';
        }
      }
      if (!empty($assessmentsByTerm[$termKey])) {
        $termGrade = $subjData['term_grades'][$stuId][$termKey] ?? null;
        $row[] = $termGrade !== null ? $termGrade : '—';
      }
    }
    $finalGrade = $subjData['averages'][$stuId] ?? null;
    $row[] = $finalGrade !== null ? $finalGrade : '—';
    $rows[] = $row;
  }

  $sheets[] = ['name' => $subj['title'], 'rows' => $rows];
}

$safeSectionName = preg_replace('/[^a-zA-Z0-9_-]+/', '-', $section['name']);
inkwell_xlsx_download($sheets, 'class-record-' . $safeSectionName . '.xlsx');
