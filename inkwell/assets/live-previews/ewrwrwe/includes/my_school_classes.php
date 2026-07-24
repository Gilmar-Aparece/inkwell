<?php
/**
 * "Your school" block on the Exams page — teachers at the student's school
 * + classes at that school they can still join. Extracted out of
 * exams.php so that page stays smaller.
 * Expects $user, $mySchool, $mySchoolTeachers, $mySchoolSubjects to
 * already be set by the including page (exams.php sets these before
 * including this file).
 */
if (!$mySchool) return;
?>
<section class="admin-card glass-card" id="my-school">
  <div class="school-card-head">
    <?php if (!empty($mySchool['logo'])): ?>
      <img class="school-logo" src="/assets/uploads/<?php echo htmlspecialchars($mySchool['logo']); ?>" alt="<?php echo htmlspecialchars($mySchool['name']); ?> logo" loading="lazy">
    <?php else: ?>
      <span class="school-logo-placeholder" aria-hidden="true">🏫</span>
    <?php endif; ?>
    <div>
      <h2><?php echo htmlspecialchars($mySchool['name']); ?></h2>
      <p class="admin-sub" style="margin:0;">Your school<?php echo !empty($mySchool['mission']) ? ' — ' . htmlspecialchars($mySchool['mission']) : ''; ?></p>
    </div>
  </div>

  <?php if (!empty($mySchoolTeachers)): ?>
    <h3 style="margin-top:18px;">Teachers at your school</h3>
    <div class="teacher-chip-grid">
      <?php foreach ($mySchoolTeachers as $t): ?>
        <button type="button" class="teacher-chip teacher-chip-lg" data-teacher-id="<?php echo (int) $t['id']; ?>" data-modal-open="teacherProfileModal">
          <span class="teacher-chip-avatar"><?php echo strtoupper(substr($t['name'], 0, 1)); ?></span>
          <?php echo htmlspecialchars($t['name']); ?>
        </button>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <p class="admin-sub" style="margin-top:12px;">No teachers have been added to your school yet.</p>
  <?php endif; ?>

  <?php if (!empty($mySchoolSubjects)): ?>
    <h3 style="margin-top:20px;">Classes you can join at your school</h3>
    <a class="exam-linkcard" href="/join-class.php">
      <span class="exam-linkcard-icon" aria-hidden="true">📚</span>
      <span class="exam-linkcard-body">
        <span class="exam-linkcard-title">Join a Class</span>
        <span class="exam-linkcard-sub"><?php echo count($mySchoolSubjects); ?> open class<?php echo count($mySchoolSubjects) === 1 ? '' : 'es'; ?> at <?php echo htmlspecialchars($mySchool['name']); ?> you haven't joined yet.</span>
      </span>
      <span class="exam-linkcard-arrow" aria-hidden="true">→</span>
    </a>
  <?php elseif (!empty($mySchoolTeachers)): ?>
    <p class="admin-sub" style="margin-top:12px;">You've already joined every class at your school, or none are published yet.</p>
  <?php endif; ?>
</section>
