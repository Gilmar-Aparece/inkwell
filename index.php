<?php
require_once __DIR__ . '/data/lessons.php';
$pageTitle = 'Learn to code';
include __DIR__ . '/includes/header.php';
$cats = inkwell_categories();
?>
<main>
  <div class="ink-bloom" aria-hidden="true"></div>
  <section class="hero">
    <div class="eyebrow">01 · a page, an editor, a running preview</div>
    <h1>Read a line. Write a line.<br>Watch it run.</h1>
    <p class="lede">Inkwell pairs short lessons with a real code editor — the same one behind
      VS Code — so nothing you learn stays theoretical. Change the code, press Run, see the result
      right beside the note that explained it.</p>
    <div class="cta-row">
      <a class="btn primary" href="/lesson.php?cat=html&slug=intro">Start with HTML →</a>
      <a class="btn" href="/playground.php">Open blank playground</a>
    </div>
  </section>

  <?php foreach ($cats as $catKey => $cat): ?>
    <a name="<?php echo $catKey; ?>"></a>
  <?php endforeach; ?>

  <section class="cat-grid">
    <?php foreach ($cats as $catKey => $cat):
      $firstSlug = array_key_first($cat['lessons']);
      $count = count($cat['lessons']);
    ?>
      <div class="cat-card" id="<?php echo htmlspecialchars($catKey); ?>">
        <a class="cat-card-main" href="/lesson.php?cat=<?php echo urlencode($catKey); ?>&slug=<?php echo urlencode($firstSlug); ?>">
          <span class="tag" style="background:<?php echo $cat['color']; ?>22; color:<?php echo $cat['color']; ?>;">
            <?php echo htmlspecialchars($cat['label']); ?>
          </span>
          <h3><?php echo htmlspecialchars($cat['tagline']); ?></h3>
          <p><?php echo htmlspecialchars($cat['lessons'][$firstSlug]['summary']); ?></p>
        </a>
        <div class="cat-card-links">
          <span class="count"><?php echo $count; ?> lesson<?php echo $count === 1 ? '' : 's'; ?></span>
          <a class="cat-exam-link" href="/exam.php?cat=<?php echo urlencode($catKey); ?>">🎓 Exam</a>
        </div>
      </div>
    <?php endforeach; ?>
  </section>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
