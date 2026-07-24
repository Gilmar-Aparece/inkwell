<?php
// Inside/dashboard page — suppress the marketing topbar (see includes/header.php).
$__hideTopbar = true;
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/marketplace.php';

$me = inkwell_require_login();
$canSell = inkwell_marketplace_can_sell($me);
if (!$canSell) {
  header('Location: /sell.php');
  exit;
}

$myBuyers = inkwell_marketplace_seller_buyers($me['id']);

$pageTitle = 'All transactions';
include __DIR__ . '/includes/header.php';
$driveActive = 'sell';
$driveCrumbs = [
  ['label' => 'Home', 'href' => '/index.php'],
  ['label' => 'My marketplace dashboard', 'href' => '/sell.php?tab=listings'],
  ['label' => 'All transactions'],
];
$driveTitle = 'All transactions';
$driveSubtitle = 'Everyone who has unlocked one of your systems, across all your listings.';
include __DIR__ . '/includes/drive_shell_top.php';
?>
  <div class="billing-page">
    <p><a href="/sell.php?tab=listings">← Back to your marketplace dashboard</a></p>

    <section class="admin-card glass-card">
      <div style="display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap;">
        <h2 style="margin:0;">Buyers</h2>
        <button type="button" class="btn" id="mktBuyersExport">⬇ Export CSV</button>
      </div>
      <?php if (!$myBuyers): ?>
        <p class="admin-sub">No purchases yet.</p>
      <?php else: ?>
        <input type="text" id="mktBuyersSearch" class="mkt-buyers-search" placeholder="Search by buyer or system name…">
        <div class="admin-table-wrap">
        <table class="admin-table" id="mktBuyersTable">
          <thead><tr><th>Buyer</th><th>System</th><th>Purchased</th><th>Download status</th></tr></thead>
          <tbody>
            <?php foreach ($myBuyers as $b): ?>
              <tr data-search="<?php echo htmlspecialchars(mb_strtolower($b['buyer_name'] . ' ' . $b['title'])); ?>">
                <td><?php echo htmlspecialchars($b['buyer_name']); ?></td>
                <td><?php echo htmlspecialchars($b['title']); ?></td>
                <td data-csv="<?php echo htmlspecialchars($b['redeemed_at']); ?>"><?php echo htmlspecialchars(inkwell_marketplace_relative_time($b['redeemed_at'])); ?></td>
                <td data-csv="<?php echo $b['unlock']['locked'] ? 'Locked (' . (int) $b['unlock']['days_left'] . 'd left)' : 'Unlocked'; ?>">
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
        <p class="admin-sub" id="mktBuyersEmpty" style="display:none;">No buyers match your search.</p>
      <?php endif; ?>
    </section>

    <script>
    (function () {
      var search = document.getElementById('mktBuyersSearch');
      var exportBtn = document.getElementById('mktBuyersExport');
      var table = document.getElementById('mktBuyersTable');
      var empty = document.getElementById('mktBuyersEmpty');
      if (search && table) {
        search.addEventListener('input', function () {
          var q = search.value.trim().toLowerCase();
          var rows = table.querySelectorAll('tbody tr');
          var visible = 0;
          rows.forEach(function (row) {
            var match = !q || (row.getAttribute('data-search') || '').indexOf(q) !== -1;
            row.style.display = match ? '' : 'none';
            if (match) visible++;
          });
          empty.style.display = visible === 0 ? '' : 'none';
        });
      }
      if (exportBtn && table) {
        exportBtn.addEventListener('click', function () {
          var rows = [['Buyer', 'System', 'Purchased', 'Download status']];
          table.querySelectorAll('tbody tr').forEach(function (row) {
            if (row.style.display === 'none') return;
            var cells = row.querySelectorAll('td');
            rows.push([
              cells[0].textContent.trim(),
              cells[1].textContent.trim(),
              cells[2].getAttribute('data-csv') || cells[2].textContent.trim(),
              cells[3].getAttribute('data-csv') || cells[3].textContent.trim(),
            ]);
          });
          var csv = rows.map(function (r) {
            return r.map(function (v) { return '"' + String(v).replace(/"/g, '""') + '"'; }).join(',');
          }).join('\r\n');
          var blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
          var a = document.createElement('a');
          a.href = URL.createObjectURL(blob);
          a.download = 'buyers.csv';
          document.body.appendChild(a);
          a.click();
          document.body.removeChild(a);
        });
      }
    })();
    </script>
  </div>
<?php include __DIR__ . '/includes/drive_shell_bottom.php'; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>
