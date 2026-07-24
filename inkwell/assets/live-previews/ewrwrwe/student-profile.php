<?php
/**
 * Returns a rendered HTML fragment with a student's full profile — used
 * by the clickable avatar/name popup on the dean, teacher, and registrar
 * student rosters. Scoped so a teacher only sees their own enrolled
 * students, and a dean/registrar only sees students who picked their
 * school.
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/students.php';

header('Content-Type: application/json');

$viewer = inkwell_current_user();
if (!$viewer || !in_array($viewer['role'], ['teacher', 'dean', 'registrar'], true)) {
  http_response_code(403);
  echo json_encode(['ok' => false, 'error' => 'Not allowed.']);
  exit;
}

$studentId = (int) ($_GET['id'] ?? 0);
$profile = $studentId ? inkwell_get_student_profile($studentId) : null;

if (!$profile || !inkwell_viewer_can_see_student($viewer, $profile['student'])) {
  http_response_code(404);
  echo json_encode(['ok' => false, 'error' => 'Student not found.']);
  exit;
}

$s = $profile['student'];
$stats = $profile['stats'];

ob_start();
?>
<div class="student-profile-head">
  <?php if (!empty($s['avatar'])): ?>
    <img class="student-avatar-img" src="/assets/uploads/<?php echo htmlspecialchars($s['avatar']); ?>" alt="<?php echo htmlspecialchars($s['name']); ?>" loading="lazy">
  <?php else: ?>
    <span class="student-avatar-placeholder" aria-hidden="true"><?php echo strtoupper(substr($s['name'], 0, 1)); ?></span>
  <?php endif; ?>
  <div>
    <h2><?php echo htmlspecialchars($s['name']); ?></h2>
    <span class="admin-sub"><?php echo htmlspecialchars($s['email']); ?></span>
  </div>
</div>

<div class="stat-row">
  <div class="stat-pill"><strong><?php echo (int) $stats['subject_count']; ?></strong><span>Classes</span></div>
  <div class="stat-pill"><strong><?php echo (int) $stats['attempt_count']; ?></strong><span>Attempts</span></div>
  <div class="stat-pill"><strong><?php echo (int) $stats['passed_count']; ?></strong><span>Passed</span></div>
  <div class="stat-pill"><strong><?php echo (int) $stats['certificate_count']; ?></strong><span>Certificates</span></div>
</div>

<div class="student-profile-section">
  <h3>Details</h3>
  <div class="student-profile-list">
    <div class="student-profile-list-row"><span>Student ID</span><strong><?php echo htmlspecialchars($s['id_number'] ?: '—'); ?></strong></div>
    <div class="student-profile-list-row"><span>Course</span><strong><?php echo htmlspecialchars($s['course'] ?: '—'); ?></strong></div>
    <div class="student-profile-list-row"><span>School</span><strong><?php echo htmlspecialchars($profile['school']['name'] ?? '—'); ?></strong></div>
    <div class="student-profile-list-row"><span>Registered</span><strong><?php echo htmlspecialchars(date('M j, Y', strtotime($s['created_at']))); ?></strong></div>
  </div>
</div>

<?php if (!empty($profile['subjects'])): ?>
<div class="student-profile-section">
  <h3>Classes joined</h3>
  <div class="student-profile-list">
    <?php foreach ($profile['subjects'] as $sub): ?>
      <div class="student-profile-list-row"><span><?php echo htmlspecialchars($sub['title']); ?></span><strong><?php echo htmlspecialchars($sub['teacher_name']); ?></strong></div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<?php if (!empty($profile['certificates'])): ?>
<div class="student-profile-section">
  <h3>Certificates</h3>
  <div class="student-profile-list">
    <?php foreach ($profile['certificates'] as $c): ?>
      <div class="student-profile-list-row"><span><?php echo htmlspecialchars($c['label']); ?></span><strong><?php echo (int) $c['percent']; ?>%</strong></div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>
<?php
$html = ob_get_clean();

echo json_encode(['ok' => true, 'html' => $html]);
