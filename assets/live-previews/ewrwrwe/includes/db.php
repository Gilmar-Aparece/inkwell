<?php
/**
 * MySQL connection for accounts, teacher-authored exams, and certificates
 * issued through them. Fill in your host's DB credentials below — on
 * InfinityFree these come from the "MySQL Databases" section of the
 * client area (host is usually sqlXXX.infinityfree.com, not "localhost").
 *
 * Run includes/schema.sql once (via phpMyAdmin -> Import) before using
 * register.php / login.php / the teacher dashboard.
 */

/**
 * Without this, PHP defaults to UTC (or whatever the host's php.ini says)
 * while InfinityFree's MySQL server clock is often set to a different
 * zone entirely — the two clocks disagree, and anything comparing a
 * MySQL-generated timestamp (e.g. `created_at DEFAULT current_timestamp()`)
 * against PHP's time() shows the wrong "time ago" (e.g. a brand new post
 * reading "3h ago"). Pinning PHP's zone here fixes every date()/time()/
 * strtotime() call app-wide; the posts feed additionally writes its own
 * created_at from PHP (see includes/posts.php) so it never depends on the
 * MySQL server's clock at all.
 */
date_default_timezone_set('Asia/Manila');

/**
 * InfinityFree's free tier doesn't give you access to the PHP error log,
 * so an uncaught error/exception just shows up as a blank "500 Internal
 * Server Error" with zero detail. This handler catches anything that
 * would otherwise crash silently and prints the real message instead —
 * e.g. "Unknown column 'id_number'" if a migration hasn't been imported
 * yet. Safe to leave on for a small self-hosted project like this one.
 */
if (!defined('INKWELL_ERROR_HANDLER_SET')) {
  define('INKWELL_ERROR_HANDLER_SET', true);

  function inkwell_render_fatal($message) {
    if (!headers_sent()) http_response_code(500);
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Something broke</title>'
       . '<style>body{font-family:system-ui,sans-serif;background:#111;color:#eee;padding:40px;max-width:700px;margin:0 auto;}'
       . 'code{background:#222;padding:2px 6px;border-radius:4px;color:#ffb4b4;}'
       . 'h1{font-size:1.3rem;} .hint{color:#9aa;font-size:0.92rem;margin-top:16px;line-height:1.6;}</style></head><body>'
       . '<h1>Something broke on this page</h1><p><code>' . htmlspecialchars($message) . '</code></p>'
       . '<p class="hint">If the message above mentions an unknown column (like <code>id_number</code> or <code>course</code>), '
       . 'the fix is to re-import <code>includes/schema.sql</code> in phpMyAdmin — specifically the <code>ALTER TABLE</code> '
       . 'lines near the bottom, under "MIGRATION". That adds the missing column(s) without touching existing data.</p>'
       . '</body></html>';
  }

  set_exception_handler(function ($e) {
    inkwell_render_fatal(get_class($e) . ': ' . $e->getMessage());
  });

  set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) return false; // respects @-suppressed errors
    if (in_array($severity, [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
      inkwell_render_fatal($message . ' in ' . $file . ' on line ' . $line);
      exit;
    }
    return false; // let PHP handle warnings/notices normally (non-fatal)
  });
}

define('INKWELL_DB_HOST', 'sql303.infinityfree.com');
define('INKWELL_DB_NAME', 'if0_41146249_wp381');
define('INKWELL_DB_USER', 'if0_41146249');
define('INKWELL_DB_PASS', 'QBdX1ozjjyicfd');

/**
 * Canonical list of student year levels (Philippine-style), used by both
 * the Enrollment Portal (per-student, set when they enroll) and Sections
 * (per-section, set by the teacher/adviser). Kept in one place so every
 * dropdown and every "group by year" view stays in sync.
 */
function inkwell_year_levels() {
  return ['1st Year', '2nd Year', '3rd Year', '4th Year'];
}

function inkwell_db() {
  static $pdo = null;
  if ($pdo !== null) return $pdo;
  try {
    $pdo = new PDO(
      'mysql:host=' . INKWELL_DB_HOST . ';dbname=' . INKWELL_DB_NAME . ';charset=utf8mb4',
      INKWELL_DB_USER,
      INKWELL_DB_PASS,
      [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
      ]
    );
    return $pdo;
  } catch (PDOException $e) {
    http_response_code(500);
    die('Database connection failed. Double-check includes/db.php credentials and that includes/schema.sql has been imported. (' . htmlspecialchars($e->getMessage()) . ')');
  }
}
