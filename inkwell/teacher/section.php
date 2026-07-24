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
      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead><tr><th>Name</th><th>Email</th></tr></thead>
          <tbody>
            <?php foreach ($students as $st): ?>
              <tr>
                <td><?php echo htmlspecialchars($st['name']); ?></td>
                <td><?php echo htmlspecialchars($st['email'] ?? ''); ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>

  <section class="admin-card glass-card">
    <h2>Teachers in this section</h2>
    <div class="admin-table-wrap">
      <table class="admin-table">
        <thead><tr><th>Name</th><th>Role</th></tr></thead>
        <tbody>
          <?php foreach ($teachers as $t): ?>
            <tr>
              <td><?php echo htmlspecialchars($t['name']); ?></td>
              <td><?php echo $t['is_adviser'] ? '<span class="badge badge-status-active">Adviser</span>' : 'Teacher'; ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>

  <section class="admin-card glass-card">
    <h2>Subjects in this section (<?php echo count($subjects); ?>)</h2>
    <p class="admin-sub">Tag subjects to this section from your <a href="/teacher/sections.php">Sections page</a>.</p>
    <?php if (empty($subjects)): ?>
      <p class="admin-sub">No subjects tagged to this section yet.</p>
    <?php else: ?>
      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead><tr><th>Subject</th><th>Teacher</th><th>Exams</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($subjects as $subj): ?>
              <tr>
                <td><?php echo htmlspecialchars($subj['title']); ?><?php echo !empty($subj['code']) ? ' <span class="admin-sub" style="font-weight:400;">(' . htmlspecialchars($subj['code']) . ')</span>' : ''; ?></td>
                <td><?php echo htmlspecialchars($subj['teacher_name']); ?></td>
                <td><?php echo (int) $subj['exam_count']; ?></td>
                <td><?php if ((int) $subj['teacher_id'] === (int) $user['id']): ?><a href="/teacher/subject.php?id=<?php echo (int) $subj['id']; ?>">Manage →</a><?php endif; ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?>
