<?php
// Inside/dashboard page — suppress the marketing topbar (see includes/header.php).
$__hideTopbar = true;
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/schools.php';
require_once __DIR__ . '/../includes/students.php';
require_once __DIR__ . '/../includes/billing.php';
require_once __DIR__ . '/../includes/departments.php';
require_once __DIR__ . '/../includes/student_profile_fields.php';

inkwell_ensure_department_columns();
$departments = inkwell_list_departments();
$profilesOk = inkwell_ensure_student_profiles_table();

$user = inkwell_require_role('registrar');
$school = $user['school_id'] ? inkwell_get_school($user['school_id']) : null;

if ($user['status'] !== 'active' || !$school || inkwell_registrar_dashboard_locked($user)) {
  header('Location: /registrar/dashboard.php');
  exit;
}

$notice = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';
  $studentId = (int) ($_POST['student_id'] ?? 0);

  if ($action === 'update_student') {
    $result = inkwell_update_student_info(
      $studentId,
      $school['id'],
      $_POST['name'] ?? '',
      $_POST['email'] ?? '',
      $_POST['id_number'] ?? '',
      $_POST['course'] ?? '',
      $_POST['department_id'] ?? null
    );
    if ($result['ok']) {
      $notice = 'Student info updated.';
    } else {
      $error = $result['error'];
    }
  }

  if ($action === 'save_profile') {
    $fields = [];
    foreach (inkwell_student_profile_field_keys() as $k) $fields[$k] = $_POST[$k] ?? '';
    $result = inkwell_save_student_profile_fields($studentId, $school['id'], $fields, $user['id']);
    if ($result['ok']) {
      $notice = 'Full profile saved.';
    } else {
      $error = $result['error'];
    }
  }
}

$students = inkwell_school_students($school['id']);
$studentProfiles = [];
if ($profilesOk) {
  foreach ($students as $st) {
    $studentProfiles[$st['id']] = inkwell_get_student_profile_fields($st['id']);
  }
}

$dashNavTitle = 'Registrar';
$dashNavActive = 'students';
$dashNavItems = [
  ['key' => 'dashboard', 'group' => 'General', 'href' => '/registrar/overview.php', 'label' => 'Dashboard', 'icon' => '🏠'],
  ['key' => 'subjects', 'group' => 'Academics', 'href' => '/registrar/dashboard.php', 'label' => 'Subjects', 'icon' => '📚'],
  ['key' => 'departments', 'group' => 'Academics', 'href' => '/registrar/dashboard.php#departments', 'label' => 'Departments', 'icon' => '🏷️'],
  ['key' => 'teachers', 'group' => 'People', 'href' => '/registrar/teachers.php', 'label' => 'Teachers', 'icon' => '🧑‍🏫', 'count' => count(inkwell_list_school_teachers($school['id']))],
  ['key' => 'deans', 'group' => 'People', 'href' => '/registrar/deans.php', 'label' => 'Deans', 'icon' => '🏫', 'count' => count(inkwell_list_school_deans($school['id']))],
  ['key' => 'students', 'group' => 'People', 'href' => '/registrar/students.php', 'label' => 'Students', 'icon' => '🎓', 'count' => count($students)],
  ['key' => 'reports', 'group' => 'Reports', 'href' => '/registrar/reports.php', 'label' => 'Reports', 'icon' => '📊'],
];

$pageTitle = 'Registrar · Students';
include __DIR__ . '/../includes/header.php';
?>
<div class="dash-shell">
  <?php include __DIR__ . '/../includes/dash_nav.php'; ?>
  <main class="admin-main">
    <div class="admin-header-row">
      <h1>Students</h1>
      <a class="btn" href="/logout.php">Log out</a>
    </div>

    <?php if ($notice): ?><div class="exam-result pass"><?php echo htmlspecialchars($notice); ?></div><?php endif; ?>
    <?php if ($error): ?><div class="exam-result fail"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

    <section class="admin-card">
      <h2>Students at <?php echo htmlspecialchars($school['name']); ?> (<?php echo count($students); ?>)</h2>
      <p class="admin-sub">Students who picked this school when they registered. Click a name to view their profile, or Edit to update their info.</p>
      <?php if (empty($students)): ?>
        <p class="admin-sub">No students have picked this school yet.</p>
      <?php else: ?>
        <div class="search-filter">
          <input type="search" class="search-filter-input" data-filter-target="#registrarStudentsTable" placeholder="Search by name, email, ID, or course...">
        </div>
        <div class="admin-table-wrap">
          <table class="admin-table" id="registrarStudentsTable" data-paginate="20">
            <thead><tr><th>Name</th><th>Email</th><th>Student ID</th><th>Course</th><th>Department</th><th>Registered</th><th></th></tr></thead>
            <tbody>
              <?php foreach ($students as $st): ?>
                <tr data-filter-row>
                  <td>
                    <button type="button" class="student-cell-btn" data-modal-open="studentProfileModal" data-student-id="<?php echo (int) $st['id']; ?>">
                      <?php if (!empty($st['avatar'])): ?>
                        <img class="student-avatar-img" src="/assets/uploads/<?php echo htmlspecialchars($st['avatar']); ?>" alt="" loading="lazy">
                      <?php else: ?>
                        <span class="student-avatar-placeholder" aria-hidden="true"><?php echo strtoupper(substr($st['name'], 0, 1)); ?></span>
                      <?php endif; ?>
                      <span class="student-cell-name"><?php echo htmlspecialchars($st['name']); ?></span>
                    </button>
                  </td>
                  <td><?php echo htmlspecialchars($st['email']); ?></td>
                  <td><?php echo htmlspecialchars($st['id_number'] ?? '—') ?: '—'; ?></td>
                  <td><?php echo htmlspecialchars($st['course'] ?? '—') ?: '—'; ?></td>
                  <td><?php
                    $stDeptCode = '—';
                    if (!empty($st['department_id'])) {
                      foreach ($departments as $d) { if ((int) $d['id'] === (int) $st['department_id']) { $stDeptCode = $d['code']; break; } }
                    }
                    echo htmlspecialchars($stDeptCode);
                  ?></td>
                  <td><?php echo htmlspecialchars(date('M j, Y', strtotime($st['created_at']))); ?></td>
                  <td>
                    <a class="btn" href="/registrar/student-grades.php?student_id=<?php echo (int) $st['id']; ?>">View Grades</a>
                    <button type="button" class="btn" data-edit-student-trigger data-modal-open="editStudentModal"
                      data-student-id="<?php echo (int) $st['id']; ?>"
                      data-name="<?php echo htmlspecialchars($st['name']); ?>"
                      data-email="<?php echo htmlspecialchars($st['email']); ?>"
                      data-id-number="<?php echo htmlspecialchars($st['id_number'] ?? ''); ?>"
                      data-course="<?php echo htmlspecialchars($st['course'] ?? ''); ?>"
                      data-department-id="<?php echo (int) ($st['department_id'] ?? 0); ?>">Edit</button>
                    <?php if ($profilesOk): ?>
                      <button type="button" class="btn" data-full-profile-trigger data-modal-open="fullProfileModal"
                        data-student-id="<?php echo (int) $st['id']; ?>"
                        data-name="<?php echo htmlspecialchars($st['name']); ?>">Full Profile</button>
                    <?php endif; ?>
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
<?php include __DIR__ . '/../includes/student_profile_modal.php'; ?>

<div class="modal-backdrop" id="editStudentModal">
  <div class="modal">
    <div class="modal-head">
      <h2>Edit student — <span id="editStudentName"></span></h2>
      <button type="button" data-modal-close aria-label="Close">✕</button>
    </div>
    <form method="post" action="/registrar/students.php" class="admin-form">
      <input type="hidden" name="action" value="update_student">
      <input type="hidden" name="student_id" id="editStudentId">
      <label for="editName">Name</label>
      <input type="text" id="editName" name="name" maxlength="150" required>
      <label for="editEmail">Email</label>
      <input type="email" id="editEmail" name="email" maxlength="190" required>
      <div class="form-grid-2">
        <div>
          <label for="editIdNumber">Student ID</label>
          <input type="text" id="editIdNumber" name="id_number" maxlength="50">
        </div>
        <div>
          <label for="editCourse">Course / Program</label>
          <input type="text" id="editCourse" name="course" maxlength="150">
        </div>
      </div>
      <label for="editDepartment">Department <small>(needed for Curriculum Builder to pre-add subjects for this student)</small></label>
      <select id="editDepartment" name="department_id">
        <option value="">— None —</option>
        <?php foreach ($departments as $d): ?>
          <option value="<?php echo (int) $d['id']; ?>"><?php echo htmlspecialchars($d['code'] . ' — ' . $d['name']); ?></option>
        <?php endforeach; ?>
      </select>
      <button class="btn primary" type="submit">Save changes</button>
    </form>
  </div>
</div>
<script>
document.addEventListener('click', function (e) {
  const btn = e.target.closest('[data-edit-student-trigger]');
  if (!btn) return;
  document.getElementById('editStudentId').value = btn.getAttribute('data-student-id') || '';
  document.getElementById('editStudentName').textContent = btn.getAttribute('data-name') || '';
  document.getElementById('editName').value = btn.getAttribute('data-name') || '';
  document.getElementById('editEmail').value = btn.getAttribute('data-email') || '';
  document.getElementById('editIdNumber').value = btn.getAttribute('data-id-number') || '';
  document.getElementById('editCourse').value = btn.getAttribute('data-course') || '';
  document.getElementById('editDepartment').value = btn.getAttribute('data-department-id') || '';
});
</script>

<?php if ($profilesOk): ?>
<div class="modal-backdrop" id="fullProfileModal">
  <div class="modal" style="max-width:720px;">
    <div class="modal-head">
      <h2>Full profile — <span id="fpName"></span></h2>
      <button type="button" data-modal-close aria-label="Close">✕</button>
    </div>
    <form method="post" action="/registrar/students.php" class="admin-form">
      <input type="hidden" name="action" value="save_profile">
      <input type="hidden" name="student_id" id="fpStudentId">

      <h3>Personal</h3>
      <div class="form-grid-2">
        <div><label for="fp_birth_date">Birth date</label><input type="date" id="fp_birth_date" name="birth_date"></div>
        <div><label for="fp_sex">Sex</label>
          <select id="fp_sex" name="sex"><option value="">— Select —</option><option>Male</option><option>Female</option></select>
        </div>
        <div><label for="fp_civil_status">Civil status</label>
          <select id="fp_civil_status" name="civil_status">
            <option value="">— Select —</option><option>Single</option><option>Married</option><option>Widowed</option><option>Separated</option>
          </select>
        </div>
        <div><label for="fp_nationality">Nationality</label><input type="text" id="fp_nationality" name="nationality" maxlength="100"></div>
        <div><label for="fp_religion">Religion</label><input type="text" id="fp_religion" name="religion" maxlength="100"></div>
        <div><label for="fp_lrn_number">LRN number</label><input type="text" id="fp_lrn_number" name="lrn_number" maxlength="20"></div>
      </div>

      <h3 style="margin-top:16px;">Address &amp; contact</h3>
      <div class="form-grid-2">
        <div><label for="fp_street">Street</label><input type="text" id="fp_street" name="street" maxlength="150"></div>
        <div><label for="fp_barangay">Barangay</label><input type="text" id="fp_barangay" name="barangay" maxlength="100"></div>
        <div><label for="fp_city">City / Municipality</label><input type="text" id="fp_city" name="city" maxlength="100"></div>
        <div><label for="fp_province">Province</label><input type="text" id="fp_province" name="province" maxlength="100"></div>
        <div><label for="fp_contact_number">Contact number</label><input type="text" id="fp_contact_number" name="contact_number" maxlength="30"></div>
      </div>

      <h3 style="margin-top:16px;">Guardian</h3>
      <div class="form-grid-2">
        <div><label for="fp_guardian_name">Guardian name</label><input type="text" id="fp_guardian_name" name="guardian_name" maxlength="150"></div>
        <div><label for="fp_guardian_relationship">Relationship</label><input type="text" id="fp_guardian_relationship" name="guardian_relationship" maxlength="50"></div>
        <div><label for="fp_guardian_contact">Guardian contact</label><input type="text" id="fp_guardian_contact" name="guardian_contact" maxlength="30"></div>
      </div>

      <h3 style="margin-top:16px;">Academic history</h3>
      <div class="form-grid-2">
        <div><label for="fp_elementary_school">Elementary school</label><input type="text" id="fp_elementary_school" name="elementary_school" maxlength="150"></div>
        <div><label for="fp_elementary_years">Years attended</label><input type="text" id="fp_elementary_years" name="elementary_years" maxlength="50" placeholder="e.g. 2012-2018"></div>
        <div><label for="fp_high_school">High school</label><input type="text" id="fp_high_school" name="high_school" maxlength="150"></div>
        <div><label for="fp_high_school_years">Years attended</label><input type="text" id="fp_high_school_years" name="high_school_years" maxlength="50"></div>
        <div><label for="fp_senior_high_school">Senior high school</label><input type="text" id="fp_senior_high_school" name="senior_high_school" maxlength="150"></div>
        <div><label for="fp_senior_high_years">Years attended</label><input type="text" id="fp_senior_high_years" name="senior_high_years" maxlength="50"></div>
        <div><label for="fp_degree_completed">Prior degree completed (if any)</label><input type="text" id="fp_degree_completed" name="degree_completed" maxlength="150"></div>
      </div>

      <button class="btn primary" type="submit" style="margin-top:14px;">Save profile</button>
    </form>
  </div>
</div>
<script>
window.__studentProfiles = <?php echo json_encode($studentProfiles, JSON_HEX_TAG | JSON_HEX_APOS); ?>;
document.addEventListener('click', function (e) {
  const btn = e.target.closest('[data-full-profile-trigger]');
  if (!btn) return;
  const id = btn.getAttribute('data-student-id');
  const profile = (window.__studentProfiles && window.__studentProfiles[id]) || {};
  document.getElementById('fpStudentId').value = id;
  document.getElementById('fpName').textContent = btn.getAttribute('data-name') || '';
  var keys = ['birth_date','sex','civil_status','nationality','religion','lrn_number',
    'street','barangay','city','province','contact_number',
    'guardian_name','guardian_relationship','guardian_contact',
    'elementary_school','elementary_years','high_school','high_school_years',
    'senior_high_school','senior_high_years','degree_completed'];
  keys.forEach(function (k) {
    var el = document.getElementById('fp_' + k);
    if (el) el.value = profile[k] || '';
  });
});
</script>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
