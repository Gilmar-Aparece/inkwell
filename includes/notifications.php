<?php
/**
 * In-app notifications — a small bell dropdown (see includes/notifications_bell.php)
 * that lights up for account-affecting transactions: a payment submitted,
 * instantly activated, approved, or rejected. Every payment touchpoint in
 * includes/billing.php calls into here so nothing has to remember to
 * "add a notification" itself — it just happens as a side effect of the
 * transaction.
 *
 * Follows the same self-healing pattern as inkwell_ensure_billing_columns()
 * in includes/billing.php: creates the `notifications` table on first use
 * if it's missing, and silently no-ops (rather than fatally erroring) on
 * hosts like InfinityFree that sometimes block DDL over a normal DB
 * connection — a failed notification should never take down the payment
 * flow that triggered it. See MIGRATION_ADD_notifications.sql for the
 * manual-apply version of the same table.
 *
 * Requires includes/db.php to already be loaded by the caller.
 */

require_once __DIR__ . '/db.php';

function inkwell_ensure_notifications_table() {
  static $checked = false;
  if ($checked) return;
  $checked = true;
  try {
    $pdo = inkwell_db();
    $pdo->exec("CREATE TABLE IF NOT EXISTS `notifications` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `user_id` int(11) NOT NULL,
        `type` varchar(40) NOT NULL DEFAULT 'general',
        `message` varchar(255) NOT NULL,
        `link` varchar(255) DEFAULT NULL,
        `is_read` tinyint(1) NOT NULL DEFAULT 0,
        `created_at` datetime NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`),
        KEY `user_id` (`user_id`),
        KEY `is_read` (`is_read`)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
  } catch (PDOException $e) {
    // Blocked or failed — every function below already guards its own
    // queries with try/catch, so callers just silently see zero
    // notifications instead of the whole transaction failing.
  }
}

/**
 * Writes one notification for a single user. Always wrapped so a DB hiccup
 * here can never bubble up and fail the payment/billing action that
 * triggered it — notifications are a nice-to-have layered on top of the
 * transaction, never a dependency of it.
 */
function inkwell_create_notification($userId, $type, $message, $link = null) {
  inkwell_ensure_notifications_table();
  try {
    $pdo = inkwell_db();
    $stmt = $pdo->prepare('INSERT INTO notifications (user_id, type, message, link) VALUES (?, ?, ?, ?)');
    $stmt->execute([(int) $userId, $type, $message, $link ?: null]);
  } catch (Exception $e) {
    // no-op — see note above
  }
}

/**
 * Same notification fanned out to every admin account — used for the
 * "someone submitted a payment" / "someone paid instantly" side of a
 * transaction, so an admin never has to go looking for new submissions.
 */
function inkwell_notify_admins($type, $message, $link = null) {
  require_once __DIR__ . '/auth.php'; // inkwell_list_admins()
  foreach (inkwell_list_admins() as $admin) {
    if (($admin['status'] ?? '') !== 'active') continue;
    inkwell_create_notification($admin['id'], $type, $message, $link);
  }
}

function inkwell_list_notifications($userId, $limit = 10) {
  inkwell_ensure_notifications_table();
  try {
    $pdo = inkwell_db();
    $stmt = $pdo->prepare('SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ?');
    $stmt->bindValue(1, (int) $userId, PDO::PARAM_INT);
    $stmt->bindValue(2, (int) $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
  } catch (Exception $e) {
    return [];
  }
}

function inkwell_count_unread_notifications($userId) {
  inkwell_ensure_notifications_table();
  try {
    $pdo = inkwell_db();
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
    $stmt->execute([(int) $userId]);
    return (int) $stmt->fetchColumn();
  } catch (Exception $e) {
    return 0;
  }
}

/** Marks a comma-separated list of notification ids (belonging to $userId) as read. */
function inkwell_mark_notifications_read($userId, array $ids) {
  $ids = array_values(array_filter(array_map('intval', $ids)));
  if (!$ids) return;
  inkwell_ensure_notifications_table();
  try {
    $pdo = inkwell_db();
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND id IN ($placeholders)");
    $stmt->execute(array_merge([(int) $userId], $ids));
  } catch (Exception $e) {
    // no-op
  }
}

function inkwell_mark_all_notifications_read($userId) {
  inkwell_ensure_notifications_table();
  try {
    $pdo = inkwell_db();
    $stmt = $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0');
    $stmt->execute([(int) $userId]);
  } catch (Exception $e) {
    // no-op
  }
}

/** Tiny relative-time formatter for the bell dropdown ("2h ago", "just now"). */
function inkwell_notif_time_ago($datetime) {
  $ts = strtotime($datetime);
  if (!$ts) return '';
  $diff = time() - $ts;
  if ($diff < 60) return 'just now';
  if ($diff < 3600) return floor($diff / 60) . 'm ago';
  if ($diff < 86400) return floor($diff / 3600) . 'h ago';
  if ($diff < 604800) return floor($diff / 86400) . 'd ago';
  return date('M j', $ts);
}
