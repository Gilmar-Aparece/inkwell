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

$sectionId = (int) ($_GET['section_id'] ?? 0);
$section = inkwell_get_section($sectionId);
if (!$section) {
  http_response_code(404);
  die('Section not found.');
}

$isAdviser = (int) $section['teacher_id'] === (int) $user['id'];
$myMemberSectionIds = array_map('intval', array_column(inkwell_teacher_member_sections($user['id']), 'id'));
$belongs = $isAdviser || in_array($sectionId, $myMemberSectionIds, true);
if (!$belongs) {
  http_response_code(404);
  die('Section not found.');
}

$record = inkwell_section_class_record($sectionId, $user['id']);
$summary = inkwell_class_record_compute($record);

$pageTitle = 'Class Record — ' . $section['name'];
include __DIR__ . '/../includes/header.php';
?>
<main class="admin-main">
  <div class="admin-header-row">
    <div>
      <h1>Class Record — <?php echo htmlspecialchars($section['name']); ?></h1>
      <p class="admin-sub">Your subjects' exam and project scores for every student in this section, grouped into Prelim, Midterm, and Final terms.</p>
    </div>
    <div style="display:flex; gap:10px;">
      <?php if (!empty($record['subjects'])): ?>
        <a class="btn primary" href="/teacher/class-record-export.php?section_id=<?php echo (int) $sectionId; ?>">⬇ Download Excel</a>
      <?php endif; ?>
      <a class="btn" href="/teacher/section.php?id=<?php echo (int) $sectionId; ?>">← Back to section</a>
    </div>
  </div>

  <?php if (empty($record['subjects'])): ?>
    <section class="admin-card glass-card">
      <h2>No subjects tagged here yet</h2>
      <p class="admin-sub">Tag one of your subjects to this section from the <a href="/teacher/sections.php">Sections page</a> to start building a class record.</p>
    </section>
  <?php elseif (empty($record['students'])): ?>
    <section class="admin-card glass-card">
      <h2>No students yet</h2>
      <p class="admin-sub">Students show up here once they're approved into a subject tagged to this section.</p>
    </section>
  <?php else: ?>

    <?php if (count($record['subjects']) > 1): ?>
      <section class="admin-card glass-card">
        <h2>Overall summary</h2>
        <p class="admin-sub">Each student's average across all of your subjects in this section.</p>
        <div class="admin-table-wrap">
          <table class="admin-table">
            <thead>
              <tr>
                <th>Student</th>
                <?php foreach ($record['subjects'] as $subj): ?>
                  <th><?php echo htmlspecialchars($subj['title']); ?></th>
                <?php endforeach; ?>
                <th>Overall Final Grade</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($record['students'] as $student): $stuId = (int) $student['id']; ?>
                <tr>
                  <td><?php echo htmlspecialchars($student['name']); ?></td>
                  <?php foreach ($record['subjects'] as $subj):
                    $sid = (int) $subj['id'];
                    $avg = $summary['per_subject'][$sid]['averages'][$stuId] ?? null;
                  ?>
                    <td><?php echo $avg !== null ? $avg . '%' : '—'; ?></td>
                  <?php endforeach; ?>
                  <td><strong><?php echo $summary['overall_averages'][$stuId] !== null ? $summary['overall_averages'][$stuId] . '%' : '—'; ?></strong></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>
    <?php endif; ?>

    <?php $__terms = inkwell_class_record_terms(); ?>
    <?php foreach ($record['subjects'] as $subj):
      $sid = (int) $subj['id'];
      $subjData = $summary['per_subject'][$sid];
      $assessments = $subjData['assessments'];
      $assessmentsByTerm = $subjData['assessments_by_term'];
    ?>
      <section class="admin-card glass-card">
        <h2><?php echo htmlspecialchars($subj['title']); ?></h2>
        <?php if (empty($assessments)): ?>
          <p class="admin-sub">No exams or projects created for this subject yet.</p>
        <?php else: ?>
          <div class="admin-table-wrap">
            <table class="admin-table">
              <thead>
                <tr>
                  <th rowspan="2">Student</th>
                  <?php foreach ($__terms as $termKey => $termLabel): ?>
                    <?php $termAsms = $assessmentsByTerm[$termKey]; ?>
                    <?php if (!empty($termAsms)): ?>
                      <th colspan="<?php echo count($termAsms) + 1; ?>" style="text-align:center;"><?php echo htmlspecialchars($termLabel); ?></th>
                    <?php endif; ?>
                  <?php endforeach; ?>
                  <th rowspan="2">Final Grade</th>
                </tr>
                <tr>
                  <?php foreach ($__terms as $termKey => $termLabel): ?>
                    <?php foreach ($assessmentsByTerm[$termKey] as $asm): ?>
                      <th>
                        <?php echo htmlspecialchars($asm['title']); ?>
                        <span class="badge <?php echo ($asm['kind'] ?? 'exam') === 'project' ? 'badge-status-pending' : 'badge-status-active'; ?>" style="margin-left:6px;"><?php echo ($asm['kind'] ?? 'exam') === 'project' ? 'Project' : 'Exam'; ?></span>
                      </th>
                    <?php endforeach; ?>
                    <?php if (!empty($assessmentsByTerm[$termKey])): ?>
                      <th><?php echo htmlspecialchars($termLabel); ?> Grade</th>
                    <?php endif; ?>
                  <?php endforeach; ?>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($record['students'] as $student): $stuId = (int) $student['id']; ?>
                  <tr>
                    <td><?php echo htmlspecialchars($student['name']); ?></td>
                    <?php foreach ($__terms as $termKey => $termLabel): ?>
                      <?php foreach ($assessmentsByTerm[$termKey] as $asm):
                        $catId = (int) $asm['id'];
                        $cell = $subjData['scores'][$stuId][$catId] ?? ['percent' => null, 'status' => 'none'];
                      ?>
                        <td>
                          <?php if ($cell['status'] === 'graded'): ?>
                            <?php echo (int) $cell['percent']; ?>%
                          <?php elseif ($cell['status'] === 'pending'): ?>
                            <span class="badge badge-status-pending">Pending</span>
                          <?php else: ?>
                            —
                          <?php endif; ?>
                        </td>
                      <?php endforeach; ?>
                      <?php if (!empty($assessmentsByTerm[$termKey])):
                        $termGrade = $subjData['term_grades'][$stuId][$termKey] ?? null;
                      ?>
                        <td><strong><?php echo $termGrade !== null ? $termGrade . '%' : '—'; ?></strong></td>
                      <?php endif; ?>
                    <?php endforeach; ?>
                    <td><strong><?php echo $subjData['averages'][$stuId] !== null ? $subjData['averages'][$stuId] . '%' : '—'; ?></strong></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </section>
    <?php endforeach; ?>

  <?php endif; ?>
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?>
