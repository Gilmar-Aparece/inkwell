<?php
// Inside/dashboard page — suppress the marketing topbar (see includes/header.php).
$__hideTopbar = true;
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/schools.php';

$user = inkwell_require_role('dean');
$school = inkwell_get_school_by_dean($user['id']);
if ($user['status'] !== 'active' || !$school) {
  header('Location: /dean/dashboard.php');
  exit;
}

$notice = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';

  if ($action === 'update_dean_signature') {
    $hasNewSig = !empty($_FILES['dean_signature']['name']);
    $result = inkwell_update_school_dean_signature($school['id'], $_POST['dean_signer_title'] ?? '', $hasNewSig ? 'dean_signature' : null);
    if (!$result['ok']) {
      $error = $result['error'];
    } else {
      $notice = 'Your Dean signature was updated — it will now appear on certificates your teachers issue.';
      $school = inkwell_get_school_by_dean($user['id']);
    }
  }
}

$dashNavTitle = 'Dean';
$dashNavActive = 'signer';
$dashNavItems = [
  ['key' => 'dashboard', 'group' => 'General', 'href' => '/dean/overview.php', 'label' => 'Dashboard', 'icon' => '🏠'],
  ['key' => 'school', 'group' => 'School', 'href' => '/dean/dashboard.php', 'label' => 'School profile', 'icon' => '🏫'],
  ['key' => 'signer', 'group' => 'Certificates', 'href' => '/dean/signer.php', 'label' => 'Certificate signer', 'icon' => '✍️'],
  ['key' => 'certificates', 'group' => 'Certificates', 'href' => '/dean/certificates.php', 'label' => 'Issue certificate', 'icon' => '📜'],
  ['key' => 'teachers', 'group' => 'People', 'href' => '/dean/teachers.php', 'label' => 'Teachers', 'icon' => '🧑‍🏫', 'count' => count(inkwell_list_school_teachers($school['id'], false, $user['department_id'] ?? null))],
  ['key' => 'students', 'group' => 'People', 'href' => '/dean/students.php', 'label' => 'Students', 'icon' => '🎓', 'count' => count(inkwell_school_students($school['id']))],
  ['key' => 'events', 'group' => 'Activity', 'href' => '/dean/events.php', 'label' => 'Events', 'icon' => '📣'],
];

$pageTitle = 'Dean · Certificate signer';
include __DIR__ . '/../includes/header.php';
?>
<div class="dash-shell">
  <?php include __DIR__ . '/../includes/dash_nav.php'; ?>
  <main class="admin-main">
    <div class="admin-header-row">
      <h1>Certificate signer</h1>
      <a class="btn" href="/logout.php">Log out</a>
    </div>

    <?php if ($notice): ?><div class="exam-result pass"><?php echo htmlspecialchars($notice); ?></div><?php endif; ?>
    <?php if ($error): ?><div class="exam-result fail"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

    <section class="admin-card glass-card">
      <h2>Dean signature</h2>
      <p class="admin-sub">Your own signature as Dean, shown on certificates alongside your name (<?php echo htmlspecialchars($user['name']); ?>) — pulled automatically from your account. You only set the title and upload a signature image here.</p>
      <form method="post" action="/dean/signer.php" enctype="multipart/form-data" class="admin-form">
        <input type="hidden" name="action" value="update_dean_signature">
        <label for="dean_signer_title">Title</label>
        <input type="text" id="dean_signer_title" name="dean_signer_title" maxlength="150" placeholder="e.g. Dean of Instruction" value="<?php echo htmlspecialchars($school['dean_signer_title'] ?? ''); ?>">
        <label for="dean_signature">Signature image (optional — PNG, JPG, or WEBP, under 2MB)</label>
        <input type="file" id="dean_signature" name="dean_signature" accept="image/png,image/jpeg,image/webp">
        <?php if (!empty($school['dean_signature'])): ?>
          <div class="admin-current-sig">
            <img src="/assets/uploads/<?php echo htmlspecialchars($school['dean_signature']); ?>" alt="Current dean signature" loading="lazy">
            <span>Current signature on file</span>
          </div>
        <?php endif; ?>
        <button class="btn primary" type="submit">Save Dean signature</button>
      </form>
    </section>

    <section class="admin-card glass-card">
      <h2>President signer</h2>
      <p class="admin-sub">An optional second signature — typically your school president or principal. Shown alongside your Dean signature on certificates. View-only here — your Registrar or an admin sets the name, title, and signature image.</p>
      <?php if (!empty($school['signer_name']) || !empty($school['signer_signature'])): ?>
        <div class="account-info-grid">
          <?php if (!empty($school['signer_name'])): ?><div class="account-info-row"><span>Name</span><strong><?php echo htmlspecialchars($school['signer_name']); ?></strong></div><?php endif; ?>
          <?php if (!empty($school['signer_title'])): ?><div class="account-info-row"><span>Title</span><strong><?php echo htmlspecialchars($school['signer_title']); ?></strong></div><?php endif; ?>
        </div>
        <?php if (!empty($school['signer_signature'])): ?>
          <div class="admin-current-sig">
            <img src="/assets/uploads/<?php echo htmlspecialchars($school['signer_signature']); ?>" alt="Current signature" loading="lazy">
            <span>Current signature on file</span>
          </div>
        <?php endif; ?>
      <?php else: ?>
        <p class="admin-sub">No President signer set for this school yet.</p>
      <?php endif; ?>
    </section>
  </main>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
