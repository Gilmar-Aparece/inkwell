<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/exams_db.php';
require_once __DIR__ . '/../includes/exam_docx.php';

$user = inkwell_require_role('teacher');

$examId = (int) ($_GET['id'] ?? 0);
$category = inkwell_get_teacher_category($examId);
if (!$category || (int) $category['teacher_id'] !== (int) $user['id']) {
  http_response_code(404);
  die('Exam not found.');
}

$questions = inkwell_get_teacher_questions($examId);
inkwell_stream_exam_docx($category, $questions);
