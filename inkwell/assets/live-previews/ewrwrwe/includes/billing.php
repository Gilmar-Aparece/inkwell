<?php
/**
 * Pricing plans + admin-managed payment methods (GCash / PayPal / Card /
 * Bank / other) + user payment submissions (proof-of-payment, reviewed
 * manually by an admin). InfinityFree's free tier can't run a real
 * payment gateway API, so this is a "submit proof, admin approves" flow
 * instead of live card processing.
 *
 * Requires includes/db.php and includes/store.php (for INKWELL_UPLOADS_DIR
 * and inkwell_handle_logo_upload()) to already be loaded by the caller.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/store.php';
require_once __DIR__ . '/schools.php'; // reuses inkwell_handle_logo_upload() / inkwell_delete_upload()
require_once __DIR__ . '/auth.php'; // reuses inkwell_set_user_status() for the school-checkout flow below

/**
 * Self-healing schema bump (same pattern as inkwell_ensure_certificate_columns()
 * in exams_db.php) — adds yearly pricing + the "does this plan unlock
 * certification exams" toggle to `plans`, and a billing_cycle column to
 * `payment_submissions`, if they aren't there yet. Safe to call on every
 * request; each ALTER only runs once.
 */
function inkwell_ensure_billing_columns() {
  static $checked = false;
  if ($checked) return;
  $checked = true;

  $pdo = inkwell_db();

  $planCols = [
    'price_yearly'       => "ALTER TABLE plans ADD COLUMN price_yearly DECIMAL(10,2) DEFAULT NULL",
    'unlocks_exams'      => "ALTER TABLE plans ADD COLUMN unlocks_exams TINYINT(1) NOT NULL DEFAULT 1",
    // Same idea as unlocks_exams, but for the lesson tracks themselves — a
    // free/guest visitor only ever sees the first N lessons of each track
    // (see inkwell_free_lessons_per_track() in store.php); the rest need a
    // plan with this flag on. Kept as its own column (rather than reusing
    // unlocks_exams) since a plan could plausibly unlock one without the
    // other (e.g. a cheap "certs only" tier).
    'unlocks_all_lessons' => "ALTER TABLE plans ADD COLUMN unlocks_all_lessons TINYINT(1) NOT NULL DEFAULT 1",
  ];
  try {
    $existing = $pdo->query('SHOW COLUMNS FROM plans')->fetchAll(PDO::FETCH_COLUMN);
  } catch (Exception $e) {
    return; // plans table itself missing — nothing to fix here
  }
  foreach ($planCols as $col => $sql) {
    if (!in_array($col, $existing, true)) {
      try { $pdo->exec($sql); } catch (Exception $e) { /* ignore race / already-there */ }
    }
  }
  // Free (price = 0, no yearly price set) plans default to NOT unlocking exams
  // or the full lesson library — only the paid tiers do, out of the box.
  try { $pdo->exec("UPDATE plans SET unlocks_exams = 0 WHERE price <= 0 AND (price_yearly IS NULL OR price_yearly <= 0)"); } catch (Exception $e) {}
  try { $pdo->exec("UPDATE plans SET unlocks_all_lessons = 0 WHERE price <= 0 AND (price_yearly IS NULL OR price_yearly <= 0)"); } catch (Exception $e) {}

  try {
    $subCols = $pdo->query('SHOW COLUMNS FROM payment_submissions')->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('billing_cycle', $subCols, true)) {
      $pdo->exec("ALTER TABLE payment_submissions ADD COLUMN billing_cycle ENUM('month','year') NOT NULL DEFAULT 'month'");
    }
    // sha256 of the uploaded proof-of-payment image's contents (not the
    // filename, which is always randomized) — lets us catch the same
    // screenshot being reused across submissions. See inkwell_find_duplicate_payment().
    if (!in_array('proof_hash', $subCols, true)) {
      $pdo->exec("ALTER TABLE payment_submissions ADD COLUMN proof_hash CHAR(64) DEFAULT NULL");
    }
    // Sender's GCash (or other e-wallet) mobile number and the date the
    // payment was made, as typed by the user from their receipt — a second,
    // independent duplicate-detection signal alongside reference_no/proof_hash.
    // See inkwell_find_duplicate_payment().
    if (!in_array('sender_number', $subCols, true)) {
      $pdo->exec("ALTER TABLE payment_submissions ADD COLUMN sender_number VARCHAR(30) DEFAULT NULL");
    }
    if (!in_array('payment_date', $subCols, true)) {
      $pdo->exec("ALTER TABLE payment_submissions ADD COLUMN payment_date DATE DEFAULT NULL");
    }
  } catch (Exception $e) {}

  // Instant-activation toggle per payment method — when on, a submission
  // to that method skips the "pending admin review" step and activates
  // the plan right away (e.g. an admin-owned GCash number/QR they trust
  // themselves to reconcile, instead of a card processor confirming it).
  try {
    $methodCols = $pdo->query('SHOW COLUMNS FROM payment_methods')->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('auto_activate', $methodCols, true)) {
      $pdo->exec("ALTER TABLE payment_methods ADD COLUMN auto_activate TINYINT(1) NOT NULL DEFAULT 0");
    }
  } catch (Exception $e) {}

  // Rate-limiting for payment submissions: a short cooldown between attempts
  // (see INKWELL_PAYMENT_COOLDOWN_SECONDS) plus a daily cap on *failed*
  // (duplicate-flagged) attempts before the user has to wait until the next
  // day. See inkwell_check_payment_rate_limit() / inkwell_record_payment_attempt().
  try {
    $userCols = $pdo->query('SHOW COLUMNS FROM users')->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('last_payment_attempt_at', $userCols, true)) {
      $pdo->exec("ALTER TABLE users ADD COLUMN last_payment_attempt_at DATETIME DEFAULT NULL");
    }
    if (!in_array('payment_fail_count', $userCols, true)) {
      $pdo->exec("ALTER TABLE users ADD COLUMN payment_fail_count INT NOT NULL DEFAULT 0");
    }
    if (!in_array('payment_fail_date', $userCols, true)) {
      $pdo->exec("ALTER TABLE users ADD COLUMN payment_fail_date DATE DEFAULT NULL");
    }
  } catch (Exception $e) {}

  // Default GCash account: replace the placeholder number from the original
  // seed data (MIGRATION_ADD_pricing_payments.sql) with the real one, once.
  // Only touches rows that still have that exact placeholder, so an admin's
  // own edits are never overwritten.
  try {
    $pdo->exec("UPDATE payment_methods SET account_number = '09463478938', account_name = 'Gilmar'
                WHERE type = 'gcash' AND account_number = '09XX-XXX-XXXX'");
  } catch (Exception $e) {}
}

/** Minimum seconds a user must wait between payment-submission attempts. */
const INKWELL_PAYMENT_COOLDOWN_SECONDS = 7;

/** Max *failed* (duplicate-flagged) submission attempts allowed per calendar day before locking out until the next day. */
const INKWELL_PAYMENT_MAX_DAILY_FAILS = 2;

/* ---------------- Plans ---------------- */

function inkwell_list_plans($activeOnly = false) {
  inkwell_ensure_billing_columns();
  $pdo = inkwell_db();
  $sql = 'SELECT * FROM plans' . ($activeOnly ? ' WHERE is_active = 1' : '') . ' ORDER BY sort_order ASC, price ASC';
  return $pdo->query($sql)->fetchAll();
}

/** Price for a given cycle ('month'|'year'). Falls back to price*12 if no yearly price was set manually. */
function inkwell_plan_price($plan, $cycle = 'month') {
  if ($cycle === 'year') {
    if (isset($plan['price_yearly']) && $plan['price_yearly'] !== null && $plan['price_yearly'] !== '') {
      return (float) $plan['price_yearly'];
    }
    return (float) $plan['price'] * 12;
  }
  return (float) $plan['price'];
}

/** Whether being on this plan (once active) unlocks certification exams / certificates. */
function inkwell_plan_unlocks_exams($plan) {
  if (!$plan) return false;
  return !empty($plan['unlocks_exams']);
}

/** Whether being on this plan (once active) unlocks every lesson in every track, not just the free preview. */
function inkwell_plan_unlocks_all_lessons($plan) {
  if (!$plan) return false;
  return !empty($plan['unlocks_all_lessons']);
}

function inkwell_get_plan($id) {
  inkwell_ensure_billing_columns();
  $pdo = inkwell_db();
  $stmt = $pdo->prepare('SELECT * FROM plans WHERE id = ?');
  $stmt->execute([(int) $id]);
  return $stmt->fetch() ?: null;
}

function inkwell_plan_features($plan) {
  if (empty($plan['features'])) return [];
  return array_values(array_filter(array_map('trim', explode("\n", $plan['features']))));
}

function inkwell_create_plan($data) {
  inkwell_ensure_billing_columns();
  $name = trim($data['name'] ?? '');
  if ($name === '') return ['ok' => false, 'error' => 'Plan name is required.'];
  $price = (float) ($data['price'] ?? 0);
  $priceYearlyRaw = trim((string) ($data['price_yearly'] ?? ''));
  $priceYearly = $priceYearlyRaw === '' ? null : (float) $priceYearlyRaw;
  $unlocksExams = !empty($data['unlocks_exams']) ? 1 : 0;
  $unlocksAllLessons = !empty($data['unlocks_all_lessons']) ? 1 : 0;
  $audience = in_array($data['audience'] ?? '', ['student', 'school', 'both'], true) ? $data['audience'] : 'both';
  $period = trim($data['billing_period'] ?? 'month') ?: 'month';
  $description = trim($data['description'] ?? '');
  $features = trim($data['features'] ?? '');
  $badge = trim($data['badge'] ?? '');
  $sort = (int) ($data['sort_order'] ?? 0);

  $pdo = inkwell_db();
  $stmt = $pdo->prepare('INSERT INTO plans (name, audience, price, price_yearly, billing_period, description, features, badge, unlocks_exams, unlocks_all_lessons, is_active, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?)');
  $stmt->execute([$name, $audience, $price, $priceYearly, $period, $description ?: null, $features ?: null, $badge ?: null, $unlocksExams, $unlocksAllLessons, $sort]);
  return ['ok' => true, 'id' => (int) $pdo->lastInsertId()];
}

function inkwell_update_plan($id, $data) {
  inkwell_ensure_billing_columns();
  $name = trim($data['name'] ?? '');
  if ($name === '') return ['ok' => false, 'error' => 'Plan name is required.'];
  $price = (float) ($data['price'] ?? 0);
  $priceYearlyRaw = trim((string) ($data['price_yearly'] ?? ''));
  $priceYearly = $priceYearlyRaw === '' ? null : (float) $priceYearlyRaw;
  $unlocksExams = !empty($data['unlocks_exams']) ? 1 : 0;
  $unlocksAllLessons = !empty($data['unlocks_all_lessons']) ? 1 : 0;
  $audience = in_array($data['audience'] ?? '', ['student', 'school', 'both'], true) ? $data['audience'] : 'both';
  $period = trim($data['billing_period'] ?? 'month') ?: 'month';
  $description = trim($data['description'] ?? '');
  $features = trim($data['features'] ?? '');
  $badge = trim($data['badge'] ?? '');
  $sort = (int) ($data['sort_order'] ?? 0);

  $pdo = inkwell_db();
  $stmt = $pdo->prepare('UPDATE plans SET name=?, audience=?, price=?, price_yearly=?, billing_period=?, description=?, features=?, badge=?, unlocks_exams=?, unlocks_all_lessons=?, sort_order=? WHERE id=?');
  $stmt->execute([$name, $audience, $price, $priceYearly, $period, $description ?: null, $features ?: null, $badge ?: null, $unlocksExams, $unlocksAllLessons, $sort, (int) $id]);
  return ['ok' => true];
}

function inkwell_toggle_plan($id) {
  $pdo = inkwell_db();
  $stmt = $pdo->prepare('UPDATE plans SET is_active = NOT is_active WHERE id = ?');
  $stmt->execute([(int) $id]);
}

function inkwell_delete_plan($id) {
  $pdo = inkwell_db();
  $stmt = $pdo->prepare('DELETE FROM plans WHERE id = ?');
  $stmt->execute([(int) $id]);
}

/* ---------------- Payment methods ---------------- */

function inkwell_list_payment_methods($activeOnly = false) {
  $pdo = inkwell_db();
  $sql = 'SELECT * FROM payment_methods' . ($activeOnly ? ' WHERE is_active = 1' : '') . ' ORDER BY sort_order ASC, id ASC';
  return $pdo->query($sql)->fetchAll();
}

function inkwell_get_payment_method($id) {
  $pdo = inkwell_db();
  $stmt = $pdo->prepare('SELECT * FROM payment_methods WHERE id = ?');
  $stmt->execute([(int) $id]);
  return $stmt->fetch() ?: null;
}

function inkwell_create_payment_method($data) {
  inkwell_ensure_billing_columns();
  $label = trim($data['label'] ?? '');
  if ($label === '') return ['ok' => false, 'error' => 'A label is required (e.g. "GCash").'];
  $type = in_array($data['type'] ?? '', ['gcash', 'paypal', 'card', 'bank', 'other'], true) ? $data['type'] : 'other';
  $autoActivate = !empty($data['auto_activate']) ? 1 : 0;

  $qrFilename = null;
  if (!empty($_FILES['qr_image']['name'])) {
    $upload = inkwell_handle_logo_upload('qr_image');
    if (!$upload['ok']) return $upload;
    $qrFilename = $upload['filename'];
  }

  $pdo = inkwell_db();
  $stmt = $pdo->prepare('INSERT INTO payment_methods (type, label, account_name, account_number, instructions, qr_image, auto_activate, is_active, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?)');
  $stmt->execute([
    $type, $label,
    trim($data['account_name'] ?? '') ?: null,
    trim($data['account_number'] ?? '') ?: null,
    trim($data['instructions'] ?? '') ?: null,
    $qrFilename,
    $autoActivate,
    (int) ($data['sort_order'] ?? 0),
  ]);
  return ['ok' => true, 'id' => (int) $pdo->lastInsertId()];
}

function inkwell_update_payment_method($id, $data) {
  inkwell_ensure_billing_columns();
  $label = trim($data['label'] ?? '');
  if ($label === '') return ['ok' => false, 'error' => 'A label is required (e.g. "GCash").'];
  $type = in_array($data['type'] ?? '', ['gcash', 'paypal', 'card', 'bank', 'other'], true) ? $data['type'] : 'other';
  $autoActivate = !empty($data['auto_activate']) ? 1 : 0;

  $existing = inkwell_get_payment_method($id);
  $qrFilename = $existing['qr_image'] ?? null;
  if (!empty($_FILES['qr_image']['name'])) {
    $upload = inkwell_handle_logo_upload('qr_image');
    if (!$upload['ok']) return $upload;
    if ($qrFilename) inkwell_delete_upload($qrFilename);
    $qrFilename = $upload['filename'];
  }

  $pdo = inkwell_db();
  $stmt = $pdo->prepare('UPDATE payment_methods SET type=?, label=?, account_name=?, account_number=?, instructions=?, qr_image=?, auto_activate=?, sort_order=? WHERE id=?');
  $stmt->execute([
    $type, $label,
    trim($data['account_name'] ?? '') ?: null,
    trim($data['account_number'] ?? '') ?: null,
    trim($data['instructions'] ?? '') ?: null,
    $qrFilename,
    $autoActivate,
    (int) ($data['sort_order'] ?? 0),
    (int) $id,
  ]);
  return ['ok' => true];
}

function inkwell_toggle_payment_method($id) {
  $pdo = inkwell_db();
  $stmt = $pdo->prepare('UPDATE payment_methods SET is_active = NOT is_active WHERE id = ?');
  $stmt->execute([(int) $id]);
}

function inkwell_delete_payment_method($id) {
  $existing = inkwell_get_payment_method($id);
  if ($existing && !empty($existing['qr_image'])) inkwell_delete_upload($existing['qr_image']);
  $pdo = inkwell_db();
  $stmt = $pdo->prepare('DELETE FROM payment_methods WHERE id = ?');
  $stmt->execute([(int) $id]);
}

/* ---------------- Payment submissions (proof of payment) ---------------- */

/** Shared activation logic: sets the user's plan active and computes the expiry from the billing cycle. */
function inkwell_activate_user_plan($userId, $planId, $cycle = 'month') {
  $cycle = $cycle === 'year' ? 'year' : 'month';
  $expires = $cycle === 'year'
    ? date('Y-m-d H:i:s', strtotime('+1 year'))
    : date('Y-m-d H:i:s', strtotime('+1 month'));
  $pdo = inkwell_db();
  $stmt = $pdo->prepare('UPDATE users SET plan_id = ?, plan_status = \'active\', plan_expires_at = ? WHERE id = ?');
  $stmt->execute([(int) $planId, $expires, (int) $userId]);
  return $expires;
}

/** sha256 of an already-saved proof-of-payment image's contents. Null if there's no file (e.g. reference-number-only submissions). */
function inkwell_payment_proof_hash($filename) {
  if (!$filename) return null;
  $path = rtrim(INKWELL_UPLOADS_DIR, '/') . '/' . $filename;
  if (!is_file($path)) return null;
  $hash = @hash_file('sha256', $path);
  return $hash ?: null;
}

/**
 * Looks for an existing submission reusing the same reference number,
 * the same sender-number + payment-date pair, and/or the exact same
 * proof-of-payment image (matched by file content, not filename — a
 * re-uploaded copy of the same screenshot still hashes identically).
 * This is the most common sign of one real receipt being claimed on more
 * than one plan purchase or account.
 *
 * Only 'pending' and 'approved' submissions count — a 'rejected' one
 * already had its details judged invalid, so it shouldn't permanently
 * block a legitimate resubmission with the same details.
 * $excludeSubmissionId lets a re-review skip comparing a submission
 * against itself.
 *
 * Returns ['reference' => bool, 'sender_date' => bool, 'proof' => bool] —
 * any combination true when a conflict is found, all false when it's clear.
 */
function inkwell_find_duplicate_payment($referenceNo, $proofHash, $excludeSubmissionId = null, $senderNumber = '', $paymentDate = '') {
  $referenceNo = trim((string) $referenceNo);
  $senderNumber = trim((string) $senderNumber);
  $paymentDate = trim((string) $paymentDate);
  $pdo = inkwell_db();
  $result = ['reference' => false, 'sender_date' => false, 'proof' => false];

  if ($referenceNo !== '') {
    $sql = "SELECT COUNT(*) FROM payment_submissions WHERE status IN ('pending','approved') AND reference_no = ?";
    $params = [$referenceNo];
    if ($excludeSubmissionId) { $sql .= ' AND id != ?'; $params[] = (int) $excludeSubmissionId; }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $result['reference'] = (int) $stmt->fetchColumn() > 0;
  }

  // Same sender mobile number *and* the same payment date already used on
  // another live submission — someone re-uploading the same GCash receipt's
  // details (possibly with a different reference number typed in) is still
  // caught by this even if the reference-number check above misses it.
  if ($senderNumber !== '' && $paymentDate !== '') {
    $sql = "SELECT COUNT(*) FROM payment_submissions WHERE status IN ('pending','approved') AND sender_number = ? AND payment_date = ?";
    $params = [$senderNumber, $paymentDate];
    if ($excludeSubmissionId) { $sql .= ' AND id != ?'; $params[] = (int) $excludeSubmissionId; }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $result['sender_date'] = (int) $stmt->fetchColumn() > 0;
  }

  if ($proofHash) {
    $sql = "SELECT COUNT(*) FROM payment_submissions WHERE status IN ('pending','approved') AND proof_hash = ?";
    $params = [$proofHash];
    if ($excludeSubmissionId) { $sql .= ' AND id != ?'; $params[] = (int) $excludeSubmissionId; }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $result['proof'] = (int) $stmt->fetchColumn() > 0;
  }

  return $result;
}

/**
 * Anti-spam gate checked at the very start of every payment-submission
 * attempt, before any file upload or duplicate check runs:
 *  1. A short cooldown (INKWELL_PAYMENT_COOLDOWN_SECONDS) between any two
 *     attempts, so a user can't hammer the submit button.
 *  2. A daily cap (INKWELL_PAYMENT_MAX_DAILY_FAILS) on *failed*
 *     (duplicate-flagged) attempts — once reached, the user is locked out
 *     of submitting again until the next calendar day. Successful
 *     submissions never count toward this cap.
 *
 * Returns ['ok' => true] when the user may proceed, or ['ok' => false,
 * 'error' => ...] with a message to show them.
 */
function inkwell_check_payment_rate_limit($userId) {
  inkwell_ensure_billing_columns();
  $pdo = inkwell_db();
  $stmt = $pdo->prepare('SELECT last_payment_attempt_at, payment_fail_count, payment_fail_date FROM users WHERE id = ?');
  $stmt->execute([(int) $userId]);
  $row = $stmt->fetch();
  if (!$row) return ['ok' => true]; // shouldn't happen, but don't block on a lookup miss

  if (!empty($row['last_payment_attempt_at'])) {
    $elapsed = time() - strtotime($row['last_payment_attempt_at']);
    if ($elapsed >= 0 && $elapsed < INKWELL_PAYMENT_COOLDOWN_SECONDS) {
      $wait = INKWELL_PAYMENT_COOLDOWN_SECONDS - $elapsed;
      return ['ok' => false, 'error' => "Please wait {$wait} more second" . ($wait === 1 ? '' : 's') . " before trying again."];
    }
  }

  $today = date('Y-m-d');
  if (($row['payment_fail_date'] ?? null) === $today && (int) $row['payment_fail_count'] >= INKWELL_PAYMENT_MAX_DAILY_FAILS) {
    return ['ok' => false, 'error' => "You've reached today's limit of " . INKWELL_PAYMENT_MAX_DAILY_FAILS . " failed payment attempts. Please try again tomorrow, or contact the admin if you believe this is a mistake."];
  }

  return ['ok' => true];
}

/**
 * Records the outcome of a payment-submission attempt for rate-limiting
 * purposes. Always stamps last_payment_attempt_at (starts the next
 * cooldown window). When $failed is true, bumps payment_fail_count —
 * resetting it to 1 first if the last failure was on a previous day, since
 * the daily cap only looks at today's count.
 */
function inkwell_record_payment_attempt($userId, $failed) {
  $pdo = inkwell_db();
  $now = date('Y-m-d H:i:s');
  if (!$failed) {
    $stmt = $pdo->prepare('UPDATE users SET last_payment_attempt_at = ? WHERE id = ?');
    $stmt->execute([$now, (int) $userId]);
    return;
  }

  $today = date('Y-m-d');
  $stmt = $pdo->prepare('SELECT payment_fail_count, payment_fail_date FROM users WHERE id = ?');
  $stmt->execute([(int) $userId]);
  $row = $stmt->fetch();
  $count = ($row && ($row['payment_fail_date'] ?? null) === $today) ? (int) $row['payment_fail_count'] + 1 : 1;

  $stmt = $pdo->prepare('UPDATE users SET last_payment_attempt_at = ?, payment_fail_count = ?, payment_fail_date = ? WHERE id = ?');
  $stmt->execute([$now, $count, $today, (int) $userId]);
}

/**
 * User submits proof of payment for a plan. Sets their plan_status to
 * 'pending' until an admin reviews it. $cycle is 'month' or 'year' — it
 * decides both the charged amount (inkwell_plan_price()) and, once
 * approved, how far out plan_expires_at is set.
 *
 * If the chosen payment method has auto_activate on (an admin-owned
 * GCash number/QR they've flagged as self-reconciled, for example), the
 * plan activates immediately instead of waiting in the pending queue —
 * the submission is still logged as 'approved' so it shows up in
 * Payments/the billing dashboard for the record.
 *
 * Rejects the submission outright if the reference number, the
 * sender-number + payment-date pair, or the proof image content exactly
 * matches another still-live submission — see inkwell_find_duplicate_payment().
 * For GCash (and other e-wallet-style) methods, the sender number and
 * payment date are required fields, not optional.
 *
 * Every attempt (including ones rejected below) first passes through
 * inkwell_check_payment_rate_limit(): a short cooldown between attempts,
 * and a daily cap on failed/duplicate attempts before the user has to
 * wait until the next day — see that function for details.
 */
function inkwell_submit_payment($userId, $planId, $methodId, $referenceNo, $proofFileField = 'proof_image', $cycle = 'month', $senderNumber = '', $paymentDate = '') {
  inkwell_ensure_billing_columns();

  $limit = inkwell_check_payment_rate_limit($userId);
  if (!$limit['ok']) return $limit;

  $plan = inkwell_get_plan($planId);
  if (!$plan) return ['ok' => false, 'error' => 'That plan no longer exists.'];
  $cycle = $cycle === 'year' ? 'year' : 'month';
  $amount = inkwell_plan_price($plan, $cycle);
  $referenceNo = trim($referenceNo);
  $senderNumber = trim($senderNumber);
  $paymentDate = trim($paymentDate);
  // Normalize/validate the date so a garbage string can't slip into the column.
  if ($paymentDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $paymentDate)) $paymentDate = '';

  // Free plans don't need proof of payment — activate immediately, before
  // touching uploads or the rate limiter's fail counter at all.
  if ($amount <= 0) {
    $pdo = inkwell_db();
    $stmt = $pdo->prepare('UPDATE users SET plan_id = ?, plan_status = ?, plan_expires_at = NULL WHERE id = ?');
    $stmt->execute([(int) $planId, 'active', (int) $userId]);
    return ['ok' => true, 'free' => true];
  }

  if (!$methodId) return ['ok' => false, 'error' => 'Choose a payment method.'];
  $method = inkwell_get_payment_method($methodId);

  if ($referenceNo === '' && empty($_FILES[$proofFileField]['name'])) {
    return ['ok' => false, 'error' => 'Enter a reference number or upload a screenshot of your payment.'];
  }
  // GCash (and similarly, any e-wallet-style method) needs the sender's
  // mobile number and the date the payment was sent — these are what let
  // the sender-number+date duplicate check actually catch a reused receipt.
  if ($method && $method['type'] === 'gcash') {
    if ($senderNumber === '') return ['ok' => false, 'error' => 'Enter the GCash mobile number the payment was sent from.'];
    if ($paymentDate === '') return ['ok' => false, 'error' => 'Enter the date the payment was made.'];
  }

  $proofFilename = null;
  if (!empty($_FILES[$proofFileField]['name'])) {
    $upload = inkwell_handle_logo_upload($proofFileField);
    if (!$upload['ok']) return $upload;
    $proofFilename = $upload['filename'];
  }

  $proofHash = inkwell_payment_proof_hash($proofFilename);
  $dup = inkwell_find_duplicate_payment($referenceNo, $proofHash, null, $senderNumber, $paymentDate);
  if ($dup['reference'] || $dup['sender_date'] || $dup['proof']) {
    if ($proofFilename) inkwell_delete_upload($proofFilename); // don't leave an orphan upload behind
    inkwell_record_payment_attempt($userId, true); // counts toward the daily fail limit
    $reasons = [];
    if ($dup['reference']) $reasons[] = 'reference number';
    if ($dup['sender_date']) $reasons[] = 'sender number and date';
    if ($dup['proof']) $reasons[] = 'payment screenshot';
    $msg = 'That ' . implode(' and ', $reasons) . ' ' . (count($reasons) > 1 ? 'are' : 'is') . ' already tied to another payment submission.';
    return ['ok' => false, 'error' => $msg . ' Double-check the details, or contact the admin if you believe this is a mistake.'];
  }

  inkwell_record_payment_attempt($userId, false); // successful attempt — doesn't count toward the daily fail limit

  $instant = $method && !empty($method['auto_activate']);

  $pdo = inkwell_db();
  if ($instant) {
    $stmt = $pdo->prepare('INSERT INTO payment_submissions (user_id, plan_id, payment_method_id, amount, reference_no, sender_number, payment_date, proof_image, proof_hash, billing_cycle, status, admin_note, reviewed_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, \'approved\', ?, NOW())');
    $stmt->execute([(int) $userId, (int) $planId, (int) $methodId, $amount, $referenceNo ?: null, $senderNumber ?: null, $paymentDate ?: null, $proofFilename, $proofHash, $cycle, 'Auto-activated instantly']);
    inkwell_activate_user_plan($userId, $planId, $cycle);
    return ['ok' => true, 'id' => (int) $pdo->lastInsertId(), 'instant' => true];
  }

  $stmt = $pdo->prepare('INSERT INTO payment_submissions (user_id, plan_id, payment_method_id, amount, reference_no, sender_number, payment_date, proof_image, proof_hash, billing_cycle, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, \'pending\')');
  $stmt->execute([(int) $userId, (int) $planId, (int) $methodId, $amount, $referenceNo ?: null, $senderNumber ?: null, $paymentDate ?: null, $proofFilename, $proofHash, $cycle]);

  $stmt2 = $pdo->prepare('UPDATE users SET plan_id = ?, plan_status = \'pending\' WHERE id = ?');
  $stmt2->execute([(int) $planId, (int) $userId]);

  return ['ok' => true, 'id' => (int) $pdo->lastInsertId()];
}

/**
 * Public "create a school" checkout — replaces the old self-service
 * Registrar signup on /register.php. Creates a brand-new school and its
 * first Registrar account together, then runs that account through the
 * same proof-of-payment flow as inkwell_submit_payment().
 *
 * The account and school exist right away, but the account starts
 * 'pending' and only flips to 'active' once payment is confirmed — either
 * instantly (a free plan or an auto-activate payment method) or once an
 * admin approves the submission in /admin/payments.php (see
 * inkwell_review_payment_submission() below, which also unlocks a
 * pending Registrar the moment their payment is approved).
 *
 * Payment details are validated *before* the school/account are created,
 * so a failed or incomplete payment step never leaves an orphan record.
 */
function inkwell_create_school_checkout($schoolName, $name, $email, $password, $idNumber, $course, $planId, $methodId, $referenceNo, $proofFileField = 'proof_image', $cycle = 'month', $senderNumber = '', $paymentDate = '') {
  inkwell_ensure_billing_columns();
  $schoolName = trim($schoolName);
  $name = trim($name);
  $email = strtolower(trim($email));
  $idNumber = trim($idNumber);
  $course = trim($course);

  if ($schoolName === '') return ['ok' => false, 'error' => 'Enter the name of the school you want to create.'];
  if ($name === '' || $email === '' || $password === '') return ['ok' => false, 'error' => 'All fields are required.'];
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return ['ok' => false, 'error' => 'Enter a valid email address.'];
  if (strlen($password) < 8) return ['ok' => false, 'error' => 'Password must be at least 8 characters.'];
  if ($idNumber === '') return ['ok' => false, 'error' => 'Enter your Registrar ID.'];
  if ($course === '') return ['ok' => false, 'error' => 'Enter your office / department.'];

  $plan = inkwell_get_plan($planId);
  if (!$plan || !in_array($plan['audience'], ['school', 'both'], true)) {
    return ['ok' => false, 'error' => 'Choose a plan to continue.'];
  }
  $planIsFree = (float) $plan['price'] <= 0 && (empty($plan['price_yearly']) || (float) $plan['price_yearly'] <= 0);
  if ($planIsFree) {
    return ['ok' => false, 'error' => 'Free plans aren\'t available for creating a school — choose a paid plan.'];
  }

  $pdo = inkwell_db();
  $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
  $stmt->execute([$email]);
  if ($stmt->fetch()) return ['ok' => false, 'error' => 'An account with that email already exists.'];

  // Validate the payment step up front (mirrors inkwell_submit_payment()'s
  // own checks) before anything is written to the database.
  $cycle = $cycle === 'year' ? 'year' : 'month';
  $amount = inkwell_plan_price($plan, $cycle);
  $referenceNo = trim($referenceNo);
  $senderNumber = trim($senderNumber);
  $paymentDate = trim($paymentDate);
  $hasProof = !empty($_FILES[$proofFileField]['name']);
  if ($amount > 0) {
    if (!$methodId) return ['ok' => false, 'error' => 'Choose a payment method.'];
    if ($referenceNo === '' && !$hasProof) {
      return ['ok' => false, 'error' => 'Enter a reference number or upload a screenshot of your payment.'];
    }
    $checkoutMethod = inkwell_get_payment_method($methodId);
    if ($checkoutMethod && $checkoutMethod['type'] === 'gcash') {
      if ($senderNumber === '') return ['ok' => false, 'error' => 'Enter the GCash mobile number the payment was sent from.'];
      if ($paymentDate === '') return ['ok' => false, 'error' => 'Enter the date the payment was made.'];
    }
  }

  $schoolResult = inkwell_create_school($schoolName);
  if (!$schoolResult['ok']) return ['ok' => false, 'error' => $schoolResult['error']];
  $schoolId = $schoolResult['id'];

  $stmt = $pdo->prepare('INSERT INTO users (role, name, email, password_hash, status, id_number, course, school_id) VALUES (\'registrar\', ?, ?, ?, \'pending\', ?, ?, ?)');
  $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), $idNumber, $course, $schoolId]);
  $userId = (int) $pdo->lastInsertId();

  $payResult = inkwell_submit_payment($userId, $planId, $methodId, $referenceNo, $proofFileField, $cycle, $senderNumber, $paymentDate);
  if (!$payResult['ok']) {
    // Something went wrong on the payment step after we'd already created
    // the account/school — clean both up rather than leave a broken record.
    $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$userId]);
    $pdo->prepare('DELETE FROM schools WHERE id = ?')->execute([$schoolId]);
    return ['ok' => false, 'error' => $payResult['error']];
  }

  // A free plan or an instant-activate payment method unlocks the account
  // right away — no separate admin approval needed in that case.
  $active = !empty($payResult['free']) || !empty($payResult['instant']);
  if ($active) {
    inkwell_set_user_status($userId, 'active', 'registrar');
  }

  return [
    'ok' => true,
    'user_id' => $userId,
    'school_id' => $schoolId,
    'active' => $active,
  ];
}

function inkwell_list_payment_submissions($status = null) {
  $pdo = inkwell_db();
  $sql = 'SELECT ps.*, u.name AS user_name, u.email AS user_email, u.role AS user_role,
                 p.name AS plan_name, pm.label AS method_label
          FROM payment_submissions ps
          JOIN users u ON u.id = ps.user_id
          JOIN plans p ON p.id = ps.plan_id
          LEFT JOIN payment_methods pm ON pm.id = ps.payment_method_id';
  $params = [];
  if ($status) { $sql .= ' WHERE ps.status = ?'; $params[] = $status; }
  $sql .= ' ORDER BY ps.status = \'pending\' DESC, ps.created_at DESC';
  $stmt = $pdo->prepare($sql);
  $stmt->execute($params);
  return $stmt->fetchAll();
}

function inkwell_list_user_payment_submissions($userId) {
  $pdo = inkwell_db();
  $stmt = $pdo->prepare('SELECT ps.*, p.name AS plan_name, pm.label AS method_label
                          FROM payment_submissions ps
                          JOIN plans p ON p.id = ps.plan_id
                          LEFT JOIN payment_methods pm ON pm.id = ps.payment_method_id
                          WHERE ps.user_id = ? ORDER BY ps.created_at DESC');
  $stmt->execute([(int) $userId]);
  return $stmt->fetchAll();
}

function inkwell_list_recent_payment_submissions($limit = 8) {
  $pdo = inkwell_db();
  $stmt = $pdo->prepare(
    'SELECT ps.*, u.name AS user_name, u.email AS user_email, u.role AS user_role,
            p.name AS plan_name, pm.label AS method_label
     FROM payment_submissions ps
     JOIN users u ON u.id = ps.user_id
     JOIN plans p ON p.id = ps.plan_id
     LEFT JOIN payment_methods pm ON pm.id = ps.payment_method_id
     ORDER BY ps.created_at DESC LIMIT ?'
  );
  $stmt->bindValue(1, (int) $limit, PDO::PARAM_INT);
  $stmt->execute();
  return $stmt->fetchAll();
}

function inkwell_get_payment_submission($id) {
  $pdo = inkwell_db();
  $stmt = $pdo->prepare('SELECT * FROM payment_submissions WHERE id = ?');
  $stmt->execute([(int) $id]);
  return $stmt->fetch() ?: null;
}

/** Admin approves or rejects a submission. Approving activates the plan on the user's account (30 days from now for paid periods billed monthly; adjust as needed). */
function inkwell_review_payment_submission($id, $approve, $adminNote = '') {
  $sub = inkwell_get_payment_submission($id);
  if (!$sub) return ['ok' => false, 'error' => 'Submission not found.'];

  $pdo = inkwell_db();
  $status = $approve ? 'approved' : 'rejected';
  $stmt = $pdo->prepare('UPDATE payment_submissions SET status = ?, admin_note = ?, reviewed_at = NOW() WHERE id = ?');
  $stmt->execute([$status, trim($adminNote) ?: null, (int) $id]);

  if ($approve) {
    $cycle = ($sub['billing_cycle'] ?? 'month') === 'year' ? 'year' : 'month';
    inkwell_activate_user_plan($sub['user_id'], $sub['plan_id'], $cycle);

    // A Registrar whose school checkout is still awaiting approval gets
    // unlocked the moment their payment is confirmed — for a school
    // created through /create-school.php, payment approval *is* the
    // account approval. (Already-active registrars renewing a plan are
    // untouched, since this only fires while status is still 'pending'.)
    $stmt2 = $pdo->prepare("SELECT id, status FROM users WHERE id = ? AND role = 'registrar'");
    $stmt2->execute([(int) $sub['user_id']]);
    $regRow = $stmt2->fetch();
    if ($regRow && $regRow['status'] === 'pending') {
      inkwell_set_user_status((int) $regRow['id'], 'active', 'registrar');
    }
  } else {
    $stmt2 = $pdo->prepare('UPDATE users SET plan_status = \'expired\' WHERE id = ? AND plan_id = ?');
    $stmt2->execute([(int) $sub['user_id'], (int) $sub['plan_id']]);
  }

  return ['ok' => true];
}

function inkwell_count_pending_payment_submissions() {
  $pdo = inkwell_db();
  return (int) $pdo->query("SELECT COUNT(*) FROM payment_submissions WHERE status = 'pending'")->fetchColumn();
}

/* ---------------- Exam access gating + renewal notices ---------------- */

/**
 * Free tier only gets lessons + community notes. Taking a certification
 * exam (any exam with purpose='cert' — teacher, admin, or self-study) and
 * downloading its certificate requires an active plan whose unlocks_exams
 * flag is on. Admin accounts always pass (they're managing the exams, not
 * "taking" them for a certificate).
 */
function inkwell_user_has_exam_access($user) {
  if (!$user) return false;
  if ($user['role'] === 'admin') return true;
  if (($user['plan_status'] ?? 'none') !== 'active') return false;
  if (!empty($user['plan_expires_at']) && strtotime($user['plan_expires_at']) < time()) return false; // safety net; auth.php normally flips this to 'expired' already
  $plan = !empty($user['plan_id']) ? inkwell_get_plan($user['plan_id']) : null;
  return inkwell_plan_unlocks_exams($plan);
}

/**
 * True if this user (logged in or not) can open ANY lesson in ANY track,
 * not just the free preview lessons. Mirrors inkwell_user_has_exam_access()
 * but for the lesson library itself — see inkwell_lesson_is_locked() in
 * includes/lesson_progress.php for the per-lesson check that uses this.
 * Guests (no account) never have full access. Admins always do.
 */
function inkwell_user_has_full_lesson_access($user) {
  if (!$user) return false;
  if ($user['role'] === 'admin') return true;
  if (($user['plan_status'] ?? 'none') !== 'active') return false;
  if (!empty($user['plan_expires_at']) && strtotime($user['plan_expires_at']) < time()) return false;
  $plan = !empty($user['plan_id']) ? inkwell_get_plan($user['plan_id']) : null;
  return inkwell_plan_unlocks_all_lessons($plan);
}

/** Days left until plan_expires_at (negative if already past). Null if there's no expiry to track. */
function inkwell_plan_days_left($user) {
  if (empty($user['plan_expires_at'])) return null;
  $diff = strtotime($user['plan_expires_at']) - time();
  return (int) floor($diff / 86400);
}

/**
 * Banner content for the site header — warns a few days before renewal,
 * and again once the plan has actually lapsed. Returns null when there's
 * nothing worth showing (no plan, free plan, or plenty of time left).
 */
function inkwell_renewal_notice($user, $warnDays = 5) {
  if (!$user || empty($user['plan_id'])) return null;
  $status = $user['plan_status'] ?? 'none';

  if ($status === 'expired') {
    return [
      'level' => 'danger',
      'message' => 'Your plan has expired — certification exams and certificates are locked until you renew.',
    ];
  }

  if ($status === 'active' && !empty($user['plan_expires_at'])) {
    $daysLeft = inkwell_plan_days_left($user);
    if ($daysLeft !== null && $daysLeft <= $warnDays) {
      if ($daysLeft <= 0) {
        return [
          'level' => 'danger',
          'message' => 'Your plan expires today — renew now to keep exam access.',
        ];
      }
      $plural = $daysLeft === 1 ? 'day' : 'days';
      return [
        'level' => 'warning',
        'message' => "Your plan renews in {$daysLeft} {$plural} — renew early to avoid losing exam access.",
      ];
    }
  }

  if ($status === 'pending') {
    return [
      'level' => 'info',
      'message' => 'Your payment is pending review — we\'ll activate your plan once an admin approves it.',
    ];
  }

  return null;
}

/* ---------------- Registrar / school plan gating ---------------- */

/**
 * Normalizes a Registrar's own plan_status into one of 'active', 'pending',
 * 'expired', or 'none'. The Registrar IS the school's billing account (the
 * plan they checked out with in inkwell_create_school_checkout(), or renew
 * on /my-billing.php), so this reads straight off their user row — same
 * field inkwell_user_has_exam_access() uses for students/teachers.
 */
function inkwell_registrar_plan_state($user) {
  if (!$user || empty($user['plan_id'])) return 'none';
  $status = $user['plan_status'] ?? 'none';
  return in_array($status, ['active', 'pending', 'expired'], true) ? $status : 'none';
}

/**
 * True when the Registrar's plan isn't active — used to lock the entire
 * Registrar dashboard (subjects, teachers, deans, students, reports) until
 * they renew. 'pending' locks too: a lapsed plan being renewed goes back to
 * 'pending' review, same as a brand-new school awaiting its first payment.
 * Admin-created Registrar accounts (inkwell_admin_create_registrar(), which
 * never go through checkout) are also locked here — a school still needs a
 * plan to actually unlock the paid feature set the pricing card promises,
 * even if an admin set the account up directly.
 */
function inkwell_registrar_dashboard_locked($user) {
  return inkwell_registrar_plan_state($user) !== 'active';
}

/**
 * Content for the full-page lock screen shown in place of the Registrar
 * dashboard while inkwell_registrar_dashboard_locked() is true.
 */
function inkwell_registrar_lock_info($user) {
  $plan = !empty($user['plan_id']) ? inkwell_get_plan($user['plan_id']) : null;
  switch (inkwell_registrar_plan_state($user)) {
    case 'expired':
      return [
        'heading' => 'Your school plan has expired',
        'message' => 'Unlimited teacher & student accounts, school branding on certificates, the reporting dashboard, and support are paused until you renew' . ($plan ? " your {$plan['name']} plan" : '') . '.',
      ];
    case 'pending':
      return [
        'heading' => 'Payment pending review',
        'message' => 'We\'ve received your payment details and the dashboard will unlock automatically once it\'s confirmed — usually within a day, or instantly for auto-activate methods.',
      ];
    default:
      return [
        'heading' => 'Choose a school plan to continue',
        'message' => 'Your registrar account is approved, but managing subjects, teachers, deans, and students needs an active School plan.',
      ];
  }
}

/* ---------------- Admin billing dashboard analytics ---------------- */

/**
 * Everything the admin billing dashboard needs in one call: revenue totals,
 * a monthly revenue trend, subscriber counts by status, plan distribution,
 * and payment-method usage. All revenue figures only count 'approved'
 * submissions — pending/rejected never count as real revenue.
 */
function inkwell_billing_dashboard_stats($trendMonths = 6) {
  inkwell_ensure_billing_columns();
  $pdo = inkwell_db();

  $totalRevenue = (float) $pdo->query("SELECT COALESCE(SUM(amount),0) FROM payment_submissions WHERE status = 'approved'")->fetchColumn();

  $monthStart = date('Y-m-01 00:00:00');
  $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM payment_submissions WHERE status = 'approved' AND reviewed_at >= ?");
  $stmt->execute([$monthStart]);
  $revenueThisMonth = (float) $stmt->fetchColumn();

  $pendingRevenue = (float) $pdo->query("SELECT COALESCE(SUM(amount),0) FROM payment_submissions WHERE status = 'pending'")->fetchColumn();
  $pendingCount = (int) $pdo->query("SELECT COUNT(*) FROM payment_submissions WHERE status = 'pending'")->fetchColumn();

  $activeSubscribers = (int) $pdo->query(
    "SELECT COUNT(*) FROM users u JOIN plans p ON p.id = u.plan_id
     WHERE u.plan_status = 'active' AND (p.price > 0 OR p.price_yearly > 0)"
  )->fetchColumn();

  $freeUsers = (int) $pdo->query(
    "SELECT COUNT(*) FROM users u JOIN plans p ON p.id = u.plan_id
     WHERE u.plan_status = 'active' AND p.price <= 0 AND (p.price_yearly IS NULL OR p.price_yearly <= 0)"
  )->fetchColumn();

  $expiredCount = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE plan_status = 'expired'")->fetchColumn();

  $soon = date('Y-m-d H:i:s', strtotime('+5 days'));
  $now = date('Y-m-d H:i:s');
  $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE plan_status = 'active' AND plan_expires_at IS NOT NULL AND plan_expires_at BETWEEN ? AND ?");
  $stmt->execute([$now, $soon]);
  $expiringSoon = (int) $stmt->fetchColumn();

  // Monthly revenue trend (approved submissions, grouped by the month they were reviewed/approved in).
  $trend = [];
  for ($i = $trendMonths - 1; $i >= 0; $i--) {
    $start = date('Y-m-01 00:00:00', strtotime("-{$i} months"));
    $end = date('Y-m-01 00:00:00', strtotime("-" . ($i - 1) . " months"));
    $label = date('M', strtotime($start));
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM payment_submissions WHERE status = 'approved' AND reviewed_at >= ? AND reviewed_at < ?");
    $stmt->execute([$start, $end]);
    $trend[$label] = (float) $stmt->fetchColumn();
  }

  // Active subscribers per plan (for the plan-distribution bars).
  $planRows = $pdo->query(
    "SELECT p.name, COUNT(*) AS c FROM users u JOIN plans p ON p.id = u.plan_id
     WHERE u.plan_status = 'active' GROUP BY p.id, p.name ORDER BY c DESC"
  )->fetchAll();
  $planDistribution = [];
  foreach ($planRows as $r) { $planDistribution[$r['name']] = (int) $r['c']; }

  // Approved revenue per payment method (for the payment-method usage bars).
  $methodRows = $pdo->query(
    "SELECT COALESCE(pm.label, 'Unknown') AS label, COUNT(*) AS c, COALESCE(SUM(ps.amount),0) AS rev
     FROM payment_submissions ps LEFT JOIN payment_methods pm ON pm.id = ps.payment_method_id
     WHERE ps.status = 'approved' GROUP BY pm.id, pm.label ORDER BY rev DESC"
  )->fetchAll();

  // Monthly vs yearly split of approved revenue.
  $cycleRows = $pdo->query(
    "SELECT billing_cycle, COUNT(*) AS c, COALESCE(SUM(amount),0) AS rev
     FROM payment_submissions WHERE status = 'approved' GROUP BY billing_cycle"
  )->fetchAll();
  $cycleSplit = ['month' => 0.0, 'year' => 0.0];
  foreach ($cycleRows as $r) {
    $cycleSplit[$r['billing_cycle'] === 'year' ? 'year' : 'month'] = (float) $r['rev'];
  }

  return [
    'total_revenue' => $totalRevenue,
    'revenue_this_month' => $revenueThisMonth,
    'pending_revenue' => $pendingRevenue,
    'pending_count' => $pendingCount,
    'active_subscribers' => $activeSubscribers,
    'free_users' => $freeUsers,
    'expired_count' => $expiredCount,
    'expiring_soon' => $expiringSoon,
    'revenue_trend' => $trend,
    'plan_distribution' => $planDistribution,
    'method_breakdown' => $methodRows,
    'cycle_split' => $cycleSplit,
  ];
}
