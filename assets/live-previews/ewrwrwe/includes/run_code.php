<?php
/**
 * "Any Language" playground backend.
 *
 * The browser can only truly *execute* HTML/CSS/JS on its own (that's what
 * the iframe preview in playground.php does). Everything else — Python,
 * PHP, C, C++, Java, Go, Rust, Ruby, etc. — needs a real interpreter/
 * compiler somewhere, which a static PHP host doesn't have installed.
 *
 * Wandbox (https://wandbox.org) runs a free public "compile & run" service
 * covering 30+ languages, but it doesn't send CORS headers, so the browser
 * can't call it directly from playground.php — every request has to be
 * relayed through our own server. That's all this file does:
 *
 *   GET  run_code.php?action=languages   -> list of languages/compilers
 *   POST run_code.php  {action:"run", compiler, code, stdin}
 *
 * Nothing here needs a database or login; it's the same "no setup required"
 * philosophy as the rest of Inkwell (see includes/store.php).
 */

require_once __DIR__ . '/store.php';

header('Content-Type: application/json; charset=utf-8');

const WANDBOX_LIST_URL    = 'https://wandbox.org/api/list.json';
const WANDBOX_COMPILE_URL = 'https://wandbox.org/api/compile.json';
const RUNTIMES_CACHE_FILE = 'runtimes_cache.json';
const RUNTIMES_CACHE_TTL  = 21600; // 6 hours — the compiler list barely changes

function inkwell_run_json_error($message, $code = 400) {
  http_response_code($code);
  echo json_encode(['ok' => false, 'error' => $message]);
  exit;
}

/**
 * Tiny cURL wrapper. Returns [decodedBody, errorMessageOrNull].
 */
function inkwell_run_http($url, $jsonBody = null) {
  if (!function_exists('curl_init')) {
    return [null, 'The PHP curl extension is not enabled on this server.'];
  }
  $ch = curl_init($url);
  $opts = [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 20,
    CURLOPT_CONNECTTIMEOUT => 8,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_USERAGENT => 'Inkwell-Playground/1.0',
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

$action = $_GET['action'] ?? $_POST['action'] ?? '';

/* ------------------------------------------------------------------ */
/* GET action=languages — grouped list of {language: [compilers]}      */
/* ------------------------------------------------------------------ */
if ($action === 'languages') {
  $cache = inkwell_read_json(RUNTIMES_CACHE_FILE, null);
  $isFresh = is_array($cache) && isset($cache['fetched_at'], $cache['languages'])
    && (time() - $cache['fetched_at']) < RUNTIMES_CACHE_TTL;

  if ($isFresh) {
    echo json_encode(['ok' => true, 'languages' => $cache['languages']]);
    exit;
  }

  [$list, $err] = inkwell_run_http(WANDBOX_LIST_URL);

  if (!is_array($list)) {
    // Serve a stale cache rather than failing outright if we have one.
    if (is_array($cache) && !empty($cache['languages'])) {
      echo json_encode(['ok' => true, 'languages' => $cache['languages'], 'stale' => true]);
      exit;
    }
    inkwell_run_json_error($err ?: 'Could not load the list of languages.', 502);
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

  // Put stable, numbered compilers first and "-head" nightly builds last —
  // head builds are the most likely to be broken or unavailable on any
  // given day, so they shouldn't be what a language defaults to.
  foreach ($byLanguage as $lang => &$compilers) {
    usort($compilers, function ($a, $b) {
      $aHead = (stripos($a['name'], 'head') !== false) ? 1 : 0;
      $bHead = (stripos($b['name'], 'head') !== false) ? 1 : 0;
      if ($aHead !== $bHead) return $aHead <=> $bHead;
      return version_compare((string) $b['version'] ?: '0', (string) $a['version'] ?: '0');
    });
  }
  unset($compilers);

  inkwell_write_json(RUNTIMES_CACHE_FILE, ['fetched_at' => time(), 'languages' => $byLanguage]);
  echo json_encode(['ok' => true, 'languages' => $byLanguage]);
  exit;
}

/* ------------------------------------------------------------------ */
/* POST action=run — compile/run one submission                       */
/* ------------------------------------------------------------------ */
if ($action === 'run') {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    inkwell_run_json_error('Use POST to run code.', 405);
  }

  $raw = file_get_contents('php://input');
  $payload = json_decode($raw, true);
  if (!is_array($payload)) $payload = $_POST;

  $compiler = trim((string) ($payload['compiler'] ?? ''));
  $code     = (string) ($payload['code'] ?? '');
  $stdin    = (string) ($payload['stdin'] ?? '');

  if ($compiler === '' || trim($code) === '') {
    inkwell_run_json_error('Pick a language and write some code first.');
  }
  if (strlen($code) > 60000) {
    inkwell_run_json_error('That code is too long to run here (60,000 character limit).');
  }
  if (strlen($stdin) > 20000) {
    inkwell_run_json_error('That stdin input is too long (20,000 character limit).');
  }

  $body = json_encode([
    'code'     => $code,
    'compiler' => $compiler,
    'stdin'    => $stdin,
    'save'     => false,
  ]);

  [$result, $err] = inkwell_run_http(WANDBOX_COMPILE_URL, $body);

  if (!is_array($result)) {
    inkwell_run_json_error($err ?: 'The run service did not respond. Try again in a moment.', 502);
  }
  if (isset($result['error'])) {
    inkwell_run_json_error(is_string($result['error']) ? $result['error'] : 'That language/version is unavailable right now.');
  }

  echo json_encode([
    'ok'               => true,
    'status'           => $result['status'] ?? null,
    'signal'           => $result['signal'] ?? '',
    'compiler_output'  => $result['compiler_output'] ?? '',
    'compiler_error'   => $result['compiler_error'] ?? '',
    'compiler_message' => $result['compiler_message'] ?? '',
    'program_output'   => $result['program_output'] ?? '',
    'program_error'    => $result['program_error'] ?? '',
    'program_message'  => $result['program_message'] ?? '',
  ]);
  exit;
}

inkwell_run_json_error('Unknown action.', 404);
