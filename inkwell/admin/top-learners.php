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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';
  $studentId = (int) ($_POST['student_id'] ?? 0);
  if ($action === 'toggle_top_learner' && $studentId) {
    inkwell_toggle_top_learner($studentId);
    $notice = 'Top learners list updated.';
  }
}

$students = inkwell_list_all_students();
$topIds = inkwell_top_learner_ids();
$topIdsFlipped = array_flip($topIds);
// Keep featured students first for convenience, in the admin's chosen order.
usort($students, function ($a, $b) use ($topIdsFlipped) {
  $aTop = isset($topIdsFlipped[(int) $a['id']]) ? 0 : 1;
  $bTop = isset($topIdsFlipped[(int) $b['id']]) ? 0 : 1;
  return $aTop <=> $bTop;
});

$dashNavTitle = 'Admin';
$dashNavActive = 'top-learners';
$dashNavItems = [
  ['key' => 'dashboard', 'group' => 'General', 'href' => '/admin/dashboard.php', 'label' => 'Dashboard', 'icon' => '🏠'],
  ['key' => 'settings', 'group' => 'General', 'href' => '/admin/index.php', 'label' => 'Settings', 'icon' => '⚙️'],
  ['key' => 'exams', 'group' => 'Academics', 'href' => '/admin/exams.php', 'label' => 'Exams', 'icon' => '🗂'],
  ['key' => 'lessons', 'group' => 'Academics', 'href' => '/admin/lessons.php', 'label' => 'Lessons', 'icon' => '📘'],
  ['key' => 'teachers', 'group' => 'People', 'href' => '/admin/teachers.php', 'label' => 'Teachers', 'icon' => '🧑‍🏫', 'count' => count(inkwell_list_teachers())],
  ['key' => 'deans', 'group' => 'People', 'href' => '/admin/deans.php', 'label' => 'Deans', 'icon' => '🎓', 'count' => count(inkwell_list_deans())],
  ['key' => 'registrars', 'group' => 'People', 'href' => '/admin/registrars.php', 'label' => 'Registrars', 'icon' => '🗂️', 'count' => count(inkwell_list_registrars())],
  ['key' => 'admins', 'group' => 'People', 'href' => '/admin/admins.php', 'label' => 'Admins', 'icon' => '🛡️', 'count' => count(inkwell_list_admins())],
  ['key' => 'students', 'group' => 'People', 'href' => '/admin/students.php', 'label' => 'Students', 'icon' => '🧑‍🎓', 'count' => count($students)],
  ['key' => 'schools', 'group' => 'Schools & Recognition', 'href' => '/admin/schools.php', 'label' => 'Schools', 'icon' => '🏫', 'count' => count(inkwell_list_schools_with_stats())],
  ['key' => 'certificates', 'group' => 'Schools & Recognition', 'href' => '/admin/certificates.php', 'label' => 'Certificates', 'icon' => '📜'],
  ['key' => 'top-learners', 'group' => 'Schools & Recognition', 'href' => '/admin/top-learners.php', 'label' => 'Top learners', 'icon' => '⭐', 'count' => count($topIds)],
  ['key' => 'lesson-progress', 'group' => 'Schools & Recognition', 'href' => '/admin/lesson-progress.php', 'label' => 'Lesson progress', 'icon' => '📈', 'count' => count(inkwell_lesson_progress_overview())],
  ['key' => 'billing-dashboard', 'group' => 'Billing', 'href' => '/admin/billing-dashboard.php', 'label' => 'Dashboard', 'icon' => '📊'],
  ['key' => 'pricing', 'group' => 'Billing', 'href' => '/admin/pricing.php', 'label' => 'Pricing', 'icon' => '💳', 'count' => count(inkwell_list_plans())],
  ['key' => 'payment-methods', 'group' => 'Billing', 'href' => '/admin/payment-methods.php', 'label' => 'Payment methods', 'icon' => '🏦'],
  ['key' => 'payments', 'group' => 'Billing', 'href' => '/admin/payments.php', 'label' => 'Payments', 'icon' => '🧾', 'count' => inkwell_count_pending_payment_submissions()],
];

$pageTitle = 'Admin · Top learners';
include __DIR__ . '/../includes/header.php';
?>
<div class="dash-shell">
  <?php include __DIR__ . '/../includes/dash_nav.php'; ?>
  <main class="admin-main">
    <div class="admin-header-row">
      <h1>Top learners</h1>
      <a class="btn" href="/admin/logout.php">Log out</a>
    </div>

    <?php if ($notice): ?><div class="exam-result pass"><?php echo htmlspecialchars($notice); ?></div><?php endif; ?>

    <section class="admin-card">
      <h2>Curate "Learners online" (<?php echo count($topIds); ?> selected)</h2>
      <p class="admin-sub">Pick which students show up as the "Learners online" avatars on the public lessons page. This is site-wide and independent of school — star any student below.</p>
      <?php if (empty($students)): ?>
        <p class="admin-sub">No student accounts have registered yet.</p>
      <?php else: ?>
        <div class="search-filter">
          <input type="search" class="search-filter-input" data-filter-target="#topLearnersTable" placeholder="Search by name, email, or school...">
        </div>
        <div class="admin-table-wrap">
          <table class="admin-table" id="topLearnersTable">
            <thead><tr><th>Name</th><th>Email</th><th>School</th><th>Course</th><th>Registered</th><th>Top learner</th></tr></thead>
            <tbody>
              <?php foreach ($students as $st): $isTop = isset($topIdsFlipped[(int) $st['id']]); ?>
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
                    <form method="post" action="/admin/top-learners.php">
                      <input type="hidden" name="action" value="toggle_top_learner">
                      <input type="hidden" name="student_id" value="<?php echo (int) $st['id']; ?>">
                      <button type="submit" class="feature-star-btn<?php echo $isTop ? ' featured' : ''; ?>" title="<?php echo $isTop ? 'Remove from top learners' : 'Add to top learners'; ?>">
                        <?php echo $isTop ? '★ Remove' : '☆ Feature'; ?>
                      </button>
                    </form>
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
<?php include __DIR__ . '/../includes/footer.php'; ?>
