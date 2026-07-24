<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/marketplace.php';

$me = inkwell_current_user();
$slug = trim($_GET['slug'] ?? '');
$listing = $slug ? inkwell_marketplace_get_listing_by_slug($slug) : null;

if (!$listing || ($listing['status'] !== 'active' && (!$me || (int) $listing['seller_id'] !== (int) $me['id']))) {
  http_response_code(404);
  $pageTitle = 'Not found';
  include __DIR__ . '/includes/header.php';
  echo '<main><div class="billing-page"><section class="admin-card glass-card"><h2>System not found</h2><p class="admin-sub">It may have been removed or hidden by its seller. <a href="/marketplace.php">Back to marketplace</a></p></section></div></main>';
  include __DIR__ . '/includes/footer.php';
  exit;
}

inkwell_marketplace_increment_views($listing['id']);

$isOwner = $me && (int) $listing['seller_id'] === (int) $me['id'];
$hasAccess = $me && ($isOwner || $me['role'] === 'admin' || inkwell_marketplace_user_has_access($listing['id'], $me['id']));
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

$flash = inkwell_flash_get();
$notice = $flash && $flash['type'] === 'notice' ? $flash['message'] : '';
$error = $flash && $flash['type'] === 'error' ? $flash['message'] : '';

$pageTitle = $listing['title'];
include __DIR__ . '/includes/header.php';
?>
<main>
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
          <?php if (!empty($listing['thumbnail'])): ?>
            <img src="/assets/uploads/<?php echo htmlspecialchars($listing['thumbnail']); ?>" alt="">
          <?php else: ?>
            <span class="mkt-card-thumb-fallback"><?php echo htmlspecialchars($category['icon'] ?? '💻'); ?></span>
          <?php endif; ?>
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
                  <li>Message the seller your GCash reference number as proof.</li>
                  <li>The seller will send you a one-time unlock code — enter it below.</li>
                </ol>
              </div>
            <?php endif; ?>

            <?php if ($me): ?>
              <form method="post" class="mkt-redeem-form">
                <input type="hidden" name="action" value="redeem">
                <label>Have an unlock code?</label>
                <input type="text" name="code" placeholder="XXXX-XXXX-XXXX" required>
                <button type="submit" class="btn primary" style="width:100%;justify-content:center;">Unlock system</button>
              </form>
            <?php else: ?>
              <a href="/login.php" class="btn primary" style="width:100%;justify-content:center;margin-top:10px;">Log in to unlock</a>
            <?php endif; ?>
          <?php endif; ?>
        </section>
      </div>
    </div>
  </div>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
