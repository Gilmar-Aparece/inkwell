<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/marketplace.php';

$me = inkwell_require_login();
$purchases = inkwell_marketplace_buyer_library($me['id']);

$totalSpent = 0.0;
foreach ($purchases as $p) $totalSpent += (float) $p['price'];
$sellerCount = count(array_unique(array_column($purchases, 'seller_id')));

$pageTitle = 'My Marketplace Dashboard';
include __DIR__ . '/includes/header.php';
?>
<main>
  <div class="billing-page">
    <div class="billing-header">
      <h1>My marketplace dashboard</h1>
      <p class="admin-sub">Every system you've bought and unlocked, in one place.</p>
    </div>

    <section class="admin-card glass-card">
      <div class="mkt-earn-stats">
        <div class="mkt-earn-stat"><span class="mkt-earn-num">₱<?php echo number_format($totalSpent, 2); ?></span><span>Total spent</span></div>
        <div class="mkt-earn-stat"><span class="mkt-earn-num"><?php echo count($purchases); ?></span><span>Systems owned</span></div>
        <div class="mkt-earn-stat"><span class="mkt-earn-num"><?php echo $sellerCount; ?></span><span>Sellers bought from</span></div>
      </div>
    </section>

    <?php if (!$purchases): ?>
      <section class="admin-card glass-card">
        <p class="admin-sub">Nothing here yet. <a href="/marketplace.php">Browse the marketplace</a> to find a system to buy.</p>
      </section>
    <?php else: ?>
      <div class="mkt-grid">
        <?php foreach ($purchases as $l): ?>
          <div class="mkt-card glass-card">
            <div class="mkt-card-thumb">
              <?php if (!empty($l['thumbnail'])): ?>
                <img src="/assets/uploads/<?php echo htmlspecialchars($l['thumbnail']); ?>" alt="" loading="lazy">
              <?php else: ?>
                <span class="mkt-card-thumb-fallback">💻</span>
              <?php endif; ?>
            </div>
            <div class="mkt-card-body">
              <h3><?php echo htmlspecialchars($l['title']); ?></h3>
              <p class="admin-sub">by <?php echo htmlspecialchars($l['seller_name']); ?> · unlocked <?php echo date('M j, Y', strtotime($l['redeemed_at'])); ?></p>
              <div class="mkt-card-foot" style="gap:8px;">
                <?php $demoUrl = inkwell_marketplace_live_demo_url($l); if ($demoUrl): ?><a href="<?php echo htmlspecialchars($demoUrl); ?>" target="_blank" rel="noopener" class="btn">Preview</a><?php endif; ?>
                <a href="/marketplace-download.php?id=<?php echo (int) $l['id']; ?>" class="btn primary">Download ZIP</a>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
