<?php
// Inside/dashboard page — suppress the marketing topbar (see includes/header.php).
$__hideTopbar = true;
require_once __DIR__ . '/../includes/store.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/exams_db.php';
require_once __DIR__ . '/../includes/schools.php';
require_once __DIR__ . '/../includes/students.php';
require_once __DIR__ . '/../includes/billing.php';
require_once __DIR__ . '/../includes/lesson_progress.php';
inkwell_require_admin();

$legacyCertificates = array_reverse(inkwell_get_certificates());
$dbCertificates = array_map(function ($c) {
  $isManual = ($c['source'] ?? 'exam') === 'manual';
  return [
    'name' => $c['student_name'],
    'label' => $c['label'] . ($isManual ? ' — issued by ' . ($c['issued_by_name'] ?: 'staff') : ($c['teacher_name'] ? ' — with ' . $c['teacher_name'] : ' — self-study')),
    'percent' => $c['percent'],
    'issued_at' => $c['issued_at'],
    'id' => $c['id'],
    'source' => $isManual ? 'Issued' : 'Exam',
  ];
}, inkwell_db_all_certificates());
$certificates = array_merge($dbCertificates, $legacyCertificates);

$dashNavTitle = 'Admin';
$dashNavActive = 'certificates';
$dashNavItems = [
  ['key' => 'dashboard', 'group' => 'General', 'href' => '/admin/dashboard.php', 'label' => 'Dashboard', 'icon' => '🏠'],
  ['key' => 'settings', 'group' => 'General', 'href' => '/admin/index.php', 'label' => 'Settings', 'icon' => '⚙️'],
  ['key' => 'exams', 'group' => 'Academics', 'href' => '/admin/exams.php', 'label' => 'Exams', 'icon' => '🗂'],
  ['key' => 'lessons', 'group' => 'Academics', 'href' => '/admin/lessons.php', 'label' => 'Lessons', 'icon' => '📘'],
  ['key' => 'teachers', 'group' => 'People', 'href' => '/admin/teachers.php', 'label' => 'Teachers', 'icon' => '🧑‍🏫', 'count' => count(inkwell_list_teachers())],
  ['key' => 'deans', 'group' => 'People', 'href' => '/admin/deans.php', 'label' => 'Deans', 'icon' => '🎓', 'count' => count(inkwell_list_deans())],
  ['key' => 'registrars', 'group' => 'People', 'href' => '/admin/registrars.php', 'label' => 'Registrars', 'icon' => '🗂️', 'count' => count(inkwell_list_registrars())],
  ['key' => 'admins', 'group' => 'People', 'href' => '/admin/admins.php', 'label' => 'Admins', 'icon' => '🛡️', 'count' => count(inkwell_list_admins())],
  ['key' => 'students', 'group' => 'People', 'href' => '/admin/students.php', 'label' => 'Students', 'icon' => '🧑‍🎓', 'count' => count(inkwell_list_all_students())],
  ['key' => 'schools', 'group' => 'Schools & Recognition', 'href' => '/admin/schools.php', 'label' => 'Schools', 'icon' => '🏫', 'count' => count(inkwell_list_schools_with_stats())],
  ['key' => 'certificates', 'group' => 'Schools & Recognition', 'href' => '/admin/certificates.php', 'label' => 'Certificates', 'icon' => '📜', 'count' => count($certificates)],
  ['key' => 'top-learners', 'group' => 'Schools & Recognition', 'href' => '/admin/top-learners.php', 'label' => 'Top learners', 'icon' => '⭐', 'count' => count(inkwell_top_learner_ids())],
  ['key' => 'lesson-progress', 'group' => 'Schools & Recognition', 'href' => '/admin/lesson-progress.php', 'label' => 'Lesson progress', 'icon' => '📈', 'count' => count(inkwell_lesson_progress_overview())],
  ['key' => 'billing-dashboard', 'group' => 'Billing', 'href' => '/admin/billing-dashboard.php', 'label' => 'Dashboard', 'icon' => '📊'],
  ['key' => 'pricing', 'group' => 'Billing', 'href' => '/admin/pricing.php', 'label' => 'Pricing', 'icon' => '💳', 'count' => count(inkwell_list_plans())],
  ['key' => 'payment-methods', 'group' => 'Billing', 'href' => '/admin/payment-methods.php', 'label' => 'Payment methods', 'icon' => '🏦'],
  ['key' => 'payments', 'group' => 'Billing', 'href' => '/admin/payments.php', 'label' => 'Payments', 'icon' => '🧾', 'count' => inkwell_count_pending_payment_submissions()],
];

$pageTitle = 'Admin · Certificates';
include __DIR__ . '/../includes/header.php';
?>
<div class="dash-shell">
  <?php include __DIR__ . '/../includes/dash_nav.php'; ?>
  <main class="admin-main">
    <div class="admin-header-row">
      <h1>Issued certificates</h1>
      <a class="btn" href="/admin/logout.php">Log out</a>
    </div>

    <section class="admin-card">
      <h2>Issued certificates (<?php echo count($certificates); ?>)</h2>
      <?php if (empty($certificates)): ?>
        <p class="admin-sub">No certificates have been issued yet.</p>
      <?php else: ?>
        <div class="search-filter">
          <input type="search" class="search-filter-input" data-filter-target="#certificatesTable" placeholder="Search by name or language...">
        </div>
        <div class="admin-table-wrap">
          <table class="admin-table" id="certificatesTable">
            <thead><tr><th>Name</th><th>Language</th><th>Score</th><th>Date</th><th>Type</th><th></th></tr></thead>
            <tbody>
              <?php foreach ($certificates as $cert): ?>
                <tr data-filter-row>
                  <td><?php echo htmlspecialchars($cert['name']); ?></td>
                  <td><?php echo htmlspecialchars($cert['label']); ?></td>
                  <td><?php echo (int) $cert['percent']; ?>%</td>
                  <td><?php echo htmlspecialchars($cert['issued_at']); ?></td>
                  <td><?php echo htmlspecialchars($cert['source'] ?? 'Exam'); ?></td>
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
