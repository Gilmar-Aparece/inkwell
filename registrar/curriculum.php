<?php
// Inside/dashboard page — suppress the marketing topbar (see includes/header.php).
$__hideTopbar = true;
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/schools.php';
require_once __DIR__ . '/../includes/exams_db.php';
require_once __DIR__ . '/../includes/departments.php';
require_once __DIR__ . '/../includes/curriculum.php';
require_once __DIR__ . '/../includes/students.php';
require_once __DIR__ . '/../includes/billing.php';

$user = inkwell_require_role('registrar');
$school = $user['school_id'] ? inkwell_get_school($user['school_id']) : null;

if ($user['status'] !== 'active' || !$school || inkwell_registrar_dashboard_locked($user)) {
  header('Location: /registrar/dashboard.php');
  exit;
}

inkwell_ensure_department_columns();
$departments = inkwell_list_departments();
$curriculumOk = inkwell_ensure_curriculum_table();

$notice = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';

  if ($action === 'save_slot') {
    $departmentId = (int) ($_POST['department_id'] ?? 0);
    $yearLevel = trim($_POST['year_level'] ?? '');
    $term = trim($_POST['term'] ?? '');
    $subjectIds = $_POST['subject_ids'] ?? [];
    $result = inkwell_set_curriculum_slot($school['id'], $departmentId, $yearLevel, $term, $subjectIds, $user['id']);
    if ($result['ok']) {
      $notice = 'Curriculum saved — ' . $result['count'] . ' subject' . ($result['count'] === 1 ? '' : 's') . ' required for this slot.';
    } else {
      $error = $result['error'];
    }
  }
}

$subjects = inkwell_school_subjects($school['id']);
$yearLevels = inkwell_year_levels();
$overview = $curriculumOk ? inkwell_curriculum_overview($school['id']) : [];
$students = inkwell_school_students($school['id']);

// Pre-select department/year from the querystring so a link like
// curriculum.php?department_id=2&year_level=1st+Year jumps straight to that slot.
$selDept = (int) ($_GET['department_id'] ?? 0);
$selYear = $_GET['year_level'] ?? '';

$dashNavTitle = 'Registrar';
$dashNavActive = 'curriculum';
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

$pageTitle = 'Registrar · Curriculum';
include __DIR__ . '/../includes/header.php';
?>
<div class="dash-shell">
  <?php include __DIR__ . '/../includes/dash_nav.php'; ?>
  <main class="admin-main">
    <div class="admin-header-row">
      <h1>Curriculum Builder</h1>
      <a class="btn" href="/logout.php">Log out</a>
    </div>

    <?php if ($notice): ?><div class="exam-result pass"><?php echo htmlspecialchars($notice); ?></div><?php endif; ?>
    <?php if ($error): ?><div class="exam-result fail"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

    <?php if (!$curriculumOk): ?>
      <section class="admin-card">
        <p class="admin-sub">The curriculum builder needs a database table this host hasn't granted yet. Ask your host to grant <code>CREATE TABLE</code> rights, or run the migration manually.</p>
      </section>
    <?php elseif (empty($departments)): ?>
      <section class="admin-card">
        <p class="admin-sub">No departments yet — <a href="/registrar/dashboard.php#departments">add a department</a> first, then come back here to build its curriculum.</p>
      </section>
    <?php else: ?>

    <section class="admin-card glass-card">
      <h2>1. Pick a department &amp; year level</h2>
      <p class="admin-sub">A "curriculum" here is just the required subject list for one department + year level. Students who follow this program and are marked <strong>Regular</strong> (in <a href="/registrar/students.php">Students</a>, set their Department) will see these subjects pre-added when they enroll.</p>
      <form method="get" action="/registrar/curriculum.php" class="form-grid-2" id="slotPickerForm">
        <div>
          <label for="pickDept">Department</label>
          <select id="pickDept" name="department_id" onchange="document.getElementById('slotPickerForm').submit()">
            <option value="">— Select —</option>
            <?php foreach ($departments as $d): ?>
              <option value="<?php echo (int) $d['id']; ?>"<?php echo $selDept === (int) $d['id'] ? ' selected' : ''; ?>><?php echo htmlspecialchars($d['code'] . ' — ' . $d['name']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label for="pickYear">Year Level</label>
          <select id="pickYear" name="year_level" onchange="document.getElementById('slotPickerForm').submit()">
            <option value="">— Select —</option>
            <?php foreach ($yearLevels as $yl): ?>
              <option value="<?php echo htmlspecialchars($yl); ?>"<?php echo $selYear === $yl ? ' selected' : ''; ?>><?php echo htmlspecialchars($yl); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </form>
    </section>

    <?php if ($selDept && $selYear): ?>
      <?php
        $slotIds = inkwell_curriculum_slot_subject_ids($school['id'], $selDept, $selYear);
      ?>
      <section class="admin-card glass-card">
        <h2>2. Required subjects — <?php
          foreach ($departments as $d) { if ((int) $d['id'] === $selDept) { echo htmlspecialchars($d['code']); break; } }
          echo ' · ' . htmlspecialchars($selYear);
        ?></h2>
        <?php if (empty($subjects)): ?>
          <p class="admin-sub">No subjects at your school yet — <a href="/registrar/dashboard.php">create some</a> first.</p>
        <?php else: ?>
          <form method="post" action="/registrar/curriculum.php?department_id=<?php echo $selDept; ?>&year_level=<?php echo urlencode($selYear); ?>" class="admin-form">
            <input type="hidden" name="action" value="save_slot">
            <input type="hidden" name="department_id" value="<?php echo $selDept; ?>">
            <input type="hidden" name="year_level" value="<?php echo htmlspecialchars($selYear); ?>">
            <div style="margin-bottom:12px;">
              <label for="slotTerm">Term (optional — leave blank to require every term)</label>
              <select id="slotTerm" name="term">
                <option value="">Any term</option>
                <option value="1st Semester">1st Semester</option>
                <option value="2nd Semester">2nd Semester</option>
                <option value="Summer">Summer</option>
              </select>
            </div>
            <div class="curriculum-subject-grid">
              <?php foreach ($subjects as $s): ?>
                <label class="curriculum-subject-check">
                  <input type="checkbox" name="subject_ids[]" value="<?php echo (int) $s['id']; ?>"<?php echo in_array((int) $s['id'], $slotIds, true) ? ' checked' : ''; ?>>
                  <span><?php echo htmlspecialchars($s['title']); ?><?php if (!empty($s['code'])): ?> <small>(<?php echo htmlspecialchars($s['code']); ?>)</small><?php endif; ?></span>
                </label>
              <?php endforeach; ?>
            </div>
            <button class="btn primary" type="submit" style="margin-top:14px;">Save curriculum</button>
          </form>
        <?php endif; ?>
      </section>
    <?php endif; ?>

    <section class="admin-card">
      <h2>Curriculum overview</h2>
      <?php if (empty($overview)): ?>
        <p class="admin-sub">Nothing built yet — pick a department and year level above to get started.</p>
      <?php else: ?>
        <?php foreach ($overview as $dept): ?>
          <h3 style="margin-top:18px;"><?php echo htmlspecialchars($dept['dept_code'] . ' — ' . $dept['dept_name']); ?></h3>
          <?php foreach ($dept['years'] as $yl => $rows): ?>
            <p class="admin-sub" style="margin:6px 0 4px;">
              <a href="/registrar/curriculum.php?department_id=<?php echo $dept['department_id']; ?>&year_level=<?php echo urlencode($yl); ?>"><strong><?php echo htmlspecialchars($yl); ?></strong></a>
              — <?php echo count($rows); ?> subject<?php echo count($rows) === 1 ? '' : 's'; ?>:
              <?php echo htmlspecialchars(implode(', ', array_column($rows, 'subject_title'))); ?>
            </p>
          <?php endforeach; ?>
        <?php endforeach; ?>
      <?php endif; ?>
    </section>

    <?php endif; ?>
  </main>
</div>
<style>
.curriculum-subject-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(220px,1fr)); gap:8px; }
.curriculum-subject-check { display:flex; align-items:center; gap:8px; padding:8px 10px; border:1px solid var(--border,#333); border-radius:8px; cursor:pointer; }
.curriculum-subject-check input { margin:0; }
</style>
<?php include __DIR__ . '/../includes/footer.php'; ?>
