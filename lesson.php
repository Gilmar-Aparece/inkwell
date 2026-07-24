<?php
require_once __DIR__ . '/data/lessons.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/lesson_progress.php';

$activeCat = $_GET['cat'] ?? '';
$activeSlug = $_GET['slug'] ?? '';
$lesson = inkwell_lesson($activeCat, $activeSlug);
$category = inkwell_category($activeCat);

if (!$lesson || !$category) {
  http_response_code(404);
  $pageTitle = 'Lesson not found';
  include __DIR__ . '/includes/header.php';
  echo '<main style="padding:60px 40px;"><h1>Lesson not found</h1><p><a href="/index.php">Back to all lessons</a></p></main>';
  include __DIR__ . '/includes/footer.php';
  exit;
}

list($prevSlug, $nextSlug) = inkwell_neighbors($activeCat, $activeSlug);

$__lessonUser = inkwell_current_user();
$__lessonLocked = inkwell_lesson_is_locked($activeCat, $activeSlug, $__lessonUser);

if ($__lessonUser) {
  inkwell_update_last_lesson($__lessonUser['id'], $activeCat, $activeSlug);
  if (!$__lessonLocked) {
    inkwell_record_lesson_view($__lessonUser['id'], $activeCat, $activeSlug);
  }
}

$pageTitle = $lesson['title'];
include __DIR__ . '/includes/header.php';
?>
<div class="shell">
  <?php include __DIR__ . '/includes/sidebar.php'; ?>

  <div class="spread">
    <button class="toc-toggle" data-role="toc-toggle" type="button">☰ Contents</button>
    <div class="notes">
      <div class="crumb">
        <a href="/index.php">Inkwell</a> / <a href="/index.php#<?php echo htmlspecialchars($activeCat); ?>"><?php echo htmlspecialchars($category['label']); ?></a>
      </div>
      <h1><?php echo htmlspecialchars($lesson['title']); ?></h1>
      <p class="summary"><?php echo htmlspecialchars($lesson['summary']); ?></p>
      <?php if ($__lessonLocked): ?>
        <div class="body">
          <p>This lesson is part of the full <?php echo htmlspecialchars($category['label']); ?> track, unlocked with <strong>Pro Learner</strong>.</p>
        </div>
        <div class="exam-result" style="margin-top:8px;">
          🔒 The first <?php echo inkwell_free_lessons_per_track(); ?> lessons in every track are free — this one and the rest need a Pro plan.
          <?php if ($__lessonUser): ?>
            <a href="/my-billing.php">Upgrade to Pro Learner →</a>
          <?php else: ?>
            <a href="/login.php?next=<?php echo urlencode($_SERVER['REQUEST_URI'] ?? '/lesson.php'); ?>">Log in</a>, then
            <a href="/my-billing.php">upgrade to Pro Learner →</a>
          <?php endif; ?>
        </div>
      <?php else: ?>
        <div class="body"><?php echo $lesson['body']; /* trusted local content */ ?></div>
      <?php endif; ?>

      <div class="lesson-nav">
        <?php if ($prevSlug): ?>
          <a href="/lesson.php?cat=<?php echo urlencode($activeCat); ?>&slug=<?php echo urlencode($prevSlug); ?>">← <?php echo htmlspecialchars($category['lessons'][$prevSlug]['title']); ?></a>
        <?php else: ?><span class="placeholder">-</span><?php endif; ?>
        <?php if ($nextSlug): ?>
          <a href="/lesson.php?cat=<?php echo urlencode($activeCat); ?>&slug=<?php echo urlencode($nextSlug); ?>"><?php echo htmlspecialchars($category['lessons'][$nextSlug]['title']); ?> →</a>
        <?php else: ?>
          <a class="exam-cta" href="/exam.php?cat=<?php echo urlencode($activeCat); ?>">🎓 Take the <?php echo htmlspecialchars($category['label']); ?> exam →</a>
        <?php endif; ?>
      </div>
    </div>

    <div class="binding"></div>

    <?php $runnable = $category['runnable'] ?? true; ?>
    <?php if ($__lessonLocked): ?>
      <div class="editor-col static" id="inkwellEditor">
        <div class="static-note" style="padding:24px;">
          The code editor for this lesson unlocks with Pro Learner.
          <a href="/my-billing.php">See plans →</a>
        </div>
      </div>
    <?php elseif ($runnable): ?>
      <div class="editor-col" id="inkwellEditor">
        <div class="editor-tabs" data-role="tabs"></div>
        <button class="run-btn" data-role="run" type="button">▶ Run</button>
        <div class="editor-pane" data-role="editor-pane"></div>
        <div class="preview-wrap">
          <div class="preview-tabs">
            <button class="preview-tab active" data-role="preview-tab" data-target="preview" type="button">Preview</button>
            <button class="preview-tab" data-role="preview-tab" data-target="console" type="button">Console</button>
          </div>
          <iframe class="preview-frame" data-role="preview-frame" title="Live preview" sandbox="allow-scripts"></iframe>
          <div class="console-pane" data-role="console-pane"></div>
        </div>
      </div>
    <?php else: ?>
      <div class="editor-col static" id="inkwellEditor">
        <div class="editor-tabs">
          <div class="editor-tab active" style="cursor:default;"><?php echo htmlspecialchars($category['filename'] ?? 'code'); ?></div>
          <div class="spacer"></div>
          <button class="run-btn secondary" data-role="copy" type="button">⧉ Copy code</button>
        </div>
        <div class="editor-pane static-pane" data-role="editor-pane"></div>
        <div class="static-note">
          <?php echo htmlspecialchars($category['label']); ?> runs outside the browser — this pane is for reading, editing, and copying, not executing. Paste the code into a local compiler/interpreter to run it.
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>

<script src="/assets/js/editor.js?v=<?php echo filemtime(__DIR__ . '/assets/js/editor.js'); ?>"></script>
<script>
  document.addEventListener('DOMContentLoaded', function () {
    <?php if ($__lessonLocked): ?>
      // Locked lesson — no editor to initialize.
    <?php elseif ($runnable): ?>
      InkwellEditor.create({
        rootEl: document.getElementById('inkwellEditor'),
        initialHtml: <?php echo json_encode($lesson['html']); ?>,
        initialCss: <?php echo json_encode($lesson['css']); ?>,
        initialJs: <?php echo json_encode($lesson['js']); ?>
      });
    <?php else: ?>
      InkwellEditor.createStatic({
        rootEl: document.getElementById('inkwellEditor'),
        code: <?php echo json_encode($lesson['code']); ?>,
        language: <?php echo json_encode($category['monacoLang'] ?? 'plaintext'); ?>
      });
    <?php endif; ?>
  });
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
