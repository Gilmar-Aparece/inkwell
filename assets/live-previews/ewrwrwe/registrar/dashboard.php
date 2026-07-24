<?php
// Inside/dashboard page — suppress the marketing topbar (see includes/header.php).
$__hideTopbar = true;
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/schools.php';
require_once __DIR__ . '/../includes/exams_db.php';
require_once __DIR__ . '/../includes/departments.php';
require_once __DIR__ . '/../includes/billing.php';

$user = inkwell_require_role('registrar');

$notice = '';
$error = '';
$subjectColsOk = inkwell_ensure_subject_code_units_columns();
$regColsOk = inkwell_ensure_subject_registrar_columns();
$deptColsOk = inkwell_ensure_department_columns();
$departments = inkwell_list_departments();

$school = $user['school_id'] ? inkwell_get_school($user['school_id']) : null;
$teachers = $school ? inkwell_list_school_teachers($school['id'], true) : [];

// Approved account but no active school plan — the whole dashboard (this
// page plus teachers/deans/students/reports, which all redirect back here)
// stays locked until they subscribe or renew. See inkwell_registrar_dashboard_locked().
$planLocked = $user['status'] === 'active' && inkwell_registrar_dashboard_locked($user);

if ($user['status'] === 'active' && !$planLocked && $_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';

  if ($action === 'create_subject') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $code = trim($_POST['code'] ?? '');
    $units = (int) ($_POST['units'] ?? 3);
    $term = trim($_POST['term'] ?? '');
    $academicYear = trim($_POST['academic_year'] ?? '');
    $teacherId = (int) ($_POST['teacher_id'] ?? 0);

    $validTeacher = false;
    foreach ($teachers as $t) {
      if ((int) $t['id'] === $teacherId) { $validTeacher = true; break; }
    }

    if (!$school) {
      $error = 'Your account has no school attached yet — contact an admin.';
    } elseif ($title === '') {
      $error = 'Give the subject a title.';
    } elseif (!$validTeacher) {
      $error = 'Pick a teacher from your school to assign this subject to.';
    } else {
      $departmentId = (int) ($_POST['department_id'] ?? 0) ?: null;
      inkwell_create_subject_for_registrar($user['id'], $teacherId, $school['id'], $title, $description, $code, $units, $term, $academicYear, $departmentId);
      $notice = 'Subject created and assigned.';
    }
  }

  if ($action === 'create_department') {
    $result = inkwell_create_department($_POST['dept_code'] ?? '', $_POST['dept_name'] ?? '');
    if (!$result['ok']) {
      $error = $result['error'];
    } else {
      $notice = 'Department added.';
      $departments = inkwell_list_departments();
    }
  }

  if ($action === 'reassign_subject') {
    $subId = (int) ($_POST['subject_id'] ?? 0);
    $teacherId = (int) ($_POST['teacher_id'] ?? 0);
    $subject = inkwell_get_subject($subId);
    $validTeacher = false;
    foreach ($teachers as $t) {
      if ((int) $t['id'] === $teacherId) { $validTeacher = true; break; }
    }
    if ($subject && (!$regColsOk['created_by'] || (int) ($subject['created_by'] ?? 0) === (int) $user['id']) && $validTeacher) {
      inkwell_reassign_subject_teacher($subId, $teacherId);
      $notice = 'Subject reassigned.';
    } else {
      $error = 'Could not reassign that subject.';
    }
  }

  if ($action === 'update_term') {
    $subId = (int) ($_POST['subject_id'] ?? 0);
    $subject = inkwell_get_subject($subId);
    if ($subject && (!$regColsOk['created_by'] || (int) ($subject['created_by'] ?? 0) === (int) $user['id'])) {
      inkwell_update_subject_term($subId, trim($_POST['term'] ?? ''), trim($_POST['academic_year'] ?? ''));
      $notice = 'Term updated.';
    }
  }

  if ($action === 'delete_subject') {
    $subId = (int) ($_POST['subject_id'] ?? 0);
    $subject = inkwell_get_subject($subId);
    if ($subject && (!$regColsOk['created_by'] || (int) ($subject['created_by'] ?? 0) === (int) $user['id'])) {
      inkwell_delete_subject($subId);
      $notice = 'Subject deleted.';
    }
  }

  if ($action === 'approve_request') {
    $reqId = (int) ($_POST['request_id'] ?? 0);
    if ($school && inkwell_enrollment_in_school($reqId, $school['id'])) {
      inkwell_approve_enrollment($reqId);
      $notice = 'Student approved — they can now take exams in that subject.';
    } else {
      $error = 'Could not approve that request.';
    }
  }

  if ($action === 'reject_request') {
    $reqId = (int) ($_POST['request_id'] ?? 0);
    if ($school && inkwell_enrollment_in_school($reqId, $school['id'])) {
      inkwell_reject_enrollment($reqId);
      $notice = 'Request declined.';
    } else {
      $error = 'Could not decline that request.';
    }
  }
}

$subjects = ($user['status'] === 'active' && !$planLocked) ? inkwell_registrar_subjects($user['id']) : [];
$joinRequests = ($user['status'] === 'active' && !$planLocked && $school) ? inkwell_registrar_pending_join_requests($school['id']) : [];

$currentYear = (int) date('Y');
$academicYears = [];
for ($y = $currentYear - 1; $y <= $currentYear + 1; $y++) $academicYears[] = $y . '-' . ($y + 1);
$defaultAcademicYear = $currentYear . '-' . ($currentYear + 1);

$dashNavTitle = 'Registrar';
$dashNavActive = 'subjects';
$dashNavItems = [
  ['key' => 'dashboard', 'group' => 'General', 'href' => '/registrar/overview.php', 'label' => 'Dashboard', 'icon' => '🏠'],
  ['key' => 'subjects', 'group' => 'Academics', 'href' => '/registrar/dashboard.php', 'label' => 'Subjects', 'icon' => '📚', 'count' => count($subjects)],
  ['key' => 'approvals', 'group' => 'Academics', 'href' => '/registrar/dashboard.php#approvals', 'label' => 'Approvals', 'icon' => '✅', 'count' => count($joinRequests)],
  ['key' => 'departments', 'group' => 'Academics', 'href' => '/registrar/dashboard.php#departments', 'label' => 'Departments', 'icon' => '🏷️', 'count' => count($departments)],
  ['key' => 'teachers', 'group' => 'People', 'href' => '/registrar/teachers.php', 'label' => 'Teachers', 'icon' => '🧑‍🏫', 'count' => count($teachers)],
  ['key' => 'deans', 'group' => 'People', 'href' => '/registrar/deans.php', 'label' => 'Deans', 'icon' => '🏫', 'count' => $school ? count(inkwell_list_school_deans($school['id'])) : 0],
  ['key' => 'students', 'group' => 'People', 'href' => '/registrar/students.php', 'label' => 'Students', 'icon' => '🎓', 'count' => $school ? count(inkwell_school_students($school['id'])) : 0],
  ['key' => 'reports', 'group' => 'Reports', 'href' => '/registrar/reports.php', 'label' => 'Reports', 'icon' => '📊'],
];

$pageTitle = 'Registrar · Subjects';
include __DIR__ . '/../includes/header.php';
?>
<?php if ($user['status'] !== 'active'): ?>
  <main class="admin-main">
    <div class="admin-header-row">
      <h1>Registrar dashboard</h1>
      <a class="btn" href="/logout.php">Log out</a>
    </div>
    <section class="admin-card glass-card">
      <h2>Waiting for admin approval</h2>
      <p class="admin-sub">Your registrar account is pending review. Once an admin approves it, you'll be able to create subjects and assign a teacher to each, per semester.</p>
    </section>
  </main>
<?php elseif ($planLocked): ?>
  <?php include __DIR__ . '/../includes/registrar_plan_lock.php'; ?>
<?php else: ?>
  <div class="dash-shell">
    <?php include __DIR__ . '/../includes/dash_nav.php'; ?>
    <main class="admin-main">
      <div class="admin-header-row">
        <h1>Subjects</h1>
        <a class="btn" href="/logout.php">Log out</a>
      </div>

      <?php if ($notice): ?><div class="exam-result pass"><?php echo htmlspecialchars($notice); ?></div><?php endif; ?>
      <?php if ($error): ?><div class="exam-result fail"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

      <?php if (!empty($joinRequests)): ?>
        <section class="admin-card glass-card" id="approvals">
          <h2>Enrollment approvals (<?php echo count($joinRequests); ?>)</h2>
          <p class="admin-sub">Students asking to join a subject at <?php echo $school ? htmlspecialchars($school['name']) : 'your school'; ?> — across every teacher's classes. Approve to let them take that subject's exams.</p>
          <div class="search-filter">
            <input type="search" class="search-filter-input" data-filter-target="#registrarJoinRequestsList" placeholder="Search by student or subject name...">
          </div>
          <div id="registrarJoinRequestsList">
            <?php foreach ($joinRequests as $r): ?>
              <div class="join-request-item" data-filter-row>
                <div class="jr-info">
                  <span class="jr-name"><?php echo htmlspecialchars($r['student_name']); ?></span>
                  <span class="jr-meta"><?php echo htmlspecialchars($r['student_email']); ?> · wants to join "<?php echo htmlspecialchars($r['subject_title']); ?>" with <?php echo htmlspecialchars($r['teacher_name']); ?><?php echo !empty($r['term']) ? ' · ' . htmlspecialchars($r['term']) : ''; ?><?php echo !empty($r['academic_year']) ? ' ' . htmlspecialchars($r['academic_year']) : ''; ?></span>
                </div>
                <div class="join-request-actions">
                  <form method="post" action="/registrar/dashboard.php">
                    <input type="hidden" name="action" value="approve_request">
                    <input type="hidden" name="request_id" value="<?php echo (int) $r['id']; ?>">
                    <button class="btn primary" type="submit">Approve</button>
                  </form>
                  <form method="post" action="/registrar/dashboard.php" onsubmit="return confirm('Decline this request?');">
                    <input type="hidden" name="action" value="reject_request">
                    <input type="hidden" name="request_id" value="<?php echo (int) $r['id']; ?>">
                    <button class="btn" type="submit">Decline</button>
                  </form>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </section>
      <?php endif; ?>

      <?php if (!$school): ?>
        <section class="admin-card glass-card">
          <h2>No school attached</h2>
          <p class="admin-sub">Your account isn't linked to a school yet, so there's no teacher list to assign subjects to. Contact an admin to get this fixed.</p>
        </section>
      <?php elseif (empty($teachers)): ?>
        <section class="admin-card glass-card">
          <h2><?php echo htmlspecialchars($school['name']); ?></h2>
          <p class="admin-sub">This school has no approved, active teachers yet. Once your dean adds and the teacher is active, they'll show up here so you can assign subjects to them.</p>
        </section>
      <?php else: ?>
        <section class="admin-card glass-card">
          <div class="admin-header-row" style="margin-bottom:0;">
            <div>
              <h2>New subject</h2>
              <p class="admin-sub"><?php echo htmlspecialchars($school['name']); ?> — only a registrar can create a subject; pick which teacher it belongs to for the semester.</p>
            </div>
            <button class="btn primary" type="button" data-modal-open="createSubjectModal">+ New subject</button>
          </div>
        </section>

        <div class="modal-backdrop" id="createSubjectModal">
          <div class="modal">
            <div class="modal-head">
              <h2>New subject</h2>
              <button type="button" data-modal-close aria-label="Close">✕</button>
            </div>
            <form method="post" action="/registrar/dashboard.php" class="admin-form">
              <input type="hidden" name="action" value="create_subject">
              <label for="title">Title</label>
              <input type="text" id="title" name="title" maxlength="150" required placeholder="e.g. IT Fundamentals">
              <label for="description">Description (optional)</label>
              <input type="text" id="description" name="description" maxlength="500">
              <div class="form-grid-2">
                <div>
                  <label for="code">Subject code (optional)</label>
                  <input type="text" id="code" name="code" maxlength="20" placeholder="e.g. SE101"<?php echo (!$subjectColsOk['code']) ? ' disabled' : ''; ?>>
                </div>
                <div>
                  <label for="units">Units</label>
                  <input type="number" id="units" name="units" min="1" max="12" value="3"<?php echo (!$subjectColsOk['units']) ? ' disabled' : ''; ?>>
                </div>
              </div>
              <div class="form-grid-2">
                <div>
                  <label for="term">Semester / term</label>
                  <select id="term" name="term"<?php echo (!$regColsOk['term']) ? ' disabled' : ''; ?>>
                    <option value="1st Semester">1st Semester</option>
                    <option value="2nd Semester">2nd Semester</option>
                    <option value="Summer">Summer</option>
                  </select>
                </div>
                <div>
                  <label for="academic_year">Academic year</label>
                  <select id="academic_year" name="academic_year"<?php echo (!$regColsOk['academic_year']) ? ' disabled' : ''; ?>>
                    <?php foreach ($academicYears as $ay): ?>
                      <option value="<?php echo htmlspecialchars($ay); ?>"<?php echo $ay === $defaultAcademicYear ? ' selected' : ''; ?>><?php echo htmlspecialchars($ay); ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>
              <label for="teacher_id">Assign to teacher</label>
              <select id="teacher_id" name="teacher_id" required>
                <option value="">Select a teacher…</option>
                <?php foreach ($teachers as $t): ?>
                  <option value="<?php echo (int) $t['id']; ?>"><?php echo htmlspecialchars($t['name']); ?></option>
                <?php endforeach; ?>
              </select>
              <label for="department_id">Department (optional — defaults to the teacher's own department)</label>
              <select id="department_id" name="department_id"<?php echo (!$deptColsOk['subjects']) ? ' disabled' : ''; ?>>
                <option value="">Use teacher's department</option>
                <?php foreach ($departments as $d): ?>
                  <option value="<?php echo (int) $d['id']; ?>"><?php echo htmlspecialchars($d['code']); ?> — <?php echo htmlspecialchars($d['name']); ?></option>
                <?php endforeach; ?>
              </select>
              <?php if (!$subjectColsOk['code'] || !$subjectColsOk['units'] || !$regColsOk['term'] || !$regColsOk['academic_year']): ?>
                <p class="exam-result fail">Some fields above can't be saved yet — the database is missing those columns. Run <code>MIGRATION_ADD_registrar_role.sql</code> (and <code>MIGRATION_ADD_subject_code_units.sql</code> if needed) from phpMyAdmin, then reload this page.</p>
              <?php endif; ?>
              <button class="btn primary" type="submit">Create subject</button>
            </form>
          </div>
        </div>

        <section class="admin-card">
          <h2>Subjects you've created (<?php echo count($subjects); ?>)</h2>
          <?php if (empty($subjects)): ?>
            <p class="admin-sub">No subjects yet — create one above.</p>
          <?php else: ?>
            <div class="search-filter">
              <input type="search" class="search-filter-input" data-filter-target="#registrarSubjectsTable" placeholder="Search subjects...">
            </div>
            <div class="admin-table-wrap">
              <table class="admin-table" id="registrarSubjectsTable" data-paginate="10">
                <thead><tr><th>Title</th><th>Code</th><th>Department</th><th>Teacher</th><th>Term</th><th>Students</th><th>Exams</th><th></th><th></th></tr></thead>
                <tbody>
                  <?php $deptByIdSubj = []; foreach ($departments as $d) $deptByIdSubj[(int) $d['id']] = $d['code']; ?>
                  <?php foreach ($subjects as $s): ?>
                    <tr data-filter-row>
                      <td><?php echo htmlspecialchars($s['title']); ?></td>
                      <td><?php echo htmlspecialchars($s['code'] ?? '—') ?: '—'; ?></td>
                      <td><?php echo htmlspecialchars($deptByIdSubj[(int) ($s['department_id'] ?? 0)] ?? '—'); ?></td>
                      <td>
                        <?php echo htmlspecialchars($s['teacher_name']); ?>
                        <?php if ($s['teacher_status'] !== 'active'): ?><span class="badge badge-status-pending">inactive</span><?php endif; ?>
                      </td>
                      <td><?php echo htmlspecialchars(trim(($s['term'] ?? '') . ' ' . ($s['academic_year'] ?? '')) ?: '—'); ?></td>
                      <td><?php echo (int) $s['student_count']; ?></td>
                      <td><?php echo (int) $s['exam_count']; ?></td>
                      <td>
                        <button class="btn" type="button" data-modal-open="reassignModal-<?php echo (int) $s['id']; ?>">Reassign</button>
                        <div class="modal-backdrop" id="reassignModal-<?php echo (int) $s['id']; ?>">
                          <div class="modal">
                            <div class="modal-head">
                              <h2>Reassign "<?php echo htmlspecialchars($s['title']); ?>"</h2>
                              <button type="button" data-modal-close aria-label="Close">✕</button>
                            </div>
                            <form method="post" action="/registrar/dashboard.php" class="admin-form">
                              <input type="hidden" name="action" value="reassign_subject">
                              <input type="hidden" name="subject_id" value="<?php echo (int) $s['id']; ?>">
                              <label for="teacher_id_<?php echo (int) $s['id']; ?>">Teacher</label>
                              <select id="teacher_id_<?php echo (int) $s['id']; ?>" name="teacher_id" required>
                                <?php foreach ($teachers as $t): ?>
                                  <option value="<?php echo (int) $t['id']; ?>"<?php echo (int) $t['id'] === (int) $s['teacher_id'] ? ' selected' : ''; ?>><?php echo htmlspecialchars($t['name']); ?></option>
                                <?php endforeach; ?>
                              </select>
                              <button class="btn primary" type="submit" style="margin-top:10px;">Save reassignment</button>
                            </form>
                          </div>
                        </div>
                        <button class="btn" type="button" data-modal-open="termModal-<?php echo (int) $s['id']; ?>">Edit term</button>
                        <div class="modal-backdrop" id="termModal-<?php echo (int) $s['id']; ?>">
                          <div class="modal">
                            <div class="modal-head">
                              <h2>Edit term — "<?php echo htmlspecialchars($s['title']); ?>"</h2>
                              <button type="button" data-modal-close aria-label="Close">✕</button>
                            </div>
                            <form method="post" action="/registrar/dashboard.php" class="admin-form">
                              <input type="hidden" name="action" value="update_term">
                              <input type="hidden" name="subject_id" value="<?php echo (int) $s['id']; ?>">
                              <div class="form-grid-2">
                                <div>
                                  <label for="term_<?php echo (int) $s['id']; ?>">Semester / term</label>
                                  <?php
                                    $rowTerms = ['1st Semester', '2nd Semester', 'Summer'];
                                    if (!empty($s['term']) && !in_array($s['term'], $rowTerms, true)) array_unshift($rowTerms, $s['term']);
                                  ?>
                                  <select id="term_<?php echo (int) $s['id']; ?>" name="term">
                                    <?php foreach ($rowTerms as $termOpt): ?>
                                      <option value="<?php echo htmlspecialchars($termOpt); ?>"<?php echo ($s['term'] ?? '') === $termOpt ? ' selected' : ''; ?>><?php echo htmlspecialchars($termOpt); ?></option>
                                    <?php endforeach; ?>
                                  </select>
                                </div>
                                <div>
                                  <label for="ay_<?php echo (int) $s['id']; ?>">Academic year</label>
                                  <?php
                                    $rowYears = $academicYears;
                                    if (!empty($s['academic_year']) && !in_array($s['academic_year'], $rowYears, true)) array_unshift($rowYears, $s['academic_year']);
                                  ?>
                                  <select id="ay_<?php echo (int) $s['id']; ?>" name="academic_year">
                                    <?php foreach ($rowYears as $ay): ?>
                                      <option value="<?php echo htmlspecialchars($ay); ?>"<?php echo ($s['academic_year'] ?? '') === $ay ? ' selected' : ''; ?>><?php echo htmlspecialchars($ay); ?></option>
                                    <?php endforeach; ?>
                                  </select>
                                </div>
                              </div>
                              <button class="btn primary" type="submit" style="margin-top:10px;">Save term</button>
                            </form>
                          </div>
                        </div>
                      </td>
                      <td>
                        <form method="post" action="/registrar/dashboard.php" onsubmit="return confirm('Delete this subject? Enrollments and exams inside it are removed too.');">
                          <input type="hidden" name="action" value="delete_subject">
                          <input type="hidden" name="subject_id" value="<?php echo (int) $s['id']; ?>">
                          <button class="btn" type="submit">Delete</button>
                        </form>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </section>
      <?php endif; ?>

      <section class="admin-card" id="departments">
        <h2>Departments (<?php echo count($departments); ?>)</h2>
        <p class="admin-sub">Tag teachers, deans, and subjects with a department so each department's Dean only sees their own teachers, subjects, and exam results. BSEED, BSIT, and BSHM are seeded by default — add more here any time.</p>
        <?php if (!$deptColsOk['users'] || !$deptColsOk['subjects']): ?>
          <p class="exam-result fail">Department tagging isn't fully active yet — the database is missing the <code>department_id</code> column. Run <code>MIGRATION_ADD_departments.sql</code> from phpMyAdmin, then reload this page.</p>
        <?php endif; ?>
        <?php if (!empty($departments)): ?>
          <div class="admin-table-wrap">
            <table class="admin-table">
              <thead><tr><th>Code</th><th>Name</th></tr></thead>
              <tbody>
                <?php foreach ($departments as $d): ?>
                  <tr>
                    <td><?php echo htmlspecialchars($d['code']); ?></td>
                    <td><?php echo htmlspecialchars($d['name']); ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
        <form method="post" action="/registrar/dashboard.php#departments" class="admin-form" style="margin-top:14px;">
          <input type="hidden" name="action" value="create_department">
          <div class="form-grid-2">
            <div>
              <label for="dept_code">Code</label>
              <input type="text" id="dept_code" name="dept_code" maxlength="20" required placeholder="e.g. BSED">
            </div>
            <div>
              <label for="dept_name">Full name</label>
              <input type="text" id="dept_name" name="dept_name" maxlength="150" required placeholder="e.g. Bachelor of Secondary Education">
            </div>
          </div>
          <button class="btn primary" type="submit">Add department</button>
        </form>
      </section>
    </main>
  </div>
<?php endif; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
