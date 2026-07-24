<?php
/**
 * Student/teacher-built systems marketplace.
 *
 * Flow (no payment gateway — same "manual GCash" philosophy as billing.php,
 * but peer-to-peer instead of admin-reviewed):
 *   1. A seller (student or teacher) lists a system: title, category,
 *      description, screenshots, a live preview link, and a downloadable
 *      ZIP of the source. They also put their own GCash number/name on
 *      the listing.
 *   2. A buyer pays the seller's GCash DIRECTLY (off-platform — Inkwell
 *      never touches the money).
 *   3. The seller generates a one-time unlock code from their dashboard
 *      and sends it to the buyer (chat, GCash message, etc).
 *   4. The buyer types the code into the listing page. Once redeemed,
 *      they permanently get the hosted preview link + a protected ZIP
 *      download, and the code can never be reused.
 *
 * Requires includes/db.php, includes/auth.php, includes/store.php to
 * already be loaded by the caller (same convention as billing.php).
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/store.php';
require_once __DIR__ . '/auth.php';

/** Where uploaded ZIPs live — NOT web-accessible directly, only through marketplace-download.php. */
define('INKWELL_MARKETPLACE_FILES_DIR', __DIR__ . '/../data-store/marketplace-files');

/**
 * Self-healing schema (same pattern as inkwell_ensure_billing_columns()) —
 * creates the marketplace tables the first time any marketplace function
 * runs, so there's no manual SQL import step. Safe to call on every request.
 */
/** Where auto-extracted static live previews live — web-accessible, but PHP execution is blocked (see .htaccess written below). */
define('INKWELL_MARKETPLACE_PREVIEWS_DIR', __DIR__ . '/../assets/live-previews');

function inkwell_ensure_marketplace_tables() {
  static $checked = false;
  if ($checked) return;
  $checked = true;
  $pdo = inkwell_db();

  $pdo->exec("CREATE TABLE IF NOT EXISTS marketplace_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    icon VARCHAR(10) DEFAULT NULL,
    sort_order INT NOT NULL DEFAULT 0
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

  $pdo->exec("CREATE TABLE IF NOT EXISTS marketplace_listings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    seller_id INT NOT NULL,
    category_id INT DEFAULT NULL,
    title VARCHAR(150) NOT NULL,
    slug VARCHAR(180) NOT NULL UNIQUE,
    tagline VARCHAR(200) DEFAULT NULL,
    description TEXT,
    tech_stack VARCHAR(255) DEFAULT NULL,
    price DECIMAL(10,2) NOT NULL DEFAULT 0,
    thumbnail VARCHAR(255) DEFAULT NULL,
    preview_url VARCHAR(255) DEFAULT NULL,
    zip_file VARCHAR(255) DEFAULT NULL,
    zip_original_name VARCHAR(255) DEFAULT NULL,
    auto_preview_entry VARCHAR(500) DEFAULT NULL,
    gcash_number VARCHAR(30) DEFAULT NULL,
    gcash_name VARCHAR(100) DEFAULT NULL,
    status ENUM('draft','active','hidden') NOT NULL DEFAULT 'active',
    views INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT current_timestamp(),
    updated_at DATETIME NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    KEY seller_idx (seller_id),
    KEY category_idx (category_id),
    KEY status_idx (status)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

  $pdo->exec("CREATE TABLE IF NOT EXISTS marketplace_screenshots (
    id INT AUTO_INCREMENT PRIMARY KEY,
    listing_id INT NOT NULL,
    filename VARCHAR(255) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    KEY listing_idx (listing_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

  $pdo->exec("CREATE TABLE IF NOT EXISTS marketplace_codes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    listing_id INT NOT NULL,
    code VARCHAR(20) NOT NULL UNIQUE,
    status ENUM('unused','used') NOT NULL DEFAULT 'unused',
    buyer_id INT DEFAULT NULL,
    redeemed_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT current_timestamp(),
    KEY listing_idx (listing_id),
    KEY buyer_idx (buyer_id),
    KEY status_idx (status)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

  // Seed default categories once (only if the table is empty — an admin
  // may have edited/removed some since, so this never re-adds them).
  $count = (int) $pdo->query('SELECT COUNT(*) FROM marketplace_categories')->fetchColumn();
  if ($count === 0) {
    $defaults = [
      ['Web Apps', 'web-apps', '🌐', 1],
      ['School / Academic Systems', 'academic-systems', '🎓', 2],
      ['E-Commerce', 'e-commerce', '🛒', 3],
      ['Games', 'games', '🎮', 4],
      ['Mobile Apps', 'mobile-apps', '📱', 5],
      ['Utilities & Tools', 'utilities', '🛠️', 6],
      ['Other', 'other', '✨', 7],
    ];
    $stmt = $pdo->prepare('INSERT INTO marketplace_categories (name, slug, icon, sort_order) VALUES (?, ?, ?, ?)');
    foreach ($defaults as $d) $stmt->execute($d);
  }

  // Self-heal for installs that created marketplace_listings before
  // auto_preview_entry existed (same pattern as inkwell_ensure_billing_columns()).
  try {
    $cols = $pdo->query('SHOW COLUMNS FROM marketplace_listings')->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('auto_preview_entry', $cols, true)) {
      $pdo->exec("ALTER TABLE marketplace_listings ADD COLUMN auto_preview_entry VARCHAR(500) DEFAULT NULL");
    }
  } catch (Exception $e) {}

  if (!is_dir(INKWELL_MARKETPLACE_FILES_DIR)) {
    @mkdir(INKWELL_MARKETPLACE_FILES_DIR, 0775, true);
  }
  $htaccess = INKWELL_MARKETPLACE_FILES_DIR . '/.htaccess';
  if (!file_exists($htaccess)) {
    @file_put_contents($htaccess, "Require all denied\nDeny from all\n");
  }

  // Live-preview folder IS meant to be publicly browsable (that's the whole
  // point — a Vercel-style "open the demo in a new tab" link), but a ZIP
  // is arbitrary code a student uploaded, so we never let it run as PHP on
  // this server — only static HTML/CSS/JS/images get served. Same
  // safety pattern as assets/uploads/.htaccess.
  if (!is_dir(INKWELL_MARKETPLACE_PREVIEWS_DIR)) {
    @mkdir(INKWELL_MARKETPLACE_PREVIEWS_DIR, 0775, true);
  }
  $previewHtaccess = INKWELL_MARKETPLACE_PREVIEWS_DIR . '/.htaccess';
  if (!file_exists($previewHtaccess)) {
    @file_put_contents($previewHtaccess,
      "<FilesMatch \"\\.(php|phtml|php\\d|cgi|pl|py|sh)$\">\n  Require all denied\n</FilesMatch>\nOptions -Indexes\n");
  }
}

/* ---------------- Categories ---------------- */

function inkwell_marketplace_categories() {
  inkwell_ensure_marketplace_tables();
  $pdo = inkwell_db();
  return $pdo->query('SELECT * FROM marketplace_categories ORDER BY sort_order ASC, name ASC')->fetchAll();
}

function inkwell_marketplace_category($id) {
  if (!$id) return null;
  inkwell_ensure_marketplace_tables();
  $pdo = inkwell_db();
  $stmt = $pdo->prepare('SELECT * FROM marketplace_categories WHERE id = ?');
  $stmt->execute([(int) $id]);
  return $stmt->fetch() ?: null;
}

function inkwell_marketplace_category_by_slug($slug) {
  inkwell_ensure_marketplace_tables();
  $pdo = inkwell_db();
  $stmt = $pdo->prepare('SELECT * FROM marketplace_categories WHERE slug = ?');
  $stmt->execute([$slug]);
  return $stmt->fetch() ?: null;
}

/* ---------------- Slugs ---------------- */

function inkwell_marketplace_slugify($title, $excludeId = null) {
  inkwell_ensure_marketplace_tables();
  $base = strtolower(trim($title));
  $base = preg_replace('/[^a-z0-9]+/', '-', $base);
  $base = trim($base, '-');
  if ($base === '') $base = 'system';
  $pdo = inkwell_db();
  $slug = $base;
  $i = 1;
  while (true) {
    $sql = 'SELECT id FROM marketplace_listings WHERE slug = ?';
    $params = [$slug];
    if ($excludeId) { $sql .= ' AND id != ?'; $params[] = (int) $excludeId; }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    if (!$stmt->fetch()) return $slug;
    $i++;
    $slug = $base . '-' . $i;
  }
}

/* ---------------- File uploads ---------------- */

/** Thumbnail / screenshot images — reuses the same constraints as school logos. */
function inkwell_marketplace_handle_image_upload($fileField) {
  if (empty($_FILES[$fileField]['name'])) return ['ok' => true, 'filename' => null];
  $err = $_FILES[$fileField]['error'];
  if ($err !== UPLOAD_ERR_OK) {
    return ['ok' => false, 'error' => 'Image upload failed (error code ' . $err . ').'];
  }
  $allowed = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];
  $tmpPath = $_FILES[$fileField]['tmp_name'];
  $info = @getimagesize($tmpPath);
  $mime = $info['mime'] ?? '';
  if ($_FILES[$fileField]['size'] > 3 * 1024 * 1024) {
    return ['ok' => false, 'error' => 'Image must be under 3MB.'];
  }
  if (!isset($allowed[$mime])) {
    return ['ok' => false, 'error' => 'Image must be a PNG, JPG, or WEBP file.'];
  }
  if (!is_dir(INKWELL_UPLOADS_DIR)) @mkdir(INKWELL_UPLOADS_DIR, 0775, true);
  $filename = 'mkt_' . bin2hex(random_bytes(8)) . '.' . $allowed[$mime];
  if (!move_uploaded_file($tmpPath, INKWELL_UPLOADS_DIR . '/' . $filename)) {
    return ['ok' => false, 'error' => 'Could not save the image.'];
  }
  return ['ok' => true, 'filename' => $filename];
}

/** The ZIP of the system's source — stored OUTSIDE the web root's reach (protected folder). */
function inkwell_marketplace_handle_zip_upload($fileField = 'zip_file') {
  if (empty($_FILES[$fileField]['name'])) return ['ok' => true, 'filename' => null, 'original' => null];
  $err = $_FILES[$fileField]['error'];
  if ($err !== UPLOAD_ERR_OK) {
    return ['ok' => false, 'error' => 'ZIP upload failed (error code ' . $err . ').'];
  }
  $original = $_FILES[$fileField]['name'];
  $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
  if ($ext !== 'zip') {
    return ['ok' => false, 'error' => 'The system file must be a .zip.'];
  }
  if ($_FILES[$fileField]['size'] > 40 * 1024 * 1024) {
    return ['ok' => false, 'error' => 'ZIP must be under 40MB.'];
  }
  inkwell_ensure_marketplace_tables();
  $filename = bin2hex(random_bytes(12)) . '.zip';
  if (!move_uploaded_file($_FILES[$fileField]['tmp_name'], INKWELL_MARKETPLACE_FILES_DIR . '/' . $filename)) {
    return ['ok' => false, 'error' => 'Could not save the ZIP file.'];
  }
  return ['ok' => true, 'filename' => $filename, 'original' => basename($original)];
}

/**
 * Auto "Live Demo" preview — extracts the ZIP's static files (HTML/CSS/JS/
 * images only, PHP execution is blocked by the .htaccess above) into a
 * public folder so buyers get a click-to-preview link without the seller
 * hosting anything themselves, similar in spirit to a Vercel preview
 * deploy. Only works for systems whose ZIP includes a browsable index.html
 * — a pure server-side/database-backed system won't have one, and that's
 * fine: the seller can still set an external "preview_url" instead, or
 * skip live preview and rely on screenshots + the ZIP download.
 * Returns the relative entry file path (e.g. "index.html") or null.
 */
function inkwell_marketplace_extract_zip_preview($storedZipFilename, $slug) {
  if (!class_exists('ZipArchive') || !$storedZipFilename) return null;

  $zipPath = INKWELL_MARKETPLACE_FILES_DIR . '/' . $storedZipFilename;
  if (!file_exists($zipPath)) return null;

  $destDir = INKWELL_MARKETPLACE_PREVIEWS_DIR . '/' . $slug;
  // Wipe any previous extraction (re-upload / edit case).
  if (is_dir($destDir)) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($destDir, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($it as $f) { $f->isDir() ? @rmdir($f->getRealPath()) : @unlink($f->getRealPath()); }
    @rmdir($destDir);
  }

  $zip = new ZipArchive();
  if ($zip->open($zipPath) !== true) return null;
  if (!@mkdir($destDir, 0775, true)) { $zip->close(); return null; }

  $realDest = realpath($destDir);
  for ($i = 0; $i < $zip->numFiles; $i++) {
    $name = $zip->getNameIndex($i);
    // Zip-slip guard: refuse absolute paths or ../ traversal.
    if ($name === false || strpos($name, '..') !== false || strpos($name, "\0") !== false) continue;
    $target = $destDir . '/' . $name;
    if (substr($name, -1) === '/') { @mkdir($target, 0775, true); continue; }
    @mkdir(dirname($target), 0775, true);
    $zip->extractTo($destDir, $name);
  }
  $zip->close();

  // Find an entry point: index.html at the root, or one level down if the
  // whole ZIP is wrapped in a single top-level folder (common export habit).
  $candidates = [$destDir . '/index.html', $destDir . '/index.htm'];
  foreach (glob($destDir . '/*', GLOB_ONLYDIR) ?: [] as $sub) {
    $candidates[] = $sub . '/index.html';
    $candidates[] = $sub . '/index.htm';
  }
  foreach ($candidates as $c) {
    if (file_exists($c)) {
      return ltrim(str_replace($destDir, '', $c), '/');
    }
  }
  return null; // no static entry point found — not a browsable ZIP
}

function inkwell_marketplace_delete_preview_dir($slug) {
  $dir = INKWELL_MARKETPLACE_PREVIEWS_DIR . '/' . $slug;
  if (!is_dir($dir)) return;
  $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
  foreach ($it as $f) { $f->isDir() ? @rmdir($f->getRealPath()) : @unlink($f->getRealPath()); }
  @rmdir($dir);
}

/** Preview link to show as "Live Demo" — an external URL the seller set, or the auto-extracted static preview. */
function inkwell_marketplace_live_demo_url($listing) {
  if (!empty($listing['preview_url'])) return $listing['preview_url'];
  if (!empty($listing['auto_preview_entry'])) {
    return '/assets/live-previews/' . rawurlencode($listing['slug']) . '/' . $listing['auto_preview_entry'];
  }
  return null;
}

function inkwell_marketplace_relative_time($datetime) {
  $diff = time() - strtotime($datetime);
  if ($diff < 60) return 'just now';
  if ($diff < 3600) return floor($diff / 60) . 'm ago';
  if ($diff < 86400) return floor($diff / 3600) . 'h ago';
  if ($diff < 86400 * 30) return floor($diff / 86400) . 'd ago';
  if ($diff < 86400 * 365) return floor($diff / (86400 * 30)) . 'mo ago';
  return floor($diff / (86400 * 365)) . 'y ago';
}



/** Who is allowed to sell on the marketplace — students and teachers. */
function inkwell_marketplace_can_sell($user) {
  return $user && in_array($user['role'], ['student', 'teacher'], true) && $user['status'] === 'active';
}

function inkwell_marketplace_create_listing($seller, $data) {
  if (!inkwell_marketplace_can_sell($seller)) return ['ok' => false, 'error' => 'Only students and teachers can list a system for sale.'];
  inkwell_ensure_marketplace_tables();

  $title = trim($data['title'] ?? '');
  if ($title === '') return ['ok' => false, 'error' => 'Title is required.'];
  $description = trim($data['description'] ?? '');
  if ($description === '') return ['ok' => false, 'error' => 'A description is required.'];
  $price = (float) ($data['price'] ?? 0);
  if ($price < 0) $price = 0;
  $gcashNumber = trim($data['gcash_number'] ?? '');
  $gcashName = trim($data['gcash_name'] ?? '');
  if ($price > 0 && ($gcashNumber === '' || $gcashName === '')) {
    return ['ok' => false, 'error' => 'Add your GCash number and account name so buyers know how to pay you.'];
  }

  $thumb = inkwell_marketplace_handle_image_upload('thumbnail');
  if (!$thumb['ok']) return $thumb;
  $zip = inkwell_marketplace_handle_zip_upload('zip_file');
  if (!$zip['ok']) return $zip;

  $slug = inkwell_marketplace_slugify($title);
  $pdo = inkwell_db();
  $stmt = $pdo->prepare('INSERT INTO marketplace_listings
    (seller_id, category_id, title, slug, tagline, description, tech_stack, price, thumbnail, preview_url, zip_file, zip_original_name, gcash_number, gcash_name, status)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
  $stmt->execute([
    (int) $seller['id'],
    !empty($data['category_id']) ? (int) $data['category_id'] : null,
    $title,
    $slug,
    trim($data['tagline'] ?? '') ?: null,
    $description,
    trim($data['tech_stack'] ?? '') ?: null,
    $price,
    $thumb['filename'],
    trim($data['preview_url'] ?? '') ?: null,
    $zip['filename'],
    $zip['original'],
    $gcashNumber ?: null,
    $gcashName ?: null,
    ($data['status'] ?? 'active') === 'draft' ? 'draft' : 'active',
  ]);
  $id = (int) $pdo->lastInsertId();

  inkwell_marketplace_save_screenshots($id, 'screenshots');

  if ($zip['filename']) {
    $entry = inkwell_marketplace_extract_zip_preview($zip['filename'], $slug);
    if ($entry) {
      $pdo->prepare('UPDATE marketplace_listings SET auto_preview_entry = ? WHERE id = ?')->execute([$entry, $id]);
    }
  }

  return ['ok' => true, 'id' => $id, 'slug' => $slug];
}

function inkwell_marketplace_save_screenshots($listingId, $fileField) {
  if (empty($_FILES[$fileField]) || empty($_FILES[$fileField]['name'][0])) return;
  $pdo = inkwell_db();
  $existing = (int) $pdo->query('SELECT COUNT(*) FROM marketplace_screenshots WHERE listing_id = ' . (int) $listingId)->fetchColumn();
  $names = $_FILES[$fileField]['name'];
  $max = min(count($names), max(0, 6 - $existing)); // cap 6 screenshots per listing
  for ($i = 0; $i < $max; $i++) {
    if (empty($names[$i])) continue;
    $single = [
      'name' => $names[$i],
      'type' => $_FILES[$fileField]['type'][$i],
      'tmp_name' => $_FILES[$fileField]['tmp_name'][$i],
      'error' => $_FILES[$fileField]['error'][$i],
      'size' => $_FILES[$fileField]['size'][$i],
    ];
    $_FILES['__mkt_single'] = $single;
    $up = inkwell_marketplace_handle_image_upload('__mkt_single');
    if ($up['ok'] && $up['filename']) {
      $pdo->prepare('INSERT INTO marketplace_screenshots (listing_id, filename, sort_order) VALUES (?, ?, ?)')
          ->execute([(int) $listingId, $up['filename'], $existing + $i]);
    }
  }
  unset($_FILES['__mkt_single']);
}

function inkwell_marketplace_update_listing($id, $seller, $data) {
  $listing = inkwell_marketplace_get_listing($id);
  if (!$listing || (int) $listing['seller_id'] !== (int) $seller['id']) {
    return ['ok' => false, 'error' => 'Listing not found.'];
  }
  $title = trim($data['title'] ?? '');
  if ($title === '') return ['ok' => false, 'error' => 'Title is required.'];
  $description = trim($data['description'] ?? '');
  if ($description === '') return ['ok' => false, 'error' => 'A description is required.'];
  $price = (float) ($data['price'] ?? 0);
  if ($price < 0) $price = 0;
  $gcashNumber = trim($data['gcash_number'] ?? '');
  $gcashName = trim($data['gcash_name'] ?? '');
  if ($price > 0 && ($gcashNumber === '' || $gcashName === '')) {
    return ['ok' => false, 'error' => 'Add your GCash number and account name so buyers know how to pay you.'];
  }

  $thumb = inkwell_marketplace_handle_image_upload('thumbnail');
  if (!$thumb['ok']) return $thumb;
  $zip = inkwell_marketplace_handle_zip_upload('zip_file');
  if (!$zip['ok']) return $zip;

  $pdo = inkwell_db();
  $slug = $listing['slug'];
  if ($title !== $listing['title']) $slug = inkwell_marketplace_slugify($title, $id);

  $sql = 'UPDATE marketplace_listings SET category_id=?, title=?, slug=?, tagline=?, description=?, tech_stack=?, price=?, preview_url=?, gcash_number=?, gcash_name=?, status=?';
  $params = [
    !empty($data['category_id']) ? (int) $data['category_id'] : null,
    $title, $slug,
    trim($data['tagline'] ?? '') ?: null,
    $description,
    trim($data['tech_stack'] ?? '') ?: null,
    $price,
    trim($data['preview_url'] ?? '') ?: null,
    $gcashNumber ?: null,
    $gcashName ?: null,
    in_array($data['status'] ?? '', ['draft', 'active', 'hidden'], true) ? $data['status'] : $listing['status'],
  ];
  if ($thumb['filename']) {
    if (!empty($listing['thumbnail'])) inkwell_delete_upload($listing['thumbnail']);
    $sql .= ', thumbnail=?';
    $params[] = $thumb['filename'];
  }
  if ($zip['filename']) {
    if (!empty($listing['zip_file'])) @unlink(INKWELL_MARKETPLACE_FILES_DIR . '/' . $listing['zip_file']);
    $sql .= ', zip_file=?, zip_original_name=?';
    $params[] = $zip['filename'];
    $params[] = $zip['original'];
  }
  $sql .= ' WHERE id = ?';
  $params[] = (int) $id;
  $pdo->prepare($sql)->execute($params);

  inkwell_marketplace_save_screenshots($id, 'screenshots');

  // Keep the live-preview folder name in sync with the slug, and re-extract
  // if a fresh ZIP was uploaded.
  if ($slug !== $listing['slug'] && is_dir(INKWELL_MARKETPLACE_PREVIEWS_DIR . '/' . $listing['slug'])) {
    @rename(INKWELL_MARKETPLACE_PREVIEWS_DIR . '/' . $listing['slug'], INKWELL_MARKETPLACE_PREVIEWS_DIR . '/' . $slug);
  }
  if ($zip['filename']) {
    $entry = inkwell_marketplace_extract_zip_preview($zip['filename'], $slug);
    $pdo->prepare('UPDATE marketplace_listings SET auto_preview_entry = ? WHERE id = ?')->execute([$entry, $id]);
  }

  return ['ok' => true, 'slug' => $slug];
}

function inkwell_marketplace_delete_listing($id, $seller) {
  $listing = inkwell_marketplace_get_listing($id);
  if (!$listing || (int) $listing['seller_id'] !== (int) $seller['id']) return ['ok' => false, 'error' => 'Listing not found.'];
  $pdo = inkwell_db();
  foreach (inkwell_marketplace_screenshots($id) as $s) inkwell_delete_upload($s['filename']);
  if (!empty($listing['thumbnail'])) inkwell_delete_upload($listing['thumbnail']);
  if (!empty($listing['zip_file'])) @unlink(INKWELL_MARKETPLACE_FILES_DIR . '/' . $listing['zip_file']);
  inkwell_marketplace_delete_preview_dir($listing['slug']);
  $pdo->prepare('DELETE FROM marketplace_screenshots WHERE listing_id = ?')->execute([(int) $id]);
  $pdo->prepare('DELETE FROM marketplace_codes WHERE listing_id = ?')->execute([(int) $id]);
  $pdo->prepare('DELETE FROM marketplace_listings WHERE id = ?')->execute([(int) $id]);
  return ['ok' => true];
}

function inkwell_marketplace_get_listing($id) {
  inkwell_ensure_marketplace_tables();
  $pdo = inkwell_db();
  $stmt = $pdo->prepare('SELECT * FROM marketplace_listings WHERE id = ?');
  $stmt->execute([(int) $id]);
  return $stmt->fetch() ?: null;
}

function inkwell_marketplace_get_listing_by_slug($slug) {
  inkwell_ensure_marketplace_tables();
  $pdo = inkwell_db();
  $stmt = $pdo->prepare('SELECT * FROM marketplace_listings WHERE slug = ?');
  $stmt->execute([$slug]);
  return $stmt->fetch() ?: null;
}

function inkwell_marketplace_screenshots($listingId) {
  inkwell_ensure_marketplace_tables();
  $pdo = inkwell_db();
  $stmt = $pdo->prepare('SELECT * FROM marketplace_screenshots WHERE listing_id = ? ORDER BY sort_order ASC');
  $stmt->execute([(int) $listingId]);
  return $stmt->fetchAll();
}

function inkwell_marketplace_seller_name($sellerId) {
  $pdo = inkwell_db();
  $stmt = $pdo->prepare('SELECT name, role, avatar FROM users WHERE id = ?');
  $stmt->execute([(int) $sellerId]);
  return $stmt->fetch() ?: null;
}

/** Public browse listing — only active listings, with optional category/search filters. */
function inkwell_marketplace_list_listings($filters = []) {
  inkwell_ensure_marketplace_tables();
  $pdo = inkwell_db();
  $sql = "SELECT l.*, u.name AS seller_name, u.role AS seller_role, c.name AS category_name, c.icon AS category_icon
          FROM marketplace_listings l
          JOIN users u ON u.id = l.seller_id
          LEFT JOIN marketplace_categories c ON c.id = l.category_id
          WHERE l.status = 'active'";
  $params = [];
  if (!empty($filters['category'])) {
    $sql .= ' AND c.slug = ?';
    $params[] = $filters['category'];
  }
  if (!empty($filters['search'])) {
    $sql .= ' AND (l.title LIKE ? OR l.tagline LIKE ? OR l.tech_stack LIKE ?)';
    $like = '%' . $filters['search'] . '%';
    $params[] = $like; $params[] = $like; $params[] = $like;
  }
  $sql .= ' ORDER BY l.created_at DESC';
  $stmt = $pdo->prepare($sql);
  $stmt->execute($params);
  return $stmt->fetchAll();
}

function inkwell_marketplace_seller_listings($sellerId) {
  inkwell_ensure_marketplace_tables();
  $pdo = inkwell_db();
  $sql = "SELECT l.*, c.name AS category_name FROM marketplace_listings l
          LEFT JOIN marketplace_categories c ON c.id = l.category_id
          WHERE l.seller_id = ? ORDER BY l.created_at DESC";
  $stmt = $pdo->prepare($sql);
  $stmt->execute([(int) $sellerId]);
  return $stmt->fetchAll();
}

function inkwell_marketplace_increment_views($id) {
  $pdo = inkwell_db();
  $pdo->prepare('UPDATE marketplace_listings SET views = views + 1 WHERE id = ?')->execute([(int) $id]);
}

/* ---------------- Unlock codes ---------------- */

function inkwell_marketplace_generate_code_string() {
  // Uppercase letters + digits, excludes ambiguous chars (0,O,1,I).
  $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
  $parts = [];
  for ($p = 0; $p < 3; $p++) {
    $chunk = '';
    for ($i = 0; $i < 4; $i++) $chunk .= $chars[random_int(0, strlen($chars) - 1)];
    $parts[] = $chunk;
  }
  return implode('-', $parts);
}

/** Seller generates $count fresh one-time unlock codes for their own listing. */
function inkwell_marketplace_generate_codes($listingId, $seller, $count = 1) {
  $listing = inkwell_marketplace_get_listing($listingId);
  if (!$listing || (int) $listing['seller_id'] !== (int) $seller['id']) return ['ok' => false, 'error' => 'Listing not found.'];
  $count = max(1, min(50, (int) $count));
  $pdo = inkwell_db();
  $codes = [];
  $stmt = $pdo->prepare('INSERT INTO marketplace_codes (listing_id, code) VALUES (?, ?)');
  for ($i = 0; $i < $count; $i++) {
    $tries = 0;
    while (true) {
      $code = inkwell_marketplace_generate_code_string();
      try {
        $stmt->execute([(int) $listingId, $code]);
        $codes[] = $code;
        break;
      } catch (Exception $e) {
        $tries++;
        if ($tries > 5) break; // extremely unlikely collision loop
      }
    }
  }
  return ['ok' => true, 'codes' => $codes];
}

function inkwell_marketplace_listing_codes($listingId, $seller) {
  $listing = inkwell_marketplace_get_listing($listingId);
  if (!$listing || (int) $listing['seller_id'] !== (int) $seller['id']) return [];
  $pdo = inkwell_db();
  $sql = "SELECT mc.*, u.name AS buyer_name FROM marketplace_codes mc
          LEFT JOIN users u ON u.id = mc.buyer_id
          WHERE mc.listing_id = ? ORDER BY mc.created_at DESC";
  $stmt = $pdo->prepare($sql);
  $stmt->execute([(int) $listingId]);
  return $stmt->fetchAll();
}

function inkwell_marketplace_delete_code($codeId, $seller) {
  $pdo = inkwell_db();
  $stmt = $pdo->prepare('SELECT mc.*, l.seller_id FROM marketplace_codes mc JOIN marketplace_listings l ON l.id = mc.listing_id WHERE mc.id = ?');
  $stmt->execute([(int) $codeId]);
  $row = $stmt->fetch();
  if (!$row || (int) $row['seller_id'] !== (int) $seller['id'] || $row['status'] !== 'unused') {
    return ['ok' => false, 'error' => 'Cannot remove that code.'];
  }
  $pdo->prepare('DELETE FROM marketplace_codes WHERE id = ?')->execute([(int) $codeId]);
  return ['ok' => true];
}

/** Buyer redeems a code on a listing's page. One-time use, tied to that listing only. */
function inkwell_marketplace_redeem_code($listingId, $buyer, $rawCode) {
  inkwell_ensure_marketplace_tables();
  $code = strtoupper(trim($rawCode));
  if ($code === '') return ['ok' => false, 'error' => 'Enter the unlock code your seller gave you.'];
  $pdo = inkwell_db();
  $stmt = $pdo->prepare('SELECT * FROM marketplace_codes WHERE listing_id = ? AND code = ?');
  $stmt->execute([(int) $listingId, $code]);
  $row = $stmt->fetch();
  if (!$row) return ['ok' => false, 'error' => 'That code doesn\'t match this system. Double-check it with your seller.'];
  if ($row['status'] === 'used') return ['ok' => false, 'error' => 'That code has already been used.'];
  $upd = $pdo->prepare("UPDATE marketplace_codes SET status='used', buyer_id=?, redeemed_at=NOW() WHERE id=? AND status='unused'");
  $upd->execute([(int) $buyer['id'], (int) $row['id']]);
  if ($upd->rowCount() === 0) return ['ok' => false, 'error' => 'That code has already been used.'];
  return ['ok' => true];
}

function inkwell_marketplace_user_has_access($listingId, $userId) {
  if (!$userId) return false;
  inkwell_ensure_marketplace_tables();
  $pdo = inkwell_db();
  $stmt = $pdo->prepare("SELECT id FROM marketplace_codes WHERE listing_id = ? AND buyer_id = ? AND status = 'used' LIMIT 1");
  $stmt->execute([(int) $listingId, (int) $userId]);
  return (bool) $stmt->fetch();
}

/** Everything a buyer has unlocked, for a "My purchased systems" library view. */
function inkwell_marketplace_buyer_library($buyerId) {
  inkwell_ensure_marketplace_tables();
  $pdo = inkwell_db();
  $sql = "SELECT l.*, u.name AS seller_name, mc.redeemed_at
          FROM marketplace_codes mc
          JOIN marketplace_listings l ON l.id = mc.listing_id
          JOIN users u ON u.id = l.seller_id
          WHERE mc.buyer_id = ? AND mc.status = 'used'
          ORDER BY mc.redeemed_at DESC";
  $stmt = $pdo->prepare($sql);
  $stmt->execute([(int) $buyerId]);
  return $stmt->fetchAll();
}

/* ---------------- Seller earnings dashboard ---------------- */

/**
 * Earnings are self-reported by design (payment happens off-platform,
 * directly to the seller's GCash) — a "sale" here means a code for that
 * listing has been redeemed, at that listing's price at redemption time.
 */
function inkwell_marketplace_seller_earnings($sellerId) {
  inkwell_ensure_marketplace_tables();
  $pdo = inkwell_db();

  $sql = "SELECT l.id, l.title, l.price, l.thumbnail,
            COUNT(mc.id) AS total_codes,
            SUM(CASE WHEN mc.status = 'used' THEN 1 ELSE 0 END) AS sold,
            SUM(CASE WHEN mc.status = 'used' THEN l.price ELSE 0 END) AS revenue
          FROM marketplace_listings l
          LEFT JOIN marketplace_codes mc ON mc.listing_id = l.id
          WHERE l.seller_id = ?
          GROUP BY l.id
          ORDER BY revenue DESC";
  $stmt = $pdo->prepare($sql);
  $stmt->execute([(int) $sellerId]);
  $perListing = $stmt->fetchAll();

  $totalRevenue = 0.0;
  $totalSold = 0;
  foreach ($perListing as $row) {
    $totalRevenue += (float) $row['revenue'];
    $totalSold += (int) $row['sold'];
  }

  // Revenue trend, last 6 months.
  $trend = [];
  for ($i = 5; $i >= 0; $i--) {
    $start = date('Y-m-01 00:00:00', strtotime("-{$i} months"));
    $end = date('Y-m-01 00:00:00', strtotime('-' . ($i - 1) . ' months'));
    $label = date('M', strtotime($start));
    $stmt2 = $pdo->prepare("SELECT COALESCE(SUM(l.price),0) FROM marketplace_codes mc
                             JOIN marketplace_listings l ON l.id = mc.listing_id
                             WHERE l.seller_id = ? AND mc.status = 'used' AND mc.redeemed_at >= ? AND mc.redeemed_at < ?");
    $stmt2->execute([(int) $sellerId, $start, $end]);
    $trend[$label] = (float) $stmt2->fetchColumn();
  }

  return [
    'total_revenue' => $totalRevenue,
    'total_sold' => $totalSold,
    'listings' => $perListing,
    'trend' => $trend,
  ];
}
