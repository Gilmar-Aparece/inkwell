<?php
/**
 * File-based storage for certificates, site config, and admin auth.
 * No database — everything lives under data-store/ as JSON, which is
 * blocked from direct web access by data-store/.htaccess. This keeps
 * Inkwell deployable to plain shared PHP hosting with zero setup, the
 * same philosophy as data/lessons.php.
 *
 * data-store/ must be writable by the web server (on InfinityFree this
 * is the default for folders you upload).
 */

define('INKWELL_DATA_DIR', __DIR__ . '/../data-store');
define('INKWELL_UPLOADS_DIR', __DIR__ . '/../assets/uploads');

function inkwell_store_path($filename) {
  return INKWELL_DATA_DIR . '/' . $filename;
}

function inkwell_read_json($filename, $default) {
  $path = inkwell_store_path($filename);
  if (!file_exists($path)) return $default;
  $raw = @file_get_contents($path);
  if ($raw === false || trim($raw) === '') return $default;
  $data = json_decode($raw, true);
  return $data === null ? $default : $data;
}

function inkwell_write_json($filename, $data) {
  if (!is_dir(INKWELL_DATA_DIR)) @mkdir(INKWELL_DATA_DIR, 0775, true);
  $path = inkwell_store_path($filename);
  $fp = fopen($path, 'c+');
  if (!$fp) return false;
  $ok = false;
  if (flock($fp, LOCK_EX)) {
    ftruncate($fp, 0);
    rewind($fp);
    $ok = fwrite($fp, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) !== false;
    fflush($fp);
    flock($fp, LOCK_UN);
  }
  fclose($fp);
  return $ok;
}

/* ---------------- Site config (signer name/title + signature image) ---------------- */

function inkwell_get_config() {
  $defaults = [
    'signer_name'  => 'Gilmar Aparece',
    'signer_title' => 'Founder, Inkwell',
    'signature_file' => null, // filename inside assets/uploads/, or null
  ];
  return array_merge($defaults, inkwell_read_json('config.json', []));
}

function inkwell_save_config($config) {
  $current = inkwell_get_config();
  return inkwell_write_json('config.json', array_merge($current, $config));
}

/* ---------------- Certificates ---------------- */

function inkwell_get_certificates() {
  return inkwell_read_json('certificates.json', []);
}

function inkwell_find_certificate($id) {
  foreach (inkwell_get_certificates() as $cert) {
    if ($cert['id'] === $id) return $cert;
  }
  return null;
}

function inkwell_add_certificate($name, $catKey, $catLabel, $score, $total) {
  $certs = inkwell_get_certificates();
  $cert = [
    'id'        => bin2hex(random_bytes(8)),
    'name'      => $name,
    'category'  => $catKey,
    'label'     => $catLabel,
    'score'     => $score,
    'total'     => $total,
    'percent'   => $total > 0 ? round(($score / $total) * 100) : 0,
    'issued_at' => date('Y-m-d'),
  ];
  $certs[] = $cert;
  inkwell_write_json('certificates.json', $certs);
  return $cert;
}

/* ---------------- Admin auth ---------------- */

function inkwell_admin_default_password() {
  return 'ChangeMe123!';
}

function inkwell_ensure_admin_account() {
  $admin = inkwell_read_json('admin.json', null);
  if ($admin === null || empty($admin['password_hash'])) {
    $admin = ['password_hash' => password_hash(inkwell_admin_default_password(), PASSWORD_DEFAULT)];
    inkwell_write_json('admin.json', $admin);
  }
  return $admin;
}

function inkwell_verify_admin_password($password) {
  $admin = inkwell_ensure_admin_account();
  return password_verify($password, $admin['password_hash']);
}

function inkwell_set_admin_password($password) {
  return inkwell_write_json('admin.json', ['password_hash' => password_hash($password, PASSWORD_DEFAULT)]);
}

function inkwell_admin_login() {
  if (session_status() !== PHP_SESSION_ACTIVE) session_start();
  $_SESSION['inkwell_admin'] = true;
}

function inkwell_admin_logout() {
  if (session_status() !== PHP_SESSION_ACTIVE) session_start();
  unset($_SESSION['inkwell_admin']);
}

function inkwell_is_admin() {
  if (session_status() !== PHP_SESSION_ACTIVE) session_start();
  return !empty($_SESSION['inkwell_admin']);
}

function inkwell_require_admin() {
  if (!inkwell_is_admin()) {
    header('Location: /admin/login.php');
    exit;
  }
}
