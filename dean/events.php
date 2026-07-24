<?php
// Inside/dashboard page — suppress the marketing topbar (see includes/header.php).
$__hideTopbar = true;
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/schools.php';
require_once __DIR__ . '/../includes/events.php';
require_once __DIR__ . '/../includes/exams_db.php';

$user = inkwell_require_role('dean');
$school = inkwell_get_school_by_dean($user['id']);
if ($user['status'] !== 'active' || !$school) {
  header('Location: /dean/dashboard.php');
  exit;
}

$notice = '';
$error = '';

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
      inkwell_create_event($user['id'], 'dean', $title, $body, $linkUrl, $linkLabel);
      $notice = 'Event posted.';
    }
  }
}

$myEvents = inkwell_events_by_author($user['id']);
$schoolExams = inkwell_school_exam_categories($school['id'], $user['department_id'] ?? null);

$dashNavTitle = 'Dean';
$dashNavActive = 'events';
$dashNavItems = [
  ['key' => 'dashboard', 'group' => 'General', 'href' => '/dean/overview.php', 'label' => 'Dashboard', 'icon' => '🏠'],
  ['key' => 'school', 'group' => 'School', 'href' => '/dean/dashboard.php', 'label' => 'School profile', 'icon' => '🏫'],
  ['key' => 'signer', 'group' => 'Certificates', 'href' => '/dean/signer.php', 'label' => 'Certificate signer', 'icon' => '✍️'],
  ['key' => 'certificates', 'group' => 'Certificates', 'href' => '/dean/certificates.php', 'label' => 'Issue certificate', 'icon' => '📜'],
  ['key' => 'teachers', 'group' => 'People', 'href' => '/dean/teachers.php', 'label' => 'Teachers', 'icon' => '🧑‍🏫', 'count' => count(inkwell_list_school_teachers($school['id'], false, $user['department_id'] ?? null))],
  ['key' => 'students', 'group' => 'People', 'href' => '/dean/students.php', 'label' => 'Students', 'icon' => '🎓', 'count' => count(inkwell_school_students($school['id']))],
  ['key' => 'events', 'group' => 'Activity', 'href' => '/dean/events.php', 'label' => 'Events', 'icon' => '📣', 'count' => count($myEvents)],
];

$pageTitle = 'Dean · Events';
include __DIR__ . '/../includes/header.php';
?>
<div class="dash-shell">
  <?php include __DIR__ . '/../includes/dash_nav.php'; ?>
  <main class="admin-main">
    <div class="admin-header-row">
      <h1>Events</h1>
      <a class="btn" href="/logout.php">Log out</a>
    </div>

    <?php if ($notice): ?><div class="exam-result pass"><?php echo htmlspecialchars($notice); ?></div><?php endif; ?>
    <?php if ($error): ?><div class="exam-result fail"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

    <section class="admin-card glass-card">
      <div class="admin-header-row" style="margin-bottom:0;">
        <div>
          <h2>Events (<?php echo count($myEvents); ?>)</h2>
          <p class="admin-sub">Post an announcement to the public events feed — visible to every student and teacher.</p>
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
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </section>

    <div class="modal-backdrop" id="postEventModal">
      <div class="modal">
        <div class="modal-head">
          <h2>Post an event</h2>
          <button type="button" data-modal-close aria-label="Close">✕</button>
        </div>
        <form method="post" action="/dean/events.php" class="admin-form">
          <input type="hidden" name="action" value="post_event">
          <label for="ev_title">Title</label>
          <input type="text" id="ev_title" name="title" maxlength="150" required>
          <label for="ev_body">Details (optional)</label>
          <textarea id="ev_body" name="body" rows="4" maxlength="2000"></textarea>

          <label for="ev_exam_pick">Attach an exam (optional)</label>
          <?php if (empty($schoolExams)): ?>
            <p class="admin-sub" style="margin-top:0;">None of your school's teachers have created an exam yet — paste any link below instead.</p>
          <?php else: ?>
            <select id="ev_exam_pick" onchange="var o=this.options[this.selectedIndex]; document.getElementById('ev_link_url').value = o.value; document.getElementById('ev_link_label').value = o.value ? ('Take the ' + o.dataset.title + ' exam →') : '';">
              <option value="">— No exam, just an announcement —</option>
              <?php foreach ($schoolExams as $ex): ?>
                <option value="/exam.php?teacher_cat=<?php echo (int) $ex['id']; ?>" data-title="<?php echo htmlspecialchars($ex['title']); ?>"><?php echo htmlspecialchars($ex['title']); ?> <?php echo $ex['teacher_name'] ? '(' . htmlspecialchars($ex['teacher_name']) . ')' : ''; ?></option>
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
