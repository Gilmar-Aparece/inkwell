<?php
// Inside/dashboard page — suppress the marketing topbar (see includes/header.php).
$__hideTopbar = true;
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/marketplace.php';

$me = inkwell_require_login();
$canSell = inkwell_marketplace_can_sell($me);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';

  if ($action === 'create') {
    $result = inkwell_marketplace_create_listing($me, $_POST);
    if ($result['ok']) {
      inkwell_flash_set('notice', 'Listing published! Buyers can now find it in the marketplace.');
      header('Location: /sell.php?tab=listings&edit=' . $result['id']);
    } else {
      inkwell_flash_set('error', $result['error']);
      header('Location: /sell.php?tab=add');
    }
    exit;
  }

  if ($action === 'update') {
    $id = (int) ($_POST['listing_id'] ?? 0);
    $result = inkwell_marketplace_update_listing($id, $me, $_POST);
    if ($result['ok']) {
      inkwell_flash_set('notice', 'Listing updated.');
      header('Location: /sell.php?tab=listings&edit=' . $id);
    } else {
      inkwell_flash_set('error', $result['error']);
      header('Location: /sell.php?tab=listings&edit=' . $id);
    }
    exit;
  }

  if ($action === 'delete') {
    $id = (int) ($_POST['listing_id'] ?? 0);
    $result = inkwell_marketplace_delete_listing($id, $me);
    inkwell_flash_set($result['ok'] ? 'notice' : 'error', $result['ok'] ? 'Listing removed.' : $result['error']);
    header('Location: /sell.php?tab=listings');
    exit;
  }

  if ($action === 'generate_codes') {
    $id = (int) ($_POST['listing_id'] ?? 0);
    $result = inkwell_marketplace_generate_codes($id, $me, (int) ($_POST['count'] ?? 1));
    inkwell_flash_set($result['ok'] ? 'notice' : 'error', $result['ok'] ? 'Generated ' . count($result['codes']) . ' code(s): ' . implode(', ', $result['codes']) : $result['error']);
    header('Location: /sell.php?tab=listings&edit=' . $id);
    exit;
  }

  if ($action === 'delete_code') {
    $id = (int) ($_POST['listing_id'] ?? 0);
    $result = inkwell_marketplace_delete_code((int) ($_POST['code_id'] ?? 0), $me);
    inkwell_flash_set($result['ok'] ? 'notice' : 'error', $result['ok'] ? 'Code removed.' : $result['error']);
    header('Location: /sell.php?tab=listings&edit=' . $id);
    exit;
  }

  if ($action === 'fulfill_request') {
    $id = (int) ($_POST['listing_id'] ?? 0);
    $result = inkwell_marketplace_fulfill_request((int) ($_POST['request_id'] ?? 0), $me);
    inkwell_flash_set($result['ok'] ? 'notice' : 'error', $result['ok'] ? 'Code generated and sent to the buyer: ' . $result['code'] : $result['error']);
    header('Location: /sell.php?tab=listings&edit=' . $id);
    exit;
  }

  if ($action === 'decline_request') {
    $id = (int) ($_POST['listing_id'] ?? 0);
    $result = inkwell_marketplace_decline_request((int) ($_POST['request_id'] ?? 0), $me);
    inkwell_flash_set($result['ok'] ? 'notice' : 'error', $result['ok'] ? 'Request declined.' : $result['error']);
    header('Location: /sell.php?tab=listings&edit=' . $id);
    exit;
  }
}

$flash = inkwell_flash_get();
$notice = $flash && $flash['type'] === 'notice' ? $flash['message'] : '';
$error = $flash && $flash['type'] === 'error' ? $flash['message'] : '';

$editId = (int) ($_GET['edit'] ?? 0);
$editing = ($editId && $canSell) ? inkwell_marketplace_get_listing($editId) : null;
if ($editing && (int) $editing['seller_id'] !== (int) $me['id']) $editing = null;

$tab = $_GET['tab'] ?? ($canSell ? 'listings' : 'library');
if (!$canSell) $tab = 'library';
if ($editing) $tab = 'listings';
if (!in_array($tab, ['add', 'listings', 'library'], true)) $tab = $canSell ? 'listings' : 'library';

$categories = $canSell ? inkwell_marketplace_categories() : [];
$myListings = $canSell ? inkwell_marketplace_seller_listings($me['id']) : [];
$earnings = $canSell ? inkwell_marketplace_seller_earnings($me['id']) : null;
$editingCodes = $editing ? inkwell_marketplace_listing_codes($editing['id'], $me) : [];
$editingRequests = $editing ? inkwell_marketplace_listing_pending_requests($editing['id'], $me) : [];

$myBuyers = $canSell ? inkwell_marketplace_seller_buyers($me['id']) : [];

$purchases = inkwell_marketplace_buyer_library($me['id']);
$totalSpent = 0.0;
foreach ($purchases as $p) $totalSpent += (float) $p['price'];
$sellerCount = count(array_unique(array_column($purchases, 'seller_id')));

$pageTitle = 'My Marketplace Dashboard';
include __DIR__ . '/includes/header.php';
$driveActive = 'sell';
$driveCrumbs = [['label' => 'Home', 'href' => '/index.php'], ['label' => 'My marketplace dashboard']];
$driveTitle = 'My marketplace dashboard';
$driveSubtitle = $canSell
  ? 'List a system you built, set your price, and get paid straight to your own GCash — plus everything you\'ve bought, in one place.'
  : 'Everything you\'ve bought on the marketplace, in one place.';
include __DIR__ . '/includes/drive_shell_top.php';
?>
  <div class="billing-page">
    <?php if ($notice): ?><div class="exam-result pass"><?php echo htmlspecialchars($notice); ?></div><?php endif; ?>
    <?php if ($error): ?><div class="exam-result fail"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

    <div class="mkt-tabs">
      <?php if ($canSell): ?>
        <a href="/sell.php?tab=add" class="mkt-tab<?php echo $tab === 'add' ? ' active' : ''; ?>">➕ Add new system</a>
        <a href="/sell.php?tab=listings" class="mkt-tab<?php echo $tab === 'listings' ? ' active' : ''; ?>">📋 My listings & pricing</a>
      <?php endif; ?>
      <a href="/sell.php?tab=library" class="mkt-tab<?php echo $tab === 'library' ? ' active' : ''; ?>">📦 Purchased library</a>
    </div>

    <?php if (!$canSell): ?>
      <section class="admin-card glass-card" style="margin-bottom:18px;">
        <p class="admin-sub">Student and teacher accounts can sell for free. Any other account can unlock selling too — <a href="/my-billing.php">upgrade to a plan that includes marketplace selling</a> — but for now you can still buy and manage what you've unlocked below.</p>
      </section>
    <?php endif; ?>

    <?php if ($tab === 'add' && $canSell): ?>
      <section class="admin-card glass-card">
        <h2>List a new system</h2>
        <form method="post" enctype="multipart/form-data" class="mkt-form">
          <input type="hidden" name="action" value="create">
          <?php $editing = null; include __DIR__ . '/includes/marketplace_form_fields.php'; ?>
          <button type="submit" class="btn primary">Publish listing</button>
        </form>
      </section>

    <?php elseif ($tab === 'listings' && $canSell): ?>

      <?php if ($editing): ?>
        <section class="admin-card glass-card">
          <h2>Edit: <?php echo htmlspecialchars($editing['title']); ?></h2>
          <form method="post" enctype="multipart/form-data" class="mkt-form">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="listing_id" value="<?php echo (int) $editing['id']; ?>">
            <?php include __DIR__ . '/includes/marketplace_form_fields.php'; ?>
            <button type="submit" class="btn primary">Save changes</button>
          </form>
          <form method="post" onsubmit="return confirm('Delete this listing permanently? This also removes its unlock codes.');" style="margin-top:10px;">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="listing_id" value="<?php echo (int) $editing['id']; ?>">
            <button type="submit" class="btn danger">Delete listing</button>
          </form>
        </section>

        <section class="admin-card glass-card">
          <h2>Purchase requests<?php if ($editingRequests): ?> <span class="badge badge-status-pending"><?php echo count($editingRequests); ?> pending</span><?php endif; ?></h2>
          <p class="admin-sub">Buyers tap "Request unlock code" once they've paid you on GCash — generate their code here and it's sent to them instantly, no separate chat needed.</p>
          <?php if (!$editingRequests): ?>
            <p class="admin-sub">No pending requests right now.</p>
          <?php else: ?>
            <div class="admin-table-wrap">
            <table class="admin-table">
              <thead><tr><th>Buyer</th><th>Message</th><th>Requested</th><th></th></tr></thead>
              <tbody>
                <?php foreach ($editingRequests as $r): ?>
                  <tr>
                    <td><?php echo htmlspecialchars($r['buyer_name']); ?></td>
                    <td><?php echo $r['message'] ? htmlspecialchars($r['message']) : '<span class="admin-sub">—</span>'; ?></td>
                    <td><?php echo htmlspecialchars(inkwell_marketplace_relative_time($r['created_at'])); ?></td>
                    <td style="display:flex; gap:6px;">
                      <form method="post">
                        <input type="hidden" name="action" value="fulfill_request">
                        <input type="hidden" name="listing_id" value="<?php echo (int) $editing['id']; ?>">
                        <input type="hidden" name="request_id" value="<?php echo (int) $r['id']; ?>">
                        <button type="submit" class="btn primary" style="padding:4px 10px;">Generate code & send</button>
                      </form>
                      <form method="post" onsubmit="return confirm('Decline this request?');">
                        <input type="hidden" name="action" value="decline_request">
                        <input type="hidden" name="listing_id" value="<?php echo (int) $editing['id']; ?>">
                        <input type="hidden" name="request_id" value="<?php echo (int) $r['id']; ?>">
                        <button type="submit" class="btn danger" style="padding:4px 10px;">Decline</button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
            </div>
          <?php endif; ?>
        </section>

        <section class="admin-card glass-card">
          <h2>Unlock codes</h2>
          <p class="admin-sub">Generate a one-time code after a buyer pays you on GCash, then send it to them. Each code works once, on this listing only.</p>
          <form method="post" class="mkt-codegen">
            <input type="hidden" name="action" value="generate_codes">
            <input type="hidden" name="listing_id" value="<?php echo (int) $editing['id']; ?>">
            <label>Generate</label>
            <input type="number" name="count" value="1" min="1" max="50" style="width:80px;">
            <button type="submit" class="btn primary">code(s)</button>
          </form>

          <?php if ($editingCodes): ?>
            <div class="admin-table-wrap">
            <table class="admin-table">
              <thead><tr><th>Code</th><th>Status</th><th>Buyer</th><th>Redeemed</th><th>Download</th><th></th></tr></thead>
              <tbody>
                <?php foreach ($editingCodes as $c): ?>
                  <?php $unlock = $c['status'] === 'used' ? inkwell_marketplace_download_unlock_info($editing, $c['redeemed_at']) : null; ?>
                  <tr>
                    <td><code><?php echo htmlspecialchars($c['code']); ?></code></td>
                    <td><span class="badge badge-status-<?php echo $c['status'] === 'used' ? 'active' : 'pending'; ?>"><?php echo htmlspecialchars(ucfirst($c['status'])); ?></span></td>
                    <td><?php echo htmlspecialchars($c['buyer_name'] ?? '—'); ?></td>
                    <td><?php echo $c['redeemed_at'] ? date('M j, Y g:ia', strtotime($c['redeemed_at'])) : '—'; ?></td>
                    <td>
                      <?php if (!$unlock): ?>
                        <span class="admin-sub">—</span>
                      <?php elseif ($unlock['locked']): ?>
                        <span class="badge badge-status-pending">🔒 <?php echo (int) $unlock['days_left']; ?>d left</span>
                      <?php else: ?>
                        <span class="badge badge-status-active">✅ Unlocked</span>
                      <?php endif; ?>
                    </td>
                    <td>
                      <?php if ($c['status'] === 'unused'): ?>
                        <form method="post" onsubmit="return confirm('Remove this unused code?');">
                          <input type="hidden" name="action" value="delete_code">
                          <input type="hidden" name="listing_id" value="<?php echo (int) $editing['id']; ?>">
                          <input type="hidden" name="code_id" value="<?php echo (int) $c['id']; ?>">
                          <button type="submit" class="btn danger" style="padding:4px 10px;">Remove</button>
                        </form>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
            </div>
          <?php else: ?>
            <p class="admin-sub">No codes generated yet.</p>
          <?php endif; ?>
        </section>

        <p><a href="/sell.php?tab=listings">← Back to all your listings</a></p>
      <?php else: ?>

        <section class="admin-card glass-card">
          <h2>Your earnings</h2>
          <div class="mkt-earn-stats">
            <div class="mkt-earn-stat"><span class="mkt-earn-num">₱<?php echo number_format($earnings['total_revenue'], 2); ?></span><span>Total earned</span></div>
            <div class="mkt-earn-stat"><span class="mkt-earn-num"><?php echo (int) $earnings['total_sold']; ?></span><span>Systems sold</span></div>
            <div class="mkt-earn-stat"><span class="mkt-earn-num"><?php echo count($myListings); ?></span><span>Listings</span></div>
            <div class="mkt-earn-stat"><span class="mkt-earn-num"><?php echo number_format(array_sum(array_column($earnings['listings'], 'views'))); ?></span><span>Total views</span></div>
          </div>

          <?php $trendMax = max(1, max($earnings['trend'])); ?>
          <div class="mkt-trend">
            <h3 class="mkt-trend-title">Revenue, last 6 months</h3>
            <div class="mkt-trend-chart">
              <?php foreach ($earnings['trend'] as $label => $amount): ?>
                <div class="mkt-trend-col">
                  <div class="mkt-trend-bar-wrap">
                    <div class="mkt-trend-bar" style="height:<?php echo max(3, round(($amount / $trendMax) * 100)); ?>%" title="₱<?php echo number_format($amount, 2); ?>"></div>
                  </div>
                  <span class="mkt-trend-amount">₱<?php echo number_format($amount, 0); ?></span>
                  <span class="mkt-trend-label"><?php echo htmlspecialchars($label); ?></span>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        </section>

        <section class="admin-card glass-card">
          <h2>Listing performance</h2>
          <p class="admin-sub">How each system is doing: page views, units sold, and views-to-sale conversion.</p>
          <?php if (!$earnings['listings']): ?>
            <p class="admin-sub">No listings yet.</p>
          <?php else: ?>
            <div class="admin-table-wrap">
            <table class="admin-table">
              <thead><tr><th>System</th><th>Views</th><th>Sold</th><th>Conversion</th><th>Revenue</th></tr></thead>
              <tbody>
                <?php foreach ($earnings['listings'] as $l): ?>
                  <?php
                    $views = (int) $l['views'];
                    $sold = (int) $l['sold'];
                    $conv = $views > 0 ? ($sold / $views) * 100 : 0;
                  ?>
                  <tr>
                    <td><?php echo htmlspecialchars($l['title']); ?></td>
                    <td><?php echo number_format($views); ?></td>
                    <td><?php echo number_format($sold); ?></td>
                    <td><?php echo $views > 0 ? number_format($conv, 1) . '%' : '—'; ?></td>
                    <td>₱<?php echo number_format((float) $l['revenue'], 2); ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
            </div>
          <?php endif; ?>
        </section>

        <section class="admin-card glass-card">
          <div style="display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap;">
            <h2 style="margin:0;">Buyers</h2>
            <a href="/sell-transactions.php" class="btn primary">View all transactions →</a>
          </div>
          <p class="admin-sub">Everyone who has unlocked one of your systems, across all your listings.</p>
          <?php if (!$myBuyers): ?>
            <p class="admin-sub">No purchases yet.</p>
          <?php else: ?>
            <div class="admin-table-wrap">
            <table class="admin-table">
              <thead><tr><th>Buyer</th><th>System</th><th>Purchased</th><th>Download status</th></tr></thead>
              <tbody>
                <?php foreach (array_slice($myBuyers, 0, 5) as $b): ?>
                  <tr>
                    <td><?php echo htmlspecialchars($b['buyer_name']); ?></td>
                    <td><?php echo htmlspecialchars($b['title']); ?></td>
                    <td><?php echo htmlspecialchars(inkwell_marketplace_relative_time($b['redeemed_at'])); ?></td>
                    <td>
                      <?php if ($b['unlock']['locked']): ?>
                        <span class="badge badge-status-pending">🔒 Locked · <?php echo (int) $b['unlock']['days_left']; ?> day<?php echo $b['unlock']['days_left'] === 1 ? '' : 's'; ?> left</span>
                      <?php else: ?>
                        <span class="badge badge-status-active">✅ Unlocked</span>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
            </div>
            <?php if (count($myBuyers) > 5): ?>
              <p class="admin-sub" style="margin-top:10px;">Showing 5 most recent of <?php echo count($myBuyers); ?> — <a href="/sell-transactions.php">see all transactions →</a></p>
            <?php endif; ?>
          <?php endif; ?>
        </section>

        <section class="admin-card glass-card">
          <div style="display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap;">
            <h2 style="margin:0;">Your listings</h2>
            <a href="/sell.php?tab=add" class="btn primary">➕ Add new system</a>
          </div>
          <?php if (!$myListings): ?>
            <p class="admin-sub">You haven't listed anything yet — use "Add new system" above to publish your first one.</p>
          <?php else: ?>
            <div class="admin-table-wrap">
            <table class="admin-table">
              <thead><tr><th>Title</th><th>Category</th><th>Price</th><th>Status</th><th></th></tr></thead>
              <tbody>
                <?php foreach ($myListings as $l): ?>
                  <tr>
                    <td><?php echo htmlspecialchars($l['title']); ?></td>
                    <td><?php echo htmlspecialchars($l['category_name'] ?: '—'); ?></td>
                    <td>₱<?php echo number_format((float) $l['price'], 2); ?></td>
                    <td><span class="badge badge-status-<?php echo $l['status'] === 'active' ? 'active' : 'pending'; ?>"><?php echo htmlspecialchars(ucfirst($l['status'])); ?></span></td>
                    <td><a href="/sell.php?tab=listings&edit=<?php echo (int) $l['id']; ?>" class="btn" style="padding:4px 10px;">Manage</a></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
            </div>
          <?php endif; ?>
        </section>
      <?php endif; ?>

    <?php else: /* library tab */ ?>
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
              <div class="mkt-card-thumb"><?php echo inkwell_marketplace_thumb_html($l); ?></div>
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
    <?php endif; ?>
  </div>
<?php include __DIR__ . '/includes/drive_shell_bottom.php'; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>
