<?php
// Inside/dashboard page — suppress the marketing topbar (see includes/header.php).
$__hideTopbar = true;
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/schools.php';
require_once __DIR__ . '/../includes/exams_db.php';
require_once __DIR__ . '/../includes/billing.php';
require_once __DIR__ . '/../includes/charts.php';
require_once __DIR__ . '/../includes/reports.php';

$user = inkwell_require_role('registrar');
$school = $user['school_id'] ? inkwell_get_school($user['school_id']) : null;

if ($user['status'] !== 'active' || !$school || inkwell_registrar_dashboard_locked($user)) {
  header('Location: /registrar/dashboard.php');
  exit;
}

$overview = inkwell_report_overview($school['id']);
$examTotals = inkwell_report_exam_totals($school['id']);
$subjectRows = inkwell_report_subject_breakdown($school['id']);
$teacherRows = inkwell_report_teacher_breakdown($school['id']);
$recentAttempts = inkwell_report_recent_attempts($school['id'], 15);

// -------- Download as plain text --------
if (isset($_GET['download'])) {
  $text = inkwell_report_text($school, $overview, $examTotals, $subjectRows, $teacherRows);
  $filename = preg_replace('/[^a-z0-9]+/i', '-', strtolower($school['name'])) . '-report-' . date('Y-m-d') . '.txt';
  header('Content-Type: text/plain; charset=utf-8');
  header('Content-Disposition: attachment; filename="' . $filename . '"');
  header('Content-Length: ' . strlen($text));
  echo $text;
  exit;
}

$dashNavTitle = 'Registrar';
$dashNavActive = 'reports';
$dashNavItems = [
  ['key' => 'dashboard', 'group' => 'General', 'href' => '/registrar/overview.php', 'label' => 'Dashboard', 'icon' => '🏠'],
  ['key' => 'subjects', 'group' => 'Academics', 'href' => '/registrar/dashboard.php', 'label' => 'Subjects', 'icon' => '📚'],
  ['key' => 'approvals', 'group' => 'Academics', 'href' => '/registrar/dashboard.php#approvals', 'label' => 'Approvals', 'icon' => '✅'],
  ['key' => 'departments', 'group' => 'Academics', 'href' => '/registrar/dashboard.php#departments', 'label' => 'Departments', 'icon' => '🏷️'],
  ['key' => 'teachers', 'group' => 'People', 'href' => '/registrar/teachers.php', 'label' => 'Teachers', 'icon' => '🧑‍🏫', 'count' => $overview['teacher_count']],
  ['key' => 'deans', 'group' => 'People', 'href' => '/registrar/deans.php', 'label' => 'Deans', 'icon' => '🏫', 'count' => $overview['dean_count']],
  ['key' => 'students', 'group' => 'People', 'href' => '/registrar/students.php', 'label' => 'Students', 'icon' => '🎓', 'count' => $overview['student_count']],
  ['key' => 'reports', 'group' => 'Reports', 'href' => '/registrar/reports.php', 'label' => 'Reports', 'icon' => '📊'],
];

$pageTitle = 'Registrar · Reports';
include __DIR__ . '/../includes/header.php';

$subjectChartRows = [];
foreach (array_slice($subjectRows, 0, 8) as $r) {
  if ($r['avg_percent'] !== null) $subjectChartRows[] = ['label' => $r['title'], 'value' => $r['avg_percent']];
}
$passFailRows = [];
if ($examTotals['graded_attempts'] > 0) {
  $passFailRows[] = ['label' => 'Passed', 'value' => $examTotals['passed_count']];
  $passFailRows[] = ['label' => 'Failed', 'value' => $examTotals['failed_count']];
}
?>
<div class="dash-shell">
  <?php include __DIR__ . '/../includes/dash_nav.php'; ?>
  <main class="admin-main">
    <div class="admin-header-row">
      <h1>Reports</h1>
      <div style="display:flex; gap:8px;">
        <a class="btn" href="/registrar/reports.php?download=1">⬇ Download report (.txt)</a>
        <a class="btn" href="/logout.php">Log out</a>
      </div>
    </div>
    <p class="admin-sub"><?php echo htmlspecialchars($school['name']); ?> — everything below is live, school-wide data across every teacher, subject, and student.</p>

    <section class="kpi-grid">
      <div class="kpi-card glass-card">
        <span class="kpi-label">Teachers</span>
        <span class="kpi-value"><?php echo $overview['teacher_count']; ?></span>
        <span class="kpi-sub"><?php echo $overview['dean_count']; ?> deans</span>
      </div>
      <div class="kpi-card glass-card">
        <span class="kpi-label">Students</span>
        <span class="kpi-value"><?php echo $overview['student_count']; ?></span>
        <span class="kpi-sub"><?php echo $overview['subject_count']; ?> subjects</span>
      </div>
      <div class="kpi-card glass-card">
        <span class="kpi-label">Exam attempts</span>
        <span class="kpi-value"><?php echo $examTotals['total_attempts']; ?></span>
        <span class="kpi-sub"><?php echo $examTotals['pending_attempts']; ?> awaiting grading</span>
      </div>
      <div class="kpi-card glass-card">
        <span class="kpi-label">Pass rate</span>
        <span class="kpi-value"><?php echo $examTotals['pass_rate'] !== null ? $examTotals['pass_rate'] . '%' : '—'; ?></span>
        <span class="kpi-sub">Avg score <?php echo $examTotals['avg_percent'] !== null ? $examTotals['avg_percent'] . '%' : 'n/a'; ?></span>
      </div>
      <div class="kpi-card glass-card">
        <span class="kpi-label">Certificates issued</span>
        <span class="kpi-value"><?php echo $overview['certificate_count']; ?></span>
        <span class="kpi-sub"><?php echo $overview['exam_count']; ?> exams total</span>
      </div>
    </section>

    <div class="dash-two-col">
      <section class="admin-card glass-card">
        <h2>Average score by subject</h2>
        <p class="admin-sub">Top 8 subjects by exam activity, graded attempts only.</p>
        <?php echo inkwell_hbar_list($subjectChartRows, ['suffix' => '%', 'color' => 'var(--nib)']); ?>
      </section>
      <section class="admin-card glass-card">
        <h2>Pass vs. fail</h2>
        <p class="admin-sub">All graded attempts, school-wide.</p>
        <?php echo inkwell_hbar_list($passFailRows, ['color' => 'var(--pine)']); ?>
      </section>
    </div>

    <section class="admin-card">
      <h2>By subject (<?php echo count($subjectRows); ?>)</h2>
      <?php if (empty($subjectRows)): ?>
        <p class="admin-sub">No subjects yet — create one from the Subjects tab.</p>
      <?php else: ?>
        <div class="search-filter">
          <input type="search" class="search-filter-input" data-filter-target="#reportSubjectsTable" placeholder="Search subjects...">
        </div>
        <div class="admin-table-wrap">
          <table class="admin-table" id="reportSubjectsTable" data-paginate="10">
            <thead><tr><th>Subject</th><th>Teacher</th><th>Students</th><th>Exams</th><th>Attempts</th><th>Pass rate</th><th>Avg score</th></tr></thead>
            <tbody>
              <?php foreach ($subjectRows as $r): ?>
                <tr data-filter-row>
                  <td><?php echo htmlspecialchars($r['title']); ?><?php echo $r['code'] ? ' <span class="admin-sub">(' . htmlspecialchars($r['code']) . ')</span>' : ''; ?></td>
                  <td><?php echo htmlspecialchars($r['teacher_name']); ?></td>
                  <td><?php echo $r['student_count']; ?></td>
                  <td><?php echo $r['exam_count']; ?></td>
                  <td><?php echo $r['attempt_count']; ?></td>
                  <td><?php echo $r['pass_rate'] !== null ? $r['pass_rate'] . '%' : '—'; ?></td>
                  <td><?php echo $r['avg_percent'] !== null ? $r['avg_percent'] . '%' : '—'; ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </section>

    <section class="admin-card">
      <h2>By teacher (<?php echo count($teacherRows); ?>)</h2>
      <?php if (empty($teacherRows)): ?>
        <p class="admin-sub">No teachers yet — add one from the Teachers tab.</p>
      <?php else: ?>
        <div class="search-filter">
          <input type="search" class="search-filter-input" data-filter-target="#reportTeachersTable" placeholder="Search teachers...">
        </div>
        <div class="admin-table-wrap">
          <table class="admin-table" id="reportTeachersTable" data-paginate="10">
            <thead><tr><th>Teacher</th><th>Subjects</th><th>Students</th><th>Attempts</th><th>Pass rate</th></tr></thead>
            <tbody>
              <?php foreach ($teacherRows as $r): ?>
                <tr data-filter-row>
                  <td><?php echo htmlspecialchars($r['name']); ?></td>
                  <td><?php echo $r['subject_count']; ?></td>
                  <td><?php echo $r['student_count']; ?></td>
                  <td><?php echo $r['attempt_count']; ?></td>
                  <td><?php echo $r['pass_rate'] !== null ? $r['pass_rate'] . '%' : '—'; ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </section>

    <section class="admin-card">
      <h2>Recent exam activity</h2>
      <?php if (empty($recentAttempts)): ?>
        <p class="admin-sub">No exam attempts yet.</p>
      <?php else: ?>
        <div class="admin-table-wrap">
          <table class="admin-table">
            <thead><tr><th>Student</th><th>Exam</th><th>Teacher</th><th>Status</th><th>Score</th><th>Submitted</th></tr></thead>
            <tbody>
              <?php foreach ($recentAttempts as $r): ?>
                <tr>
                  <td><?php echo htmlspecialchars($r['student_name']); ?></td>
                  <td><?php echo htmlspecialchars($r['exam_title']); ?></td>
                  <td><?php echo htmlspecialchars($r['teacher_name']); ?></td>
                  <td>
                    <?php if ($r['status'] !== 'graded'): ?>
                      <span class="badge badge-status-pending">grading pending</span>
                    <?php else: ?>
                      <span class="badge badge-status-<?php echo $r['passed'] ? 'active' : 'pending'; ?>"><?php echo $r['passed'] ? 'passed' : 'failed'; ?></span>
                    <?php endif; ?>
                  </td>
                  <td><?php echo $r['percent'] !== null ? (int) $r['percent'] . '%' : '—'; ?></td>
                  <td><?php echo date('M j, Y g:i A', strtotime($r['submitted_at'])); ?></td>
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
