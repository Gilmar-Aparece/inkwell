<?php
// Inside/dashboard page — suppress the marketing topbar (see includes/header.php).
$__hideTopbar = true;
require_once __DIR__ . '/../includes/store.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/exams_db.php';
require_once __DIR__ . '/../includes/schools.php';
require_once __DIR__ . '/../includes/students.php';
require_once __DIR__ . '/../includes/billing.php';
require_once __DIR__ . '/../includes/lesson_progress.php';
inkwell_require_admin();

$error = '';
$notice = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';

  if ($action === 'create_admin_exam') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $passScore = max(1, min(100, (int) ($_POST['pass_score'] ?? 70)));
    if ($title === '') {
      $error = 'Give the exam a title.';
    } else {
      $newId = inkwell_create_admin_exam($title, $description, $passScore);
      header('Location: /admin/category.php?id=' . $newId);
      exit;
    }
  }

  if ($action === 'edit_admin_exam') {
    $examId = (int) ($_POST['category_id'] ?? 0);
    $exam = inkwell_get_teacher_category($examId);
    if ($exam && $exam['owner_type'] === 'admin') {
      $title = trim($_POST['title'] ?? '');
      $description = trim($_POST['description'] ?? '');
      $passScore = max(1, min(100, (int) ($_POST['pass_score'] ?? 70)));
      if ($title === '') {
        $error = 'Give the exam a title.';
      } else {
        inkwell_update_teacher_category($examId, $title, $description, $passScore);
        $notice = 'Certification exam updated.';
      }
    }
  }

  if ($action === 'delete_admin_exam') {
    $examId = (int) ($_POST['category_id'] ?? 0);
    $exam = inkwell_get_teacher_category($examId);
    if ($exam && $exam['owner_type'] === 'admin') {
      inkwell_delete_teacher_category($examId);
      $notice = 'Certification exam deleted.';
    }
  }

  if ($action === 'create_selfstudy_exam') {
    $languageKey = strtolower(trim($_POST['language_key'] ?? ''));
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $passScore = max(1, min(100, (int) ($_POST['pass_score'] ?? 70)));
    if (!preg_match('/^[a-z0-9_-]{1,30}$/', $languageKey)) {
      $error = 'Language key must be lowercase letters, numbers, "-" or "_" only.';
    } elseif ($title === '') {
      $error = 'Give the exam a title.';
    } elseif (inkwell_selfstudy_key_taken($languageKey)) {
      $error = 'That language key is already used by another exam.';
    } else {
      $newId = inkwell_create_selfstudy_exam($languageKey, $title, $description, $passScore);
      header('Location: /admin/category.php?id=' . $newId);
      exit;
    }
  }

  if ($action === 'edit_selfstudy_exam') {
    $examId = (int) ($_POST['category_id'] ?? 0);
    $exam = inkwell_get_teacher_category($examId);
    if ($exam && $exam['owner_type'] === 'selfstudy') {
      $title = trim($_POST['title'] ?? '');
      $description = trim($_POST['description'] ?? '');
      $passScore = max(1, min(100, (int) ($_POST['pass_score'] ?? 70)));
      if ($title === '') {
        $error = 'Give the exam a title.';
      } else {
        inkwell_update_teacher_category($examId, $title, $description, $passScore);
        $notice = 'Self-study exam updated.';
      }
    }
  }

  if ($action === 'delete_selfstudy_exam') {
    $examId = (int) ($_POST['category_id'] ?? 0);
    $exam = inkwell_get_teacher_category($examId);
    if ($exam && $exam['owner_type'] === 'selfstudy') {
      inkwell_delete_teacher_category($examId);
      $notice = 'Self-study exam deleted.';
    }
  }
}

$exams = inkwell_all_exam_categories();
$adminExams = inkwell_admin_exam_categories();
$selfstudyExams = inkwell_selfstudy_exam_categories();

// Group teacher exams by subject so it reads like the teacher's own "subject -> exams" view.
$bySubject = [];
foreach ($exams as $ex) {
  if ($ex['owner_type'] === 'admin' || $ex['owner_type'] === 'selfstudy') continue; // shown separately above
  $key = $ex['subject_id'] ?? 0;
  if (!isset($bySubject[$key])) {
    $bySubject[$key] = ['title' => $ex['subject_title'] ?? 'No subject', 'items' => []];
  }
  $bySubject[$key]['items'][] = $ex;
}

$dashNavTitle = 'Admin';
$dashNavActive = 'exams';
$dashNavItems = [
  ['key' => 'dashboard', 'group' => 'General', 'href' => '/admin/dashboard.php', 'label' => 'Dashboard', 'icon' => '🏠'],
  ['key' => 'settings', 'group' => 'General', 'href' => '/admin/index.php', 'label' => 'Settings', 'icon' => '⚙️'],
  ['key' => 'exams', 'group' => 'Academics', 'href' => '/admin/exams.php', 'label' => 'Exams', 'icon' => '🗂'],
  ['key' => 'lessons', 'group' => 'Academics', 'href' => '/admin/lessons.php', 'label' => 'Lessons', 'icon' => '📘'],
  ['key' => 'teachers', 'group' => 'People', 'href' => '/admin/teachers.php', 'label' => 'Teachers', 'icon' => '🧑‍🏫', 'count' => count(inkwell_list_teachers())],
  ['key' => 'deans', 'group' => 'People', 'href' => '/admin/deans.php', 'label' => 'Deans', 'icon' => '🎓', 'count' => count(inkwell_list_deans())],
  ['key' => 'registrars', 'group' => 'People', 'href' => '/admin/registrars.php', 'label' => 'Registrars', 'icon' => '🗂️', 'count' => count(inkwell_list_registrars())],
  ['key' => 'admins', 'group' => 'People', 'href' => '/admin/admins.php', 'label' => 'Admins', 'icon' => '🛡️', 'count' => count(inkwell_list_admins())],
  ['key' => 'students', 'group' => 'People', 'href' => '/admin/students.php', 'label' => 'Students', 'icon' => '🧑‍🎓', 'count' => count(inkwell_list_all_students())],
  ['key' => 'schools', 'group' => 'Schools & Recognition', 'href' => '/admin/schools.php', 'label' => 'Schools', 'icon' => '🏫', 'count' => count(inkwell_list_schools_with_stats())],
  ['key' => 'certificates', 'group' => 'Schools & Recognition', 'href' => '/admin/certificates.php', 'label' => 'Certificates', 'icon' => '📜'],
  ['key' => 'top-learners', 'group' => 'Schools & Recognition', 'href' => '/admin/top-learners.php', 'label' => 'Top learners', 'icon' => '⭐', 'count' => count(inkwell_top_learner_ids())],
  ['key' => 'lesson-progress', 'group' => 'Schools & Recognition', 'href' => '/admin/lesson-progress.php', 'label' => 'Lesson progress', 'icon' => '📈', 'count' => count(inkwell_lesson_progress_overview())],
  ['key' => 'billing-dashboard', 'group' => 'Billing', 'href' => '/admin/billing-dashboard.php', 'label' => 'Dashboard', 'icon' => '📊'],
  ['key' => 'pricing', 'group' => 'Billing', 'href' => '/admin/pricing.php', 'label' => 'Pricing', 'icon' => '💳', 'count' => count(inkwell_list_plans())],
  ['key' => 'payment-methods', 'group' => 'Billing', 'href' => '/admin/payment-methods.php', 'label' => 'Payment methods', 'icon' => '🏦'],
  ['key' => 'payments', 'group' => 'Billing', 'href' => '/admin/payments.php', 'label' => 'Payments', 'icon' => '🧾', 'count' => inkwell_count_pending_payment_submissions()],
];

$pageTitle = 'All exams';
include __DIR__ . '/../includes/header.php';
?>
<div class="dash-shell">
  <?php include __DIR__ . '/../includes/dash_nav.php'; ?>
  <main class="admin-main">
  <div class="admin-header-row">
    <div>
      <h1>All exams</h1>
    </div>
  </div>

  <?php if ($notice): ?><div class="exam-result pass"><?php echo htmlspecialchars($notice); ?></div><?php endif; ?>
  <?php if ($error): ?><div class="exam-result fail"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

  <section class="admin-card glass-card">
    <div class="admin-header-row" style="margin-bottom:0;">
      <div>
        <h2>Self-study exams (<?php echo count($selfstudyExams); ?>)</h2>
        <p class="admin-sub">One certification exam per language, shown on the public Exams page under "Self-study exams" and reachable from each lesson category's "🎓 Exam" link — no teacher attached. Add a new language, or open one to add/edit questions.</p>
      </div>
      <button class="btn primary" type="button" data-modal-open="createSelfstudyExamModal">+ New self-study exam</button>
    </div>
    <?php if (empty($selfstudyExams)): ?>
      <p class="admin-sub">No self-study exams yet — create one above.</p>
    <?php else: ?>
      <div class="search-filter">
        <input type="search" class="search-filter-input" data-filter-target="#selfstudyExamsGrid" placeholder="Search self-study exams...">
      </div>
      <div class="subject-grid" id="selfstudyExamsGrid">
        <?php foreach ($selfstudyExams as $ex): ?>
          <div class="subject-card" data-filter-row>
            <div class="subject-card-top">
              <h3><?php echo htmlspecialchars($ex['title']); ?></h3>
              <div style="display:flex; gap:4px;">
                <button class="icon-btn" type="button" data-modal-open="editSelfstudyExamModal_<?php echo (int) $ex['id']; ?>" title="Edit exam">✎</button>
                <form method="post" action="/admin/exams.php" onsubmit="return confirm('Delete this self-study exam (&quot;<?php echo htmlspecialchars(addslashes($ex['language_key'])); ?>&quot;) and all its questions? The lesson category&#39;s Exam link will stop working.');">
                  <input type="hidden" name="action" value="delete_selfstudy_exam">
                  <input type="hidden" name="category_id" value="<?php echo (int) $ex['id']; ?>">
                  <button class="icon-btn danger" type="submit" title="Delete exam">✕</button>
                </form>
              </div>
            </div>
            <p class="admin-sub">Language key: <code><?php echo htmlspecialchars($ex['language_key']); ?></code> · <?php echo htmlspecialchars($ex['description'] ?: 'No description yet.'); ?></p>
            <div class="stat-row">
              <div class="stat-pill"><strong><?php echo (int) $ex['pass_score']; ?>%</strong><span>Pass score</span></div>
              <div class="stat-pill"><strong><?php echo (int) $ex['question_count']; ?></strong><span>Questions</span></div>
            </div>
            <a class="btn primary" style="width:100%; justify-content:center; margin-top:12px;" href="/admin/category.php?id=<?php echo (int) $ex['id']; ?>">Manage questions →</a>
            <a class="btn" style="width:100%; justify-content:center; margin-top:8px;" href="/admin/download-exam.php?id=<?php echo (int) $ex['id']; ?>">⬇ Download (.docx)</a>
          </div>

          <div class="modal-backdrop" id="editSelfstudyExamModal_<?php echo (int) $ex['id']; ?>">
            <div class="modal">
              <div class="modal-head">
                <h2>Edit self-study exam</h2>
                <button type="button" data-modal-close aria-label="Close">✕</button>
              </div>
              <form method="post" action="/admin/exams.php" class="admin-form">
                <input type="hidden" name="action" value="edit_selfstudy_exam">
                <input type="hidden" name="category_id" value="<?php echo (int) $ex['id']; ?>">
                <label for="ss_title_<?php echo (int) $ex['id']; ?>">Title</label>
                <input type="text" id="ss_title_<?php echo (int) $ex['id']; ?>" name="title" maxlength="150" value="<?php echo htmlspecialchars($ex['title']); ?>" required>
                <label for="ss_description_<?php echo (int) $ex['id']; ?>">Description (optional)</label>
                <input type="text" id="ss_description_<?php echo (int) $ex['id']; ?>" name="description" maxlength="500" value="<?php echo htmlspecialchars($ex['description'] ?? ''); ?>">
                <label for="ss_pass_score_<?php echo (int) $ex['id']; ?>">Pass score (%)</label>
                <input type="number" id="ss_pass_score_<?php echo (int) $ex['id']; ?>" name="pass_score" min="1" max="100" value="<?php echo (int) $ex['pass_score']; ?>">
                <button class="btn primary" type="submit">Save changes</button>
              </form>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <div class="modal-backdrop" id="createSelfstudyExamModal">
    <div class="modal">
      <div class="modal-head">
        <h2>New self-study exam</h2>
        <button type="button" data-modal-close aria-label="Close">✕</button>
      </div>
      <p class="admin-sub">The language key controls the URL (<code>/exam.php?cat=&lt;key&gt;</code>) and links it to a lesson category of the same key, if one exists.</p>
      <form method="post" action="/admin/exams.php" class="admin-form">
        <input type="hidden" name="action" value="create_selfstudy_exam">
        <label for="language_key">Language key (lowercase, e.g. <code>ruby</code>)</label>
        <input type="text" id="language_key" name="language_key" maxlength="30" pattern="[a-z0-9_-]+" placeholder="ruby" required>
        <label for="ss_new_title">Title</label>
        <input type="text" id="ss_new_title" name="title" maxlength="150" placeholder="Ruby Certification Exam" required>
        <label for="ss_new_description">Description (optional)</label>
        <input type="text" id="ss_new_description" name="description" maxlength="500">
        <label for="ss_new_pass_score">Pass score (%)</label>
        <input type="number" id="ss_new_pass_score" name="pass_score" min="1" max="100" value="70">
        <button class="btn primary" type="submit">Create exam</button>
      </form>
    </div>
  </div>

  <section class="admin-card glass-card">
    <div class="admin-header-row" style="margin-bottom:0;">
      <div>
        <h2>Official certification exams (<?php echo count($adminExams); ?>)</h2>
        <p class="admin-sub">Admin-authored exams, not tied to any teacher or subject — open to any logged-in student, like self-study, but managed here.</p>
      </div>
      <button class="btn primary" type="button" data-modal-open="createAdminExamModal">+ New certification exam</button>
    </div>
    <?php if (empty($adminExams)): ?>
      <p class="admin-sub">No official certification exams yet — create one above.</p>
    <?php else: ?>
      <div class="search-filter">
        <input type="search" class="search-filter-input" data-filter-target="#adminExamsGrid" placeholder="Search certification exams...">
      </div>
      <div class="subject-grid" id="adminExamsGrid">
        <?php foreach ($adminExams as $ex): ?>
          <div class="subject-card" data-filter-row>
            <div class="subject-card-top">
              <h3><?php echo htmlspecialchars($ex['title']); ?></h3>
              <div style="display:flex; gap:4px;">
                <button class="icon-btn" type="button" data-modal-open="editAdminExamModal_<?php echo (int) $ex['id']; ?>" title="Edit exam">✎</button>
                <form method="post" action="/admin/exams.php" onsubmit="return confirm('Delete this certification exam and all its questions?');">
                  <input type="hidden" name="action" value="delete_admin_exam">
                  <input type="hidden" name="category_id" value="<?php echo (int) $ex['id']; ?>">
                  <button class="icon-btn danger" type="submit" title="Delete exam">✕</button>
                </form>
              </div>
            </div>
            <p class="admin-sub"><?php echo htmlspecialchars($ex['description'] ?: 'No description yet.'); ?></p>
            <div class="stat-row">
              <div class="stat-pill"><strong><?php echo (int) $ex['pass_score']; ?>%</strong><span>Pass score</span></div>
              <div class="stat-pill"><strong><?php echo (int) $ex['question_count']; ?></strong><span>Questions</span></div>
            </div>
            <a class="btn primary" style="width:100%; justify-content:center; margin-top:12px;" href="/admin/category.php?id=<?php echo (int) $ex['id']; ?>">Manage questions →</a>
            <a class="btn" style="width:100%; justify-content:center; margin-top:8px;" href="/admin/download-exam.php?id=<?php echo (int) $ex['id']; ?>">⬇ Download (.docx)</a>
          </div>

          <div class="modal-backdrop" id="editAdminExamModal_<?php echo (int) $ex['id']; ?>">
            <div class="modal">
              <div class="modal-head">
                <h2>Edit certification exam</h2>
                <button type="button" data-modal-close aria-label="Close">✕</button>
              </div>
              <form method="post" action="/admin/exams.php" class="admin-form">
                <input type="hidden" name="action" value="edit_admin_exam">
                <input type="hidden" name="category_id" value="<?php echo (int) $ex['id']; ?>">
                <label for="title_<?php echo (int) $ex['id']; ?>">Title</label>
                <input type="text" id="title_<?php echo (int) $ex['id']; ?>" name="title" maxlength="150" value="<?php echo htmlspecialchars($ex['title']); ?>" required>
                <label for="description_<?php echo (int) $ex['id']; ?>">Description (optional)</label>
                <input type="text" id="description_<?php echo (int) $ex['id']; ?>" name="description" maxlength="500" value="<?php echo htmlspecialchars($ex['description'] ?? ''); ?>">
                <label for="pass_score_<?php echo (int) $ex['id']; ?>">Pass score (%)</label>
                <input type="number" id="pass_score_<?php echo (int) $ex['id']; ?>" name="pass_score" min="1" max="100" value="<?php echo (int) $ex['pass_score']; ?>">
                <button class="btn primary" type="submit">Save changes</button>
              </form>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <div class="modal-backdrop" id="createAdminExamModal">
    <div class="modal">
      <div class="modal-head">
        <h2>New certification exam</h2>
        <button type="button" data-modal-close aria-label="Close">✕</button>
      </div>
      <p class="admin-sub">Official Inkwell certification exams always issue a certificate on pass — no separate purpose picker needed.</p>
      <form method="post" action="/admin/exams.php" class="admin-form">
        <input type="hidden" name="action" value="create_admin_exam">
        <label for="title">Title</label>
        <input type="text" id="title" name="title" maxlength="150" required>
        <label for="description">Description (optional)</label>
        <input type="text" id="description" name="description" maxlength="500">
        <label for="pass_score">Pass score (%)</label>
        <input type="number" id="pass_score" name="pass_score" min="1" max="100" value="70">
        <button class="btn primary" type="submit">Create exam</button>
      </form>
    </div>
  </div>

  <p class="admin-sub">Every exam created by every teacher, in one place. As admin you can open any exam below and add or delete questions on it — you're not limited to exams you created yourself.</p>

  <?php if (empty($bySubject)): ?>
    <section class="admin-card glass-card">
      <p class="admin-sub">No teacher exams have been created yet.</p>
    </section>
  <?php else: ?>
    <div class="search-filter">
      <input type="search" class="search-filter-input" data-filter-target="#allTeacherExams" placeholder="Search exam, teacher, or subject...">
    </div>
    <div id="allTeacherExams">
    <?php foreach ($bySubject as $group): ?>
      <section class="admin-card glass-card" data-filter-group>
        <h2><?php echo htmlspecialchars($group['title']); ?></h2>
        <div class="admin-table-wrap">
          <table class="admin-table">
            <thead><tr><th>Exam</th><th>Teacher</th><th>Purpose</th><th>Pass score</th><th>Questions</th><th></th><th></th></tr></thead>
            <tbody>
              <?php foreach ($group['items'] as $ex): ?>
                <tr data-filter-row>
                  <td><?php echo htmlspecialchars($ex['title']); ?></td>
                  <td><?php echo htmlspecialchars($ex['teacher_name']); ?></td>
                  <td><span class="badge badge-purpose-<?php echo $ex['purpose']; ?>"><?php echo $ex['purpose'] === 'cert' ? 'Certificate' : 'Grade only'; ?></span></td>
                  <td><?php echo (int) $ex['pass_score']; ?>%</td>
                  <td><?php echo (int) $ex['question_count']; ?></td>
                  <td><a href="/admin/category.php?id=<?php echo (int) $ex['id']; ?>">Manage questions →</a></td>
                  <td><a href="/admin/download-exam.php?id=<?php echo (int) $ex['id']; ?>">⬇ .docx</a></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>
    <?php endforeach; ?>
    </div>
  <?php endif; ?>
  </main>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
