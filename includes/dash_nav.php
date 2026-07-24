<?php
/**
 * Shared dashboard section nav — a sticky sidebar on desktop and a dropdown
 * panel on mobile. The including page must set, before requiring this file:
 *   $dashNavItems  array of ['key','href','label','icon','count' (optional), 'group' (optional)]
 *                  Items are shown in array order; a label is printed above
 *                  the first item of each new 'group' value so the sidebar
 *                  reads as sections instead of one long flat list.
 *   $dashNavActive string matching one item's 'key'
 *   $dashNavTitle  string shown above the sidebar links (optional)
 */
$__dashActiveItem = null;
foreach ($dashNavItems as $__it) {
  if ($__it['key'] === $dashNavActive) { $__dashActiveItem = $__it; break; }
}
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/notifications.php';
$__dashMe = inkwell_current_user();
$__notifItems = $__dashMe ? inkwell_list_notifications($__dashMe['id']) : [];
$__notifUnread = $__dashMe ? inkwell_count_unread_notifications($__dashMe['id']) : 0;
?>
<div class="dash-mobile-nav">
  <div class="dash-mobile-nav-row">
    <a href="/index.php" class="dash-back-arrow" aria-label="Back to Lessons" title="Back to Lessons">←</a>
    <button type="button" class="dash-mobile-menu-btn" id="dashMobileMenuToggle" aria-label="Open menu" aria-expanded="false" aria-controls="dashSidebar">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>
    <a href="/index.php" class="dash-mobile-brand" aria-label="Inkwell home">
      <span class="dash-mobile-brand-mark" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M18.5 2.5c1 1 1.1 2.6.2 3.7L9.8 16.9l-4.3 1.1 1.1-4.3L16.5 3c1.1-1 2.7-1.1 3.7-.2z" fill="#fff"/>
          <path d="M5.5 18l-2 3.5 3.5-2-1.5-1.5z" fill="#fff" opacity="0.55"/>
        </svg>
      </span>
    </a>
    <div class="dash-mobile-nav-title">
      <?php if ($__dashActiveItem): ?>
        <?php echo $__dashActiveItem['icon']; ?> <?php echo htmlspecialchars($__dashActiveItem['label']); ?>
      <?php else: ?>
        <?php echo htmlspecialchars($dashNavTitle ?? 'Dashboard'); ?>
      <?php endif; ?>
    </div>
    <?php if ($__dashMe): ?><?php include __DIR__ . '/notifications_bell.php'; ?><?php endif; ?>
    <button type="button" class="dash-theme-toggle theme-toggle" title="Toggle dark mode" aria-label="Toggle dark mode">◐</button>
  </div>
</div>
<div class="dash-sidebar-backdrop" id="dashSidebarBackdrop"></div>
<aside class="dash-sidebar" id="dashSidebar">
  <a href="/index.php" class="dash-back-link">
    <span aria-hidden="true">←</span> Back to Lessons
  </a>
  <div class="dash-sidebar-head">
    <div class="dash-sidebar-label"><?php echo htmlspecialchars($dashNavTitle ?? 'Dashboard'); ?></div>
    <?php if ($__dashMe): ?><?php include __DIR__ . '/notifications_bell.php'; ?><?php endif; ?>
    <button type="button" class="dash-theme-toggle theme-toggle" title="Toggle dark mode" aria-label="Toggle dark mode">◐</button>
  </div>
  <nav>
    <?php $__dashPrevGroup = null; foreach ($dashNavItems as $__it): ?>
      <?php if (!empty($__it['group']) && $__it['group'] !== $__dashPrevGroup): $__dashPrevGroup = $__it['group']; ?>
        <div class="dash-nav-group-label"><?php echo htmlspecialchars($__it['group']); ?></div>
      <?php endif; ?>
      <a class="dash-nav-link<?php echo $__it['key'] === $dashNavActive ? ' active' : ''; ?>" href="<?php echo htmlspecialchars($__it['href']); ?>">
        <span class="dash-nav-icon"><?php echo $__it['icon']; ?></span>
        <span class="dash-nav-label"><?php echo htmlspecialchars($__it['label']); ?></span>
        <?php if (isset($__it['count'])): ?><span class="dash-nav-count"><?php echo (int) $__it['count']; ?></span><?php endif; ?>
      </a>
    <?php endforeach; ?>
  </nav>
</aside>
