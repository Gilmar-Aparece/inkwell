<?php
/**
 * Announcements ("events") posted by teachers or deans, shown on the
 * public /events.php feed. Originally just a title + body, newest first;
 * now also supports an optional link (e.g. "Take the exam →") so a
 * teacher/dean can point students straight at an exam or any other URL.
 */

require_once __DIR__ . '/db.php';

/**
 * Self-heals `link_url` / `link_label` onto `events` (same self-healing
 * pattern as inkwell_ensure_exam_schedule_columns in exams_db.php) — an
 * event can carry an optional call-to-action link without anyone having
 * to run MIGRATION_ADD_event_link.sql by hand first.
 */
function inkwell_ensure_event_link_columns() {
  static $checked = false;
  if ($checked) return;
  $checked = true;
  $pdo = inkwell_db();
  $columns = [
    'link_url' => "ALTER TABLE events ADD COLUMN link_url VARCHAR(500) DEFAULT NULL",
    'link_label' => "ALTER TABLE events ADD COLUMN link_label VARCHAR(100) DEFAULT NULL",
  ];
  try {
    $existing = $pdo->query('SHOW COLUMNS FROM events')->fetchAll(PDO::FETCH_COLUMN);
  } catch (PDOException $e) {
    return; // table itself missing — nothing we can fix here
  }
  foreach ($columns as $name => $sql) {
    if (in_array($name, $existing, true)) continue;
    try {
      $pdo->exec($sql);
    } catch (PDOException $e) {
      // ignore — another request likely added it concurrently, or no ALTER privilege
    }
  }
}

/**
 * Only http(s) links or same-site absolute paths ("/exam.php?...") are
 * allowed — blocks a "javascript:" or other unsafe scheme from being
 * stored and later rendered as an href. Returns null when unsafe/empty.
 */
function inkwell_sanitize_event_link($url) {
  $url = trim((string) $url);
  if ($url === '') return null;
  if ($url[0] === '/' && (strlen($url) === 1 || $url[1] !== '/')) return $url; // same-site absolute path
  if (preg_match('~^https?://~i', $url)) return $url;
  return null;
}

function inkwell_create_event($authorId, $authorRole, $title, $body, $linkUrl = null, $linkLabel = null) {
  $authorRole = in_array($authorRole, ['teacher', 'dean'], true) ? $authorRole : 'teacher';
  inkwell_ensure_event_link_columns();
  $linkUrl = inkwell_sanitize_event_link($linkUrl);
  $linkLabel = $linkUrl ? trim((string) $linkLabel) : null;
  if ($linkUrl && $linkLabel === '') $linkLabel = null;
  $pdo = inkwell_db();
  $stmt = $pdo->prepare('INSERT INTO events (author_id, author_role, title, body, link_url, link_label) VALUES (?, ?, ?, ?, ?, ?)');
  $stmt->execute([$authorId, $authorRole, $title, $body, $linkUrl, $linkLabel]);
  return (int) $pdo->lastInsertId();
}

/** Newest first, joined with the author's name for display. */
function inkwell_all_events($limit = 50) {
  inkwell_ensure_event_link_columns();
  $pdo = inkwell_db();
  $stmt = $pdo->prepare(
    "SELECT e.*, u.name AS author_name
     FROM events e
     JOIN users u ON u.id = e.author_id
     ORDER BY e.created_at DESC
     LIMIT " . (int) $limit
  );
  $stmt->execute();
  return $stmt->fetchAll();
}

function inkwell_events_by_author($authorId) {
  inkwell_ensure_event_link_columns();
  $pdo = inkwell_db();
  $stmt = $pdo->prepare('SELECT * FROM events WHERE author_id = ? ORDER BY created_at DESC');
  $stmt->execute([$authorId]);
  return $stmt->fetchAll();
}

function inkwell_get_event($id) {
  $pdo = inkwell_db();
  $stmt = $pdo->prepare('SELECT * FROM events WHERE id = ?');
  $stmt->execute([$id]);
  return $stmt->fetch() ?: null;
}

/** Events posted by a school's dean or any of its teachers — newest first. */
function inkwell_school_events($schoolId, $limit = 30) {
  inkwell_ensure_event_link_columns();
  $pdo = inkwell_db();
  $stmt = $pdo->prepare(
    "SELECT e.*, u.name AS author_name
     FROM events e
     JOIN users u ON u.id = e.author_id
     WHERE u.school_id = :sid OR u.id = (SELECT dean_id FROM schools WHERE id = :sid2)
     ORDER BY e.created_at DESC
     LIMIT " . (int) $limit
  );
  $stmt->execute(['sid' => $schoolId, 'sid2' => $schoolId]);
  return $stmt->fetchAll();
}

/** Absolute, shareable URL for one event (used by the copy-link box on the event card). */
function inkwell_event_url($eventId) {
  $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
  $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
  return $scheme . '://' . $host . '/events.php#event-' . (int) $eventId;
}

/**
 * Escapes event body text, then turns any http(s) URL in it (including a
 * pasted-in event share link like the one from the copy-link button) into
 * a clickable link, and preserves line breaks. Use this instead of a bare
 * nl2br(htmlspecialchars(...)) wherever an event body is displayed.
 */
function inkwell_linkify($text) {
  $escaped = htmlspecialchars((string) $text, ENT_QUOTES);
  $linked = preg_replace_callback('~https?://[^\s<]+~i', function ($m) {
    $url = $m[0];
    // Trailing punctuation (a period ending the sentence, a closing
    // parenthesis, etc.) usually isn't part of the URL — trim it back off.
    $trail = '';
    while ($url !== '' && strpos('.,;:!?)]', substr($url, -1)) !== false) {
      $trail = substr($url, -1) . $trail;
      $url = substr($url, 0, -1);
    }
    if ($url === '') return $m[0];
    return '<a href="' . $url . '" target="_blank" rel="noopener noreferrer">' . $url . '</a>' . $trail;
  }, $escaped);
  return nl2br($linked);
}

/** Only the original author may delete their own event. */
function inkwell_delete_event($id, $authorId) {
  $pdo = inkwell_db();
  $stmt = $pdo->prepare('DELETE FROM events WHERE id = ? AND author_id = ?');
  return $stmt->execute([$id, $authorId]);
}
