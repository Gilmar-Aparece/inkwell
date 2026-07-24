<?php
// Inside/dashboard page — suppress the marketing topbar (see includes/header.php).
$__hideTopbar = true;
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/marketplace.php';

$me = inkwell_current_user();
$categories = inkwell_marketplace_categories();
$activeCat = trim($_GET['cat'] ?? '');
$search = trim($_GET['q'] ?? '');

$listings = inkwell_marketplace_list_listings([
  'category' => $activeCat ?: null,
  'search' => $search ?: null,
]);

$pageTitle = 'Marketplace';
include __DIR__ . '/includes/header.php';
$driveActive = 'marketplace';
$driveCrumbs = [['label' => 'Home', 'href' => '/index.php'], ['label' => 'Marketplace']];
$driveTitle = 'Student marketplace';
$driveSubtitle = 'Browse systems built by Inkwell students and teachers — preview them live, then buy directly from the builder via GCash.';
include __DIR__ . '/includes/drive_shell_top.php';
?>
  <div class="billing-page">
    <div class="mkt-toolbar">
      <form method="get" class="mkt-search" role="search">
        <?php if ($activeCat): ?><input type="hidden" name="cat" value="<?php echo htmlspecialchars($activeCat); ?>"><?php endif; ?>
        <input type="text" name="q" placeholder="Search systems, tech stack…" value="<?php echo htmlspecialchars($search); ?>">
        <button type="submit" class="btn">Search</button>
      </form>
      <?php if (!$me): ?>
        <a href="/login.php" class="btn">Log in to buy or sell</a>
      <?php endif; ?>
    </div>

    <div class="mkt-cats">
      <a href="/marketplace.php<?php echo $search ? '?q=' . urlencode($search) : ''; ?>" class="mkt-cat-chip<?php echo $activeCat === '' ? ' active' : ''; ?>">All</a>
      <?php foreach ($categories as $cat): ?>
        <a href="/marketplace.php?cat=<?php echo urlencode($cat['slug']); ?><?php echo $search ? '&q=' . urlencode($search) : ''; ?>"
           class="mkt-cat-chip<?php echo $activeCat === $cat['slug'] ? ' active' : ''; ?>">
          <?php echo htmlspecialchars($cat['icon']); ?> <?php echo htmlspecialchars($cat['name']); ?>
        </a>
      <?php endforeach; ?>
    </div>

    <?php if (!$listings): ?>
      <section class="admin-card glass-card">
        <p class="admin-sub">No systems here yet<?php echo $activeCat || $search ? ' matching that filter' : ''; ?>.</p>
      </section>
    <?php else: ?>
      <div class="mkt-grid" id="mktListingsGrid" data-paginate="12">
        <?php foreach ($listings as $l): $demoUrl = inkwell_marketplace_live_demo_url($l); ?>
          <div class="mkt-card glass-card" data-filter-row>
            <a href="/marketplace-view.php?slug=<?php echo urlencode($l['slug']); ?>" class="mkt-card-thumb-link">
              <div class="mkt-card-thumb">
                <?php echo inkwell_marketplace_thumb_html($l, $l['category_icon'] ?: '💻'); ?>
                <span class="mkt-card-price"><?php echo (float) $l['price'] > 0 ? '₱' . number_format((float) $l['price'], 0) : 'Free'; ?></span>
              </div>
            </a>
            <div class="mkt-card-body">
              <?php if ($l['category_name']): ?><span class="badge"><?php echo htmlspecialchars($l['category_icon'] . ' ' . $l['category_name']); ?></span><?php endif; ?>
              <h3><a href="/marketplace-view.php?slug=<?php echo urlencode($l['slug']); ?>"><?php echo htmlspecialchars($l['title']); ?></a></h3>
              <p class="mkt-card-updated">Updated <?php echo htmlspecialchars(inkwell_marketplace_relative_time($l['updated_at'])); ?></p>
              <p class="admin-sub"><?php echo htmlspecialchars($l['tagline'] ?: mb_strimwidth($l['description'], 0, 90, '…')); ?></p>
              <div class="mkt-card-foot">
                <span>by <?php echo htmlspecialchars($l['seller_name']); ?></span>
                <span class="badge badge-<?php echo htmlspecialchars($l['seller_role']); ?>"><?php echo htmlspecialchars(ucfirst($l['seller_role'])); ?></span>
              </div>
              <div class="mkt-card-actions">
                <?php if ($demoUrl): ?><a href="<?php echo htmlspecialchars($demoUrl); ?>" target="_blank" rel="noopener" class="btn">Live Demo</a><?php endif; ?>
                <a href="/marketplace-view.php?slug=<?php echo urlencode($l['slug']); ?>" class="btn primary">View & Buy</a>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
<?php include __DIR__ . '/includes/drive_shell_bottom.php'; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>
