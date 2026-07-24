<?php
/**
 * Shared "Dashboard" overview grid — a set of stat/shortcut cards built
 * straight from the same $dashNavItems array the sidebar uses, so it never
 * drifts out of sync with what a role can actually access.
 * The including page must set, before requiring this file:
 *   $dashOverviewItems  array — usually $dashNavItems with the 'dashboard'
 *                       entry itself filtered out
 * Optionally:
 *   $dashOverviewGreeting  string shown above the grid
 */
?>
<?php if (!empty($dashOverviewGreeting)): ?>
  <p class="admin-sub"><?php echo htmlspecialchars($dashOverviewGreeting); ?></p>
<?php endif; ?>
<div class="dash-overview-grid">
  <?php foreach ($dashOverviewItems as $__ov): ?>
    <a class="dash-overview-card" href="<?php echo htmlspecialchars($__ov['href']); ?>">
      <span class="dash-overview-card-icon"><?php echo $__ov['icon']; ?></span>
      <span class="dash-overview-card-label"><?php echo htmlspecialchars($__ov['label']); ?></span>
      <?php if (isset($__ov['count'])): ?>
        <span class="dash-overview-card-count"><?php echo (int) $__ov['count']; ?></span>
      <?php endif; ?>
    </a>
  <?php endforeach; ?>
</div>
