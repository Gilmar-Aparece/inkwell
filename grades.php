<?php
// Inside/dashboard page — suppress the marketing topbar (see includes/header.php).
$__hideTopbar = true;
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/exams_db.php';
require_once __DIR__ . '/includes/class_record.php';

$user = inkwell_require_role('student');

$subjects = inkwell_student_enrolled_subjects($user['id']);
$termLabels = inkwell_class_record_terms(); // ['prelim'=>'Prelim','midterm'=>'Midterm','final'=>'Final']

$pageTitle = 'My Grades';
include __DIR__ . '/includes/header.php';
$driveActive = 'grades';
$driveCrumbs = [['label' => 'Home', 'href' => '/index.php'], ['label' => 'My Grades']];
$driveTitle = 'My Grades';
$driveSubtitle = 'Prelim, Midterm, and Final results for every class you\'re enrolled in, as recorded by each teacher\'s E-Class Record.';
include __DIR__ . '/includes/drive_shell_top.php';
?>

  <?php if (empty($subjects)): ?>
    <section class="admin-card glass-card">
      <h2>No classes yet</h2>
      <p class="admin-sub">Once you're approved into a class, its grades will show up here as your teacher records them. Head to the <a href="/enroll.php">Enrollment Portal</a> to get started.</p>
    </section>
  <?php else: ?>
    <section class="admin-card glass-card" style="margin-bottom:16px;">
      <p class="admin-sub" style="margin:0;">Want your official enrollment record instead? <a href="/cor.php">📄 View / print your Certificate of Registration</a>.</p>
    </section>

    <?php foreach ($subjects as $s): ?>
      <?php $summary = inkwell_erecord_student_subject_summary((int) $s['id'], $user); ?>
      <section class="admin-card glass-card" style="margin-bottom:16px;">
        <div class="admin-header-row" style="margin-bottom:6px;">
          <div>
            <h2 style="margin:0;"><?php echo htmlspecialchars($s['title']); ?></h2>
            <p class="admin-sub" style="margin:2px 0 0;">with <?php echo htmlspecialchars($s['teacher_name']); ?><?php echo !empty($s['term']) ? ' · ' . htmlspecialchars($s['term']) : ''; ?><?php echo !empty($s['academic_year']) ? ' ' . htmlspecialchars($s['academic_year']) : ''; ?></p>
          </div>
        </div>
        <div class="admin-table-wrap">
          <table class="admin-table">
            <thead><tr><th>Term</th><th>Total</th><th>FR</th><th>Final Grade</th><th>Remarks</th></tr></thead>
            <tbody>
              <?php foreach ($termLabels as $key => $label): ?>
                <?php $t = $summary[$key]; ?>
                <tr>
                  <td><?php echo htmlspecialchars($label); ?></td>
                  <?php if (!$t['recorded']): ?>
                    <td colspan="4"><span class="admin-sub">Not recorded yet</span></td>
                  <?php else: ?>
                    <td><?php echo number_format($t['total'], 2); ?> / <?php echo number_format($t['max_total'], 2); ?></td>
                    <td><?php echo number_format($t['fr'], 2); ?></td>
                    <td><?php echo number_format($t['final_grade'], 2); ?></td>
                    <td><?php echo $t['remarks'] !== '' ? htmlspecialchars($t['remarks']) : '<span class="admin-sub">—</span>'; ?></td>
                  <?php endif; ?>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>
    <?php endforeach; ?>
  <?php endif; ?>

<?php include __DIR__ . '/includes/drive_shell_bottom.php'; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>
