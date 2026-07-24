<?php
// Inside/dashboard page — suppress the marketing topbar (see includes/header.php).
$__hideTopbar = true;
require_once __DIR__ . '/data/lessons.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/exams_db.php';

$user = inkwell_current_user();
$adminExams = inkwell_admin_exam_categories();

$pageTitle = 'Official certification exams';
include __DIR__ . '/includes/header.php';
$driveActive = 'exams';
$driveActiveSub = 'official';
$driveCrumbs = [['label' => 'Home', 'href' => '/index.php'], ['label' => 'Exams', 'href' => '/exams.php'], ['label' => 'Official certification exams']];
$driveTitle = 'Official certification exams';
$driveSubtitle = 'Admin-published certification exams — no class to join, just log in and take one.';
include __DIR__ . '/includes/drive_shell_top.php';
?>
    <section class="admin-card glass-card" id="official">
      <?php inkwell_render_exam_list($adminExams, function ($ex) { return '/exam.php?teacher_cat=' . (int) $ex['id']; }); ?>
    </section>

<?php inkwell_exam_list_scripts(); ?>
<?php include __DIR__ . '/includes/drive_shell_bottom.php'; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>
