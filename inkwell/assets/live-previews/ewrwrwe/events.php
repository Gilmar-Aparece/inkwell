<?php
// Inside/dashboard page — suppress the marketing topbar (see includes/header.php).
$__hideTopbar = true;
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/events.php';

$user = inkwell_current_user();
$events = inkwell_all_events(100);

$notice = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_event') {
  if ($user && in_array($user['role'], ['teacher', 'dean'], true)) {
    inkwell_delete_event((int) ($_POST['event_id'] ?? 0), $user['id']);
    $notice = 'Event deleted.';
    $events = inkwell_all_events(100);
  }
}

$pageTitle = 'Events';
include __DIR__ . '/includes/header.php';
$driveActive = 'events';
$driveCrumbs = [['label' => 'Home', 'href' => '/index.php'], ['label' => 'Events']];
$driveTitle = 'Events';
$driveSubtitle = 'Announcements posted by teachers and deans across Inkwell.';
include __DIR__ . '/includes/drive_shell_top.php';
?>
    <?php if ($notice): ?><div class="exam-result pass"><?php echo htmlspecialchars($notice); ?></div><?php endif; ?>

    <?php if ($user && $user['role'] === 'teacher' && $user['status'] === 'active'): ?>
      <p><a class="btn primary" href="/teacher/events.php">+ Post an event from your dashboard</a></p>
    <?php elseif ($user && $user['role'] === 'dean' && $user['status'] === 'active'): ?>
      <p><a class="btn primary" href="/dean/dashboard.php">+ Post an event from your dashboard</a></p>
    <?php endif; ?>

    <?php if (empty($events)): ?>
      <section class="admin-card glass-card">
        <p class="admin-sub">No events posted yet.</p>
      </section>
    <?php else: ?>
      <?php foreach ($events as $ev): ?>
        <?php $evUrl = inkwell_event_url($ev['id']); ?>
        <div class="event-card" id="event-<?php echo (int) $ev['id']; ?>">
          <div class="event-card-top">
            <div>
              <h3><?php echo htmlspecialchars($ev['title']); ?></h3>
              <span class="badge badge-author-<?php echo $ev['author_role']; ?>"><?php echo $ev['author_role'] === 'dean' ? 'Dean' : 'Teacher'; ?> · <?php echo htmlspecialchars($ev['author_name']); ?></span>
            </div>
            <span class="event-meta"><?php echo htmlspecialchars(date('M j, Y', strtotime($ev['created_at']))); ?></span>
          </div>
          <?php if ($ev['body']): ?><p class="event-body"><?php echo inkwell_linkify($ev['body']); ?></p><?php endif; ?>
          <?php if (!empty($ev['link_url'])): ?>
            <p><a class="btn primary event-link-btn" href="<?php echo htmlspecialchars($ev['link_url']); ?>"><?php echo htmlspecialchars($ev['link_label'] ?: 'Open link →'); ?></a></p>
          <?php endif; ?>
          <div class="event-copylink-row">
            <input type="text" class="event-copylink-input" id="event-link-<?php echo (int) $ev['id']; ?>" value="<?php echo htmlspecialchars($evUrl); ?>" readonly onclick="this.select();" aria-label="Link to this event">
            <button type="button" class="event-copylink-btn" data-copy-event-link data-event-id="<?php echo (int) $ev['id']; ?>">Copy link</button>
          </div>
          <?php if ($user && (int) $user['id'] === (int) $ev['author_id']): ?>
            <form method="post" action="/events.php" onsubmit="return confirm('Delete this event?');">
              <input type="hidden" name="action" value="delete_event">
              <input type="hidden" name="event_id" value="<?php echo (int) $ev['id']; ?>">
              <button class="btn" type="submit">Delete</button>
            </form>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
<?php include __DIR__ . '/includes/drive_shell_bottom.php'; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>
