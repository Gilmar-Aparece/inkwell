<?php
// Inside/dashboard page — suppress the marketing topbar (see includes/header.php).
$__hideTopbar = true;
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/schools.php';

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

$stats = inkwell_school_stats($school['id']);
$faculty = inkwell_list_school_teachers($school['id'], true);
$facultyByDept = inkwell_school_faculty_by_department($school['id']);
$dean = inkwell_get_user($school['dean_id']);
$school['dean_name'] = $dean ? $dean['name'] : 'Unknown';

$__me = $user;
$pageTitle = $school['name'] . ' faculty';
include __DIR__ . '/includes/header.php';
$driveActive = 'my-school';
$driveCrumbs = [
  ['label' => 'Home', 'href' => '/index.php'],
  ['label' => 'My school', 'href' => '/my-school.php'],
  ['label' => 'Faculty'],
];
$driveTitle = 'Faculty & Dean';
$driveSubtitle = 'Every Dean and teacher at ' . $school['name'] . ' — tap a card for their profile.';
include __DIR__ . '/includes/drive_shell_top.php';
?>

<div class="school-hero school-hero-v2 faculty-hero">
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
      <h1 class="drive-title" style="margin:0;"><?php echo htmlspecialchars($school['name']); ?> faculty</h1>
      <span class="dean-line"><span class="dean-line-dot" aria-hidden="true"></span>
        <?php if (!empty($facultyByDept)): ?>
          Grouped by department — tap a card for their profile.
        <?php else: ?>
          Everyone leading <?php echo htmlspecialchars($school['name']); ?>.
        <?php endif; ?>
      </span>
    </div>
    <span class="hub-count faculty-hero-count"><?php echo count($faculty) + 1; ?> people</span>
  </div>
</div>

<?php if (!empty($facultyByDept)): ?>
  <div class="dept-card-stack">
    <?php echo inkwell_render_department_faculty_cards($facultyByDept); ?>
  </div>
<?php else: ?>
  <section class="admin-card glass-card">
    <?php echo inkwell_render_faculty_dean_grid($faculty, $dean); ?>
  </section>
<?php endif; ?>

<?php include __DIR__ . '/includes/drive_shell_bottom.php'; ?>
<?php include __DIR__ . '/includes/teacher_profile_modal.php'; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>
