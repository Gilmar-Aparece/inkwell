<?php
/**
 * Shared "Drive" shell — left nav sidebar + topbar row + page title.
 * The including page must set, before requiring this file:
 *   $driveActive      string — one of: lessons, exams, events, playground, account
 *   $driveCrumbs      array of ['label'=>..,'href'=>..] — last item should omit href
 *   $driveTitle       string — H1 shown under the topbar row
 *   $driveSubtitle    string (optional) — supporting paragraph under the title
 * Optionally:
 *   $driveHideCta     bool — hide the "Start with HTML" CTA button
 * Must be included AFTER includes/header.php (relies on $__me / inkwell_current_user()).
 * The page must close with includes/drive_shell_bottom.php.
 */
$__me = isset($__me) ? $__me : inkwell_current_user();
$__driveCrumbs = $driveCrumbs ?? [];

$__continueLessonHref = '/lesson.php?cat=html&slug=intro';
$__continueLessonLabel = 'Start with HTML';
if ($__me && !empty($__me['last_lesson_cat']) && !empty($__me['last_lesson_slug'])) {
  require_once __DIR__ . '/../data/lessons.php';
  $__lastCat = inkwell_category($__me['last_lesson_cat']);
  if ($__lastCat && isset($__lastCat['lessons'][$__me['last_lesson_slug']])) {
    $__continueLessonHref = '/lesson.php?cat=' . urlencode($__me['last_lesson_cat']) . '&slug=' . urlencode($__me['last_lesson_slug']);
    $__continueLessonLabel = 'Continue: ' . $__lastCat['lessons'][$__me['last_lesson_slug']]['title'];
  }
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

      <?php if (empty($driveHideCta)): ?>
        <a class="drive-cta" href="<?php echo htmlspecialchars($__continueLessonHref); ?>">
          <span>▶ <?php echo htmlspecialchars($__continueLessonLabel); ?></span>
        </a>
      <?php endif; ?>

      <nav class="drive-nav">
        <div class="drive-nav-section-label">Learn</div>
        <a class="drive-nav-link<?php echo $driveActive === 'lessons' ? ' active' : ''; ?>" href="/index.php"><span class="drive-nav-icon">🏠</span>Lessons</a>
        <a class="drive-nav-link<?php echo $driveActive === 'playground' ? ' active' : ''; ?>" href="/playground.php"><span class="drive-nav-icon">▶</span>Playground</a>

        <div class="drive-nav-section-label">Exams &amp; Certification</div>
        <div class="drive-nav-group">
          <div class="drive-nav-group-head">
            <a class="drive-nav-link<?php echo $driveActive === 'exams' ? ' active' : ''; ?>" href="/exams.php"><span class="drive-nav-icon">🎓</span>Exams</a>
            <button type="button" class="drive-nav-caret<?php echo $driveActive === 'exams' ? ' open' : ''; ?>" data-nav-toggle="examsNavSub" aria-expanded="<?php echo $driveActive === 'exams' ? 'true' : 'false'; ?>" aria-controls="examsNavSub" aria-label="Toggle exam categories">⌄</button>
          </div>
          <div class="drive-nav-sub" id="examsNavSub"<?php echo $driveActive === 'exams' ? '' : ' hidden'; ?>>
            <a class="drive-nav-sublink<?php echo ($driveActiveSub ?? '') === 'selfstudy' ? ' active' : ''; ?>" href="/self-study-exams.php">Self-study exams</a>
            <a class="drive-nav-sublink<?php echo ($driveActiveSub ?? '') === 'official' ? ' active' : ''; ?>" href="/official-certification-exams.php">Official certification exams</a>
          </div>
        </div>
        <a class="drive-nav-link<?php echo $driveActive === 'subjects' ? ' active' : ''; ?>" href="/join-class.php"><span class="drive-nav-icon">📚</span>Join a Class</a>
        <?php if ($__me && in_array($__me['role'], ['student', 'teacher'], true)): ?>
          <a class="drive-nav-link<?php echo $driveActive === 'my-section' ? ' active' : ''; ?>" href="/my-section.php"><span class="drive-nav-icon">👥</span>My Section</a>
        <?php endif; ?>
        <a class="drive-nav-link<?php echo $driveActive === 'certificates' ? ' active' : ''; ?>" href="<?php echo $__me ? '/account.php#certificates' : '/login.php?next=' . urlencode('/account.php#certificates'); ?>"><span class="drive-nav-icon">📜</span>Certificates</a>

        <div class="drive-nav-section-label">Enrollment</div>
        <a class="drive-nav-link<?php echo $driveActive === 'admission' ? ' active' : ''; ?>" href="/admission-requirements.php"><span class="drive-nav-icon">📋</span>Admission Requirements</a>
        <?php if (!$__me || $__me['role'] === 'student'): ?>
          <a class="drive-nav-link<?php echo $driveActive === 'enroll' ? ' active' : ''; ?>" href="<?php echo $__me ? '/enroll.php' : '/login.php?next=' . urlencode('/enroll.php'); ?>"><span class="drive-nav-icon">📝</span>Enrollment Portal</a>
        <?php endif; ?>
        <a class="drive-nav-link<?php echo $driveActive === 'schools' ? ' active' : ''; ?>" href="/schools.php"><span class="drive-nav-icon">🏫</span>Schools</a>
        <?php if ($__me && $__me['role'] !== 'dean'): ?>
          <a class="drive-nav-link<?php echo $driveActive === 'my-school' ? ' active' : ''; ?>" href="/my-school.php"><span class="drive-nav-icon">🎒</span>My school</a>
        <?php endif; ?>

        <div class="drive-nav-section-label">Community</div>
        <a class="drive-nav-link<?php echo $driveActive === 'events' ? ' active' : ''; ?>" href="/events.php"><span class="drive-nav-icon">📣</span>Events</a>
        <?php if ($__me): ?>
          <a class="drive-nav-link<?php echo $driveActive === 'community' ? ' active' : ''; ?>" href="/posts.php"><span class="drive-nav-icon">🖼️</span>Community</a>
          <a class="drive-nav-link<?php echo $driveActive === 'notes' ? ' active' : ''; ?>" href="/notes.php"><span class="drive-nav-icon">📝</span>Notes</a>
        <?php endif; ?>

        <?php if ($__me && in_array($__me['role'], ['teacher', 'dean', 'registrar', 'admin'], true)): ?>
          <div class="drive-nav-section-label">Workspace</div>
          <?php if ($__me['role'] === 'teacher'): ?>
            <a class="drive-nav-link" href="/teacher/overview.php"><span class="drive-nav-icon">🧑‍🏫</span>Teacher dashboard</a>
          <?php elseif ($__me['role'] === 'dean'): ?>
            <a class="drive-nav-link" href="/dean/overview.php"><span class="drive-nav-icon">🏫</span>Dean dashboard</a>
          <?php elseif ($__me['role'] === 'registrar'): ?>
            <a class="drive-nav-link" href="/registrar/overview.php"><span class="drive-nav-icon">🗂️</span>Registrar dashboard</a>
          <?php else: ?>
            <a class="drive-nav-link" href="/admin/dashboard.php"><span class="drive-nav-icon">🛡️</span>Admin dashboard</a>
          <?php endif; ?>
        <?php endif; ?>
        <a class="drive-nav-link<?php echo $driveActive === 'account' ? ' active' : ''; ?>" href="<?php echo $__me ? '/account.php' : '/login.php'; ?>"><span class="drive-nav-icon">👤</span><?php echo $__me ? 'My profile' : 'Log in'; ?></a>
      </nav>

      <?php if (!$__me): ?>
        <a class="drive-upgrade-btn" href="/register.php">Register free →</a>
      <?php endif; ?>
    </aside>
    <div class="drive-backdrop" id="driveSidebarBackdrop"></div>

    <div class="drive-main<?php echo !empty($driveFullBleedMobile) ? ' drive-main-fullbleed' : ''; ?>">
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
          <div class="drive-breadcrumb">
            <?php foreach ($__driveCrumbs as $__i => $__c): ?>
              <?php if ($__i > 0): ?><span>›</span><?php endif; ?>
              <?php if (!empty($__c['href'])): ?><a href="<?php echo htmlspecialchars($__c['href']); ?>"><?php echo htmlspecialchars($__c['label']); ?></a><?php else: ?><?php echo htmlspecialchars($__c['label']); ?><?php endif; ?>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="drive-topbar-right">
          <div class="drive-toolbar">
            <button type="button" class="drive-tool-btn theme-toggle" title="Toggle dark mode" aria-label="Toggle dark mode">◐</button>
            <a class="drive-tool-btn" href="/playground.php" title="Open playground">⌘</a>
          </div>
        </div>
      </div>

      <?php if (!empty($driveTitle)): ?><h1 class="drive-title"><?php echo htmlspecialchars($driveTitle); ?></h1><?php endif; ?>
      <?php if (!empty($driveSubtitle)): ?><p class="drive-subtitle"><?php echo $driveSubtitle; ?></p><?php endif; ?>
