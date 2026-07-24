<?php
// Inside/dashboard page — suppress the marketing topbar (see includes/header.php).
$__hideTopbar = true;
require_once __DIR__ . '/../includes/store.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/schools.php';
require_once __DIR__ . '/../includes/students.php';
require_once __DIR__ . '/../includes/billing.php';
require_once __DIR__ . '/../includes/lesson_progress.php';
inkwell_require_admin();

$notice = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';
  $studentId = (int) ($_POST['student_id'] ?? 0);

  if ($action === 'add_note' && $studentId) {
    $result = inkwell_add_student_note($studentId, $_POST['body'] ?? '');
    $notice = $result['ok'] ? 'Note saved.' : $result['error'];
  }

  if ($action === 'delete_note') {
    inkwell_delete_student_note((int) ($_POST['note_id'] ?? 0));
    $notice = 'Note deleted.';
  }
}

$students = inkwell_list_all_students();

$dashNavTitle = 'Admin';
$dashNavActive = 'students';
$dashNavItems = [
  ['key' => 'dashboard', 'group' => 'General', 'href' => '/admin/dashboard.php', 'label' => 'Dashboard', 'icon' => '🏠'],
  ['key' => 'settings', 'group' => 'General', 'href' => '/admin/index.php', 'label' => 'Settings', 'icon' => '⚙️'],
  ['key' => 'exams', 'group' => 'Academics', 'href' => '/admin/exams.php', 'label' => 'Exams', 'icon' => '🗂'],
  ['key' => 'teachers', 'group' => 'People', 'href' => '/admin/teachers.php', 'label' => 'Teachers', 'icon' => '🧑‍🏫', 'count' => count(inkwell_list_teachers())],
  ['key' => 'deans', 'group' => 'People', 'href' => '/admin/deans.php', 'label' => 'Deans', 'icon' => '🎓', 'count' => count(inkwell_list_deans())],
  ['key' => 'registrars', 'group' => 'People', 'href' => '/admin/registrars.php', 'label' => 'Registrars', 'icon' => '🗂️', 'count' => count(inkwell_list_registrars())],
  ['key' => 'admins', 'group' => 'People', 'href' => '/admin/admins.php', 'label' => 'Admins', 'icon' => '🛡️', 'count' => count(inkwell_list_admins())],
  ['key' => 'students', 'group' => 'People', 'href' => '/admin/students.php', 'label' => 'Students', 'icon' => '🧑‍🎓', 'count' => count($students)],
  ['key' => 'schools', 'group' => 'Schools & Recognition', 'href' => '/admin/schools.php', 'label' => 'Schools', 'icon' => '🏫', 'count' => count(inkwell_list_schools_with_stats())],
  ['key' => 'certificates', 'group' => 'Schools & Recognition', 'href' => '/admin/certificates.php', 'label' => 'Certificates', 'icon' => '📜'],
  ['key' => 'top-learners', 'group' => 'Schools & Recognition', 'href' => '/admin/top-learners.php', 'label' => 'Top learners', 'icon' => '⭐', 'count' => count(inkwell_top_learner_ids())],
  ['key' => 'lesson-progress', 'group' => 'Schools & Recognition', 'href' => '/admin/lesson-progress.php', 'label' => 'Lesson progress', 'icon' => '📈', 'count' => count(inkwell_lesson_progress_overview())],
  ['key' => 'billing-dashboard', 'group' => 'Billing', 'href' => '/admin/billing-dashboard.php', 'label' => 'Dashboard', 'icon' => '📊'],
  ['key' => 'pricing', 'group' => 'Billing', 'href' => '/admin/pricing.php', 'label' => 'Pricing', 'icon' => '💳', 'count' => count(inkwell_list_plans())],
  ['key' => 'payment-methods', 'group' => 'Billing', 'href' => '/admin/payment-methods.php', 'label' => 'Payment methods', 'icon' => '🏦'],
  ['key' => 'payments', 'group' => 'Billing', 'href' => '/admin/payments.php', 'label' => 'Payments', 'icon' => '🧾', 'count' => inkwell_count_pending_payment_submissions()],
];

$pageTitle = 'Admin · Students';
include __DIR__ . '/../includes/header.php';
?>
<div class="dash-shell">
  <?php include __DIR__ . '/../includes/dash_nav.php'; ?>
  <main class="admin-main">
    <div class="admin-header-row">
      <h1>Student accounts</h1>
      <a class="btn" href="/admin/logout.php">Log out</a>
    </div>

    <?php if ($notice): ?><div class="exam-result pass"><?php echo htmlspecialchars($notice); ?></div><?php endif; ?>
    <?php if ($error): ?><div class="exam-result fail"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

    <section class="admin-card">
      <h2>Students (<?php echo count($students); ?>)</h2>
      <p class="admin-sub">Every student account across all schools. Keep a running log of notes on any student — visible only here in the admin panel.</p>
      <?php if (empty($students)): ?>
        <p class="admin-sub">No student accounts have registered yet.</p>
      <?php else: ?>
        <div class="search-filter">
          <input type="search" class="search-filter-input" data-filter-target="#adminStudentsTable" placeholder="Search by name, email, or school...">
        </div>
        <div class="admin-table-wrap">
          <table class="admin-table" id="adminStudentsTable" data-paginate="20">
            <thead><tr><th>Name</th><th>Email</th><th>School</th><th>Course</th><th>Registered</th><th>Notes</th></tr></thead>
            <tbody>
              <?php foreach ($students as $st): ?>
                <tr data-filter-row>
                  <td>
                    <div style="display:flex; align-items:center; gap:10px;">
                      <?php if (!empty($st['avatar'])): ?>
                        <img class="student-avatar-img" src="/assets/uploads/<?php echo htmlspecialchars($st['avatar']); ?>" alt="" style="width:32px;height:32px;border-radius:50%;" loading="lazy">
                      <?php else: ?>
                        <span class="student-avatar-placeholder" aria-hidden="true" style="width:32px;height:32px;border-radius:50%;"><?php echo strtoupper(substr($st['name'], 0, 1)); ?></span>
                      <?php endif; ?>
                      <strong><?php echo htmlspecialchars($st['name']); ?></strong>
                    </div>
                  </td>
                  <td><?php echo htmlspecialchars($st['email']); ?></td>
                  <td><?php echo htmlspecialchars($st['school_name'] ?? '—'); ?></td>
                  <td><?php echo htmlspecialchars($st['course'] ?? '—'); ?></td>
                  <td><?php echo htmlspecialchars(date('M j, Y', strtotime($st['created_at']))); ?></td>
                  <td>
                    <button type="button" class="btn" data-notes-trigger data-modal-open="studentNotesModal"
                      data-student-id="<?php echo (int) $st['id']; ?>"
                      data-student-name="<?php echo htmlspecialchars($st['name']); ?>">
                      Notes<?php echo (int) $st['note_count'] ? ' (' . (int) $st['note_count'] . ')' : ''; ?>
                    </button>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </section>
  </main>
</div>

<div class="modal-backdrop" id="studentNotesModal">
  <div class="modal">
    <div class="modal-head">
      <h2>Notes — <span id="notesStudentName"></span></h2>
      <button type="button" data-modal-close aria-label="Close">✕</button>
    </div>
    <div id="notesList" class="student-profile-list" style="margin-bottom:16px;"></div>
    <form method="post" action="/admin/students.php" class="admin-form">
      <input type="hidden" name="action" value="add_note">
      <input type="hidden" name="student_id" id="notesStudentId">
      <label for="notesBody">Add a note</label>
      <textarea id="notesBody" name="body" rows="4" maxlength="4000" placeholder="e.g. Flagged for repeated late submissions, or Reached out about scholarship eligibility..."></textarea>
      <button class="btn primary" type="submit">Save note</button>
    </form>
  </div>
</div>
<script>
(function () {
  const allNotes = <?php
    $notesByStudent = [];
    foreach ($students as $st) {
      if ((int) $st['note_count'] > 0) {
        $notesByStudent[$st['id']] = inkwell_student_notes($st['id']);
      }
    }
    echo json_encode($notesByStudent, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
  ?>;

  function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
  }

  document.addEventListener('click', function (e) {
    const btn = e.target.closest('[data-notes-trigger]');
    if (!btn) return;
    const studentId = btn.getAttribute('data-student-id');
    document.getElementById('notesStudentId').value = studentId;
    document.getElementById('notesStudentName').textContent = btn.getAttribute('data-student-name') || '';

    const notes = allNotes[studentId] || [];
    const list = document.getElementById('notesList');
    if (!notes.length) {
      list.innerHTML = '<p class="admin-sub">No notes yet — add the first one below.</p>';
      return;
    }
    list.innerHTML = notes.map(function (n) {
      return '<div class="student-profile-list-row" style="flex-direction:column; align-items:flex-start; gap:4px;">' +
        '<span style="white-space:pre-wrap;">' + escapeHtml(n.body) + '</span>' +
        '<span class="admin-sub" style="margin:0; font-size:0.75rem;">' + escapeHtml(n.created_at) + '</span>' +
        '</div>';
    }).join('');
  });
})();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
