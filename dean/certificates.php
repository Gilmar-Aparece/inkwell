<?php
// Inside/dashboard page — suppress the marketing topbar (see includes/header.php).
$__hideTopbar = true;
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/schools.php';
require_once __DIR__ . '/../includes/exams_db.php';

$user = inkwell_require_role('dean');
$school = inkwell_get_school_by_dean($user['id']);
if ($user['status'] !== 'active' || !$school) {
  header('Location: /dean/dashboard.php');
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

    $stmt = inkwell_db()->prepare('SELECT name FROM users WHERE id = ? AND role = ? AND school_id = ?');
    $stmt->execute([$studentId, 'student', $school['id']]);
    $studentName = $stmt->fetchColumn();

    if (!$label) {
      $error = 'Give the certificate a title.';
    } elseif (!$studentName) {
      $error = 'That student is not part of your school.';
    } else {
      inkwell_db_add_manual_certificate($studentId, $studentName, $label, $user['id'], $user['name'], 'dean', $message, $color, $school['id'], $design);
      $notice = 'Certificate issued to ' . htmlspecialchars($studentName) . '.';
    }
  }
}

$students = inkwell_school_students($school['id']);
$issued = inkwell_certificates_for_school($school['id']);
$certFormAction = '/dean/certificates.php';
$certDefaultSigner = inkwell_get_cert_signer(['issuer_school_id' => $school['id']]);
$certSignerNote = 'Leave blank to use your school\'s signer (currently "' . $certDefaultSigner['name'] . '", set on the Certificate signer page). Only fill these in to use a different signer for just this certificate.';

$dashNavTitle = 'Dean';
$dashNavActive = 'certificates';
$dashNavItems = [
  ['key' => 'dashboard', 'group' => 'General', 'href' => '/dean/overview.php', 'label' => 'Dashboard', 'icon' => '🏠'],
  ['key' => 'school', 'group' => 'School', 'href' => '/dean/dashboard.php', 'label' => 'School profile', 'icon' => '🏫'],
  ['key' => 'signer', 'group' => 'Certificates', 'href' => '/dean/signer.php', 'label' => 'Certificate signer', 'icon' => '✍️'],
  ['key' => 'certificates', 'group' => 'Certificates', 'href' => '/dean/certificates.php', 'label' => 'Issue certificate', 'icon' => '📜'],
  ['key' => 'teachers', 'group' => 'People', 'href' => '/dean/teachers.php', 'label' => 'Teachers', 'icon' => '🧑‍🏫', 'count' => count(inkwell_list_school_teachers($school['id'], false, $user['department_id'] ?? null))],
  ['key' => 'students', 'group' => 'People', 'href' => '/dean/students.php', 'label' => 'Students', 'icon' => '🎓', 'count' => count(inkwell_school_students($school['id']))],
  ['key' => 'events', 'group' => 'Activity', 'href' => '/dean/events.php', 'label' => 'Events', 'icon' => '📣'],
];

$pageTitle = 'Dean · Certificates';
include __DIR__ . '/../includes/header.php';
?>
<div class="dash-shell">
  <?php include __DIR__ . '/../includes/dash_nav.php'; ?>
  <main class="admin-main">
    <div class="admin-header-row">
      <h1>Issue a certificate</h1>
      <a class="btn" href="/logout.php">Log out</a>
    </div>

    <?php if ($notice): ?><div class="exam-result pass"><?php echo $notice; ?></div><?php endif; ?>
    <?php if ($error): ?><div class="exam-result fail"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

    <section class="admin-card glass-card">
      <h2>New certificate</h2>
      <p class="admin-sub">Award a certificate to any student in your school — for a school event, an achievement, anything worth recognizing. It doesn't require an exam. It'll use your <a href="/dean/signer.php">certificate signer</a> settings automatically.</p>
      <?php if (empty($students)): ?>
        <p class="admin-sub">No students in your school yet.</p>
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
          <input type="search" class="search-filter-input" data-filter-target="#deanCertsTable" placeholder="Search by student or title...">
        </div>
        <div class="admin-table-wrap">
          <table class="admin-table" id="deanCertsTable">
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
