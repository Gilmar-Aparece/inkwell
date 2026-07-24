<?php
/**
 * "My subjects" dashboard block — extracted out of index.php.
 * Expects $__me, $__mySubjects, $__mySubjectsLabel to already be set by
 * the including page (index.php sets these before including this file).
 *
 * Subjects are grouped by year level so a teacher/student with subjects
 * across multiple year levels (e.g. a 1st Year and a 3rd Year class) sees
 * them split into labeled groups instead of one flat grid. For a teacher
 * the year level comes from the section the subject is tagged to
 * (section_year_level); for a student it's the year level they picked
 * when they enrolled (year_level on their enrollment row). Subjects with
 * no year level attached fall into a trailing "Other subjects" group.
 */
if (!$__me || empty($__mySubjects)) return;

$__mySubjectsByYear = [];
$__mySubjectsNoYear = [];
foreach ($__mySubjects as $__subj) {
  $__yl = trim($__subj['section_year_level'] ?? $__subj['year_level'] ?? '');
  if ($__yl === '') {
    $__mySubjectsNoYear[] = $__subj;
  } else {
    $__mySubjectsByYear[$__yl][] = $__subj;
  }
}
// Keep a stable, natural order (1st -> 4th) even if the DB returns them
// out of order, then append any custom/unrecognized year label, then the
// no-year group last.
$__yearOrder = inkwell_year_levels();
uksort($__mySubjectsByYear, function ($a, $b) use ($__yearOrder) {
  $ia = array_search($a, $__yearOrder, true);
  $ib = array_search($b, $__yearOrder, true);
  if ($ia === false) $ia = 999;
  if ($ib === false) $ib = 999;
  return $ia <=> $ib;
});
if (!empty($__mySubjectsNoYear)) $__mySubjectsByYear['Other subjects'] = $__mySubjectsNoYear;
?>
<section class="drive-my-subjects">
  <div class="drive-section-head">
    <h2><?php echo htmlspecialchars($__mySubjectsLabel); ?></h2>
  </div>
  <?php foreach ($__mySubjectsByYear as $__yearLabel => $__yearSubjects): ?>
    <?php if (count($__mySubjectsByYear) > 1): ?>
      <h3 class="drive-my-subjects-year"><?php echo htmlspecialchars($__yearLabel); ?></h3>
    <?php endif; ?>
    <div class="drive-grid">
      <?php foreach ($__yearSubjects as $__subj): ?>
        <a class="drive-file-card" href="/exams.php?subject=<?php echo (int) $__subj['id']; ?>">
          <div class="drive-file-cover" style="background:linear-gradient(135deg, #6c5ce726, #6c5ce70d);">
            <span class="drive-file-icon" style="background:#6c5ce71f; color:#6c5ce7;">📚</span>
            <?php if (!empty($__subj['code'])): ?><span class="drive-file-badge"><?php echo htmlspecialchars($__subj['code']); ?></span><?php endif; ?>
          </div>
          <div class="drive-file-body">
            <span class="drive-file-name"><?php echo htmlspecialchars($__subj['title']); ?></span>
            <div class="drive-file-meta">
              <span><?php echo (int) $__subj['units']; ?> unit<?php echo (int) $__subj['units'] === 1 ? '' : 's'; ?></span>
              <?php if (!empty($__subj['exam_count'])): ?><span><?php echo (int) $__subj['exam_count']; ?> exam<?php echo (int) $__subj['exam_count'] === 1 ? '' : 's'; ?></span><?php endif; ?>
            </div>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endforeach; ?>
</section>
