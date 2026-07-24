<?php
/**
 * Personal notes tool — available to every logged-in role (student,
 * teacher, dean). Each note is either:
 *   - "text": a rich note edited in a contenteditable box (bold/italic/
 *     underline/font family/font size), stored as HTML.
 *   - "code": a plain monospace note with a language label, stored as
 *     raw text (no HTML formatting).
 * Notes can optionally have one attached file. Requires
 * MIGRATION_ADD_user_notes.sql to have been run.
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/schools.php'; // reuses inkwell_handle_logo_upload-style helpers pattern

define('INKWELL_USER_NOTES_SQL', "CREATE TABLE `user_notes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL DEFAULT 'Untitled note',
  `type` enum('text','code') NOT NULL DEFAULT 'text',
  `content` longtext DEFAULT NULL,
  `font_family` varchar(60) DEFAULT NULL,
  `font_size` int(11) DEFAULT NULL,
  `code_language` varchar(30) DEFAULT NULL,
  `attachment` varchar(255) DEFAULT NULL,
  `attachment_name` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");

function inkwell_notes_migration_hint() {
  return "Notes couldn't be saved because the user_notes table doesn't exist on this database yet, and this host isn't letting the app create it automatically. "
       . "Fix: open phpMyAdmin for this database, go to the SQL tab, paste the contents of MIGRATION_ADD_user_notes.sql (or the box below), and click Go.";
}

/** True once we've confirmed user_notes actually exists (cached per request). */
function inkwell_user_notes_table_exists() {
  static $exists = null;
  if ($exists !== null) return $exists;
  try {
    $pdo = inkwell_db();
    $pdo->query('SELECT 1 FROM user_notes LIMIT 1');
    $exists = true;
  } catch (PDOException $e) {
    $exists = false;
  }
  return $exists;
}

/**
 * Self-healing: creates the user_notes table on first use if it doesn't
 * exist yet, so the Notes feature works out of the box on hosts that
 * allow DDL over a normal DB connection. Some free hosts (InfinityFree
 * and similar) block CREATE/ALTER statements from anything but their own
 * phpMyAdmin, in which case this silently no-ops and
 * inkwell_user_notes_table_exists() keeps returning false — the calling
 * code then shows the manual-setup instructions instead of pretending it
 * worked.
 */
function inkwell_ensure_user_notes_table() {
  static $checked = false;
  if ($checked) return;
  $checked = true;
  if (inkwell_user_notes_table_exists()) return;
  try {
    $pdo = inkwell_db();
    $pdo->exec("CREATE TABLE IF NOT EXISTS `user_notes` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `user_id` int(11) NOT NULL,
        `title` varchar(200) NOT NULL DEFAULT 'Untitled note',
        `type` enum('text','code') NOT NULL DEFAULT 'text',
        `content` longtext DEFAULT NULL,
        `font_family` varchar(60) DEFAULT NULL,
        `font_size` int(11) DEFAULT NULL,
        `code_language` varchar(30) DEFAULT NULL,
        `attachment` varchar(255) DEFAULT NULL,
        `attachment_name` varchar(255) DEFAULT NULL,
        `created_at` datetime NOT NULL DEFAULT current_timestamp(),
        `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
        PRIMARY KEY (`id`),
        KEY `user_id` (`user_id`)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    // Force a recheck now that we've (hopefully) just created it.
    $pdo->query('SELECT 1 FROM user_notes LIMIT 1');
  } catch (PDOException $e) {
    // Blocked or failed — inkwell_user_notes_table_exists() will keep
    // reporting false and callers fall back to the manual-setup message.
  }
}

/** Strips anything that could execute script from user-authored note HTML. */
function inkwell_sanitize_note_html($html) {
  $html = (string) $html;
  $html = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $html);
  $html = preg_replace('/<iframe\b[^>]*>.*?<\/iframe>/is', '', $html);
  $html = preg_replace('/\son\w+\s*=\s*"(?:[^"\\\\]|\\\\.)*"/i', '', $html);
  $html = preg_replace("/\son\w+\s*=\s*'(?:[^'\\\\]|\\\\.)*'/i", '', $html);
  $html = preg_replace('/\bjavascript\s*:/i', '', $html);
  return $html;
}

/** All notes for a user, most recently updated first. */
function inkwell_list_user_notes($userId) {
  inkwell_ensure_user_notes_table();
  try {
    $pdo = inkwell_db();
    // Tie-break on id when two notes share the same updated_at second —
    // without it, MySQL can return same-second rows in an arbitrary order,
    // which made a just-created note look like it swapped places/type.
    $stmt = $pdo->prepare('SELECT * FROM user_notes WHERE user_id = ? ORDER BY updated_at DESC, id DESC');
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
  } catch (PDOException $e) {
    return [];
  }
}

/** One note, only if it belongs to this user. */
function inkwell_get_user_note($id, $userId) {
  inkwell_ensure_user_notes_table();
  try {
    $pdo = inkwell_db();
    $stmt = $pdo->prepare('SELECT * FROM user_notes WHERE id = ? AND user_id = ?');
    $stmt->execute([$id, $userId]);
    $row = $stmt->fetch();
    return $row ?: null;
  } catch (PDOException $e) {
    return null;
  }
}

/** Creates a blank note of the given type and returns its new id. */
function inkwell_create_user_note($userId, $type, $title = null) {
  inkwell_ensure_user_notes_table();
  $type = $type === 'code' ? 'code' : 'text';
  if ($title === null) $title = $type === 'code' ? 'Untitled code note' : 'Untitled text note';
  try {
    $pdo = inkwell_db();
    $stmt = $pdo->prepare('INSERT INTO user_notes (user_id, title, type, content, font_family, font_size, code_language) VALUES (?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([
      $userId, $title, $type, '',
      $type === 'text' ? 'Inter, sans-serif' : null,
      $type === 'text' ? 16 : null,
      $type === 'code' ? 'javascript' : null,
    ]);
    return ['ok' => true, 'id' => (int) $pdo->lastInsertId()];
  } catch (PDOException $e) {
    return ['ok' => false, 'error' => inkwell_notes_migration_hint() . ' (' . $e->getMessage() . ')'];
  }
}

/** Saves the title/content/formatting of a note the user owns. */
function inkwell_update_user_note($id, $userId, array $fields) {
  $note = inkwell_get_user_note($id, $userId);
  if (!$note) return ['ok' => false, 'error' => 'Note not found.'];

  $title = trim($fields['title'] ?? $note['title']);
  if ($title === '') $title = 'Untitled note';
  $content = $note['type'] === 'text'
    ? inkwell_sanitize_note_html($fields['content'] ?? $note['content'])
    : (string) ($fields['content'] ?? $note['content']);

  try {
    $pdo = inkwell_db();
    $stmt = $pdo->prepare(
      'UPDATE user_notes SET title = ?, content = ?, font_family = ?, font_size = ?, code_language = ? WHERE id = ? AND user_id = ?'
    );
    $stmt->execute([
      $title,
      $content,
      $note['type'] === 'text' ? ($fields['font_family'] ?? $note['font_family']) : null,
      $note['type'] === 'text' ? (int) ($fields['font_size'] ?? $note['font_size']) : null,
      $note['type'] === 'code' ? ($fields['code_language'] ?? $note['code_language']) : null,
      $id, $userId,
    ]);
    return ['ok' => true];
  } catch (PDOException $e) {
    return ['ok' => false, 'error' => inkwell_notes_migration_hint() . ' (' . $e->getMessage() . ')'];
  }
}

/** Attaches (or replaces) a file on a note the user owns. Any file type, up to 8MB. */
function inkwell_attach_user_note_file($id, $userId, $fileField = 'attachment') {
  $note = inkwell_get_user_note($id, $userId);
  if (!$note) return ['ok' => false, 'error' => 'Note not found.'];
  if (empty($_FILES[$fileField]['name'])) return ['ok' => true]; // nothing chosen, not an error

  $err = $_FILES[$fileField]['error'];
  if ($err !== UPLOAD_ERR_OK) {
    $messages = [
      UPLOAD_ERR_INI_SIZE => 'That file is too large for this server\'s upload limit.',
      UPLOAD_ERR_FORM_SIZE => 'That file is too large.',
      UPLOAD_ERR_PARTIAL => 'The upload was interrupted — please try again.',
    ];
    return ['ok' => false, 'error' => $messages[$err] ?? 'Upload failed (error code ' . $err . ').'];
  }
  if ($_FILES[$fileField]['size'] > 8 * 1024 * 1024) {
    return ['ok' => false, 'error' => 'File must be under 8MB.'];
  }

  $origName = $_FILES[$fileField]['name'];
  $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
  $ext = preg_replace('/[^a-z0-9]/', '', $ext) ?: 'bin';
  if (!is_dir(INKWELL_UPLOADS_DIR)) @mkdir(INKWELL_UPLOADS_DIR, 0775, true);
  $filename = 'note_' . bin2hex(random_bytes(6)) . '.' . $ext;
  if (!move_uploaded_file($_FILES[$fileField]['tmp_name'], INKWELL_UPLOADS_DIR . '/' . $filename)) {
    return ['ok' => false, 'error' => 'Could not save the uploaded file — check that assets/uploads/ is writable.'];
  }

  try {
    $pdo = inkwell_db();
    if (!empty($note['attachment'])) inkwell_delete_upload($note['attachment']);
    $stmt = $pdo->prepare('UPDATE user_notes SET attachment = ?, attachment_name = ? WHERE id = ? AND user_id = ?');
    $stmt->execute([$filename, $origName, $id, $userId]);
    return ['ok' => true];
  } catch (PDOException $e) {
    return ['ok' => false, 'error' => inkwell_notes_migration_hint() . ' (' . $e->getMessage() . ')'];
  }
}

function inkwell_remove_user_note_attachment($id, $userId) {
  $note = inkwell_get_user_note($id, $userId);
  if (!$note) return;
  if (!empty($note['attachment'])) inkwell_delete_upload($note['attachment']);
  try {
    $pdo = inkwell_db();
    $stmt = $pdo->prepare('UPDATE user_notes SET attachment = NULL, attachment_name = NULL WHERE id = ? AND user_id = ?');
    $stmt->execute([$id, $userId]);
  } catch (PDOException $e) {}
}

/** Renders one sidebar list-item link for a note. $isActive controls the highlighted state. */
function inkwell_render_note_list_item($note, $activeId) {
  $isActive = (int) $note['id'] === (int) $activeId;
  ob_start();
  ?>
  <a class="notes-list-item<?php echo $isActive ? ' active' : ''; ?>" href="/notes.php?id=<?php echo (int) $note['id']; ?>" data-note-link data-note-id="<?php echo (int) $note['id']; ?>">
    <?php echo $note['type'] === 'code' ? '💻 ' : '📝 '; ?><?php echo htmlspecialchars($note['title']); ?>
    <span class="note-type-badge note-type-<?php echo $note['type']; ?>"><?php echo $note['type'] === 'code' ? 'CODE' : 'TEXT'; ?></span>
    <small><?php echo htmlspecialchars(date('M j, g:ia', strtotime($note['updated_at']))); ?></small>
  </a>
  <?php
  return ob_get_clean();
}

/** Renders the attachment box for the currently-open note. */
function inkwell_render_note_attachment($note) {
  ob_start();
  ?>
  <div class="admin-card" style="margin-top:16px;" id="noteAttachmentBox">
    <h3 style="margin-top:0;">Attachment</h3>
    <?php if (!empty($note['attachment'])): ?>
      <p class="admin-sub">
        📎 <a href="/assets/uploads/<?php echo htmlspecialchars($note['attachment']); ?>" target="_blank" rel="noopener"><?php echo htmlspecialchars($note['attachment_name'] ?: 'Attached file'); ?></a>
      </p>
      <form data-attachment-remove-form data-note-id="<?php echo (int) $note['id']; ?>">
        <input type="hidden" name="action" value="remove_attachment">
        <input type="hidden" name="note_id" value="<?php echo (int) $note['id']; ?>">
        <button type="submit" class="btn">Remove attachment</button>
      </form>
    <?php else: ?>
      <form data-attachment-upload-form data-note-id="<?php echo (int) $note['id']; ?>" enctype="multipart/form-data" class="admin-form" style="flex-direction:row; gap:8px;">
        <input type="hidden" name="action" value="attach_file">
        <input type="hidden" name="note_id" value="<?php echo (int) $note['id']; ?>">
        <input type="file" name="attachment" style="flex:1;" required>
        <button type="submit" class="btn">Attach file</button>
      </form>
      <p class="admin-sub">Any file type, up to 8MB.</p>
    <?php endif; ?>
  </div>
  <?php
  return ob_get_clean();
}

/** Renders the whole right-hand editor panel (title/toolbar/body + attachment box) for one note. */
function inkwell_render_note_editor($note) {
  ob_start();
  ?>
  <form action="/notes.php?id=<?php echo (int) $note['id']; ?>" class="admin-form" id="noteForm" data-note-id="<?php echo (int) $note['id']; ?>">
    <input type="hidden" name="action" value="save_note">
    <input type="hidden" name="note_id" value="<?php echo (int) $note['id']; ?>">
    <div class="admin-header-row" style="margin-bottom:0;">
      <input type="text" name="title" value="<?php echo htmlspecialchars($note['title']); ?>" maxlength="200"
        style="font-size:1.3rem; font-weight:700; border:none; background:transparent; flex:1; padding:6px 0;" placeholder="Untitled note">
      <button type="button" class="btn danger" data-note-delete data-note-id="<?php echo (int) $note['id']; ?>">Delete</button>
    </div>

    <?php if ($note['type'] === 'text'): ?>
      <input type="hidden" name="content" id="noteContentInput">
      <div class="note-toolbar">
        <button type="button" data-cmd="bold"><strong>B</strong></button>
        <button type="button" data-cmd="italic"><em>I</em></button>
        <button type="button" data-cmd="underline"><u>U</u></button>
        <button type="button" data-cmd="insertUnorderedList">• List</button>
        <button type="button" data-cmd="insertOrderedList">1. List</button>
        <select id="noteFontFamily" name="font_family">
          <?php foreach (['Inter, sans-serif' => 'Inter', 'Georgia, serif' => 'Georgia', "'JetBrains Mono', monospace" => 'Monospace', 'Arial, sans-serif' => 'Arial', "'Comic Sans MS', cursive" => 'Comic Sans'] as $val => $label): ?>
            <option value="<?php echo htmlspecialchars($val); ?>" <?php echo ($note['font_family'] ?? '') === $val ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
          <?php endforeach; ?>
        </select>
        <select id="noteFontSize" name="font_size">
          <?php foreach ([12, 14, 16, 18, 22, 28] as $sz): ?>
            <option value="<?php echo $sz; ?>" <?php echo (int) ($note['font_size'] ?? 16) === $sz ? 'selected' : ''; ?>><?php echo $sz; ?>px</option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="note-editable" id="noteEditable" contenteditable="true" data-placeholder="Start writing…"
        style="font-family: <?php echo htmlspecialchars($note['font_family'] ?: 'Inter, sans-serif'); ?>; font-size: <?php echo (int) ($note['font_size'] ?: 16); ?>px;"
      ><?php echo $note['content']; ?></div>
    <?php else: ?>
      <div class="note-toolbar">
        <label style="display:flex; align-items:center; gap:6px; font-size:0.85rem;">Language
          <select name="code_language">
            <?php foreach (['javascript', 'php', 'python', 'java', 'c', 'cpp', 'csharp', 'html', 'css', 'sql', 'plaintext'] as $lang): ?>
              <option value="<?php echo $lang; ?>" <?php echo ($note['code_language'] ?? '') === $lang ? 'selected' : ''; ?>><?php echo htmlspecialchars($lang); ?></option>
            <?php endforeach; ?>
          </select>
        </label>
      </div>
      <textarea class="note-code-area" name="content" id="noteCodeArea" spellcheck="false"><?php echo htmlspecialchars($note['content']); ?></textarea>
    <?php endif; ?>

    <div class="note-save-row" style="display:flex; align-items:center; gap:10px; margin-top:14px;">
      <button class="btn primary" type="submit">Save note</button>
      <span class="note-save-status" id="noteSaveStatus" style="font-size:0.82rem; color:var(--ink-dim);"></span>
    </div>
  </form>

  <?php echo inkwell_render_note_attachment($note); ?>
  <?php
  return ob_get_clean();
}

function inkwell_delete_user_note($id, $userId) {
  $note = inkwell_get_user_note($id, $userId);
  if (!$note) return;
  if (!empty($note['attachment'])) inkwell_delete_upload($note['attachment']);
  try {
    $pdo = inkwell_db();
    $stmt = $pdo->prepare('DELETE FROM user_notes WHERE id = ? AND user_id = ?');
    $stmt->execute([$id, $userId]);
  } catch (PDOException $e) {}
}
