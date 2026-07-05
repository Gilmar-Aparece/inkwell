<?php
/**
 * Sidebar table of contents. Expects (optionally) $activeCat and $activeSlug
 * set by the including page to highlight the current lesson.
 */
$__sidebarActiveCat = $activeCat ?? null;
$__sidebarActiveSlug = $activeSlug ?? null;
$__navCategories = inkwell_categories();
?>
<aside class="sidebar" id="sidebar">
  <div class="sidebar-header">
    <span>Contents</span>
    <button class="sidebar-close" id="sidebarClose" aria-label="Close contents">✕</button>
  </div>
  <?php foreach ($__navCategories as $__navCatKey => $__navCat): ?>
    <div class="sidebar-group">
      <div class="sidebar-group-label">
        <span class="dot" style="background:<?php echo $__navCat['color']; ?>;"></span>
        <?php echo htmlspecialchars($__navCat['label']); ?>
      </div>
      <?php $__navIndex = 1; foreach ($__navCat['lessons'] as $__navSlug => $__navLesson): ?>
        <a class="sidebar-link<?php echo ($__sidebarActiveCat === $__navCatKey && $__sidebarActiveSlug === $__navSlug) ? ' active' : ''; ?>"
           href="/lesson.php?cat=<?php echo urlencode($__navCatKey); ?>&slug=<?php echo urlencode($__navSlug); ?>">
          <span class="num"><?php echo str_pad($__navIndex, 2, '0', STR_PAD_LEFT); ?></span>
          <span><?php echo htmlspecialchars($__navLesson['title']); ?></span>
        </a>
      <?php $__navIndex++; endforeach; ?>
    </div>
  <?php endforeach; ?>
</aside>
