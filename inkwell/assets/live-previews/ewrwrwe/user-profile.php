<?php
/**
 * Returns a rendered HTML fragment with a community-feed author's public
 * profile — used by the clickable name/avatar popup on posts.php, same
 * idea as Facebook clicking a poster's name. Any logged-in user (student,
 * teacher, dean, admin) can view any other logged-in user's basic info
 * and their recent posts.
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/posts.php';

header('Content-Type: application/json');

$viewer = inkwell_current_user();
if (!$viewer) {
  http_response_code(401);
  echo json_encode(['ok' => false, 'error' => 'Please log in to view profiles.']);
  exit;
}
$isAdmin = $viewer['role'] === 'admin';

$userId = (int) ($_GET['id'] ?? 0);
$profile = $userId ? inkwell_get_post_author_profile($userId, $viewer['id'], $isAdmin) : null;

if (!$profile) {
  http_response_code(404);
  echo json_encode(['ok' => false, 'error' => 'User not found.']);
  exit;
}

$a = $profile['author'];
$grads = inkwell_post_avatar_gradients();
$aGrad = inkwell_avatar_gradient($grads, (int) $a['id']);

ob_start();
?>
<div class="student-profile-head">
  <span class="post-avatar" style="width:60px;height:60px;font-size:1.3rem;background:<?php echo $aGrad; ?>;">
    <?php if (!empty($a['avatar'])): ?>
      <img src="/assets/uploads/<?php echo htmlspecialchars($a['avatar']); ?>" alt="<?php echo htmlspecialchars($a['name']); ?>" loading="lazy">
    <?php else: ?>
      <?php echo strtoupper(substr($a['name'], 0, 1)); ?>
    <?php endif; ?>
  </span>
  <div>
    <h2><?php echo htmlspecialchars($a['name']); ?></h2>
    <span class="admin-sub">
      <span class="post-role-chip role-<?php echo htmlspecialchars($a['role']); ?>"><?php echo htmlspecialchars($a['role']); ?></span>
      <?php if (!empty($a['course'])): ?> · <?php echo htmlspecialchars($a['course']); ?><?php endif; ?>
    </span>
  </div>
</div>

<div class="stat-row">
  <div class="stat-pill"><strong><?php echo (int) $profile['post_count']; ?></strong><span>Posts</span></div>
  <div class="stat-pill"><strong><?php echo htmlspecialchars(date('M Y', strtotime($a['created_at']))); ?></strong><span>Joined</span></div>
</div>

<a class="btn" href="/profile.php?id=<?php echo (int) $a['id']; ?>" style="display:block;text-align:center;margin:0 0 16px;">View full profile →</a>

<div class="student-profile-section">
  <h3>Posts</h3>
  <?php if (empty($profile['posts'])): ?>
    <p class="admin-sub" style="margin:0;">No posts yet.</p>
  <?php else: ?>
    <div class="post-profile-grid">
      <?php foreach ($profile['posts'] as $p): ?>
        <a class="post-profile-preview" href="/posts.php#post-<?php echo (int) $p['id']; ?>">
          <?php if (!empty($p['video'])): ?>
            <div class="post-profile-preview-media"><video src="/assets/uploads/<?php echo htmlspecialchars($p['video']); ?>" preload="metadata"></video><span class="post-profile-preview-play">▶</span></div>
          <?php elseif (!empty($p['image'])): ?>
            <div class="post-profile-preview-media"><img src="/assets/uploads/<?php echo htmlspecialchars($p['image']); ?>" alt="" loading="lazy"></div>
          <?php endif; ?>
          <?php if (!empty($p['caption'])): ?>
            <p class="post-profile-preview-caption"><?php echo htmlspecialchars(mb_strimwidth($p['caption'], 0, 90, '…')); ?></p>
          <?php endif; ?>
          <div class="post-profile-preview-meta">
            <span><?php echo htmlspecialchars(inkwell_time_ago($p['created_at'])); ?></span>
            <span>♥ <?php echo (int) $p['like_count']; ?> · 💬 <?php echo (int) $p['comment_count']; ?></span>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
<?php
$html = ob_get_clean();

echo json_encode(['ok' => true, 'html' => $html]);
