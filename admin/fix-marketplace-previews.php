<?php
/**
 * One-time/occasional maintenance tool: re-extracts every marketplace
 * listing's stored ZIP and recomputes auto_preview_entry with the current
 * inkwell_marketplace_extract_zip_preview() logic.
 *
 * Why this exists: the live-preview thumbnail (browser-chrome dots +
 * shrunk iframe, see inkwell_marketplace_thumb_html()) reads
 * auto_preview_entry from the DB. If a listing was uploaded before this
 * logic learned to look one level down for a wrapped top-level folder (a
 * common habit of "Download ZIP" exports), or a re-extraction was
 * interrupted, that column can point at a path that no longer exists —
 * the iframe then "loads" a 404/blank page and the thumbnail is just an
 * empty white box behind the chrome dots. Client-side (assets/js/mkt-thumb.js)
 * now detects that and falls back to the category icon, but the real fix
 * is recomputing the correct entry point, which is what this does.
 *
 * Safe to run repeatedly — it only touches listings that still have a
 * zip_file on disk, and re-extraction wipes and rebuilds that listing's
 * own preview folder before recomputing the entry point.
 */
$__hideTopbar = true;
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/marketplace.php';
inkwell_require_admin();

$pdo = inkwell_db();
$results = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'repair_all') {
  $listings = $pdo->query('SELECT id, slug, title, zip_file, auto_preview_entry FROM marketplace_listings')->fetchAll();
  foreach ($listings as $l) {
    if (empty($l['zip_file'])) continue; // nothing to re-extract from
    $before = $l['auto_preview_entry'];
    $after = inkwell_marketplace_extract_zip_preview($l['zip_file'], $l['slug']);
    $pdo->prepare('UPDATE marketplace_listings SET auto_preview_entry = ? WHERE id = ?')->execute([$after, $l['id']]);
    $results[] = [
      'title' => $l['title'],
      'slug' => $l['slug'],
      'before' => $before,
      'after' => $after,
      'changed' => $before !== $after,
    ];
  }
}

$pageTitle = 'Fix marketplace previews';
include __DIR__ . '/../includes/header.php';
?>
<main class="admin-main">
  <div class="admin-header-row">
    <div>
      <div class="crumb"><a href="/admin/index.php">Admin dashboard</a> / Fix marketplace previews</div>
      <h1>Fix marketplace live previews</h1>
      <p class="admin-sub" style="margin-top:2px;">Re-extracts every listing's stored ZIP and recomputes its live-preview entry point. Use this if a marketplace card's thumbnail shows a blank box instead of the actual page.</p>
    </div>
  </div>

  <section class="admin-card glass-card">
    <form method="post">
      <input type="hidden" name="action" value="repair_all">
      <button type="submit" class="btn primary">Re-extract all listings now</button>
    </form>
  </section>

  <?php if ($results): ?>
    <section class="admin-card glass-card" style="margin-top:16px;">
      <h2>Results</h2>
      <table class="admin-table">
        <thead><tr><th>Listing</th><th>Before</th><th>After</th><th>Status</th></tr></thead>
        <tbody>
          <?php foreach ($results as $r): ?>
            <tr>
              <td><?php echo htmlspecialchars($r['title']); ?> <span class="admin-sub">(<?php echo htmlspecialchars($r['slug']); ?>)</span></td>
              <td><code><?php echo htmlspecialchars($r['before'] ?: '—'); ?></code></td>
              <td><code><?php echo htmlspecialchars($r['after'] ?: '— (no static entry point found)'); ?></code></td>
              <td><?php echo $r['changed'] ? '✅ Updated' : 'No change needed'; ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </section>
  <?php endif; ?>
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?>
