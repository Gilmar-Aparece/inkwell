<?php
/**
 * Direct messages — lets any logged-in user (student, teacher, dean,
 * registrar, admin) start a private conversation with any other user on
 * the platform. One row per message; a "conversation" is just every row
 * where (sender_id, recipient_id) matches a pair of users in either
 * direction, so there's no separate conversations/threads table to keep
 * in sync — the thread is derived from the messages themselves.
 *
 * Messages can carry file/image attachments (see `message_attachments`,
 * one row per uploaded file, added by MIGRATION_ADD_message_attachments.sql).
 * A message row's `body` can be empty as long as it has at least one
 * attachment — "just a photo, no caption" is a normal message.
 *
 * Follows the same self-healing pattern as includes/notifications.php:
 * creates the `messages` / `message_attachments` tables on first use if
 * missing, and silently no-ops (rather than fatally erroring) on hosts
 * like InfinityFree that sometimes block DDL over a normal DB connection.
 * See MIGRATION_ADD_messages.sql / MIGRATION_ADD_message_attachments.sql
 * for the manual-apply versions.
 *
 * Sending a message also drops a row into `notifications` (via
 * includes/notifications.php) so the recipient sees it in the bell
 * dropdown too, same as every other cross-user event in the app.
 *
 * Requires includes/db.php and includes/store.php (for INKWELL_UPLOADS_DIR
 * and inkwell_format_bytes()) to already be loaded by the caller.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/store.php';
// Unconditional (not just inside inkwell_send_message()) — inkwell_notif_time_ago()
// from here is used by inkwell_render_conversation_item() and
// inkwell_render_message_bubble() too, both of which run on the AJAX
// load_thread/list_conversations/poll_thread paths where a message is
// never actually sent. Loading it lazily only inside inkwell_send_message()
// left those paths calling an undefined function — a fatal Error that got
// swallowed by messages.php's outer catch(Throwable) and surfaced to the
// person as a generic "Something went wrong" popup the moment they opened
// a conversation, even though nothing was actually broken about the thread.
require_once __DIR__ . '/notifications.php';

function inkwell_ensure_messages_table() {
  static $checked = false;
  if ($checked) return;
  $checked = true;
  try {
    $pdo = inkwell_db();
    $pdo->exec("CREATE TABLE IF NOT EXISTS `messages` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `sender_id` int(11) NOT NULL,
        `recipient_id` int(11) NOT NULL,
        `body` text NOT NULL,
        `is_read` tinyint(1) NOT NULL DEFAULT 0,
        `created_at` datetime NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`),
        KEY `sender_id` (`sender_id`),
        KEY `recipient_id` (`recipient_id`),
        KEY `recipient_read` (`recipient_id`, `is_read`)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
  } catch (PDOException $e) {
    // Blocked or failed — every function below already guards its own
    // queries with try/catch, so callers just silently see zero
    // messages instead of the whole page failing. See INKWELL_MESSAGES_SQL
    // below for the phpMyAdmin fallback shown to the user in that case.
  }
}

/** Same self-healing pattern as inkwell_ensure_messages_table(), for the attachments side table. */
function inkwell_ensure_message_attachments_table() {
  static $checked = false;
  if ($checked) return;
  $checked = true;
  try {
    $pdo = inkwell_db();
    $pdo->exec("CREATE TABLE IF NOT EXISTS `message_attachments` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `message_id` int(11) NOT NULL,
        `kind` enum('image','file') NOT NULL DEFAULT 'file',
        `filename` varchar(190) NOT NULL,
        `original_name` varchar(190) NOT NULL,
        `mime` varchar(120) NOT NULL DEFAULT '',
        `size` int(11) NOT NULL DEFAULT 0,
        `created_at` datetime NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`),
        KEY `message_id` (`message_id`)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
  } catch (PDOException $e) {
    // Same silent-no-op story as messages — inkwell_send_message() just
    // won't persist attachments on a host that blocks this, but plain
    // text messages keep working.
  }
}

/** True once we've confirmed the table exists (used to decide whether to show the manual-SQL fallback box). */
function inkwell_messages_table_exists() {
  inkwell_ensure_messages_table();
  try {
    inkwell_db()->query('SELECT 1 FROM messages LIMIT 1');
    return true;
  } catch (PDOException $e) {
    return false;
  }
}

define('INKWELL_MESSAGES_SQL', "CREATE TABLE IF NOT EXISTS `messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sender_id` int(11) NOT NULL,
  `recipient_id` int(11) NOT NULL,
  `body` text NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `sender_id` (`sender_id`),
  KEY `recipient_id` (`recipient_id`),
  KEY `recipient_read` (`recipient_id`, `is_read`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `message_attachments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `message_id` int(11) NOT NULL,
  `kind` enum('image','file') NOT NULL DEFAULT 'file',
  `filename` varchar(190) NOT NULL,
  `original_name` varchar(190) NOT NULL,
  `mime` varchar(120) NOT NULL DEFAULT '',
  `size` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `message_id` (`message_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");

/**
 * Every user who's allowed to receive a DM — currently everyone with an
 * active account. Used by the "New message" search box on messages.php.
 * $query filters by name/email (case-insensitive substring); empty
 * query returns the most recently active accounts first, capped at $limit.
 * $role optionally restricts to one role ('student'|'teacher'|'dean'|'registrar'|'admin').
 */
function inkwell_search_messageable_users($query, $excludeUserId, $limit = 20, $role = null) {
  $pdo = inkwell_db();
  $query = trim((string) $query);
  $validRoles = ['student', 'teacher', 'dean', 'registrar', 'admin'];
  $role = in_array($role, $validRoles, true) ? $role : null;

  $where = "status = 'active' AND id != ?";
  $params = [(int) $excludeUserId];
  if ($query !== '') {
    $where .= ' AND (name LIKE ? OR email LIKE ?)';
    $like = '%' . $query . '%';
    $params[] = $like;
    $params[] = $like;
  }
  if ($role !== null) {
    $where .= ' AND role = ?';
    $params[] = $role;
  }

  $stmt = $pdo->prepare("SELECT id, name, email, role, avatar FROM users WHERE $where ORDER BY name ASC LIMIT ?");
  $i = 1;
  foreach ($params as $p) { $stmt->bindValue($i++, $p); }
  $stmt->bindValue($i, (int) $limit, PDO::PARAM_INT);
  $stmt->execute();
  return $stmt->fetchAll();
}

function inkwell_get_messageable_user($userId) {
  $pdo = inkwell_db();
  $stmt = $pdo->prepare("SELECT id, name, email, role, avatar FROM users WHERE id = ? AND status = 'active'");
  $stmt->execute([(int) $userId]);
  $user = $stmt->fetch();
  return $user ?: null;
}

/* ---------------- Attachment upload handling ---------------- */

/** image/* mime => file extension to save under. */
function inkwell_message_attachment_image_types() {
  return ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp', 'image/gif' => 'gif'];
}

/** Non-image mime => extension, for the generic "file" attachment kind. Extension is trusted here (office/zip formats don't have one reliable mime), but re-checked against the upload's own name. */
function inkwell_message_attachment_file_extensions() {
  return ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv', 'zip', 'rar'];
}

const INKWELL_MESSAGE_ATTACHMENT_MAX_IMAGE_BYTES = 8 * 1024 * 1024;   // 8MB
const INKWELL_MESSAGE_ATTACHMENT_MAX_FILE_BYTES = 20 * 1024 * 1024;   // 20MB
const INKWELL_MESSAGE_ATTACHMENT_MAX_COUNT = 6;                        // per message

/**
 * Validates and saves ONE already-split upload item (see
 * inkwell_normalize_files() in includes/posts.php's pattern — messages.php
 * calls the same helper for its 'attachments' field). Returns
 * ['ok'=>true,'filename'=>...,'original_name'=>...,'mime'=>...,'size'=>...,'kind'=>'image'|'file']
 * or ['ok'=>false,'error'=>...].
 */
function inkwell_handle_message_attachment_item($item) {
  if (($item['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    $messages = [
      UPLOAD_ERR_INI_SIZE => 'One of those files is too large for this server\'s upload limit.',
      UPLOAD_ERR_FORM_SIZE => 'One of those files is too large.',
      UPLOAD_ERR_PARTIAL => 'One of those uploads was interrupted — please try again.',
      UPLOAD_ERR_NO_TMP_DIR => 'Server is missing a temp folder for uploads — contact the site admin.',
      UPLOAD_ERR_CANT_WRITE => 'Could not write a file to disk — contact the site admin.',
    ];
    return ['ok' => false, 'error' => $messages[$item['error']] ?? 'Upload failed (error code ' . $item['error'] . ').'];
  }

  $tmpPath = $item['tmp_name'];
  $mime = '';
  if (function_exists('finfo_open')) {
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    if ($finfo) { $mime = finfo_file($finfo, $tmpPath); finfo_close($finfo); }
  }

  $imageTypes = inkwell_message_attachment_image_types();
  $originalName = trim((string) ($item['name'] ?? 'file'));
  $safeOriginalName = preg_replace('/[^\p{L}\p{N}\s._-]/u', '', $originalName);
  if ($safeOriginalName === '') $safeOriginalName = 'file';
  if (mb_strlen($safeOriginalName) > 120) $safeOriginalName = mb_substr($safeOriginalName, 0, 120);

  // Images: verified by real mime type via finfo/getimagesize, same rule as post photos.
  if (isset($imageTypes[$mime])) {
    if ($item['size'] > INKWELL_MESSAGE_ATTACHMENT_MAX_IMAGE_BYTES) {
      return ['ok' => false, 'error' => 'Each photo must be under 8MB.'];
    }
    if (!@getimagesize($tmpPath)) {
      return ['ok' => false, 'error' => 'That image looks corrupted — try a different file.'];
    }
    if (!is_dir(INKWELL_UPLOADS_DIR)) @mkdir(INKWELL_UPLOADS_DIR, 0775, true);
    $filename = 'msg_att_' . bin2hex(random_bytes(6)) . '.' . $imageTypes[$mime];
    if (!move_uploaded_file($tmpPath, INKWELL_UPLOADS_DIR . '/' . $filename)) {
      return ['ok' => false, 'error' => 'Could not save an uploaded photo — check that assets/uploads/ is writable.'];
    }
    return ['ok' => true, 'filename' => $filename, 'original_name' => $safeOriginalName, 'mime' => $mime, 'size' => (int) $item['size'], 'kind' => 'image'];
  }

  // Everything else: whitelist by extension (office/zip formats don't have one dependable mime across servers).
  $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
  $allowedExt = inkwell_message_attachment_file_extensions();
  if (!in_array($ext, $allowedExt, true)) {
    return ['ok' => false, 'error' => '"' . $safeOriginalName . '" isn\'t a supported file type.'];
  }
  if ($item['size'] > INKWELL_MESSAGE_ATTACHMENT_MAX_FILE_BYTES) {
    return ['ok' => false, 'error' => 'Each file must be under 20MB.'];
  }
  if (!is_dir(INKWELL_UPLOADS_DIR)) @mkdir(INKWELL_UPLOADS_DIR, 0775, true);
  $filename = 'msg_att_' . bin2hex(random_bytes(6)) . '.' . $ext;
  if (!move_uploaded_file($tmpPath, INKWELL_UPLOADS_DIR . '/' . $filename)) {
    return ['ok' => false, 'error' => 'Could not save an uploaded file — check that assets/uploads/ is writable.'];
  }
  return ['ok' => true, 'filename' => $filename, 'original_name' => $safeOriginalName, 'mime' => $mime, 'size' => (int) $item['size'], 'kind' => 'file'];
}

/** Turns PHP's parallel-array shape for a multi-file <input multiple name="attachments[]"> field into a normal list of [name, type, tmp_name, error, size] items. Safe to call on a field that wasn't submitted (returns []). Local copy of the same helper posts.php uses, so this file has no dependency on includes/posts.php. */
function inkwell_normalize_message_files($fileField) {
  if (empty($_FILES[$fileField]) || empty($_FILES[$fileField]['name'])) return [];
  $f = $_FILES[$fileField];
  if (!is_array($f['name'])) return [$f];
  $items = [];
  foreach ($f['name'] as $i => $name) {
    if ($name === '' || $name === null) continue;
    $items[] = [
      'name' => $name,
      'type' => $f['type'][$i] ?? '',
      'tmp_name' => $f['tmp_name'][$i] ?? '',
      'error' => $f['error'][$i] ?? UPLOAD_ERR_NO_FILE,
      'size' => $f['size'][$i] ?? 0,
    ];
  }
  return $items;
}

/** All attachment rows for one message, oldest first. */
function inkwell_get_message_attachments($messageId) {
  inkwell_ensure_message_attachments_table();
  try {
    $stmt = inkwell_db()->prepare('SELECT * FROM message_attachments WHERE message_id = ? ORDER BY id ASC');
    $stmt->execute([(int) $messageId]);
    return $stmt->fetchAll();
  } catch (Exception $e) {
    return [];
  }
}

/** Bulk-fetch attachments for many messages at once (one query instead of N+1), keyed by message_id. */
function inkwell_get_attachments_for_messages(array $messageIds) {
  $messageIds = array_values(array_unique(array_map('intval', $messageIds)));
  if (!$messageIds) return [];
  inkwell_ensure_message_attachments_table();
  try {
    $placeholders = implode(',', array_fill(0, count($messageIds), '?'));
    $stmt = inkwell_db()->prepare("SELECT * FROM message_attachments WHERE message_id IN ($placeholders) ORDER BY id ASC");
    $stmt->execute($messageIds);
    $byMessage = [];
    foreach ($stmt->fetchAll() as $row) {
      $byMessage[(int) $row['message_id']][] = $row;
    }
    return $byMessage;
  } catch (Exception $e) {
    return [];
  }
}

/**
 * Sends one message and fires a notification for the recipient. Refuses
 * a user messaging themselves and messages with neither text nor a
 * valid attachment; everything else (any role to any role) is allowed
 * by design — this is a general "everyone can talk to everyone" inbox,
 * not restricted by section/class.
 *
 * $attachmentItems is a plain list of normalized $_FILES-style items
 * (see inkwell_normalize_files() in includes/posts.php — messages.php's
 * caller uses the same helper against the 'attachments' field). Pass []
 * for a text-only message.
 */
function inkwell_send_message($senderId, $recipientId, $body, $attachmentItems = []) {
  $senderId = (int) $senderId;
  $recipientId = (int) $recipientId;
  $body = trim((string) $body);

  if (mb_strlen($body) > 4000) return ['ok' => false, 'error' => 'That message is too long.'];
  if ($senderId === $recipientId) return ['ok' => false, 'error' => 'You can\'t message yourself.'];

  $recipient = inkwell_get_messageable_user($recipientId);
  if (!$recipient) return ['ok' => false, 'error' => 'That user is no longer available.'];

  // Validate + save every attachment BEFORE touching the messages table,
  // so a bad file never leaves behind an empty message row.
  $pendingItems = array_values(array_filter($attachmentItems, function ($item) {
    return ($item['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
  }));
  if (count($pendingItems) > INKWELL_MESSAGE_ATTACHMENT_MAX_COUNT) {
    return ['ok' => false, 'error' => 'You can attach up to ' . INKWELL_MESSAGE_ATTACHMENT_MAX_COUNT . ' files at once.'];
  }
  $savedAttachments = [];
  foreach ($pendingItems as $item) {
    $result = inkwell_handle_message_attachment_item($item);
    if (!$result['ok']) return $result;
    $savedAttachments[] = $result;
  }

  if ($body === '' && !$savedAttachments) {
    return ['ok' => false, 'error' => 'Message can\'t be empty.'];
  }

  inkwell_ensure_messages_table();
  try {
    $pdo = inkwell_db();
    $stmt = $pdo->prepare('INSERT INTO messages (sender_id, recipient_id, body) VALUES (?, ?, ?)');
    $stmt->execute([$senderId, $recipientId, $body]);
    $id = (int) $pdo->lastInsertId();
  } catch (Exception $e) {
    return ['ok' => false, 'error' => 'Could not send your message. Try again in a moment.'];
  }

  if ($savedAttachments) {
    inkwell_ensure_message_attachments_table();
    try {
      $stmt = inkwell_db()->prepare(
        'INSERT INTO message_attachments (message_id, kind, filename, original_name, mime, size) VALUES (?, ?, ?, ?, ?, ?)'
      );
      foreach ($savedAttachments as $att) {
        $stmt->execute([$id, $att['kind'], $att['filename'], $att['original_name'], $att['mime'], $att['size']]);
      }
    } catch (Exception $e) {
      // Message itself already sent successfully; losing the attachment
      // row (e.g. table blocked on this host) shouldn't fail the whole send.
    }
  }

  $sender = inkwell_get_messageable_user($senderId);
  $senderName = $sender ? $sender['name'] : 'Someone';
  if ($body !== '') {
    $preview = mb_strlen($body) > 60 ? mb_substr($body, 0, 60) . '…' : $body;
    $notifText = $senderName . ' sent you a message: "' . $preview . '"';
  } elseif (count($savedAttachments) === 1) {
    $notifText = $senderName . ' sent you ' . ($savedAttachments[0]['kind'] === 'image' ? 'a photo' : 'a file') . '.';
  } else {
    $notifText = $senderName . ' sent you ' . count($savedAttachments) . ' files.';
  }
  inkwell_create_notification($recipientId, 'message', $notifText, '/messages.php?with=' . $senderId);

  return ['ok' => true, 'id' => $id];
}

/**
 * One row per conversation partner, most-recently-active first: the
 * other user's profile fields plus the latest message and how many of
 * their messages to me are still unread. Built with two subqueries
 * (last message id per pair, unread count per pair) rather than a
 * window function, since some InfinityFree MariaDB versions don't
 * support ROW_NUMBER()/OVER.
 */
/**
 * $archived selects which tab this is for: false (default) = General,
 * everything I haven't archived; true = Archive, only threads I have
 * archived. Archiving is per-user (see inkwell_ensure_message_archives_table()
 * above), so it's a LEFT JOIN against my own archive rows, filtered by
 * whether that join matched.
 */
function inkwell_list_conversations($userId, $archived = false) {
  inkwell_ensure_messages_table();
  inkwell_ensure_message_archives_table();
  $userId = (int) $userId;
  try {
    $pdo = inkwell_db();
    $stmt = $pdo->prepare(
      "SELECT
         u.id, u.name, u.email, u.role, u.avatar,
         m.id AS last_message_id, m.body AS last_body, m.created_at AS last_at,
         m.sender_id AS last_sender_id,
         COALESCE(unread.cnt, 0) AS unread_count
       FROM (
         SELECT
           CASE WHEN sender_id = ? THEN recipient_id ELSE sender_id END AS partner_id,
           MAX(id) AS last_message_id
         FROM messages
         WHERE sender_id = ? OR recipient_id = ?
         GROUP BY partner_id
       ) latest
       JOIN messages m ON m.id = latest.last_message_id
       JOIN users u ON u.id = latest.partner_id
       LEFT JOIN (
         SELECT sender_id, COUNT(*) AS cnt
         FROM messages
         WHERE recipient_id = ? AND is_read = 0
         GROUP BY sender_id
       ) unread ON unread.sender_id = u.id
       LEFT JOIN message_archives ma ON ma.user_id = ? AND ma.other_user_id = u.id
       WHERE " . ($archived ? "ma.id IS NOT NULL" : "ma.id IS NULL") . "
       ORDER BY m.created_at DESC"
    );
    $stmt->execute([$userId, $userId, $userId, $userId, $userId]);
    $rows = $stmt->fetchAll();
  } catch (Exception $e) {
    return [];
  }

  // Attach a one-line preview of the last message's attachments (e.g. "📷 Photo"),
  // used when last_body is empty so the sidebar preview isn't blank.
  $lastIds = array_column($rows, 'last_message_id');
  $attByMessage = inkwell_get_attachments_for_messages($lastIds);
  foreach ($rows as &$row) {
    $atts = $attByMessage[(int) $row['last_message_id']] ?? [];
    $row['last_attachment_summary'] = inkwell_attachment_summary_text($atts);
  }
  unset($row);
  return $rows;
}

/**
 * Per-user "I archived this conversation" flags. Archiving is personal —
 * if I archive my thread with someone, it just moves off my General list
 * into my Archive tab; it has no effect on what they see. One row per
 * (user_id, other_user_id) pair; presence of a row means "archived by
 * user_id". Same self-healing create-on-first-use pattern as the other
 * tables in this file.
 */
function inkwell_ensure_message_archives_table() {
  static $checked = false;
  if ($checked) return;
  $checked = true;
  try {
    $pdo = inkwell_db();
    $pdo->exec("CREATE TABLE IF NOT EXISTS `message_archives` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `user_id` int(11) NOT NULL,
        `other_user_id` int(11) NOT NULL,
        `archived_at` datetime NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`),
        UNIQUE KEY `user_other` (`user_id`, `other_user_id`),
        KEY `user_id` (`user_id`)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
  } catch (PDOException $e) {
    // Blocked on this host — inkwell_archive_conversation() below will
    // just silently no-op, same story as the messages table itself.
  }
}

/** Moves a conversation to my Archive tab. Idempotent — archiving an already-archived thread is a no-op, not an error. */
function inkwell_archive_conversation($userId, $otherUserId) {
  inkwell_ensure_message_archives_table();
  try {
    $pdo = inkwell_db();
    $stmt = $pdo->prepare('INSERT INTO message_archives (user_id, other_user_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE archived_at = archived_at');
    $stmt->execute([(int) $userId, (int) $otherUserId]);
    return true;
  } catch (Exception $e) {
    return false;
  }
}

/** Moves a conversation back to my General tab. */
function inkwell_unarchive_conversation($userId, $otherUserId) {
  inkwell_ensure_message_archives_table();
  try {
    $pdo = inkwell_db();
    $stmt = $pdo->prepare('DELETE FROM message_archives WHERE user_id = ? AND other_user_id = ?');
    $stmt->execute([(int) $userId, (int) $otherUserId]);
    return true;
  } catch (Exception $e) {
    return false;
  }
}

/** Whether I've archived my thread with $otherUserId. Used to label the toggle in the thread header ("Archive" vs "Unarchive"). */
function inkwell_is_conversation_archived($userId, $otherUserId) {
  inkwell_ensure_message_archives_table();
  try {
    $pdo = inkwell_db();
    $stmt = $pdo->prepare('SELECT 1 FROM message_archives WHERE user_id = ? AND other_user_id = ? LIMIT 1');
    $stmt->execute([(int) $userId, (int) $otherUserId]);
    return (bool) $stmt->fetchColumn();
  } catch (Exception $e) {
    return false;
  }
}

/** "📷 Photo" / "📷 3 photos" / "📎 report.pdf" / "📎 4 files" — used as the sidebar preview line when a message has no text body. */
function inkwell_attachment_summary_text($attachments) {
  if (!$attachments) return '';
  $images = array_filter($attachments, function ($a) { return $a['kind'] === 'image'; });
  $files = array_filter($attachments, function ($a) { return $a['kind'] === 'file'; });
  if ($images && !$files) {
    return count($images) === 1 ? '📷 Photo' : '📷 ' . count($images) . ' photos';
  }
  if ($files && !$images) {
    // reset() instead of array_key_first() — array_key_first() needs PHP
    // 7.3+, and on older shared-hosting PHP calling it throws a fatal
    // Error (not an Exception), which the AJAX handler's try/catch can't
    // catch, corrupting the JSON response. reset() does the same job
    // ("give me the first value") and has worked since PHP 4.
    $first = reset($files);
    return count($files) === 1 ? '📎 ' . $first['original_name'] : '📎 ' . count($files) . ' files';
  }
  return '📎 ' . count($attachments) . ' attachments';
}

/** All messages exchanged between two users, oldest first, ready to render top-to-bottom in a thread. Each row gets an 'attachments' key. */
function inkwell_list_thread_messages($userId, $otherUserId, $limit = 300) {
  inkwell_ensure_messages_table();
  try {
    $pdo = inkwell_db();
    $stmt = $pdo->prepare(
      "SELECT * FROM messages
       WHERE (sender_id = ? AND recipient_id = ?) OR (sender_id = ? AND recipient_id = ?)
       ORDER BY created_at ASC, id ASC
       LIMIT ?"
    );
    $stmt->bindValue(1, (int) $userId, PDO::PARAM_INT);
    $stmt->bindValue(2, (int) $otherUserId, PDO::PARAM_INT);
    $stmt->bindValue(3, (int) $otherUserId, PDO::PARAM_INT);
    $stmt->bindValue(4, (int) $userId, PDO::PARAM_INT);
    $stmt->bindValue(5, (int) $limit, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();
  } catch (Exception $e) {
    return [];
  }

  $attByMessage = inkwell_get_attachments_for_messages(array_column($rows, 'id'));
  foreach ($rows as &$row) {
    $row['attachments'] = $attByMessage[(int) $row['id']] ?? [];
  }
  unset($row);
  return $rows;
}

/** Marks every message the other user sent to me in this thread as read (called when I open the thread). */
function inkwell_mark_thread_read($userId, $otherUserId) {
  inkwell_ensure_messages_table();
  try {
    $pdo = inkwell_db();
    $stmt = $pdo->prepare('UPDATE messages SET is_read = 1 WHERE recipient_id = ? AND sender_id = ? AND is_read = 0');
    $stmt->execute([(int) $userId, (int) $otherUserId]);
  } catch (Exception $e) {
    // no-op
  }
}

/** Total unread DMs across every conversation — used for the nav badge, same idea as inkwell_count_unread_notifications(). */
function inkwell_count_unread_messages($userId) {
  inkwell_ensure_messages_table();
  try {
    $pdo = inkwell_db();
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM messages WHERE recipient_id = ? AND is_read = 0');
    $stmt->execute([(int) $userId]);
    return (int) $stmt->fetchColumn();
  } catch (Exception $e) {
    return 0;
  }
}

/* ---------------- Render helpers (shared between full page load and AJAX partial refresh) ---------------- */

function inkwell_message_avatar_html($user) {
  if (!empty($user['avatar'])) {
    return '<img src="/assets/uploads/' . htmlspecialchars($user['avatar']) . '" alt="" loading="lazy">';
  }
  return htmlspecialchars(strtoupper(substr($user['name'] ?? '?', 0, 1)));
}

/** $archived: which tab this item is being rendered for — controls whether the kebab menu offers "Archive" or "Unarchive". */
function inkwell_render_conversation_item($convo, $activePartnerId, $archived = false) {
  $isActive = (int) $convo['id'] === (int) $activePartnerId;
  $preview = trim((string) $convo['last_body']);
  if ($preview === '' && !empty($convo['last_attachment_summary'])) {
    $preview = $convo['last_attachment_summary'];
  }
  if (mb_strlen($preview) > 46) $preview = mb_substr($preview, 0, 46) . '…';
  $mine = isset($convo['last_sender_id']) && (int) $convo['last_sender_id'] !== (int) $convo['id'];
  $actionName = $archived ? 'unarchive' : 'archive';
  $actionLabel = $archived ? 'Unarchive conversation' : 'Archive conversation';
  ob_start();
  ?>
  <div class="msg-convo-item<?php echo $isActive ? ' active' : ''; ?><?php echo ((int) $convo['unread_count'] > 0) ? ' has-unread' : ''; ?>" data-user-id="<?php echo (int) $convo['id']; ?>">
    <a href="/messages.php?with=<?php echo (int) $convo['id']; ?>"
       class="msg-convo-link"
       data-convo-link data-user-id="<?php echo (int) $convo['id']; ?>" data-user-name="<?php echo htmlspecialchars($convo['name']); ?>" data-user-role="<?php echo htmlspecialchars(ucfirst($convo['role'])); ?>">
      <span class="msg-convo-avatar"><?php echo inkwell_message_avatar_html($convo); ?></span>
      <span class="msg-convo-body">
        <span class="msg-convo-top">
          <strong><?php echo htmlspecialchars($convo['name']); ?></strong>
          <span class="msg-convo-time"><?php echo htmlspecialchars(inkwell_notif_time_ago($convo['last_at'])); ?></span>
        </span>
        <span class="msg-convo-preview"><?php echo $mine ? 'You: ' : ''; ?><?php echo htmlspecialchars($preview); ?></span>
      </span>
      <?php if ((int) $convo['unread_count'] > 0): ?>
        <span class="msg-convo-badge" title="<?php echo (int) $convo['unread_count']; ?> unread"></span>
      <?php endif; ?>
    </a>
    <div class="msg-convo-menu-wrap">
      <button type="button" class="msg-convo-menu-btn" data-convo-menu-btn aria-label="More options">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="5" r="1.5"/><circle cx="12" cy="12" r="1.5"/><circle cx="12" cy="19" r="1.5"/></svg>
      </button>
      <div class="msg-convo-menu" data-convo-menu-panel>
        <button type="button" data-convo-action="<?php echo $actionName; ?>" data-user-id="<?php echo (int) $convo['id']; ?>"><?php echo $actionLabel; ?></button>
      </div>
    </div>
  </div>
  <?php
  return ob_get_clean();
}

/** One attachment as a chip/thumbnail — used inside a bubble. Images link to the full-size file; other files link with download="" so the browser saves rather than tries to navigate to it. */
function inkwell_render_attachment_html($att) {
  $url = '/assets/uploads/' . rawurlencode($att['filename']);
  if ($att['kind'] === 'image') {
    ob_start();
    ?>
    <a class="msg-att-image" href="<?php echo htmlspecialchars($url); ?>" target="_blank" rel="noopener">
      <img src="<?php echo htmlspecialchars($url); ?>" alt="<?php echo htmlspecialchars($att['original_name']); ?>" loading="lazy">
    </a>
    <?php
    return ob_get_clean();
  }
  $ext = strtoupper(pathinfo($att['original_name'], PATHINFO_EXTENSION));
  ob_start();
  ?>
  <a class="msg-att-file" href="<?php echo htmlspecialchars($url); ?>" target="_blank" rel="noopener" download="<?php echo htmlspecialchars($att['original_name']); ?>">
    <span class="msg-att-file-icon" aria-hidden="true">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
    </span>
    <span class="msg-att-file-info">
      <span class="msg-att-file-name"><?php echo htmlspecialchars($att['original_name']); ?></span>
      <span class="msg-att-file-meta"><?php echo htmlspecialchars($ext); ?> · <?php echo htmlspecialchars(inkwell_format_bytes((int) $att['size'])); ?></span>
    </span>
    <span class="msg-att-file-dl" aria-hidden="true">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
    </span>
  </a>
  <?php
  return ob_get_clean();
}

function inkwell_render_message_bubble($message, $meId) {
  $mine = (int) $message['sender_id'] === (int) $meId;
  $attachments = $message['attachments'] ?? [];
  $images = array_values(array_filter($attachments, function ($a) { return $a['kind'] === 'image'; }));
  $files = array_values(array_filter($attachments, function ($a) { return $a['kind'] === 'file'; }));
  $body = trim((string) $message['body']);
  ob_start();
  ?>
  <div class="msg-bubble-row<?php echo $mine ? ' mine' : ''; ?>" data-message-id="<?php echo (int) $message['id']; ?>">
    <?php if ($images): ?>
      <div class="msg-att-image-grid msg-att-image-grid-<?php echo min(count($images), 4); ?>">
        <?php foreach ($images as $img) echo inkwell_render_attachment_html($img); ?>
      </div>
    <?php endif; ?>
    <?php if ($files): ?>
      <div class="msg-att-file-list">
        <?php foreach ($files as $f) echo inkwell_render_attachment_html($f); ?>
      </div>
    <?php endif; ?>
    <?php if ($body !== ''): ?>
      <div class="msg-bubble"><?php echo nl2br(htmlspecialchars($body)); ?></div>
    <?php endif; ?>
    <div class="msg-bubble-time"><?php echo htmlspecialchars(inkwell_notif_time_ago($message['created_at'])); ?></div>
  </div>
  <?php
  return ob_get_clean();
}

function inkwell_render_message_thread($messages, $meId) {
  if (!$messages) {
    return '<p class="admin-sub msg-thread-empty">No messages yet — say hello!</p>';
  }
  $html = '';
  foreach ($messages as $m) $html .= inkwell_render_message_bubble($m, $meId);
  return $html;
}

function inkwell_render_new_convo_result($user) {
  ob_start();
  ?>
  <button type="button" class="msg-search-result" data-start-convo data-user-id="<?php echo (int) $user['id']; ?>" data-user-name="<?php echo htmlspecialchars($user['name']); ?>" data-user-role="<?php echo htmlspecialchars(ucfirst($user['role'])); ?>">
    <span class="msg-convo-avatar"><?php echo inkwell_message_avatar_html($user); ?></span>
    <span class="msg-convo-body">
      <strong><?php echo htmlspecialchars($user['name']); ?></strong>
      <span class="msg-convo-preview"><?php echo htmlspecialchars(ucfirst($user['role'])); ?> · <?php echo htmlspecialchars($user['email']); ?></span>
    </span>
  </button>
  <?php
  return ob_get_clean();
}
