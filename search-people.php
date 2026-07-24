<?php
// Inside/dashboard page — suppress the marketing topbar (see includes/header.php).
$__hideTopbar = true;
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/messages.php';

$user = inkwell_require_login();

$search = trim($_GET['q'] ?? '');
$roleFilter = trim($_GET['role'] ?? '');
$validRoles = ['student', 'teacher', 'dean', 'registrar', 'admin'];
if (!in_array($roleFilter, $validRoles, true)) $roleFilter = '';

// A blank search with no role picked would otherwise return "everyone
// active on the platform" sorted alphabetically — still useful as a
// browse view, so we don't force a query, just cap it higher.
$people = inkwell_search_messageable_users($search, $user['id'], 60, $roleFilter ?: null);

$pageTitle = 'Find people';
include __DIR__ . '/includes/header.php';
$driveActive = 'search-people';
$driveCrumbs = [['label' => 'Home', 'href' => '/index.php'], ['label' => 'Find people']];
$driveTitle = 'Find people';
$driveSubtitle = 'Search any student, teacher, dean, registrar, or admin on Inkwell — view their profile or start a conversation.';
include __DIR__ . '/includes/drive_shell_top.php';
?>
<style>
  .people-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 14px; }
  .people-card { display: flex; flex-direction: column; align-items: center; text-align: center; gap: 10px; padding: 18px 14px; }
  .people-card-avatar { width: 64px; height: 64px; border-radius: 50%; background: var(--surface-2); display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 1.3rem; overflow: hidden; }
  .people-card-avatar img { width: 100%; height: 100%; object-fit: cover; }
  .people-card h3 { margin: 0; font-size: 0.98rem; }
  .people-card-actions { display: flex; gap: 8px; width: 100%; margin-top: 4px; }
  .people-card-actions .btn { flex: 1; padding: 7px 10px; font-size: 0.82rem; }
  .people-empty { text-align: center; padding: 40px 20px; }
</style>

<div class="mkt-toolbar">
  <form method="get" class="mkt-search" role="search">
    <?php if ($roleFilter): ?><input type="hidden" name="role" value="<?php echo htmlspecialchars($roleFilter); ?>"><?php endif; ?>
    <input type="text" name="q" placeholder="Search by name or email…" value="<?php echo htmlspecialchars($search); ?>">
    <button type="submit" class="btn">Search</button>
  </form>
</div>

<div class="mkt-cats">
  <a href="/search-people.php<?php echo $search ? '?q=' . urlencode($search) : ''; ?>" class="mkt-cat-chip<?php echo $roleFilter === '' ? ' active' : ''; ?>">All</a>
  <?php foreach ($validRoles as $r): ?>
    <a href="/search-people.php?role=<?php echo urlencode($r); ?><?php echo $search ? '&q=' . urlencode($search) : ''; ?>"
       class="mkt-cat-chip<?php echo $roleFilter === $r ? ' active' : ''; ?>"><?php echo htmlspecialchars(ucfirst($r)); ?>s</a>
  <?php endforeach; ?>
</div>

<?php if (!$people): ?>
  <section class="admin-card glass-card people-empty">
    <p class="admin-sub">No one found<?php echo $search ? ' matching "' . htmlspecialchars($search) . '"' : ''; ?><?php echo $roleFilter ? ' in that role' : ''; ?>.</p>
  </section>
<?php else: ?>
  <div class="people-grid" id="peopleGrid" data-paginate="18">
    <?php foreach ($people as $p): ?>
      <div class="admin-card glass-card people-card" data-filter-row>
        <span class="people-card-avatar">
          <?php if (!empty($p['avatar'])): ?>
            <img src="/assets/uploads/<?php echo htmlspecialchars($p['avatar']); ?>" alt="" loading="lazy">
          <?php else: ?>
            <?php echo htmlspecialchars(strtoupper(substr($p['name'], 0, 1))); ?>
          <?php endif; ?>
        </span>
        <h3><?php echo htmlspecialchars($p['name']); ?></h3>
        <span class="badge badge-<?php echo htmlspecialchars($p['role']); ?>"><?php echo htmlspecialchars(ucfirst($p['role'])); ?></span>
        <div class="people-card-actions">
          <button type="button" class="btn" data-modal-open="postAuthorProfileModal" data-post-user-id="<?php echo (int) $p['id']; ?>">Profile</button>
          <a class="btn primary" href="/messages.php?with=<?php echo (int) $p['id']; ?>">💬 Message</a>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php include __DIR__ . '/includes/drive_shell_bottom.php'; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>
