<?php
// Inside/dashboard page — suppress the marketing topbar (see includes/header.php).
$__hideTopbar = true;
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/posts.php';

$user = inkwell_require_login();
$isAdmin = $user['role'] === 'admin';

/** Only allow redirecting back to a same-site path — never an absolute/external URL. */
function inkwell_safe_return_path($raw, $fallback) {
  $raw = trim((string) $raw);
  if ($raw === '' || $raw[0] !== '/' || (isset($raw[1]) && $raw[1] === '/')) return $fallback;
  return $raw;
}

$postId = (int) ($_GET['id'] ?? $_POST['post_id'] ?? 0);
$returnTo = inkwell_safe_return_path($_GET['return'] ?? $_POST['return'] ?? '', '/posts.php');

$post = $postId ? inkwell_get_post($postId) : null;
if (!$post || (!$isAdmin && (int) $post['user_id'] !== (int) $user['id'])) {
  inkwell_flash_set('error', 'That post could not be found or you don\'t have permission to edit it.');
  header('Location: /posts.php');
  exit;
}

// Text styling (bold/align/background) only applies to text-only posts —
// same rule the composer and inkwell_create_post() use — photo/video posts
// just get a plain caption.
$isTextOnly = empty($post['video']) && empty($post['image']);
$bgTemplates = inkwell_post_bg_templates();
$avatarGradients = inkwell_post_avatar_gradients();
$postAuthor = inkwell_get_user((int) $post['user_id']);
$authorGrad = inkwell_avatar_gradient($avatarGradients, (int) $post['user_id']);
$authorAvatarHtml = !empty($postAuthor['avatar'])
  ? '<img src="/assets/uploads/' . htmlspecialchars($postAuthor['avatar']) . '" alt="" loading="lazy">'
  : strtoupper(substr($postAuthor['name'] ?? '?', 0, 1));

$error = '';
$captionValue = $post['caption'] ?? '';
$textAlign = in_array($post['text_align'] ?? 'left', ['left', 'center', 'right'], true) ? $post['text_align'] : 'left';
$textBold = !empty($post['text_bold']);
$bgTemplate = $isTextOnly && !empty($post['bg_template']) && isset($bgTemplates[$post['bg_template']]) ? $post['bg_template'] : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $captionValue = $_POST['caption'] ?? '';
  $textAlign = $_POST['text_align'] ?? 'left';
  $textBold = !empty($_POST['text_bold']);
  $bgTemplate = $_POST['bg_template'] ?? '';

  $result = inkwell_edit_post($postId, $user['id'], $captionValue, $textAlign, $textBold, $isAdmin, $bgTemplate);
  if ($result['ok']) {
    inkwell_flash_set('notice', 'Post updated.');
    header('Location: ' . $returnTo . (strpos($returnTo, '#') === false ? '#post-' . $postId : ''));
    exit;
  }
  $error = $result['error'];
}

$pageTitle = 'Edit post';
include __DIR__ . '/includes/header.php';
$driveActive = 'community';
$driveCrumbs = [['label' => 'Home', 'href' => '/index.php'], ['label' => 'Community', 'href' => '/posts.php'], ['label' => 'Edit post']];
$driveTitle = 'Edit post';
$driveSubtitle = 'Update your post below, then save to go back to where you were.';
include __DIR__ . '/includes/drive_shell_top.php';
?>

<div class="admin-card glass-card" style="max-width:640px;">
  <?php if ($error): ?><div class="exam-result fail"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

  <form method="post" action="/edit-post.php" class="admin-form" id="editPostForm">
    <input type="hidden" name="post_id" value="<?php echo (int) $postId; ?>">
    <input type="hidden" name="return" value="<?php echo htmlspecialchars($returnTo); ?>">

    <div class="post-modal-user">
      <span class="post-composer-avatar" style="background:<?php echo $authorGrad; ?>;"><?php echo $authorAvatarHtml; ?></span>
      <div>
        <div class="post-modal-user-name"><?php echo htmlspecialchars($postAuthor['name'] ?? 'Unknown'); ?></div>
        <span class="post-modal-audience">🌐 Community</span>
      </div>
    </div>

    <?php if ($isTextOnly): ?>
      <div class="post-editor-toolbar" id="postEditorToolbar">
        <button type="button" class="post-editor-btn<?php echo $textBold ? ' active' : ''; ?>" id="postBoldBtn" title="Bold"><strong>B</strong></button>
        <span class="post-editor-sep"></span>
        <button type="button" class="post-editor-btn<?php echo $textAlign === 'left' ? ' active' : ''; ?>" data-align="left" title="Align left">L</button>
        <button type="button" class="post-editor-btn<?php echo $textAlign === 'center' ? ' active' : ''; ?>" data-align="center" title="Align center">C</button>
        <button type="button" class="post-editor-btn<?php echo $textAlign === 'right' ? ' active' : ''; ?>" data-align="right" title="Align right">R</button>
      </div>
    <?php endif; ?>

    <textarea name="caption" maxlength="5000" placeholder="What's on your mind?" id="postCaptionInput" class="post-caption-editor<?php echo $bgTemplate ? ' post-tpl-' . htmlspecialchars($bgTemplate) : ''; ?>" rows="6" style="width:100%; text-align:<?php echo htmlspecialchars($textAlign); ?>; font-weight:<?php echo $textBold ? '800' : '400'; ?>;"><?php echo htmlspecialchars($captionValue); ?></textarea>

    <?php if ($isTextOnly): ?>
      <div class="post-template-row" id="postTemplateRow">
        <button type="button" class="post-template-swatch none<?php echo $bgTemplate === '' ? ' active' : ''; ?>" data-template="" title="No background">Aa</button>
        <?php foreach ($bgTemplates as $tplId => $tplGradient): ?>
          <button type="button" class="post-template-swatch<?php echo $bgTemplate === $tplId ? ' active' : ''; ?>" data-template="<?php echo htmlspecialchars($tplId); ?>" style="background:<?php echo $tplGradient; ?>;" title="Background template <?php echo htmlspecialchars($tplId); ?>">Aa</button>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <input type="hidden" name="text_align" id="postTextAlign" value="<?php echo htmlspecialchars($textAlign); ?>">
    <input type="hidden" name="text_bold" id="postTextBold" value="<?php echo $textBold ? '1' : '0'; ?>">
    <input type="hidden" name="bg_template" id="postBgTemplate" value="<?php echo htmlspecialchars($bgTemplate); ?>">

    <div style="display:flex; justify-content:flex-end; gap:8px; margin-top:16px;">
      <a class="btn" href="<?php echo htmlspecialchars($returnTo . (strpos($returnTo, '#') === false ? '#post-' . $postId : '')); ?>">Cancel</a>
      <button type="submit" class="btn primary">Save</button>
    </div>
  </form>
</div>

<?php if ($isTextOnly): ?>
<script>
(function () {
  const textarea = document.getElementById('postCaptionInput');
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
      templateInput.value = sw.getAttribute('data-template') || '';
      templateSwatches.forEach(function (s) { s.classList.toggle('active', s === sw); });
      applyEditorStyle();
    });
  });
})();
</script>
<?php endif; ?>

<?php include __DIR__ . '/includes/drive_shell_bottom.php'; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>
