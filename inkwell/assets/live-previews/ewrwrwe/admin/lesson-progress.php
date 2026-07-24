<?php
// Inside/dashboard page — suppress the marketing topbar (see includes/header.php).
$__hideTopbar = true;
require_once __DIR__ . '/../data/lessons.php';
require_once __DIR__ . '/../includes/store.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/schools.php';
require_once __DIR__ . '/../includes/students.php';
require_once __DIR__ . '/../includes/billing.php';
require_once __DIR__ . '/../includes/lesson_progress.php';
inkwell_require_admin();

$overview = inkwell_lesson_progress_overview();
$totalLessons = inkwell_total_lesson_count();

// Lesson titles keyed by "cat/slug", for the per-user drill-down list.
$lessonTitles = [];
foreach (inkwell_categories() as $catKey => $cat) {
  foreach ($cat['lessons'] as $slug => $l) {
    $lessonTitles[$catKey . '/' . $slug] = $cat['label'] . ' — ' . ($l['title'] ?? $slug);
  }
}

// Per-user detail, only built for users who show up in the overview (keeps
// this cheap even with a large user base).
$detailByUser = [];
foreach ($overview as $row) {
  $detailByUser[$row['id']] = inkwell_user_lesson_progress($row['id']);
}

$dashNavTitle = 'Admin';
$dashNavActive = 'lesson-progress';
$dashNavItems = [
  ['key' => 'dashboard', 'group' => 'General', 'href' => '/admin/dashboard.php', 'label' => 'Dashboard', 'icon' => '🏠'],
  ['key' => 'settings', 'group' => 'General', 'href' => '/admin/index.php', 'label' => 'Settings', 'icon' => '⚙️'],
  ['key' => 'exams', 'group' => 'Academics', 'href' => '/admin/exams.php', 'label' => 'Exams', 'icon' => '🗂'],
  ['key' => 'lessons', 'group' => 'Academics', 'href' => '/admin/lessons.php', 'label' => 'Lessons', 'icon' => '📘'],
  ['key' => 'lesson-progress', 'group' => 'Academics', 'href' => '/admin/lesson-progress.php', 'label' => 'Lesson progress', 'icon' => '📈', 'count' => count($overview)],
  ['key' => 'teachers', 'group' => 'People', 'href' => '/admin/teachers.php', 'label' => 'Teachers', 'icon' => '🧑‍🏫', 'count' => count(inkwell_list_teachers())],
  ['key' => 'deans', 'group' => 'People', 'href' => '/admin/deans.php', 'label' => 'Deans', 'icon' => '🎓', 'count' => count(inkwell_list_deans())],
  ['key' => 'registrars', 'group' => 'People', 'href' => '/admin/registrars.php', 'label' => 'Registrars', 'icon' => '🗂️', 'count' => count(inkwell_list_registrars())],
  ['key' => 'admins', 'group' => 'People', 'href' => '/admin/admins.php', 'label' => 'Admins', 'icon' => '🛡️', 'count' => count(inkwell_list_admins())],
  ['key' => 'students', 'group' => 'People', 'href' => '/admin/students.php', 'label' => 'Students', 'icon' => '🧑‍🎓', 'count' => count(inkwell_list_all_students())],
  ['key' => 'schools', 'group' => 'Schools & Recognition', 'href' => '/admin/schools.php', 'label' => 'Schools', 'icon' => '🏫', 'count' => count(inkwell_list_schools_with_stats())],
  ['key' => 'certificates', 'group' => 'Schools & Recognition', 'href' => '/admin/certificates.php', 'label' => 'Certificates', 'icon' => '📜'],
  ['key' => 'top-learners', 'group' => 'Schools & Recognition', 'href' => '/admin/top-learners.php', 'label' => 'Top learners', 'icon' => '⭐', 'count' => count(inkwell_top_learner_ids())],
  ['key' => 'billing-dashboard', 'group' => 'Billing', 'href' => '/admin/billing-dashboard.php', 'label' => 'Dashboard', 'icon' => '📊'],
  ['key' => 'pricing', 'group' => 'Billing', 'href' => '/admin/pricing.php', 'label' => 'Pricing', 'icon' => '💳', 'count' => count(inkwell_list_plans())],
  ['key' => 'payment-methods', 'group' => 'Billing', 'href' => '/admin/payment-methods.php', 'label' => 'Payment methods', 'icon' => '🏦'],
  ['key' => 'payments', 'group' => 'Billing', 'href' => '/admin/payments.php', 'label' => 'Payments', 'icon' => '🧾', 'count' => inkwell_count_pending_payment_submissions()],
];

$pageTitle = 'Admin · Lesson progress';
include __DIR__ . '/../includes/header.php';
?>
<div class="dash-shell">
  <?php include __DIR__ . '/../includes/dash_nav.php'; ?>
  <main class="admin-main">
    <div class="admin-header-row">
      <h1>Lesson progress</h1>
      <a class="btn" href="/admin/logout.php">Log out</a>
    </div>
    <p class="admin-sub">Every user who has opened a lesson, across all roles and schools — <?php echo (int) $totalLessons; ?> lessons total across every track. Click a row to see exactly which lessons they've opened.</p>

    <section class="admin-card glass-card">
      <h2>Users (<?php echo count($overview); ?>)</h2>
      <?php if (empty($overview)): ?>
        <p class="admin-sub">No one has opened a lesson yet.</p>
      <?php else: ?>
        <div class="search-filter">
          <input type="search" class="search-filter-input" data-filter-target="#lessonProgressTable" placeholder="Search by name or email...">
        </div>
        <div class="admin-table-wrap">
          <table class="admin-table" id="lessonProgressTable" data-paginate="20">
            <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Lessons viewed</th><th>Last viewed</th><th></th></tr></thead>
            <tbody>
              <?php foreach ($overview as $row): ?>
                <tr data-filter-row>
                  <td><strong><?php echo htmlspecialchars($row['name']); ?></strong></td>
                  <td><?php echo htmlspecialchars($row['email']); ?></td>
                  <td><?php echo htmlspecialchars(ucfirst($row['role'])); ?></td>
                  <td><?php echo (int) $row['lessons_viewed']; ?> / <?php echo (int) $totalLessons; ?></td>
                  <td><?php echo $row['last_viewed_at'] ? htmlspecialchars(date('M j, Y g:ia', strtotime($row['last_viewed_at']))) : '—'; ?></td>
                  <td>
                    <button type="button" class="btn" data-progress-trigger data-modal-open="lessonProgressModal"
                      data-user-id="<?php echo (int) $row['id']; ?>"
                      data-user-name="<?php echo htmlspecialchars($row['name']); ?>">View</button>
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

<div class="modal-backdrop" id="lessonProgressModal">
  <div class="modal">
    <div class="modal-head">
      <h2>Lessons viewed — <span id="progressUserName"></span></h2>
      <button type="button" data-modal-close aria-label="Close">✕</button>
    </div>
    <div id="progressList" class="student-profile-list"></div>
  </div>
</div>
<script>
(function () {
  const detailByUser = <?php
    $jsDetail = [];
    foreach ($detailByUser as $uid => $rows) {
      $jsDetail[$uid] = array_map(function ($r) use ($lessonTitles) {
        $key = $r['cat'] . '/' . $r['slug'];
        return [
          'title' => $lessonTitles[$key] ?? ($r['cat'] . ' / ' . $r['slug']),
          'when' => $r['last_viewed_at'],
        ];
      }, $rows);
    }
    echo json_encode($jsDetail, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
  ?>;

  function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
  }

  document.addEventListener('click', function (e) {
    const btn = e.target.closest('[data-progress-trigger]');
    if (!btn) return;
    const userId = btn.getAttribute('data-user-id');
    document.getElementById('progressUserName').textContent = btn.getAttribute('data-user-name') || '';

    const rows = detailByUser[userId] || [];
    const list = document.getElementById('progressList');
    if (!rows.length) {
      list.innerHTML = '<p class="admin-sub">No lessons recorded.</p>';
      return;
    }
    list.innerHTML = rows.map(function (r) {
      return '<div class="student-profile-list-row">' +
        '<span>' + escapeHtml(r.title) + '</span>' +
        '<span class="admin-sub" style="margin:0; font-size:0.75rem;">' + escapeHtml(r.when) + '</span>' +
        '</div>';
    }).join('');
  });
})();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
