<?php
/**
 * Returns a rendered HTML fragment with a teacher's or dean's public
 * profile — used by the clickable faculty-card popup on the "Browse &
 * join classes" / "Your classes" cards on exams.php, and the
 * department-grouped Faculty & Dean rows on school.php / my-school.php.
 * Pass role=dean for a Dean account. Intentionally public: anyone can see
 * which subjects an active teacher teaches (or which department a Dean
 * oversees), same as the pages these popups are opened from.
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/students.php';

header('Content-Type: application/json');

$teacherId = (int) ($_GET['id'] ?? 0);
$role = ($_GET['role'] ?? 'teacher') === 'dean' ? 'dean' : 'teacher';
$profile = $teacherId ? inkwell_get_teacher_profile($teacherId, $role) : null;

if (!$profile) {
  http_response_code(404);
  echo json_encode(['ok' => false, 'error' => ($role === 'dean' ? 'Dean' : 'Teacher') . ' not found.']);
  exit;
}

$t = $profile['teacher'];
$stats = $profile['stats'];
$department = $profile['department'];
$isDean = $profile['role'] === 'dean';

ob_start();
?>
<div class="student-profile-head">
  <?php if (!empty($t['avatar'])): ?>
    <img class="student-avatar-img" src="/assets/uploads/<?php echo htmlspecialchars($t['avatar']); ?>" alt="<?php echo htmlspecialchars($t['name']); ?>" loading="lazy">
  <?php else: ?>
    <span class="student-avatar-placeholder" aria-hidden="true"><?php echo strtoupper(substr($t['name'], 0, 1)); ?></span>
  <?php endif; ?>
  <div>
    <h2><?php echo htmlspecialchars($t['name']); ?></h2>
    <span class="admin-sub">
      <?php echo $isDean ? 'Dean' : 'Teacher'; ?><?php echo $department ? ' · ' . htmlspecialchars($department['code']) : ''; ?><?php echo !empty($profile['school']['name']) ? ' · ' . htmlspecialchars($profile['school']['name']) : ''; ?>
    </span>
  </div>
</div>

<?php if ($isDean): ?>
  <div class="stat-row">
    <div class="stat-pill"><strong><?php echo (int) $stats['teacher_count']; ?></strong><span>Teachers</span></div>
    <div class="stat-pill"><strong><?php echo (int) $stats['subject_count']; ?></strong><span>Subjects</span></div>
    <div class="stat-pill"><strong><?php echo (int) $stats['student_count']; ?></strong><span>Students</span></div>
  </div>

  <div class="student-profile-section">
    <h3>Details</h3>
    <div class="student-profile-list">
      <div class="student-profile-list-row"><span>Department</span><strong><?php echo htmlspecialchars($department ? $department['code'] . ' — ' . $department['name'] : ($t['course'] ?: 'Unassigned')); ?></strong></div>
      <div class="student-profile-list-row"><span>Dean since</span><strong><?php echo htmlspecialchars(date('M j, Y', strtotime($t['created_at']))); ?></strong></div>
    </div>
  </div>

  <div class="student-profile-section">
    <h3>Teachers in this department</h3>
    <?php if (!empty($profile['teachers'])): ?>
      <div class="student-profile-list">
        <?php foreach ($profile['teachers'] as $teach): ?>
          <div class="student-profile-list-row">
            <span><?php echo htmlspecialchars($teach['name']); ?></span>
            <strong><?php echo htmlspecialchars($teach['email']); ?></strong>
          </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <p class="admin-sub">No teachers assigned to this department yet.</p>
    <?php endif; ?>
  </div>
<?php else: ?>
  <div class="stat-row">
    <div class="stat-pill"><strong><?php echo (int) $stats['subject_count']; ?></strong><span>Subjects</span></div>
    <div class="stat-pill"><strong><?php echo (int) $stats['exam_count']; ?></strong><span>Exams</span></div>
    <div class="stat-pill"><strong><?php echo (int) $stats['student_count']; ?></strong><span>Students</span></div>
  </div>

  <div class="student-profile-section">
    <h3>Details</h3>
    <div class="student-profile-list">
      <div class="student-profile-list-row"><span>Department</span><strong><?php echo htmlspecialchars($department ? $department['code'] . ' — ' . $department['name'] : ($t['course'] ?: '—')); ?></strong></div>
      <div class="student-profile-list-row"><span>Teaching since</span><strong><?php echo htmlspecialchars(date('M j, Y', strtotime($t['created_at']))); ?></strong></div>
    </div>
  </div>

  <?php if (!empty($profile['subjects'])): ?>
  <div class="student-profile-section">
    <h3>Subjects taught</h3>
    <div class="student-profile-list">
      <?php foreach ($profile['subjects'] as $sub): ?>
        <div class="student-profile-list-row">
          <span><?php echo htmlspecialchars($sub['title']); ?></span>
          <strong><?php echo (int) $sub['exam_count']; ?> exams · <?php echo (int) $sub['student_count']; ?> students</strong>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php else: ?>
  <div class="student-profile-section">
    <p class="admin-sub">No subjects published yet.</p>
  </div>
  <?php endif; ?>
<?php endif; ?>
<?php
$html = ob_get_clean();

echo json_encode(['ok' => true, 'html' => $html]);
