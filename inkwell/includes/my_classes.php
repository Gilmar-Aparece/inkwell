<?php
/**
 * "Your classes" block — a quick-access grid of the student's approved
 * subjects. Lives on My Section (my-section.php), not the Exams page.
 * Each card opens the subject's dedicated page (/class.php?id=...) where
 * its exams, teacher info, and the Leave button live.
 * Expects $user, $enrolledSubjects to already be set by the including page.
 */
if (!$user || $user['role'] !== 'student' || empty($enrolledSubjects)) return;
?>
<section class="admin-card glass-card">
  <div class="admin-header-row" style="margin-bottom:0;">
    <div>
      <h2>Your classes (<?php echo count($enrolledSubjects); ?>)</h2>
      <p class="admin-sub">Every subject you've joined — open one to see its exams.</p>
    </div>
    <a class="btn" href="/cor.php">📄 Download COR</a>
  </div>
  <div class="drive-grid" style="margin-top:14px;">
    <?php foreach ($enrolledSubjects as $s): $subjExams = inkwell_subject_exams($s['id']); ?>
      <a class="drive-file-card" href="/class.php?id=<?php echo (int) $s['id']; ?>">
        <div class="drive-file-cover" style="background:linear-gradient(135deg, #6c5ce726, #6c5ce70d);">
          <span class="drive-file-icon" style="background:#6c5ce71f; color:#6c5ce7;">📚</span>
          <?php if (!empty($s['code'])): ?><span class="drive-file-badge"><?php echo htmlspecialchars($s['code']); ?></span><?php endif; ?>
        </div>
        <div class="drive-file-body">
          <span class="drive-file-name"><?php echo htmlspecialchars($s['title']); ?></span>
          <div class="drive-file-meta">
            <span><?php echo htmlspecialchars($s['teacher_name']); ?></span>
            <span><?php echo count($subjExams); ?> exam<?php echo count($subjExams) === 1 ? '' : 's'; ?></span>
          </div>
        </div>
      </a>
    <?php endforeach; ?>
  </div>
</section>
