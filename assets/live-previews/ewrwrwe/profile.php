<?php
// Inside/dashboard page — suppress the marketing topbar (see includes/header.php).
$__hideTopbar = true;
/**
 * A user's full public Timeline — same idea as clicking someone's name
 * on Facebook: a real page showing their info plus every post they've
 * shared (not just the handful in the quick-view popup on posts.php).
 * Any logged-in user can view any other logged-in user's Timeline.
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/posts.php';

$viewer = inkwell_require_login();
$isAdmin = $viewer['role'] === 'admin';
$ajax = inkwell_is_ajax();

define('PROFILE_PAGE_SIZE', 10);

$userId = (int) ($_GET['id'] ?? 0);
$author = $userId ? inkwell_get_user($userId) : null;

// ---- AJAX: "Load more" pagination for the post list ----
if ($ajax && ($_POST['action'] ?? '') === 'load_more') {
  $offset = max(0, (int) ($_POST['offset'] ?? 0));
  $posts = $author ? inkwell_list_posts_by_user($userId, $viewer['id'], PROFILE_PAGE_SIZE, $offset) : [];
  $html = '';
  foreach ($posts as $p) $html .= inkwell_render_post_card($p, $viewer, $isAdmin);
  inkwell_json_response(['ok' => true, 'html' => $html, 'has_more' => count($posts) === PROFILE_PAGE_SIZE]);
  exit;
}

if (!$author) {
  http_response_code(404);
  $pageTitle = 'Profile not found';
  include __DIR__ . '/includes/header.php';
  $driveActive = 'community';
  $driveCrumbs = [['label' => 'Home', 'href' => '/index.php'], ['label' => 'Profile not found']];
  include __DIR__ . '/includes/drive_shell_top.php';
  echo '<p class="admin-sub">That user doesn\'t exist, or may have been removed. <a href="/posts.php">Back to Community →</a></p>';
  include __DIR__ . '/includes/drive_shell_bottom.php';
  include __DIR__ . '/includes/footer.php';
  exit;
}

$postCount = inkwell_count_user_posts($userId);
$posts = inkwell_list_posts_by_user($userId, $viewer['id'], PROFILE_PAGE_SIZE, 0);
$hasMore = count($posts) === PROFILE_PAGE_SIZE && $postCount > PROFILE_PAGE_SIZE;
$grads = inkwell_post_avatar_gradients();
$aGrad = inkwell_avatar_gradient($grads, (int) $author['id']);

$pageTitle = $author['name'];
include __DIR__ . '/includes/header.php';
$driveActive = 'community';
$driveCrumbs = [['label' => 'Home', 'href' => '/index.php'], ['label' => 'Community', 'href' => '/posts.php'], ['label' => $author['name']]];
$driveHideCta = true;
include __DIR__ . '/includes/drive_shell_top.php';
?>

<div class="school-page-head">
  <span class="post-avatar" style="width:72px;height:72px;font-size:1.5rem;background:<?php echo $aGrad; ?>;">
    <?php if (!empty($author['avatar'])): ?>
      <img src="/assets/uploads/<?php echo htmlspecialchars($author['avatar']); ?>" alt="<?php echo htmlspecialchars($author['name']); ?>" loading="lazy">
    <?php else: ?>
      <?php echo strtoupper(substr($author['name'], 0, 1)); ?>
    <?php endif; ?>
  </span>
  <div class="school-page-head-text">
    <h1 class="drive-title" style="margin:0;"><?php echo htmlspecialchars($author['name']); ?></h1>
    <span class="dean-line">
      <span class="post-role-chip role-<?php echo htmlspecialchars($author['role']); ?>"><?php echo htmlspecialchars($author['role']); ?></span>
      <?php if (!empty($author['course'])): ?> · <?php echo htmlspecialchars($author['course']); ?><?php endif; ?>
    </span>
  </div>
</div>

<div class="stat-row school-page-stats">
  <div class="stat-pill"><strong><?php echo (int) $postCount; ?></strong><span>Posts</span></div>
  <div class="stat-pill"><strong><?php echo htmlspecialchars(date('M Y', strtotime($author['created_at']))); ?></strong><span>Joined</span></div>
</div>

<section class="admin-card glass-card" id="profileTimeline" style="margin-top:20px;">
  <h2>Posts</h2>
  <?php if (empty($posts)): ?>
    <p class="admin-sub" id="profileTimelineEmpty" style="margin:0;">No posts yet.</p>
  <?php else: ?>
    <div id="profileTimelinePosts">
      <?php foreach ($posts as $post) echo inkwell_render_post_card($post, $viewer, $isAdmin); ?>
    </div>
  <?php endif; ?>
  <button type="button" class="btn" id="profileLoadMoreBtn" data-offset="<?php echo PROFILE_PAGE_SIZE; ?>" data-user-id="<?php echo (int) $userId; ?>" style="<?php echo $hasMore ? '' : 'display:none;'; ?> margin-top:14px;">Load more</button>
</section>

<script>
(function () {
  const btn = document.getElementById('profileLoadMoreBtn');
  if (!btn) return;
  const list = document.getElementById('profileTimelinePosts') || (function () {
    const section = document.getElementById('profileTimeline');
    const div = document.createElement('div');
    div.id = 'profileTimelinePosts';
    const empty = document.getElementById('profileTimelineEmpty');
    if (empty) empty.remove();
    section.insertBefore(div, btn);
    return div;
  })();

  btn.addEventListener('click', function () {
    btn.disabled = true;
    btn.textContent = 'Loading…';
    const body = new FormData();
    body.append('action', 'load_more');
    body.append('offset', btn.getAttribute('data-offset'));
    fetch('/profile.php?id=' + encodeURIComponent(btn.getAttribute('data-user-id')), {
      method: 'POST',
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      body: body,
    })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        btn.disabled = false;
        btn.textContent = 'Load more';
        if (!res.ok) return;
        list.insertAdjacentHTML('beforeend', res.html);
        btn.setAttribute('data-offset', parseInt(btn.getAttribute('data-offset'), 10) + <?php echo PROFILE_PAGE_SIZE; ?>);
        btn.style.display = res.has_more ? '' : 'none';
      })
      .catch(function () {
        btn.disabled = false;
        btn.textContent = 'Load more';
      });
  });
})();
</script>

<?php include __DIR__ . '/includes/drive_shell_bottom.php'; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>
