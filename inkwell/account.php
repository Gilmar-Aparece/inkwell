<?php
// Inside/dashboard page — suppress the marketing topbar (see includes/header.php).
$__hideTopbar = true;
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/exams_db.php';
require_once __DIR__ . '/includes/schools.php';
require_once __DIR__ . '/includes/students.php';
require_once __DIR__ . '/includes/posts.php';

$user = inkwell_require_login();

$avatarError = '';
$profileNotice = '';
$profileError = '';
$passwordNotice = '';
$passwordError = '';
$joinSchoolError = '';
$lockError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';

  if ($action === 'update_avatar') {
    $result = inkwell_update_user_avatar($user['id'], 'avatar');
    if ($result['ok']) {
      header('Location: /account.php?tab=edit#avatar-saved');
      exit;
    }
    $avatarError = $result['error'];
  }

  if ($action === 'remove_avatar') {
    inkwell_remove_user_avatar($user['id']);
    header('Location: /account.php?tab=edit#avatar-saved');
    exit;
  }

  if ($action === 'join_school' && $user['role'] === 'teacher') {
    $result = inkwell_teacher_join_school($user['id'], (int) ($_POST['school_id'] ?? 0));
    if ($result['ok']) {
      header('Location: /account.php?tab=edit#school');
      exit;
    }
    $joinSchoolError = $result['error'];
    $user = inkwell_get_user($user['id']);
  }

  if ($action === 'update_profile') {
    $result = inkwell_update_profile_details($user['id'], $_POST['name'] ?? '', $_POST['bio'] ?? '');
    if ($result['ok']) {
      $profileNotice = !empty($result['warning']) ? $result['warning'] : 'Profile updated.';
      $user = inkwell_get_user($user['id']);
    } else {
      $profileError = $result['error'];
    }
  }

  if ($action === 'change_password') {
    $result = inkwell_change_password($user['id'], $_POST['current_password'] ?? '', $_POST['new_password'] ?? '');
    if ($result['ok']) {
      $passwordNotice = 'Password updated.';
    } else {
      $passwordError = $result['error'];
    }
  }

  if ($action === 'lock_account') {
    $result = inkwell_lock_account($user['id'], $_POST['lock_password'] ?? '');
    if ($result['ok']) {
      header('Location: /login.php');
      exit;
    }
    $lockError = $result['error'];
  }
}

/* ---------------- Role-specific data ---------------- */
$subjects = [];
$pendingAttempts = [];
$enrolled = [];
$attempts = [];
$certificates = [];
$school = null;
$schoolStats = null;
$schoolTeachers = [];

if ($user['role'] === 'teacher') {
  $subjects = inkwell_teacher_subjects($user['id']);
  $pendingAttempts = $user['status'] === 'active' ? inkwell_teacher_pending_attempts($user['id']) : [];
  $certificates = inkwell_certificates_for_teacher($user['id']);
} elseif ($user['role'] === 'dean') {
  $school = inkwell_get_school_by_dean($user['id']);
  if ($school) {
    $schoolStats = inkwell_school_stats($school['id']);
    $certificates = inkwell_certificates_for_school($school['id']);
    $schoolTeachers = inkwell_list_school_teachers($school['id']);
  }
} elseif ($user['role'] === 'student') {
  $enrolled = inkwell_student_enrolled_subjects($user['id']);
  $attempts = inkwell_student_attempts($user['id']);
  $certificates = inkwell_student_certificates($user['id']);
}

$myPosts = inkwell_list_posts_by_user($user['id'], $user['id']);
$postCount = count($myPosts);
$engagement = inkwell_user_engagement_stats($user['id']);
$isAdminViewer = $user['role'] === 'admin';
$avatarGradients = inkwell_post_avatar_gradients();

$pageTitle = 'My profile';
include __DIR__ . '/includes/header.php';
$driveActive = 'account';
$driveCrumbs = [['label' => 'Home', 'href' => '/index.php'], ['label' => 'My profile']];
$driveTitle = 'My profile';
$driveFullBleedMobile = true;
include __DIR__ . '/includes/drive_shell_top.php';
?>
  <div class="admin-header-row" style="margin-bottom:18px;">
    <span></span>
    <a class="btn" href="/logout.php">Log out</a>
  </div>

  <!-- ================= Head card: avatar, name, bio, stats ================= -->
  <section class="admin-card glass-card profile-head-card">
    <div class="profile-head">
      <button type="button" class="profile-head-avatar-wrap" id="profileHeadAvatarBtn" title="Edit profile">
        <?php if (!empty($user['avatar'])): ?>
          <img class="profile-avatar-img" src="/assets/uploads/<?php echo htmlspecialchars($user['avatar']); ?>" alt="<?php echo htmlspecialchars($user['name']); ?>" loading="lazy">
        <?php else: ?>
          <span class="profile-avatar-placeholder" aria-hidden="true"><?php echo strtoupper(substr($user['name'], 0, 1)); ?></span>
        <?php endif; ?>
      </button>
      <div class="profile-head-info">
        <h1><?php echo htmlspecialchars($user['name']); ?></h1>
        <p class="profile-bio"><?php echo !empty($user['bio']) ? htmlspecialchars($user['bio']) : 'No bio yet.'; ?></p>
        <div class="profile-meta-row">
          <span class="badge badge-<?php echo htmlspecialchars($user['role']); ?>"><?php echo ucfirst($user['role']); ?></span>
          <?php if ($user['status'] !== 'active'): ?>
            <span class="badge badge-status-<?php echo htmlspecialchars($user['status']); ?>"><?php echo ucfirst($user['status']); ?></span>
          <?php endif; ?>
          <span class="admin-sub">Joined <?php echo htmlspecialchars(date('M Y', strtotime($user['created_at']))); ?></span>
        </div>
      </div>
    </div>

    <div class="stat-row">
      <div class="stat-pill"><strong><?php echo $postCount; ?></strong><span>Posts</span></div>
      <?php if ($user['role'] === 'student'): ?>
        <div class="stat-pill"><strong><?php echo count($enrolled); ?></strong><span>Subjects</span></div>
        <div class="stat-pill"><strong><?php echo count($certificates); ?></strong><span>Certificates</span></div>
        <div class="stat-pill"><strong><?php echo count($attempts); ?></strong><span>Attempts</span></div>
      <?php elseif ($user['role'] === 'teacher'): ?>
        <div class="stat-pill"><strong><?php echo count($subjects); ?></strong><span>Subjects</span></div>
        <div class="stat-pill"><strong><?php echo array_sum(array_column($subjects, 'student_count')); ?></strong><span>Students</span></div>
        <div class="stat-pill"><strong><?php echo count($certificates); ?></strong><span>Certificates</span></div>
      <?php elseif ($user['role'] === 'dean'): ?>
        <div class="stat-pill"><strong><?php echo (int) ($schoolStats['teacher_count'] ?? 0); ?></strong><span>Teachers</span></div>
        <div class="stat-pill"><strong><?php echo (int) ($schoolStats['subject_count'] ?? 0); ?></strong><span>Subjects</span></div>
        <div class="stat-pill"><strong><?php echo (int) ($schoolStats['certificate_count'] ?? 0); ?></strong><span>Certificates</span></div>
      <?php endif; ?>
    </div>
  </section>

  <!-- ================= Tabs: Posts / Subjects / Certificates / Edit Profile ================= -->
  <section class="admin-card glass-card profile-tabs-card">
    <div class="profile-tabs" id="profileTabs" role="tablist">
      <button type="button" class="profile-tab active" data-tab="posts" role="tab">🖼️ Posts</button>
      <button type="button" class="profile-tab" data-tab="subjects" role="tab">📚 Subjects</button>
      <button type="button" class="profile-tab" data-tab="certificates" role="tab">📜 Certificates</button>
      <button type="button" class="profile-tab" data-tab="analytics" role="tab">📊 Analytics</button>
      <button type="button" class="profile-tab" data-tab="edit" role="tab">✏️ Edit Profile</button>
    </div>

    <div class="profile-tab-viewport">
      <div class="profile-tab-track" id="profileTabTrack">

        <!-- ---- Posts ---- -->
        <div class="profile-tab-panel" data-panel="posts">
          <div style="display:flex; align-items:center; justify-content:space-between; gap:10px; margin-bottom:14px;">
            <h2 style="margin:0;">Your posts</h2>
            <a class="btn primary" href="/posts.php">+ New post</a>
          </div>
          <div class="post-feed" id="postFeed">
            <?php if (empty($myPosts)): ?>
              <div class="profile-empty-tab" id="postFeedEmpty">
                <span class="icon">🖼️</span>
                <p class="admin-sub" style="margin:0;">You haven't posted anything yet. <a href="/posts.php">Share something →</a></p>
              </div>
            <?php else: ?>
              <?php foreach ($myPosts as $post) echo inkwell_render_post_card($post, $user, $isAdminViewer); ?>
            <?php endif; ?>
          </div>
        </div>

        <!-- ---- Subjects ---- -->
        <div class="profile-tab-panel" data-panel="subjects">
          <?php if ($user['role'] === 'student'): ?>
            <h2 style="margin-top:0;">Your classes (<?php echo count($enrolled); ?>)</h2>
            <?php if (empty($enrolled)): ?>
              <div class="profile-empty-tab"><span class="icon">📚</span><p class="admin-sub" style="margin:0;">You haven't joined a class yet. <a href="/exams.php">Browse classes →</a></p></div>
            <?php else: ?>
              <div class="admin-table-wrap">
                <table class="admin-table" id="myClassesTable" data-paginate="10">
                  <thead><tr><th>Subject</th><th>Teacher</th><th>Exams</th></tr></thead>
                  <tbody>
                    <?php foreach ($enrolled as $s): ?>
                      <tr>
                        <td><?php echo htmlspecialchars($s['title']); ?></td>
                        <td><?php echo htmlspecialchars($s['teacher_name']); ?></td>
                        <td><a href="/class.php?id=<?php echo (int) $s['id']; ?>"><?php echo (int) $s['exam_count']; ?> →</a></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          <?php elseif ($user['role'] === 'teacher'): ?>
            <h2 style="margin-top:0;">Your subjects (<?php echo count($subjects); ?>)</h2>
            <?php if ($user['status'] !== 'active'): ?>
              <div class="profile-empty-tab"><span class="icon">⏳</span><p class="admin-sub" style="margin:0;">Waiting for admin approval before you can create subjects.</p></div>
            <?php elseif (empty($subjects)): ?>
              <div class="profile-empty-tab"><span class="icon">📚</span><p class="admin-sub" style="margin:0;">No subjects yet. <a href="/teacher/dashboard.php">Create one →</a></p></div>
            <?php else: ?>
              <div class="admin-table-wrap">
                <table class="admin-table" id="mySubjectsTable" data-paginate="10">
                  <thead><tr><th>Subject</th><th>Students</th><th>Exams</th></tr></thead>
                  <tbody>
                    <?php foreach ($subjects as $s): ?>
                      <tr>
                        <td><?php echo htmlspecialchars($s['title']); ?></td>
                        <td><?php echo (int) $s['student_count']; ?></td>
                        <td><?php echo (int) $s['exam_count']; ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
              <a class="btn primary" style="margin-top:14px;" href="/teacher/dashboard.php">Manage subjects →</a>
            <?php endif; ?>
          <?php elseif ($user['role'] === 'dean'): ?>
            <h2 style="margin-top:0;">Your school</h2>
            <?php if (!$school): ?>
              <div class="profile-empty-tab"><span class="icon">🏫</span><p class="admin-sub" style="margin:0;">You haven't set up your school yet. <a href="/dean/dashboard.php">Set up school →</a></p></div>
            <?php else: ?>
              <div class="school-card-head" style="margin-bottom:14px;">
                <?php if ($school['logo']): ?>
                  <img class="school-logo" src="/assets/uploads/<?php echo htmlspecialchars($school['logo']); ?>" alt="<?php echo htmlspecialchars($school['name']); ?> logo" loading="lazy">
                <?php else: ?>
                  <span class="school-logo-placeholder" aria-hidden="true">🏫</span>
                <?php endif; ?>
                <div>
                  <h2 style="margin:0;"><?php echo htmlspecialchars($school['name']); ?></h2>
                  <p class="admin-sub" style="margin:2px 0 0;"><?php echo (int) count($schoolTeachers); ?> teacher<?php echo count($schoolTeachers) === 1 ? '' : 's'; ?> · <?php echo (int) ($schoolStats['subject_count'] ?? 0); ?> subjects</p>
                </div>
              </div>
              <a class="btn primary" href="/dean/dashboard.php">Manage school &amp; teachers →</a>
            <?php endif; ?>
          <?php else: ?>
            <div class="profile-empty-tab"><span class="icon">📚</span><p class="admin-sub" style="margin:0;">Subjects aren't tracked for admin accounts.</p></div>
          <?php endif; ?>
        </div>

        <!-- ---- Certificates ---- -->
        <div class="profile-tab-panel" data-panel="certificates">
          <h2 style="margin-top:0;">Certificates (<?php echo count($certificates); ?>)</h2>
          <?php if (empty($certificates)): ?>
            <div class="profile-empty-tab"><span class="icon">📜</span><p class="admin-sub" style="margin:0;"><?php echo $user['role'] === 'student' ? 'No certificates yet — pass an exam to earn one.' : 'No certificates issued yet.'; ?></p></div>
          <?php else: ?>
            <div class="admin-table-wrap">
              <table class="admin-table" id="myCertificatesTable" data-paginate="10">
                <thead>
                  <tr>
                    <?php if ($user['role'] !== 'student'): ?><th>Student</th><?php endif; ?>
                    <th>Certificate</th><th>Score</th><th>Issued</th><th></th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($certificates as $c): ?>
                    <tr>
                      <?php if ($user['role'] !== 'student'): ?><td><?php echo htmlspecialchars($c['student_name']); ?></td><?php endif; ?>
                      <td><?php echo htmlspecialchars($c['label']); ?></td>
                      <td><?php echo (int) $c['percent']; ?>%</td>
                      <td><?php echo htmlspecialchars(date('M j, Y', strtotime($c['issued_at']))); ?></td>
                      <td><a href="/certificate.php?id=<?php echo htmlspecialchars($c['id']); ?>">View →</a></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>

        <!-- ---- Analytics ---- -->
        <div class="profile-tab-panel" data-panel="analytics">
          <h2 style="margin-top:0;">Post analytics</h2>
          <?php if ($engagement['post_count'] === 0): ?>
            <div class="profile-empty-tab"><span class="icon">📊</span><p class="admin-sub" style="margin:0;">Nothing to show yet — post something and check back here.</p></div>
          <?php else: ?>
            <div class="stat-row">
              <div class="stat-pill"><strong><?php echo (int) $engagement['post_count']; ?></strong><span>Posts</span></div>
              <div class="stat-pill"><strong><?php echo (int) $engagement['likes_received']; ?></strong><span>Likes received</span></div>
              <div class="stat-pill"><strong><?php echo (int) $engagement['comments_received']; ?></strong><span>Comments received</span></div>
              <div class="stat-pill"><strong><?php echo (int) $engagement['saves_received']; ?></strong><span>Saves received</span></div>
            </div>

            <?php if (!empty($engagement['top_posts'])): ?>
              <h2 style="margin-top:22px;">Your best-performing posts</h2>
              <div class="admin-table-wrap">
                <table class="admin-table" id="myAnalyticsTable" data-paginate="10">
                  <thead><tr><th>Post</th><th>Posted</th><th>♥ Likes</th><th>💬 Comments</th><th>🔖 Saves</th></tr></thead>
                  <tbody>
                    <?php foreach ($engagement['top_posts'] as $p): ?>
                      <tr>
                        <td><a href="/posts.php#post-<?php echo (int) $p['id']; ?>"><?php echo htmlspecialchars($p['caption'] !== null && $p['caption'] !== '' ? (mb_strlen($p['caption']) > 60 ? mb_substr($p['caption'], 0, 60) . '…' : $p['caption']) : '(no caption)'); ?></a></td>
                        <td><?php echo htmlspecialchars(date('M j, Y', strtotime($p['created_at']))); ?></td>
                        <td><?php echo (int) $p['like_count']; ?></td>
                        <td><?php echo (int) $p['comment_count']; ?></td>
                        <td><?php echo (int) $p['save_count']; ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          <?php endif; ?>
        </div>

        <!-- ---- Edit Profile ---- -->
        <div class="profile-tab-panel" data-panel="edit">
          <section id="avatar">
            <h2 style="margin-top:0;">Profile photo</h2>
            <div class="profile-avatar-row">
              <?php if (!empty($user['avatar'])): ?>
                <img class="profile-avatar-img" id="avatarPreviewImg" src="/assets/uploads/<?php echo htmlspecialchars($user['avatar']); ?>" alt="<?php echo htmlspecialchars($user['name']); ?>" loading="lazy">
              <?php else: ?>
                <img class="profile-avatar-img" id="avatarPreviewImg" src="" alt="" style="display:none;" loading="lazy">
              <?php endif; ?>
              <span class="profile-avatar-placeholder" id="avatarPreviewPlaceholder" aria-hidden="true" style="<?php echo !empty($user['avatar']) ? 'display:none;' : ''; ?>"><?php echo strtoupper(substr($user['name'], 0, 1)); ?></span>
              <form method="post" action="/account.php?tab=edit#avatar" enctype="multipart/form-data" class="profile-avatar-form">
                <input type="hidden" name="action" value="update_avatar">
                <label for="avatarFileInput" class="btn">Change photo</label>
                <input type="file" id="avatarFileInput" name="avatar" accept="image/png,image/jpeg,image/webp" style="display:none;" onchange="inkwellPreviewAvatar(this)">
                <span class="admin-sub">PNG, JPG, or WEBP — under 2MB.</span>
              </form>
              <?php if (!empty($user['avatar'])): ?>
                <form method="post" action="/account.php?tab=edit#avatar" onsubmit="return confirm('Remove your profile photo?');">
                  <input type="hidden" name="action" value="remove_avatar">
                  <button type="submit" class="btn">Remove photo</button>
                </form>
              <?php endif; ?>
            </div>
            <?php if ($avatarError): ?><div class="exam-result fail"><?php echo htmlspecialchars($avatarError); ?></div><?php endif; ?>
          </section>

          <hr class="profile-edit-divider">

          <h2>Name &amp; bio</h2>
          <?php if ($profileNotice): ?><div class="exam-result pass"><?php echo htmlspecialchars($profileNotice); ?></div><?php endif; ?>
          <?php if ($profileError): ?><div class="exam-result fail"><?php echo htmlspecialchars($profileError); ?></div><?php endif; ?>
          <form method="post" action="/account.php?tab=edit" class="profile-edit-form">
            <input type="hidden" name="action" value="update_profile">
            <div>
              <label for="profile_name">Full name</label>
              <input type="text" id="profile_name" name="name" maxlength="100" value="<?php echo htmlspecialchars($user['name']); ?>" required>
            </div>
            <div>
              <label for="profile_bio">Bio</label>
              <textarea id="profile_bio" name="bio" maxlength="160" placeholder="Say something about yourself…"><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea>
              <div class="profile-edit-hint">Up to 160 characters.</div>
            </div>
            <button type="submit" class="btn primary">Save changes</button>
          </form>

          <hr class="profile-edit-divider">

          <h2>Change password</h2>
          <?php if ($passwordNotice): ?><div class="exam-result pass"><?php echo htmlspecialchars($passwordNotice); ?></div><?php endif; ?>
          <?php if ($passwordError): ?><div class="exam-result fail"><?php echo htmlspecialchars($passwordError); ?></div><?php endif; ?>
          <form method="post" action="/account.php?tab=edit" class="profile-edit-form">
            <input type="hidden" name="action" value="change_password">
            <div>
              <label for="current_password">Current password</label>
              <input type="password" id="current_password" name="current_password" required>
            </div>
            <div>
              <label for="new_password">New password</label>
              <input type="password" id="new_password" name="new_password" minlength="8" required>
              <div class="profile-edit-hint">At least 8 characters.</div>
            </div>
            <button type="submit" class="btn primary">Update password</button>
          </form>

          <hr class="profile-edit-divider">

          <h2>Lock account</h2>
          <p class="admin-sub">Locking your account signs you out everywhere and blocks new logins until you unlock it again with your password — handy if you think your account may have been compromised, or you just want a break.</p>
          <?php if ($lockError): ?><div class="exam-result fail"><?php echo htmlspecialchars($lockError); ?></div><?php endif; ?>
          <form method="post" action="/account.php?tab=edit" class="profile-edit-form" onsubmit="return confirm('Lock your account? You\'ll be signed out immediately and need your password to unlock it again.');">
            <input type="hidden" name="action" value="lock_account">
            <div>
              <label for="lock_password">Confirm password</label>
              <input type="password" id="lock_password" name="lock_password" required autocomplete="current-password">
            </div>
            <button type="submit" class="btn danger">Lock my account</button>
          </form>

          <hr class="profile-edit-divider">

          <h2>Account details</h2>
          <div class="account-info-grid">
            <div class="account-info-row"><span>Account ID</span><strong>#<?php echo str_pad($user['id'], 5, '0', STR_PAD_LEFT); ?></strong></div>
            <div class="account-info-row"><span>Email</span><strong><?php echo htmlspecialchars($user['email']); ?></strong></div>
            <?php if (!empty($user['id_number'])): ?>
              <div class="account-info-row"><span><?php echo $user['role'] === 'teacher' ? 'Teacher ID' : ($user['role'] === 'dean' ? 'Dean ID' : 'Student ID'); ?></span><strong><?php echo htmlspecialchars($user['id_number']); ?></strong></div>
            <?php endif; ?>
            <?php if (!empty($user['course'])): ?>
              <div class="account-info-row"><span><?php echo $user['role'] === 'teacher' ? 'Department' : ($user['role'] === 'dean' ? 'Institution / Position' : 'Course'); ?></span><strong><?php echo htmlspecialchars($user['course']); ?></strong></div>
            <?php endif; ?>
            <div class="account-info-row"><span>Role</span><strong class="badge badge-<?php echo $user['role']; ?>"><?php echo ucfirst($user['role']); ?></strong></div>
            <div class="account-info-row"><span>Status</span><strong class="badge badge-status-<?php echo $user['status']; ?>"><?php echo ucfirst($user['status']); ?></strong></div>
            <div class="account-info-row"><span>Member since</span><strong><?php echo htmlspecialchars(date('F j, Y', strtotime($user['created_at']))); ?></strong></div>
          </div>

          <?php if ($user['role'] === 'teacher' && $user['status'] === 'active' && empty($user['school_id'])): ?>
            <hr class="profile-edit-divider">
            <section id="school">
              <h2>Join your school</h2>
              <p class="admin-sub">You're not attached to a school yet, so you can't feature top students or appear in the school's faculty list.</p>
              <?php if ($joinSchoolError): ?><div class="exam-result fail"><?php echo htmlspecialchars($joinSchoolError); ?></div><?php endif; ?>
              <?php $allSchools = inkwell_list_schools(); ?>
              <?php if (empty($allSchools)): ?>
                <p class="admin-sub">No schools have been created yet. Ask a dean to set one up first.</p>
              <?php else: ?>
                <form method="post" action="/account.php?tab=edit#school" class="admin-form" style="flex-direction:row; flex-wrap:wrap; gap:10px; align-items:flex-end;">
                  <input type="hidden" name="action" value="join_school">
                  <div style="flex:1; min-width:200px;">
                    <label for="join_school_id">School</label>
                    <select id="join_school_id" name="school_id" required>
                      <option value="">Select a school…</option>
                      <?php foreach ($allSchools as $s): ?>
                        <option value="<?php echo (int) $s['id']; ?>"><?php echo htmlspecialchars($s['name']); ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <button type="submit" class="btn primary">Join school</button>
                </form>
              <?php endif; ?>
            </section>
          <?php endif; ?>

          <?php if ($user['role'] === 'teacher' && $user['status'] !== 'active'): ?>
            <hr class="profile-edit-divider">
            <div class="profile-empty-tab"><span class="icon">⏳</span><p class="admin-sub" style="margin:0;">Waiting for admin approval. Once approved you'll be able to create subjects, add exams, and grade student attempts.</p></div>
          <?php endif; ?>

          <?php if ($user['role'] === 'dean' && $user['status'] !== 'active'): ?>
            <hr class="profile-edit-divider">
            <div class="profile-empty-tab"><span class="icon">⏳</span><p class="admin-sub" style="margin:0;">Waiting for admin approval. Once approved you'll be able to set up your school and add teacher accounts.</p></div>
          <?php endif; ?>
        </div>

      </div>
    </div>
  </section>

<script>
function inkwellPreviewAvatar(input) {
  if (!input.files || !input.files[0]) return;
  const file = input.files[0];
  const maxBytes = 2 * 1024 * 1024;
  if (file.size > maxBytes) {
    alert('Image must be under 2MB.');
    input.value = '';
    return;
  }
  const reader = new FileReader();
  reader.onload = function (e) {
    const img = document.getElementById('avatarPreviewImg');
    const placeholder = document.getElementById('avatarPreviewPlaceholder');
    img.src = e.target.result;
    img.style.display = '';
    if (placeholder) placeholder.style.display = 'none';
    input.form.submit();
  };
  reader.readAsDataURL(file);
}

(function () {
  /* ============ Tabs: click + swipe carousel, like the reference profile UI ============ */
  const tabs = Array.prototype.slice.call(document.querySelectorAll('.profile-tab'));
  const track = document.getElementById('profileTabTrack');
  const viewport = track ? track.parentElement : null;
  const panels = Array.prototype.slice.call(document.querySelectorAll('.profile-tab-panel'));
  let current = 0;

  function panelHeight(i) {
    const p = panels[i];
    return p ? p.scrollHeight : 0;
  }

  function goTo(index, skipHash) {
    index = Math.max(0, Math.min(panels.length - 1, index));
    current = index;
    tabs.forEach(function (t, i) { t.classList.toggle('active', i === index); });
    if (track) track.style.transform = 'translateX(-' + (index * (100 / panels.length)) + '%)';
    if (viewport) viewport.style.height = panelHeight(index) + 'px';
    if (!skipHash && tabs[index]) {
      history.replaceState(null, '', '#' + tabs[index].getAttribute('data-tab'));
    }
  }

  tabs.forEach(function (tab, i) {
    tab.addEventListener('click', function () { goTo(i); });
  });

  window.addEventListener('resize', function () { goTo(current, true); });

  // Deep links: /account.php#edit, #certificates, #subjects, #avatar, #school, or ?tab=edit
  function tabIndexFromHint(hint) {
    hint = (hint || '').replace('#', '').toLowerCase();
    if (hint === 'avatar' || hint === 'school' || hint === 'avatar-saved') hint = 'edit';
    const idx = tabs.findIndex(function (t) { return t.getAttribute('data-tab') === hint; });
    return idx >= 0 ? idx : 0;
  }
  const params = new URLSearchParams(window.location.search);
  const initialHint = window.location.hash || params.get('tab') || '';
  goTo(tabIndexFromHint(initialHint), true);

  const headAvatarBtn = document.getElementById('profileHeadAvatarBtn');
  if (headAvatarBtn) {
    headAvatarBtn.addEventListener('click', function () { goTo(tabs.findIndex(function (t) { return t.getAttribute('data-tab') === 'edit'; })); });
  }

  // ---- Touch swipe between tabs ----
  if (track) {
    let startX = 0, startY = 0, dx = 0, dragging = false, lockedAxis = null;

    track.addEventListener('touchstart', function (e) {
      const t = e.touches[0];
      startX = t.clientX; startY = t.clientY; dx = 0; dragging = true; lockedAxis = null;
      track.classList.add('dragging');
    }, { passive: true });

    track.addEventListener('touchmove', function (e) {
      if (!dragging) return;
      const t = e.touches[0];
      const moveX = t.clientX - startX;
      const moveY = t.clientY - startY;
      if (!lockedAxis) lockedAxis = Math.abs(moveX) > Math.abs(moveY) ? 'x' : 'y';
      if (lockedAxis !== 'x') return;
      dx = moveX;
      const basePct = -(current * (100 / panels.length));
      const dragPct = (dx / track.offsetWidth) * 100;
      track.style.transform = 'translateX(calc(' + basePct + '% + ' + dragPct + '%))';
    }, { passive: true });

    track.addEventListener('touchend', function () {
      dragging = false;
      track.classList.remove('dragging');
      if (lockedAxis === 'x' && Math.abs(dx) > 45) {
        goTo(dx < 0 ? current + 1 : current - 1);
      } else {
        goTo(current);
      }
    });
  }

  /* ============ Own-posts feed: like / comment / delete (same pattern as Community) ============ */
  const feed = document.getElementById('postFeed');
  const feedEmpty = document.getElementById('postFeedEmpty');

  function postAjax(fields) {
    const body = new FormData();
    Object.keys(fields).forEach(function (k) { body.append(k, fields[k]); });
    return fetch('/posts.php', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: body })
      .then(function (r) { return r.json(); });
  }

  function setCommentsHeaderCount(postId, count) {
    const header = document.getElementById('post-comments-header-' + postId);
    if (!header) return;
    const countEl = header.querySelector('.count');
    if (countEl) countEl.textContent = count;
    header.style.display = count > 0 ? '' : 'none';
  }

  function flashCopied(btn, label) {
    if (!btn) return;
    const original = btn.tagName === 'BUTTON' ? btn.textContent : btn.getAttribute('title');
    if (btn.tagName === 'BUTTON') btn.textContent = label || 'Copied!';
    btn.classList.add('copied-flash');
    setTimeout(function () {
      if (btn.tagName === 'BUTTON') btn.textContent = original;
      btn.classList.remove('copied-flash');
    }, 1400);
  }

  function copyPostLink(postId, btn) {
    const input = document.getElementById('post-link-' + postId);
    const url = input ? input.value : (window.location.origin + '/posts.php#post-' + postId);
    const done = function () { flashCopied(btn, 'Copied!'); };
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(url).then(done).catch(function () {
        if (input) { input.select(); document.execCommand('copy'); done(); }
      });
    } else if (input) {
      input.select();
      document.execCommand('copy');
      done();
    }
  }

  if (feed) {
    feed.addEventListener('click', function (e) {
      const likeBtn = e.target.closest('[data-like-btn]');
      if (likeBtn) {
        const postId = likeBtn.getAttribute('data-post-id');
        likeBtn.disabled = true;
        postAjax({ action: 'toggle_like', post_id: postId })
          .then(function (data) {
            if (!data.ok) { alert(data.error || 'Could not update like.'); return; }
            const glyph = likeBtn.querySelector('.post-icon-glyph');
            likeBtn.classList.toggle('liked', data.liked);
            if (glyph) glyph.textContent = data.liked ? '♥' : '♡';
            const c = likeBtn.querySelector('.post-stats-likes .count'); if (c) c.textContent = data.count;
          })
          .catch(function () { alert('Network error — please try again.'); })
          .finally(function () { likeBtn.disabled = false; goTo(current, true); });
        return;
      }

      const saveBtn = e.target.closest('[data-save-btn]');
      if (saveBtn) {
        const postId = saveBtn.getAttribute('data-post-id');
        saveBtn.disabled = true;
        postAjax({ action: 'toggle_save', post_id: postId })
          .then(function (data) {
            if (!data.ok) { alert(data.error || 'Could not update save.'); return; }
            const glyph = saveBtn.querySelector('.post-icon-glyph');
            saveBtn.classList.toggle('saved', data.saved);
            if (glyph) glyph.textContent = data.saved ? '🔖' : '📑';
            const c = saveBtn.querySelector('.post-stats-saves .count'); if (c) c.textContent = data.count;
          })
          .catch(function () { alert('Network error — please try again.'); })
          .finally(function () { saveBtn.disabled = false; goTo(current, true); });
        return;
      }

      const copyLinkBtn = e.target.closest('[data-copy-link]');
      if (copyLinkBtn) {
        copyPostLink(copyLinkBtn.getAttribute('data-post-id'), copyLinkBtn);
        return;
      }

      const commentDeleteBtn = e.target.closest('[data-comment-delete]');
      if (commentDeleteBtn) {
        if (!confirm('Delete this comment?')) return;
        const commentId = commentDeleteBtn.getAttribute('data-comment-id');
        const postId = commentDeleteBtn.getAttribute('data-post-id');
        postAjax({ action: 'delete_comment', comment_id: commentId, post_id: postId })
          .then(function (data) {
            if (!data.ok) { alert(data.error || 'Could not delete comment.'); return; }
            const row = document.getElementById('comment-' + commentId);
            if (row) row.remove();
            const list = document.getElementById('post-comments-' + postId);
            const newCount = list ? list.querySelectorAll('.post-comment').length : 0;
            if (list && newCount === 0) list.style.display = 'none';
            setCommentsHeaderCount(postId, newCount);
            const stats = document.getElementById('post-stats-' + postId);
            if (stats) {
              const c = stats.querySelector('.post-stats-comments .count'); if (c) c.textContent = newCount;
            }
          })
          .catch(function () { alert('Network error — please try again.'); })
          .finally(function () { goTo(current, true); });
      }
    });

    feed.addEventListener('submit', function (e) {
      const form = e.target.closest('[data-comment-form]');
      if (!form) return;
      e.preventDefault();
      const postId = form.getAttribute('data-post-id');
      const input = form.querySelector('input[name="comment"]');
      const text = input ? input.value.trim() : '';
      if (!text) return;
      const sendBtn = form.querySelector('.post-comment-send');
      if (sendBtn) sendBtn.disabled = true;

      postAjax({ action: 'add_comment', post_id: postId, comment: text })
        .then(function (data) {
          if (!data.ok) { alert(data.error || 'Could not add comment.'); return; }
          const list = document.getElementById('post-comments-' + postId);
          if (list) { list.style.display = ''; list.insertAdjacentHTML('beforeend', data.html); }
          if (input) input.value = '';
          setCommentsHeaderCount(postId, data.comment_count);
          const stats = document.getElementById('post-stats-' + postId);
          if (stats) {
            const c = stats.querySelector('.post-stats-comments .count'); if (c) c.textContent = data.comment_count;
          }
        })
        .catch(function () { alert('Network error — please try again.'); })
        .finally(function () { if (sendBtn) sendBtn.disabled = false; goTo(current, true); });
    });
  }
})();
</script>
<?php include __DIR__ . '/includes/drive_shell_bottom.php'; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>
