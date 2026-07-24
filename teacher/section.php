<?php
// Inside/dashboard page — suppress the marketing topbar (see includes/header.php).
$__hideTopbar = true;
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/exams_db.php';
require_once __DIR__ . '/../includes/sections.php';

$user = inkwell_require_role('teacher');
if ($user['status'] !== 'active') {
  header('Location: /teacher/dashboard.php');
  exit;
}

$sectionId = (int) ($_GET['id'] ?? 0);
$section = inkwell_get_section($sectionId);
if (!$section) {
  http_response_code(404);
  die('Section not found.');
}

$isAdviser = (int) $section['teacher_id'] === (int) $user['id'];
$myMemberSectionIds = array_column(inkwell_teacher_member_sections($user['id']), 'id');
$belongs = $isAdviser || in_array($sectionId, array_map('intval', $myMemberSectionIds), true);
if (!$belongs) {
  http_response_code(404);
  die('Section not found.');
}

$subjects = inkwell_section_subjects($sectionId);
$teachers = inkwell_section_teacher_list($sectionId);
$students = inkwell_section_students($sectionId);
$myOwnSubjectsHere = array_values(array_filter($subjects, function ($s) use ($user) {
  return (int) $s['teacher_id'] === (int) $user['id'];
}));

$pageTitle = $section['name'];
include __DIR__ . '/../includes/header.php';
?>
<main class="admin-main">
  <div class="admin-header-row">
    <div>
      <h1><?php echo htmlspecialchars($section['name']); ?></h1>
      <p class="admin-sub">
        <?php echo htmlspecialchars(trim(($section['term'] ?? '') . ' ' . ($section['academic_year'] ?? '')) ?: 'No term set'); ?>
        <?php if (!empty($section['year_level'])): ?> · <?php echo htmlspecialchars($section['year_level']); ?><?php endif; ?>
      </p>
    </div>
    <a class="btn" href="/teacher/sections.php">← Back to sections</a>
  </div>

  <?php if (!empty($myOwnSubjectsHere)): ?>
    <section class="admin-card glass-card">
      <div class="admin-header-row" style="margin-bottom:0;">
        <div>
          <h2>Class Record</h2>
          <p class="admin-sub">Every student's score across your exams and projects in this section, plus a downloadable Excel copy.</p>
        </div>
        <a class="btn primary" href="/teacher/class-record.php?section_id=<?php echo (int) $sectionId; ?>">Open Class Record →</a>
      </div>
    </section>
  <?php endif; ?>

  <section class="admin-card glass-card">
    <h2>Students in this section (<?php echo count($students); ?>)</h2>
    <?php if (empty($students)): ?>
      <p class="admin-sub">No students yet — they'll show up here automatically once they're approved into a subject tagged to this section.</p>
    <?php else: ?>
      <div class="people-list">
        <?php foreach ($students as $st): ?>
          <?php $__initial = strtoupper(substr(trim($st['name']), 0, 1)) ?: '?'; ?>
          <div class="people-row">
            <span class="people-avatar people-avatar-student"><?php echo htmlspecialchars($__initial); ?></span>
            <div class="people-text">
              <span class="people-name"><?php echo htmlspecialchars($st['name']); ?></span>
              <span class="people-sub"><?php echo htmlspecialchars($st['email'] ?? '—'); ?></span>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <section class="admin-card glass-card">
    <h2>Teachers in this section</h2>
    <div class="people-list">
      <?php foreach ($teachers as $t): ?>
        <?php $__initial = strtoupper(substr(trim($t['name']), 0, 1)) ?: '?'; ?>
        <div class="people-row">
          <span class="people-avatar people-avatar-teacher"><?php echo htmlspecialchars($__initial); ?></span>
          <div class="people-text">
            <span class="people-name"><?php echo htmlspecialchars($t['name']); ?></span>
          </div>
          <span class="badge <?php echo $t['is_adviser'] ? 'badge-status-active' : 'badge-teacher'; ?> people-role-badge"><?php echo $t['is_adviser'] ? 'Adviser' : 'Teacher'; ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="admin-card glass-card">
    <h2>Subjects in this section (<?php echo count($subjects); ?>)</h2>
    <p class="admin-sub">Tag subjects to this section from your <a href="/teacher/sections.php">Sections page</a>.</p>
    <?php if (empty($subjects)): ?>
      <p class="admin-sub">No subjects tagged to this section yet.</p>
    <?php else: ?>
      <div class="subj-list">
        <?php foreach ($subjects as $subj): ?>
          <?php
            $__initial = strtoupper(substr(trim($subj['title']), 0, 1)) ?: '?';
            $__canManage = (int) $subj['teacher_id'] === (int) $user['id'];
          ?>
          <div class="subj-row">
            <div class="subj-row-info">
              <span class="subj-row-avatar"><?php echo htmlspecialchars($__initial); ?></span>
              <div class="subj-row-text">
                <span class="subj-row-name">
                  <?php echo htmlspecialchars($subj['title']); ?>
                  <?php if (!empty($subj['code'])): ?><span class="subj-row-code"><?php echo htmlspecialchars($subj['code']); ?></span><?php endif; ?>
                </span>
                <span class="subj-row-teacher"><?php echo htmlspecialchars($subj['teacher_name']); ?></span>
              </div>
            </div>
            <div class="subj-row-meta">
              <span class="subj-row-exams"><?php echo (int) $subj['exam_count']; ?> exam<?php echo (int) $subj['exam_count'] === 1 ? '' : 's'; ?></span>
              <?php if ($__canManage): ?><a class="subj-row-manage" href="/teacher/subject.php?id=<?php echo (int) $subj['id']; ?>">Manage →</a><?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?>
