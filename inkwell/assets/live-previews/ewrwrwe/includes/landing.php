<?php
/**
 * Public landing page shown to guests instead of the logged-in dashboard.
 * Included from index.php when there's no signed-in user. Expects
 * $cats, $catsByCourse, $totalLessons, $allSchoolsCount, $allCertsCount,
 * $catIcons to already be set by index.php, and includes/billing.php to
 * be loaded.
 */
require_once __DIR__ . '/billing.php';
$__landingPlans = inkwell_list_plans(true);
$__landingMethods = inkwell_list_payment_methods(true);
?>
<main class="landing-main">

  <section class="landing-hero">
    <div class="landing-hero-inner">
      <span class="landing-eyebrow">Notebook meets terminal</span>
      <h1>Learn to code the way you'd actually enjoy it.</h1>
      <p class="landing-sub">Inkwell is a coding classroom in a browser tab — bite-sized lessons, a live playground, certification exams, and real certificates. Built for students, run by schools.</p>
      <div class="landing-cta-row">
        <a class="btn primary" href="/register.php">Get started free</a>
        <a class="btn" href="/login.php">Log in</a>
      </div>
      <div class="landing-stats">
        <div class="landing-stat"><strong><?php echo (int) $totalLessons; ?>+</strong><span>Lessons</span></div>
        <div class="landing-stat"><strong><?php echo (int) $allSchoolsCount; ?>+</strong><span>Schools</span></div>
        <div class="landing-stat"><strong><?php echo (int) $allCertsCount; ?>+</strong><span>Certificates issued</span></div>
      </div>
    </div>
  </section>

  <section class="landing-section">
    <h2 class="landing-section-title">Everything you need to go from "hello world" to certified</h2>

    <div class="dept-tabs dept-tabs-landing" id="landingDeptTabs" role="tablist" aria-label="Filter by department">
      <?php $__landingFirst = true; foreach ($catsByCourse as $__landingCode => $__landingData):
        if (empty($__landingData['tracks'])) continue;
        $__landingId = 'landing-course-' . strtolower($__landingCode);
      ?>
        <button type="button" class="dept-tab<?php echo $__landingFirst ? ' active' : ''; ?>" role="tab" data-dept-target="<?php echo htmlspecialchars($__landingId); ?>">
          <?php echo htmlspecialchars($__landingCode); ?>
          <span class="dept-tab-count"><?php echo count($__landingData['tracks']); ?></span>
        </button>
      <?php $__landingFirst = false; endforeach; ?>
    </div>

    <div class="dept-swipe" id="landingDeptSwipe">
      <?php $__landingFirst = true; foreach ($catsByCourse as $__landingCode => $__landingData):
        if (empty($__landingData['tracks'])) continue;
      ?>
        <section class="dept-panel" id="landing-course-<?php echo htmlspecialchars(strtolower($__landingCode)); ?>" role="tabpanel">
          <div class="landing-feature-grid">
            <?php foreach ($__landingData['tracks'] as $catKey => $cat): ?>
              <div class="landing-feature-card glass-card">
                <span class="landing-feature-icon"><?php echo $catIcons[$catKey] ?? '📄'; ?></span>
                <strong><?php echo htmlspecialchars($cat['label']); ?></strong>
                <span class="landing-feature-count"><?php echo count($cat['lessons']); ?> lessons</span>
              </div>
            <?php endforeach; ?>
          </div>
        </section>
      <?php $__landingFirst = false; endforeach; ?>
    </div>
  </section>

  <script>
  (function () {
    var tabs = document.getElementById('landingDeptTabs');
    var swipe = document.getElementById('landingDeptSwipe');
    if (!tabs || !swipe) return;
    var buttons = Array.prototype.slice.call(tabs.querySelectorAll('.dept-tab'));
    var panels = Array.prototype.slice.call(swipe.querySelectorAll('.dept-panel'));
    function setActive(id) {
      buttons.forEach(function (b) { b.classList.toggle('active', b.getAttribute('data-dept-target') === id); });
    }
    function goTo(id, behavior) {
      var panel = document.getElementById(id);
      if (!panel) return;
      swipe.scrollTo({ left: panel.offsetLeft - swipe.offsetLeft, behavior: behavior || 'smooth' });
      setActive(id);
    }
    buttons.forEach(function (b) {
      b.addEventListener('click', function () { goTo(b.getAttribute('data-dept-target')); });
    });
    var t = null;
    swipe.addEventListener('scroll', function () {
      if (t) clearTimeout(t);
      t = setTimeout(function () {
        var closest = null, dist = Infinity;
        panels.forEach(function (p) {
          var d = Math.abs((p.offsetLeft - swipe.offsetLeft) - swipe.scrollLeft);
          if (d < dist) { dist = d; closest = p; }
        });
        if (closest) setActive(closest.id);
      }, 100);
    }, { passive: true });
  })();
  </script>


  <section class="landing-section landing-section-alt">
    <h2 class="landing-section-title">Built for the whole classroom</h2>
    <div class="landing-value-grid">
      <div class="landing-value-card glass-card">
        <strong>For students</strong>
        <p>Runnable lessons, an in-browser playground, exams, and downloadable certificates that carry your school's logo and signature.</p>
      </div>
      <div class="landing-value-card glass-card">
        <strong>For teachers</strong>
        <p>Author certification exams, grade attempts, and post announcements — no separate LMS to babysit.</p>
      </div>
      <div class="landing-value-card glass-card">
        <strong>For schools</strong>
        <p>Give registrars, deans, and teachers their own dashboards, with your school's branding on every certificate you issue.</p>
      </div>
    </div>
  </section>

  <section class="landing-section" id="pricing">
    <h2 class="landing-section-title">Simple pricing</h2>
    <p class="landing-section-sub">Free to start. Upgrade whenever you're ready — pay with GCash, PayPal, or card.</p>
    <?php if (array_filter($__landingPlans, fn($p) => (float) $p['price'] > 0)): ?>
      <div class="pricing-cycle-toggle" id="landingCycleToggle">
        <button type="button" class="active" data-cycle="month">Monthly</button>
        <button type="button" data-cycle="year">Yearly<span class="pricing-cycle-save">save more</span></button>
      </div>
    <?php endif; ?>

    <div class="pricing-grid">
      <?php foreach ($__landingPlans as $plan): $features = inkwell_plan_features($plan);
        $isFree = (float) $plan['price'] <= 0 && (empty($plan['price_yearly']) || (float) $plan['price_yearly'] <= 0);
        $yearlyPrice = inkwell_plan_price($plan, 'year');
        $unlocksExams = inkwell_plan_unlocks_exams($plan);
      ?>
        <div class="plan-card glass-card<?php echo !empty($plan['badge']) ? ' featured' : ''; ?>">
          <?php if (!empty($plan['badge'])): ?><div class="plan-badge"><?php echo htmlspecialchars($plan['badge']); ?></div><?php endif; ?>
          <h3><?php echo htmlspecialchars($plan['name']); ?></h3>
          <?php if ($plan['description']): ?><p class="plan-desc"><?php echo htmlspecialchars($plan['description']); ?></p><?php endif; ?>
          <?php if ($isFree): ?>
            <div class="plan-price"><span class="plan-price-amount">Free</span></div>
            <p class="plan-locked-note">Lessons &amp; community notes only — certification needs a paid plan.</p>
          <?php else: ?>
            <div class="plan-price plan-price-monthly">
              <span class="plan-price-amount">₱<?php echo number_format((float) $plan['price'], 2); ?></span>
              <span class="plan-price-period">/ month</span>
            </div>
            <div class="plan-price plan-price-yearly" style="display:none;">
              <span class="plan-price-amount">₱<?php echo number_format($yearlyPrice, 2); ?></span>
              <span class="plan-price-period">/ year</span>
            </div>
            <?php if ($unlocksExams): ?><p class="plan-locked-note" style="color:var(--pine);border-style:solid;">Unlocks certification exams &amp; certificates ✅</p><?php endif; ?>
          <?php endif; ?>
          <?php if ($features): ?>
            <ul class="plan-features">
              <?php foreach ($features as $f): ?><li><?php echo htmlspecialchars($f); ?></li><?php endforeach; ?>
            </ul>
          <?php endif; ?>
          <?php if ($plan['audience'] === 'school'): ?>
            <?php if ($isFree): ?>
              <p class="plan-locked-note">Creating a school always needs a paid plan — this one's for reference only.</p>
            <?php else: ?>
              <a class="btn primary" href="/create-school.php?plan_id=<?php echo (int) $plan['id']; ?>">Create your school</a>
            <?php endif; ?>
          <?php else: ?>
            <a class="btn primary" href="/register.php">Get started</a>
            <?php if ($plan['audience'] === 'both' && !$isFree): ?>
              <a class="btn plan-alt-cta" href="/create-school.php?plan_id=<?php echo (int) $plan['id']; ?>">…or create a school on this plan</a>
            <?php endif; ?>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
      <?php if (!$__landingPlans): ?><p class="admin-sub">Pricing is being set up — check back soon.</p><?php endif; ?>
    </div>

    <script>
    (function () {
      var toggle = document.getElementById('landingCycleToggle');
      if (!toggle) return;
      toggle.querySelectorAll('button').forEach(function (b) {
        b.addEventListener('click', function () {
          var cycle = b.dataset.cycle;
          toggle.querySelectorAll('button').forEach(function (x) { x.classList.toggle('active', x === b); });
          document.querySelectorAll('.plan-price-monthly').forEach(function (el) { el.style.display = cycle === 'month' ? 'flex' : 'none'; });
          document.querySelectorAll('.plan-price-yearly').forEach(function (el) { el.style.display = cycle === 'year' ? 'flex' : 'none'; });
        });
      });
    })();
    </script>

    <?php if ($__landingMethods): ?>
      <div class="landing-payment-strip">
        <span class="landing-payment-strip-label">Pay with</span>
        <?php foreach ($__landingMethods as $m): ?>
          <span class="landing-payment-chip"><?php echo htmlspecialchars($m['label']); ?></span>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <section class="landing-cta-final">
    <h2>Ready to start writing code?</h2>
    <a class="btn primary" href="/register.php">Create your free account</a>
  </section>

</main>
