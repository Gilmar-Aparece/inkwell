<?php
/**
 * Generates a printable Word (.docx) version of an exam, laid out like a
 * classic school periodical test: centered title, Name/Section/Score line,
 * "Directions" line, then numbered items with a blank before the number
 * (for the student to write their answer) and A/B/C/D choices in a clean
 * two-column grid for multiple-choice items. Essay/code items get ruled
 * space to write on instead of choices. A teacher-only answer key page is
 * appended at the end.
 *
 * No external libraries — a .docx is just a zip of XML parts, built here
 * by hand with ZipArchive.
 */

function inkwell_docx_esc($s) {
  return htmlspecialchars((string) $s, ENT_QUOTES | ENT_XML1, 'UTF-8');
}

/** A single run of text, optionally bold. */
function inkwell_docx_run($text, $bold = false, $size = 24) {
  $rpr = '<w:rPr><w:rFonts w:ascii="Times New Roman" w:hAnsi="Times New Roman"/><w:sz w:val="' . (int) $size . '"/>' . ($bold ? '<w:b/>' : '') . '</w:rPr>';
  return '<w:r>' . $rpr . '<w:t xml:space="preserve">' . inkwell_docx_esc($text) . '</w:t></w:r>';
}

/** A paragraph made of one or more runs (array of ['text'=>, 'bold'=>]). */
function inkwell_docx_p($runsOrText, $align = null, $spacingAfter = 160, $bold = false, $size = 24) {
  $runs = is_array($runsOrText) ? $runsOrText : [['text' => $runsOrText, 'bold' => $bold]];
  $out = '<w:p><w:pPr>';
  if ($align) $out .= '<w:jc w:val="' . $align . '"/>';
  $out .= '<w:spacing w:after="' . (int) $spacingAfter . '"/></w:pPr>';
  foreach ($runs as $r) {
    $out .= inkwell_docx_run($r['text'], $r['bold'] ?? false, $r['size'] ?? $size);
  }
  return $out . '</w:p>';
}

/** Borderless 2x2 table used for A/C on row 1, B/D on row 2. */
function inkwell_docx_options_table($a, $b, $c, $d) {
  $cell = function ($label, $text) {
    return '<w:tc><w:tcPr><w:tcW w:w="4500" w:type="dxa"/><w:tcBorders>'
      . '<w:top w:val="nil"/><w:bottom w:val="nil"/><w:left w:val="nil"/><w:right w:val="nil"/>'
      . '</w:tcBorders></w:tcPr>'
      . '<w:p><w:pPr><w:spacing w:after="80"/><w:ind w:left="360"/></w:pPr>'
      . inkwell_docx_run($label . '. ' . $text, false, 24)
      . '</w:p></w:tc>';
  };
  return '<w:tbl><w:tblPr><w:tblW w:w="0" w:type="auto"/><w:tblBorders>'
    . '<w:top w:val="none" w:sz="0" w:space="0" w:color="auto"/><w:bottom w:val="none" w:sz="0" w:space="0" w:color="auto"/>'
    . '<w:left w:val="none" w:sz="0" w:space="0" w:color="auto"/><w:right w:val="none" w:sz="0" w:space="0" w:color="auto"/>'
    . '<w:insideH w:val="none" w:sz="0" w:space="0" w:color="auto"/><w:insideV w:val="none" w:sz="0" w:space="0" w:color="auto"/>'
    . '</w:tblBorders><w:tblLayout w:type="fixed"/></w:tblPr>'
    . '<w:tblGrid><w:gridCol w:w="4500"/><w:gridCol w:w="4500"/></w:tblGrid>'
    . '<w:tr>' . $cell('A', $a) . $cell('C', $c) . '</w:tr>'
    . '<w:tr>' . $cell('B', $b) . $cell('D', $d) . '</w:tr>'
    . '</w:tbl>';
}

/** A couple of ruled (underscored) lines for a written answer — essay/code items. */
function inkwell_docx_answer_lines($count = 2) {
  $out = '';
  for ($i = 0; $i < $count; $i++) {
    $out .= inkwell_docx_p(str_repeat('_', 90), null, 240);
  }
  return $out;
}

/**
 * Builds the full word/document.xml body for one exam.
 * $category: row from inkwell_get_teacher_category() / inkwell_get_admin exam.
 * $questions: rows from inkwell_get_teacher_questions().
 */
function inkwell_build_exam_document_xml($category, $questions) {
  $title = strtoupper(trim($category['title']));
  $subject = trim($category['subject_title'] ?? '');

  $body = '';
  $body .= inkwell_docx_p($title, 'center', 60, true, 28);
  if ($subject !== '') {
    $body .= inkwell_docx_p($subject, 'center', 300, false, 22);
  } else {
    $body .= inkwell_docx_p('', null, 200);
  }

  $body .= inkwell_docx_p([
    ['text' => 'NAME: _______________________________________     ', 'bold' => false],
    ['text' => 'GR. & SEC. __________     ', 'bold' => false],
    ['text' => 'SCORE: __________', 'bold' => false],
  ], null, 300);

  $body .= inkwell_docx_p([
    ['text' => 'Directions: ', 'bold' => true],
    ['text' => 'Read each item carefully and write the letter (or your answer) on the blank before each number.', 'bold' => false],
  ], null, 320);

  $n = 0;
  foreach ($questions as $q) {
    $n++;
    $points = (int) ($q['max_points'] ?? 1);
    $suffix = $q['qtype'] !== 'mcq' && $points > 1 ? ' (' . $points . ' points)' : '';
    $body .= inkwell_docx_p('______' . $n . '. ' . $q['question'] . $suffix, null, 100);

    if ($q['qtype'] === 'mcq') {
      $body .= inkwell_docx_options_table(
        $q['option_a'] ?? '', $q['option_b'] ?? '', $q['option_c'] ?? '', $q['option_d'] ?? ''
      );
      $body .= inkwell_docx_p('', null, 160);
    } elseif ($q['qtype'] === 'code') {
      $lang = $q['code_language'] ? ' (' . $q['code_language'] . ')' : '';
      $body .= inkwell_docx_p('Write your code' . $lang . ' below:', null, 100);
      $body .= inkwell_docx_answer_lines(6);
      $body .= inkwell_docx_p('', null, 120);
    } else { // essay
      $body .= inkwell_docx_answer_lines(3);
      $body .= inkwell_docx_p('', null, 120);
    }
  }

  // ---- Answer key page (teacher's copy) ----
  $body .= '<w:p><w:pPr><w:pageBreakBefore/></w:pPr></w:p>';
  $body .= inkwell_docx_p('ANSWER KEY — TEACHER COPY', 'center', 240, true, 26);
  $body .= inkwell_docx_p($title, 'center', 300, false, 20);

  $letters = ['A', 'B', 'C', 'D'];
  $n = 0;
  foreach ($questions as $q) {
    $n++;
    if ($q['qtype'] === 'mcq') {
      $idx = (int) ($q['correct_index'] ?? 0);
      $ans = $letters[$idx] ?? '?';
      $body .= inkwell_docx_p($n . '. ' . $ans, null, 100);
    } else {
      $body .= inkwell_docx_p($n . '. (manually graded — ' . ucfirst($q['qtype']) . ')', null, 100);
    }
  }

  return $body;
}

const INKWELL_DOCX_HEADER = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
  . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
  . '<w:body>';

const INKWELL_DOCX_SECTPR = '<w:sectPr><w:pgSz w:w="12240" w:h="15840"/>'
  . '<w:pgMar w:top="1440" w:right="1440" w:bottom="1440" w:left="1440" w:header="720" w:footer="720" w:gutter="0"/>'
  . '</w:sectPr>';

const INKWELL_DOCX_FOOTER = '</w:body></w:document>';

const INKWELL_DOCX_CONTENT_TYPES = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
  . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
  . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
  . '<Default Extension="xml" ContentType="application/xml"/>'
  . '<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
  . '<Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>'
  . '</Types>';

const INKWELL_DOCX_RELS = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
  . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
  . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
  . '</Relationships>';

const INKWELL_DOCX_DOC_RELS = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
  . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
  . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
  . '</Relationships>';

const INKWELL_DOCX_STYLES = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
  . '<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
  . '<w:docDefaults><w:rPrDefault><w:rPr><w:rFonts w:ascii="Times New Roman" w:hAnsi="Times New Roman"/><w:sz w:val="24"/></w:rPr></w:rPrDefault></w:docDefaults>'
  . '<w:style w:type="paragraph" w:default="1" w:styleId="Normal"><w:name w:val="Normal"/></w:style>'
  . '</w:styles>';

/**
 * Builds the word/document.xml body for one student's exam RESULT (used by
 * results.php's "Download as Word" button) — question by question, the
 * student's answer next to the correct answer for mcq, or the teacher's
 * points/feedback for code/essay, plus a score summary up top.
 */
function inkwell_build_attempt_result_document_xml($attempt, $answers) {
  $codeLangs = ['javascript' => 'JavaScript', 'python' => 'Python', 'php' => 'PHP', 'java' => 'Java', 'csharp' => 'C#', 'cpp' => 'C++', 'html' => 'HTML/CSS', 'sql' => 'SQL'];

  $body = '';
  $body .= inkwell_docx_p('INKWELL — EXAM RESULT', 'center', 60, true, 28);
  $body .= inkwell_docx_p(strtoupper(trim($attempt['exam_title'])), 'center', 300, false, 22);

  $meta = 'Student: ' . $attempt['student_name'];
  if (!empty($attempt['teacher_id'])) {
    $meta .= '   ·   Teacher: ' . ($attempt['teacher_name'] ?? ('#' . $attempt['teacher_id']));
  }
  $body .= inkwell_docx_p($meta, null, 60);
  $body .= inkwell_docx_p('Submitted: ' . date('F j, Y g:i A', strtotime($attempt['submitted_at'])), null, 160);

  if ($attempt['status'] === 'graded') {
    $finalPoints = (int) $attempt['auto_points'] + (int) $attempt['manual_points'];
    $body .= inkwell_docx_p([
      ['text' => 'SCORE: ', 'bold' => true],
      ['text' => $finalPoints . ' / ' . (int) $attempt['total_points'] . ' points (' . (int) $attempt['percent'] . '%) — ' . ($attempt['passed'] ? 'PASSED' : 'NOT PASSED'), 'bold' => false],
    ], null, 100);
    $body .= inkwell_docx_p('Pass score required: ' . (int) $attempt['pass_score'] . '%', null, 260);
  } else {
    $body .= inkwell_docx_p('SCORE: Not finalized yet — this exam has questions still awaiting teacher grading.', null, 260);
  }

  $n = 0;
  foreach ($answers as $ans) {
    $n++;
    $body .= inkwell_docx_p($n . '. ' . $ans['question'], null, 80, true);

    if ($ans['qtype'] === 'mcq') {
      $opts = [$ans['option_a'], $ans['option_b'], $ans['option_c'], $ans['option_d']];
      $letters = ['A', 'B', 'C', 'D'];
      foreach ($opts as $i => $opt) {
        if ($opt === null || $opt === '') continue;
        $isCorrect = (int) $ans['correct_index'] === $i;
        $isPicked = $ans['selected_index'] !== null && (int) $ans['selected_index'] === $i;
        $suffix = '';
        if ($isCorrect) $suffix .= ' — correct answer';
        if ($isPicked) $suffix .= $isCorrect ? ' (your answer)' : ' — your answer';
        $body .= inkwell_docx_p('   ' . $letters[$i] . ') ' . $opt . $suffix, null, 60, $isCorrect || $isPicked);
      }
      $result = ($ans['is_correct'] ? 'Correct' : 'Incorrect') . ' (' . (int) $ans['points_awarded'] . '/' . (int) $ans['max_points'] . ' pts)';
      $body .= inkwell_docx_p('Result: ' . $result, null, 200);
    } else {
      $label = $ans['qtype'] === 'code' ? 'Code answer (' . ($codeLangs[$ans['code_language']] ?? $ans['code_language']) . '):' : 'Essay answer:';
      $body .= inkwell_docx_p($label, null, 60);
      $answerText = trim((string) $ans['text_answer']) ?: '(no answer submitted)';
      $body .= inkwell_docx_p($answerText, null, 100, false, 22);
      if ($ans['points_awarded'] !== null) {
        $body .= inkwell_docx_p('Score: ' . (int) $ans['points_awarded'] . '/' . (int) $ans['max_points'] . ' pts', null, 60);
      }
      if (!empty($ans['feedback'])) {
        $body .= inkwell_docx_p([
          ['text' => 'Teacher feedback: ', 'bold' => true],
          ['text' => $ans['feedback'], 'bold' => false],
        ], null, 200);
      } else {
        $body .= inkwell_docx_p('', null, 140);
      }
    }
  }

  return $body;
}

/**
 * Streams one exam attempt's result as a .docx download and exits. Call
 * only after confirming the requesting user is allowed to see this attempt
 * (owner student or the grading teacher — same rule as the text export it
 * replaces).
 */
function inkwell_stream_attempt_result_docx($attempt, $answers) {
  $bodyXml = inkwell_build_attempt_result_document_xml($attempt, $answers);
  $documentXml = INKWELL_DOCX_HEADER . $bodyXml . INKWELL_DOCX_SECTPR . INKWELL_DOCX_FOOTER;

  $tmpPath = tempnam(sys_get_temp_dir(), 'result') . '.docx';
  $zip = new ZipArchive();
  $zip->open($tmpPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
  $zip->addEmptyDir('_rels');
  $zip->addEmptyDir('word');
  $zip->addEmptyDir('word/_rels');
  $zip->addFromString('[Content_Types].xml', INKWELL_DOCX_CONTENT_TYPES);
  $zip->addFromString('_rels/.rels', INKWELL_DOCX_RELS);
  $zip->addFromString('word/document.xml', $documentXml);
  $zip->addFromString('word/_rels/document.xml.rels', INKWELL_DOCX_DOC_RELS);
  $zip->addFromString('word/styles.xml', INKWELL_DOCX_STYLES);
  $zip->close();

  $safeName = preg_replace('/[^A-Za-z0-9 _-]/', '', $attempt['exam_title']);
  $safeName = trim($safeName) !== '' ? trim($safeName) : 'exam';
  $fileName = 'inkwell-result-' . str_replace(' ', '-', strtolower($safeName)) . '-' . (int) $attempt['id'];

  header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
  header('Content-Disposition: attachment; filename="' . $fileName . '.docx"');
  header('Content-Length: ' . filesize($tmpPath));
  readfile($tmpPath);
  unlink($tmpPath);
  exit;
}

/**
 * Streams the exam as a .docx download and exits. Call only after
 * confirming the requesting user is allowed to see this exam.
 */
function inkwell_stream_exam_docx($category, $questions) {
  $bodyXml = inkwell_build_exam_document_xml($category, $questions);
  $documentXml = INKWELL_DOCX_HEADER . $bodyXml . INKWELL_DOCX_SECTPR . INKWELL_DOCX_FOOTER;

  $tmpPath = tempnam(sys_get_temp_dir(), 'exam') . '.docx';
  $zip = new ZipArchive();
  $zip->open($tmpPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
  $zip->addEmptyDir('_rels');
  $zip->addEmptyDir('word');
  $zip->addEmptyDir('word/_rels');
  $zip->addFromString('[Content_Types].xml', INKWELL_DOCX_CONTENT_TYPES);
  $zip->addFromString('_rels/.rels', INKWELL_DOCX_RELS);
  $zip->addFromString('word/document.xml', $documentXml);
  $zip->addFromString('word/_rels/document.xml.rels', INKWELL_DOCX_DOC_RELS);
  $zip->addFromString('word/styles.xml', INKWELL_DOCX_STYLES);
  $zip->close();

  $fileName = preg_replace('/[^A-Za-z0-9 _-]/', '', $category['title']);
  $fileName = trim($fileName) !== '' ? trim($fileName) : 'exam';

  header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
  header('Content-Disposition: attachment; filename="' . $fileName . '.docx"');
  header('Content-Length: ' . filesize($tmpPath));
  readfile($tmpPath);
  unlink($tmpPath);
  exit;
}
