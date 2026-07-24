<?php
// Inside/dashboard page — suppress the marketing topbar (see includes/header.php).
$__hideTopbar = true;
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/marketplace.php';

$me = inkwell_current_user();
$slug = trim($_GET['slug'] ?? '');
$listing = $slug ? inkwell_marketplace_get_listing_by_slug($slug) : null;

if (!$listing || ($listing['status'] !== 'active' && (!$me || (int) $listing['seller_id'] !== (int) $me['id']))) {
  http_response_code(404);
  $pageTitle = 'Not found';
  include __DIR__ . '/includes/header.php';
  $driveActive = 'marketplace';
  $driveCrumbs = [['label' => 'Home', 'href' => '/index.php'], ['label' => 'Marketplace', 'href' => '/marketplace.php'], ['label' => 'Not found']];
  $driveTitle = 'System not found';
  include __DIR__ . '/includes/drive_shell_top.php';
  echo '<div class="billing-page"><section class="admin-card glass-card"><p class="admin-sub">It may have been removed or hidden by its seller. <a href="/marketplace.php">Back to marketplace</a></p></section></div>';
  include __DIR__ . '/includes/drive_shell_bottom.php';
  include __DIR__ . '/includes/footer.php';
  exit;
}

inkwell_marketplace_increment_views($listing['id']);

$isOwner = $me && (int) $listing['seller_id'] === (int) $me['id'];
$hasAccess = $me && ($isOwner || $me['role'] === 'admin' || inkwell_marketplace_user_has_access($listing['id'], $me['id']));
$myRequest = ($me && !$isOwner && !$hasAccess) ? inkwell_marketplace_buyer_request($listing['id'], $me['id']) : null;
$seller = inkwell_marketplace_seller_name($listing['seller_id']);
$screenshots = inkwell_marketplace_screenshots($listing['id']);
$category = inkwell_marketplace_category($listing['category_id']);

$notice = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'redeem') {
  if (!$me) {
    header('Location: /login.php');
    exit;
  }
  $result = inkwell_marketplace_redeem_code($listing['id'], $me, $_POST['code'] ?? '');
  if ($result['ok']) {
    inkwell_flash_set('notice', '🎉 Unlocked! You now have hosted access and the ZIP download below.');
  } else {
    inkwell_flash_set('error', $result['error']);
  }
  header('Location: /marketplace-view.php?slug=' . urlencode($slug));
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'request_code') {
  if (!$me) {
    header('Location: /login.php');
    exit;
  }
  $result = inkwell_marketplace_create_purchase_request($listing['id'], $me, $_POST['message'] ?? '');
  if ($result['ok']) {
    inkwell_flash_set('notice', '✅ Request sent! The seller has been notified and will send your one-time code shortly.');
  } else {
    inkwell_flash_set('error', $result['error']);
  }
  header('Location: /marketplace-view.php?slug=' . urlencode($slug));
  exit;
}

$flash = inkwell_flash_get();
$notice = $flash && $flash['type'] === 'notice' ? $flash['message'] : '';
$error = $flash && $flash['type'] === 'error' ? $flash['message'] : '';

$pageTitle = $listing['title'];
include __DIR__ . '/includes/header.php';
$driveActive = 'marketplace';
$driveCrumbs = [['label' => 'Home', 'href' => '/index.php'], ['label' => 'Marketplace', 'href' => '/marketplace.php'], ['label' => $listing['title']]];
$driveTitle = $listing['title'];
include __DIR__ . '/includes/drive_shell_top.php';
?>
  <div class="billing-page mkt-detail">
    <p class="admin-sub"><a href="/marketplace.php">← Marketplace</a><?php if ($category): ?> / <?php echo htmlspecialchars($category['icon'] . ' ' . $category['name']); ?><?php endif; ?></p>

    <?php if ($notice): ?><div class="exam-result pass"><?php echo htmlspecialchars($notice); ?></div><?php endif; ?>
    <?php if ($error): ?><div class="exam-result fail"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
    <?php if ($isOwner && $listing['status'] !== 'active'): ?>
      <div class="exam-result pending">This listing is <?php echo htmlspecialchars($listing['status']); ?> — only you can see this page. <a href="/sell.php">Manage it</a></div>
    <?php endif; ?>

    <div class="mkt-detail-grid">
      <div class="mkt-detail-main">
        <div class="mkt-preview-hero">
          <?php echo inkwell_marketplace_thumb_html($listing, $category['icon'] ?? '💻'); ?>
        </div>

        <?php if ($screenshots): ?>
          <div class="mkt-screens">
            <?php foreach ($screenshots as $s): ?>
              <img src="/assets/uploads/<?php echo htmlspecialchars($s['filename']); ?>" alt="Screenshot" loading="lazy">
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <section class="admin-card glass-card">
          <h2>About this system</h2>
          <p class="admin-sub" style="white-space:pre-wrap;"><?php echo htmlspecialchars($listing['description']); ?></p>
          <?php if ($listing['tech_stack']): ?>
            <p class="admin-sub"><strong>Built with:</strong> <?php echo htmlspecialchars($listing['tech_stack']); ?></p>
          <?php endif; ?>
        </section>
      </div>

      <div class="mkt-detail-side">
        <section class="admin-card glass-card mkt-buy-card">
          <h2><?php echo htmlspecialchars($listing['title']); ?></h2>
          <p class="admin-sub">by <strong><?php echo htmlspecialchars($seller['name'] ?? 'Unknown'); ?></strong> · <span class="badge badge-<?php echo htmlspecialchars($seller['role'] ?? ''); ?>"><?php echo htmlspecialchars(ucfirst($seller['role'] ?? '')); ?></span></p>
          <div class="mkt-price"><?php echo (float) $listing['price'] > 0 ? '₱' . number_format((float) $listing['price'], 2) : 'Free'; ?></div>

          <?php $demoUrl = inkwell_marketplace_live_demo_url($listing); if ($demoUrl): ?>
            <a href="<?php echo htmlspecialchars($demoUrl); ?>" target="_blank" rel="noopener" class="btn" style="width:100%;justify-content:center;">👁️ Preview live demo</a>
          <?php endif; ?>

          <?php if ($isOwner): ?>
            <a href="/sell.php?edit=<?php echo (int) $listing['id']; ?>" class="btn primary" style="width:100%;justify-content:center;margin-top:8px;">Manage this listing</a>

          <?php elseif ($hasAccess): ?>
            <div class="exam-result pass" style="margin-top:12px;">You own this system.</div>
            <a href="/marketplace-download.php?id=<?php echo (int) $listing['id']; ?>" class="btn primary" style="width:100%;justify-content:center;margin-top:8px;">⬇️ Download ZIP</a>

          <?php else: ?>
            <?php if ((float) $listing['price'] > 0): ?>
              <div class="mkt-gcash-box">
                <p class="admin-sub"><strong>How to buy:</strong></p>
                <ol class="admin-sub" style="padding-left:18px;">
                  <li>Pay <strong>₱<?php echo number_format((float) $listing['price'], 2); ?></strong> via GCash to:<br>
                    <strong><?php echo htmlspecialchars($listing['gcash_name'] ?: 'the seller'); ?></strong> — <strong><?php echo htmlspecialchars($listing['gcash_number'] ?: 'ask seller'); ?></strong>
                  </li>
                  <li>Tap "Request unlock code" below and mention your GCash reference number — the seller gets notified instantly.</li>
                  <li>Your one-time code shows up right here as soon as the seller generates it.</li>
                </ol>
              </div>
            <?php endif; ?>

            <?php if (!$me): ?>
              <a href="/login.php" class="btn primary" style="width:100%;justify-content:center;margin-top:10px;">Log in to unlock</a>

            <?php elseif ($myRequest && $myRequest['status'] === 'fulfilled'): ?>
              <div class="exam-result pass" style="margin-top:12px;">
                🎉 Your code is ready: <code><?php echo htmlspecialchars($myRequest['code']); ?></code>
              </div>
              <form method="post" class="mkt-redeem-form">
                <input type="hidden" name="action" value="redeem">
                <input type="hidden" name="code" value="<?php echo htmlspecialchars($myRequest['code']); ?>">
                <button type="submit" class="btn primary" style="width:100%;justify-content:center;">Unlock system</button>
              </form>

            <?php elseif ($myRequest && $myRequest['status'] === 'pending'): ?>
              <div class="exam-result pending" style="margin-top:12px;">
                ⏳ Request sent — waiting for the seller to send your code.
                <?php if ($myRequest['message']): ?><br><span class="admin-sub">Your note: "<?php echo htmlspecialchars($myRequest['message']); ?>"</span><?php endif; ?>
              </div>
              <form method="post" class="mkt-redeem-form" style="margin-top:10px;">
                <input type="hidden" name="action" value="request_code">
                <label>Update your note (optional)</label>
                <input type="text" name="message" maxlength="300" placeholder="e.g. GCash ref number" value="<?php echo htmlspecialchars($myRequest['message'] ?? ''); ?>">
                <button type="submit" class="btn" style="width:100%;justify-content:center;">Resend request</button>
              </form>

            <?php else: ?>
              <form method="post" class="mkt-redeem-form">
                <input type="hidden" name="action" value="request_code">
                <label>Message the seller (optional)</label>
                <input type="text" name="message" maxlength="300" placeholder="e.g. GCash ref number, or your situation">
                <button type="submit" class="btn primary" style="width:100%;justify-content:center;">Request unlock code</button>
              </form>
              <form method="post" class="mkt-redeem-form" style="margin-top:10px;">
                <input type="hidden" name="action" value="redeem">
                <label>Already have an unlock code?</label>
                <input type="text" name="code" placeholder="XXXX-XXXX-XXXX" required>
                <button type="submit" class="btn" style="width:100%;justify-content:center;">Unlock system</button>
              </form>
            <?php endif; ?>
          <?php endif; ?>
        </section>
      </div>
    </div>
  </div>
<?php include __DIR__ . '/includes/drive_shell_bottom.php'; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>
