<?php
require_once __DIR__ . '/data/lessons.php';
require_once __DIR__ . '/includes/schools.php';
require_once __DIR__ . '/includes/events.php';
require_once __DIR__ . '/includes/exams_db.php';
require_once __DIR__ . '/includes/students.php';
require_once __DIR__ . '/includes/auth.php';

// Teacher dashboard shortcut: announce "exam today" + (optionally) open that
// exam for a time window that closes itself automatically. Must run before
// header.php prints any HTML, since a successful post redirects.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'post_exam_today') {
  $__postingUser = inkwell_current_user();
  if ($__postingUser && $__postingUser['role'] === 'teacher' && $__postingUser['status'] === 'active') {
    $examId = (int) ($_POST['category_id'] ?? 0);
    $exam = inkwell_get_teacher_category($examId);
    $note = trim($_POST['note'] ?? '');
    $until = trim($_POST['available_until'] ?? '');
    $until = $until !== '' ? str_replace('T', ' ', $until) . ':00' : '';
    if ($exam && (int) $exam['teacher_id'] === (int) $__postingUser['id']) {
      if ($until !== '') {
        inkwell_update_exam_schedule($examId, $__postingUser['id'], true, '', $until);
      }
      $title = '🎓 Exam today: ' . $exam['title'];
      $body = $until !== ''
        ? 'Open until ' . date('g:i A', strtotime($until)) . ' today — it\'ll close itself automatically after that.'
        : 'Head to Exams to take it.';
      if ($note !== '') $body .= "\n\n" . $note;
      inkwell_create_event($__postingUser['id'], 'teacher', $title, $body);
    }
    header('Location: /index.php');
    exit;
  }
}

$pageTitle = 'Learn to code';
// Guests see the marketing topbar (this is their landing page); logged-in
// users get the dashboard shell instead, so the topbar would be a redundant
// second header on top of it.
if (inkwell_current_user()) { $__hideTopbar = true; }
include __DIR__ . '/includes/header.php';
$cats = inkwell_categories();
$catsByCourse = inkwell_categories_by_course();
$defaultCourseCode = null;
foreach ($catsByCourse as $__code => $__data) {
  if (!empty($__data['tracks'])) { $defaultCourseCode = $__code; break; }
}
if ($defaultCourseCode === null) $defaultCourseCode = array_key_first($catsByCourse);
$recentEvents = array_slice(inkwell_all_events(50), 0, 5);
$topLearners = inkwell_top_learners(5);
$totalLessons = 0;
foreach ($cats as $c) { $totalLessons += count($c['lessons']); }
$allSchoolsCount = count(inkwell_list_schools());
$allCertsCount = count(inkwell_db_all_certificates());
$allSubjectsCount = count(inkwell_all_subjects());

$catIcons = [
  'html' => '📄', 'css' => '🎨', 'js' => '⚡', 'php' => '🐘',
  'c' => '🔧', 'cpp' => '🧩', 'java' => '☕', 'python' => '🐍', 'csharp' => '🎯',
];
$__me = inkwell_current_user();

$__continueLessonHref = '/lesson.php?cat=html&slug=intro';
$__continueLessonLabel = 'Start with HTML';
if ($__me && !empty($__me['last_lesson_cat']) && !empty($__me['last_lesson_slug'])) {
  $__lastCat = inkwell_category($__me['last_lesson_cat']);
  if ($__lastCat && isset($__lastCat['lessons'][$__me['last_lesson_slug']])) {
    $__continueLessonHref = '/lesson.php?cat=' . urlencode($__me['last_lesson_cat']) . '&slug=' . urlencode($__me['last_lesson_slug']);
    $__continueLessonLabel = 'Continue: ' . $__lastCat['lessons'][$__me['last_lesson_slug']]['title'];
  }
}
$__isActiveTeacher = $__me && $__me['role'] === 'teacher' && $__me['status'] === 'active';
$__myTeacherExams = $__isActiveTeacher ? inkwell_teacher_categories($__me['id']) : [];

// Guests see a marketing landing page (hero, features, pricing) instead of
// the lessons dashboard below, which is only useful once you're signed in.
if (!$__me) {
  include __DIR__ . '/includes/landing.php';
  include __DIR__ . '/includes/footer.php';
  exit;
}
?>
<main>
  <div class="drive-shell">

    <aside class="drive-sidebar" id="driveSidebar">
      <div class="drive-sidebar-top">
        <a href="/index.php" class="drive-sidebar-logo" aria-label="Inkwell home">
          <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M18.5 2.5c1 1 1.1 2.6.2 3.7L9.8 16.9l-4.3 1.1 1.1-4.3L16.5 3c1.1-1 2.7-1.1 3.7-.2z" fill="#fff"/>
            <path d="M5.5 18l-2 3.5 3.5-2-1.5-1.5z" fill="#fff" opacity="0.55"/>
          </svg>
        </a>
        <?php if ($__me): ?>
          <div class="drive-user-wrap">
            <button type="button" class="drive-user" id="driveUserTrigger" aria-haspopup="true" aria-expanded="false" aria-controls="driveUserMenu">
              <span class="drive-user-avatar">
                <?php if (!empty($__me['avatar'])): ?>
                  <img src="/assets/uploads/<?php echo htmlspecialchars($__me['avatar']); ?>" alt="" loading="lazy">
                <?php else: ?>
                  <?php echo strtoupper(substr($__me['name'], 0, 1)); ?>
                <?php endif; ?>
              </span>
              <div class="drive-user-info">
                <strong><?php echo htmlspecialchars($__me['name']); ?></strong>
                <span><?php echo htmlspecialchars(ucfirst($__me['role'])); ?></span>
              </div>
              <svg class="drive-user-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>
            </button>
            <div class="drive-user-menu" id="driveUserMenu">
              <a href="/account.php"><span class="drive-nav-icon">👤</span>My profile</a>
              <a href="/account.php#avatar"><span class="drive-nav-icon">🖼️</span>Edit profile photo</a>
              <a href="/logout.php" class="drive-user-menu-logout"><span class="drive-nav-icon">↩</span>Log out</a>
            </div>
          </div>
        <?php else: ?>
          <div class="drive-user">
            <span class="drive-user-avatar">🖊</span>
            <div class="drive-user-info">
              <strong>Guest</strong>
              <span>Not signed in</span>
            </div>
          </div>
        <?php endif; ?>
      </div>

      <a class="drive-cta" href="<?php echo $__continueLessonHref; ?>">
        <span>▶ <?php echo $__continueLessonLabel; ?></span>
      </a>

      <nav class="drive-nav">
        <div class="drive-nav-section-label">Learn</div>
        <a class="drive-nav-link active" href="/index.php"><span class="drive-nav-icon">🏠</span>Lessons</a>
        <a class="drive-nav-link" href="/playground.php"><span class="drive-nav-icon">▶</span>Playground</a>

        <div class="drive-nav-section-label">Exams &amp; Certification</div>
        <a class="drive-nav-link" href="/exams.php"><span class="drive-nav-icon">🎓</span>Exams</a>
        <a class="drive-nav-link" href="/join-class.php"><span class="drive-nav-icon">📚</span>Join a Class</a>
        <?php if ($__me && in_array($__me['role'], ['student', 'teacher'], true)): ?>
          <a class="drive-nav-link" href="/my-section.php"><span class="drive-nav-icon">👥</span>My Section</a>
        <?php endif; ?>
        <a class="drive-nav-link" href="<?php echo $__me ? '/account.php#certificates' : '/login.php?next=' . urlencode('/account.php#certificates'); ?>"><span class="drive-nav-icon">📜</span>Certificates</a>
        <a class="drive-nav-link" href="/my-billing.php"><span class="drive-nav-icon">💳</span>My billing</a>

        <div class="drive-nav-section-label">Enrollment</div>
        <a class="drive-nav-link" href="/admission-requirements.php"><span class="drive-nav-icon">📋</span>Admission Requirements</a>
        <?php if (!$__me || $__me['role'] === 'student'): ?>
          <a class="drive-nav-link" href="<?php echo $__me ? '/enroll.php' : '/login.php?next=' . urlencode('/enroll.php'); ?>"><span class="drive-nav-icon">📝</span>Enrollment Portal</a>
        <?php endif; ?>
        <a class="drive-nav-link" href="/schools.php"><span class="drive-nav-icon">🏫</span>Schools</a>
        <?php if ($__me && $__me['role'] !== 'dean'): ?>
          <a class="drive-nav-link" href="/my-school.php"><span class="drive-nav-icon">🎒</span>My school</a>
        <?php endif; ?>

        <div class="drive-nav-section-label">Community</div>
        <a class="drive-nav-link" href="/events.php"><span class="drive-nav-icon">📣</span>Events</a>
        <?php if ($__me): ?>
          <a class="drive-nav-link" href="/posts.php"><span class="drive-nav-icon">🖼️</span>Community</a>
          <a class="drive-nav-link" href="/notes.php"><span class="drive-nav-icon">📝</span>Notes</a>
        <?php endif; ?>

        <?php if ($__me && in_array($__me['role'], ['teacher', 'dean', 'registrar', 'admin'], true)): ?>
          <div class="drive-nav-section-label">Workspace</div>
          <?php if ($__me['role'] === 'teacher'): ?>
            <a class="drive-nav-link" href="/teacher/dashboard.php"><span class="drive-nav-icon">🧑‍🏫</span>Teacher dashboard</a>
          <?php elseif ($__me['role'] === 'dean'): ?>
            <a class="drive-nav-link" href="/dean/dashboard.php"><span class="drive-nav-icon">🏫</span>Dean dashboard</a>
          <?php elseif ($__me['role'] === 'registrar'): ?>
            <a class="drive-nav-link" href="/registrar/dashboard.php"><span class="drive-nav-icon">🗂️</span>Registrar dashboard</a>
          <?php else: ?>
            <a class="drive-nav-link" href="/admin/index.php"><span class="drive-nav-icon">🛡️</span>Admin dashboard</a>
          <?php endif; ?>
        <?php endif; ?>
        <a class="drive-nav-link" href="<?php echo $__me ? '/account.php' : '/login.php'; ?>"><span class="drive-nav-icon">👤</span><?php echo $__me ? 'My profile' : 'Log in'; ?></a>
      </nav>

      <div class="drive-progress-card">
        <div class="drive-progress-head">
          <span>Course library</span>
          <span><?php echo count($cats); ?> tracks</span>
        </div>
        <div class="drive-progress-bar"><span style="width:68%;"></span></div>
        <span class="drive-progress-sub"><?php echo $totalLessons; ?> lessons total</span>
      </div>
      <?php if (!$__me): ?>
        <a class="drive-upgrade-btn" href="/register.php">Register free →</a>
      <?php endif; ?>
    </aside>
    <div class="drive-backdrop" id="driveSidebarBackdrop"></div>

    <div class="drive-main">
      <div class="drive-topbar-row">
        <div class="drive-topbar-left">
          <button type="button" class="drive-mobile-menu-btn" id="driveMobileMenuToggle" aria-label="Open menu" aria-expanded="false" aria-controls="driveSidebar">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
          </button>
          <a href="/index.php" class="drive-mobile-brand" aria-label="Inkwell home">
            <span class="drive-mobile-brand-mark" aria-hidden="true">
              <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M18.5 2.5c1 1 1.1 2.6.2 3.7L9.8 16.9l-4.3 1.1 1.1-4.3L16.5 3c1.1-1 2.7-1.1 3.7-.2z" fill="#fff"/>
                <path d="M5.5 18l-2 3.5 3.5-2-1.5-1.5z" fill="#fff" opacity="0.55"/>
              </svg>
            </span>
          </a>
          <div class="drive-breadcrumb"><a href="/index.php">Home</a> <span>›</span> Lessons</div>
        </div>
        <div class="drive-topbar-right">
          <?php if (!empty($topLearners)): ?>
            <span class="drive-share-label">Top learners</span>
            <div class="drive-avatars">
              <?php foreach ($topLearners as $tl): ?>
                <span class="drive-avatar-chip" title="<?php echo htmlspecialchars($tl['name']); ?>">
                  <?php if (!empty($tl['avatar'])): ?>
                    <img src="/assets/uploads/<?php echo htmlspecialchars($tl['avatar']); ?>" alt="" loading="lazy">
                  <?php else: ?>
                    <?php echo strtoupper(substr($tl['name'], 0, 1)); ?>
                  <?php endif; ?>
                </span>
              <?php endforeach; ?>
              <a class="drive-avatar-add" href="/register.php" title="Join Inkwell">+</a>
            </div>
          <?php else: ?>
            <a class="drive-avatar-add" href="/register.php" title="Join Inkwell">+ Join Inkwell</a>
          <?php endif; ?>
          <div class="drive-toolbar">
            <button type="button" class="drive-tool-btn theme-toggle" title="Toggle dark mode" aria-label="Toggle dark mode">◐</button>
            <a class="drive-tool-btn" href="/playground.php" title="Open playground">⌘</a>
            <button class="drive-tool-btn" type="button" title="Grid view">▦</button>
            <button class="drive-tool-btn" type="button" title="More">⋯</button>
          </div>
        </div>
      </div>

      <h1 class="drive-title">Lessons</h1>
      <p class="drive-subtitle">Inkwell pairs short lessons with a real code editor — the same one behind VS Code. Change the code, press Run, see the result right beside the note that explained it.</p>

      <div class="dept-tabs" id="deptTabs" role="tablist" aria-label="Filter lessons by department">
        <?php foreach ($catsByCourse as $courseCode => $courseData): $__id = 'course-' . strtolower($courseCode); ?>
          <button type="button" class="dept-tab<?php echo $courseCode === $defaultCourseCode ? ' active' : ''; ?>" role="tab" data-dept-target="<?php echo htmlspecialchars($__id); ?>">
            <?php echo htmlspecialchars($courseCode); ?>
            <span class="dept-tab-count"><?php echo count($courseData['tracks']); ?></span>
          </button>
        <?php endforeach; ?>
      </div>

      <div class="dept-swipe" id="deptSwipe" data-default="course-<?php echo htmlspecialchars(strtolower($defaultCourseCode)); ?>">
        <?php foreach ($catsByCourse as $courseCode => $courseData):
          $courseTracks = $courseData['tracks'];
          $courseLessonCount = 0;
          foreach ($courseTracks as $ct) { $courseLessonCount += count($ct['lessons']); }
        ?>
          <section class="dept-panel" id="course-<?php echo htmlspecialchars(strtolower($courseCode)); ?>" role="tabpanel">
            <div class="dept-panel-head">
              <h2><?php echo htmlspecialchars($courseCode); ?> <span class="dept-panel-name">— <?php echo htmlspecialchars($courseData['name']); ?></span></h2>
              <span class="dept-panel-count"><?php echo count($courseTracks); ?> track<?php echo count($courseTracks) === 1 ? '' : 's'; ?>, <?php echo $courseLessonCount; ?> lesson<?php echo $courseLessonCount === 1 ? '' : 's'; ?></span>
            </div>

            <?php if (empty($courseTracks)): ?>
              <p class="admin-sub" style="padding:4px 2px 6px;">No <?php echo htmlspecialchars($courseCode); ?> lesson tracks yet — check back soon.</p>
            <?php else: ?>
              <section class="drive-grid">
                <?php foreach ($courseTracks as $catKey => $cat):
                  $firstSlug = array_key_first($cat['lessons']);
                  $count = count($cat['lessons']);
                  $icon = $catIcons[$catKey] ?? '📘';
                ?>
                  <div class="drive-file-card" id="<?php echo htmlspecialchars($catKey); ?>">
                    <a class="drive-file-cover" href="/lesson.php?cat=<?php echo urlencode($catKey); ?>&slug=<?php echo urlencode($firstSlug); ?>"
                       style="background:linear-gradient(135deg, <?php echo $cat['color']; ?>26, <?php echo $cat['color']; ?>0d);">
                      <span class="drive-file-icon" style="background:<?php echo $cat['color']; ?>1f; color:<?php echo $cat['color']; ?>;"><?php echo $icon; ?></span>
                      <span class="drive-file-badge"><?php echo htmlspecialchars($cat['label']); ?></span>
                    </a>
                    <div class="drive-file-body">
                      <a class="drive-file-name" href="/lesson.php?cat=<?php echo urlencode($catKey); ?>&slug=<?php echo urlencode($firstSlug); ?>"><?php echo htmlspecialchars($cat['tagline']); ?></a>
                      <div class="drive-file-meta">
                        <span><?php echo $count; ?> lesson<?php echo $count === 1 ? '' : 's'; ?></span>
                        <a class="drive-file-exam" href="/exam.php?cat=<?php echo urlencode($catKey); ?>">🎓 Exam</a>
                      </div>
                    </div>
                  </div>
                <?php endforeach; ?>
              </section>
            <?php endif; ?>
          </section>
        <?php endforeach; ?>
      </div>


      <section class="drive-schools drive-schools-teaser">
        <div class="drive-section-head">
          <h2>Schools</h2>
          <p>See every school set up by an approved dean, ranked by how active their teachers and students are.</p>
        </div>
        <a class="btn primary" href="/schools.php">🏫 View trusted schools →</a>
      </section>
    </div>

    <aside class="drive-activity">
      <div class="drive-activity-tabs" data-role="activity-tabs">
        <button type="button" class="drive-tab active" data-tab="activity">Activity</button>
        <button type="button" class="drive-tab" data-tab="information">Information</button>
        <?php if ($__isActiveTeacher): ?>
          <button type="button" class="drive-activity-fab" data-modal-open="postExamTodayModal" title="Post: exam today">+</button>
        <?php endif; ?>
      </div>

      <div class="drive-activity-panel active" data-panel="activity">
        <div class="drive-activity-list">
          <?php if (empty($recentEvents)): ?>
            <p class="admin-sub">No announcements yet — check back soon.</p>
          <?php else: foreach ($recentEvents as $ev): ?>
            <?php $__isExamPost = strpos($ev['title'], '🎓 Exam today:') === 0; ?>
            <a class="drive-activity-item" href="/events.php">
              <span class="drive-activity-avatar"><?php echo strtoupper(substr($ev['author_name'], 0, 1)); ?></span>
              <div class="drive-activity-body">
                <p><strong><?php echo htmlspecialchars($ev['author_name']); ?></strong> posted in <?php echo htmlspecialchars(ucfirst($ev['author_role'])); ?> events</p>
                <div class="drive-activity-file">
                  <span class="drive-activity-file-ico"><?php echo $__isExamPost ? '🎓' : '📣'; ?></span>
                  <?php echo htmlspecialchars($ev['title']); ?>
                </div>
              </div>
            </a>
          <?php endforeach; endif; ?>
        </div>
      </div>

      <div class="drive-activity-panel" data-panel="information">
        <div class="drive-info-list">
          <div class="drive-info-row"><span>Course tracks</span><strong><?php echo count($cats); ?></strong></div>
          <div class="drive-info-row"><span>Lessons</span><strong><?php echo $totalLessons; ?></strong></div>
          <div class="drive-info-row"><span>Subjects taught</span><strong><?php echo $allSubjectsCount; ?></strong></div>
          <div class="drive-info-row"><span>Certificates issued</span><strong><?php echo $allCertsCount; ?></strong></div>
          <div class="drive-info-row"><span>Schools on Inkwell</span><strong><?php echo $allSchoolsCount; ?></strong></div>
        </div>
        <p class="drive-info-note">New here? Start with the <a href="/lesson.php?cat=html&amp;slug=intro">HTML basics</a> lesson, or jump into the <a href="/playground.php">playground</a> to experiment freely.</p>
        <p class="drive-info-note">Want a certificate? Browse <a href="/exams.php">exams &amp; subjects</a> — pass one and it lands on your <a href="<?php echo $__me ? '/account.php#certificates' : '/login.php'; ?>">account page</a> automatically.</p>
      </div>
    </aside>
  </div>

  <?php if ($__isActiveTeacher): ?>
    <div class="modal-backdrop" id="postExamTodayModal">
      <div class="modal" style="max-width:440px;">
        <div class="modal-head">
          <h2>Post: exam today</h2>
          <button type="button" data-modal-close aria-label="Close">✕</button>
        </div>
        <?php if (empty($__myTeacherExams)): ?>
          <p class="admin-sub">You don't have any exams yet. <a href="/teacher/dashboard.php">Create one from your dashboard →</a></p>
        <?php else: ?>
          <p class="admin-sub" style="margin-top:2px;">Announces it on the Events feed. Set a close time and it'll disable itself automatically — no need to come back and turn it off.</p>
          <form method="post" action="/index.php" class="admin-form">
            <input type="hidden" name="action" value="post_exam_today">
            <label for="examTodayPick">Which exam?</label>
            <select id="examTodayPick" name="category_id" required>
              <?php foreach ($__myTeacherExams as $te): ?>
                <option value="<?php echo (int) $te['id']; ?>"><?php echo htmlspecialchars($te['title']); ?></option>
              <?php endforeach; ?>
            </select>
            <label for="examTodayUntil">Open until (optional — leave blank to just post the announcement)</label>
            <input type="datetime-local" id="examTodayUntil" name="available_until" value="<?php echo htmlspecialchars(date('Y-m-d\T18:00')); ?>">
            <label for="examTodayNote">Note for students (optional)</label>
            <textarea id="examTodayNote" name="note" rows="3" maxlength="500" placeholder="e.g. Covers chapters 4–6, bring your notes."></textarea>
            <button class="btn primary" type="submit">Post announcement</button>
          </form>
        <?php endif; ?>
      </div>
    </div>
  <?php endif; ?>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
