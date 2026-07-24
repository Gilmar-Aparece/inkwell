<?php
/**
 * Reusable notification bell — markup only. The including page/partial
 * must set, right before including this file:
 *   $__notifItems   array of rows from inkwell_list_notifications()
 *   $__notifUnread  int from inkwell_count_unread_notifications()
 * (see includes/drive_shell_top.php and includes/dash_nav.php, which both
 * compute these once and then include this file — dash_nav.php includes it
 * twice, for the mobile row and the desktop sidebar, same as it already
 * does with the .theme-toggle button.)
 * JS behavior lives in assets/js/app.js under "Notification bell dropdown".
 */
?>
<div class="notif-bell-wrap">
  <button type="button" class="notif-bell-trigger" aria-haspopup="true" aria-expanded="false" aria-label="Notifications">
    <span aria-hidden="true">🔔</span>
    <?php if ($__notifUnread > 0): ?><span class="notif-bell-badge"><?php echo $__notifUnread > 9 ? '9+' : (int) $__notifUnread; ?></span><?php endif; ?>
  </button>
  <div class="notif-bell-panel">
    <div class="notif-bell-panel-head">
      <strong>Notifications</strong>
      <?php if ($__notifUnread > 0): ?><button type="button" class="notif-bell-markall">Mark all read</button><?php endif; ?>
    </div>
    <div class="notif-bell-list">
      <?php if (!$__notifItems): ?>
        <p class="notif-bell-empty">You're all caught up.</p>
      <?php else: ?>
        <?php foreach ($__notifItems as $__n): ?>
          <a href="<?php echo htmlspecialchars($__n['link'] ?: '#'); ?>" class="notif-bell-item<?php echo empty($__n['is_read']) ? ' unread' : ''; ?>" data-notif-id="<?php echo (int) $__n['id']; ?>">
            <span class="notif-bell-item-dot" aria-hidden="true"></span>
            <span class="notif-bell-item-body">
              <span class="notif-bell-item-msg"><?php echo htmlspecialchars($__n['message']); ?></span>
              <span class="notif-bell-item-time"><?php echo htmlspecialchars(inkwell_notif_time_ago($__n['created_at'])); ?></span>
            </span>
          </a>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</div>
