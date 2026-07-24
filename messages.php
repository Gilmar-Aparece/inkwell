<?php
// Inside/dashboard page — suppress the marketing topbar (see includes/header.php).
$__hideTopbar = true;
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/store.php'; // inkwell_is_ajax(), inkwell_json_response(), inkwell_post_too_large()
require_once __DIR__ . '/includes/messages.php';

$user = inkwell_require_login();
$ajax = inkwell_is_ajax();

// AJAX responses must be pure JSON. If anything upstream (a notice, a
// warning, stray whitespace) prints before we get here, it corrupts the
// JSON and the browser's fetch().json() call throws — which surfaces to
// the person as a generic "Network error, please try again" alert when
// they click a conversation, even though the request actually succeeded
// server-side. Buffering here and wiping the buffer right before each
// JSON reply keeps that stray output from ever reaching the response body.
if ($ajax) ob_start();
function inkwell_messages_ajax_json($data) {
  if (ob_get_level() > 0) ob_clean();
  inkwell_json_response($data);
}

// Safety net: a *fatal* PHP Error (calling an undefined function, a
// version-incompatible builtin, etc.) is NOT an Exception, so a normal
// try/catch(Exception) doesn't stop it from corrupting the AJAX response
// — that's exactly how the old array_key_first() crash surfaced as a
// generic "Network error" alert on hosts running an older PHP version.
// register_shutdown_function() runs even after a fatal error, so this
// makes sure an AJAX request always ends in valid JSON no matter what
// breaks inside it, today or in some future change.
if ($ajax) {
  register_shutdown_function(function () {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
      if (ob_get_level() > 0) ob_clean();
      http_response_code(200);
      header('Content-Type: application/json');
      echo json_encode(['ok' => false, 'error' => 'Something went wrong on our end — please try again.']);
    }
  });
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Second layer of the same safety net: catches any Throwable (Error or
  // Exception) raised anywhere in the action handlers below, so one bad
  // action can never fall through and print a raw PHP warning/error into
  // what's supposed to be a pure JSON response.
  try {

  if (inkwell_post_too_large()) {
    $msg = inkwell_post_too_large_message();
    if ($ajax) inkwell_messages_ajax_json(['ok' => false, 'error' => $msg]);
    inkwell_flash_set('error', $msg);
    header('Location: /messages.php');
    exit;
  }

  $action = $_POST['action'] ?? '';

  if ($action === 'send_message') {
    $toId = (int) ($_POST['to'] ?? 0);
    $attachmentItems = inkwell_normalize_message_files('attachments');
    $result = inkwell_send_message($user['id'], $toId, $_POST['body'] ?? '', $attachmentItems);
    if ($ajax) {
      if ($result['ok']) {
        $messages = inkwell_list_thread_messages($user['id'], $toId);
        $last = end($messages);
        inkwell_messages_ajax_json([
          'ok' => true,
          'bubble_html' => inkwell_render_message_bubble($last, $user['id']),
        ]);
      }
      inkwell_messages_ajax_json($result);
    }
    if (!$result['ok']) inkwell_flash_set('error', $result['error']);
    header('Location: /messages.php?with=' . $toId);
    exit;
  }

  if ($action === 'search_users') {
    $results = inkwell_search_messageable_users($_POST['q'] ?? '', $user['id']);
    $html = '';
    foreach ($results as $u) $html .= inkwell_render_new_convo_result($u);
    if (!$results) $html = '<p class="admin-sub" style="padding:10px 4px;">No users found.</p>';
    inkwell_messages_ajax_json(['ok' => true, 'html' => $html]);
  }

  if ($action === 'load_thread') {
    $withId = (int) ($_POST['with'] ?? 0);
    $other = inkwell_get_messageable_user($withId);
    if (!$other) inkwell_messages_ajax_json(['ok' => false, 'error' => 'That user is no longer available.']);
    inkwell_mark_thread_read($user['id'], $withId);
    $messages = inkwell_list_thread_messages($user['id'], $withId);
    inkwell_messages_ajax_json([
      'ok' => true,
      'thread_html' => inkwell_render_message_thread($messages, $user['id']),
      'header_name' => $other['name'],
      'header_role' => ucfirst($other['role']),
      'header_avatar' => inkwell_message_avatar_html($other),
      'archived' => inkwell_is_conversation_archived($user['id'], $withId),
    ]);
  }

  if ($action === 'list_conversations') {
    $archived = !empty($_POST['archived']);
    $convos = inkwell_list_conversations($user['id'], $archived);
    $activeId = (int) ($_POST['active'] ?? 0);
    $html = '';
    if (!$convos) {
      $html = $archived
        ? '<p class="msg-convo-empty">No archived conversations.</p>'
        : '<p class="msg-convo-empty">No conversations yet. Search above to message someone.</p>';
    } else {
      foreach ($convos as $c) $html .= inkwell_render_conversation_item($c, $activeId, $archived);
    }
    inkwell_messages_ajax_json(['ok' => true, 'html' => $html]);
  }

  if ($action === 'archive_conversation') {
    $withId = (int) ($_POST['with'] ?? 0);
    inkwell_archive_conversation($user['id'], $withId);
    inkwell_messages_ajax_json(['ok' => true]);
  }

  if ($action === 'unarchive_conversation') {
    $withId = (int) ($_POST['with'] ?? 0);
    inkwell_unarchive_conversation($user['id'], $withId);
    inkwell_messages_ajax_json(['ok' => true]);
  }

  if ($action === 'poll_thread') {
    // Lightweight refresh used while a thread is open, so a reply shows
    // up without the user having to reload the page.
    $withId = (int) ($_POST['with'] ?? 0);
    $messages = inkwell_list_thread_messages($user['id'], $withId);
    inkwell_mark_thread_read($user['id'], $withId);
    inkwell_messages_ajax_json([
      'ok' => true,
      'thread_html' => inkwell_render_message_thread($messages, $user['id']),
      'count' => count($messages),
    ]);
  }

  } catch (Throwable $e) {
    if ($ajax) {
      inkwell_messages_ajax_json(['ok' => false, 'error' => 'Something went wrong — please try again.']);
    }
    inkwell_flash_set('error', 'Something went wrong — please try again.');
    header('Location: /messages.php');
    exit;
  }
}

$flash = inkwell_flash_get();
$notice = $flash && $flash['type'] === 'notice' ? $flash['message'] : '';
$error = $flash && $flash['type'] === 'error' ? $flash['message'] : '';

$conversations = inkwell_list_conversations($user['id'], false);
$withId = (int) ($_GET['with'] ?? 0);
$activePartner = $withId ? inkwell_get_messageable_user($withId) : null;
if ($withId && $activePartner) {
  inkwell_mark_thread_read($user['id'], $withId);
  $threadMessages = inkwell_list_thread_messages($user['id'], $withId);
} else {
  $withId = 0;
  $threadMessages = [];
}

$pageTitle = 'Messages';
include __DIR__ . '/includes/header.php';
$driveActive = 'messages';
$driveCrumbs = [['label' => 'Home', 'href' => '/index.php'], ['label' => 'Messages']];
$driveTitle = 'Messages';
$driveSubtitle = 'Direct-message any student, teacher, dean, registrar, or admin on Inkwell.';
// Edge-to-edge on phones (same opt-in the Community feed uses) so the
// chat shell gets the full screen instead of floating as a padded card —
// matches the full-bleed structure of a native messaging app.
$driveFullBleedMobile = true;
include __DIR__ . '/includes/drive_shell_top.php';
?>
<style>
  .msg-shell {
    --accent: var(--nib);
    --accent-dark: var(--nib);
    --accent-glow: var(--nib-glow);
    --accent-tint: color-mix(in srgb, var(--nib) 10%, transparent);
    --accent-tint-strong: color-mix(in srgb, var(--nib) 16%, transparent);
    display: grid; grid-template-columns: 320px 1fr; gap: 18px; align-items: stretch; height: calc(100vh - 230px); min-height: 480px;
  }
  /* Breakpoint matches the drive-shell's own mobile switch (860px, see
     style.css .drive-sidebar) — this used to be 900px, a 40px mismatch
     that made the sidebar/search area render mid-transition and overlap
     itself on tablet-width screens. */
  @media (max-width: 860px) {
    .msg-shell { grid-template-columns: 1fr; height: calc(100dvh - 130px); min-height: 420px; position: relative; overflow: hidden; }
    .msg-sidebar, .msg-thread-panel { border-radius: 0; border-left: none; border-right: none; position: absolute; inset: 0; transition: transform 0.22s ease; }
    .msg-thread-panel { transform: translateX(100%); }
    .msg-shell.show-thread .msg-sidebar { transform: translateX(-100%); }
    .msg-shell.show-thread .msg-thread-panel { transform: translateX(0); }
    .msg-thread-back { display: inline-flex !important; }
  }

  .msg-sidebar, .msg-thread-panel { border-radius: 22px; }

  /* ---------- Sidebar ---------- */
  .msg-sidebar { display: flex; flex-direction: column; overflow: hidden; padding: 0; }
  .msg-sidebar-head { padding: 18px 18px 14px; border-bottom: 1px solid var(--border-soft); flex-shrink: 0; }
  .msg-sidebar-head h2 { margin: 0 0 14px; font-size: 1.15rem; font-weight: 800; }

  .msg-tabs { display: flex; gap: 4px; padding: 4px; background: var(--surface-2); border-radius: 999px; margin-bottom: 12px; }
  .msg-tab { flex: 1; border: none; background: none; padding: 7px 10px; border-radius: 999px; font: inherit; font-size: 0.82rem; font-weight: 700; color: var(--ink-dim); cursor: pointer; transition: background .15s ease, color .15s ease; }
  .msg-tab.active { background: var(--accent); color: #fff; box-shadow: 0 4px 10px color-mix(in srgb, var(--nib) 35%, transparent); }

  /* Search box + its results dropdown. NOTE: this dropdown previously used
     background: var(--surface-1) — a CSS variable that is never defined
     anywhere in style.css (only --surface / --surface-2 exist). That left
     the dropdown effectively transparent, so it rendered directly on top
     of the conversation list below it with no visual separation — the
     "garbled overlapping text" look. Fixed to use the real --surface /
     --border tokens (same ones .admin-card uses), plus an explicit
     isolate/z-index stack so it reliably paints above the list. */
  .msg-search-wrap { position: relative; isolation: isolate; z-index: 5; }
  .msg-search-wrap svg { position: absolute; left: 13px; top: 50%; transform: translateY(-50%); width: 16px; height: 16px; color: var(--ink-dim); pointer-events: none; }
  .msg-search-wrap input { width: 100%; padding: 10px 12px 10px 36px; border: 1px solid transparent; border-radius: 999px; background: var(--surface-2); font: inherit; font-size: 0.85rem; color: inherit; }
  .msg-search-wrap input:focus { outline: none; border-color: var(--accent); background: var(--surface); }
  .msg-search-results {
    position: absolute; top: calc(100% + 6px); left: 0; right: 0;
    background: var(--surface); border: 1px solid var(--border);
    border-radius: 16px; box-shadow: var(--shadow, 0 10px 26px rgba(0,0,0,0.35));
    max-height: 320px; overflow-y: auto; z-index: 30; display: none;
  }
  .msg-search-results.open { display: block; }
  .msg-search-results.loading { display: block; }
  .msg-search-result { display: flex; align-items: center; gap: 10px; width: 100%; text-align: left; padding: 9px 12px; border: none; background: none; cursor: pointer; font: inherit; color: inherit; }
  .msg-search-result:hover { background: var(--accent-tint); }

  .msg-convo-list { flex: 1; overflow-y: auto; padding: 8px; }
  .msg-convo-item { display: flex; align-items: center; gap: 2px; padding: 4px; border-radius: 16px; position: relative; margin-bottom: 2px; }
  .msg-convo-item:hover { background: var(--accent-tint); }
  .msg-convo-item.active { background: var(--accent-tint-strong); }
  .msg-convo-link { display: flex; align-items: center; gap: 11px; flex: 1; min-width: 0; padding: 6px; border-radius: 14px; text-decoration: none; color: inherit; }
  .msg-convo-avatar { width: 44px; height: 44px; flex-shrink: 0; border-radius: 50%; background: linear-gradient(135deg, var(--accent), var(--accent-glow)); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 700; overflow: hidden; }
  .msg-convo-avatar img { width: 100%; height: 100%; object-fit: cover; }
  .msg-convo-body { min-width: 0; flex: 1; display: flex; flex-direction: column; gap: 2px; }
  .msg-convo-top { display: flex; justify-content: space-between; gap: 6px; align-items: baseline; }
  .msg-convo-top strong { font-size: 0.9rem; font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .msg-convo-time { font-size: 0.7rem; color: var(--ink-dim); white-space: nowrap; }
  .msg-convo-preview { font-size: 0.8rem; color: var(--ink-dim); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .msg-convo-item.has-unread .msg-convo-preview { color: var(--ink); font-weight: 600; }
  .msg-convo-badge { flex-shrink: 0; min-width: 9px; height: 9px; width: 9px; border-radius: 999px; background: var(--accent); }
  .msg-convo-empty { padding: 24px 14px; text-align: center; color: var(--ink-dim); font-size: 0.9rem; }

  /* ---------- Per-conversation "…" archive menu ---------- */
  .msg-convo-menu-wrap { position: relative; flex-shrink: 0; }
  .msg-convo-menu-btn {
    width: 30px; height: 30px; flex-shrink: 0; border-radius: 50%; border: none; background: none;
    color: var(--ink-dim); display: flex; align-items: center; justify-content: center; cursor: pointer;
    opacity: 0; transition: opacity .12s ease, background .12s ease, color .12s ease;
  }
  .msg-convo-menu-btn svg { width: 16px; height: 16px; }
  .msg-convo-item:hover .msg-convo-menu-btn, .msg-convo-menu-btn.active { opacity: 1; }
  .msg-convo-menu-btn:hover, .msg-convo-menu-btn.active { background: var(--surface-2); color: var(--accent); }
  .msg-convo-menu {
    display: none; position: absolute; top: calc(100% + 4px); right: 0; z-index: 10;
    background: var(--surface); border: 1px solid var(--border); border-radius: 12px;
    box-shadow: var(--shadow, 0 10px 26px rgba(0,0,0,0.35)); padding: 6px; min-width: 176px;
  }
  .msg-convo-menu.open { display: block; }
  .msg-convo-menu button { display: block; width: 100%; padding: 8px 10px; border: none; background: none; border-radius: 9px; font: inherit; font-size: 0.83rem; font-weight: 600; color: inherit; cursor: pointer; text-align: left; }
  .msg-convo-menu button:hover { background: var(--accent-tint); color: var(--accent); }

  /* ---------- Thread panel ---------- */
  .msg-thread-panel { display: flex; flex-direction: column; padding: 0; overflow: hidden; }
  .msg-thread-head { display: flex; align-items: center; gap: 12px; padding: 16px 20px; border-bottom: 1px solid var(--border-soft); flex-shrink: 0; }
  .msg-thread-back { display: none; align-items: center; justify-content: center; width: 34px; height: 34px; flex-shrink: 0; border-radius: 50%; border: none; background: var(--surface-2); color: var(--ink); cursor: pointer; }
  .msg-thread-back svg { width: 18px; height: 18px; }
  .msg-thread-head .msg-convo-avatar { width: 42px; height: 42px; }
  .msg-thread-head-info { flex: 1; min-width: 0; }
  .msg-thread-head-info strong { display: block; font-size: 0.98rem; font-weight: 800; }
  .msg-thread-role-badge { display: inline-flex; align-items: center; gap: 5px; margin-top: 3px; padding: 2px 10px; border-radius: 999px; background: var(--accent-tint); color: var(--accent-dark); font-size: 0.72rem; font-weight: 700; }
  .msg-thread-role-badge svg { width: 12px; height: 12px; }
  .msg-thread-head-action-wrap { position: relative; flex-shrink: 0; }
  .msg-thread-head-action { width: 36px; height: 36px; border-radius: 50%; border: none; background: var(--surface-2); color: var(--ink-dim); display: flex; align-items: center; justify-content: center; cursor: pointer; flex-shrink: 0; }
  .msg-thread-head-action:hover, .msg-thread-head-action.active { background: var(--accent-tint); color: var(--accent); }
  .msg-thread-head-menu {
    display: none; position: absolute; top: calc(100% + 6px); right: 0; z-index: 10;
    background: var(--surface); border: 1px solid var(--border); border-radius: 12px;
    box-shadow: var(--shadow, 0 10px 26px rgba(0,0,0,0.35)); padding: 6px; min-width: 190px;
  }
  .msg-thread-head-menu.open { display: block; }
  .msg-thread-head-menu button { display: block; width: 100%; padding: 8px 10px; border: none; background: none; border-radius: 9px; font: inherit; font-size: 0.83rem; font-weight: 600; color: inherit; cursor: pointer; text-align: left; }
  .msg-thread-head-menu button:hover { background: var(--accent-tint); color: var(--accent); }

  .msg-thread-body { flex: 1; overflow-y: auto; padding: 22px; display: flex; flex-direction: column; gap: 14px; }
  .msg-thread-empty { text-align: center; margin-top: 40px; }
  .msg-bubble-row { display: flex; flex-direction: column; max-width: 68%; gap: 6px; }
  .msg-bubble-row.mine { align-self: flex-end; align-items: flex-end; }
  .msg-bubble {
    padding: 10px 15px; border-radius: 18px; background: var(--surface-2);
    box-shadow: 0 1px 2px rgba(20,20,30,0.05);
    word-wrap: break-word; white-space: pre-wrap; font-size: 0.9rem; line-height: 1.4;
    border-bottom-left-radius: 4px;
  }
  .msg-bubble-row.mine .msg-bubble {
    background: linear-gradient(135deg, var(--accent), var(--accent-dark));
    color: #fff;
    border-bottom-left-radius: 18px;
    border-bottom-right-radius: 4px;
  }
  .msg-bubble-time { font-size: 0.68rem; color: var(--ink-dim); margin-top: -2px; padding: 0 4px; }

  /* ---------- Attachments (bubbles) ---------- */
  .msg-att-image-grid { display: grid; gap: 4px; border-radius: 16px; overflow: hidden; max-width: 260px; }
  .msg-att-image-grid-1 { grid-template-columns: 1fr; }
  .msg-att-image-grid-2, .msg-att-image-grid-4 { grid-template-columns: 1fr 1fr; }
  .msg-att-image-grid-3 { grid-template-columns: 1fr 1fr; }
  .msg-att-image-grid-3 .msg-att-image:first-child { grid-column: 1 / -1; }
  .msg-att-image { display: block; background: var(--surface-2); }
  .msg-att-image img { width: 100%; height: 100%; max-height: 220px; object-fit: cover; display: block; }
  .msg-att-image-grid-1 img { max-height: 280px; }

  .msg-att-file-list { display: flex; flex-direction: column; gap: 6px; max-width: 260px; }
  .msg-att-file {
    display: flex; align-items: center; gap: 10px; padding: 9px 11px; border-radius: 14px;
    background: var(--surface-2); border: 1px solid var(--border-soft); text-decoration: none; color: inherit;
    transition: background .15s ease;
  }
  .msg-att-file:hover { background: var(--accent-tint); }
  .msg-bubble-row.mine .msg-att-file { background: color-mix(in srgb, var(--accent) 14%, var(--surface-2)); }
  .msg-att-file-icon { width: 34px; height: 34px; border-radius: 10px; flex-shrink: 0; background: var(--accent-tint); color: var(--accent-dark); display: flex; align-items: center; justify-content: center; }
  .msg-att-file-icon svg { width: 17px; height: 17px; }
  .msg-att-file-info { min-width: 0; flex: 1; display: flex; flex-direction: column; gap: 1px; }
  .msg-att-file-name { font-size: 0.82rem; font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .msg-att-file-meta { font-size: 0.7rem; color: var(--ink-dim); }
  .msg-att-file-dl { flex-shrink: 0; color: var(--ink-dim); display: flex; }
  .msg-att-file-dl svg { width: 15px; height: 15px; }

  /* ---------- Composer ---------- */
  .msg-composer { display: flex; flex-direction: column; gap: 8px; padding: 14px 18px; border-top: 1px solid var(--border-soft); flex-shrink: 0; }
  .msg-composer-row { display: flex; align-items: center; gap: 10px; }
  .msg-composer-attach-wrap { position: relative; flex-shrink: 0; }
  .msg-composer-attach { width: 38px; height: 38px; flex-shrink: 0; border-radius: 50%; border: none; background: var(--surface-2); color: var(--ink-dim); display: flex; align-items: center; justify-content: center; cursor: pointer; }
  .msg-composer-attach:hover, .msg-composer-attach.active { background: var(--accent-tint); color: var(--accent); }
  .msg-composer-attach-menu {
    display: none; position: absolute; bottom: calc(100% + 8px); left: 0; z-index: 10;
    background: var(--surface); border: 1px solid var(--border); border-radius: 14px;
    box-shadow: var(--shadow, 0 10px 26px rgba(0,0,0,0.35)); padding: 6px; min-width: 168px;
  }
  .msg-composer-attach-menu.open { display: block; }
  .msg-composer-attach-menu button { display: flex; align-items: center; gap: 9px; width: 100%; padding: 9px 10px; border: none; background: none; border-radius: 10px; font: inherit; font-size: 0.85rem; font-weight: 600; color: inherit; cursor: pointer; text-align: left; }
  .msg-composer-attach-menu button:hover { background: var(--accent-tint); color: var(--accent); }
  .msg-composer-attach-menu svg { width: 16px; height: 16px; flex-shrink: 0; }
  .msg-composer textarea {
    flex: 1; resize: none; min-height: 42px; max-height: 140px; padding: 11px 16px;
    border: 1px solid transparent; border-radius: 999px; font: inherit; font-size: 0.88rem;
    background: var(--surface-2); color: inherit;
  }
  .msg-composer textarea:focus { outline: none; border-color: var(--accent); background: var(--surface); }
  .msg-composer button.btn.primary {
    width: 42px; height: 42px; padding: 0; flex-shrink: 0; border-radius: 50%; border: none;
    background: linear-gradient(135deg, var(--accent), var(--accent-dark)); color: #fff;
    display: flex; align-items: center; justify-content: center; cursor: pointer;
    box-shadow: 0 6px 14px color-mix(in srgb, var(--nib) 35%, transparent);
  }
  .msg-composer button.btn.primary:disabled { opacity: 0.6; cursor: default; }
  .msg-composer button.btn.primary svg { width: 18px; height: 18px; }

  /* Pending-attachment preview chips shown above the input before sending */
  .msg-pending-atts { display: none; flex-wrap: wrap; gap: 8px; padding: 0 2px; }
  .msg-pending-atts.has-items { display: flex; }
  .msg-pending-chip { position: relative; display: flex; align-items: center; gap: 7px; padding: 6px 10px 6px 6px; border-radius: 12px; background: var(--surface-2); border: 1px solid var(--border-soft); font-size: 0.76rem; font-weight: 600; max-width: 160px; }
  .msg-pending-chip img { width: 30px; height: 30px; border-radius: 8px; object-fit: cover; flex-shrink: 0; }
  .msg-pending-chip-icon { width: 30px; height: 30px; border-radius: 8px; background: var(--accent-tint); color: var(--accent-dark); display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
  .msg-pending-chip-icon svg { width: 15px; height: 15px; }
  .msg-pending-chip-name { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .msg-pending-chip-remove { border: none; background: rgba(0,0,0,0.55); color: #fff; width: 17px; height: 17px; border-radius: 50%; position: absolute; top: -5px; right: -5px; display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 11px; line-height: 1; padding: 0; }

  .msg-no-thread { display: flex; align-items: center; justify-content: center; flex: 1; color: var(--ink-dim); text-align: center; padding: 30px; }
</style>

<?php if ($notice): ?><div class="exam-result pass" style="margin-bottom:12px;"><?php echo htmlspecialchars($notice); ?></div><?php endif; ?>
<?php if ($error): ?><div class="exam-result fail" style="margin-bottom:12px;"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
<?php if ($notice === '' && $error === '' && !inkwell_messages_table_exists()): ?>
  <div class="admin-sub" style="margin-bottom:12px;">
    <p>This host isn't letting the app create the messages table itself. One-time fix: open <strong>phpMyAdmin</strong> for this database → your database → the <strong>SQL</strong> tab → paste this → <strong>Go</strong>. Only needs to be done once, ever.</p>
    <textarea readonly onclick="this.select();" style="width:100%; min-height:180px; font-family:var(--mono); font-size:0.78rem; padding:10px;"><?php echo htmlspecialchars(INKWELL_MESSAGES_SQL); ?></textarea>
  </div>
<?php endif; ?>

<div class="msg-shell<?php echo $withId ? ' show-thread' : ''; ?>" id="msgShell">
  <section class="admin-card glass-card msg-sidebar">
    <div class="msg-sidebar-head">
      <h2>Messages</h2>
      <div class="msg-tabs" id="msgTabs">
        <button type="button" class="msg-tab active" data-tab="general">General</button>
        <button type="button" class="msg-tab" data-tab="archive">Archive</button>
      </div>
      <div class="msg-search-wrap">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <input type="text" id="msgUserSearch" placeholder="Message someone new…" autocomplete="off">
        <div class="msg-search-results" id="msgSearchResults"></div>
      </div>
    </div>
    <div class="msg-convo-list" id="msgConvoList">
      <?php if (!$conversations): ?>
        <p class="msg-convo-empty">No conversations yet. Search above to message someone.</p>
      <?php else: ?>
        <?php foreach ($conversations as $c) echo inkwell_render_conversation_item($c, $withId, false); ?>
      <?php endif; ?>
    </div>
    <p class="msg-convo-empty" id="msgArchiveEmpty" style="display:none;">No archived conversations.</p>
  </section>

  <section class="admin-card glass-card msg-thread-panel">
    <?php if (!$activePartner): ?>
      <div class="msg-no-thread">Select a conversation on the left, or search for someone to start a new one.</div>
    <?php else: ?>
      <div class="msg-thread-head" id="msgThreadHead">
        <button type="button" class="msg-thread-back" id="msgThreadBackBtn" aria-label="Back to conversations">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
        </button>
        <span class="msg-convo-avatar"><?php echo inkwell_message_avatar_html($activePartner); ?></span>
        <div class="msg-thread-head-info">
          <strong id="msgThreadName"><?php echo htmlspecialchars($activePartner['name']); ?></strong>
          <span class="msg-thread-role-badge">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            <span id="msgThreadRole"><?php echo htmlspecialchars(ucfirst($activePartner['role'])); ?></span>
          </span>
        </div>
        <div class="msg-thread-head-action-wrap">
          <button type="button" class="msg-thread-head-action" id="msgThreadMoreBtn" aria-label="More options">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="5" r="1.5"/><circle cx="12" cy="12" r="1.5"/><circle cx="12" cy="19" r="1.5"/></svg>
          </button>
          <div class="msg-thread-head-menu" id="msgThreadMoreMenu">
            <?php $__isArchived = inkwell_is_conversation_archived($user['id'], $withId); ?>
            <button type="button" id="msgThreadArchiveBtn" data-archived="<?php echo $__isArchived ? '1' : '0'; ?>">
              <?php echo $__isArchived ? 'Unarchive conversation' : 'Archive conversation'; ?>
            </button>
          </div>
        </div>
      </div>
      <div class="msg-thread-body" id="msgThreadBody"><?php echo inkwell_render_message_thread($threadMessages, $user['id']); ?></div>
      <?php include __DIR__ . '/includes/messages_composer.php'; ?>
    <?php endif; ?>
  </section>
</div>

<script>
(function () {
  var meId = <?php echo (int) $user['id']; ?>;
  var msgShell = document.getElementById('msgShell');
  var convoList = document.getElementById('msgConvoList');
  var threadPanel = document.querySelector('.msg-thread-panel');
  var searchInput = document.getElementById('msgUserSearch');
  var searchResults = document.getElementById('msgSearchResults');
  var pollTimer = null;
  var searchTimer = null;
  var searchSeq = 0; // guards against a slower earlier response overwriting a faster later one
  var pendingFiles = []; // File objects queued for the next send
  var currentTab = 'general'; // 'general' | 'archive' — which list the sidebar is showing

  var MAX_ATTACHMENTS = <?php echo (int) INKWELL_MESSAGE_ATTACHMENT_MAX_COUNT; ?>;

  // Finds the JSON object our PHP printed, ignoring anything appended
  // after it (see postAjax below). Scans brace depth rather than just
  // taking the last "}" in the text, since a chat message can itself
  // contain literal braces.
  function extractJsonObject(text) {
    var start = text.indexOf('{');
    if (start === -1) return null;
    var depth = 0, inStr = false, esc = false;
    for (var i = start; i < text.length; i++) {
      var ch = text[i];
      if (inStr) {
        if (esc) { esc = false; }
        else if (ch === '\\') { esc = true; }
        else if (ch === '"') { inStr = false; }
        continue;
      }
      if (ch === '"') { inStr = true; continue; }
      if (ch === '{') depth++;
      else if (ch === '}') {
        depth--;
        if (depth === 0) return text.slice(start, i + 1);
      }
    }
    return null;
  }

  // Accepts either plain values or File/FileList/Array<File> values, so
  // the same helper works for text-only actions and the send-with-attachments case.
  function postAjax(fields) {
    var body = new FormData();
    Object.keys(fields).forEach(function (k) {
      var v = fields[k];
      if (Array.isArray(v)) {
        // PHP only keeps the LAST value when multiple form fields share one
        // exact name — it needs the classic name[] suffix to collect them
        // into an array under $_FILES, same as any multi-file <input multiple>.
        v.forEach(function (f) { body.append(k + '[]', f); });
      } else {
        body.set(k, v);
      }
    });
    return fetch('/messages.php', {
      method: 'POST',
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      body: body,
    }).then(function (r) { return r.text(); }).then(function (text) {
      // Some free hosts (this one included) inject extra content — an ad or
      // tracking snippet — into the response outside of anything our PHP
      // controls. That breaks a strict JSON.parse even though our actual
      // reply was fine, so pull out just the JSON object we printed and
      // ignore whatever, if anything, got tacked on after it.
      var jsonStr = extractJsonObject(text);
      if (!jsonStr) throw new Error('No JSON found in response: ' + text.slice(0, 200));
      return JSON.parse(jsonStr);
    });
  }

  function scrollThreadToBottom() {
    var b = document.getElementById('msgThreadBody');
    if (b) b.scrollTop = b.scrollHeight;
  }

  function setActiveConvo(userId) {
    convoList.querySelectorAll('.msg-convo-item').forEach(function (a) {
      a.classList.toggle('active', a.getAttribute('data-user-id') === String(userId));
    });
  }

  function stopPolling() {
    if (pollTimer) { clearInterval(pollTimer); pollTimer = null; }
  }

  function startPolling(withId) {
    stopPolling();
    pollTimer = setInterval(function () {
      postAjax({ action: 'poll_thread', with: withId }).then(function (data) {
        if (!data.ok) return;
        var b = document.getElementById('msgThreadBody');
        if (b) {
          var wasAtBottom = b.scrollHeight - b.scrollTop - b.clientHeight < 40;
          b.innerHTML = data.thread_html;
          if (wasAtBottom) scrollThreadToBottom();
        }
        refreshConvoList();
      }).catch(function () {});
    }, 4000);
  }

  function escapeHtml(s) {
    var d = document.createElement('div');
    d.textContent = s || '';
    return d.innerHTML;
  }

  function fileIconSvg() {
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>';
  }

  // ---- Composer markup (shared by initial page load and openThread()) ----
  function composerHtml(withId) {
    return (
      '<form class="msg-composer" id="msgComposerForm" data-with="' + withId + '">' +
        '<div class="msg-pending-atts" id="msgPendingAtts"></div>' +
        '<div class="msg-composer-row">' +
          '<div class="msg-composer-attach-wrap">' +
            '<button type="button" class="msg-composer-attach" id="msgAttachBtn" aria-label="Attach">' +
              '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>' +
            '</button>' +
            '<div class="msg-composer-attach-menu" id="msgAttachMenu">' +
              '<button type="button" data-pick="image"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>Photo or image</button>' +
              '<button type="button" data-pick="file"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>File or document</button>' +
            '</div>' +
            '<input type="file" id="msgFileImage" accept="image/png,image/jpeg,image/webp,image/gif" multiple hidden>' +
            '<input type="file" id="msgFileGeneric" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv,.zip,.rar" multiple hidden>' +
          '</div>' +
          '<textarea name="body" id="msgComposerInput" placeholder="Write a message…"></textarea>' +
          '<button type="submit" class="btn primary" aria-label="Send">' +
            '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>' +
          '</button>' +
        '</div>' +
      '</form>'
    );
  }

  function openThread(userId, name, role) {
    postAjax({ action: 'load_thread', with: userId }).then(function (data) {
      if (!data.ok) { alert(data.error || 'Could not open that conversation.'); return; }
      setActiveConvo(userId);
      history.replaceState(null, '', '/messages.php?with=' + userId);
      if (msgShell) msgShell.classList.add('show-thread');
      threadPanel.innerHTML =
        '<div class="msg-thread-head" id="msgThreadHead">' +
          '<button type="button" class="msg-thread-back" id="msgThreadBackBtn" aria-label="Back to conversations">' +
            '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>' +
          '</button>' +
          '<span class="msg-convo-avatar">' + data.header_avatar + '</span>' +
          '<div class="msg-thread-head-info">' +
            '<strong id="msgThreadName">' + escapeHtml(data.header_name) + '</strong>' +
            '<span class="msg-thread-role-badge">' +
              '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>' +
              '<span id="msgThreadRole">' + escapeHtml(data.header_role) + '</span>' +
            '</span>' +
          '</div>' +
          '<div class="msg-thread-head-action-wrap">' +
            '<button type="button" class="msg-thread-head-action" id="msgThreadMoreBtn" aria-label="More options">' +
              '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="5" r="1.5"/><circle cx="12" cy="12" r="1.5"/><circle cx="12" cy="19" r="1.5"/></svg>' +
            '</button>' +
            '<div class="msg-thread-head-menu" id="msgThreadMoreMenu">' +
              '<button type="button" id="msgThreadArchiveBtn" data-archived="' + (data.archived ? '1' : '0') + '">' +
                (data.archived ? 'Unarchive conversation' : 'Archive conversation') +
              '</button>' +
            '</div>' +
          '</div>' +
        '</div>' +
        '<div class="msg-thread-body" id="msgThreadBody">' + data.thread_html + '</div>' +
        composerHtml(userId);
      pendingFiles = [];
      bindComposer();
      bindBackButton();
      bindThreadMoreMenu(userId);
      scrollThreadToBottom();
      startPolling(userId);
    }).catch(function () { alert('Network error — please try again.'); });
  }

  // ---- Thread header "…" menu: archive / unarchive the open conversation ----
  function bindThreadMoreMenu(withId) {
    var moreBtn = document.getElementById('msgThreadMoreBtn');
    var moreMenu = document.getElementById('msgThreadMoreMenu');
    var archiveBtn = document.getElementById('msgThreadArchiveBtn');
    if (!moreBtn || !moreMenu || !archiveBtn) return;
    moreBtn.addEventListener('click', function (e) {
      e.stopPropagation();
      moreMenu.classList.toggle('open');
      moreBtn.classList.toggle('active', moreMenu.classList.contains('open'));
    });
    archiveBtn.addEventListener('click', function () {
      var isArchived = archiveBtn.getAttribute('data-archived') === '1';
      moreMenu.classList.remove('open');
      moreBtn.classList.remove('active');
      postAjax({ action: isArchived ? 'unarchive_conversation' : 'archive_conversation', with: withId }).then(function (data) {
        if (!data.ok) return;
        archiveBtn.setAttribute('data-archived', isArchived ? '0' : '1');
        archiveBtn.textContent = isArchived ? 'Archive conversation' : 'Unarchive conversation';
        refreshConvoList();
      }).catch(function () {});
    });
  }

  function bindBackButton() {
    var backBtn = document.getElementById('msgThreadBackBtn');
    if (!backBtn) return;
    backBtn.addEventListener('click', function () {
      if (msgShell) msgShell.classList.remove('show-thread');
      history.replaceState(null, '', '/messages.php');
      stopPolling();
    });
  }

  // ---- Pending-attachment preview chips ----
  function renderPendingAtts() {
    var wrap = document.getElementById('msgPendingAtts');
    if (!wrap) return;
    wrap.innerHTML = '';
    wrap.classList.toggle('has-items', pendingFiles.length > 0);
    pendingFiles.forEach(function (file, idx) {
      var chip = document.createElement('div');
      chip.className = 'msg-pending-chip';
      var thumb;
      if (file.type && file.type.indexOf('image/') === 0) {
        thumb = document.createElement('img');
        thumb.src = URL.createObjectURL(file);
      } else {
        thumb = document.createElement('span');
        thumb.className = 'msg-pending-chip-icon';
        thumb.innerHTML = fileIconSvg();
      }
      var nameSpan = document.createElement('span');
      nameSpan.className = 'msg-pending-chip-name';
      nameSpan.textContent = file.name;
      var removeBtn = document.createElement('button');
      removeBtn.type = 'button';
      removeBtn.className = 'msg-pending-chip-remove';
      removeBtn.setAttribute('aria-label', 'Remove');
      removeBtn.textContent = '×';
      removeBtn.addEventListener('click', function () {
        pendingFiles.splice(idx, 1);
        renderPendingAtts();
      });
      chip.appendChild(thumb);
      chip.appendChild(nameSpan);
      chip.appendChild(removeBtn);
      wrap.appendChild(chip);
    });
  }

  function addFiles(fileList) {
    var incoming = Array.prototype.slice.call(fileList || []);
    incoming.forEach(function (f) {
      if (pendingFiles.length >= MAX_ATTACHMENTS) return;
      pendingFiles.push(f);
    });
    if (incoming.length && pendingFiles.length >= MAX_ATTACHMENTS) {
      // silent cap — server also enforces this, this just avoids an obviously oversized picker result
    }
    renderPendingAtts();
  }

  function bindComposer() {
    var form = document.getElementById('msgComposerForm');
    if (!form) return;
    var attachBtn = document.getElementById('msgAttachBtn');
    var attachMenu = document.getElementById('msgAttachMenu');
    var fileImage = document.getElementById('msgFileImage');
    var fileGeneric = document.getElementById('msgFileGeneric');

    if (attachBtn && attachMenu) {
      attachBtn.addEventListener('click', function (e) {
        e.stopPropagation();
        attachMenu.classList.toggle('open');
        attachBtn.classList.toggle('active', attachMenu.classList.contains('open'));
      });
      attachMenu.addEventListener('click', function (e) {
        var pick = e.target.closest('[data-pick]');
        if (!pick) return;
        attachMenu.classList.remove('open');
        attachBtn.classList.remove('active');
        if (pick.getAttribute('data-pick') === 'image') fileImage.click();
        else fileGeneric.click();
      });
      document.addEventListener('click', function (e) {
        if (!e.target.closest('.msg-composer-attach-wrap')) {
          attachMenu.classList.remove('open');
          attachBtn.classList.remove('active');
        }
      });
    }
    if (fileImage) fileImage.addEventListener('change', function () { addFiles(fileImage.files); fileImage.value = ''; });
    if (fileGeneric) fileGeneric.addEventListener('change', function () { addFiles(fileGeneric.files); fileGeneric.value = ''; });

    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var withId = form.getAttribute('data-with');
      var input = document.getElementById('msgComposerInput');
      var text = input.value.trim();
      if (!text && !pendingFiles.length) return;
      var btn = form.querySelector('button[type="submit"]');
      if (btn) btn.disabled = true;
      postAjax({ action: 'send_message', to: withId, body: text, attachments: pendingFiles.slice(0, MAX_ATTACHMENTS) }).then(function (data) {
        if (!data.ok) { alert(data.error || 'Could not send that message.'); return; }
        var b = document.getElementById('msgThreadBody');
        if (b) {
          var empty = b.querySelector('.msg-thread-empty');
          if (empty) empty.remove();
          b.insertAdjacentHTML('beforeend', data.bubble_html);
          scrollThreadToBottom();
        }
        input.value = '';
        pendingFiles = [];
        renderPendingAtts();
        refreshConvoList();
      }).catch(function () { alert('Network error — your message was not sent.'); })
        .finally(function () { if (btn) btn.disabled = false; input.focus(); });
    });
    var input = document.getElementById('msgComposerInput');
    if (input) {
      input.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); form.requestSubmit(); }
      });
    }
    renderPendingAtts();
  }

  function currentOpenUserId() {
    var active = convoList.querySelector('.msg-convo-item.active');
    return active ? active.getAttribute('data-user-id') : (new URLSearchParams(location.search)).get('with') || '';
  }

  function refreshConvoList() {
    postAjax({ action: 'list_conversations', active: currentOpenUserId(), archived: currentTab === 'archive' ? 1 : 0 }).then(function (data) {
      if (!data.ok || !convoList) return;
      convoList.innerHTML = data.html;
    }).catch(function () {});
  }

  // ---- Sidebar: switch conversation without reload ----
  if (convoList) {
    convoList.addEventListener('click', function (e) {
      var link = e.target.closest('[data-convo-link]');
      if (!link) return;
      e.preventDefault();
      var userId = link.getAttribute('data-user-id');
      openThread(userId, link.getAttribute('data-user-name') || '', link.getAttribute('data-user-role') || '');
    });
  }

  // ---- New-conversation search ----
  if (searchInput) {
    searchInput.addEventListener('input', function () {
      var q = searchInput.value;
      clearTimeout(searchTimer);
      if (!q.trim()) { searchResults.classList.remove('open'); searchResults.innerHTML = ''; return; }
      var mySeq = ++searchSeq;
      searchTimer = setTimeout(function () {
        postAjax({ action: 'search_users', q: q }).then(function (data) {
          if (mySeq !== searchSeq) return; // a newer keystroke's response already landed — drop this stale one
          if (!data.ok) return;
          searchResults.innerHTML = data.html;
          searchResults.classList.add('open');
        }).catch(function () {});
      }, 250);
    });
    searchInput.addEventListener('focus', function () {
      if (searchResults.innerHTML.trim()) searchResults.classList.add('open');
    });
    document.addEventListener('click', function (e) {
      if (!e.target.closest('.msg-search-wrap')) searchResults.classList.remove('open');
    });
    searchResults.addEventListener('click', function (e) {
      var res = e.target.closest('[data-start-convo]');
      if (!res) return;
      searchResults.classList.remove('open');
      searchInput.value = '';
      openThread(res.getAttribute('data-user-id'), res.getAttribute('data-user-name'), res.getAttribute('data-user-role') || '');
    });
  }

  // ---- Sidebar: General / Archive tabs — each tab fetches its own real list ----
  var msgTabs = document.getElementById('msgTabs');
  var archiveEmptyStatic = document.getElementById('msgArchiveEmpty');
  if (archiveEmptyStatic) archiveEmptyStatic.style.display = 'none'; // superseded by the empty-state <p> the server now returns inside data.html
  if (msgTabs && convoList) {
    msgTabs.addEventListener('click', function (e) {
      var tab = e.target.closest('.msg-tab');
      if (!tab) return;
      msgTabs.querySelectorAll('.msg-tab').forEach(function (t) { t.classList.remove('active'); });
      tab.classList.add('active');
      currentTab = tab.getAttribute('data-tab') === 'archive' ? 'archive' : 'general';
      refreshConvoList();
    });
  }

  // ---- Per-conversation "…" menu (event delegation, since convoList's
  // contents get replaced wholesale by refreshConvoList()) ----
  if (convoList) {
    convoList.addEventListener('click', function (e) {
      var menuBtn = e.target.closest('[data-convo-menu-btn]');
      if (menuBtn) {
        e.preventDefault();
        e.stopPropagation();
        var panel = menuBtn.parentElement.querySelector('[data-convo-menu-panel]');
        var wasOpen = panel && panel.classList.contains('open');
        convoList.querySelectorAll('.msg-convo-menu.open').forEach(function (p) { p.classList.remove('open'); });
        convoList.querySelectorAll('.msg-convo-menu-btn.active').forEach(function (b) { b.classList.remove('active'); });
        if (panel && !wasOpen) { panel.classList.add('open'); menuBtn.classList.add('active'); }
        return;
      }
      var actionBtn = e.target.closest('[data-convo-action]');
      if (actionBtn) {
        e.preventDefault();
        e.stopPropagation();
        var withId = actionBtn.getAttribute('data-user-id');
        var isArchiveAction = actionBtn.getAttribute('data-convo-action') === 'archive';
        postAjax({ action: isArchiveAction ? 'archive_conversation' : 'unarchive_conversation', with: withId }).then(function (data) {
          if (!data.ok) return;
          refreshConvoList();
          // If I just archived/unarchived the conversation I currently have open,
          // update its header toggle too so it stays in sync without a reopen.
          var openArchiveBtn = document.getElementById('msgThreadArchiveBtn');
          if (openArchiveBtn && currentOpenUserId() === String(withId)) {
            openArchiveBtn.setAttribute('data-archived', isArchiveAction ? '1' : '0');
            openArchiveBtn.textContent = isArchiveAction ? 'Unarchive conversation' : 'Archive conversation';
          }
        }).catch(function () {});
      }
    });
  }

  // ---- Close any open dropdown (attach menu, per-item menu, thread-header menu) on an outside click ----
  document.addEventListener('click', function (e) {
    if (!e.target.closest('.msg-convo-menu-wrap')) {
      document.querySelectorAll('.msg-convo-menu.open').forEach(function (p) { p.classList.remove('open'); });
      document.querySelectorAll('.msg-convo-menu-btn.active').forEach(function (b) { b.classList.remove('active'); });
    }
    if (!e.target.closest('.msg-thread-head-action-wrap')) {
      var menu = document.getElementById('msgThreadMoreMenu');
      var btn = document.getElementById('msgThreadMoreBtn');
      if (menu) menu.classList.remove('open');
      if (btn) btn.classList.remove('active');
    }
  });

  bindThreadMoreMenu(<?php echo (int) $withId; ?>);

  bindComposer();
  bindBackButton();
  <?php if ($withId): ?>
  scrollThreadToBottom();
  startPolling(<?php echo (int) $withId; ?>);
  <?php endif; ?>
})();
</script>

<?php include __DIR__ . '/includes/drive_shell_bottom.php'; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>
