<?php
// Inside/dashboard page — suppress the marketing topbar (see includes/header.php).
$__hideTopbar = true;
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/exams_db.php';
require_once __DIR__ . '/../includes/events.php';
require_once __DIR__ . '/../includes/students.php';
require_once __DIR__ . '/../includes/sections.php';

$user = inkwell_require_role('teacher');
$isApproved = $user['status'] === 'active';

$error = '';
$notice = '';

if ($isApproved && $_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';

  if ($action === 'create_section') {
    $name = trim($_POST['name'] ?? '');
    $term = trim($_POST['term'] ?? '');
    $academicYear = trim($_POST['academic_year'] ?? '');
    $yearLevel = trim($_POST['year_level'] ?? '');
    $result = inkwell_create_section($user['id'], $user['school_id'] ?? null, $name, $term, $academicYear, $yearLevel);
    if ($result['ok']) {
      $notice = 'Section "' . htmlspecialchars($name) . '" created.';
    } else {
      $error = $result['error'];
    }
  }

  if ($action === 'request_join_section') {
    $sectionId = (int) ($_POST['section_id'] ?? 0);
    inkwell_request_join_section($user['id'], $sectionId);
    $notice = 'Request sent — the section adviser needs to approve it before you can teach there.';
  }

  if ($action === 'approve_section_request') {
    $reqId = (int) ($_POST['request_id'] ?? 0);
    inkwell_approve_section_request($reqId);
    $notice = 'Teacher approved into your section.';
  }

  if ($action === 'reject_section_request') {
    $reqId = (int) ($_POST['request_id'] ?? 0);
    inkwell_reject_section_request($reqId);
    $notice = 'Request declined.';
  }

  if ($action === 'assign_subject_section') {
    $subjectId = (int) ($_POST['subject_id'] ?? 0);
    $sectionId = (int) ($_POST['section_id'] ?? 0);
    $result = inkwell_set_subject_section($subjectId, $user['id'], $sectionId ?: null);
    if ($result['ok']) {
      $notice = 'Subject updated.';
    } else {
      $error = $result['error'];
    }
  }
}

$ownedSections = $isApproved ? inkwell_teacher_owned_sections($user['id']) : [];
$mySubjects = $isApproved ? inkwell_teacher_subjects($user['id']) : [];
$allMySections = $isApproved ? inkwell_teacher_all_sections($user['id']) : [];

$pendingCount = $isApproved ? count(inkwell_teacher_pending_attempts($user['id'])) : 0;
$joinRequestsCount = $isApproved ? count(inkwell_teacher_pending_join_requests($user['id'])) : 0;
$eventCount = $isApproved ? count(inkwell_events_by_author($user['id'])) : 0;
$studentCount = $isApproved ? count(inkwell_teacher_students($user['id'])) : 0;

$dashNavTitle = 'Teacher';
$dashNavActive = 'sections';
$dashNavItems = [
  ['key' => 'dashboard', 'group' => 'General', 'href' => '/teacher/overview.php', 'label' => 'Dashboard', 'icon' => '🏠'],
  ['key' => 'subjects', 'group' => 'Teaching', 'href' => '/teacher/dashboard.php', 'label' => 'Subjects', 'icon' => '🗂', 'count' => $joinRequestsCount],
  ['key' => 'sections', 'group' => 'Teaching', 'href' => '/teacher/sections.php', 'label' => 'Sections', 'icon' => '👥'],
  ['key' => 'grade', 'group' => 'Teaching', 'href' => '/teacher/grade.php', 'label' => 'Grade', 'icon' => '🖊', 'count' => $pendingCount],
  ['key' => 'students', 'group' => 'People', 'href' => '/teacher/students.php', 'label' => 'Students', 'icon' => '🎓', 'count' => $studentCount],
  ['key' => 'certificates', 'group' => 'People', 'href' => '/teacher/certificates.php', 'label' => 'Certificates', 'icon' => '📜'],
  ['key' => 'events', 'group' => 'Activity', 'href' => '/teacher/events.php', 'label' => 'Events', 'icon' => '📣', 'count' => $eventCount],
];

$pageTitle = 'Teacher · Sections';
include __DIR__ . '/../includes/header.php';
?>
<div class="dash-shell">
  <?php include __DIR__ . '/../includes/dash_nav.php'; ?>
  <main class="admin-main">
  <div class="admin-header-row">
    <h1>Sections</h1>
    <button class="btn primary" type="button" data-modal-open="createSectionModal">+ New section</button>
  </div>

  <?php if ($notice): ?><div class="exam-result pass"><?php echo htmlspecialchars($notice); ?></div><?php endif; ?>
  <?php if ($error): ?><div class="exam-result fail"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

  <?php if (!$isApproved): ?>
    <section class="admin-card glass-card">
      <h2>Waiting for admin approval</h2>
      <p class="admin-sub">Sections unlock once an admin approves your teacher account.</p>
    </section>
  <?php else: ?>

    <div class="modal-backdrop" id="createSectionModal">
      <div class="modal">
        <div class="modal-head">
          <h2>New section</h2>
          <button type="button" data-modal-close aria-label="Close">✕</button>
        </div>
        <p class="admin-sub">A section groups students taking the same block of subjects together, e.g. "BSIT-1A" or "Section A". Name it however your school does.</p>
        <form method="post" action="/teacher/sections.php" class="admin-form">
          <input type="hidden" name="action" value="create_section">
          <label for="section_name">Section name</label>
          <input type="text" id="section_name" name="name" maxlength="100" required placeholder="e.g. Section A">
          <div class="form-grid-2">
            <div>
              <label for="section_term">Term (optional)</label>
              <input type="text" id="section_term" name="term" maxlength="20" placeholder="e.g. 1st Semester">
            </div>
            <div>
              <label for="section_year">Academic year (optional)</label>
              <input type="text" id="section_year" name="academic_year" maxlength="20" placeholder="e.g. 2026-2027">
            </div>
          </div>
          <label for="section_year_level">Year level (optional)</label>
          <select id="section_year_level" name="year_level">
            <option value="">— Not set —</option>
            <?php foreach (inkwell_year_levels() as $__yl): ?>
              <option value="<?php echo htmlspecialchars($__yl); ?>"><?php echo htmlspecialchars($__yl); ?></option>
            <?php endforeach; ?>
          </select>
          <button class="btn primary" type="submit">Create section</button>
        </form>
      </div>
    </div>

    <section class="admin-card glass-card">
      <h2>Sections you advise (<?php echo count($ownedSections); ?>)</h2>
      <?php if (empty($ownedSections)): ?>
        <p class="admin-sub">You haven't created a section yet — use "+ New section" above.</p>
      <?php else: ?>
        <div class="admin-table-wrap">
          <table class="admin-table">
            <thead><tr><th>Name</th><th>Year level</th><th>Term</th><th>Subjects</th><th></th></tr></thead>
            <tbody>
              <?php foreach ($ownedSections as $sec): ?>
                <tr>
                  <td><?php echo htmlspecialchars($sec['name']); ?></td>
                  <td><?php echo htmlspecialchars($sec['year_level'] ?? '') ?: '—'; ?></td>
                  <td><?php echo htmlspecialchars(trim(($sec['term'] ?? '') . ' ' . ($sec['academic_year'] ?? '')) ?: '—'); ?></td>
                  <td><?php echo (int) $sec['subject_count']; ?></td>
                  <td><a href="/teacher/section.php?id=<?php echo (int) $sec['id']; ?>">Manage →</a></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </section>

    <?php if (!empty($mySubjects) && !empty($allMySections)): ?>
      <?php
        $__assignedCount = 0;
        foreach ($mySubjects as $__s) { if (!empty($__s['section_id'])) { $__assignedCount++; } }
      ?>
      <section class="admin-card glass-card">
        <div class="subj-assign-head">
          <div>
            <h2>Assign your subjects to a section</h2>
            <p class="admin-sub">Tag each subject to the section its students belong to, so they show up on that section's "My Section" page.</p>
          </div>
          <span class="subj-assign-progress"><?php echo (int) $__assignedCount; ?> / <?php echo count($mySubjects); ?> assigned</span>
        </div>

        <div class="subj-assign-list">
          <?php foreach ($mySubjects as $subj): ?>
            <?php
              $__isAssigned = !empty($subj['section_id']);
              $__initial = strtoupper(substr(trim($subj['title']), 0, 1)) ?: '?';
            ?>
            <div class="subj-assign-row<?php echo $__isAssigned ? '' : ' is-unassigned'; ?>">
              <div class="subj-assign-info">
                <span class="subj-assign-avatar"><?php echo htmlspecialchars($__initial); ?></span>
                <div class="subj-assign-text">
                  <span class="subj-assign-name"><?php echo htmlspecialchars($subj['title']); ?></span>
                  <span class="subj-assign-status">
                    <span class="subj-assign-dot"></span>
                    <?php echo $__isAssigned ? 'Assigned' : 'Unassigned'; ?>
                  </span>
                </div>
              </div>
              <form method="post" action="/teacher/sections.php" class="subj-assign-form">
                <input type="hidden" name="action" value="assign_subject_section">
                <input type="hidden" name="subject_id" value="<?php echo (int) $subj['id']; ?>">
                <select name="section_id" class="subj-assign-select" onchange="this.form.submit()">
                  <option value="">— No section —</option>
                  <?php foreach ($allMySections as $sec): ?>
                    <option value="<?php echo (int) $sec['id']; ?>"<?php echo (int) ($subj['section_id'] ?? 0) === (int) $sec['id'] ? ' selected' : ''; ?>><?php echo htmlspecialchars($sec['name']); ?></option>
                  <?php endforeach; ?>
                </select>
                <noscript><button class="btn" type="submit">Save</button></noscript>
              </form>
            </div>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endif; ?>

  <?php endif; ?>
  </main>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
