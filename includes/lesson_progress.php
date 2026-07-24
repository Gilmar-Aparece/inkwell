<?php
/**
 * Tracks which lessons each logged-in user has opened (used for the admin
 * "lesson progress" view — see admin/lesson-progress.php) and decides
 * whether a given lesson is locked behind the Pro plan.
 *
 * Self-healing table creation, same pattern as inkwell_ensure_billing_columns()
 * in billing.php — no manual migration needed on InfinityFree.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/store.php';
require_once __DIR__ . '/billing.php';

function inkwell_ensure_lesson_progress_table() {
  static $checked = false;
  if ($checked) return;
  $checked = true;
  $pdo = inkwell_db();
  try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS lesson_progress (
      id INT NOT NULL AUTO_INCREMENT,
      user_id INT NOT NULL,
      cat VARCHAR(100) NOT NULL,
      slug VARCHAR(150) NOT NULL,
      first_viewed_at DATETIME NOT NULL DEFAULT current_timestamp(),
      last_viewed_at DATETIME NOT NULL DEFAULT current_timestamp(),
      PRIMARY KEY (id),
      UNIQUE KEY uniq_user_lesson (user_id, cat, slug),
      KEY user_id (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
  } catch (Exception $e) {
    // Table already exists, or the account can't create tables — either
    // way, the calls below fail silently rather than breaking the lesson page.
  }
}

/**
 * Position (0-based) of a lesson within its track's ordered lesson list, or
 * null if the lesson/category doesn't exist. Used to decide whether it
 * falls within the free preview window.
 */
function inkwell_lesson_position($cat, $slug) {
  $category = inkwell_category($cat);
  if (!$category) return null;
  $keys = array_keys($category['lessons']);
  $i = array_search($slug, $keys, true);
  return $i === false ? null : $i;
}

/**
 * True if this lesson is locked for this user — i.e. it's past the free
 * preview window for its track AND the user doesn't have an active plan
 * that unlocks the full lesson library. $user may be null (guest).
 */
function inkwell_lesson_is_locked($cat, $slug, $user) {
  $position = inkwell_lesson_position($cat, $slug);
  if ($position === null) return false; // unknown lesson — let the 404 path handle it
  if ($position < inkwell_free_lessons_per_track()) return false; // within the free preview
  return !inkwell_user_has_full_lesson_access($user);
}

/** Records (or refreshes) that a logged-in user opened a lesson. No-op for guests. */
function inkwell_record_lesson_view($userId, $cat, $slug) {
  if (!$userId) return;
  inkwell_ensure_lesson_progress_table();
  try {
    $pdo = inkwell_db();
    $stmt = $pdo->prepare(
      'INSERT INTO lesson_progress (user_id, cat, slug) VALUES (?, ?, ?)
       ON DUPLICATE KEY UPDATE last_viewed_at = NOW()'
    );
    $stmt->execute([(int) $userId, $cat, $slug]);
  } catch (Exception $e) {
    // Table missing/unwritable — skip silently, same convention as
    // inkwell_update_last_lesson() in auth.php.
  }
}

/** All (cat, slug, first_viewed_at) rows for one user, most recent first. */
function inkwell_user_lesson_progress($userId) {
  inkwell_ensure_lesson_progress_table();
  try {
    $pdo = inkwell_db();
    $stmt = $pdo->prepare('SELECT cat, slug, first_viewed_at, last_viewed_at FROM lesson_progress WHERE user_id = ? ORDER BY last_viewed_at DESC');
    $stmt->execute([(int) $userId]);
    return $stmt->fetchAll();
  } catch (Exception $e) {
    return [];
  }
}

/** Total lessons across every track (built-in + admin-added), for "X of Y completed" displays. */
function inkwell_total_lesson_count() {
  $total = 0;
  foreach (inkwell_categories() as $cat) $total += count($cat['lessons']);
  return $total;
}

/**
 * One row per user who has viewed at least one lesson: id, name, email,
 * role, lessons_viewed count, last_viewed_at. Ordered by most lessons
 * viewed first. Powers admin/lesson-progress.php.
 */
function inkwell_lesson_progress_overview() {
  inkwell_ensure_lesson_progress_table();
  try {
    $pdo = inkwell_db();
    return $pdo->query(
      "SELECT u.id, u.name, u.email, u.role,
              COUNT(lp.id) AS lessons_viewed,
              MAX(lp.last_viewed_at) AS last_viewed_at
       FROM lesson_progress lp
       JOIN users u ON u.id = lp.user_id
       GROUP BY u.id, u.name, u.email, u.role
       ORDER BY lessons_viewed DESC, last_viewed_at DESC"
    )->fetchAll();
  } catch (Exception $e) {
    return [];
  }
}
