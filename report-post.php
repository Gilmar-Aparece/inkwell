<?php
// Inside/dashboard page — suppress the marketing topbar (see includes/header.php).
$__hideTopbar = true;
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/posts.php';

$user = inkwell_require_login();

/** Only allow redirecting back to a same-site path — never an absolute/external URL. */
function inkwell_safe_return_path($raw, $fallback) {
  $raw = trim((string) $raw);
  if ($raw === '' || $raw[0] !== '/' || (isset($raw[1]) && $raw[1] === '/')) return $fallback;
  return $raw;
}

$postId = (int) ($_GET['id'] ?? $_POST['post_id'] ?? 0);
$returnTo = inkwell_safe_return_path($_GET['return'] ?? $_POST['return'] ?? '', '/posts.php');

$post = $postId ? inkwell_get_post_full($postId, $user['id']) : null;
if (!$post) {
  inkwell_flash_set('error', 'That post could not be found.');
  header('Location: /posts.php');
  exit;
}

// Quick-pick chips — just fill the textarea with a starting point, they
// don't limit or replace what the person actually types.
$quickReasons = ['Spam', 'Nudity or sexual content', 'Hate speech', 'Harassment or bullying', 'Violence', 'False information'];

$error = '';
$reasonValue = trim((string) ($_POST['reason'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if ($reasonValue === '') {
    $error = "Please describe what's wrong with this post before submitting.";
  } else {
    $result = inkwell_report_post($postId, $user['id'], $reasonValue);
    if ($result['ok']) {
      inkwell_flash_set('notice', 'Thanks — this post has been reported and hidden from your feed.');
      header('Location: ' . $returnTo);
      exit;
    }
    $error = $result['error'];
  }
}

$avatarGradients = inkwell_post_avatar_gradients();
$authorGrad = inkwell_avatar_gradient($avatarGradients, (int) $post['user_id']);
$captionSnippet = trim((string) ($post['caption'] ?? ''));
if (mb_strlen($captionSnippet) > 160) $captionSnippet = mb_substr($captionSnippet, 0, 160) . '…';
$firstImage = $post['images'][0] ?? null;
$previewImage = $firstImage ? (is_array($firstImage) ? ($firstImage['image'] ?? null) : $firstImage) : ($post['image'] ?? null);

$pageTitle = 'Report post';
include __DIR__ . '/includes/header.php';
$driveActive = 'community';
$driveCrumbs = [['label' => 'Home', 'href' => '/index.php'], ['label' => 'Community', 'href' => '/posts.php'], ['label' => 'Report post']];
$driveTitle = 'Report post';
$driveSubtitle = 'Tell us what\'s wrong with this post, in your own words. Reporting it also hides it from your own feed.';
include __DIR__ . '/includes/drive_shell_top.php';
?>
<style>
  .report-post-preview { display: flex; gap: 12px; align-items: center; padding: 12px; border: 1px solid var(--border-soft); border-radius: var(--radius-sm); background: var(--surface-2); margin-bottom: 18px; }
  .report-post-preview-avatar { width: 38px; height: 38px; border-radius: 50%; flex-shrink: 0; display: flex; align-items: center; justify-content: center; color: #fff; font-weight: 700; font-size: 0.9rem; overflow: hidden; }
  .report-post-preview-avatar img { width: 100%; height: 100%; object-fit: cover; }
  .report-post-preview-body { min-width: 0; flex: 1; }
  .report-post-preview-body strong { display: block; font-size: 0.9rem; }
  .report-post-preview-body span { display: block; font-size: 0.82rem; color: var(--ink-dim); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  .report-post-preview-thumb { width: 48px; height: 48px; border-radius: var(--radius-sm); object-fit: cover; flex-shrink: 0; background: var(--surface-1); }

  .report-quick-chips { display: flex; flex-wrap: wrap; gap: 8px; margin: 6px 0 4px; }
  .report-quick-chip { border: 1px solid var(--border-soft); background: var(--surface-2); color: var(--ink-dim); font-size: 0.8rem; font-weight: 600; padding: 6px 12px; border-radius: 999px; cursor: pointer; font-family: inherit; transition: background 0.15s, color 0.15s, border-color 0.15s; }
  .report-quick-chip:hover { background: var(--border-soft); color: var(--ink); }

  .report-reason-count { text-align: right; font-size: 0.74rem; color: var(--ink-dim); margin-top: 4px; }
</style>

<div class="admin-card glass-card" style="max-width:640px;">
  <?php if ($error): ?><div class="exam-result fail"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

  <div class="report-post-preview">
    <span class="report-post-preview-avatar" style="background:<?php echo $authorGrad; ?>;">
      <?php if (!empty($post['author_avatar'])): ?>
        <img src="/assets/uploads/<?php echo htmlspecialchars($post['author_avatar']); ?>" alt="">
      <?php else: ?>
        <?php echo strtoupper(substr($post['author_name'] ?? '?', 0, 1)); ?>
      <?php endif; ?>
    </span>
    <div class="report-post-preview-body">
      <strong><?php echo htmlspecialchars($post['author_name'] ?? 'Unknown'); ?></strong>
      <span><?php echo $captionSnippet !== '' ? htmlspecialchars($captionSnippet) : '(no text — photo/video post)'; ?></span>
    </div>
    <?php if ($previewImage): ?>
      <img class="report-post-preview-thumb" src="/assets/uploads/<?php echo htmlspecialchars($previewImage); ?>" alt="">
    <?php endif; ?>
  </div>

  <form method="post" action="/report-post.php" class="admin-form" id="reportPostForm">
    <input type="hidden" name="post_id" value="<?php echo (int) $postId; ?>">
    <input type="hidden" name="return" value="<?php echo htmlspecialchars($returnTo); ?>">

    <label for="reportReason">What's wrong with this post?</label>
    <div class="report-quick-chips">
      <?php foreach ($quickReasons as $chip): ?>
        <button type="button" class="report-quick-chip" data-quick-reason="<?php echo htmlspecialchars($chip); ?>"><?php echo htmlspecialchars($chip); ?></button>
      <?php endforeach; ?>
    </div>
    <textarea id="reportReason" name="reason" maxlength="255" rows="5" required placeholder="Describe what's wrong with this post, in your own words…" style="width:100%; resize:vertical; border:1px solid var(--border-soft); border-radius:var(--radius-sm); background:var(--surface-2); color:var(--ink); font:inherit; padding:10px 12px;"><?php echo htmlspecialchars($reasonValue); ?></textarea>
    <div class="report-reason-count"><span id="reportReasonCount"><?php echo mb_strlen($reasonValue); ?></span>/255</div>

    <div style="display:flex; justify-content:flex-end; gap:8px; margin-top:10px;">
      <a class="btn" href="<?php echo htmlspecialchars($returnTo); ?>">Cancel</a>
      <button type="submit" class="btn danger">Submit report</button>
    </div>
  </form>
</div>

<script>
  (function () {
    var textarea = document.getElementById('reportReason');
    var count = document.getElementById('reportReasonCount');
    if (textarea && count) {
      textarea.addEventListener('input', function () { count.textContent = textarea.value.length; });
    }
    document.querySelectorAll('[data-quick-reason]').forEach(function (chip) {
      chip.addEventListener('click', function () {
        var text = chip.getAttribute('data-quick-reason');
        if (!textarea) return;
        // Starting point they can still edit — don't stomp on anything they've already typed.
        textarea.value = textarea.value.trim() === '' ? text + ' — ' : (textarea.value.replace(/\s+$/, '') + ', ' + text.toLowerCase());
        textarea.focus();
        textarea.selectionStart = textarea.selectionEnd = textarea.value.length;
        if (count) count.textContent = textarea.value.length;
      });
    });
  })();
</script>

<?php include __DIR__ . '/includes/drive_shell_bottom.php'; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>
