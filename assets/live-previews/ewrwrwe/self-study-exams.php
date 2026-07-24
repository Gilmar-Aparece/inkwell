<?php
// Inside/dashboard page — suppress the marketing topbar (see includes/header.php).
$__hideTopbar = true;
require_once __DIR__ . '/data/lessons.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/exams_db.php';

$user = inkwell_current_user();
$selfstudyExams = inkwell_selfstudy_exam_categories();

$pageTitle = 'Self-study exams';
include __DIR__ . '/includes/header.php';
$driveActive = 'exams';
$driveActiveSub = 'selfstudy';
$driveCrumbs = [['label' => 'Home', 'href' => '/index.php'], ['label' => 'Exams', 'href' => '/exams.php'], ['label' => 'Self-study exams']];
$driveTitle = 'Self-study exams';
$driveSubtitle = "The standard Inkwell certification exam for each language — no teacher attached. You'll need to be logged in so your certificate is tied to your account.";
include __DIR__ . '/includes/drive_shell_top.php';
?>
    <section class="admin-card glass-card" id="selfstudy">
      <?php inkwell_render_exam_list($selfstudyExams, function ($ex) { return '/exam.php?cat=' . urlencode($ex['language_key']); }); ?>
    </section>

<?php inkwell_exam_list_scripts(); ?>
<?php include __DIR__ . '/includes/drive_shell_bottom.php'; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>
