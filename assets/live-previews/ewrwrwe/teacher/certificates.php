<?php
// Inside/dashboard page — suppress the marketing topbar (see includes/header.php).
$__hideTopbar = true;
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/students.php';
require_once __DIR__ . '/../includes/exams_db.php';
require_once __DIR__ . '/../includes/events.php';

$user = inkwell_require_role('teacher');
if ($user['status'] !== 'active') {
  header('Location: /teacher/dashboard.php');
  exit;
}

$notice = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';

  if ($action === 'issue_certificate') {
    $studentId = (int) ($_POST['student_id'] ?? 0);
    $label = trim($_POST['label'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $color = trim($_POST['accent_color'] ?? '');
    $design = [
      'template' => $_POST['template'] ?? '',
      'font_choice' => $_POST['font_choice'] ?? '',
      'bg_style' => $_POST['bg_style'] ?? '',
      'title_text' => $_POST['heading_text'] ?? '',
      'seal_label' => $_POST['seal_label'] ?? '',
      'signer_name' => $_POST['signer_name'] ?? '',
      'signer_title' => $_POST['signer_title'] ?? '',
    ];

    if (!$studentId || $label === '') {
      $error = 'Pick a student and give the certificate a title.';
    } elseif (!inkwell_is_teacher_student($user['id'], $studentId)) {
      $error = 'That student is not enrolled in one of your subjects.';
    } else {
      $stmt = inkwell_db()->prepare('SELECT name FROM users WHERE id = ?');
      $stmt->execute([$studentId]);
      $studentName = $stmt->fetchColumn();
      if (!$studentName) {
        $error = 'Student not found.';
      } else {
        $result = inkwell_db_add_manual_certificate($studentId, $studentName, $label, $user['id'], $user['name'], 'teacher', $message, $color, null, $design);
        $notice = 'Certificate issued to ' . htmlspecialchars($studentName) . '.';
      }
    }
  }
}

$students = inkwell_teacher_students($user['id']);
$issued = inkwell_certificates_for_teacher($user['id']);
$certFormAction = '/teacher/certificates.php';
$certDefaultSigner = inkwell_get_cert_signer(['teacher_id' => $user['id']]);
$certSignerNote = 'Leave blank to use your signer from the dashboard settings (currently "' . $certDefaultSigner['name'] . '"). Only fill these in to use a different signer for just this certificate.';

$dashNavTitle = 'Teacher';
$dashNavActive = 'certificates';
$dashNavItems = [
  ['key' => 'dashboard', 'group' => 'General', 'href' => '/teacher/overview.php', 'label' => 'Dashboard', 'icon' => '🏠'],
  ['key' => 'subjects', 'group' => 'Teaching', 'href' => '/teacher/dashboard.php', 'label' => 'Subjects', 'icon' => '🗂'],
  ['key' => 'sections', 'group' => 'Teaching', 'href' => '/teacher/sections.php', 'label' => 'Sections', 'icon' => '👥'],
  ['key' => 'grade', 'group' => 'Teaching', 'href' => '/teacher/grade.php', 'label' => 'Grade', 'icon' => '🖊', 'count' => count(inkwell_teacher_pending_attempts($user['id']))],
  ['key' => 'students', 'group' => 'People', 'href' => '/teacher/students.php', 'label' => 'Students', 'icon' => '🎓', 'count' => count($students)],
  ['key' => 'certificates', 'group' => 'People', 'href' => '/teacher/certificates.php', 'label' => 'Certificates', 'icon' => '📜'],
  ['key' => 'events', 'group' => 'Activity', 'href' => '/teacher/events.php', 'label' => 'Events', 'icon' => '📣', 'count' => count(inkwell_events_by_author($user['id']))],
];

$pageTitle = 'Issue a certificate';
include __DIR__ . '/../includes/header.php';
?>
<div class="dash-shell">
  <?php include __DIR__ . '/../includes/dash_nav.php'; ?>
  <main class="admin-main">
  <div class="admin-header-row">
    <h1>Issue a certificate</h1>
    <div style="display:flex; gap:10px;">
      <a class="btn" href="/teacher/dashboard.php">← Dashboard</a>
    </div>
  </div>

  <?php if ($notice): ?><div class="exam-result pass"><?php echo $notice; ?></div><?php endif; ?>
  <?php if ($error): ?><div class="exam-result fail"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

  <section class="admin-card glass-card">
    <h2>New certificate</h2>
    <p class="admin-sub">Award a certificate to any student enrolled in one of your subjects — for a workshop, a project, a milestone, anything worth recognizing. It doesn't require an exam.</p>
    <?php if (empty($students)): ?>
      <p class="admin-sub">No students yet — once someone joins one of your subjects, they'll show up here.</p>
    <?php else: ?>
      <?php include __DIR__ . '/../includes/cert_editor_form.php'; ?>
    <?php endif; ?>
  </section>

  <section class="admin-card" style="margin-top:20px;">
    <h2>Certificates issued (<?php echo count($issued); ?>)</h2>
    <?php if (empty($issued)): ?>
      <p class="admin-sub">Nothing issued yet.</p>
    <?php else: ?>
      <div class="search-filter">
        <input type="search" class="search-filter-input" data-filter-target="#teacherCertsTable" placeholder="Search by student or title...">
      </div>
      <div class="admin-table-wrap">
        <table class="admin-table" id="teacherCertsTable">
          <thead><tr><th>Student</th><th>Title</th><th>Type</th><th>Date</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($issued as $cert): ?>
              <tr data-filter-row>
                <td><?php echo htmlspecialchars($cert['student_name']); ?></td>
                <td><?php echo htmlspecialchars($cert['label']); ?></td>
                <td><?php echo ($cert['source'] ?? 'exam') === 'manual' ? 'Issued' : 'Exam'; ?></td>
                <td><?php echo htmlspecialchars($cert['issued_at']); ?></td>
                <td><a href="/certificate.php?id=<?php echo urlencode($cert['id']); ?>" target="_blank">View →</a></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>
  </main>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
