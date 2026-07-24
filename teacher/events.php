<?php
// Inside/dashboard page — suppress the marketing topbar (see includes/header.php).
$__hideTopbar = true;
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/events.php';
require_once __DIR__ . '/../includes/exams_db.php';
require_once __DIR__ . '/../includes/students.php';

$user = inkwell_require_role('teacher');
if ($user['status'] !== 'active') {
  header('Location: /teacher/dashboard.php');
  exit;
}

$error = '';
$notice = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';

  if ($action === 'post_event') {
    $title = trim($_POST['title'] ?? '');
    $body = trim($_POST['body'] ?? '');
    $linkUrl = trim($_POST['link_url'] ?? '');
    $linkLabel = trim($_POST['link_label'] ?? '');
    if ($title === '') {
      $error = 'Give the event a title.';
    } else {
      if ($linkUrl !== '' && $linkLabel === '') $linkLabel = 'Take the exam →';
      inkwell_create_event($user['id'], 'teacher', $title, $body, $linkUrl, $linkLabel);
      $notice = 'Event posted.';
    }
  }

  if ($action === 'delete_event') {
    $evId = (int) ($_POST['event_id'] ?? 0);
    inkwell_delete_event($evId, $user['id']);
    $notice = 'Event deleted.';
  }
}

$myEvents = inkwell_events_by_author($user['id']);
$myExams = inkwell_teacher_categories($user['id']);

$dashNavTitle = 'Teacher';
$dashNavActive = 'events';
$dashNavItems = [
  ['key' => 'dashboard', 'group' => 'General', 'href' => '/teacher/overview.php', 'label' => 'Dashboard', 'icon' => '🏠'],
  ['key' => 'subjects', 'group' => 'Teaching', 'href' => '/teacher/dashboard.php', 'label' => 'Subjects', 'icon' => '🗂'],
  ['key' => 'sections', 'group' => 'Teaching', 'href' => '/teacher/sections.php', 'label' => 'Sections', 'icon' => '👥'],
  ['key' => 'grade', 'group' => 'Teaching', 'href' => '/teacher/grade.php', 'label' => 'Grade', 'icon' => '🖊', 'count' => count(inkwell_teacher_pending_attempts($user['id']))],
  ['key' => 'students', 'group' => 'People', 'href' => '/teacher/students.php', 'label' => 'Students', 'icon' => '🎓', 'count' => count(inkwell_teacher_students($user['id']))],
  ['key' => 'certificates', 'group' => 'People', 'href' => '/teacher/certificates.php', 'label' => 'Certificates', 'icon' => '📜'],
  ['key' => 'events', 'group' => 'Activity', 'href' => '/teacher/events.php', 'label' => 'Events', 'icon' => '📣', 'count' => count($myEvents)],
];

$pageTitle = 'Events';
include __DIR__ . '/../includes/header.php';
?>
<div class="dash-shell">
  <?php include __DIR__ . '/../includes/dash_nav.php'; ?>
  <main class="admin-main">
  <div class="admin-header-row">
    <h1>Your events</h1>
    <a class="btn" href="/teacher/dashboard.php">← Back to dashboard</a>
  </div>

  <?php if ($notice): ?><div class="exam-result pass"><?php echo htmlspecialchars($notice); ?></div><?php endif; ?>
  <?php if ($error): ?><div class="exam-result fail"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

  <section class="admin-card glass-card">
    <div class="admin-header-row" style="margin-bottom:0;">
      <div>
        <h2>Events (<?php echo count($myEvents); ?>)</h2>
        <p class="admin-sub">Post an announcement to the public events feed — visible to every student and teacher. <a href="/events.php">View the public feed →</a></p>
      </div>
      <button class="btn primary" type="button" data-modal-open="postEventModal">+ Post event</button>
    </div>
    <?php if (empty($myEvents)): ?>
      <p class="admin-sub">You haven't posted any events yet.</p>
    <?php else: ?>
      <?php foreach ($myEvents as $ev): ?>
        <?php $evUrl = inkwell_event_url($ev['id']); ?>
        <div class="event-card" id="event-<?php echo (int) $ev['id']; ?>">
          <div class="event-card-top">
            <h3><?php echo htmlspecialchars($ev['title']); ?></h3>
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
          <form method="post" action="/teacher/events.php" onsubmit="return confirm('Delete this event?');">
            <input type="hidden" name="action" value="delete_event">
            <input type="hidden" name="event_id" value="<?php echo (int) $ev['id']; ?>">
            <button class="btn" type="submit">Delete</button>
          </form>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </section>

  <!-- Post event modal -->
  <div class="modal-backdrop" id="postEventModal">
    <div class="modal">
      <div class="modal-head">
        <h2>Post an event</h2>
        <button type="button" data-modal-close aria-label="Close">✕</button>
      </div>
      <form method="post" action="/teacher/events.php" class="admin-form">
        <input type="hidden" name="action" value="post_event">
        <label for="ev_title">Title</label>
        <input type="text" id="ev_title" name="title" maxlength="150" required>
        <label for="ev_body">Details (optional)</label>
        <textarea id="ev_body" name="body" rows="4" maxlength="2000"></textarea>

        <label for="ev_exam_pick">Attach an exam (optional)</label>
        <?php if (empty($myExams)): ?>
          <p class="admin-sub" style="margin-top:0;">You haven't created any exams yet — <a href="/teacher/dashboard.php">create one</a> first, or paste any link below instead.</p>
        <?php else: ?>
          <select id="ev_exam_pick" onchange="var o=this.options[this.selectedIndex]; document.getElementById('ev_link_url').value = o.value; document.getElementById('ev_link_label').value = o.value ? ('Take the ' + o.dataset.title + ' exam →') : '';">
            <option value="">— No exam, just an announcement —</option>
            <?php foreach ($myExams as $ex): ?>
              <option value="/exam.php?teacher_cat=<?php echo (int) $ex['id']; ?>" data-title="<?php echo htmlspecialchars($ex['title']); ?>"><?php echo htmlspecialchars($ex['title']); ?></option>
            <?php endforeach; ?>
          </select>
        <?php endif; ?>

        <label for="ev_link_url">Link (auto-filled when you pick an exam above, or paste any URL)</label>
        <input type="text" id="ev_link_url" name="link_url" maxlength="500" placeholder="/exam.php?teacher_cat=12 or https://...">
        <label for="ev_link_label">Button text</label>
        <input type="text" id="ev_link_label" name="link_label" maxlength="100" placeholder="Take the exam →">

        <button class="btn primary" type="submit">Post event</button>
      </form>
    </div>
  </div>
  </main>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
