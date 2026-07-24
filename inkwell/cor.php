<?php
// Inside/dashboard page — suppress the marketing topbar (see includes/header.php).
$__hideTopbar = true;
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/exams_db.php';
require_once __DIR__ . '/includes/schools.php';

$user = inkwell_require_role('student');

$subjects = inkwell_student_enrolled_subjects($user['id']);
$school = !empty($user['school_id']) ? inkwell_get_school($user['school_id']) : null;

// Group subjects by term + academic year so a student with multiple
// enrollment batches gets a clearly separated COR per term.
$groups = [];
foreach ($subjects as $s) {
  $term = $s['term'] ?: 'No term set';
  $year = $s['academic_year'] ?: '';
  $key = $term . '|' . $year;
  if (!isset($groups[$key])) {
    $groups[$key] = ['term' => $term, 'year' => $year, 'subjects' => [], 'total_units' => 0];
  }
  $groups[$key]['subjects'][] = $s;
  $groups[$key]['total_units'] += (int) ($s['units'] ?? 3);
}
// Most recently enrolled batch first (subjects already ordered by enrolled_at DESC).
$groups = array_values($groups);

$corNumber = 'COR-' . str_pad($user['id'], 5, '0', STR_PAD_LEFT) . '-' . date('Ymd');
$pageTitle = 'Certificate of Registration';
include __DIR__ . '/includes/header.php';
$driveActive = 'enroll';
$driveCrumbs = [['label' => 'Home', 'href' => '/index.php'], ['label' => 'Enrollment Portal', 'href' => '/enroll.php'], ['label' => 'Certificate of Registration']];
$driveHideCta = true;
include __DIR__ . '/includes/drive_shell_top.php';
?>
  <div class="cert-toolbar no-print">
    <a class="btn" href="/enroll.php">← Back to Enrollment Portal</a>
    <button class="btn primary" onclick="window.print()" type="button">🖨 Print / Save as PDF</button>
  </div>

  <div class="cert-sheet cor-sheet">
    <?php if (empty($subjects)): ?>
      <div class="cor-empty">
        <p class="admin-sub">You don't have any approved subjects yet, so there's nothing to put on a COR. Head to the <a href="/enroll.php">Enrollment Portal</a> and get approved into a class first.</p>
      </div>
    <?php else: ?>
      <?php foreach ($groups as $i => $group): ?>
        <div class="cor-doc<?php echo $i > 0 ? ' cor-doc-break' : ''; ?>">
          <div class="cor-letterhead">
            <div class="cor-letterhead-logo">
              <?php if ($school && !empty($school['logo'])): ?>
                <img src="/assets/uploads/<?php echo htmlspecialchars($school['logo']); ?>" alt="School logo" loading="lazy">
              <?php else: ?>
                <span class="cert-logo-mark" aria-hidden="true"><span class="nib-dot"></span></span>
              <?php endif; ?>
            </div>
            <div class="cor-letterhead-text">
              <h1><?php echo htmlspecialchars($school['name'] ?? 'Inkwell'); ?></h1>
              <p>Certificate of Registration</p>
            </div>
          </div>

          <div class="cor-meta-grid">
            <div><span>Student Name</span><strong><?php echo htmlspecialchars($user['name']); ?></strong></div>
            <div><span>Student ID</span><strong><?php echo htmlspecialchars($user['id_number'] ?: '—'); ?></strong></div>
            <div><span>Course</span><strong><?php echo htmlspecialchars($user['course'] ?: '—'); ?></strong></div>
            <div><span>Term</span><strong><?php echo htmlspecialchars($group['term']); ?></strong></div>
            <div><span>Academic Year</span><strong><?php echo htmlspecialchars($group['year'] ?: '—'); ?></strong></div>
            <div><span>COR No.</span><strong><?php echo htmlspecialchars($corNumber . ($i > 0 ? '-' . ($i + 1) : '')); ?></strong></div>
          </div>

          <table class="admin-table cor-table">
            <thead>
              <tr><th>Subject Code</th><th>Subject Description</th><th>Instructor</th><th class="cor-col-units">Units</th></tr>
            </thead>
            <tbody>
              <?php foreach ($group['subjects'] as $s): ?>
                <tr>
                  <td><?php echo htmlspecialchars($s['code'] ?? '' ?: '—'); ?></td>
                  <td><?php echo htmlspecialchars($s['title']); ?></td>
                  <td><?php echo htmlspecialchars($s['teacher_name']); ?></td>
                  <td class="cor-col-units"><?php echo (int) ($s['units'] ?? 3); ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
            <tfoot>
              <tr>
                <td colspan="3" class="cor-totalcell">Total # of Units</td>
                <td class="cor-col-units cor-totalcell"><?php echo (int) $group['total_units']; ?></td>
              </tr>
            </tfoot>
          </table>

          <p class="cor-totalline">Total subjects enrolled: <strong><?php echo count($group['subjects']); ?></strong></p>

          <div class="cor-footer-grid">
            <div class="cor-sign">
              <div class="cert-sign-line"></div>
              <div class="cert-sign-name">Registrar</div>
              <div class="cert-sign-title">Office of the Registrar</div>
            </div>
            <div class="cor-issued">
              <div class="cert-meta-row"><span>Date Printed</span><strong><?php echo date('F j, Y'); ?></strong></div>
              <div class="cert-meta-row"><span>Verified via</span><strong>Inkwell</strong></div>
            </div>
          </div>

          <div class="cert-copyright-footer">This is a system-generated Certificate of Registration and reflects the student's approved enrollment records at the time of printing.</div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
<?php include __DIR__ . '/includes/drive_shell_bottom.php'; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>
