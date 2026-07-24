<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/billing.php';
$me = inkwell_require_login();

$notice = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'subscribe') {
  $cycle = ($_POST['billing_cycle'] ?? 'month') === 'year' ? 'year' : 'month';
  $result = inkwell_submit_payment(
    $me['id'],
    (int) ($_POST['plan_id'] ?? 0),
    (int) ($_POST['payment_method_id'] ?? 0),
    $_POST['reference_no'] ?? '',
    'proof_image',
    $cycle,
    $_POST['sender_number'] ?? '',
    $_POST['payment_date'] ?? ''
  );
  if (!$result['ok']) {
    inkwell_flash_set('error', $result['error']);
  } elseif (!empty($result['free'])) {
    inkwell_flash_set('notice', 'Free plan activated — enjoy!');
  } elseif (!empty($result['instant'])) {
    inkwell_flash_set('notice', '🎉 Payment received — your plan is active now. Enjoy!');
  } else {
    inkwell_flash_set('notice', 'Thanks! Your payment is pending review — we\'ll activate your plan once an admin approves it.');
  }
  header('Location: /my-billing.php');
  exit;
}

$flash = inkwell_flash_get();
$notice = $flash && $flash['type'] === 'notice' ? $flash['message'] : '';
$error = $flash && $flash['type'] === 'error' ? $flash['message'] : '';

$audienceFilter = in_array($me['role'], ['registrar', 'dean'], true) ? ['school', 'both'] : ['student', 'both'];
if ($me['role'] === 'admin') $audienceFilter = ['student', 'school', 'both'];
$allPlans = inkwell_list_plans(true);
$plans = array_values(array_filter($allPlans, fn($p) => in_array($p['audience'], $audienceFilter, true)));
$methods = inkwell_list_payment_methods(true);
$mySubmissions = inkwell_list_user_payment_submissions($me['id']);
$currentPlan = !empty($me['plan_id']) ? inkwell_get_plan($me['plan_id']) : null;

$pageTitle = 'My Billing';
include __DIR__ . '/includes/header.php';
?>
<main>
  <div class="billing-page">
    <div class="billing-header">
      <h1>My billing</h1>
      <p class="admin-sub">Pick a plan, pay using GCash, PayPal, or card, then upload your proof of payment. Methods marked <strong>⚡ Instant activation</strong> unlock your plan the moment you submit — everything else is reviewed by an admin, usually within a day.</p>
    </div>

    <?php if ($notice): ?><div class="exam-result pass"><?php echo htmlspecialchars($notice); ?></div><?php endif; ?>
    <?php if ($error): ?><div class="exam-result fail"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

    <section class="admin-card glass-card">
      <h2>Current plan</h2>
      <?php if ($currentPlan): ?>
        <p class="admin-sub">
          <strong><?php echo htmlspecialchars($currentPlan['name']); ?></strong>
          — status: <span class="payment-status-badge status-<?php echo htmlspecialchars($me['plan_status']); ?>"><?php echo htmlspecialchars(ucfirst($me['plan_status'])); ?></span>
          <?php if (!empty($me['plan_expires_at']) && $me['plan_status'] === 'active'): ?>
            (renews/expires <?php echo date('M j, Y', strtotime($me['plan_expires_at'])); ?>)
          <?php endif; ?>
          — <?php echo inkwell_user_has_exam_access($me) ? 'certification exams unlocked ✅' : 'certification exams locked 🔒'; ?>
        </p>
      <?php else: ?>
        <p class="admin-sub">You're not on a plan yet — you can still browse lessons and community notes for free. Pick a plan below to unlock certification exams and certificates.</p>
      <?php endif; ?>
      <?php $__notice = inkwell_renewal_notice($me); ?>
      <?php if ($__notice): ?>
        <p class="plan-locked-note"><?php echo htmlspecialchars($__notice['message']); ?></p>
      <?php endif; ?>
    </section>

    <?php if (array_filter($plans, fn($p) => (float) $p['price'] > 0)): ?>
      <div class="pricing-cycle-toggle" id="cycleToggle">
        <button type="button" class="active" data-cycle="month">Monthly</button>
        <button type="button" data-cycle="year">Yearly<span class="pricing-cycle-save">save more</span></button>
      </div>
    <?php endif; ?>

    <section class="pricing-grid" id="plans">
      <?php foreach ($plans as $plan): $features = inkwell_plan_features($plan);
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
            <p class="plan-locked-note">Lessons &amp; community notes only — certification exams need a paid plan.</p>
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
          <button type="button" class="btn primary plan-choose-btn"
            data-plan-id="<?php echo (int) $plan['id']; ?>"
            data-plan-name="<?php echo htmlspecialchars($plan['name']); ?>"
            data-plan-price-month="<?php echo (float) $plan['price']; ?>"
            data-plan-price-year="<?php echo $yearlyPrice; ?>">
            <?php echo (int) $plan['id'] === (int) ($me['plan_id'] ?? 0) ? 'Renew / change' : 'Choose this plan'; ?>
          </button>
        </div>
      <?php endforeach; ?>
      <?php if (!$plans): ?><p class="admin-sub">No plans are available right now.</p><?php endif; ?>
    </section>

    <section class="admin-card glass-card" id="checkoutSection" style="display:none;">
      <h2>Checkout — <span id="checkoutPlanName"></span></h2>
      <form method="post" action="/my-billing.php" enctype="multipart/form-data" class="admin-form" id="checkoutForm">
        <input type="hidden" name="action" value="subscribe">
        <input type="hidden" name="plan_id" id="checkoutPlanId" value="">
        <input type="hidden" name="billing_cycle" id="checkoutCycle" value="month">

        <div id="paidCheckoutFields">
          <p class="admin-sub" id="checkoutCycleSummary"></p>
          <label>Payment method</label>
          <div class="payment-method-tiles">
            <?php
            $__methodIcons = ['gcash' => '💚', 'paypal' => '🅿️', 'card' => '💳', 'bank' => '🏦', 'other' => '✨'];
            foreach ($methods as $m):
              $__icon = $__methodIcons[$m['type']] ?? '✨';
            ?>
              <label class="payment-method-tile">
                <input type="radio" name="payment_method_id" value="<?php echo (int) $m['id']; ?>" class="payment-method-radio" data-target="pm-details-<?php echo (int) $m['id']; ?>" data-type="<?php echo htmlspecialchars($m['type']); ?>">
                <span class="payment-method-tile-check">✓</span>
                <span class="payment-method-tile-icon"><?php echo $__icon; ?></span>
                <span class="payment-method-tile-label"><?php echo htmlspecialchars($m['label']); ?></span>
                <?php if (!empty($m['auto_activate'])): ?><span class="payment-method-instant-badge">⚡ Instant</span><?php endif; ?>
              </label>
            <?php endforeach; ?>
            <?php if (!$methods): ?><p class="admin-sub">No payment methods configured yet — contact the admin.</p><?php endif; ?>
          </div>

          <?php foreach ($methods as $m): ?>
            <div class="payment-method-details" id="pm-details-<?php echo (int) $m['id']; ?>" style="display:none;">
              <?php if ($m['type'] === 'card'): ?>
                <div class="payment-method-cardtypes">
                  <span class="payment-method-cardtypes-label">ⓘ Accepted card types</span>
                  <span class="card-brand card-brand-visa">VISA</span>
                  <span class="card-brand card-brand-mc">Mastercard</span>
                  <span class="card-brand card-brand-disc">Discover</span>
                </div>
              <?php endif; ?>
              <h3 class="payment-method-details-title"><?php echo htmlspecialchars($m['label']); ?> details</h3>
              <div class="payment-method-details-body">
                <?php if (!empty($m['qr_image'])): ?>
                  <img class="payment-method-qr-large" src="/assets/uploads/<?php echo htmlspecialchars($m['qr_image']); ?>" alt="<?php echo htmlspecialchars($m['label']); ?> QR code">
                <?php endif; ?>
                <?php if ($m['account_name'] || $m['account_number']): ?>
                  <p class="payment-method-account"><?php echo htmlspecialchars(trim(($m['account_name'] ?? '') . ' · ' . ($m['account_number'] ?? ''), ' ·')); ?></p>
                <?php endif; ?>
                <?php if ($m['instructions']): ?><p class="payment-method-instructions"><?php echo htmlspecialchars($m['instructions']); ?></p><?php endif; ?>

                <?php if ($m['type'] === 'card'): ?>
                  <p class="payment-method-instructions">🔒 For your security, never enter your full card number, expiration date, or CVV anywhere on this form — we only need the transaction reference and a receipt screenshot below to verify your payment.</p>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>

          <div id="senderFieldsGroup" style="display:none;">
            <label for="sender_number">Sender's GCash mobile number</label>
            <div class="icon-input-group">
              <span class="icon-input-group-icon">📱</span>
              <input type="text" id="sender_number" name="sender_number" maxlength="30" placeholder="e.g. 09171234567" inputmode="numeric" autocomplete="off">
            </div>

            <label for="payment_date" style="margin-top:10px;">Date the payment was made</label>
            <div class="icon-input-group">
              <span class="icon-input-group-icon">📅</span>
              <input type="date" id="payment_date" name="payment_date" max="<?php echo date('Y-m-d'); ?>">
            </div>
            <p class="admin-sub" style="margin-top:4px;">We check the sender number, date, and reference number against previous submissions to catch a receipt being reused — you get up to 2 mismatched attempts per day.</p>
          </div>

          <label for="reference_no" id="referenceLabel">Reference number (from GCash/PayPal receipt)</label>
          <div class="icon-input-group">
            <span class="icon-input-group-icon" id="referenceIcon">🧾</span>
            <input type="text" id="reference_no" name="reference_no" maxlength="150" placeholder="e.g. 0912345678901">
          </div>

          <label for="proof_image" id="proofLabel" style="margin-top:10px;">Upload proof of payment (screenshot, PNG/JPG/WEBP under 2MB)</label>
          <div class="icon-input-group">
            <span class="icon-input-group-icon">📎</span>
            <input type="file" id="proof_image" name="proof_image" accept="image/png,image/jpeg,image/webp">
          </div>
        </div>

        <button class="btn primary payment-submit-btn" type="submit" id="paymentSubmitBtn">Submit payment 🔒</button>
      </form>
    </section>

    <?php if ($mySubmissions): ?>
      <section class="admin-card glass-card">
        <h2>Payment history</h2>
        <div class="plan-admin-list">
          <?php foreach ($mySubmissions as $s): ?>
            <div class="plan-admin-row payment-history-row">
              <span class="plan-admin-name"><?php echo htmlspecialchars($s['plan_name']); ?></span>
              <span class="plan-admin-price">₱<?php echo number_format((float) $s['amount'], 2); ?> · <?php echo htmlspecialchars($s['method_label'] ?? '—'); ?><?php if (!empty($s['sender_number'])): ?> · from <?php echo htmlspecialchars($s['sender_number']); ?><?php endif; ?></span>
              <span class="payment-status-badge status-<?php echo htmlspecialchars($s['status']); ?>"><?php echo htmlspecialchars(ucfirst($s['status'])); ?></span>
              <span class="plan-admin-audience"><?php echo date('M j, Y', strtotime($s['created_at'])); ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endif; ?>
  </div>
</main>

<script>
(function () {
  var checkoutSection = document.getElementById('checkoutSection');
  var checkoutPlanId = document.getElementById('checkoutPlanId');
  var checkoutPlanName = document.getElementById('checkoutPlanName');
  var checkoutCycle = document.getElementById('checkoutCycle');
  var checkoutCycleSummary = document.getElementById('checkoutCycleSummary');
  var paidFields = document.getElementById('paidCheckoutFields');
  var radios = document.querySelectorAll('.payment-method-radio');
  var cycleToggle = document.getElementById('cycleToggle');
  var currentCycle = 'month';

  function applyCycle(cycle) {
    currentCycle = cycle;
    document.querySelectorAll('.plan-price-monthly').forEach(function (el) { el.style.display = cycle === 'month' ? 'flex' : 'none'; });
    document.querySelectorAll('.plan-price-yearly').forEach(function (el) { el.style.display = cycle === 'year' ? 'flex' : 'none'; });
    if (cycleToggle) {
      cycleToggle.querySelectorAll('button').forEach(function (b) { b.classList.toggle('active', b.dataset.cycle === cycle); });
    }
  }

  if (cycleToggle) {
    cycleToggle.querySelectorAll('button').forEach(function (b) {
      b.addEventListener('click', function () { applyCycle(b.dataset.cycle); });
    });
  }

  document.querySelectorAll('.plan-choose-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      checkoutPlanId.value = btn.dataset.planId;
      checkoutPlanName.textContent = btn.dataset.planName;
      checkoutCycle.value = currentCycle;
      var price = currentCycle === 'year' ? parseFloat(btn.dataset.planPriceYear) : parseFloat(btn.dataset.planPriceMonth);
      var isFree = price <= 0;
      paidFields.style.display = isFree ? 'none' : 'block';
      if (!isFree) {
        checkoutCycleSummary.textContent = (currentCycle === 'year' ? 'Yearly plan' : 'Monthly plan') + ' — ₱' + price.toFixed(2) + ' / ' + currentCycle;
      }
      checkoutSection.style.display = 'block';
      checkoutSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
  });

  var senderFieldsGroup = document.getElementById('senderFieldsGroup');
  var senderNumberInput = document.getElementById('sender_number');
  var paymentDateInput = document.getElementById('payment_date');

  radios.forEach(function (r) {
    r.addEventListener('change', function () {
      document.querySelectorAll('.payment-method-tile').forEach(function (opt) { opt.classList.remove('selected'); });
      r.closest('.payment-method-tile').classList.add('selected');
      document.querySelectorAll('.payment-method-details').forEach(function (d) { d.style.display = 'none'; });
      var target = document.getElementById(r.dataset.target);
      if (target) target.style.display = 'block';

      var referenceIcon = document.getElementById('referenceIcon');
      var referenceLabel = document.getElementById('referenceLabel');
      var proofLabel = document.getElementById('proofLabel');
      var isGcash = r.dataset.type === 'gcash';

      if (senderFieldsGroup) senderFieldsGroup.style.display = isGcash ? 'block' : 'none';
      if (senderNumberInput) senderNumberInput.required = isGcash;
      if (paymentDateInput) paymentDateInput.required = isGcash;

      if (r.dataset.type === 'card') {
        if (referenceIcon) referenceIcon.textContent = '💳';
        if (referenceLabel) referenceLabel.textContent = 'Transaction / reference number';
        if (proofLabel) proofLabel.textContent = 'Upload payment receipt (screenshot, PNG/JPG/WEBP under 2MB)';
      } else {
        if (referenceIcon) referenceIcon.textContent = '🧾';
        if (referenceLabel) referenceLabel.textContent = 'Reference number (from GCash/PayPal receipt)';
        if (proofLabel) proofLabel.textContent = 'Upload proof of payment (screenshot, PNG/JPG/WEBP under 2MB)';
      }
    });
  });

  // 7-second cooldown on the submit button — mirrors the server-side
  // cooldown in inkwell_check_payment_rate_limit(), mainly to stop
  // accidental double-clicks/double-submits rather than as the real guard.
  var checkoutForm = document.getElementById('checkoutForm');
  var submitBtn = document.getElementById('paymentSubmitBtn');
  if (checkoutForm && submitBtn) {
    checkoutForm.addEventListener('submit', function () {
      if (submitBtn.disabled) return;
      var seconds = 7;
      var originalText = submitBtn.textContent;
      submitBtn.disabled = true;
      submitBtn.textContent = 'Submitting… please wait (' + seconds + 's)';
      var timer = setInterval(function () {
        seconds -= 1;
        if (seconds <= 0) {
          clearInterval(timer);
          submitBtn.textContent = originalText;
          submitBtn.disabled = false;
          return;
        }
        submitBtn.textContent = 'Submitting… please wait (' + seconds + 's)';
      }, 1000);
    });
  }
})();
</script>
<script src="/assets/js/receipt-scan.js" defer></script>

<?php include __DIR__ . '/includes/footer.php'; ?>
