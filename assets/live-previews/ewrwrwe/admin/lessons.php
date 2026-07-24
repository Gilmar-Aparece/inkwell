<?php
// Inside/dashboard page — suppress the marketing topbar (see includes/header.php).
$__hideTopbar = true;
require_once __DIR__ . '/../data/lessons.php';
require_once __DIR__ . '/../includes/store.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/schools.php';
require_once __DIR__ . '/../includes/students.php';
require_once __DIR__ . '/../includes/billing.php';
require_once __DIR__ . '/../includes/lesson_progress.php';
require_once __DIR__ . '/../includes/departments.php';
inkwell_require_admin();

function inkwell_slugify($text) {
  $text = strtolower(trim($text));
  $text = preg_replace('/[^a-z0-9]+/', '-', $text);
  return trim($text, '-') ?: 'lesson';
}

$cats = inkwell_categories();
$catKey = $_GET['cat'] ?? array_key_first($cats);
if (!isset($cats[$catKey])) $catKey = array_key_first($cats);
$lessonDepartments = inkwell_list_departments();

$notice = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';
  $postCat = $_POST['cat'] ?? $catKey;
  if (!isset($cats[$postCat])) $postCat = $catKey;
  $catKey = $postCat;
  $runnable = !empty($cats[$catKey]['runnable']);

  if ($action === 'save_lesson') {
    $title = trim($_POST['title'] ?? '');
    $summary = trim($_POST['summary'] ?? '');
    $body = trim($_POST['body'] ?? '');
    $origSlug = trim($_POST['orig_slug'] ?? '');

    if ($title === '') {
      $error = 'A lesson title is required.';
    } else {
      $slug = $origSlug !== '' ? $origSlug : inkwell_slugify($title);
      // Make sure a brand-new slug doesn't collide with an existing one.
      if ($origSlug === '') {
        $base = $slug; $n = 2;
        while (isset($cats[$catKey]['lessons'][$slug])) { $slug = $base . '-' . $n; $n++; }
      }
      $lesson = ['title' => $title, 'summary' => $summary, 'body' => $body];
      if ($runnable) {
        $lesson['html'] = $_POST['html'] ?? '';
        $lesson['css'] = $_POST['css'] ?? '';
        $lesson['js'] = $_POST['js'] ?? '';
      } else {
        $lesson['code'] = $_POST['code'] ?? '';
      }
      inkwell_save_lesson_override($catKey, $slug, $lesson);
      $notice = $origSlug !== '' ? 'Lesson updated.' : 'Lesson added.';
      $cats = inkwell_categories(); // refresh after save (static cache already set once per request, so re-derive manually)
      $cats[$catKey]['lessons'][$slug] = $lesson;
    }
  } elseif ($action === 'delete_lesson') {
    $slug = $_POST['slug'] ?? '';
    if ($slug !== '' && isset($cats[$catKey]['lessons'][$slug])) {
      inkwell_delete_lesson_override($catKey, $slug);
      unset($cats[$catKey]['lessons'][$slug]);
      $notice = 'Lesson deleted.';
    }
  } elseif ($action === 'save_track') {
    $trackLabel = trim($_POST['track_label'] ?? '');
    $trackKey = trim($_POST['track_key'] ?? '');
    $trackTagline = trim($_POST['track_tagline'] ?? '');
    $trackColor = trim($_POST['track_color'] ?? '#2d5c4c');
    $trackRunnable = !empty($_POST['track_runnable']);
    $trackCourse = strtoupper(trim($_POST['track_course'] ?? ''));
    $origKey = trim($_POST['orig_track_key'] ?? '');

    if ($trackLabel === '') {
      $error = 'Give the track a name.';
    } else {
      $key = $origKey !== '' ? $origKey : inkwell_slugify($trackKey !== '' ? $trackKey : $trackLabel);
      if ($origKey === '' && isset($cats[$key])) {
        $error = 'A track with that key already exists.';
      } else {
        inkwell_save_category_override($key, [
          'label' => $trackLabel,
          'color' => $trackColor !== '' ? $trackColor : '#2d5c4c',
          'tagline' => $trackTagline,
          'runnable' => $trackRunnable,
          'course' => $trackCourse,
        ]);
        $notice = $origKey !== '' ? 'Track updated.' : 'Track added.';
        $cats = inkwell_categories();
        $catKey = $key;
      }
    }
  } elseif ($action === 'save_settings') {
    $free = (int) ($_POST['free_lessons_per_track'] ?? 3);
    inkwell_save_config(['free_lessons_per_track' => max(0, $free)]);
    $notice = 'Settings saved.';
  } elseif ($action === 'delete_track') {
    $delKey = $_POST['track_key'] ?? '';
    if ($delKey !== '' && isset($cats[$delKey])) {
      if (inkwell_is_custom_category($delKey)) {
        inkwell_delete_category_override($delKey);
        unset($cats[$delKey]);
        $notice = 'Track deleted.';
        $catKey = array_key_first($cats);
      } else {
        $error = "Built-in tracks can't be deleted.";
      }
    }
  }
}

$lessons = $cats[$catKey]['lessons'] ?? [];
$runnable = !empty($cats[$catKey]['runnable']);

$dashNavTitle = 'Admin';
$dashNavActive = 'lessons';
$dashNavItems = [
  ['key' => 'dashboard', 'group' => 'General', 'href' => '/admin/dashboard.php', 'label' => 'Dashboard', 'icon' => '🏠'],
  ['key' => 'settings', 'group' => 'General', 'href' => '/admin/index.php', 'label' => 'Settings', 'icon' => '⚙️'],
  ['key' => 'exams', 'group' => 'Academics', 'href' => '/admin/exams.php', 'label' => 'Exams', 'icon' => '🗂'],
  ['key' => 'lessons', 'group' => 'Academics', 'href' => '/admin/lessons.php', 'label' => 'Lessons', 'icon' => '📘'],
  ['key' => 'teachers', 'group' => 'People', 'href' => '/admin/teachers.php', 'label' => 'Teachers', 'icon' => '🧑‍🏫', 'count' => count(inkwell_list_teachers())],
  ['key' => 'deans', 'group' => 'People', 'href' => '/admin/deans.php', 'label' => 'Deans', 'icon' => '🎓', 'count' => count(inkwell_list_deans())],
  ['key' => 'registrars', 'group' => 'People', 'href' => '/admin/registrars.php', 'label' => 'Registrars', 'icon' => '🗂️', 'count' => count(inkwell_list_registrars())],
  ['key' => 'admins', 'group' => 'People', 'href' => '/admin/admins.php', 'label' => 'Admins', 'icon' => '🛡️', 'count' => count(inkwell_list_admins())],
  ['key' => 'students', 'group' => 'People', 'href' => '/admin/students.php', 'label' => 'Students', 'icon' => '🧑‍🎓', 'count' => count(inkwell_list_all_students())],
  ['key' => 'schools', 'group' => 'Schools & Recognition', 'href' => '/admin/schools.php', 'label' => 'Schools', 'icon' => '🏫', 'count' => count(inkwell_list_schools_with_stats())],
  ['key' => 'certificates', 'group' => 'Schools & Recognition', 'href' => '/admin/certificates.php', 'label' => 'Certificates', 'icon' => '📜'],
  ['key' => 'top-learners', 'group' => 'Schools & Recognition', 'href' => '/admin/top-learners.php', 'label' => 'Top learners', 'icon' => '⭐', 'count' => count(inkwell_top_learner_ids())],
  ['key' => 'lesson-progress', 'group' => 'Schools & Recognition', 'href' => '/admin/lesson-progress.php', 'label' => 'Lesson progress', 'icon' => '📈', 'count' => count(inkwell_lesson_progress_overview())],
  ['key' => 'billing-dashboard', 'group' => 'Billing', 'href' => '/admin/billing-dashboard.php', 'label' => 'Dashboard', 'icon' => '📊'],
  ['key' => 'pricing', 'group' => 'Billing', 'href' => '/admin/pricing.php', 'label' => 'Pricing', 'icon' => '💳', 'count' => count(inkwell_list_plans())],
  ['key' => 'payment-methods', 'group' => 'Billing', 'href' => '/admin/payment-methods.php', 'label' => 'Payment methods', 'icon' => '🏦'],
  ['key' => 'payments', 'group' => 'Billing', 'href' => '/admin/payments.php', 'label' => 'Payments', 'icon' => '🧾', 'count' => inkwell_count_pending_payment_submissions()],
];

$pageTitle = 'Admin · Lessons';
include __DIR__ . '/../includes/header.php';
?>
<div class="dash-shell">
  <?php include __DIR__ . '/../includes/dash_nav.php'; ?>
  <main class="admin-main">
    <div class="admin-header-row">
      <h1>Customize lessons</h1>
      <a class="btn" href="/admin/logout.php">Log out</a>
    </div>

    <?php if ($notice): ?><div class="exam-result pass"><?php echo htmlspecialchars($notice); ?></div><?php endif; ?>
    <?php if ($error): ?><div class="exam-result fail"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

    <section class="admin-card glass-card">
      <h2>Free preview</h2>
      <p class="admin-sub">The first N lessons of <strong>every</strong> track are free for anyone — guest or logged in. Anything past that needs an active plan that unlocks all lessons (e.g. Pro Learner — see <a href="/admin/pricing.php">Pricing</a>).</p>
      <form method="post" action="/admin/lessons.php?cat=<?php echo urlencode($catKey); ?>" class="admin-form" style="max-width:320px;">
        <input type="hidden" name="action" value="save_settings">
        <label for="free_lessons_per_track">Free lessons per track</label>
        <input type="number" id="free_lessons_per_track" name="free_lessons_per_track" min="0" value="<?php echo (int) inkwell_free_lessons_per_track(); ?>">
        <button class="btn primary" type="submit">Save</button>
      </form>
    </section>

    <section class="admin-card">
      <div class="admin-header-row" style="margin-bottom:0;">
        <h2>Track</h2>
        <button type="button" class="btn primary" data-track-add data-modal-open="trackModal">+ Add track</button>
      </div>
      <p class="admin-sub">Edit, add, or remove lessons in any track. Changes appear on the public lessons page immediately.</p>
      <div class="admin-tabs" style="display:flex; flex-wrap:wrap; gap:8px; margin-top:10px;">
        <?php foreach ($cats as $ck => $c): ?>
          <span style="display:inline-flex; align-items:center; gap:2px;">
            <a class="btn<?php echo $ck === $catKey ? ' primary' : ''; ?>" href="/admin/lessons.php?cat=<?php echo urlencode($ck); ?>"><?php echo htmlspecialchars($c['label']); ?> <span class="admin-sub" style="font-size:0.7rem;">(<?php echo htmlspecialchars($c['course'] ?? 'BSIT'); ?>)</span></a>
            <button type="button" class="icon-btn" title="Edit track" data-track-edit data-modal-open="trackModal"
              data-key="<?php echo htmlspecialchars($ck); ?>"
              data-label="<?php echo htmlspecialchars($c['label']); ?>"
              data-tagline="<?php echo htmlspecialchars($c['tagline'] ?? ''); ?>"
              data-color="<?php echo htmlspecialchars($c['color'] ?? '#2d5c4c'); ?>"
              data-runnable="<?php echo !empty($c['runnable']) ? '1' : '0'; ?>"
              data-course="<?php echo htmlspecialchars($c['course'] ?? 'BSIT'); ?>">✎</button>
            <?php if (inkwell_is_custom_category($ck)): ?>
              <form method="post" action="/admin/lessons.php?cat=<?php echo urlencode($catKey); ?>" style="display:inline;" onsubmit="return confirm('Delete the &quot;<?php echo htmlspecialchars(addslashes($c['label'])); ?>&quot; track and all its lessons?');">
                <input type="hidden" name="action" value="delete_track">
                <input type="hidden" name="track_key" value="<?php echo htmlspecialchars($ck); ?>">
                <button type="submit" class="icon-btn danger" title="Delete track">✕</button>
              </form>
            <?php endif; ?>
          </span>
        <?php endforeach; ?>
      </div>
    </section>

    <section class="admin-card glass-card">
      <div class="admin-header-row" style="margin-bottom:0;">
        <h2><?php echo htmlspecialchars($cats[$catKey]['label']); ?> lessons (<?php echo count($lessons); ?>)</h2>
        <button type="button" class="btn primary" data-lesson-add data-modal-open="lessonModal">+ Add lesson</button>
      </div>
      <?php if (empty($lessons)): ?>
        <p class="admin-sub">No lessons in this track yet — add the first one.</p>
      <?php else: ?>
        <div class="search-filter">
          <input type="search" class="search-filter-input" data-filter-target="#lessonsTable" placeholder="Search lessons by title...">
        </div>
        <div class="admin-table-wrap">
          <table class="admin-table" id="lessonsTable">
            <thead><tr><th>Title</th><th>Summary</th><th>Slug</th><th></th></tr></thead>
            <tbody>
              <?php foreach ($lessons as $slug => $l): ?>
                <tr data-filter-row>
                  <td><strong><?php echo htmlspecialchars($l['title'] ?? ''); ?></strong></td>
                  <td><?php echo htmlspecialchars($l['summary'] ?? ''); ?></td>
                  <td><code><?php echo htmlspecialchars($slug); ?></code></td>
                  <td style="white-space:nowrap;">
                    <button type="button" class="btn" data-lesson-edit data-modal-open="lessonModal"
                      data-slug="<?php echo htmlspecialchars($slug); ?>"
                      data-title="<?php echo htmlspecialchars($l['title'] ?? ''); ?>"
                      data-summary="<?php echo htmlspecialchars($l['summary'] ?? ''); ?>"
                      data-body="<?php echo htmlspecialchars($l['body'] ?? ''); ?>"
                      data-html="<?php echo htmlspecialchars($l['html'] ?? ''); ?>"
                      data-css="<?php echo htmlspecialchars($l['css'] ?? ''); ?>"
                      data-js="<?php echo htmlspecialchars($l['js'] ?? ''); ?>"
                      data-code="<?php echo htmlspecialchars($l['code'] ?? ''); ?>">Edit</button>
                    <form method="post" action="/admin/lessons.php?cat=<?php echo urlencode($catKey); ?>" style="display:inline;" onsubmit="return confirm('Delete this lesson?');">
                      <input type="hidden" name="action" value="delete_lesson">
                      <input type="hidden" name="cat" value="<?php echo htmlspecialchars($catKey); ?>">
                      <input type="hidden" name="slug" value="<?php echo htmlspecialchars($slug); ?>">
                      <button type="submit" class="btn danger">Delete</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </section>
  </main>
</div>

<div class="modal-backdrop" id="lessonModal">
  <div class="modal">
    <div class="modal-head">
      <h2 id="lessonModalTitle">Add lesson</h2>
      <button type="button" data-modal-close aria-label="Close">✕</button>
    </div>
    <form method="post" action="/admin/lessons.php?cat=<?php echo urlencode($catKey); ?>" class="admin-form">
      <input type="hidden" name="action" value="save_lesson">
      <input type="hidden" name="cat" value="<?php echo htmlspecialchars($catKey); ?>">
      <input type="hidden" name="orig_slug" id="lessonOrigSlug" value="">
      <label for="lessonTitle">Title</label>
      <input type="text" id="lessonTitle" name="title" maxlength="150" required>
      <label for="lessonSummary">Summary</label>
      <input type="text" id="lessonSummary" name="summary" maxlength="255">
      <label for="lessonBody">Teaching text (HTML allowed)</label>
      <textarea id="lessonBody" name="body" rows="5"></textarea>
      <?php if ($runnable): ?>
        <label for="lessonHtml">Starter HTML</label>
        <textarea id="lessonHtml" name="html" rows="4" class="mono-textarea"></textarea>
        <label for="lessonCss">Starter CSS</label>
        <textarea id="lessonCss" name="css" rows="4" class="mono-textarea"></textarea>
        <label for="lessonJs">Starter JS</label>
        <textarea id="lessonJs" name="js" rows="4" class="mono-textarea"></textarea>
      <?php else: ?>
        <label for="lessonCode">Starter code</label>
        <textarea id="lessonCode" name="code" rows="8" class="mono-textarea"></textarea>
      <?php endif; ?>
      <button class="btn primary" type="submit">Save lesson</button>
    </form>
  </div>
</div>
<div class="modal-backdrop" id="trackModal">
  <div class="modal">
    <div class="modal-head">
      <h2 id="trackModalTitle">Add track</h2>
      <button type="button" data-modal-close aria-label="Close">✕</button>
    </div>
    <form method="post" action="/admin/lessons.php?cat=<?php echo urlencode($catKey); ?>" class="admin-form">
      <input type="hidden" name="action" value="save_track">
      <input type="hidden" name="orig_track_key" id="trackOrigKey" value="">
      <label for="trackLabel">Track name</label>
      <input type="text" id="trackLabel" name="track_label" maxlength="60" required placeholder="e.g. Ruby">
      <label for="trackKeyField">URL key (letters/numbers only, leave blank to auto-generate)</label>
      <input type="text" id="trackKeyField" name="track_key" maxlength="40" placeholder="e.g. ruby">
      <label for="trackTagline">Tagline</label>
      <input type="text" id="trackTagline" name="track_tagline" maxlength="120" placeholder="e.g. Scripting made friendly">
      <label for="trackColor">Accent color</label>
      <input type="color" id="trackColor" name="track_color" value="#2d5c4c">
      <label for="trackCourse">Department / course</label>
      <select id="trackCourse" name="track_course">
        <?php foreach ($lessonDepartments as $dept): ?>
          <option value="<?php echo htmlspecialchars($dept['code']); ?>"><?php echo htmlspecialchars($dept['code']); ?> — <?php echo htmlspecialchars($dept['name']); ?></option>
        <?php endforeach; ?>
      </select>
      <p class="admin-sub" style="margin:2px 0 0;">Need another department? A Registrar can add one from their dashboard's Departments section, and it'll show up here.</p>
      <label style="display:flex; align-items:center; gap:8px; margin-top:6px;">
        <input type="checkbox" id="trackRunnable" name="track_runnable" value="1" style="width:auto;">
        Use the live HTML/CSS/JS preview editor (leave unchecked for a single code editor, e.g. Python/Java/C#)
      </label>
      <button class="btn primary" type="submit">Save track</button>
    </form>
  </div>
</div>
<script>
document.addEventListener('click', function (e) {
  const addBtn = e.target.closest('[data-track-add]');
  const editBtn = e.target.closest('[data-track-edit]');
  if (addBtn) {
    document.getElementById('trackModalTitle').textContent = 'Add track';
    document.getElementById('trackOrigKey').value = '';
    document.getElementById('trackLabel').value = '';
    document.getElementById('trackKeyField').value = '';
    document.getElementById('trackKeyField').disabled = false;
    document.getElementById('trackTagline').value = '';
    document.getElementById('trackColor').value = '#2d5c4c';
    document.getElementById('trackCourse').value = 'BSIT';
    document.getElementById('trackRunnable').checked = false;
  } else if (editBtn) {
    document.getElementById('trackModalTitle').textContent = 'Edit track';
    document.getElementById('trackOrigKey').value = editBtn.getAttribute('data-key') || '';
    document.getElementById('trackLabel').value = editBtn.getAttribute('data-label') || '';
    document.getElementById('trackKeyField').value = editBtn.getAttribute('data-key') || '';
    document.getElementById('trackKeyField').disabled = true;
    document.getElementById('trackTagline').value = editBtn.getAttribute('data-tagline') || '';
    document.getElementById('trackColor').value = editBtn.getAttribute('data-color') || '#2d5c4c';
    document.getElementById('trackCourse').value = editBtn.getAttribute('data-course') || 'BSIT';
    document.getElementById('trackRunnable').checked = editBtn.getAttribute('data-runnable') === '1';
  }
});
</script>
<script>
document.addEventListener('click', function (e) {
  const addBtn = e.target.closest('[data-lesson-add]');
  const editBtn = e.target.closest('[data-lesson-edit]');
  if (addBtn) {
    document.getElementById('lessonModalTitle').textContent = 'Add lesson';
    document.getElementById('lessonOrigSlug').value = '';
    document.querySelectorAll('#lessonModal textarea, #lessonModal input[type="text"]').forEach(function (el) { el.value = ''; });
  } else if (editBtn) {
    document.getElementById('lessonModalTitle').textContent = 'Edit lesson';
    document.getElementById('lessonOrigSlug').value = editBtn.getAttribute('data-slug') || '';
    document.getElementById('lessonTitle').value = editBtn.getAttribute('data-title') || '';
    document.getElementById('lessonSummary').value = editBtn.getAttribute('data-summary') || '';
    document.getElementById('lessonBody').value = editBtn.getAttribute('data-body') || '';
    const htmlEl = document.getElementById('lessonHtml');
    const cssEl = document.getElementById('lessonCss');
    const jsEl = document.getElementById('lessonJs');
    const codeEl = document.getElementById('lessonCode');
    if (htmlEl) htmlEl.value = editBtn.getAttribute('data-html') || '';
    if (cssEl) cssEl.value = editBtn.getAttribute('data-css') || '';
    if (jsEl) jsEl.value = editBtn.getAttribute('data-js') || '';
    if (codeEl) codeEl.value = editBtn.getAttribute('data-code') || '';
  }
});
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
