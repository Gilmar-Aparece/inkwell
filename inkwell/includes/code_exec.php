<?php
/**
 * Output-matching auto-grader for "code" exam questions.
 *
 * A teacher can tick "Auto-grade by matching output" on a code question and
 * paste in a sample/expected output. When a student submits that question,
 * we run *their* code through Wandbox (the same free compile-and-run
 * service playground.php and run_code.php already use) and compare what it
 * printed to the teacher's expected output. The student's code doesn't have
 * to match the teacher's approach at all — different variable names,
 * different algorithm, whatever — only the printed output has to match, so
 * this is intentionally separate from includes/run_code.php (which is the
 * AJAX endpoint for the interactive playground) even though both talk to
 * the same Wandbox API. Keeping this file self-contained (its own
 * inkwell_ce_* http/cache helpers) means it can safely be required from
 * includes/exams_db.php without any risk of clashing with run_code.php if
 * both ever happened to load in the same request.
 */

require_once __DIR__ . '/store.php';

const WANDBOX_LIST_URL_CE    = 'https://wandbox.org/api/list.json';
const WANDBOX_COMPILE_URL_CE = 'https://wandbox.org/api/compile.json';
const RUNTIMES_CACHE_FILE_CE = 'runtimes_cache.json'; // same cache file run_code.php writes — no need to fetch twice
const RUNTIMES_CACHE_TTL_CE  = 21600; // 6 hours

/** Which of Inkwell's code_language values Wandbox can actually run, and what its "language" field is called there. HTML/CSS is browser-only (see run_code.php), so it's deliberately excluded — it has no auto-grade option. */
const INKWELL_CODE_LANG_WANDBOX = [
  'javascript' => ['JavaScript'],
  'python'     => ['Python'],
  'php'        => ['PHP'],
  'java'       => ['Java'],
  'csharp'     => ['C#', 'CSharp'],
  'cpp'        => ['C++', 'CPP'],
  'sql'        => ['SQLite', 'SQL'],
];

function inkwell_code_lang_supports_autograde($langKey) {
  return isset(INKWELL_CODE_LANG_WANDBOX[$langKey]);
}

/** Tiny cURL wrapper, mirrors inkwell_run_http() in run_code.php. Returns [decodedBody, errorMessageOrNull]. */
function inkwell_ce_http($url, $jsonBody = null) {
  if (!function_exists('curl_init')) {
    return [null, 'The PHP curl extension is not enabled on this server.'];
  }
  $ch = curl_init($url);
  $opts = [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 20,
    CURLOPT_CONNECTTIMEOUT => 8,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_USERAGENT => 'Inkwell-AutoGrader/1.0',
  ];
  if ($jsonBody !== null) {
    $opts[CURLOPT_POST] = true;
    $opts[CURLOPT_POSTFIELDS] = $jsonBody;
    $opts[CURLOPT_HTTPHEADER] = ['Content-Type: application/json', 'Accept: application/json'];
  }
  curl_setopt_array($ch, $opts);
  $body = curl_exec($ch);
  $curlErr = curl_error($ch);
  curl_close($ch);

  if ($body === false) {
    return [null, $curlErr ?: 'Could not reach the run service.'];
  }
  $decoded = json_decode($body, true);
  if ($decoded === null && trim($body) !== '') {
    return [null, 'The run service returned an unexpected response.'];
  }
  return [$decoded, null];
}

/** Grouped {language: [compilers]} list, from cache when fresh, otherwise fetched from Wandbox. Returns null if neither is available. */
function inkwell_ce_wandbox_languages() {
  $cache = inkwell_read_json(RUNTIMES_CACHE_FILE_CE, null);
  $isFresh = is_array($cache) && isset($cache['fetched_at'], $cache['languages'])
    && (time() - $cache['fetched_at']) < RUNTIMES_CACHE_TTL_CE;
  if ($isFresh) return $cache['languages'];

  [$list, $err] = inkwell_ce_http(WANDBOX_LIST_URL_CE);
  if (!is_array($list)) {
    // Stale cache beats no cache.
    return (is_array($cache) && !empty($cache['languages'])) ? $cache['languages'] : null;
  }

  $byLanguage = [];
  foreach ($list as $entry) {
    if (empty($entry['name']) || empty($entry['language'])) continue;
    $lang = $entry['language'];
    $byLanguage[$lang][] = [
      'name'    => $entry['name'],
      'version' => $entry['version'] ?? '',
      'label'   => $entry['display-name'] ?? $entry['name'],
    ];
  }
  ksort($byLanguage, SORT_NATURAL | SORT_FLAG_CASE);
  foreach ($byLanguage as $lang => &$compilers) {
    usort($compilers, function ($a, $b) {
      $aHead = (stripos($a['name'], 'head') !== false) ? 1 : 0;
      $bHead = (stripos($b['name'], 'head') !== false) ? 1 : 0;
      if ($aHead !== $bHead) return $aHead <=> $bHead;
      return version_compare((string) $b['version'] ?: '0', (string) $a['version'] ?: '0');
    });
  }
  unset($compilers);

  inkwell_write_json(RUNTIMES_CACHE_FILE_CE, ['fetched_at' => time(), 'languages' => $byLanguage]);
  return $byLanguage;
}

function inkwell_ce_pick_compiler($languages, $candidateNames) {
  foreach ($candidateNames as $want) {
    foreach ($languages as $langName => $compilers) {
      if (strcasecmp($langName, $want) === 0 && !empty($compilers)) return $compilers[0]['name'];
    }
  }
  foreach ($candidateNames as $want) {
    foreach ($languages as $langName => $compilers) {
      if (stripos($langName, $want) !== false && !empty($compilers)) return $compilers[0]['name'];
    }
  }
  return null;
}

/**
 * Runs $code through Wandbox for the given Inkwell language key.
 * Returns ['ok'=>bool, 'output'=>string, 'compile_error'=>string|null, 'error'=>string|null].
 * ok=false means the *service* failed (no internet, Wandbox down, no
 * compiler available) — not that the student's code was wrong. Callers
 * should fall back to manual grading in that case rather than marking the
 * answer incorrect.
 */
function inkwell_run_student_code($langKey, $code, $stdin = '') {
  $candidates = INKWELL_CODE_LANG_WANDBOX[$langKey] ?? null;
  if (!$candidates) {
    return ['ok' => false, 'output' => '', 'compile_error' => null, 'error' => 'Auto-grading by output is not available for this language.'];
  }
  if (strlen((string) $code) > 60000) {
    return ['ok' => false, 'output' => '', 'compile_error' => null, 'error' => 'The submitted code is too long to auto-run.'];
  }

  $languages = inkwell_ce_wandbox_languages();
  if (!$languages) {
    return ['ok' => false, 'output' => '', 'compile_error' => null, 'error' => 'Could not reach the code-run service.'];
  }
  $compiler = inkwell_ce_pick_compiler($languages, $candidates);
  if (!$compiler) {
    return ['ok' => false, 'output' => '', 'compile_error' => null, 'error' => 'No compiler is available for this language right now.'];
  }

  $body = json_encode([
    'code' => (string) $code,
    'compiler' => $compiler,
    'stdin' => (string) $stdin,
    'save' => false,
  ]);
  [$result, $err] = inkwell_ce_http(WANDBOX_COMPILE_URL_CE, $body);
  if (!is_array($result)) {
    return ['ok' => false, 'output' => '', 'compile_error' => null, 'error' => $err ?: 'The run service did not respond.'];
  }
  if (isset($result['error'])) {
    return ['ok' => false, 'output' => '', 'compile_error' => null, 'error' => is_string($result['error']) ? $result['error'] : 'That language is unavailable right now.'];
  }

  return [
    'ok' => true,
    'output' => (string) ($result['program_output'] ?? ''),
    'compile_error' => trim((string) ($result['compiler_error'] ?? '')) !== '' ? (string) $result['compiler_error'] : null,
    'error' => null,
  ];
}

/** Line-by-line trim + normalized newlines, so trailing whitespace/blank-line differences never fail a match — only the actual printed content does. */
function inkwell_normalize_program_output($s) {
  $s = str_replace(["\r\n", "\r"], "\n", (string) $s);
  $lines = array_map('rtrim', explode("\n", $s));
  return trim(implode("\n", $lines));
}

/**
 * Runs the student's code and compares it to the teacher's expected output.
 * Returns ['ok'=>bool, 'match'=>bool, 'output'=>string, 'error'=>string|null].
 * ok=false (service unavailable) should be treated as "needs manual grading",
 * never as "incorrect".
 */
function inkwell_check_code_output($langKey, $code, $expectedOutput, $stdin = '') {
  $run = inkwell_run_student_code($langKey, $code, $stdin);
  if (!$run['ok']) {
    return ['ok' => false, 'match' => false, 'output' => '', 'error' => $run['error']];
  }
  $match = inkwell_normalize_program_output($run['output']) === inkwell_normalize_program_output($expectedOutput);
  return ['ok' => true, 'match' => $match, 'output' => $run['output'], 'error' => $run['compile_error']];
}
