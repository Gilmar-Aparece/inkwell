<?php
// Inside/dashboard page — suppress the marketing topbar (see includes/header.php).
$__hideTopbar = true;
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/schools.php';
require_once __DIR__ . '/includes/events.php';

$user = inkwell_require_login();

if ($user['role'] === 'dean') {
  header('Location: /dean/dashboard.php');
  exit;
}

$school = !empty($user['school_id']) ? inkwell_get_school($user['school_id']) : null;

if (!$school) {
  header('Location: /my-school.php');
  exit;
}

$notice = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_event') {
  if (in_array($user['role'], ['teacher', 'dean'], true)) {
    inkwell_delete_event((int) ($_POST['event_id'] ?? 0), $user['id']);
    $notice = 'Event deleted.';
  }
}

$schoolEvents = inkwell_school_events($school['id'], 100);

$__me = $user;
$pageTitle = $school['name'] . ' events';
include __DIR__ . '/includes/header.php';
$driveActive = 'my-school';
$driveCrumbs = [
  ['label' => 'Home', 'href' => '/index.php'],
  ['label' => 'My school', 'href' => '/my-school.php'],
  ['label' => 'Events'],
];
$driveTitle = 'Events';
$driveSubtitle = 'Announcements posted by ' . $school['name'] . "'s dean and teachers.";
include __DIR__ . '/includes/drive_shell_top.php';
?>
    <?php if ($notice): ?><div class="exam-result pass"><?php echo htmlspecialchars($notice); ?></div><?php endif; ?>

    <?php if ($user['role'] === 'teacher' && $user['status'] === 'active'): ?>
      <p><a class="btn primary" href="/teacher/events.php">+ Post an event from your dashboard</a></p>
    <?php endif; ?>

    <?php if (empty($schoolEvents)): ?>
      <section class="admin-card glass-card">
        <p class="admin-sub">No announcements from <?php echo htmlspecialchars($school['name']); ?> yet.</p>
      </section>
    <?php else: ?>
      <?php foreach ($schoolEvents as $ev): ?>
        <?php $evUrl = inkwell_event_url($ev['id']); $__isExamPost = strpos($ev['title'], '🎓 Exam today:') === 0; ?>
        <div class="event-card" id="event-<?php echo (int) $ev['id']; ?>">
          <div class="event-card-top">
            <div>
              <h3><?php echo htmlspecialchars($ev['title']); ?><?php if ($__isExamPost): ?> <span class="badge badge-purpose-cert">Exam</span><?php endif; ?></h3>
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
          <?php if ((int) $user['id'] === (int) $ev['author_id']): ?>
            <form method="post" action="/school-events.php" onsubmit="return confirm('Delete this event?');">
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
