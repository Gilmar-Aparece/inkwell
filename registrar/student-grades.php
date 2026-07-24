<?php
// Inside/dashboard page — suppress the marketing topbar (see includes/header.php).
$__hideTopbar = true;
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/schools.php';
require_once __DIR__ . '/../includes/students.php';
require_once __DIR__ . '/../includes/billing.php';
require_once __DIR__ . '/../includes/exams_db.php';
require_once __DIR__ . '/../includes/class_record.php';

$user = inkwell_require_role('registrar');
$school = $user['school_id'] ? inkwell_get_school($user['school_id']) : null;

if ($user['status'] !== 'active' || !$school || inkwell_registrar_dashboard_locked($user)) {
  header('Location: /registrar/dashboard.php');
  exit;
}

$studentId = (int) ($_GET['student_id'] ?? 0);
$student = $studentId ? inkwell_get_user($studentId) : null;

// Only ever show grades for a student who actually belongs to this
// registrar's school — same trust boundary as inkwell_school_students().
if (!$student || $student['role'] !== 'student' || (int) ($student['school_id'] ?? 0) !== (int) $school['id']) {
  header('Location: /registrar/students.php');
  exit;
}

$subjects = inkwell_student_enrolled_subjects($student['id']);
$termLabels = inkwell_class_record_terms();

$students = inkwell_school_students($school['id']);
$dashNavTitle = 'Registrar';
$dashNavActive = 'students';
$dashNavItems = [
  ['key' => 'dashboard', 'group' => 'General', 'href' => '/registrar/overview.php', 'label' => 'Dashboard', 'icon' => '🏠'],
  ['key' => 'subjects', 'group' => 'Academics', 'href' => '/registrar/dashboard.php', 'label' => 'Subjects', 'icon' => '📚'],
  ['key' => 'departments', 'group' => 'Academics', 'href' => '/registrar/dashboard.php#departments', 'label' => 'Departments', 'icon' => '🏷️'],
  ['key' => 'curriculum', 'group' => 'Academics', 'href' => '/registrar/curriculum.php', 'label' => 'Curriculum', 'icon' => '🧭'],
  ['key' => 'teachers', 'group' => 'People', 'href' => '/registrar/teachers.php', 'label' => 'Teachers', 'icon' => '🧑‍🏫', 'count' => count(inkwell_list_school_teachers($school['id']))],
  ['key' => 'deans', 'group' => 'People', 'href' => '/registrar/deans.php', 'label' => 'Deans', 'icon' => '🏫', 'count' => count(inkwell_list_school_deans($school['id']))],
  ['key' => 'students', 'group' => 'People', 'href' => '/registrar/students.php', 'label' => 'Students', 'icon' => '🎓', 'count' => count($students)],
  ['key' => 'reports', 'group' => 'Reports', 'href' => '/registrar/reports.php', 'label' => 'Reports', 'icon' => '📊'],
];

$pageTitle = 'Registrar · Student Grades';
include __DIR__ . '/../includes/header.php';
?>
<div class="dash-shell">
  <?php include __DIR__ . '/../includes/dash_nav.php'; ?>
  <main class="admin-main">
    <div class="admin-header-row">
      <div>
        <h1><?php echo htmlspecialchars($student['name']); ?> — Grades</h1>
        <p class="admin-sub"><?php echo htmlspecialchars($student['email']); ?><?php echo !empty($student['id_number']) ? ' · ID ' . htmlspecialchars($student['id_number']) : ''; ?><?php echo !empty($student['course']) ? ' · ' . htmlspecialchars($student['course']) : ''; ?></p>
      </div>
      <a class="btn" href="/registrar/students.php">← Back to Students</a>
    </div>

    <?php if (empty($subjects)): ?>
      <section class="admin-card">
        <p class="admin-sub">This student isn't approved into any classes yet.</p>
      </section>
    <?php else: ?>
      <?php foreach ($subjects as $s): ?>
        <?php $summary = inkwell_erecord_student_subject_summary((int) $s['id'], $student); ?>
        <section class="admin-card" style="margin-bottom:16px;">
          <h2 style="margin:0;"><?php echo htmlspecialchars($s['title']); ?></h2>
          <p class="admin-sub" style="margin:2px 0 12px;">with <?php echo htmlspecialchars($s['teacher_name']); ?><?php echo !empty($s['term']) ? ' · ' . htmlspecialchars($s['term']) : ''; ?><?php echo !empty($s['academic_year']) ? ' ' . htmlspecialchars($s['academic_year']) : ''; ?></p>
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
  </main>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
