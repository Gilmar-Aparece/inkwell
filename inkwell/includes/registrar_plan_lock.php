<?php
/**
 * Full-page "your school plan isn't active" screen for the Registrar area.
 * Included by registrar/dashboard.php, teachers.php, deans.php, students.php,
 * and reports.php whenever inkwell_registrar_dashboard_locked($user) is true.
 *
 * Expects $user (and optionally $school) to already be set by the caller.
 * Requires includes/billing.php to already be loaded.
 */
$__lock = inkwell_registrar_lock_info($user);
$__lockState = inkwell_registrar_plan_state($user);
?>
<main class="admin-main">
  <div class="admin-header-row">
    <h1>Registrar dashboard</h1>
    <a class="btn" href="/logout.php">Log out</a>
  </div>
  <section class="admin-card glass-card">
    <h2><?php echo htmlspecialchars($__lock['heading']); ?></h2>
    <p class="admin-sub">
      <?php if (!empty($school)): ?><strong><?php echo htmlspecialchars($school['name']); ?></strong> — <?php endif; ?>
      <?php echo htmlspecialchars($__lock['message']); ?>
    </p>
    <p class="admin-sub">
      Status:
      <span class="payment-status-badge status-<?php echo htmlspecialchars($__lockState); ?>"><?php echo htmlspecialchars(ucfirst($__lockState === 'none' ? 'No plan' : $__lockState)); ?></span>
    </p>
    <a class="btn primary" href="/my-billing.php"><?php echo $__lockState === 'expired' ? 'Renew your plan' : (($__lockState === 'pending') ? 'View payment status' : 'Choose a plan'); ?></a>
  </section>
</main>
