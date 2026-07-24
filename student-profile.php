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
require_once __DIR__ . '/includes/student_profile_fields.php';

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

<?php if ((int) $s['id'] !== (int) $viewer['id']): ?>
  <a class="btn primary" href="/messages.php?with=<?php echo (int) $s['id']; ?>" style="display:block;text-align:center;margin:0 0 16px;">💬 Message</a>
<?php endif; ?>

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

<?php if (in_array($viewer['role'], ['registrar', 'dean'], true) && inkwell_ensure_student_profiles_table()):
  $ext = inkwell_get_student_profile_fields($s['id']);
  $hasAny = $ext && count(array_filter($ext, function ($v, $k) { return $k !== 'user_id' && $v; }, ARRAY_FILTER_USE_BOTH)) > 0;
  if ($hasAny):
?>
<div class="student-profile-section">
  <h3>Personal &amp; family details</h3>
  <div class="student-profile-list">
    <?php if (!empty($ext['birth_date'])): ?><div class="student-profile-list-row"><span>Birth date</span><strong><?php echo htmlspecialchars(date('M j, Y', strtotime($ext['birth_date']))); ?></strong></div><?php endif; ?>
    <?php if (!empty($ext['sex'])): ?><div class="student-profile-list-row"><span>Sex</span><strong><?php echo htmlspecialchars($ext['sex']); ?></strong></div><?php endif; ?>
    <?php if (!empty($ext['civil_status'])): ?><div class="student-profile-list-row"><span>Civil status</span><strong><?php echo htmlspecialchars($ext['civil_status']); ?></strong></div><?php endif; ?>
    <?php if (!empty($ext['nationality'])): ?><div class="student-profile-list-row"><span>Nationality</span><strong><?php echo htmlspecialchars($ext['nationality']); ?></strong></div><?php endif; ?>
    <?php if (!empty($ext['lrn_number'])): ?><div class="student-profile-list-row"><span>LRN</span><strong><?php echo htmlspecialchars($ext['lrn_number']); ?></strong></div><?php endif; ?>
    <?php
      $addrParts = array_filter([$ext['street'] ?? '', $ext['barangay'] ?? '', $ext['city'] ?? '', $ext['province'] ?? '']);
      if (!empty($addrParts)):
    ?><div class="student-profile-list-row"><span>Address</span><strong><?php echo htmlspecialchars(implode(', ', $addrParts)); ?></strong></div><?php endif; ?>
    <?php if (!empty($ext['contact_number'])): ?><div class="student-profile-list-row"><span>Contact</span><strong><?php echo htmlspecialchars($ext['contact_number']); ?></strong></div><?php endif; ?>
    <?php if (!empty($ext['guardian_name'])): ?><div class="student-profile-list-row"><span>Guardian</span><strong><?php echo htmlspecialchars($ext['guardian_name'] . (!empty($ext['guardian_relationship']) ? ' (' . $ext['guardian_relationship'] . ')' : '')); ?></strong></div><?php endif; ?>
    <?php if (!empty($ext['guardian_contact'])): ?><div class="student-profile-list-row"><span>Guardian contact</span><strong><?php echo htmlspecialchars($ext['guardian_contact']); ?></strong></div><?php endif; ?>
    <?php if (!empty($ext['elementary_school'])): ?><div class="student-profile-list-row"><span>Elementary</span><strong><?php echo htmlspecialchars($ext['elementary_school'] . (!empty($ext['elementary_years']) ? ' (' . $ext['elementary_years'] . ')' : '')); ?></strong></div><?php endif; ?>
    <?php if (!empty($ext['high_school'])): ?><div class="student-profile-list-row"><span>High school</span><strong><?php echo htmlspecialchars($ext['high_school'] . (!empty($ext['high_school_years']) ? ' (' . $ext['high_school_years'] . ')' : '')); ?></strong></div><?php endif; ?>
    <?php if (!empty($ext['senior_high_school'])): ?><div class="student-profile-list-row"><span>Senior high</span><strong><?php echo htmlspecialchars($ext['senior_high_school'] . (!empty($ext['senior_high_years']) ? ' (' . $ext['senior_high_years'] . ')' : '')); ?></strong></div><?php endif; ?>
    <?php if (!empty($ext['degree_completed'])): ?><div class="student-profile-list-row"><span>Prior degree</span><strong><?php echo htmlspecialchars($ext['degree_completed']); ?></strong></div><?php endif; ?>
  </div>
</div>
<?php endif; endif; ?>

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
