<?php
/**
 * File-based storage for certificates, site config, and admin auth.
 * No database — everything lives under data-store/ as JSON, which is
 * blocked from direct web access by data-store/.htaccess. This keeps
 * Inkwell deployable to plain shared PHP hosting with zero setup, the
 * same philosophy as data/lessons.php.
 *
 * data-store/ must be writable by the web server (on InfinityFree this
 * is the default for folders you upload).
 */

define('INKWELL_DATA_DIR', __DIR__ . '/../data-store');
define('INKWELL_UPLOADS_DIR', __DIR__ . '/../assets/uploads');

function inkwell_store_path($filename) {
  return INKWELL_DATA_DIR . '/' . $filename;
}

/** True when the current request was made via fetch()/XHR from our own JS, not a normal browser navigation. */
function inkwell_is_ajax() {
  return strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';
}

/** Sends a JSON response and stops execution — used by AJAX branches instead of header('Location:'). */
function inkwell_json_response(array $data) {
  header('Content-Type: application/json');
  echo json_encode($data);
  exit;
}

/* ---------------- Upload-size guard ----------------
 * PHP enforces `post_max_size` / `upload_max_filesize` from php.ini
 * *before* our code ever runs. When a request goes over those limits,
 * PHP silently empties $_POST and $_FILES — there's no PHP error to
 * catch, the request just looks like an empty submit. On shared hosts
 * (InfinityFree and similar) the default post_max_size is often only
 * 8MB, well under the 25MB video / 2MB image limits the app advertises,
 * so without this guard a large upload just fails with no explanation.
 * inkwell_ini_bytes() parses ini shorthand ("8M", "2G") into a byte
 * count; inkwell_post_too_large() detects the silent-drop case by
 * comparing the raw Content-Length header (still set by the browser)
 * against the configured limit.
 */
function inkwell_ini_bytes($iniValue) {
  $iniValue = trim((string) $iniValue);
  if ($iniValue === '' || $iniValue === '0') return 0;
  $unit = strtolower(substr($iniValue, -1));
  $num = (float) $iniValue;
  switch ($unit) {
    case 'g': return (int) ($num * 1024 * 1024 * 1024);
    case 'm': return (int) ($num * 1024 * 1024);
    case 'k': return (int) ($num * 1024);
    default:  return (int) $num;
  }
}

/** The smaller of post_max_size / upload_max_filesize — the real ceiling for any single upload. */
function inkwell_effective_upload_limit_bytes() {
  $post = inkwell_ini_bytes(ini_get('post_max_size'));
  $upload = inkwell_ini_bytes(ini_get('upload_max_filesize'));
  if ($post <= 0) return $upload;
  if ($upload <= 0) return $post;
  return min($post, $upload);
}

function inkwell_format_bytes($bytes) {
  if ($bytes >= 1024 * 1024 * 1024) return round($bytes / (1024 * 1024 * 1024), 1) . 'GB';
  if ($bytes >= 1024 * 1024) return round($bytes / (1024 * 1024)) . 'MB';
  if ($bytes >= 1024) return round($bytes / 1024) . 'KB';
  return $bytes . 'B';
}

/**
 * True when this POST request arrived with a body larger than the
 * server would accept, which means PHP already discarded $_POST/$_FILES
 * before our code ran. Detected via the Content-Length header (set by
 * the browser regardless of what PHP kept) compared to post_max_size.
 * Call this before trusting an "empty" $_POST on any upload endpoint.
 */
function inkwell_post_too_large() {
  if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') return false;
  $contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
  if ($contentLength <= 0) return false;
  $limit = inkwell_ini_bytes(ini_get('post_max_size'));
  if ($limit <= 0) return false;
  return $contentLength > $limit && empty($_POST) && empty($_FILES);
}

function inkwell_post_too_large_message() {
  $limit = inkwell_ini_bytes(ini_get('post_max_size'));
  $suffix = $limit > 0 ? ' This server currently accepts uploads up to ' . inkwell_format_bytes($limit) . ' per request.' : '';
  return 'That file is too large for this server to accept.' . $suffix;
}

function inkwell_read_json($filename, $default) {
  $path = inkwell_store_path($filename);
  if (!file_exists($path)) return $default;
  $raw = @file_get_contents($path);
  if ($raw === false || trim($raw) === '') return $default;
  $data = json_decode($raw, true);
  return $data === null ? $default : $data;
}

function inkwell_write_json($filename, $data) {
  if (!is_dir(INKWELL_DATA_DIR)) @mkdir(INKWELL_DATA_DIR, 0775, true);
  $path = inkwell_store_path($filename);
  $fp = fopen($path, 'c+');
  if (!$fp) return false;
  $ok = false;
  if (flock($fp, LOCK_EX)) {
    ftruncate($fp, 0);
    rewind($fp);
    $ok = fwrite($fp, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) !== false;
    fflush($fp);
    flock($fp, LOCK_UN);
  }
  fclose($fp);
  return $ok;
}

/* ---------------- Site config (signer name/title + signature image) ---------------- */

function inkwell_get_config() {
  $defaults = [
    'signer_name'  => 'Gilmar Aparece',
    'signer_title' => 'Founder, Inkwell',
    'signature_file' => null, // filename inside assets/uploads/, or null
    // How many lessons (in order) at the start of EVERY track are free to
    // open for anyone — guest or logged in. Anything past this needs an
    // active plan whose unlocks_all_lessons flag is on (Pro Learner by
    // default). Admin-editable on /admin/lessons.php.
    'free_lessons_per_track' => 3,
  ];
  return array_merge($defaults, inkwell_read_json('config.json', []));
}

function inkwell_save_config($config) {
  $current = inkwell_get_config();
  return inkwell_write_json('config.json', array_merge($current, $config));
}

/** How many lessons per track are free for everyone, per the admin setting above (never negative). */
function inkwell_free_lessons_per_track() {
  $n = (int) (inkwell_get_config()['free_lessons_per_track'] ?? 3);
  return max(0, $n);
}

/* ---------------- Certificates ---------------- */

function inkwell_get_certificates() {
  return inkwell_read_json('certificates.json', []);
}

function inkwell_find_certificate($id) {
  foreach (inkwell_get_certificates() as $cert) {
    if ($cert['id'] === $id) return $cert;
  }
  return null;
}

function inkwell_add_certificate($name, $catKey, $catLabel, $score, $total) {
  $certs = inkwell_get_certificates();
  $cert = [
    'id'        => bin2hex(random_bytes(8)),
    'name'      => $name,
    'category'  => $catKey,
    'label'     => $catLabel,
    'score'     => $score,
    'total'     => $total,
    'percent'   => $total > 0 ? round(($score / $total) * 100) : 0,
    'issued_at' => date('Y-m-d'),
  ];
  $certs[] = $cert;
  inkwell_write_json('certificates.json', $certs);
  return $cert;
}

/* ---------------- Admin auth ----------------
 * Admins are just another role in the `users` table now (see
 * includes/auth.php) — register one at /admin/register.php, get approved
 * by an existing admin at /admin/admins.php, log in at /admin/login.php.
 * Nothing admin-related is stored as JSON anymore.
 */

function inkwell_require_admin() {
  require_once __DIR__ . '/auth.php';
  $user = inkwell_current_user();
  if (!$user || $user['role'] !== 'admin' || $user['status'] !== 'active') {
    header('Location: /admin/login.php');
    exit;
  }
  return $user;
}
