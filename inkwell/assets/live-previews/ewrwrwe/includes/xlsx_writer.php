<?php
/**
 * Generates a real .xlsx (Excel) file — no external libraries. An xlsx is
 * just a zip of XML parts, built here by hand with ZipArchive, the same
 * approach includes/exam_docx.php uses for Word exports.
 *
 * Usage:
 *   inkwell_xlsx_download([
 *     ['name' => 'Sheet 1', 'rows' => [['Header A', 'Header B'], ['x', 1]]],
 *     ['name' => 'Sheet 2', 'rows' => [...]],
 *   ], 'my-file.xlsx');
 *
 * Cell values: strings are written as inline strings; int/float values are
 * written as real numbers (so Excel can sum/average a scores column).
 * Sends headers and streams the file straight to the browser.
 */

function inkwell_xlsx_esc($s) {
  return htmlspecialchars((string) $s, ENT_QUOTES | ENT_XML1, 'UTF-8');
}

/** Converts a 0-based column index to a spreadsheet column letter (0 -> A, 26 -> AA). */
function inkwell_xlsx_col_letter($index) {
  $letter = '';
  $index++;
  while ($index > 0) {
    $rem = ($index - 1) % 26;
    $letter = chr(65 + $rem) . $letter;
    $index = intdiv($index - 1, 26);
  }
  return $letter;
}

/** Builds the <sheetData> XML for one sheet's rows. */
function inkwell_xlsx_sheet_xml($rows) {
  $out = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
    . '<sheetData>';
  foreach ($rows as $rIdx => $row) {
    $rowNum = $rIdx + 1;
    $out .= '<row r="' . $rowNum . '">';
    foreach ($row as $cIdx => $val) {
      $ref = inkwell_xlsx_col_letter($cIdx) . $rowNum;
      if (is_int($val) || is_float($val)) {
        $out .= '<c r="' . $ref . '"><v>' . $val . '</v></c>';
      } elseif ($val === null || $val === '') {
        $out .= '<c r="' . $ref . '" t="inlineStr"><is><t xml:space="preserve"></t></is></c>';
      } else {
        $out .= '<c r="' . $ref . '" t="inlineStr"><is><t xml:space="preserve">' . inkwell_xlsx_esc($val) . '</t></is></c>';
      }
    }
    $out .= '</row>';
  }
  $out .= '</sheetData></worksheet>';
  return $out;
}

/** Excel sheet names: <=31 chars, no : \ / ? * [ ]. */
function inkwell_xlsx_safe_sheet_name($name, $fallbackIndex) {
  $name = preg_replace('/[:\\\\\/\?\*\[\]]/', ' ', (string) $name);
  $name = trim($name);
  if ($name === '') $name = 'Sheet' . $fallbackIndex;
  return function_exists('mb_substr') ? mb_substr($name, 0, 31) : substr($name, 0, 31);
}

/**
 * Builds the xlsx and streams it to the browser with download headers.
 * $sheets: array of ['name' => string, 'rows' => array of arrays].
 */
function inkwell_xlsx_download($sheets, $filename) {
  if (empty($sheets)) $sheets = [['name' => 'Sheet1', 'rows' => [['No data']]]];

  $tmpPath = tempnam(sys_get_temp_dir(), 'ikxlsx');
  $zip = new ZipArchive();
  $zip->open($tmpPath, ZipArchive::OVERWRITE);

  $zip->addFromString('[Content_Types].xml',
    '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
    . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
    . '<Default Extension="xml" ContentType="application/xml"/>'
    . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
    . implode('', array_map(function ($i) {
        return '<Override PartName="/xl/worksheets/sheet' . $i . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
      }, range(1, count($sheets))))
    . '</Types>'
  );

  $zip->addFromString('_rels/.rels',
    '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
    . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
    . '</Relationships>'
  );

  $sheetEntries = '';
  $relEntries = '';
  $i = 0;
  foreach ($sheets as $sheet) {
    $i++;
    $safeName = inkwell_xlsx_safe_sheet_name($sheet['name'] ?? '', $i);
    $sheetEntries .= '<sheet name="' . inkwell_xlsx_esc($safeName) . '" sheetId="' . $i . '" r:id="rId' . $i . '"/>';
    $relEntries .= '<Relationship Id="rId' . $i . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $i . '.xml"/>';
    $zip->addFromString('xl/worksheets/sheet' . $i . '.xml', inkwell_xlsx_sheet_xml($sheet['rows'] ?? []));
  }

  $zip->addFromString('xl/workbook.xml',
    '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
    . '<sheets>' . $sheetEntries . '</sheets>'
    . '</workbook>'
  );

  $zip->addFromString('xl/_rels/workbook.xml.rels',
    '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
    . $relEntries
    . '</Relationships>'
  );

  $zip->close();

  header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
  header('Content-Disposition: attachment; filename="' . $filename . '"');
  header('Content-Length: ' . filesize($tmpPath));
  readfile($tmpPath);
  unlink($tmpPath);
}
