<?php
// Inside/dashboard page — suppress the marketing topbar (see includes/header.php).
$__hideTopbar = true;
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/notes.php';

$user = inkwell_require_login();

$activeId = (int) ($_GET['id'] ?? 0);
$ajax = inkwell_is_ajax();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Same silent-drop guard as posts.php: a big attachment over
  // post_max_size arrives with $_POST/$_FILES already emptied by PHP.
  if (inkwell_post_too_large()) {
    $msg = inkwell_post_too_large_message();
    if ($ajax) inkwell_json_response(['ok' => false, 'error' => $msg]);
    inkwell_flash_set('error', $msg);
    header('Location: /notes.php' . ($activeId ? '?id=' . $activeId : ''));
    exit;
  }

  $action = $_POST['action'] ?? '';

  if ($action === 'create_note') {
    $type = ($_POST['type'] ?? 'text') === 'code' ? 'code' : 'text';
    $result = inkwell_create_user_note($user['id'], $type);
    if ($result['ok']) {
      if ($ajax) {
        $note = inkwell_get_user_note($result['id'], $user['id']);
        inkwell_json_response([
          'ok' => true,
          'id' => $result['id'],
          'list_item_html' => inkwell_render_note_list_item($note, $result['id']),
          'editor_html' => inkwell_render_note_editor($note),
        ]);
      }
      header('Location: /notes.php?id=' . $result['id']);
    } else {
      if ($ajax) inkwell_json_response(['ok' => false, 'error' => $result['error']]);
      inkwell_flash_set('error', $result['error']);
      header('Location: /notes.php');
    }
    exit;
  }

  if ($action === 'save_note') {
    $activeId = (int) ($_POST['note_id'] ?? 0);
    $result = inkwell_update_user_note($activeId, $user['id'], [
      'title' => $_POST['title'] ?? '',
      'content' => $_POST['content'] ?? '',
      'font_family' => $_POST['font_family'] ?? '',
      'font_size' => $_POST['font_size'] ?? '',
      'code_language' => $_POST['code_language'] ?? '',
    ]);
    if ($ajax) {
      if ($result['ok']) {
        $note = inkwell_get_user_note($activeId, $user['id']);
        inkwell_json_response([
          'ok' => true,
          'list_item_html' => inkwell_render_note_list_item($note, $activeId),
        ]);
      }
      inkwell_json_response($result);
    }
    inkwell_flash_set($result['ok'] ? 'notice' : 'error', $result['ok'] ? 'Note saved.' : $result['error']);
    header('Location: /notes.php?id=' . $activeId);
    exit;
  }

  if ($action === 'attach_file') {
    $activeId = (int) ($_POST['note_id'] ?? 0);
    $chose = !empty($_FILES['attachment']['name']);
    $result = inkwell_attach_user_note_file($activeId, $user['id'], 'attachment');
    if ($ajax) {
      if ($result['ok']) {
        $note = inkwell_get_user_note($activeId, $user['id']);
        inkwell_json_response(['ok' => true, 'attachment_html' => inkwell_render_note_attachment($note)]);
      }
      inkwell_json_response($result);
    }
    if (!$result['ok']) {
      inkwell_flash_set('error', $result['error']);
    } elseif ($chose) {
      inkwell_flash_set('notice', 'File attached.');
    }
    header('Location: /notes.php?id=' . $activeId);
    exit;
  }

  if ($action === 'remove_attachment') {
    $activeId = (int) ($_POST['note_id'] ?? 0);
    inkwell_remove_user_note_attachment($activeId, $user['id']);
    if ($ajax) {
      $note = inkwell_get_user_note($activeId, $user['id']);
      inkwell_json_response(['ok' => true, 'attachment_html' => inkwell_render_note_attachment($note)]);
    }
    inkwell_flash_set('notice', 'Attachment removed.');
    header('Location: /notes.php?id=' . $activeId);
    exit;
  }

  if ($action === 'delete_note') {
    $delId = (int) ($_POST['note_id'] ?? 0);
    inkwell_delete_user_note($delId, $user['id']);
    if ($ajax) inkwell_json_response(['ok' => true]);
    inkwell_flash_set('notice', 'Note deleted.');
    header('Location: /notes.php');
    exit;
  }

  if ($action === 'load_note') {
    // Used only for AJAX "switch note in sidebar without reloading".
    $activeId = (int) ($_POST['note_id'] ?? 0);
    $note = inkwell_get_user_note($activeId, $user['id']);
    if (!$note) inkwell_json_response(['ok' => false, 'error' => 'Note not found.']);
    inkwell_json_response(['ok' => true, 'editor_html' => inkwell_render_note_editor($note)]);
  }
}

$flash = inkwell_flash_get();
$notice = $flash && $flash['type'] === 'notice' ? $flash['message'] : '';
$error = $flash && $flash['type'] === 'error' ? $flash['message'] : '';

$notes = inkwell_list_user_notes($user['id']);
if (!$activeId && !empty($notes)) $activeId = (int) $notes[0]['id'];
$activeNote = $activeId ? inkwell_get_user_note($activeId, $user['id']) : null;

$pageTitle = 'Notes';
include __DIR__ . '/includes/header.php';
$driveActive = 'notes';
$driveCrumbs = [['label' => 'Home', 'href' => '/index.php'], ['label' => 'Notes']];
$driveTitle = 'Notes';
$driveSubtitle = 'Personal notes only you can see — write plain formatted text like a doc, or a code snippet, and optionally attach a file.';
include __DIR__ . '/includes/drive_shell_top.php';
?>
<style>
  .notes-shell { display: grid; grid-template-columns: 260px 1fr; gap: 18px; align-items: start; }
  @media (max-width: 900px) { .notes-shell { grid-template-columns: 1fr; } }
  .notes-list { display: flex; flex-direction: column; gap: 6px; }
  .notes-list-item { display: block; padding: 10px 12px; border-radius: var(--radius-sm); text-decoration: none; color: inherit; border: 1px solid transparent; }
  .notes-list-item:hover { background: var(--surface-2); }
  .notes-list-item.active { background: var(--surface-2); border-color: var(--border-soft); font-weight: 700; }
  .notes-list-item small { display: block; font-weight: 400; color: var(--ink-dim); }
  .note-type-badge { display: inline-block; margin-left: 6px; padding: 1px 7px; border-radius: 999px; font-size: 0.65rem; font-weight: 800; letter-spacing: 0.04em; vertical-align: middle; }
  .note-type-badge.note-type-text { background: #5B7CFA26; color: #5B7CFA; }
  .note-type-badge.note-type-code { background: #1E9E6926; color: #1E9E69; }
  .note-toolbar { display: flex; flex-wrap: wrap; gap: 6px; padding: 8px; border: 1px solid var(--border-soft); border-radius: var(--radius-sm) var(--radius-sm) 0 0; background: var(--surface-2); }
  .note-toolbar button { border: 1px solid var(--border-soft); background: var(--surface-1); border-radius: 6px; padding: 5px 10px; cursor: pointer; font-size: 0.85rem; }
  .note-toolbar button:hover { background: var(--surface-2); }
  .note-toolbar select { border: 1px solid var(--border-soft); border-radius: 6px; padding: 5px 8px; font-size: 0.85rem; }
  .note-editable { min-height: 360px; border: 1px solid var(--border-soft); border-top: none; border-radius: 0 0 var(--radius-sm) var(--radius-sm); padding: 16px; background: var(--surface-1); outline: none; overflow-wrap: break-word; }
  .note-editable:empty:before { content: attr(data-placeholder); color: var(--ink-dim); }
  .note-code-area { width: 100%; min-height: 360px; border: 1px solid var(--border-soft); border-top: none; border-radius: 0 0 var(--radius-sm) var(--radius-sm); padding: 16px; font-family: var(--mono); font-size: 0.9rem; resize: vertical; }

  /* Floating "+" button that opens the add-note modal */
  .notes-fab {
    position: fixed; right: 24px; bottom: 24px; z-index: 150;
    width: 56px; height: 56px; border-radius: 50%; border: none;
    background: linear-gradient(135deg, var(--nib), var(--nib-glow)); color: #fff; font-size: 1.6rem; line-height: 1;
    display: flex; align-items: center; justify-content: center; cursor: pointer;
    box-shadow: 0 10px 26px color-mix(in srgb, var(--nib) 45%, transparent), 0 2px 6px rgba(0,0,0,0.2);
    transition: transform 0.15s ease, box-shadow 0.15s ease;
  }
  .notes-fab:hover { transform: translateY(-2px) scale(1.04); box-shadow: 0 14px 32px color-mix(in srgb, var(--nib) 55%, transparent), 0 3px 8px rgba(0,0,0,0.25); }
  .notes-fab:active { transform: translateY(0) scale(0.97); }
  @media (max-width: 900px) { .notes-fab { right: 16px; bottom: 16px; } }

  /* Add-note modal: two big tappable type cards */
  .note-type-choices { display: flex; flex-direction: column; gap: 10px; margin-top: 14px; }
  .note-type-choice {
    display: flex; align-items: center; gap: 14px; width: 100%; text-align: left;
    padding: 14px 16px; border-radius: 12px; border: 1px solid var(--border-soft);
    background: var(--surface-1); cursor: pointer; transition: background 0.15s, border-color 0.15s, transform 0.15s;
    font: inherit; color: inherit;
  }
  .note-type-choice:hover { background: var(--surface-2); border-color: var(--border); transform: translateY(-1px); }
  .note-type-choice:disabled { opacity: 0.6; cursor: default; transform: none; }
  .note-type-choice-icon {
    width: 40px; height: 40px; border-radius: 10px; background: var(--surface-2);
    display: flex; align-items: center; justify-content: center; font-size: 1.2rem; flex-shrink: 0;
  }
  .note-type-choice-body { display: flex; flex-direction: column; gap: 2px; min-width: 0; }
  .note-type-choice-title { font-weight: 700; font-size: 0.95rem; }
  .note-type-choice-sub { font-size: 0.8rem; color: var(--ink-dim); }
</style>

<div class="notes-shell">
  <section class="admin-card glass-card">
    <div class="admin-header-row" style="margin-bottom:8px;">
      <h2 style="margin:0;">Your notes</h2>
      <button type="button" class="btn primary" data-modal-open="addNoteModal" style="padding:6px 14px; font-size:0.85rem;">+ New</button>
    </div>
    <div class="notes-list" id="notesList">
      <?php foreach ($notes as $n) echo inkwell_render_note_list_item($n, $activeId); ?>
    </div>
    <p class="admin-sub" id="notesEmptyHint" style="<?php echo empty($notes) ? '' : 'display:none;'; ?>">No notes yet — tap <strong>+ New</strong> to create one.</p>
  </section>

  <section class="admin-card glass-card">
    <?php if ($notice): ?><div class="exam-result pass"><?php echo htmlspecialchars($notice); ?></div><?php endif; ?>
    <?php if ($error): ?>
      <div class="exam-result fail"><?php echo htmlspecialchars($error); ?></div>
      <?php if (!inkwell_user_notes_table_exists()): ?>
        <div class="admin-sub" style="margin-top:10px;">
          <p>This host isn't letting the app create the table itself. One-time fix: open <strong>phpMyAdmin</strong> for this database → your database → the <strong>SQL</strong> tab → paste this → <strong>Go</strong>. Only needs to be done once, ever.</p>
          <textarea readonly onclick="this.select();" style="width:100%; min-height:220px; font-family:var(--mono); font-size:0.78rem; padding:10px;"><?php echo htmlspecialchars(INKWELL_USER_NOTES_SQL); ?></textarea>
        </div>
      <?php endif; ?>
    <?php endif; ?>

    <div id="noteEditorPanel">
      <?php if (!$activeNote): ?>
        <p class="admin-sub">Select a note on the left, or create a new one.</p>
      <?php else: ?>
        <?php echo inkwell_render_note_editor($activeNote); ?>
      <?php endif; ?>
    </div>
  </section>
</div>

<button type="button" class="notes-fab" data-modal-open="addNoteModal" aria-label="Add note">+</button>

<div class="modal-backdrop" id="addNoteModal">
  <div class="modal" style="max-width:420px;">
    <div class="modal-head">
      <h2>New note</h2>
      <button type="button" data-modal-close aria-label="Close">✕</button>
    </div>
    <p class="admin-sub" style="margin-top:2px;">Pick a note type to get started.</p>
    <div class="note-type-choices">
      <form data-create-note-form data-note-modal-form>
        <input type="hidden" name="action" value="create_note">
        <input type="hidden" name="type" value="text">
        <button type="submit" class="note-type-choice">
          <span class="note-type-choice-icon" aria-hidden="true">📝</span>
          <span class="note-type-choice-body">
            <span class="note-type-choice-title">Text note</span>
            <span class="note-type-choice-sub">Formatted text, like a doc.</span>
          </span>
        </button>
      </form>
      <form data-create-note-form data-note-modal-form>
        <input type="hidden" name="action" value="create_note">
        <input type="hidden" name="type" value="code">
        <button type="submit" class="note-type-choice">
          <span class="note-type-choice-icon" aria-hidden="true">💻</span>
          <span class="note-type-choice-body">
            <span class="note-type-choice-title">Code note</span>
            <span class="note-type-choice-sub">A code snippet with syntax-friendly formatting.</span>
          </span>
        </button>
      </form>
    </div>
  </div>
</div>

<script>
(function () {
  const notesList = document.getElementById('notesList');
  const emptyHint = document.getElementById('notesEmptyHint');
  const editorPanel = document.getElementById('noteEditorPanel');
  const NO_NOTE_HTML = '<p class="admin-sub">Select a note on the left, or create a new one.</p>';

  function postAjax(formEl, extraFields) {
    const body = formEl ? new FormData(formEl) : new FormData();
    if (extraFields) {
      Object.keys(extraFields).forEach(function (k) { body.set(k, extraFields[k]); });
    }
    return fetch('/notes.php', {
      method: 'POST',
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      body: body,
    }).then(function (r) { return r.json(); });
  }

  function setActiveInList(id) {
    notesList.querySelectorAll('.notes-list-item').forEach(function (a) {
      a.classList.toggle('active', a.getAttribute('data-note-id') === String(id));
    });
  }

  // ---- Bind everything inside the (dynamically-replaced) editor panel ----
  function bindEditor() {
    const editable = document.getElementById('noteEditable');
    const hiddenInput = document.getElementById('noteContentInput');
    const form = document.getElementById('noteForm');
    const fontFamily = document.getElementById('noteFontFamily');
    const fontSize = document.getElementById('noteFontSize');
    const saveStatus = document.getElementById('noteSaveStatus');

    if (editable) {
      document.querySelectorAll('.note-toolbar [data-cmd]').forEach(function (btn) {
        btn.addEventListener('click', function () {
          editable.focus();
          document.execCommand(btn.getAttribute('data-cmd'), false, null);
        });
      });
      if (fontFamily) fontFamily.addEventListener('change', function () { editable.style.fontFamily = fontFamily.value; });
      if (fontSize) fontSize.addEventListener('change', function () { editable.style.fontSize = fontSize.value + 'px'; });
    }

    // Code notes: Tab inserts two spaces instead of moving focus away.
    const codeArea = document.getElementById('noteCodeArea');
    if (codeArea) {
      codeArea.addEventListener('keydown', function (e) {
        if (e.key === 'Tab') {
          e.preventDefault();
          const start = codeArea.selectionStart, end = codeArea.selectionEnd;
          codeArea.value = codeArea.value.slice(0, start) + '  ' + codeArea.value.slice(end);
          codeArea.selectionStart = codeArea.selectionEnd = start + 2;
        }
      });
    }

    if (form) {
      const noteId = form.getAttribute('data-note-id');
      form.addEventListener('submit', function (e) {
        e.preventDefault();
        if (editable && hiddenInput) hiddenInput.value = editable.innerHTML;
        const submitBtn = form.querySelector('button[type="submit"]');
        if (submitBtn) submitBtn.disabled = true;
        if (saveStatus) saveStatus.textContent = 'Saving…';

        postAjax(form)
          .then(function (data) {
            if (!data.ok) {
              if (saveStatus) saveStatus.textContent = '';
              alert(data.error || 'Could not save note — your change was not saved.');
              return;
            }
            if (saveStatus) {
              saveStatus.textContent = 'Saved';
              setTimeout(function () { if (saveStatus) saveStatus.textContent = ''; }, 1500);
            }
            const oldItem = notesList.querySelector('[data-note-id="' + noteId + '"]');
            if (oldItem) oldItem.outerHTML = data.list_item_html;
            setActiveInList(noteId);
          })
          .catch(function () {
            if (saveStatus) saveStatus.textContent = '';
            alert('Network error — your change was not saved. Please try again.');
          })
          .finally(function () { if (submitBtn) submitBtn.disabled = false; });
      });

      const deleteBtn = form.querySelector('[data-note-delete]');
      if (deleteBtn) {
        deleteBtn.addEventListener('click', function () {
          if (!confirm('Delete this note permanently?')) return;
          const delId = deleteBtn.getAttribute('data-note-id');
          postAjax(null, { action: 'delete_note', note_id: delId })
            .then(function (data) {
              if (!data.ok) { alert(data.error || 'Could not delete note.'); return; }
              const item = notesList.querySelector('[data-note-id="' + delId + '"]');
              if (item) item.remove();
              const remaining = notesList.querySelector('.notes-list-item');
              if (!remaining) {
                editorPanel.innerHTML = NO_NOTE_HTML;
                if (emptyHint) emptyHint.style.display = '';
                history.replaceState(null, '', '/notes.php');
              } else {
                remaining.click();
              }
            })
            .catch(function () { alert('Network error — please try again.'); });
        });
      }
    }

    bindAttachmentForms();
  }

  function bindAttachmentForms() {
    const removeForm = document.querySelector('[data-attachment-remove-form]');
    if (removeForm) {
      removeForm.addEventListener('submit', function (e) {
        e.preventDefault();
        postAjax(removeForm)
          .then(function (data) {
            if (!data.ok) { alert(data.error || 'Could not remove attachment.'); return; }
            const box = document.getElementById('noteAttachmentBox');
            if (box) box.outerHTML = data.attachment_html;
            bindAttachmentForms();
          })
          .catch(function () { alert('Network error — please try again.'); });
      });
    }
    const uploadForm = document.querySelector('[data-attachment-upload-form]');
    if (uploadForm) {
      uploadForm.addEventListener('submit', function (e) {
        e.preventDefault();
        const submitBtn = uploadForm.querySelector('button[type="submit"]');
        if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = 'Uploading…'; }
        postAjax(uploadForm)
          .then(function (data) {
            if (!data.ok) { alert(data.error || 'Could not attach file.'); return; }
            const box = document.getElementById('noteAttachmentBox');
            if (box) box.outerHTML = data.attachment_html;
            bindAttachmentForms();
          })
          .catch(function () { alert('Network error — please try again.'); })
          .finally(function () {
            if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'Attach file'; }
          });
      });
    }
  }

  // ---- Sidebar: switch notes without a page reload ----
  if (notesList) {
    notesList.addEventListener('click', function (e) {
      const link = e.target.closest('[data-note-link]');
      if (!link) return;
      e.preventDefault();
      const noteId = link.getAttribute('data-note-id');
      setActiveInList(noteId);
      postAjax(null, { action: 'load_note', note_id: noteId })
        .then(function (data) {
          if (!data.ok) { alert(data.error || 'Could not open that note.'); return; }
          editorPanel.innerHTML = data.editor_html;
          history.replaceState(null, '', '/notes.php?id=' + noteId);
          bindEditor();
        })
        .catch(function () { alert('Network error — please try again.'); });
    });
  }

  // ---- Create note without a page reload ----
  document.querySelectorAll('[data-create-note-form]').forEach(function (form) {
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      const btn = form.querySelector('button[type="submit"]');
      if (btn) btn.disabled = true;
      postAjax(form)
        .then(function (data) {
          if (!data.ok) { alert(data.error || 'Could not create note.'); return; }
          if (emptyHint) emptyHint.style.display = 'none';
          if (notesList) notesList.insertAdjacentHTML('afterbegin', data.list_item_html);
          setActiveInList(data.id);
          editorPanel.innerHTML = data.editor_html;
          history.replaceState(null, '', '/notes.php?id=' + data.id);
          bindEditor();
          if (form.hasAttribute('data-note-modal-form')) {
            const backdrop = form.closest('.modal-backdrop');
            if (backdrop) {
              backdrop.classList.remove('open');
              if (!document.querySelector('.modal-backdrop.open')) document.body.style.overflow = '';
            }
          }
        })
        .catch(function () { alert('Network error — please try again.'); })
        .finally(function () { if (btn) btn.disabled = false; });
    });
  });

  bindEditor();
})();
</script>

<?php include __DIR__ . '/includes/drive_shell_bottom.php'; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>
