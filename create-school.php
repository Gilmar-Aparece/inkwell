<?php
// Inside/dashboard page — suppress the marketing topbar (see includes/header.php).
$__hideTopbar = true;
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/schools.php';
require_once __DIR__ . '/includes/billing.php';

if (inkwell_current_user()) {
  header('Location: /index.php');
  exit;
}

$allPlans = inkwell_list_plans(true);
$plans = array_values(array_filter($allPlans, function ($p) {
  if (!in_array($p['audience'], ['school', 'both'], true)) return false;
  // Creating a school always requires a paid plan — free plans are
  // excluded here even if they're otherwise available to students.
  $isFree = (float) $p['price'] <= 0 && (empty($p['price_yearly']) || (float) $p['price_yearly'] <= 0);
  return !$isFree;
}));
$methods = inkwell_list_payment_methods(true);

$error = '';
$created = null;

$schoolName = '';
$name = '';
$email = '';
$idNumber = '';
$course = '';
$requestedPlanId = (int) ($_GET['plan_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $schoolName = trim($_POST['new_school_name'] ?? '');
  $name = trim($_POST['name'] ?? '');
  $email = trim($_POST['email'] ?? '');
  $password = $_POST['password'] ?? '';
  $confirm = $_POST['confirm_password'] ?? '';
  $idNumber = trim($_POST['id_number'] ?? '');
  $course = trim($_POST['course'] ?? '');
  $planId = (int) ($_POST['plan_id'] ?? 0);
  $methodId = (int) ($_POST['payment_method_id'] ?? 0);
  $referenceNo = $_POST['reference_no'] ?? '';
  $senderNumber = $_POST['sender_number'] ?? '';
  $paymentDate = $_POST['payment_date'] ?? '';
  $cycle = ($_POST['billing_cycle'] ?? 'month') === 'year' ? 'year' : 'month';
  $requestedPlanId = $planId;

  if ($password !== $confirm) {
    $error = 'Passwords do not match.';
  } else {
    $result = inkwell_create_school_checkout($schoolName, $name, $email, $password, $idNumber, $course, $planId, $methodId, $referenceNo, 'proof_image', $cycle, $senderNumber, $paymentDate);
    if (!$result['ok']) {
      $error = $result['error'];
    } else {
      $created = $result;
      $createdUser = inkwell_get_user($result['user_id']);
      $createdSchool = inkwell_get_school($result['school_id']);
    }
  }
}

$pageTitle = 'Create your school';
include __DIR__ . '/includes/header.php';
$driveActive = 'account';
$driveCrumbs = [['label' => 'Home', 'href' => '/index.php'], ['label' => 'Create your school']];
include __DIR__ . '/includes/drive_shell_top.php';
?>
  <div class="admin-auth-card admin-auth-card-shell glass-card auth-card-wide<?php echo $created ? ' auth-card-success' : ''; ?>">
    <?php if (!$created): ?>
      <div class="auth-card-head">
        <span class="auth-card-icon" aria-hidden="true"><span class="nib-dot"></span></span>
        <h1>Create your school</h1>
        <p class="admin-sub">This sets up a brand-new school with you as its first Registrar. Every school plan requires payment — pick one and pay to get started. Your account activates instantly with an instant-activate payment method, otherwise as soon as an admin confirms your payment. Once you're in, you can create subjects and add Teacher/Dean accounts. Student, Teacher, and Dean signups don't go through here — students register at <a href="/register.php">/register.php</a>, and Teacher/Dean accounts are created by you afterward.</p>
      </div>

      <?php if ($error): ?><div class="exam-result fail"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

      <form method="post" action="/create-school.php" enctype="multipart/form-data" class="admin-form" id="createSchoolForm" novalidate>
        <h2 style="margin-top:0;">Your school</h2>
        <label for="new_school_name">School name</label>
        <input type="text" id="new_school_name" name="new_school_name" maxlength="150" required placeholder="e.g. Dapitan City College" value="<?php echo htmlspecialchars($schoolName); ?>">

        <h2>Your details (first Registrar)</h2>
        <div class="form-grid-2">
          <div>
            <label for="name">Full name</label>
            <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($name); ?>" maxlength="100" required>
          </div>
          <div>
            <label for="email">Email</label>
            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" maxlength="150" required>
          </div>
        </div>
        <div class="form-grid-2">
          <div>
            <label for="id_number">Registrar ID</label>
            <input type="text" id="id_number" name="id_number" value="<?php echo htmlspecialchars($idNumber); ?>" maxlength="50" placeholder="e.g. REG-0012" required>
          </div>
          <div>
            <label for="course">Office / Department</label>
            <input type="text" id="course" name="course" maxlength="150" placeholder="e.g. Registrar's Office" value="<?php echo htmlspecialchars($course); ?>" required>
          </div>
        </div>
        <div class="form-grid-2">
          <div>
            <label for="password">Password</label>
            <input type="password" id="password" name="password" minlength="8" required>
          </div>
          <div>
            <label for="confirm_password">Confirm password</label>
            <input type="password" id="confirm_password" name="confirm_password" minlength="8" required>
          </div>
        </div>

        <h2>Choose a plan</h2>
        <?php if (array_filter($plans, fn($p) => (float) $p['price'] > 0)): ?>
          <div class="pricing-cycle-toggle" id="cycleToggle">
            <button type="button" class="active" data-cycle="month">Monthly</button>
            <button type="button" data-cycle="year">Yearly<span class="pricing-cycle-save">save more</span></button>
          </div>
        <?php endif; ?>

        <input type="hidden" name="plan_id" id="selectedPlanId" value="<?php echo (int) $requestedPlanId; ?>">
        <input type="hidden" name="billing_cycle" id="selectedCycle" value="month">

        <div class="pricing-grid" id="schoolPlansGrid">
          <?php foreach ($plans as $plan): $features = inkwell_plan_features($plan);
            $isFree = (float) $plan['price'] <= 0 && (empty($plan['price_yearly']) || (float) $plan['price_yearly'] <= 0);
            $yearlyPrice = inkwell_plan_price($plan, 'year');
          ?>
            <label class="plan-card glass-card plan-card-selectable<?php echo !empty($plan['badge']) ? ' featured' : ''; ?>" data-plan-price-month="<?php echo (float) $plan['price']; ?>" data-plan-price-year="<?php echo $yearlyPrice; ?>">
              <input type="radio" name="plan_radio" value="<?php echo (int) $plan['id']; ?>" class="plan-radio-input" style="position:absolute; opacity:0;" <?php echo (int) $plan['id'] === $requestedPlanId ? 'checked' : ''; ?>>
              <?php if (!empty($plan['badge'])): ?><div class="plan-badge"><?php echo htmlspecialchars($plan['badge']); ?></div><?php endif; ?>
              <h3><?php echo htmlspecialchars($plan['name']); ?></h3>
              <?php if ($plan['description']): ?><p class="plan-desc"><?php echo htmlspecialchars($plan['description']); ?></p><?php endif; ?>
              <?php if ($isFree): ?>
                <div class="plan-price"><span class="plan-price-amount">Free</span></div>
              <?php else: ?>
                <div class="plan-price plan-price-monthly">
                  <span class="plan-price-amount">₱<?php echo number_format((float) $plan['price'], 2); ?></span>
                  <span class="plan-price-period">/ month</span>
                </div>
                <div class="plan-price plan-price-yearly" style="display:none;">
                  <span class="plan-price-amount">₱<?php echo number_format($yearlyPrice, 2); ?></span>
                  <span class="plan-price-period">/ year</span>
                </div>
              <?php endif; ?>
              <?php if ($features): ?>
                <ul class="plan-features">
                  <?php foreach ($features as $f): ?><li><?php echo htmlspecialchars($f); ?></li><?php endforeach; ?>
                </ul>
              <?php endif; ?>
              <span class="btn plan-select-indicator">Select</span>
            </label>
          <?php endforeach; ?>
          <?php if (!$plans): ?><p class="admin-sub">No school plans are available right now — contact the admin.</p><?php endif; ?>
        </div>

        <div id="paidCheckoutFields" style="display:none;">
          <h2>Payment</h2>
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
              <h3 class="payment-method-details-title"><?php echo htmlspecialchars($m['label']); ?> details</h3>
              <div class="payment-method-details-body">
                <?php if (!empty($m['qr_image'])): ?>
                  <img class="payment-method-qr-large" src="/assets/uploads/<?php echo htmlspecialchars($m['qr_image']); ?>" alt="<?php echo htmlspecialchars($m['label']); ?> QR code">
                <?php endif; ?>
                <?php if ($m['account_name'] || $m['account_number']): ?>
                  <p class="payment-method-account"><?php echo htmlspecialchars(trim(($m['account_name'] ?? '') . ' · ' . ($m['account_number'] ?? ''), ' ·')); ?></p>
                <?php endif; ?>
                <?php if ($m['instructions']): ?><p class="payment-method-instructions"><?php echo htmlspecialchars($m['instructions']); ?></p><?php endif; ?>
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

        <button class="btn primary" type="submit" style="margin-top:16px;" id="paymentSubmitBtn">Create school &amp; continue 🔒</button>
      </form>
      <p class="admin-sub">Already have an account? <a href="/login.php">Log in</a></p>
    <?php else: ?>
      <div class="auth-success-icon" aria-hidden="true">✓</div>
      <h1>School created</h1>
      <p class="admin-sub">
        <?php if ($created['active']): ?>
          Welcome, <?php echo htmlspecialchars($createdUser['name']); ?>. <strong><?php echo htmlspecialchars($createdSchool['name']); ?></strong> is active and your Registrar account is ready — log in to create subjects and add Teacher/Dean accounts.
        <?php else: ?>
          Welcome, <?php echo htmlspecialchars($createdUser['name']); ?>. <strong><?php echo htmlspecialchars($createdSchool['name']); ?></strong> has been created and you can log in right away, but it stays locked until an admin confirms your payment — usually within a day.
        <?php endif; ?>
      </p>

      <div class="account-info-grid">
        <div class="account-info-row"><span>Account ID</span><strong>#<?php echo str_pad($createdUser['id'], 5, '0', STR_PAD_LEFT); ?></strong></div>
        <div class="account-info-row"><span>Full name</span><strong><?php echo htmlspecialchars($createdUser['name']); ?></strong></div>
        <div class="account-info-row"><span>Email</span><strong><?php echo htmlspecialchars($createdUser['email']); ?></strong></div>
        <div class="account-info-row"><span>School</span><strong><?php echo htmlspecialchars($createdSchool['name']); ?></strong></div>
        <div class="account-info-row"><span>Role</span><strong class="badge badge-registrar">Registrar</strong></div>
        <div class="account-info-row"><span>Status</span><strong class="badge badge-status-<?php echo $created['active'] ? 'active' : 'pending'; ?>"><?php echo $created['active'] ? 'Active' : 'Pending'; ?></strong></div>
      </div>

      <a href="/login.php?registered=1" class="btn primary auth-continue-btn">Continue to log in →</a>
    <?php endif; ?>
  </div>
<?php include __DIR__ . '/includes/drive_shell_bottom.php'; ?>
<script>
(function () {
  var cards = document.querySelectorAll('.plan-card-selectable');
  var selectedPlanId = document.getElementById('selectedPlanId');
  var selectedCycle = document.getElementById('selectedCycle');
  var paidFields = document.getElementById('paidCheckoutFields');
  var checkoutCycleSummary = document.getElementById('checkoutCycleSummary');
  var cycleToggle = document.getElementById('cycleToggle');
  var currentCycle = 'month';

  function applyCycle(cycle) {
    currentCycle = cycle;
    selectedCycle.value = cycle;
    document.querySelectorAll('.plan-price-monthly').forEach(function (el) { el.style.display = cycle === 'month' ? 'flex' : 'none'; });
    document.querySelectorAll('.plan-price-yearly').forEach(function (el) { el.style.display = cycle === 'year' ? 'flex' : 'none'; });
    if (cycleToggle) cycleToggle.querySelectorAll('button').forEach(function (b) { b.classList.toggle('active', b.dataset.cycle === cycle); });
    updatePaidVisibility();
  }

  function updatePaidVisibility() {
    var checked = document.querySelector('.plan-radio-input:checked');
    if (!checked) { paidFields.style.display = 'none'; return; }
    var card = checked.closest('.plan-card-selectable');
    var price = currentCycle === 'year' ? parseFloat(card.dataset.planPriceYear) : parseFloat(card.dataset.planPriceMonth);
    var isFree = !(price > 0);
    paidFields.style.display = isFree ? 'none' : 'block';
    if (!isFree) checkoutCycleSummary.textContent = (currentCycle === 'year' ? 'Yearly plan' : 'Monthly plan') + ' — ₱' + price.toFixed(2) + ' / ' + currentCycle;
  }

  if (cycleToggle) {
    cycleToggle.querySelectorAll('button').forEach(function (b) {
      b.addEventListener('click', function () { applyCycle(b.dataset.cycle); });
    });
  }

  cards.forEach(function (card) {
    card.addEventListener('click', function () {
      var radio = card.querySelector('.plan-radio-input');
      radio.checked = true;
      cards.forEach(function (c) { c.classList.toggle('selected', c === card); });
      selectedPlanId.value = radio.value;
      updatePaidVisibility();
    });
  });

  var preselected = document.querySelector('.plan-radio-input:checked');
  if (preselected) {
    preselected.closest('.plan-card-selectable').classList.add('selected');
    selectedPlanId.value = preselected.value;
  }
  updatePaidVisibility();

  var senderFieldsGroup = document.getElementById('senderFieldsGroup');
  var senderNumberInput = document.getElementById('sender_number');
  var paymentDateInput = document.getElementById('payment_date');

  document.querySelectorAll('.payment-method-radio').forEach(function (r) {
    r.addEventListener('change', function () {
      document.querySelectorAll('.payment-method-tile').forEach(function (opt) { opt.classList.remove('selected'); });
      r.closest('.payment-method-tile').classList.add('selected');
      document.querySelectorAll('.payment-method-details').forEach(function (d) { d.style.display = 'none'; });
      var target = document.getElementById(r.dataset.target);
      if (target) target.style.display = 'block';

      var isGcash = r.dataset.type === 'gcash';
      if (senderFieldsGroup) senderFieldsGroup.style.display = isGcash ? 'block' : 'none';
      if (senderNumberInput) senderNumberInput.required = isGcash;
      if (paymentDateInput) paymentDateInput.required = isGcash;
    });
  });

  // 7-second cooldown on the submit button — mirrors the server-side
  // cooldown in inkwell_check_payment_rate_limit().
  var createSchoolForm = document.getElementById('createSchoolForm');
  var submitBtn = document.getElementById('paymentSubmitBtn');
  if (createSchoolForm && submitBtn) {
    createSchoolForm.addEventListener('submit', function () {
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
