<?php
/**
 * AJAX endpoint for the notification bell (see includes/notifications_bell.php
 * and the "Notification bell dropdown" block in assets/js/app.js). Called
 * with a POST of either:
 *   ids=1,2,3   — mark those specific notification ids read (must belong
 *                 to the logged-in user; enforced in inkwell_mark_notifications_read())
 *   all=1       — mark every unread notification read
 * Always responds with JSON; never redirects, since it's fetch()-only.
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/notifications.php';

header('Content-Type: application/json');

$me = inkwell_current_user();
if (!$me) {
  http_response_code(401);
  echo json_encode(['ok' => false, 'error' => 'Not logged in.']);
  exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok' => false, 'error' => 'POST required.']);
  exit;
}

if (!empty($_POST['all'])) {
  inkwell_mark_all_notifications_read($me['id']);
} else {
  $ids = array_filter(explode(',', $_POST['ids'] ?? ''), 'strlen');
  inkwell_mark_notifications_read($me['id'], $ids);
}

echo json_encode(['ok' => true]);
