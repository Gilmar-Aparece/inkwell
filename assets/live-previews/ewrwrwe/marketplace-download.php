<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/marketplace.php';

$me = inkwell_require_login();
$listing = inkwell_marketplace_get_listing((int) ($_GET['id'] ?? 0));

if (!$listing || empty($listing['zip_file'])) {
  http_response_code(404);
  die('File not found.');
}

$isSeller = (int) $listing['seller_id'] === (int) $me['id'];
$isAdmin = $me['role'] === 'admin';
$hasAccess = $isSeller || $isAdmin || inkwell_marketplace_user_has_access($listing['id'], $me['id']);

if (!$hasAccess) {
  http_response_code(403);
  die('You need to unlock this system with a code from the seller before you can download it.');
}

$path = INKWELL_MARKETPLACE_FILES_DIR . '/' . $listing['zip_file'];
if (!file_exists($path)) {
  http_response_code(404);
  die('File not found.');
}

$downloadName = preg_replace('/[^a-zA-Z0-9._-]/', '-', $listing['zip_original_name'] ?: ($listing['slug'] . '.zip'));

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $downloadName . '"');
header('Content-Length: ' . filesize($path));
header('X-Content-Type-Options: nosniff');
readfile($path);
exit;
