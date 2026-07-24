<?php
// Inside/dashboard page — suppress the marketing topbar (see includes/header.php).
$__hideTopbar = true;
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/exams_db.php';
require_once __DIR__ . '/../includes/events.php';
require_once __DIR__ . '/../includes/students.php';

$user = inkwell_require_role('teacher');
$isApproved = $user['status'] === 'active';
$subjectColsOk = inkwell_ensure_subject_code_units_columns();

$error = '';
$notice = '';

if ($isApproved && $_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';

  if ($action === 'approve_request') {
    $reqId = (int) ($_POST['request_id'] ?? 0);
    inkwell_approve_enrollment($reqId);
    $notice = 'Student approved — they can now take exams in that subject.';
  }

  if ($action === 'reject_request') {
    $reqId = (int) ($_POST['request_id'] ?? 0);
    inkwell_reject_enrollment($reqId);
    $notice = 'Request declined.';
  }

  if ($action === 'update_signer') {
    inkwell_update_teacher_signer($user['id'], $_POST['signer_name'] ?? '', $_POST['signer_title'] ?? '');
    $notice = 'Certificate signer updated.';
    $user = inkwell_get_user($user['id']);
  }

}

$subjects = $isApproved ? inkwell_teacher_subjects($user['id']) : [];
$pendingCount = $isApproved ? count(inkwell_teacher_pending_attempts($user['id'])) : 0;
$joinRequests = $isApproved ? inkwell_teacher_pending_join_requests($user['id']) : [];
$eventCount = $isApproved ? count(inkwell_events_by_author($user['id'])) : 0;
$studentCount = $isApproved ? count(inkwell_teacher_students($user['id'])) : 0;
$featured = ($isApproved && $user['school_id']) ? inkwell_featured_students((int) $user['school_id']) : [];

$dashNavTitle = 'Teacher';
$dashNavActive = 'subjects';
$dashNavItems = [
  ['key' => 'dashboard', 'group' => 'General', 'href' => '/teacher/overview.php', 'label' => 'Dashboard', 'icon' => '🏠'],
  ['key' => 'subjects', 'group' => 'Teaching', 'href' => '/teacher/dashboard.php', 'label' => 'Subjects', 'icon' => '🗂'],
  ['key' => 'sections', 'group' => 'Teaching', 'href' => '/teacher/sections.php', 'label' => 'Sections', 'icon' => '👥'],
  ['key' => 'grade', 'group' => 'Teaching', 'href' => '/teacher/grade.php', 'label' => 'Grade', 'icon' => '🖊', 'count' => $pendingCount],
  ['key' => 'students', 'group' => 'People', 'href' => '/teacher/students.php', 'label' => 'Students', 'icon' => '🎓', 'count' => $studentCount],
  ['key' => 'certificates', 'group' => 'People', 'href' => '/teacher/certificates.php', 'label' => 'Certificates', 'icon' => '📜'],
  ['key' => 'events', 'group' => 'Activity', 'href' => '/teacher/events.php', 'label' => 'Events', 'icon' => '📣', 'count' => $eventCount],
];

$pageTitle = 'Teacher dashboard';
include __DIR__ . '/../includes/header.php';
?>
<div class="dash-shell">
  <?php include __DIR__ . '/../includes/dash_nav.php'; ?>
  <main class="admin-main">
  <div class="admin-header-row">
    <h1>Teacher dashboard</h1>
    <div style="display:flex; gap:10px;">
      <a class="btn" href="/account.php">My account</a>
    </div>
  </div>

  <?php if ($notice): ?><div class="exam-result pass"><?php echo htmlspecialchars($notice); ?></div><?php endif; ?>
  <?php if ($error): ?><div class="exam-result fail"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

  <?php if (!$isApproved): ?>
    <section class="admin-card glass-card">
      <h2>Waiting for admin approval</h2>
      <p class="admin-sub">Hi <?php echo htmlspecialchars($user['name']); ?> — your teacher account is registered but hasn't been approved yet. Once an admin grants you exam-creation permission from the admin dashboard, this page will let you build subjects, exams, and questions.</p>
    </section>
  <?php else: ?>

    <?php if (!empty($joinRequests)): ?>
      <section class="admin-card glass-card">
        <h2>Join requests (<?php echo count($joinRequests); ?>)</h2>
        <p class="admin-sub">Students asking to join one of your subjects. Approve to let them take its exams.</p>
        <div class="search-filter">
          <input type="search" class="search-filter-input" data-filter-target="#joinRequestsList" placeholder="Search by student name...">
        </div>
        <div id="joinRequestsList">
        <?php foreach ($joinRequests as $r): ?>
          <div class="join-request-item" data-filter-row>
            <div class="jr-info">
              <span class="jr-name"><?php echo htmlspecialchars($r['student_name']); ?></span>
              <span class="jr-meta"><?php echo htmlspecialchars($r['student_email']); ?> · wants to join "<?php echo htmlspecialchars($r['subject_title']); ?>"</span>
            </div>
            <div class="join-request-actions">
              <form method="post" action="/teacher/dashboard.php">
                <input type="hidden" name="action" value="approve_request">
                <input type="hidden" name="request_id" value="<?php echo (int) $r['id']; ?>">
                <button class="btn primary" type="submit">Approve</button>
              </form>
              <form method="post" action="/teacher/dashboard.php" onsubmit="return confirm('Decline this request?');">
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

    <section class="admin-card glass-card">
      <div class="admin-header-row" style="margin-bottom:0;">
        <div>
          <h2>Your subjects (<?php echo count($subjects); ?>)</h2>
          <p class="admin-sub">A subject is your class, e.g. "IT Fundamentals". Only your registrar can create a subject and assign it to you — once one shows up here, add exams inside it and approve students who request to join.</p>
        </div>
      </div>
      <?php if (!empty($subjects)): ?>
        <div class="admin-header-row" style="margin-bottom:0; margin-top:-6px;">
          <span></span>
          <button class="btn" type="button" data-modal-open="quickCreateExamModal">+ New exam</button>
        </div>
      <?php endif; ?>
      <?php if (empty($subjects)): ?>
        <p class="admin-sub">No subjects assigned yet — ask your registrar to create one and assign it to you.</p>
      <?php else: ?>
        <div class="search-filter">
          <input type="search" class="search-filter-input" data-filter-target="#teacherSubjectsGrid" placeholder="Search your subjects...">
        </div>
        <div class="subject-grid" id="teacherSubjectsGrid">
          <?php foreach ($subjects as $s): ?>
            <div class="subject-card" data-filter-row>
              <div class="subject-card-top">
                <h3><?php echo htmlspecialchars($s['title']); ?><?php echo !empty($s['code']) ? ' <span class="admin-sub" style="font-weight:400;">(' . htmlspecialchars($s['code']) . ')</span>' : ''; ?></h3>
              </div>
              <p class="admin-sub"><?php echo htmlspecialchars($s['description'] ?: 'No description yet.'); ?></p>
              <div class="stat-row">
                <div class="stat-pill"><strong><?php echo (int) $s['exam_count']; ?></strong><span>Exams</span></div>
                <div class="stat-pill"><strong><?php echo (int) $s['student_count']; ?></strong><span>Students</span></div>
                <div class="stat-pill"><strong><?php echo (int) ($s['units'] ?? 3); ?></strong><span>Units</span></div>
              </div>
              <a class="btn primary" style="width:100%; justify-content:center; margin-top:12px;" href="/teacher/subject.php?id=<?php echo (int) $s['id']; ?>">Manage exams →</a>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>

    <section class="admin-card glass-card">
      <div class="admin-header-row" style="margin-bottom:0;">
        <div>
          <h2>Events (<?php echo $eventCount; ?>)</h2>
          <p class="admin-sub">Announcements you've posted to the public events feed, visible to every student and teacher.</p>
        </div>
        <a class="btn primary" href="/teacher/events.php">Manage events →</a>
      </div>
    </section>

    <section class="admin-card glass-card">
      <div class="admin-header-row" style="margin-bottom:0;">
        <div>
          <h2>Top students (<?php echo count($featured); ?>)</h2>
          <p class="admin-sub">Standout students you've featured for your school. Feature one from the <a href="/teacher/students.php">Students page</a>.</p>
        </div>
      </div>
      <?php if (empty($featured)): ?>
        <p class="admin-sub">No top students featured yet.</p>
      <?php else: ?>
        <div class="top-students-grid">
          <?php foreach ($featured as $f): ?>
            <div class="top-student-card">
              <span class="top-student-card-badge">🏅</span>
              <button type="button" class="student-cell-btn" style="flex-direction:column; align-items:center;" data-modal-open="studentProfileModal" data-student-id="<?php echo (int) $f['student_id']; ?>">
                <?php if (!empty($f['avatar'])): ?>
                  <img class="student-avatar-img" src="/assets/uploads/<?php echo htmlspecialchars($f['avatar']); ?>" alt="" loading="lazy">
                <?php else: ?>
                  <span class="student-avatar-placeholder" aria-hidden="true"><?php echo strtoupper(substr($f['name'], 0, 1)); ?></span>
                <?php endif; ?>
                <span class="top-student-card-name"><?php echo htmlspecialchars($f['name']); ?></span>
              </button>
              <span class="top-student-card-course"><?php echo htmlspecialchars($f['course'] ?: '—'); ?></span>
              <?php if (!empty($f['accomplishment'])): ?><span class="top-student-card-accomplishment"><?php echo htmlspecialchars($f['accomplishment']); ?></span><?php endif; ?>
              <?php if (!empty($f['note'])): ?><span class="top-student-card-note">"<?php echo htmlspecialchars($f['note']); ?>"</span><?php endif; ?>
              <?php if (!empty($f['description'])): ?><p class="top-student-card-desc"><?php echo nl2br(htmlspecialchars($f['description'])); ?></p><?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>

    <section class="admin-card glass-card">
      <h2>Certificate signer</h2>
      <p class="admin-sub">The name and title shown at the bottom of certificates your students earn — e.g. your school's principal or president. Overrides your school's signer, if any. Leave blank to use the default.</p>
      <form method="post" action="/teacher/dashboard.php" class="admin-form">
        <input type="hidden" name="action" value="update_signer">
        <div class="form-grid-2">
          <div>
            <label for="signer_name">Signer name</label>
            <input type="text" id="signer_name" name="signer_name" maxlength="100" placeholder="e.g. Dr. Maria Santos" value="<?php echo htmlspecialchars($user['signer_name'] ?? ''); ?>">
          </div>
          <div>
            <label for="signer_title">Title</label>
            <input type="text" id="signer_title" name="signer_title" maxlength="150" placeholder="e.g. School Principal" value="<?php echo htmlspecialchars($user['signer_title'] ?? ''); ?>">
          </div>
        </div>
        <button class="btn primary" type="submit">Save signer</button>
      </form>
    </section>

    <!-- Quick new exam modal (pick subject via dropdown) -->
    <div class="modal-backdrop" id="quickCreateExamModal">
      <div class="modal">
        <div class="modal-head">
          <h2>New exam</h2>
          <button type="button" data-modal-close aria-label="Close">✕</button>
        </div>
        <p class="admin-sub">Pick the subject/class this exam belongs to. You'll add questions on the next screen.</p>
        <form method="post" action="/teacher/subject.php" class="admin-form">
          <input type="hidden" name="action" value="create_exam">
          <label for="quick_exam_subject_id">Subject / class</label>
          <select id="quick_exam_subject_id" name="subject_id" required>
            <?php foreach ($subjects as $s): ?>
              <option value="<?php echo (int) $s['id']; ?>"><?php echo htmlspecialchars($s['title']); ?></option>
            <?php endforeach; ?>
          </select>
          <label for="quick_exam_title">Exam title</label>
          <input type="text" id="quick_exam_title" name="title" maxlength="150" required>
          <label for="quick_exam_description">Description (optional)</label>
          <input type="text" id="quick_exam_description" name="description" maxlength="500">

          <label>What is this exam for?</label>
          <div class="purpose-picker">
            <label class="purpose-option active" data-purpose-btn="cert">
              <input type="radio" name="purpose" value="cert" checked>
              <strong>Certification</strong>
              <span class="hint">Issues a certificate to students who pass.</span>
            </label>
            <label class="purpose-option" data-purpose-btn="grade">
              <input type="radio" name="purpose" value="grade">
              <strong>Grade only</strong>
              <span class="hint">Records a pass/fail score — no certificate.</span>
            </label>
          </div>

          <label for="quick_exam_pass_score">Pass score (%)</label>
          <input type="number" id="quick_exam_pass_score" name="pass_score" min="1" max="100" value="70">

          <button class="btn primary" type="submit">Create exam</button>
        </form>
      </div>
    </div>

  <?php endif; ?>
  </main>
</div>
<?php include __DIR__ . '/../includes/student_profile_modal.php'; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
