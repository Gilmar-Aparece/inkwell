<?php
// Inside/dashboard page — suppress the marketing topbar (see includes/header.php).
$__hideTopbar = true;
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/schools.php';
require_once __DIR__ . '/../includes/students.php';
require_once __DIR__ . '/../includes/departments.php';

$user = inkwell_require_role('dean');
$myDepartment = !empty($user['department_id']) ? inkwell_get_department($user['department_id']) : null;

$notice = '';
$error = '';

if ($user['status'] === 'active' && $_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';
  $school = inkwell_get_school_by_dean($user['id']);

  if ($action === 'unfeature_student' && $school) {
    inkwell_remove_featured_student((int) ($_POST['featured_id'] ?? 0), $school['id']);
    $notice = 'Removed from top students.';
  }
}

$school = inkwell_get_school_by_dean($user['id']);
$stats = $school ? inkwell_school_stats($school['id'], $user['department_id'] ?? null) : null;
$featured = $school ? inkwell_featured_students($school['id']) : [];

$dashNavTitle = 'Dean';
$dashNavActive = 'school';
$dashNavItems = [
  ['key' => 'dashboard', 'group' => 'General', 'href' => '/dean/overview.php', 'label' => 'Dashboard', 'icon' => '🏠'],
  ['key' => 'school', 'group' => 'School', 'href' => '/dean/dashboard.php', 'label' => 'School profile', 'icon' => '🏫'],
  ['key' => 'signer', 'group' => 'Certificates', 'href' => '/dean/signer.php', 'label' => 'Certificate signer', 'icon' => '✍️'],
  ['key' => 'certificates', 'group' => 'Certificates', 'href' => '/dean/certificates.php', 'label' => 'Issue certificate', 'icon' => '📜'],
  ['key' => 'teachers', 'group' => 'People', 'href' => '/dean/teachers.php', 'label' => 'Teachers', 'icon' => '🧑‍🏫', 'count' => $school ? count(inkwell_list_school_teachers($school['id'], false, $user['department_id'] ?? null)) : 0],
  ['key' => 'students', 'group' => 'People', 'href' => '/dean/students.php', 'label' => 'Students', 'icon' => '🎓', 'count' => $school ? count(inkwell_school_students($school['id'])) : 0],
  ['key' => 'events', 'group' => 'Activity', 'href' => '/dean/events.php', 'label' => 'Events', 'icon' => '📣'],
];

$pageTitle = 'Dean · School profile';
include __DIR__ . '/../includes/header.php';
?>
<?php if ($user['status'] !== 'active' || !$school): ?>
  <main class="admin-main">
    <div class="admin-header-row">
      <h1>Dean dashboard</h1>
      <a class="btn" href="/logout.php">Log out</a>
    </div>

    <?php if ($error): ?><div class="exam-result fail"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

    <?php if ($user['status'] !== 'active'): ?>
      <section class="admin-card glass-card">
        <h2>Waiting for admin approval</h2>
        <p class="admin-sub">Your dean account is pending review. Once an admin approves it, you'll be able to view your school profile and manage your certificate signature here.</p>
      </section>
    <?php else: ?>
      <section class="admin-card glass-card">
        <h2>No school attached to your account</h2>
        <p class="admin-sub">Dean accounts are created by your school's Registrar and should already be attached to a school. If you're seeing this, contact your Registrar or an admin to get this fixed.</p>
      </section>
    <?php endif; ?>
  </main>
<?php else: ?>
  <div class="dash-shell">
    <?php include __DIR__ . '/../includes/dash_nav.php'; ?>
    <main class="admin-main">
      <div class="admin-header-row">
        <h1>School profile</h1>
        <a class="btn" href="/logout.php">Log out</a>
      </div>

      <?php if ($notice): ?><div class="exam-result pass"><?php echo htmlspecialchars($notice); ?></div><?php endif; ?>
      <?php if ($error): ?><div class="exam-result fail"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

      <section class="admin-card glass-card">
        <div class="school-card-head">
          <?php if ($school['logo']): ?>
            <img class="school-logo" src="/assets/uploads/<?php echo htmlspecialchars($school['logo']); ?>" alt="<?php echo htmlspecialchars($school['name']); ?> logo" loading="lazy">
          <?php else: ?>
            <span class="school-logo-placeholder" aria-hidden="true">🏫</span>
          <?php endif; ?>
          <div>
            <h2><?php echo htmlspecialchars($school['name']); ?><?php if ($myDepartment): ?> <span class="badge badge-status-active"><?php echo htmlspecialchars($myDepartment['code']); ?></span><?php endif; ?></h2>
            <p class="admin-sub">
              <?php if ($myDepartment): ?>
                Scoped to <?php echo htmlspecialchars($myDepartment['name']); ?> (<?php echo htmlspecialchars($myDepartment['code']); ?>) — the numbers and teacher list below are just your department's. View-only here — your Registrar or an admin edits the school's name, mission, and logo.
              <?php else: ?>
                Your school profile — visible wherever your teachers and classes are listed, and on the homepage's Top Schools section. View-only here — your Registrar or an admin edits the name, mission, and logo.
              <?php endif; ?>
            </p>
          </div>
        </div>
        <div class="stat-row" style="margin-top:14px;">
          <div class="stat-pill"><strong><?php echo (int) $stats['teacher_count']; ?></strong><span>Teachers</span></div>
          <div class="stat-pill"><strong><?php echo (int) $stats['student_count']; ?></strong><span>Students</span></div>
          <div class="stat-pill"><strong><?php echo (int) $stats['subject_count']; ?></strong><span>Subjects</span></div>
          <div class="stat-pill"><strong><?php echo (int) $stats['certificate_count']; ?></strong><span>Certificates</span></div>
        </div>
        <?php if (!empty($school['mission'])): ?>
          <div class="admin-form" style="margin-top:14px;">
            <label>Mission statement</label>
            <p class="admin-sub"><?php echo nl2br(htmlspecialchars($school['mission'])); ?></p>
          </div>
        <?php endif; ?>
      </section>

      <section class="admin-card glass-card">
        <div class="admin-header-row" style="margin-bottom:0;">
          <div>
            <h2>Top students (<?php echo count($featured); ?>)</h2>
            <p class="admin-sub">Standout students you've featured for this school — shown here with their photo. Feature a student from the <a href="/dean/students.php">Students page</a>.</p>
          </div>
        </div>
        <?php if (empty($featured)): ?>
          <p class="admin-sub">No top students featured yet.</p>
        <?php else: ?>
          <div class="top-students-grid">
            <?php foreach ($featured as $f): ?>
              <div class="top-student-card">
                <span class="top-student-card-badge">🏅</span>
                <button type="button" class="student-cell-btn" style="flex-direction:column; align-items:center;" data-modal-open="studentProfileModal" data-student-id="<?php echo (int) $f['student_id']; ?>">
                  <?php if (!empty($f['avatar'])): ?>
                    <img class="student-avatar-img" src="/assets/uploads/<?php echo htmlspecialchars($f['avatar']); ?>" alt="" loading="lazy">
                  <?php else: ?>
                    <span class="student-avatar-placeholder" aria-hidden="true"><?php echo strtoupper(substr($f['name'], 0, 1)); ?></span>
                  <?php endif; ?>
                  <span class="top-student-card-name"><?php echo htmlspecialchars($f['name']); ?></span>
                </button>
                <span class="top-student-card-course"><?php echo htmlspecialchars($f['course'] ?: '—'); ?></span>
                <?php if (!empty($f['accomplishment'])): ?><span class="top-student-card-accomplishment"><?php echo htmlspecialchars($f['accomplishment']); ?></span><?php endif; ?>
                <?php if (!empty($f['note'])): ?><span class="top-student-card-note">"<?php echo htmlspecialchars($f['note']); ?>"</span><?php endif; ?>
                <?php if (!empty($f['description'])): ?><p class="top-student-card-desc"><?php echo nl2br(htmlspecialchars($f['description'])); ?></p><?php endif; ?>
                <form method="post" action="/dean/dashboard.php">
                  <input type="hidden" name="action" value="unfeature_student">
                  <input type="hidden" name="featured_id" value="<?php echo (int) $f['id']; ?>">
                  <button type="submit" class="top-student-card-remove">Remove</button>
                </form>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </section>
    </main>
  </div>
  <?php include __DIR__ . '/../includes/student_profile_modal.php'; ?>
<?php endif; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
