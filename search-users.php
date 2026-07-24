<?php
/**
 * AJAX endpoint for the topbar "Find people" search (see
 * includes/user_search_widget.php + the "Topbar user search" block in
 * assets/js/app.js). Returns a list of matching active users as an HTML
 * fragment; each result is a [data-post-user-id] trigger that opens the
 * same profile popup already used on posts.php (see
 * includes/post_author_profile_modal.php + assets/js/post-profile.js),
 * so "search a user, see their profile" reuses the existing profile
 * modal instead of a new one.
 *
 * Reuses inkwell_search_messageable_users() from includes/messages.php —
 * it already does exactly what this needs (active users, excluding me,
 * matched by name/email) even though it was written for the "new
 * message" picker.
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/messages.php';

header('Content-Type: application/json');

$me = inkwell_current_user();
if (!$me) {
  http_response_code(401);
  echo json_encode(['ok' => false, 'error' => 'Please log in to search.']);
  exit;
}

$query = trim((string) ($_GET['q'] ?? $_POST['q'] ?? ''));
if ($query === '') {
  echo json_encode(['ok' => true, 'html' => '']);
  exit;
}

$results = inkwell_search_messageable_users($query, $me['id'], 8);

if (!$results) {
  echo json_encode(['ok' => true, 'html' => '<p class="nav-search-empty">No users found for "' . htmlspecialchars($query) . '".</p>']);
  exit;
}

$html = '';
foreach ($results as $u) {
  $html .= '<button type="button" class="nav-search-result" data-modal-open="postAuthorProfileModal" data-post-user-id="' . (int) $u['id'] . '">'
    . '<span class="nav-search-avatar">'
    . (!empty($u['avatar'])
        ? '<img src="/assets/uploads/' . htmlspecialchars($u['avatar']) . '" alt="" loading="lazy">'
        : htmlspecialchars(strtoupper(substr($u['name'], 0, 1))))
    . '</span>'
    . '<span class="nav-search-body">'
    . '<strong>' . htmlspecialchars($u['name']) . '</strong>'
    . '<span>' . htmlspecialchars(ucfirst($u['role'])) . '</span>'
    . '</span>'
    . '</button>';
}

echo json_encode(['ok' => true, 'html' => $html]);
