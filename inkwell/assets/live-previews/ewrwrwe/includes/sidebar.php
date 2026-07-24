<?php
/**
 * Sidebar table of contents. Expects (optionally) $activeCat and $activeSlug
 * set by the including page to highlight the current lesson.
 */
$__sidebarActiveCat = $activeCat ?? null;
$__sidebarActiveSlug = $activeSlug ?? null;
$__navCategories = inkwell_categories();
$__sidebarMe = isset($__lessonUser) ? $__lessonUser : inkwell_current_user();
require_once __DIR__ . '/lesson_progress.php';
$__sidebarHasFullAccess = inkwell_user_has_full_lesson_access($__sidebarMe);
$__sidebarFreeCount = inkwell_free_lessons_per_track();
?>
<aside class="sidebar" id="sidebar">
  <div class="sidebar-header">
    <span>Contents</span>
    <button class="sidebar-close" id="sidebarClose" aria-label="Close contents">✕</button>
  </div>

  <?php if ($__sidebarMe): ?>
    <div class="drive-user-wrap sidebar-account">
      <button type="button" class="drive-user" id="driveUserTrigger" aria-haspopup="true" aria-expanded="false" aria-controls="driveUserMenu">
        <span class="drive-user-avatar">
          <?php if (!empty($__sidebarMe['avatar'])): ?>
            <img src="/assets/uploads/<?php echo htmlspecialchars($__sidebarMe['avatar']); ?>" alt="" loading="lazy">
          <?php else: ?>
            <?php echo strtoupper(substr($__sidebarMe['name'], 0, 1)); ?>
          <?php endif; ?>
        </span>
        <div class="drive-user-info">
          <strong><?php echo htmlspecialchars($__sidebarMe['name']); ?></strong>
          <span><?php echo htmlspecialchars(ucfirst($__sidebarMe['role'])); ?></span>
        </div>
        <svg class="drive-user-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>
      </button>
      <div class="drive-user-menu" id="driveUserMenu">
        <a href="/account.php"><span class="drive-nav-icon">👤</span>My profile</a>
        <a href="/account.php#avatar"><span class="drive-nav-icon">🖼️</span>Edit profile photo</a>
        <a href="/notes.php"><span class="drive-nav-icon">📝</span>Notes</a>
        <a href="/posts.php"><span class="drive-nav-icon">🖼️</span>Community</a>
        <a href="/marketplace.php"><span class="drive-nav-icon">🛒</span>Marketplace</a>
        <?php if (in_array($__sidebarMe['role'], ['student', 'teacher'], true)): ?>
          <a href="/sell.php"><span class="drive-nav-icon">💰</span>Sell a system</a>
        <?php endif; ?>
        <a href="/marketplace-library.php"><span class="drive-nav-icon">📦</span>My marketplace dashboard</a>
        <a href="/logout.php" class="drive-user-menu-logout"><span class="drive-nav-icon">↩</span>Log out</a>
      </div>
    </div>
  <?php else: ?>
    <a class="sidebar-account-guest" href="/login.php">👤 Log in</a>
  <?php endif; ?>

  <?php foreach ($__navCategories as $__navCatKey => $__navCat): ?>
    <div class="sidebar-group">
      <div class="sidebar-group-label">
        <span class="dot" style="background:<?php echo $__navCat['color']; ?>;"></span>
        <?php echo htmlspecialchars($__navCat['label']); ?>
      </div>
      <?php $__navIndex = 1; foreach ($__navCat['lessons'] as $__navSlug => $__navLesson): ?>
        <?php $__navLocked = !$__sidebarHasFullAccess && ($__navIndex - 1) >= $__sidebarFreeCount; ?>
        <a class="sidebar-link<?php echo ($__sidebarActiveCat === $__navCatKey && $__sidebarActiveSlug === $__navSlug) ? ' active' : ''; ?>"
           href="/lesson.php?cat=<?php echo urlencode($__navCatKey); ?>&slug=<?php echo urlencode($__navSlug); ?>">
          <span class="num"><?php echo str_pad($__navIndex, 2, '0', STR_PAD_LEFT); ?></span>
          <span><?php echo htmlspecialchars($__navLesson['title']); ?></span>
          <?php if ($__navLocked): ?><span title="Pro Learner lesson">🔒</span><?php endif; ?>
        </a>
      <?php $__navIndex++; endforeach; ?>
    </div>
  <?php endforeach; ?>
</aside>
