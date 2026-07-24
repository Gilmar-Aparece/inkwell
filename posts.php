<?php
// Inside/dashboard page — suppress the marketing topbar (see includes/header.php).
$__hideTopbar = true;
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/posts.php';

$user = inkwell_require_login();
$isAdmin = $user['role'] === 'admin';
$ajax = inkwell_is_ajax();

$mySchoolName = null;
if (!empty($user['school_id'])) {
  require_once __DIR__ . '/includes/schools.php';
  $mySchool = inkwell_get_school((int) $user['school_id']);
  $mySchoolName = $mySchool ? $mySchool['name'] : null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // A large photo/video upload that exceeds this server's post_max_size
  // arrives with an empty $_POST/$_FILES — PHP already dropped it before
  // we got here. Catch that case with a real error instead of it looking
  // like the Post button silently did nothing.
  if (inkwell_post_too_large()) {
    $msg = inkwell_post_too_large_message();
    if ($ajax) inkwell_json_response(['ok' => false, 'error' => $msg]);
    inkwell_flash_set('error', $msg);
    header('Location: /posts.php');
    exit;
  }

  $action = $_POST['action'] ?? '';
  $postId = (int) ($_POST['post_id'] ?? 0);

  if ($action === 'poll') {
    $afterId = (int) ($_POST['after_id'] ?? 0);
    $watchIds = [];
    if (!empty($_POST['watch_ids'])) {
      foreach (explode(',', $_POST['watch_ids']) as $wid) $watchIds[] = (int) $wid;
    }
    $newPosts = inkwell_list_new_posts($afterId, $user['id']);
    $newPostsOut = [];
    foreach ($newPosts as $p) {
      $newPostsOut[] = ['id' => (int) $p['id'], 'html' => inkwell_render_post_card($p, $user, $isAdmin)];
    }
    inkwell_json_response([
      'ok' => true,
      'new_posts' => $newPostsOut,
      'counts' => inkwell_get_post_counts($watchIds, $user['id']),
    ]);
    exit;
  }

  if ($action === 'create_post') {
    $shareToSchoolId = (!empty($_POST['share_to_school']) && !empty($user['school_id'])) ? (int) $user['school_id'] : null;
    $result = inkwell_create_post(
      $user['id'],
      $_POST['caption'] ?? '',
      'media',
      null,
      $shareToSchoolId,
      $_POST['text_align'] ?? 'left',
      !empty($_POST['text_bold']),
      $_POST['bg_template'] ?? null
    );
    if ($result['ok']) {
      if ($ajax) {
        $post = inkwell_get_post_full($result['id'], $user['id']);
        inkwell_json_response(['ok' => true, 'id' => $result['id'], 'html' => inkwell_render_post_card($post, $user, $isAdmin)]);
      }
      inkwell_flash_set('notice', 'Posted! Your update is now live in the feed.');
      header('Location: /posts.php#post-' . $result['id']);
    } else {
      if ($ajax) inkwell_json_response(['ok' => false, 'error' => $result['error']]);
      inkwell_flash_set('error', $result['error']);
      header('Location: /posts.php');
    }
    exit;
  }

  if ($action === 'create_share') {
    $sharedPostId = (int) ($_POST['shared_post_id'] ?? 0);
    $shareToSchoolId = (!empty($_POST['share_to_school']) && !empty($user['school_id'])) ? (int) $user['school_id'] : null;
    $result = inkwell_create_share($user['id'], $sharedPostId, $_POST['caption'] ?? '', $shareToSchoolId);
    if ($result['ok']) {
      if ($ajax) {
        $post = inkwell_get_post_full($result['id'], $user['id']);
        inkwell_json_response(['ok' => true, 'id' => $result['id'], 'html' => inkwell_render_post_card($post, $user, $isAdmin)]);
      }
      inkwell_flash_set('notice', 'Shared to your feed!');
      header('Location: /posts.php#post-' . $result['id']);
    } else {
      if ($ajax) inkwell_json_response(['ok' => false, 'error' => $result['error']]);
      inkwell_flash_set('error', $result['error']);
      header('Location: /posts.php');
    }
    exit;
  }

  if ($action === 'toggle_like') {
    $result = inkwell_toggle_post_like($postId, $user['id']);
    if ($ajax) inkwell_json_response($result);
    if (!$result['ok']) inkwell_flash_set('error', $result['error']);
    header('Location: /posts.php#post-' . $postId);
    exit;
  }

  if ($action === 'toggle_save') {
    $result = inkwell_toggle_post_save($postId, $user['id']);
    if ($ajax) inkwell_json_response($result);
    if (!$result['ok']) inkwell_flash_set('error', $result['error']);
    header('Location: /posts.php#post-' . $postId);
    exit;
  }

  if ($action === 'add_comment') {
    $result = inkwell_add_comment($postId, $user['id'], $_POST['comment'] ?? '');
    if ($ajax) {
      if ($result['ok']) {
        $post = inkwell_get_post($postId);
        $comments = inkwell_list_comments($postId);
        $new = end($comments);
        inkwell_json_response(['ok' => true, 'html' => inkwell_render_comment($new, $post, $user, $isAdmin), 'comment_count' => count($comments)]);
      }
      inkwell_json_response($result);
    }
    inkwell_flash_set($result['ok'] ? 'notice' : 'error', $result['ok'] ? 'Comment added.' : $result['error']);
    header('Location: /posts.php#post-' . $postId);
    exit;
  }

  if ($action === 'delete_post') {
    $result = inkwell_delete_post($postId, $user['id'], $isAdmin);
    if ($ajax) inkwell_json_response($result);
    inkwell_flash_set($result['ok'] ? 'notice' : 'error', $result['ok'] ? 'Post deleted.' : $result['error']);
    header('Location: /posts.php');
    exit;
  }

  if ($action === 'hide_post') {
    $result = inkwell_hide_post($postId, $user['id']);
    if ($ajax) inkwell_json_response($result);
    inkwell_flash_set($result['ok'] ? 'notice' : 'error', $result['ok'] ? 'Post hidden.' : $result['error']);
    header('Location: /posts.php');
    exit;
  }

  if ($action === 'report_post') {
    $result = inkwell_report_post($postId, $user['id'], $_POST['reason'] ?? '');
    if ($ajax) inkwell_json_response($result);
    inkwell_flash_set($result['ok'] ? 'notice' : 'error', $result['ok'] ? 'Post reported.' : $result['error']);
    header('Location: /posts.php');
    exit;
  }

  if ($action === 'edit_post') {
    $result = inkwell_edit_post($postId, $user['id'], $_POST['caption'] ?? '', $_POST['text_align'] ?? 'left', !empty($_POST['text_bold']), $isAdmin, $_POST['bg_template'] ?? null);
    if ($ajax) {
      if ($result['ok']) {
        $post = inkwell_get_post($postId);
        inkwell_json_response(['ok' => true, 'html' => inkwell_render_post_card($post, $user, $isAdmin)]);
      }
      inkwell_json_response($result);
    }
    inkwell_flash_set($result['ok'] ? 'notice' : 'error', $result['ok'] ? 'Post updated.' : $result['error']);
    header('Location: /posts.php#post-' . $postId);
    exit;
  }

  if ($action === 'delete_comment') {
    $commentId = (int) ($_POST['comment_id'] ?? 0);
    $result = inkwell_delete_comment($commentId, $user['id'], $isAdmin);
    if ($ajax) inkwell_json_response($result);
    inkwell_flash_set($result['ok'] ? 'notice' : 'error', $result['ok'] ? 'Comment deleted.' : $result['error']);
    header('Location: /posts.php#post-' . $postId);
    exit;
  }

  // ---- Per-photo (Facebook-style) engagement: separate like/comment
  // threads scoped to one picture inside a multi-photo post, surfaced
  // through the fullscreen lightbox rather than the feed card itself. ----

  if ($action === 'toggle_image_like') {
    $imageId = (int) ($_POST['image_id'] ?? 0);
    $result = inkwell_toggle_image_like($imageId, $user['id']);
    inkwell_json_response($result);
    exit;
  }

  if ($action === 'get_image_comments') {
    $imageId = (int) ($_POST['image_id'] ?? 0);
    $comments = inkwell_list_image_comments($imageId);
    $html = '';
    foreach ($comments as $c) $html .= inkwell_render_image_comment($c, $imageId, $user, $isAdmin);
    inkwell_json_response(['ok' => true, 'html' => $html, 'count' => count($comments)]);
    exit;
  }

  if ($action === 'add_image_comment') {
    $imageId = (int) ($_POST['image_id'] ?? 0);
    $result = inkwell_add_image_comment($imageId, $user['id'], $_POST['comment'] ?? '');
    if ($result['ok']) {
      $comments = inkwell_list_image_comments($imageId);
      $new = end($comments);
      inkwell_json_response(['ok' => true, 'html' => inkwell_render_image_comment($new, $imageId, $user, $isAdmin), 'count' => count($comments)]);
    }
    inkwell_json_response($result);
    exit;
  }

  if ($action === 'delete_image_comment') {
    $commentId = (int) ($_POST['comment_id'] ?? 0);
    $imageId = (int) ($_POST['image_id'] ?? 0);
    $result = inkwell_delete_image_comment($commentId, $user['id'], $isAdmin);
    if ($result['ok']) {
      $result['count'] = count(inkwell_list_image_comments($imageId));
    }
    inkwell_json_response($result);
    exit;
  }
}

$flash = inkwell_flash_get();
$posts = inkwell_list_posts($user['id']);
$maxPostId = $posts ? (int) $posts[0]['id'] : 0;
$avatarGradients = inkwell_post_avatar_gradients();

$pageTitle = 'Community';
include __DIR__ . '/includes/header.php';
$driveActive = 'community';
$driveCrumbs = [['label' => 'Home', 'href' => '/index.php'], ['label' => 'Community']];
$driveTitle = 'Community';
$driveSubtitle = 'Share what you\'re working on — post text, a photo, or a video, and like or comment on posts from other students, teachers, and deans.';
$driveFullBleedMobile = true;
include __DIR__ . '/includes/drive_shell_top.php';
?>

<?php if ($flash): ?>
  <div class="post-flash <?php echo htmlspecialchars($flash['type']); ?>">
    <span class="post-flash-icon"><?php echo $flash['type'] === 'notice' ? '✓' : '!'; ?></span>
    <span><?php echo htmlspecialchars($flash['message']); ?></span>
  </div>
  <?php if ($flash['type'] === 'error' && !inkwell_posts_tables_exist()): ?>
    <div class="admin-sub" style="margin-bottom:16px;">
      <p>This host isn't letting the app create the tables itself. One-time fix: open <strong>phpMyAdmin</strong> for this database → your database → the <strong>SQL</strong> tab → paste this → <strong>Go</strong>. Only needs to be done once, ever.</p>
      <textarea readonly onclick="this.select();" style="width:100%; min-height:220px; font-family:var(--mono); font-size:0.78rem; padding:10px;"><?php echo htmlspecialchars(INKWELL_POSTS_SQL); ?></textarea>
    </div>
  <?php endif; ?>
<?php endif; ?>

<?php
  $composerAvatarHtml = !empty($user['avatar'])
    ? '<img src="/assets/uploads/' . htmlspecialchars($user['avatar']) . '" alt="" loading="lazy">'
    : strtoupper(substr($user['name'], 0, 1));
  $composerGrad = inkwell_avatar_gradient($avatarGradients, $user['id']);
?>
<section class="admin-card glass-card post-composer-trigger">
  <div class="post-composer-trigger-row">
    <span class="post-composer-avatar" style="background:<?php echo $composerGrad; ?>;"><?php echo $composerAvatarHtml; ?></span>
    <button type="button" class="post-composer-fake-input" id="postOpenModalBtn">What's on your mind, <?php echo htmlspecialchars(explode(' ', $user['name'])[0]); ?>?</button>
  </div>
  <div class="post-composer-quick-row">
    <button type="button" class="post-composer-quick-btn" id="postOpenModalBtn2">
      <span class="quick-icon">✍️</span> Write something
    </button>
    <button type="button" class="post-composer-quick-btn media" id="postOpenModalMediaBtn">
      <span class="quick-icon">🎞️</span> Photo/video
    </button>
  </div>
</section>

<div class="post-modal-overlay" id="postModalOverlay">
  <div class="post-modal">
    <form method="post" action="/posts.php" enctype="multipart/form-data" id="postComposerForm">
      <input type="hidden" name="action" value="create_post">
      <div class="post-modal-head">
        <h3>Create post</h3>
        <button type="button" class="post-modal-close" id="postModalCloseBtn" aria-label="Close">✕</button>
      </div>
      <div class="post-modal-body">
        <div class="post-modal-user">
          <span class="post-composer-avatar" style="background:<?php echo $composerGrad; ?>;"><?php echo $composerAvatarHtml; ?></span>
          <div>
            <div class="post-modal-user-name"><?php echo htmlspecialchars($user['name']); ?></div>
            <span class="post-modal-audience">🌐 Community</span>
          </div>
        </div>

        <div class="post-editor-toolbar" id="postEditorToolbar">
          <button type="button" class="post-editor-btn" id="postBoldBtn" title="Bold"><strong>B</strong></button>
          <span class="post-editor-sep"></span>
          <button type="button" class="post-editor-btn active" data-align="left" title="Align left">L</button>
          <button type="button" class="post-editor-btn" data-align="center" title="Align center">C</button>
          <button type="button" class="post-editor-btn" data-align="right" title="Align right">R</button>
        </div>

        <textarea name="caption" maxlength="2000" placeholder="What's on your mind?" id="postCaptionInput" class="post-caption-editor" rows="3" autofocus></textarea>

        <div class="post-template-row" id="postTemplateRow">
          <button type="button" class="post-template-swatch none active" data-template="" title="No background">Aa</button>
          <?php foreach (inkwell_post_bg_templates() as $tplId => $tplGradient): ?>
            <button type="button" class="post-template-swatch" data-template="<?php echo htmlspecialchars($tplId); ?>" style="background:<?php echo $tplGradient; ?>;" title="Background template <?php echo htmlspecialchars($tplId); ?>">Aa</button>
          <?php endforeach; ?>
        </div>
        <input type="hidden" name="text_align" id="postTextAlign" value="left">
        <input type="hidden" name="text_bold" id="postTextBold" value="0">
        <input type="hidden" name="bg_template" id="postBgTemplate" value="">

        <div class="post-modal-preview-wrap" id="postPreviewWrap" style="display:none;">
          <video id="postPreviewVideo" controls style="display:none;"></video>
          <div class="post-modal-photo-grid" id="postPreviewGrid" style="display:none;"></div>
        </div>

        <div class="post-modal-addto">
          <span class="post-modal-addto-label">Add to your post</span>
          <div class="post-modal-addto-icons">
            <span class="post-modal-icon-btn media" title="Photo(s) or a video">
              🖼️
              <input type="file" name="media[]" id="postMediaInput" multiple accept="image/png,image/jpeg,image/webp,video/mp4,video/webm,video/quicktime,video/ogg">
            </span>
            <span class="post-modal-icon-btn muted" title="Coming soon">🙂</span>
            <span class="post-modal-icon-btn muted" title="Coming soon">📍</span>
            <span class="post-modal-icon-btn muted" title="Coming soon">⋯</span>
          </div>
        </div>
        <p class="post-modal-hint" id="postModalHint">Photos up to 8MB each (up to 10) · or one video up to 100MB<?php $serverLimit = inkwell_effective_upload_limit_bytes(); if ($serverLimit > 0 && $serverLimit < 100 * 1024 * 1024) echo ' (this server currently accepts up to ' . htmlspecialchars(inkwell_format_bytes($serverLimit)) . ' per upload)'; ?></p>

        <?php if ($mySchoolName): ?>
          <label class="post-modal-school-share">
            <input type="checkbox" name="share_to_school" value="1">
            🏫 Also post to <?php echo htmlspecialchars($mySchoolName); ?>'s school page
          </label>
        <?php endif; ?>
        <p class="post-modal-hint" id="postComposerError" style="display:none; color:var(--danger);"></p>

        <button class="btn primary" type="submit" id="postSubmitBtn" disabled>Post</button>
      </div>
    </form>
  </div>
</div>

<div class="post-modal-overlay" id="postShareModalOverlay">
  <div class="post-modal post-share-modal">
    <form method="post" action="/posts.php" id="postShareForm">
      <input type="hidden" name="action" value="create_share">
      <input type="hidden" name="shared_post_id" id="postShareTargetId" value="">
      <div class="post-modal-head">
        <h3>Share post</h3>
        <button type="button" class="post-modal-close" id="postShareModalCloseBtn" aria-label="Close">✕</button>
      </div>
      <div class="post-modal-body">
        <div class="post-modal-user">
          <span class="post-composer-avatar" style="background:<?php echo $composerGrad; ?>;"><?php echo $composerAvatarHtml; ?></span>
          <div>
            <div class="post-modal-user-name"><?php echo htmlspecialchars($user['name']); ?></div>
            <span class="post-modal-audience">🌐 Community</span>
          </div>
        </div>

        <textarea name="caption" maxlength="2000" placeholder="Say something about this (optional)…" id="postShareCaptionInput" rows="2"></textarea>

        <div class="post-share-preview" id="postSharePreview"></div>

        <?php if ($mySchoolName): ?>
          <label class="post-modal-school-share">
            <input type="checkbox" name="share_to_school" value="1">
            🏫 Also share to <?php echo htmlspecialchars($mySchoolName); ?>'s school page
          </label>
        <?php endif; ?>

        <p class="post-modal-hint" id="postShareError" style="display:none; color:var(--danger);"></p>

        <button class="btn primary" type="submit" id="postShareSubmitBtn">Share now</button>
      </div>
    </form>
  </div>
</div>

<div style="display:flex; justify-content:flex-end; margin-bottom:10px;">
  <span class="post-live-dot">Live</span>
</div>
<div class="post-feed" id="postFeed" data-max-id="<?php echo (int) $maxPostId; ?>">
  <?php if (empty($posts)): ?>
    <div class="admin-card glass-card post-empty" id="postFeedEmpty">
      <span class="icon">🖼️</span>
      <p class="admin-sub" style="margin:0;">No posts yet — be the first to share something.</p>
    </div>
  <?php else: ?>
    <?php foreach ($posts as $post) echo inkwell_render_post_card($post, $user, $isAdmin); ?>
  <?php endif; ?>
</div>

<script>
(function () {
  const overlay = document.getElementById('postModalOverlay');
  const openBtns = [
    document.getElementById('postOpenModalBtn'),
    document.getElementById('postOpenModalBtn2'),
    document.getElementById('postOpenModalMediaBtn'),
  ].filter(Boolean);
  const closeBtn = document.getElementById('postModalCloseBtn');
  const mediaInput = document.getElementById('postMediaInput');
  const previewWrap = document.getElementById('postPreviewWrap');
  const previewGrid = document.getElementById('postPreviewGrid');
  const previewVideo = document.getElementById('postPreviewVideo');
  const textarea = document.getElementById('postCaptionInput');
  const submitBtn = document.getElementById('postSubmitBtn');
  const hint = document.getElementById('postModalHint');
  const defaultHint = hint ? hint.textContent : '';

  // ---- Formatting toolbar: bold, align, colorful-background templates ----
  const boldBtn = document.getElementById('postBoldBtn');
  const alignBtns = Array.prototype.slice.call(document.querySelectorAll('#postEditorToolbar [data-align]'));
  const templateSwatches = Array.prototype.slice.call(document.querySelectorAll('#postTemplateRow .post-template-swatch'));
  const alignInput = document.getElementById('postTextAlign');
  const boldInput = document.getElementById('postTextBold');
  const templateInput = document.getElementById('postBgTemplate');

  function applyEditorStyle() {
    if (!textarea) return;
    textarea.className = 'post-caption-editor' + (templateInput.value ? ' post-tpl-' + templateInput.value : '');
    textarea.style.textAlign = alignInput.value;
    textarea.style.fontWeight = boldInput.value === '1' ? '800' : '400';
  }

  function resetToolbar() {
    alignInput.value = 'left';
    boldInput.value = '0';
    templateInput.value = '';
    if (boldBtn) boldBtn.classList.remove('active');
    alignBtns.forEach(function (b) { b.classList.toggle('active', b.getAttribute('data-align') === 'left'); });
    templateSwatches.forEach(function (s) { s.classList.toggle('active', s.classList.contains('none')); });
    applyEditorStyle();
  }

  function setTemplatesEnabled(enabled) {
    templateSwatches.forEach(function (s) {
      s.disabled = !enabled;
      s.classList.toggle('disabled', !enabled);
    });
    if (!enabled && templateInput.value) {
      templateInput.value = '';
      templateSwatches.forEach(function (s) { s.classList.toggle('active', s.classList.contains('none')); });
      applyEditorStyle();
    }
  }

  if (boldBtn) {
    boldBtn.addEventListener('click', function () {
      boldInput.value = boldInput.value === '1' ? '0' : '1';
      boldBtn.classList.toggle('active', boldInput.value === '1');
      applyEditorStyle();
    });
  }
  alignBtns.forEach(function (btn) {
    btn.addEventListener('click', function () {
      alignInput.value = btn.getAttribute('data-align');
      alignBtns.forEach(function (b) { b.classList.toggle('active', b === btn); });
      applyEditorStyle();
    });
  });
  templateSwatches.forEach(function (sw) {
    sw.addEventListener('click', function () {
      if (sw.disabled) return;
      templateInput.value = sw.getAttribute('data-template') || '';
      templateSwatches.forEach(function (s) { s.classList.toggle('active', s === sw); });
      applyEditorStyle();
    });
  });

  // <input multiple>'s FileList is read-only, so per-photo "remove" (✕ on
  // one thumbnail) is done by keeping our own array of selected photo
  // Files and rebuilding the input's FileList from it via DataTransfer
  // every time it changes. Video stays single-file and bypasses this.
  let selectedPhotos = [];

  function openModal(focusMedia) {
    overlay.classList.add('open');
    if (focusMedia && mediaInput) {
      mediaInput.click();
    } else if (textarea) {
      setTimeout(function () { textarea.focus(); }, 30);
    }
  }

  function closeModal() {
    overlay.classList.remove('open');
  }

  function updateSubmitState() {
    const hasText = textarea && textarea.value.trim() !== '';
    const hasFile = (mediaInput && mediaInput.files && mediaInput.files.length > 0);
    submitBtn.disabled = !hasText && !hasFile;
  }

  function syncInputFiles() {
    const dt = new DataTransfer();
    selectedPhotos.forEach(function (f) { dt.items.add(f); });
    mediaInput.files = dt.files;
  }

  function renderPhotoGrid() {
    previewGrid.innerHTML = '';
    if (!selectedPhotos.length) {
      previewGrid.style.display = 'none';
      return;
    }
    selectedPhotos.forEach(function (file, idx) {
      const url = URL.createObjectURL(file);
      const tile = document.createElement('div');
      tile.className = 'post-modal-photo-tile';
      tile.innerHTML = '<img src="' + url + '" alt="" loading="lazy">' +
        '<button type="button" class="post-modal-photo-remove" title="Remove" data-idx="' + idx + '">✕</button>';
      previewGrid.appendChild(tile);
    });
    previewGrid.style.display = 'grid';
    hint.textContent = selectedPhotos.length === 1 ? '1 photo selected' : selectedPhotos.length + ' photos selected';
  }

  function clearPreview() {
    selectedPhotos = [];
    if (mediaInput) mediaInput.value = '';
    previewWrap.style.display = 'none';
    previewGrid.style.display = 'none';
    previewGrid.innerHTML = '';
    previewVideo.style.display = 'none';
    previewVideo.removeAttribute('src');
    if (hint) hint.textContent = defaultHint;
    setTemplatesEnabled(true);
  }

  function showVideoPreview(file) {
    selectedPhotos = [];
    const url = URL.createObjectURL(file);
    previewVideo.src = url;
    previewVideo.style.display = 'block';
    previewGrid.style.display = 'none';
    previewWrap.style.display = 'block';
    hint.textContent = 'Video selected — ' + file.name;
  }

  resetToolbar();

  openBtns.forEach(function (btn) {
    btn.addEventListener('click', function () { openModal(btn.id === 'postOpenModalMediaBtn'); });
  });
  if (closeBtn) closeBtn.addEventListener('click', closeModal);
  if (overlay) {
    overlay.addEventListener('click', function (e) { if (e.target === overlay) closeModal(); });
  }
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && overlay.classList.contains('open')) closeModal();
  });

  if (mediaInput) {
    mediaInput.addEventListener('change', function () {
      const files = Array.prototype.slice.call(mediaInput.files || []);
      if (!files.length) { updateSubmitState(); return; }

      if (files[0].type.indexOf('video/') === 0) {
        showVideoPreview(files[0]);
      } else {
        // Photos only from here — drop any video that snuck in via a mixed selection.
        selectedPhotos = selectedPhotos.concat(files.filter(function (f) { return f.type.indexOf('video/') !== 0; })).slice(0, 10);
        previewVideo.style.display = 'none';
        previewVideo.removeAttribute('src');
        syncInputFiles();
        renderPhotoGrid();
        previewWrap.style.display = 'block';
      }
      setTemplatesEnabled(false);
      updateSubmitState();
    });
  }

  if (previewGrid) {
    previewGrid.addEventListener('click', function (e) {
      const btn = e.target.closest('.post-modal-photo-remove');
      if (!btn) return;
      const idx = parseInt(btn.getAttribute('data-idx'), 10);
      selectedPhotos.splice(idx, 1);
      syncInputFiles();
      renderPhotoGrid();
      if (!selectedPhotos.length) previewWrap.style.display = 'none';
      updateSubmitState();
    });
  }

  if (textarea) {
    textarea.addEventListener('input', updateSubmitState);
  }

  // ---- Create post over fetch(), no page reload ----
  const composerForm = document.getElementById('postComposerForm');
  const composerError = document.getElementById('postComposerError');
  const feed = document.getElementById('postFeed');
  const feedEmpty = document.getElementById('postFeedEmpty');

  function showComposerError(msg) {
    if (!composerError) return;
    composerError.textContent = msg;
    composerError.style.display = msg ? 'block' : 'none';
  }

  if (composerForm) {
    composerForm.addEventListener('submit', function (e) {
      e.preventDefault();
      showComposerError('');
      submitBtn.disabled = true;
      const originalLabel = submitBtn.textContent;
      submitBtn.textContent = 'Posting…';

      fetch('/posts.php', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: new FormData(composerForm),
      })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (data.ok) {
            if (feedEmpty) feedEmpty.remove();
            if (feed) feed.insertAdjacentHTML('afterbegin', data.html);
            composerForm.reset();
            clearPreview();
            resetToolbar();
            closeModal();
            const newCard = document.getElementById('post-' + data.id);
            if (newCard) newCard.scrollIntoView({ behavior: 'smooth', block: 'start' });
          } else {
            showComposerError(data.error || 'Could not post — please try again.');
          }
        })
        .catch(function () {
          showComposerError('Network error — please check your connection and try again.');
        })
        .finally(function () {
          submitBtn.textContent = originalLabel;
          updateSubmitState();
        });
    });
  }

  // ---- Feed interactions (like / comment / delete) over fetch(), delegated so it also covers posts inserted after load ----
  function postAjax(fields) {
    const body = new FormData();
    Object.keys(fields).forEach(function (k) { body.append(k, fields[k]); });
    return fetch('/posts.php', {
      method: 'POST',
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      body: body,
    }).then(function (r) { return r.json(); });
  }

  // Sets the count text on a post's comments-header ("Comments (N)") and shows/hides it.
  function setCommentsHeaderCount(postId, count) {
    const header = document.getElementById('post-comments-header-' + postId);
    if (!header) return;
    const countEl = header.querySelector('.count');
    if (countEl) countEl.textContent = count;
    header.style.display = count > 0 ? '' : 'none';
  }

  // Briefly flashes a button's title text as a tooltip-style confirmation (used by the copy-link buttons).
  function flashCopied(btn, label) {
    if (!btn) return;
    const original = btn.tagName === 'BUTTON' ? btn.textContent : btn.getAttribute('title');
    if (btn.tagName === 'BUTTON') btn.textContent = label || 'Copied!';
    btn.classList.add('copied-flash');
    setTimeout(function () {
      if (btn.tagName === 'BUTTON') btn.textContent = original;
      btn.classList.remove('copied-flash');
    }, 1400);
  }

  function copyPostLink(postId, btn) {
    const input = document.getElementById('post-link-' + postId);
    const url = input ? input.value : (window.location.origin + '/posts.php#post-' + postId);
    const done = function () { flashCopied(btn, 'Copied!'); };
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(url).then(done).catch(function () {
        if (input) { input.select(); document.execCommand('copy'); done(); }
      });
    } else if (input) {
      input.select();
      document.execCommand('copy');
      done();
    }
  }

  if (feed) {
    feed.addEventListener('click', function (e) {
      const likeBtn = e.target.closest('[data-like-btn]');
      if (likeBtn) {
        const postId = likeBtn.getAttribute('data-post-id');
        likeBtn.disabled = true;
        postAjax({ action: 'toggle_like', post_id: postId })
          .then(function (data) {
            if (!data.ok) { alert(data.error || 'Could not update like.'); return; }
            const glyph = likeBtn.querySelector('.post-icon-glyph');
            likeBtn.classList.toggle('liked', data.liked);
            likeBtn.title = data.liked ? 'Unlike' : 'Like';
            if (glyph) glyph.textContent = data.liked ? '♥' : '♡';
            const countEl = likeBtn.querySelector('.post-stats-likes .count');
            if (countEl) countEl.textContent = data.count;
          })
          .catch(function () { alert('Network error — please try again.'); })
          .finally(function () { likeBtn.disabled = false; });
        return;
      }

      const saveBtn = e.target.closest('[data-save-btn]');
      if (saveBtn) {
        const postId = saveBtn.getAttribute('data-post-id');
        saveBtn.disabled = true;
        postAjax({ action: 'toggle_save', post_id: postId })
          .then(function (data) {
            if (!data.ok) { alert(data.error || 'Could not update save.'); return; }
            const glyph = saveBtn.querySelector('.post-icon-glyph');
            saveBtn.classList.toggle('saved', data.saved);
            saveBtn.title = data.saved ? 'Remove from saved' : 'Save';
            if (glyph) glyph.textContent = data.saved ? '🔖' : '📑';
            const countEl = saveBtn.querySelector('.post-stats-saves .count');
            if (countEl) countEl.textContent = data.count;
          })
          .catch(function () { alert('Network error — please try again.'); })
          .finally(function () { saveBtn.disabled = false; });
        return;
      }

      const copyLinkBtn = e.target.closest('[data-copy-link]');
      if (copyLinkBtn) {
        copyPostLink(copyLinkBtn.getAttribute('data-post-id'), copyLinkBtn);
        return;
      }

      const shareBtn = e.target.closest('[data-share-btn]');
      if (shareBtn) {
        openShareModal(shareBtn.getAttribute('data-post-id'));
        return;
      }

      const commentDeleteBtn = e.target.closest('[data-comment-delete]');
      if (commentDeleteBtn) {
        if (!confirm('Delete this comment?')) return;
        const commentId = commentDeleteBtn.getAttribute('data-comment-id');
        const postId = commentDeleteBtn.getAttribute('data-post-id');
        postAjax({ action: 'delete_comment', comment_id: commentId, post_id: postId })
          .then(function (data) {
            if (!data.ok) { alert(data.error || 'Could not delete comment.'); return; }
            const row = document.getElementById('comment-' + commentId);
            if (row) row.remove();
            const list = document.getElementById('post-comments-' + postId);
            const newCount = list ? list.querySelectorAll('.post-comment').length : 0;
            if (list && newCount === 0) list.style.display = 'none';
            setCommentsHeaderCount(postId, newCount);
            const stats = document.getElementById('post-stats-' + postId);
            if (stats) {
              const countEl = stats.querySelector('.post-stats-comments .count');
              if (countEl) countEl.textContent = newCount;
            }
          })
          .catch(function () { alert('Network error — please try again.'); });
      }
    });

    feed.addEventListener('submit', function (e) {
      const form = e.target.closest('[data-comment-form]');
      if (!form) return;
      e.preventDefault();
      const postId = form.getAttribute('data-post-id');
      const input = form.querySelector('input[name="comment"]');
      const text = input ? input.value.trim() : '';
      if (!text) return;
      const sendBtn = form.querySelector('.post-comment-send');
      if (sendBtn) sendBtn.disabled = true;

      postAjax({ action: 'add_comment', post_id: postId, comment: text })
        .then(function (data) {
          if (!data.ok) { alert(data.error || 'Could not add comment.'); return; }
          const list = document.getElementById('post-comments-' + postId);
          if (list) {
            list.style.display = '';
            list.insertAdjacentHTML('beforeend', data.html);
          }
          if (input) input.value = '';
          setCommentsHeaderCount(postId, data.comment_count);
          const stats = document.getElementById('post-stats-' + postId);
          if (stats) {
            const countEl = stats.querySelector('.post-stats-comments .count');
            if (countEl) countEl.textContent = data.comment_count;
          }
        })
        .catch(function () { alert('Network error — please try again.'); })
        .finally(function () { if (sendBtn) sendBtn.disabled = false; });
    });
  }

  // ---- Share modal: open with a client-built preview of the post being shared, submit over fetch() ----
  const shareOverlay = document.getElementById('postShareModalOverlay');
  const shareForm = document.getElementById('postShareForm');
  const shareTargetInput = document.getElementById('postShareTargetId');
  const sharePreview = document.getElementById('postSharePreview');
  const shareCaptionInput = document.getElementById('postShareCaptionInput');
  const shareError = document.getElementById('postShareError');
  const shareSubmitBtn = document.getElementById('postShareSubmitBtn');
  const shareCloseBtn = document.getElementById('postShareModalCloseBtn');

  function showShareError(msg) {
    if (!shareError) return;
    shareError.textContent = msg;
    shareError.style.display = msg ? 'block' : 'none';
  }

  function closeShareModal() {
    if (shareOverlay) shareOverlay.classList.remove('open');
  }

  function openShareModal(postId) {
    const card = document.getElementById('post-' + postId);
    if (!card || !shareOverlay) return;
    shareTargetInput.value = postId;
    if (shareCaptionInput) shareCaptionInput.value = '';
    showShareError('');

    const authorName = card.querySelector('.post-author') ? card.querySelector('.post-author').textContent : '';
    const avatarEl = card.querySelector('.post-avatar');
    const avatarHtml = avatarEl ? avatarEl.innerHTML : '';
    const avatarStyle = avatarEl ? avatarEl.getAttribute('style') : '';
    const timeEl = card.querySelector('.post-meta span[title]');
    const timeText = timeEl ? timeEl.textContent : '';
    const captionEl = card.querySelector('.post-caption');
    const captionText = captionEl ? captionEl.textContent : '';
    const mediaEl = card.querySelector('.post-image-wrap');
    const mediaHtml = mediaEl ? mediaEl.outerHTML : '';

    if (sharePreview) {
      sharePreview.innerHTML =
        '<div class="post-shared-embed">' +
          '<div class="post-shared-head">' +
            '<span class="post-avatar" style="width:32px;height:32px;font-size:0.8rem;' + (avatarStyle || '') + '">' + avatarHtml + '</span>' +
            '<div class="post-headtext">' +
              '<div class="post-author" style="font-size:0.85rem;">' + authorName + '</div>' +
              '<div class="post-meta"><span>' + timeText + '</span></div>' +
            '</div>' +
          '</div>' +
          (captionText ? '<p class="post-shared-caption">' + captionText + '</p>' : '') +
          mediaHtml +
        '</div>';
    }

    shareOverlay.classList.add('open');
  }

  if (shareCloseBtn) shareCloseBtn.addEventListener('click', closeShareModal);
  if (shareOverlay) {
    shareOverlay.addEventListener('click', function (e) { if (e.target === shareOverlay) closeShareModal(); });
  }
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && shareOverlay && shareOverlay.classList.contains('open')) closeShareModal();
  });

  if (shareForm) {
    shareForm.addEventListener('submit', function (e) {
      e.preventDefault();
      showShareError('');
      shareSubmitBtn.disabled = true;
      const originalLabel = shareSubmitBtn.textContent;
      shareSubmitBtn.textContent = 'Sharing…';

      fetch('/posts.php', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: new FormData(shareForm),
      })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (data.ok) {
            if (feedEmpty) feedEmpty.remove();
            if (feed) feed.insertAdjacentHTML('afterbegin', data.html);
            closeShareModal();
            const newCard = document.getElementById('post-' + data.id);
            if (newCard) newCard.scrollIntoView({ behavior: 'smooth', block: 'start' });
          } else {
            showShareError(data.error || 'Could not share — please try again.');
          }
        })
        .catch(function () {
          showShareError('Network error — please check your connection and try again.');
        })
        .finally(function () {
          shareSubmitBtn.disabled = false;
          shareSubmitBtn.textContent = originalLabel;
        });
    });
  }

  // ---- Arrived from a school page's "Share" link (/posts.php?share=ID) — open that post's Share modal automatically ----
  <?php $autoShareId = (int) ($_GET['share'] ?? 0); ?>
  <?php if ($autoShareId): ?>
    setTimeout(function () { openShareModal(<?php echo $autoShareId; ?>); }, 50);
  <?php endif; ?>

  // ---- Realtime: poll for new posts + refresh like/comment counts set by other people ----
  if (feed) {
    var lastMaxId = parseInt(feed.getAttribute('data-max-id') || '0', 10);
    var polling = false;
    var pausedForVisibility = false;

    document.addEventListener('visibilitychange', function () {
      pausedForVisibility = document.hidden;
    });

    function collectWatchIds() {
      var ids = [];
      feed.querySelectorAll('.post-card[id^="post-"]').forEach(function (el) {
        var id = el.id.replace('post-', '');
        if (id) ids.push(id);
      });
      return ids.slice(0, 40).join(',');
    }

    function applyLiveCounts(counts) {
      if (!counts) return;
      Object.keys(counts).forEach(function (postId) {
        var data = counts[postId];
        var likeBtn = feed.querySelector('.post-like-btn[data-post-id="' + postId + '"]');
        if (likeBtn && document.activeElement !== likeBtn) {
          var glyph = likeBtn.querySelector('.post-icon-glyph');
          likeBtn.classList.toggle('liked', !!data.liked_by_me);
          if (glyph) glyph.textContent = data.liked_by_me ? '♥' : '♡';
          var lc = likeBtn.querySelector('.post-stats-likes .count'); if (lc) lc.textContent = data.like_count;
        }
        var stats = document.getElementById('post-stats-' + postId);
        if (stats) {
          var cc = stats.querySelector('.post-stats-comments .count'); if (cc) cc.textContent = data.comment_count;
        }
        setCommentsHeaderCount(postId, data.comment_count);
      });
    }

    function pollFeed() {
      if (polling || pausedForVisibility) return;
      if (overlay && overlay.classList.contains('open')) return; // don't reflow the feed while composing
      polling = true;
      postAjax({ action: 'poll', after_id: lastMaxId, watch_ids: collectWatchIds() })
        .then(function (data) {
          if (!data || !data.ok) return;
          if (data.new_posts && data.new_posts.length) {
            if (feedEmpty && feedEmpty.parentNode) feedEmpty.remove();
            data.new_posts.forEach(function (item) {
              if (document.getElementById('post-' + item.id)) return; // e.g. the post we just created ourselves
              feed.insertAdjacentHTML('afterbegin', item.html);
              var card = document.getElementById('post-' + item.id);
              if (card) {
                card.classList.add('post-card-new');
                setTimeout(function () { card.classList.remove('post-card-new'); }, 1800);
              }
              if (item.id > lastMaxId) lastMaxId = item.id;
            });
          }
          applyLiveCounts(data.counts);
        })
        .catch(function () {})
        .finally(function () { polling = false; });
    }

    setInterval(pollFeed, 6000);
  }
})();
</script>

<?php include __DIR__ . '/includes/drive_shell_bottom.php'; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>
