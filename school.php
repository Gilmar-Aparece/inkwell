<?php
// Inside/dashboard page — suppress the marketing topbar (see includes/header.php).
$__hideTopbar = true;
require_once __DIR__ . '/includes/schools.php';
require_once __DIR__ . '/includes/events.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/exams_db.php';
require_once __DIR__ . '/includes/students.php';
require_once __DIR__ . '/includes/posts.php';

$id = (int) ($_GET['id'] ?? 0);
$school = $id ? inkwell_get_school($id) : null;

if (!$school) {
  http_response_code(404);
  $pageTitle = 'School not found';
  include __DIR__ . '/includes/header.php';
  $driveActive = 'schools';
  $driveCrumbs = [['label' => 'Home', 'href' => '/index.php'], ['label' => 'School not found']];
  include __DIR__ . '/includes/drive_shell_top.php';
  echo '<p class="admin-sub">That school doesn\'t exist, or may have been removed. <a href="/schools.php">Back to trusted schools →</a></p>';
  include __DIR__ . '/includes/drive_shell_bottom.php';
  include __DIR__ . '/includes/footer.php';
  exit;
}

$stats = inkwell_school_stats($id);
$faculty = inkwell_list_school_teachers($id, true);
$facultyByDept = inkwell_school_faculty_by_department($id);
$schoolEvents = inkwell_school_events($id, 30);
$schoolSubjects = inkwell_school_subjects($id);
$topStudents = array_slice(inkwell_featured_students($id), 0, 5);
$dean = inkwell_get_user($school['dean_id']);
$school['dean_name'] = $dean ? $dean['name'] : 'Unknown';
$__me = inkwell_current_user();
$schoolPosts = inkwell_list_school_posts($id, $__me ? $__me['id'] : 0, 20);

$pageTitle = $school['name'];
include __DIR__ . '/includes/header.php';
$driveActive = 'schools';
$driveCrumbs = [['label' => 'Home', 'href' => '/index.php'], ['label' => 'Schools', 'href' => '/schools.php'], ['label' => $school['name']]];
$driveHideCta = true;
include __DIR__ . '/includes/drive_shell_top.php';
?>
    <div class="school-page-head">
      <?php if ($school['logo']): ?>
        <img class="school-page-logo" src="/assets/uploads/<?php echo htmlspecialchars($school['logo']); ?>" alt="<?php echo htmlspecialchars($school['name']); ?> logo" loading="lazy">
      <?php else: ?>
        <span class="school-page-logo school-page-logo-empty" aria-hidden="true">🏫</span>
      <?php endif; ?>
      <div class="school-page-head-text">
        <h1 class="drive-title" style="margin:0;"><?php echo htmlspecialchars($school['name']); ?></h1>
        <span class="dean-line">Dean: <?php echo htmlspecialchars($school['dean_name']); ?></span>
      </div>
    </div>

    <?php if (!empty($school['mission'])): ?>
      <p class="school-mission"><?php echo nl2br(htmlspecialchars($school['mission'])); ?></p>
    <?php endif; ?>

    <div class="stat-row school-page-stats">
      <div class="stat-pill"><strong><?php echo (int) $stats['teacher_count']; ?></strong><span>Teachers</span></div>
      <div class="stat-pill"><strong><?php echo (int) $stats['student_count']; ?></strong><span>Students</span></div>
      <div class="stat-pill"><strong><?php echo (int) $stats['subject_count']; ?></strong><span>Subjects</span></div>
      <div class="stat-pill"><strong><?php echo (int) $stats['certificate_count']; ?></strong><span>Certificates</span></div>
    </div>

    <div class="school-page-grid">
      <section class="admin-card glass-card">
        <h2>Faculty &amp; Dean (<?php echo count($faculty) + 1; ?>)</h2>
        <p class="admin-sub">
          <?php if (!empty($facultyByDept)): ?>
            Every department's Dean and teachers at <?php echo htmlspecialchars($school['name']); ?> — swipe within a department, tap a card for their profile.
          <?php else: ?>
            Swipe to browse everyone leading <?php echo htmlspecialchars($school['name']); ?>.
          <?php endif; ?>
        </p>
        <?php if (!empty($facultyByDept)): ?>
          <?php echo inkwell_render_department_faculty_groups($facultyByDept); ?>
        <?php else: ?>
          <?php echo inkwell_render_faculty_dean_swipe($faculty, $dean); ?>
        <?php endif; ?>
      </section>

      <section class="admin-card glass-card">
        <h2>Events (<?php echo count($schoolEvents); ?>)</h2>
        <p class="admin-sub">Announcements posted by <?php echo htmlspecialchars($school['name']); ?>'s dean and teachers.</p>
        <?php if (empty($schoolEvents)): ?>
          <p class="admin-sub">No announcements from this school yet.</p>
        <?php else: ?>
          <div class="school-event-list">
            <?php foreach ($schoolEvents as $ev): ?>
              <div class="school-event-item">
                <div class="school-event-head">
                  <span class="drive-activity-avatar"><?php echo strtoupper(substr($ev['author_name'], 0, 1)); ?></span>
                  <div>
                    <strong><?php echo htmlspecialchars($ev['title']); ?></strong>
                    <span class="admin-sub" style="margin:0;">by <?php echo htmlspecialchars($ev['author_name']); ?> · <?php echo htmlspecialchars(date('M j, Y', strtotime($ev['created_at']))); ?></span>
                  </div>
                </div>
                <p class="school-event-body"><?php echo nl2br(htmlspecialchars($ev['body'])); ?></p>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </section>
    </div>

    <?php if (!empty($topStudents)): ?>
      <section class="admin-card glass-card" style="margin-top:24px;">
        <h2>Top students</h2>
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
              <?php if (!empty($f['avatar'])): ?>
                <img class="student-avatar-img" src="/assets/uploads/<?php echo htmlspecialchars($f['avatar']); ?>" alt="" loading="lazy">
              <?php else: ?>
                <span class="student-avatar-placeholder" aria-hidden="true"><?php echo strtoupper(substr($f['name'], 0, 1)); ?></span>
              <?php endif; ?>
              <span class="top-student-card-name"><?php echo htmlspecialchars($f['name']); ?></span>
              <span class="top-student-card-course"><?php echo htmlspecialchars($f['course'] ?: '—'); ?></span>
              <?php if (!empty($f['accomplishment'])): ?><span class="top-student-card-accomplishment"><?php echo htmlspecialchars($f['accomplishment']); ?></span><?php endif; ?>
              <?php if (!empty($f['note'])): ?><span class="top-student-card-note">"<?php echo htmlspecialchars($f['note']); ?>"</span><?php endif; ?>
            </button>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endif; ?>

    <section class="admin-card glass-card" style="margin-top:24px;">
      <h2>Teacher &amp; student posts</h2>
      <p class="admin-sub">Swipe through what <?php echo htmlspecialchars($school['name']); ?>'s teachers and students have shared — just like a page.</p>
      <?php echo inkwell_render_school_posts_swipe($schoolPosts); ?>
    </section>

    <section class="admin-card glass-card" style="margin-top:24px;">
      <h2>Subjects (<?php echo count($schoolSubjects); ?>)</h2>
      <p class="admin-sub">Classes taught by <?php echo htmlspecialchars($school['name']); ?>'s teachers — join one to unlock every exam inside it.</p>
      <?php if (empty($schoolSubjects)): ?>
        <p class="admin-sub">No subjects offered yet.</p>
      <?php else: ?>
        <div class="admin-table-wrap">
          <table class="admin-table">
            <thead><tr><th>Subject</th><th>Teacher</th><th>Exams</th><th>Students</th><th></th></tr></thead>
            <tbody>
              <?php foreach ($schoolSubjects as $s): ?>
                <tr>
                  <td><?php echo htmlspecialchars($s['title']); ?></td>
                  <td><?php echo htmlspecialchars($s['teacher_name']); ?></td>
                  <td><?php echo (int) $s['exam_count']; ?></td>
                  <td><?php echo (int) $s['student_count']; ?></td>
                  <td>
                    <?php if (!$__me): ?>
                      <a class="btn" href="/login.php?next=<?php echo urlencode('/join-class.php'); ?>">Log in to join</a>
                    <?php elseif ($__me['role'] !== 'student'): ?>
                      <span class="admin-sub">Student accounts only</span>
                    <?php else: ?>
                      <a class="btn primary" href="/join-class.php">Join →</a>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </section>
<?php include __DIR__ . '/includes/drive_shell_bottom.php'; ?>
<?php include __DIR__ . '/includes/teacher_profile_modal.php'; ?>

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
