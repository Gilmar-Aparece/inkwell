<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/marketplace.php';

$me = inkwell_require_login();
if (!inkwell_marketplace_can_sell($me)) {
  $pageTitle = 'Sell a system';
  include __DIR__ . '/includes/header.php';
  echo '<main><div class="billing-page"><section class="admin-card glass-card"><h2>Not available</h2><p class="admin-sub">Only student and teacher accounts can sell systems on the marketplace.</p></section></div></main>';
  include __DIR__ . '/includes/footer.php';
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';

  if ($action === 'create') {
    $result = inkwell_marketplace_create_listing($me, $_POST);
    if ($result['ok']) {
      inkwell_flash_set('notice', 'Listing published! Buyers can now find it in the marketplace.');
      header('Location: /sell.php?edit=' . $result['id']);
    } else {
      inkwell_flash_set('error', $result['error']);
      header('Location: /sell.php');
    }
    exit;
  }

  if ($action === 'update') {
    $id = (int) ($_POST['listing_id'] ?? 0);
    $result = inkwell_marketplace_update_listing($id, $me, $_POST);
    if ($result['ok']) {
      inkwell_flash_set('notice', 'Listing updated.');
      header('Location: /sell.php?edit=' . $id);
    } else {
      inkwell_flash_set('error', $result['error']);
      header('Location: /sell.php?edit=' . $id);
    }
    exit;
  }

  if ($action === 'delete') {
    $id = (int) ($_POST['listing_id'] ?? 0);
    $result = inkwell_marketplace_delete_listing($id, $me);
    inkwell_flash_set($result['ok'] ? 'notice' : 'error', $result['ok'] ? 'Listing removed.' : $result['error']);
    header('Location: /sell.php');
    exit;
  }

  if ($action === 'generate_codes') {
    $id = (int) ($_POST['listing_id'] ?? 0);
    $result = inkwell_marketplace_generate_codes($id, $me, (int) ($_POST['count'] ?? 1));
    inkwell_flash_set($result['ok'] ? 'notice' : 'error', $result['ok'] ? 'Generated ' . count($result['codes']) . ' code(s): ' . implode(', ', $result['codes']) : $result['error']);
    header('Location: /sell.php?edit=' . $id);
    exit;
  }

  if ($action === 'delete_code') {
    $id = (int) ($_POST['listing_id'] ?? 0);
    $result = inkwell_marketplace_delete_code((int) ($_POST['code_id'] ?? 0), $me);
    inkwell_flash_set($result['ok'] ? 'notice' : 'error', $result['ok'] ? 'Code removed.' : $result['error']);
    header('Location: /sell.php?edit=' . $id);
    exit;
  }
}

$flash = inkwell_flash_get();
$notice = $flash && $flash['type'] === 'notice' ? $flash['message'] : '';
$error = $flash && $flash['type'] === 'error' ? $flash['message'] : '';

$categories = inkwell_marketplace_categories();
$myListings = inkwell_marketplace_seller_listings($me['id']);
$earnings = inkwell_marketplace_seller_earnings($me['id']);

$editId = (int) ($_GET['edit'] ?? 0);
$editing = $editId ? inkwell_marketplace_get_listing($editId) : null;
if ($editing && (int) $editing['seller_id'] !== (int) $me['id']) $editing = null;
$editingCodes = $editing ? inkwell_marketplace_listing_codes($editing['id'], $me) : [];
$editingScreens = $editing ? inkwell_marketplace_screenshots($editing['id']) : [];

$pageTitle = 'Sell a system';
include __DIR__ . '/includes/header.php';
?>
<main>
  <div class="billing-page">
    <div class="billing-header">
      <h1>Marketplace seller dashboard</h1>
      <p class="admin-sub">List a system you built, set your price, and get paid straight to your own GCash — no middleman.</p>
    </div>

    <?php if ($notice): ?><div class="exam-result pass"><?php echo htmlspecialchars($notice); ?></div><?php endif; ?>
    <?php if ($error): ?><div class="exam-result fail"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

    <section class="admin-card glass-card">
      <h2>Your earnings</h2>
      <div class="mkt-earn-stats">
        <div class="mkt-earn-stat"><span class="mkt-earn-num">₱<?php echo number_format($earnings['total_revenue'], 2); ?></span><span>Total earned</span></div>
        <div class="mkt-earn-stat"><span class="mkt-earn-num"><?php echo (int) $earnings['total_sold']; ?></span><span>Systems sold</span></div>
        <div class="mkt-earn-stat"><span class="mkt-earn-num"><?php echo count($myListings); ?></span><span>Listings</span></div>
      </div>
      <?php if ($earnings['listings']): ?>
        <table class="admin-table">
          <thead><tr><th>System</th><th>Price</th><th>Codes issued</th><th>Sold</th><th>Revenue</th></tr></thead>
          <tbody>
            <?php foreach ($earnings['listings'] as $row): ?>
              <tr>
                <td><?php echo htmlspecialchars($row['title']); ?></td>
                <td>₱<?php echo number_format((float) $row['price'], 2); ?></td>
                <td><?php echo (int) $row['total_codes']; ?></td>
                <td><?php echo (int) $row['sold']; ?></td>
                <td>₱<?php echo number_format((float) $row['revenue'], 2); ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </section>

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
          <table class="admin-table">
            <thead><tr><th>Code</th><th>Status</th><th>Buyer</th><th>Redeemed</th><th></th></tr></thead>
            <tbody>
              <?php foreach ($editingCodes as $c): ?>
                <tr>
                  <td><code><?php echo htmlspecialchars($c['code']); ?></code></td>
                  <td><span class="badge badge-status-<?php echo $c['status'] === 'used' ? 'active' : 'pending'; ?>"><?php echo htmlspecialchars(ucfirst($c['status'])); ?></span></td>
                  <td><?php echo htmlspecialchars($c['buyer_name'] ?? '—'); ?></td>
                  <td><?php echo $c['redeemed_at'] ? date('M j, Y g:ia', strtotime($c['redeemed_at'])) : '—'; ?></td>
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
        <?php else: ?>
          <p class="admin-sub">No codes generated yet.</p>
        <?php endif; ?>
      </section>

      <p><a href="/sell.php">← Back to all your listings</a></p>
    <?php else: ?>
      <section class="admin-card glass-card">
        <h2>List a new system</h2>
        <form method="post" enctype="multipart/form-data" class="mkt-form">
          <input type="hidden" name="action" value="create">
          <?php $editing = null; include __DIR__ . '/includes/marketplace_form_fields.php'; ?>
          <button type="submit" class="btn primary">Publish listing</button>
        </form>
      </section>

      <section class="admin-card glass-card">
        <h2>Your listings</h2>
        <?php if (!$myListings): ?>
          <p class="admin-sub">You haven't listed anything yet — use the form above to publish your first system.</p>
        <?php else: ?>
          <table class="admin-table">
            <thead><tr><th>Title</th><th>Category</th><th>Price</th><th>Status</th><th></th></tr></thead>
            <tbody>
              <?php foreach ($myListings as $l): ?>
                <tr>
                  <td><?php echo htmlspecialchars($l['title']); ?></td>
                  <td><?php echo htmlspecialchars($l['category_name'] ?: '—'); ?></td>
                  <td>₱<?php echo number_format((float) $l['price'], 2); ?></td>
                  <td><span class="badge badge-status-<?php echo $l['status'] === 'active' ? 'active' : 'pending'; ?>"><?php echo htmlspecialchars(ucfirst($l['status'])); ?></span></td>
                  <td><a href="/sell.php?edit=<?php echo (int) $l['id']; ?>" class="btn" style="padding:4px 10px;">Manage</a></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </section>
    <?php endif; ?>
  </div>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
