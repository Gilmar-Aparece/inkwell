<?php
// Inside/dashboard page — suppress the marketing topbar (see includes/header.php).
$__hideTopbar = true;
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/schools.php';
require_once __DIR__ . '/includes/events.php';
require_once __DIR__ . '/includes/exams_db.php';
require_once __DIR__ . '/includes/students.php';
require_once __DIR__ . '/includes/posts.php';

$user = inkwell_require_login();

if ($user['role'] === 'dean') {
  header('Location: /dean/dashboard.php');
  exit;
}

$joinError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'join_school') {
  $result = inkwell_user_join_school($user['id'], (int) ($_POST['school_id'] ?? 0));
  if ($result['ok']) {
    header('Location: /my-school.php');
    exit;
  }
  $joinError = $result['error'];
  $user = inkwell_current_user();
}

$school = !empty($user['school_id']) ? inkwell_get_school($user['school_id']) : null;

if ($school) {
  $stats = inkwell_school_stats($school['id']);
  $faculty = inkwell_list_school_teachers($school['id'], true);
  $facultyByDept = inkwell_school_faculty_by_department($school['id']);
  $schoolEvents = inkwell_school_events($school['id'], 30);
  $schoolSubjects = inkwell_school_subjects($school['id']);
  $topStudents = array_slice(inkwell_featured_students($school['id']), 0, 5);
  $dean = inkwell_get_user($school['dean_id']);
  $school['dean_name'] = $dean ? $dean['name'] : 'Unknown';
  $schoolPosts = inkwell_list_school_posts($school['id'], $user['id'], 20);
}

$__me = $user;
$pageTitle = 'My school';
include __DIR__ . '/includes/header.php';
$driveActive = 'my-school';
$driveCrumbs = [['label' => 'Home', 'href' => '/index.php'], ['label' => 'My school']];
$driveTitle = $school ? null : 'My school';
$driveSubtitle = $school ? null : 'The school you\'re enrolled with — its faculty, announcements, and subjects, all in one place.';
include __DIR__ . '/includes/drive_shell_top.php';

if (!$school):
?>
  <section class="admin-card glass-card" id="school">
    <h2>You're not enrolled with a school yet</h2>
    <p class="admin-sub">Pick your school below to join it. You'll show up in its <?php echo $user['role'] === 'teacher' ? 'faculty list' : 'student roster'; ?>, and any events or subjects it offers will appear here.</p>
    <?php if ($joinError): ?><div class="exam-result fail"><?php echo htmlspecialchars($joinError); ?></div><?php endif; ?>
    <?php $allSchools = inkwell_list_schools(); ?>
    <?php if (empty($allSchools)): ?>
      <p class="admin-sub">No schools have been created yet. Ask a dean to set one up first, or browse <a href="/schools.php">Schools</a> later.</p>
    <?php else: ?>
      <form method="post" action="/my-school.php" class="admin-form" style="flex-direction:row; gap:10px; align-items:flex-end;">
        <input type="hidden" name="action" value="join_school">
        <div style="flex:1;">
          <label for="join_school_id">School</label>
          <select id="join_school_id" name="school_id" required>
            <option value="">Select a school…</option>
            <?php foreach ($allSchools as $s): ?>
              <option value="<?php echo (int) $s['id']; ?>"><?php echo htmlspecialchars($s['name']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <button type="submit" class="btn primary">Join school</button>
      </form>
    <?php endif; ?>
  </section>
<?php else: ?>
  <div class="school-hero school-hero-v2">
    <div class="school-hero-glow" aria-hidden="true"></div>
    <div class="school-page-head">
      <span class="school-page-logo-ring">
        <?php if ($school['logo']): ?>
          <img class="school-page-logo" src="/assets/uploads/<?php echo htmlspecialchars($school['logo']); ?>" alt="<?php echo htmlspecialchars($school['name']); ?> logo" loading="lazy">
        <?php else: ?>
          <span class="school-page-logo school-page-logo-empty" aria-hidden="true">🏫</span>
        <?php endif; ?>
      </span>
      <div class="school-page-head-text">
        <h1 class="drive-title" style="margin:0;"><?php echo htmlspecialchars($school['name']); ?></h1>
        <span class="dean-line"><span class="dean-line-dot" aria-hidden="true"></span>Dean: <?php echo htmlspecialchars($school['dean_name']); ?></span>
      </div>
    </div>

    <?php if (!empty($school['mission'])): ?>
      <p class="school-mission"><?php echo nl2br(htmlspecialchars($school['mission'])); ?></p>
    <?php endif; ?>

    <div class="school-stat-strip">
      <div class="school-stat"><strong><?php echo (int) $stats['teacher_count']; ?></strong><span>Teachers</span></div>
      <div class="school-stat"><strong><?php echo (int) $stats['student_count']; ?></strong><span>Students</span></div>
      <div class="school-stat"><strong><?php echo (int) $stats['subject_count']; ?></strong><span>Subjects</span></div>
      <div class="school-stat"><strong><?php echo (int) $stats['certificate_count']; ?></strong><span>Certificates</span></div>
    </div>
  </div>

  <div class="school-page-grid">
    <section class="hub-card hub-card-nib">
      <span class="hub-icon" aria-hidden="true">👥</span>
      <div class="hub-card-body">
        <h2>Faculty &amp; Dean <span class="hub-count"><?php echo count($faculty) + 1; ?></span></h2>
        <p class="admin-sub">
          <?php if (!empty($facultyByDept)): ?>
            Every department's Dean and teachers at <?php echo htmlspecialchars($school['name']); ?>, grouped by department.
          <?php else: ?>
            Everyone leading <?php echo htmlspecialchars($school['name']); ?>.
          <?php endif; ?>
        </p>
        <a class="btn primary hub-card-cta" href="/school-faculty.php">View faculty &amp; dean →</a>
      </div>
    </section>

    <section class="hub-card hub-card-clay">
      <span class="hub-icon" aria-hidden="true">📣</span>
      <div class="hub-card-body">
        <h2>Events <span class="hub-count"><?php echo count($schoolEvents); ?></span></h2>
        <p class="admin-sub">Announcements posted by <?php echo htmlspecialchars($school['name']); ?>'s dean and teachers.</p>
        <?php if (empty($schoolEvents)): ?>
          <p class="admin-sub">No announcements from this school yet.</p>
        <?php else: ?>
          <a class="btn primary hub-card-cta" href="/school-events.php">View all events →</a>
        <?php endif; ?>
      </div>
    </section>
  </div>

  <?php if (!empty($topStudents)): ?>
    <section class="admin-card glass-card modern-section" style="margin-top:24px;">
      <h2><span class="section-head-icon" aria-hidden="true">🏅</span>Top students</h2>
      <p class="admin-sub">Students <?php echo htmlspecialchars($school['name']); ?>'s dean and teachers have highlighted.</p>
      <div class="top-students-grid">
        <?php foreach ($topStudents as $f): ?>
          <button type="button" class="top-student-card" data-modal-open="topStudentSpotlightModal"
            data-spotlight-name="<?php echo htmlspecialchars($f['name']); ?>"
            data-spotlight-course="<?php echo htmlspecialchars($f['course'] ?: ''); ?>"
            data-spotlight-avatar="<?php echo !empty($f['avatar']) ? htmlspecialchars('/assets/uploads/' . $f['avatar']) : ''; ?>"
            data-spotlight-note="<?php echo htmlspecialchars($f['note'] ?? ''); ?>"
            data-spotlight-accomplishment="<?php echo htmlspecialchars($f['accomplishment'] ?? ''); ?>"
            data-spotlight-description="<?php echo htmlspecialchars($f['description'] ?? ''); ?>">
            <span class="top-student-card-badge">🏅</span>
            <span class="top-student-avatar-ring">
              <?php if (!empty($f['avatar'])): ?>
                <img class="student-avatar-img" src="/assets/uploads/<?php echo htmlspecialchars($f['avatar']); ?>" alt="" loading="lazy">
              <?php else: ?>
                <span class="student-avatar-placeholder" aria-hidden="true"><?php echo strtoupper(substr($f['name'], 0, 1)); ?></span>
              <?php endif; ?>
            </span>
            <span class="top-student-card-name"><?php echo htmlspecialchars($f['name']); ?></span>
            <span class="top-student-card-course"><?php echo htmlspecialchars($f['course'] ?: '—'); ?></span>
            <?php if (!empty($f['accomplishment'])): ?><span class="top-student-card-accomplishment"><?php echo htmlspecialchars($f['accomplishment']); ?></span><?php endif; ?>
            <?php if (!empty($f['note'])): ?><span class="top-student-card-note">"<?php echo htmlspecialchars($f['note']); ?>"</span><?php endif; ?>
          </button>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>

  <section class="admin-card glass-card modern-section" style="margin-top:24px;">
    <h2><span class="section-head-icon" aria-hidden="true">🖼️</span>Teacher &amp; student posts</h2>
    <p class="admin-sub">Swipe through what <?php echo htmlspecialchars($school['name']); ?>'s teachers and students have shared — just like a page.</p>
    <?php echo inkwell_render_school_posts_swipe($schoolPosts); ?>
  </section>

  <section class="admin-card glass-card modern-section" style="margin-top:24px;">
    <h2><span class="section-head-icon" aria-hidden="true">📚</span>Subjects (<?php echo count($schoolSubjects); ?>)</h2>
    <p class="admin-sub">Classes taught by <?php echo htmlspecialchars($school['name']); ?>'s teachers — join one to unlock every exam inside it.</p>
    <?php if (empty($schoolSubjects)): ?>
      <p class="admin-sub">No subjects offered yet.</p>
    <?php else: ?>
      <div class="table-search-wrap">
        <input type="text" id="mySchoolSubjectsSearch" class="table-search-input" placeholder="Search by subject or teacher…">
      </div>
      <div class="admin-table-wrap">
        <table class="admin-table" id="mySchoolSubjectsTable">
          <thead><tr><th>Subject</th><th>Teacher</th><th>Exams</th><th>Students</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($schoolSubjects as $s): ?>
              <tr data-search="<?php echo htmlspecialchars(strtolower($s['title'] . ' ' . $s['teacher_name'])); ?>">
                <td><?php echo htmlspecialchars($s['title']); ?></td>
                <td><?php echo htmlspecialchars($s['teacher_name']); ?></td>
                <td><?php echo (int) $s['exam_count']; ?></td>
                <td><?php echo (int) $s['student_count']; ?></td>
                <td>
                  <?php if ($user['role'] !== 'student'): ?>
                    <span class="admin-sub">Student accounts only</span>
                  <?php else: ?>
                    <a class="btn primary" href="/join-class.php">Join →</a>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <p id="mySchoolSubjectsNoResults" class="admin-sub" style="display:none; padding:14px;">No subjects match your search.</p>
      </div>
    <?php endif; ?>
  </section>

  <?php if (!empty($schoolSubjects)): ?>
    <script>
      (function () {
        var input = document.getElementById('mySchoolSubjectsSearch');
        var table = document.getElementById('mySchoolSubjectsTable');
        var noResults = document.getElementById('mySchoolSubjectsNoResults');
        if (!input || !table) return;
        var rows = Array.prototype.slice.call(table.querySelectorAll('tbody tr'));
        input.addEventListener('input', function () {
          var q = input.value.trim().toLowerCase();
          var visibleCount = 0;
          rows.forEach(function (row) {
            var match = !q || (row.getAttribute('data-search') || '').indexOf(q) !== -1;
            row.style.display = match ? '' : 'none';
            if (match) visibleCount++;
          });
          if (noResults) noResults.style.display = visibleCount === 0 ? '' : 'none';
          table.style.display = visibleCount === 0 && q ? 'none' : '';
        });
      })();
    </script>
  <?php endif; ?>
<?php endif; ?>

<?php include __DIR__ . '/includes/drive_shell_bottom.php'; ?>
<?php if ($school): include __DIR__ . '/includes/teacher_profile_modal.php'; endif; ?>

<div class="modal-backdrop" id="topStudentSpotlightModal">
  <div class="modal">
    <div class="modal-head">
      <h2>Top student</h2>
      <button type="button" data-modal-close aria-label="Close">✕</button>
    </div>
    <div class="student-profile-head">
      <img class="student-avatar-img" id="spotlightAvatarImg" src="" alt="" style="display:none;" loading="lazy">
      <span class="student-avatar-placeholder" id="spotlightAvatarPlaceholder" aria-hidden="true"></span>
      <div>
        <h2 id="spotlightName"></h2>
        <span class="admin-sub" id="spotlightCourse"></span>
      </div>
    </div>
    <div class="student-profile-section" id="spotlightAccomplishmentWrap" style="display:none;">
      <h3>Accomplishment</h3>
      <p id="spotlightAccomplishment"></p>
    </div>
    <div class="student-profile-section" id="spotlightNoteWrap" style="display:none;">
      <h3>Highlight</h3>
      <p id="spotlightNote" style="font-style:italic;"></p>
    </div>
    <div class="student-profile-section" id="spotlightDescriptionWrap" style="display:none;">
      <h3>About</h3>
      <p id="spotlightDescription"></p>
    </div>
  </div>
</div>
<script>
document.addEventListener('click', function (e) {
  const card = e.target.closest('[data-spotlight-name]');
  if (!card) return;

  document.getElementById('spotlightName').textContent = card.getAttribute('data-spotlight-name') || '';
  document.getElementById('spotlightCourse').textContent = card.getAttribute('data-spotlight-course') || '';

  const avatar = card.getAttribute('data-spotlight-avatar') || '';
  const img = document.getElementById('spotlightAvatarImg');
  const placeholder = document.getElementById('spotlightAvatarPlaceholder');
  if (avatar) {
    img.src = avatar;
    img.style.display = '';
    placeholder.style.display = 'none';
  } else {
    img.style.display = 'none';
    placeholder.style.display = '';
    placeholder.textContent = (card.getAttribute('data-spotlight-name') || '?').charAt(0).toUpperCase();
  }

  const accomplishment = card.getAttribute('data-spotlight-accomplishment') || '';
  document.getElementById('spotlightAccomplishmentWrap').style.display = accomplishment ? '' : 'none';
  document.getElementById('spotlightAccomplishment').textContent = accomplishment;

  const note = card.getAttribute('data-spotlight-note') || '';
  document.getElementById('spotlightNoteWrap').style.display = note ? '' : 'none';
  document.getElementById('spotlightNote').textContent = note ? '"' + note + '"' : '';

  const description = card.getAttribute('data-spotlight-description') || '';
  document.getElementById('spotlightDescriptionWrap').style.display = description ? '' : 'none';
  document.getElementById('spotlightDescription').textContent = description;
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
