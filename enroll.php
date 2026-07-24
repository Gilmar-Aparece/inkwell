<?php
// Inside/dashboard page — suppress the marketing topbar (see includes/header.php).
$__hideTopbar = true;
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/exams_db.php';
require_once __DIR__ . '/includes/schools.php';
require_once __DIR__ . '/includes/curriculum.php';

$user = inkwell_current_user();
if (!$user) {
  header('Location: /login.php?next=' . urlencode('/enroll.php'));
  exit;
}

$notice = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user['role'] === 'student') {
  $action = $_POST['action'] ?? '';

  if ($action === 'forward_for_approval') {
    $term = trim($_POST['term'] ?? '');
    $academicYear = trim($_POST['academic_year'] ?? '');
    $yearLevel = trim($_POST['year_level'] ?? '');
    $subjectIds = array_unique(array_map('intval', $_POST['subject_ids'] ?? []));
    $added = 0;
    $skipped = 0;
    foreach ($subjectIds as $sid) {
      if ($sid <= 0) continue;
      $subj = inkwell_get_subject($sid);
      if (!$subj || $subj['teacher_status'] !== 'active') { $skipped++; continue; }
      if (inkwell_is_enrolled($user['id'], $sid) || inkwell_has_pending_request($user['id'], $sid)) { $skipped++; continue; }
      inkwell_request_enrollment($user['id'], $sid, $term, $academicYear, $yearLevel);
      $added++;
    }
    if ($added > 0) {
      $notice = 'Forwarded ' . $added . ' subject' . ($added === 1 ? '' : 's') . ' for approval. You\'ll get access to each one once its teacher approves it.';
      if ($skipped > 0) $notice .= ' (' . $skipped . ' already added or unavailable, skipped.)';
    } else {
      $error = 'Add at least one subject before forwarding for approval.';
    }
  }

  if ($action === 'cancel_request' || $action === 'leave_class') {
    $subjectId = (int) ($_POST['subject_id'] ?? 0);
    inkwell_unenroll_student($user['id'], $subjectId);
    $notice = $action === 'cancel_request' ? 'Enrollment request cancelled.' : 'Left the class.';
  }
}

$pageTitle = 'Enrollment Portal';
include __DIR__ . '/includes/header.php';
$driveActive = 'enroll';
$driveCrumbs = [['label' => 'Home', 'href' => '/index.php'], ['label' => 'Enrollment Portal']];
$driveTitle = 'Enrollment Portal';
$driveSubtitle = 'Set your term, then add subjects — on curriculum for regular students, or one at a time if you\'re irregular.';
include __DIR__ . '/includes/drive_shell_top.php';

if ($user['role'] !== 'student'):
?>
  <section class="admin-card glass-card">
    <p class="admin-sub">The enrollment portal is for student accounts. <?php echo $user['role'] === 'teacher' ? 'Approve incoming requests from your <a href="/teacher/dashboard.php">teacher dashboard</a>.' : ($user['role'] === 'dean' ? 'You can review your school\'s enrollment activity from your <a href="/dean/dashboard.php">dean dashboard</a>.' : ''); ?></p>
  </section>
<?php
else:
  $approvedSubjects = inkwell_student_enrolled_subjects($user['id']);
  $pendingSubjects = inkwell_student_pending_subjects($user['id']);
  $takenIds = array_merge(array_column($approvedSubjects, 'id'), array_column($pendingSubjects, 'id'));

  $mySchool = !empty($user['school_id']) ? inkwell_get_school($user['school_id']) : null;
  $mySchoolSubjects = $mySchool ? inkwell_school_subjects($mySchool['id']) : [];

  // If the registrar has assigned this student a department AND built a
  // curriculum for it, use the real required-subject list (scoped to
  // their department + year/term). Otherwise fall back to "every school
  // subject" like before — so nothing breaks for schools that haven't
  // set up a curriculum yet.
  $curriculumBuilt = $mySchool && !empty($user['department_id'])
    ? inkwell_student_curriculum_subjects($mySchool['id'], $user['department_id'])
    : [];

  if (!empty($curriculumBuilt)) {
    $curriculumSubjects = array_values(array_filter($curriculumBuilt, function ($s) use ($takenIds) {
      return !in_array($s['id'], $takenIds);
    }));
  } else {
    $curriculumSubjects = array_values(array_filter($mySchoolSubjects, function ($s) use ($takenIds) {
      return !in_array($s['id'], $takenIds);
    }));
  }
  $mySchoolIds = array_column($mySchoolSubjects, 'id');

  $allSubjects = inkwell_all_subjects();
  $otherAvailable = array_values(array_filter($allSubjects, function ($s) use ($takenIds, $mySchoolIds) {
    return !in_array($s['id'], $takenIds) && !in_array($s['id'], $mySchoolIds);
  }));
  // Irregular students can hand-pick from their curriculum too, plus every other open subject.
  $pickableSubjects = array_merge($curriculumSubjects, $otherAvailable);

  $curriculumJson = json_encode(array_map(function ($s) {
    return [
      'id' => (int) $s['id'], 'title' => $s['title'], 'teacher' => $s['teacher_name'],
      'term' => $s['term'] ?? '', 'academic_year' => $s['academic_year'] ?? '',
      // Present only when this subject came from a real curriculum slot
      // (see inkwell_student_curriculum_subjects()) — used client-side to
      // only auto-add subjects matching the year level the student picked.
      'slot_year_level' => $s['slot_year_level'] ?? '',
      'slot_term' => $s['slot_term'] ?? '',
    ];
  }, $curriculumSubjects));
  $pickableJson = json_encode(array_map(function ($s) {
    return ['id' => (int) $s['id'], 'title' => $s['title'], 'teacher' => $s['teacher_name'], 'term' => $s['term'] ?? '', 'academic_year' => $s['academic_year'] ?? ''];
  }, $pickableSubjects));

  $currentYear = (int) date('Y');
  $academicYears = [];
  for ($y = $currentYear - 1; $y <= $currentYear + 1; $y++) $academicYears[] = $y . '-' . ($y + 1);
?>
  <?php if ($notice): ?><div class="exam-result pass"><?php echo htmlspecialchars($notice); ?></div><?php endif; ?>
  <?php if ($error): ?><div class="exam-result fail"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

  <section class="admin-card glass-card">
    <div class="admin-header-row" style="margin-bottom:0;">
      <div>
        <h2>My Enrollment</h2>
        <p class="admin-sub">Status of subjects you've requested or been approved into.</p>
      </div>
      <?php if (!empty($approvedSubjects)): ?>
        <a class="btn" href="/grades.php">📊 My Grades</a>
        <a class="btn" href="/cor.php">📄 Download COR</a>
      <?php endif; ?>
    </div>
    <?php if (empty($approvedSubjects) && empty($pendingSubjects)): ?>
      <p class="admin-sub">Nothing yet — follow the steps below to add subjects.</p>
    <?php else: ?>
      <?php foreach ($approvedSubjects as $s): ?>
        <div class="enroll-status-row">
          <div>
            <div class="esr-name"><?php echo htmlspecialchars($s['title']); ?></div>
            <div class="esr-meta">with <?php echo htmlspecialchars($s['teacher_name']); ?><?php echo !empty($s['term']) ? ' · ' . htmlspecialchars($s['term']) : ''; ?><?php echo !empty($s['academic_year']) ? ' ' . htmlspecialchars($s['academic_year']) : ''; ?><?php echo !empty($s['year_level']) ? ' · ' . htmlspecialchars($s['year_level']) : ''; ?></div>
          </div>
          <div style="display:flex; align-items:center; gap:10px;">
            <span class="badge badge-approved">Approved</span>
            <form method="post" action="/enroll.php" class="enroll-confirm-form" data-confirm-message="Leave &ldquo;<?php echo htmlspecialchars($s['title']); ?>&rdquo;? You'll need to be re-approved to rejoin.">
              <input type="hidden" name="action" value="leave_class">
              <input type="hidden" name="subject_id" value="<?php echo (int) $s['id']; ?>">
              <button class="btn" type="submit">Leave</button>
            </form>
          </div>
        </div>
      <?php endforeach; ?>
      <?php foreach ($pendingSubjects as $s): ?>
        <div class="enroll-status-row">
          <div>
            <div class="esr-name"><?php echo htmlspecialchars($s['title']); ?></div>
            <div class="esr-meta">with <?php echo htmlspecialchars($s['teacher_name']); ?><?php echo !empty($s['term']) ? ' · ' . htmlspecialchars($s['term']) : ''; ?><?php echo !empty($s['academic_year']) ? ' ' . htmlspecialchars($s['academic_year']) : ''; ?><?php echo !empty($s['year_level']) ? ' · ' . htmlspecialchars($s['year_level']) : ''; ?> · forwarded for approval</div>
          </div>
          <div style="display:flex; align-items:center; gap:10px;">
            <span class="badge badge-pending">Pending</span>
            <form method="post" action="/enroll.php" class="enroll-confirm-form" data-confirm-message="Cancel your enrollment request for &ldquo;<?php echo htmlspecialchars($s['title']); ?>&rdquo;?">
              <input type="hidden" name="action" value="cancel_request">
              <input type="hidden" name="subject_id" value="<?php echo (int) $s['id']; ?>">
              <button class="btn" type="submit">Cancel</button>
            </form>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </section>

  <div class="modal-backdrop" id="enrollConfirmModal">
    <div class="modal" style="max-width:420px;">
      <div class="modal-head">
        <h2>Are you sure?</h2>
        <button type="button" data-modal-close aria-label="Close">✕</button>
      </div>
      <p class="admin-sub" id="enrollConfirmMessage"></p>
      <div style="display:flex; gap:10px; justify-content:flex-end; margin-top:18px;">
        <button type="button" class="btn" data-modal-close>Nevermind</button>
        <button type="button" class="btn primary" id="enrollConfirmYesBtn">Yes, continue</button>
      </div>
    </div>
  </div>

  <form method="post" action="/enroll.php" id="enrollForm" class="admin-form">
    <input type="hidden" name="action" value="forward_for_approval">

    <section class="admin-card glass-card">
      <h2><span class="step-num">1</span> Enrollment Details</h2>
      <div class="form-grid-2">
        <div>
          <label for="term">Term</label>
          <select id="term" name="term">
            <option value="1st Semester">1st Semester</option>
            <option value="2nd Semester">2nd Semester</option>
            <option value="Summer">Summer</option>
          </select>
        </div>
        <div>
          <label for="academic_year">Academic Year</label>
          <select id="academic_year" name="academic_year">
            <?php foreach ($academicYears as $ay): ?>
              <option value="<?php echo htmlspecialchars($ay); ?>"<?php echo $ay === ($currentYear . '-' . ($currentYear + 1)) ? ' selected' : ''; ?>><?php echo htmlspecialchars($ay); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div style="margin-top:14px;">
        <label for="year_level">Year Level</label>
        <select id="year_level" name="year_level">
          <?php foreach (inkwell_year_levels() as $__yl): ?>
            <option value="<?php echo htmlspecialchars($__yl); ?>"><?php echo htmlspecialchars($__yl); ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <label style="margin-top:14px;">I am enrolling as</label>
      <div class="role-picker role-picker-cards role-picker-cards-3" style="max-width:480px;">
        <label class="role-option role-card active" data-student-type-btn="regular">
          <input type="radio" name="student_type_ui" value="regular" checked>
          <span class="role-option-icon" aria-hidden="true">🎓</span>
          <span class="role-card-text">
            <strong>Regular</strong>
            <small>Following the standard curriculum</small>
          </span>
        </label>
        <label class="role-option role-card" data-student-type-btn="irregular">
          <input type="radio" name="student_type_ui" value="irregular">
          <span class="role-option-icon" aria-hidden="true">🧭</span>
          <span class="role-card-text">
            <strong>Irregular</strong>
            <small>Picking subjects one at a time</small>
          </span>
        </label>
      </div>
    </section>

    <section class="admin-card glass-card" id="regularPanel">
      <h2><span class="step-num">2</span> Subjects — Regular</h2>
      <?php if (!empty($curriculumSubjects)): ?>
        <p class="admin-sub">This adds every subject offered at <?php echo $mySchool ? htmlspecialchars($mySchool['name']) : 'your school'; ?> that you haven't taken yet, for the term you picked above. Double-check the list below, remove anything you don't need, then forward it for approval.</p>
        <button type="button" class="btn primary btn-curriculum" id="addCurriculumBtn">+ Add Subjects on Curriculum</button>
        <p class="admin-sub" id="curriculumStatus" style="margin-top:8px;"></p>
      <?php elseif (!$mySchool): ?>
        <p class="admin-sub">You're not linked to a school yet, so there's no curriculum to auto-add. Visit <a href="/my-school.php">My school</a> to join one, or switch to <strong>Irregular</strong> above to hand-pick subjects instead.</p>
      <?php else: ?>
        <p class="admin-sub">You've already added every subject on your school's curriculum. Switch to <strong>Irregular</strong> above if you want to add something else.</p>
      <?php endif; ?>
    </section>

    <section class="admin-card glass-card" id="irregularPanel" style="display:none;">
      <h2><span class="step-num">2</span> Subjects — Irregular</h2>
      <?php if (!empty($pickableSubjects)): ?>
        <p class="admin-sub">Search for a subject, pick it from the list, then click <strong>Add</strong>. Repeat for each subject you want, then forward the list for approval.</p>
        <div class="form-grid-2" style="align-items:end;">
          <div class="search-combo">
            <label for="irregularSearch">Subject</label>
            <input type="text" id="irregularSearch" placeholder="Search by subject or teacher name…" autocomplete="off">
            <input type="hidden" id="irregularSelectedId">
            <div id="irregularResults" class="search-combo-results" hidden></div>
          </div>
          <div>
            <button type="button" class="btn primary" style="width:100%; justify-content:center;" id="irregularAddBtn" disabled>add</button>
          </div>
        </div>
        <p class="admin-sub" id="irregularStatus" style="margin-top:8px;"></p>
      <?php else: ?>
        <p class="admin-sub">No subjects are available to add right now.</p>
      <?php endif; ?>
    </section>

    <section class="admin-card glass-card">
      <h2><span class="step-num">3</span> Review &amp; Forward for Approval</h2>
      <p class="admin-sub">Subjects you've added so far. Grouped/populated here — double-check before forwarding.</p>
      <div id="stagedEmpty" class="admin-sub">No subjects added yet.</div>
      <div id="stagedList" class="staged-list"></div>
      <div id="stagedInputs"></div>
      <button class="btn primary" type="submit" id="forwardBtn" style="margin-top:16px;" disabled>Forward for Approval →</button>
    </section>
  </form>

  <script>
    (function () {
      var curriculum = <?php echo $curriculumJson ?: '[]'; ?>;
      var pickable = <?php echo $pickableJson ?: '[]'; ?>;
      var staged = {};

      var termSelect = document.getElementById('term');
      var academicYearSelect = document.getElementById('academic_year');
      var yearLevelSelect = document.getElementById('year_level');
      var curriculumStatus = document.getElementById('curriculumStatus');
      var irregularStatus = document.getElementById('irregularStatus');
      var addCurriculumBtn = document.getElementById('addCurriculumBtn');

      // A subject with no term/year set is treated as available for any
      // term/year (so nothing silently disappears just because a
      // registrar hasn't assigned one yet). A subject WITH a term or year
      // only matches that exact value. Curriculum subjects additionally
      // carry slot_year_level/slot_term (from the Curriculum Builder) —
      // when present, those must match the student's picked year level too.
      function filterByTermAndYear(list, term, year, yearLevel) {
        return list.filter(function (s) {
          var termOk = !s.term || s.term === term;
          var yearOk = !s.academic_year || s.academic_year === year;
          var slotYearOk = !s.slot_year_level || s.slot_year_level === yearLevel;
          var slotTermOk = !s.slot_term || s.slot_term === term;
          return termOk && yearOk && slotYearOk && slotTermOk;
        });
      }

      var filteredCurriculum = curriculum;
      var filteredPickable = pickable;

      function refreshFiltered() {
        var term = termSelect ? termSelect.value : '';
        var year = academicYearSelect ? academicYearSelect.value : '';
        var yearLevel = yearLevelSelect ? yearLevelSelect.value : '';
        filteredCurriculum = filterByTermAndYear(curriculum, term, year, yearLevel);
        filteredPickable = filterByTermAndYear(pickable, term, year, yearLevel);

        var label = term + (year ? ' · ' + year : '');

        if (curriculumStatus && curriculum.length) {
          curriculumStatus.textContent = filteredCurriculum.length
            ? filteredCurriculum.length + ' subject' + (filteredCurriculum.length === 1 ? '' : 's') + ' available for ' + label + '.'
            : 'No subjects available for ' + label + '.';
          if (addCurriculumBtn) {
            addCurriculumBtn.disabled = filteredCurriculum.length === 0;
            addCurriculumBtn.textContent = filteredCurriculum.length
              ? '+ Add ' + filteredCurriculum.length + ' Subject' + (filteredCurriculum.length === 1 ? '' : 's') + ' on Curriculum'
              : '+ Add Subjects on Curriculum';
          }
        }

        if (irregularStatus && pickable.length) {
          irregularStatus.textContent = filteredPickable.length
            ? filteredPickable.length + ' subject' + (filteredPickable.length === 1 ? '' : 's') + ' available for ' + label + '.'
            : 'No subjects available for ' + label + '.';
        }

        // Drop any staged subject that no longer matches the newly picked term/year.
        var pickableIds = {};
        filteredPickable.forEach(function (s) { pickableIds[s.id] = true; });
        Object.keys(staged).forEach(function (id) {
          if ((staged[id].term || staged[id].academic_year) && !pickableIds[id]) delete staged[id];
        });
        render();

        if (typeof showResults === 'function' && irregularSearch) showResults(irregularSearch.value);
      }

      var stagedList = document.getElementById('stagedList');
      var stagedEmpty = document.getElementById('stagedEmpty');
      var stagedInputs = document.getElementById('stagedInputs');
      var forwardBtn = document.getElementById('forwardBtn');

      function render() {
        var ids = Object.keys(staged);
        stagedList.innerHTML = '';
        stagedInputs.innerHTML = '';
        stagedEmpty.style.display = ids.length ? 'none' : '';
        forwardBtn.disabled = ids.length === 0;

        ids.forEach(function (id) {
          var item = staged[id];

          var row = document.createElement('div');
          row.className = 'enroll-status-row';
          var info = document.createElement('div');
          info.innerHTML = '<div class="esr-name"></div><div class="esr-meta">with </div>';
          info.querySelector('.esr-name').textContent = item.title;
          info.querySelector('.esr-meta').append(item.teacher);
          row.appendChild(info);

          var removeBtn = document.createElement('button');
          removeBtn.type = 'button';
          removeBtn.className = 'btn';
          removeBtn.textContent = 'Remove';
          removeBtn.addEventListener('click', function () { removeStaged(id); });
          row.appendChild(removeBtn);

          stagedList.appendChild(row);

          var hidden = document.createElement('input');
          hidden.type = 'hidden';
          hidden.name = 'subject_ids[]';
          hidden.value = id;
          stagedInputs.appendChild(hidden);
        });
      }

      function addStaged(item) {
        if (!item || staged[item.id]) return;
        staged[item.id] = item;
        render();
      }

      function removeStaged(id) {
        delete staged[id];
        render();
      }

      if (addCurriculumBtn) {
        addCurriculumBtn.addEventListener('click', function () {
          filteredCurriculum.forEach(addStaged);
        });
      }

      var irregularAddBtn = document.getElementById('irregularAddBtn');
      var irregularSearch = document.getElementById('irregularSearch');
      var irregularSelectedId = document.getElementById('irregularSelectedId');
      var irregularResults = document.getElementById('irregularResults');

      if (irregularAddBtn && irregularSearch && irregularResults) {
        function closeResults() { irregularResults.hidden = true; irregularResults.innerHTML = ''; }

        function pickResult(item) {
          irregularSearch.value = item.title + ' — ' + item.teacher;
          irregularSelectedId.value = item.id;
          irregularAddBtn.disabled = false;
          closeResults();
        }

        function showResults(query) {
          var q = query.trim().toLowerCase();
          var matches = !q ? filteredPickable : filteredPickable.filter(function (s) {
            return s.title.toLowerCase().indexOf(q) !== -1 || s.teacher.toLowerCase().indexOf(q) !== -1;
          });
          matches = matches.slice(0, 8);

          irregularResults.innerHTML = '';
          if (matches.length === 0) {
            var empty = document.createElement('div');
            empty.className = 'search-combo-empty';
            empty.textContent = 'No subjects match "' + query + '".';
            irregularResults.appendChild(empty);
          } else {
            matches.forEach(function (item) {
              var opt = document.createElement('button');
              opt.type = 'button';
              opt.className = 'search-combo-option';
              opt.innerHTML = '<span class="search-combo-option-title"></span><span class="search-combo-option-sub"></span>';
              opt.querySelector('.search-combo-option-title').textContent = item.title;
              opt.querySelector('.search-combo-option-sub').textContent = 'with ' + item.teacher;
              opt.addEventListener('mousedown', function (e) { e.preventDefault(); pickResult(item); });
              irregularResults.appendChild(opt);
            });
          }
          irregularResults.hidden = false;
        }

        irregularSearch.addEventListener('input', function () {
          irregularSelectedId.value = '';
          irregularAddBtn.disabled = true;
          showResults(irregularSearch.value);
        });
        irregularSearch.addEventListener('focus', function () { showResults(irregularSearch.value); });
        irregularSearch.addEventListener('blur', function () { setTimeout(closeResults, 120); });

        irregularAddBtn.addEventListener('click', function () {
          var id = irregularSelectedId.value;
          var item = filteredPickable.filter(function (s) { return String(s.id) === String(id); })[0];
          if (!item) return;
          addStaged(item);
          irregularSearch.value = '';
          irregularSelectedId.value = '';
          irregularAddBtn.disabled = true;
        });
      }

      var regularPanel = document.getElementById('regularPanel');
      var irregularPanel = document.getElementById('irregularPanel');
      document.querySelectorAll('[data-student-type-btn]').forEach(function (label) {
        label.addEventListener('click', function () {
          document.querySelectorAll('[data-student-type-btn]').forEach(function (l) { l.classList.remove('active'); });
          label.classList.add('active');
          var isRegular = label.getAttribute('data-student-type-btn') === 'regular';
          regularPanel.style.display = isRegular ? '' : 'none';
          irregularPanel.style.display = isRegular ? 'none' : '';
        });
      });

      if (termSelect) {
        termSelect.addEventListener('change', refreshFiltered);
      }
      if (academicYearSelect) {
        academicYearSelect.addEventListener('change', refreshFiltered);
      }
      if (yearLevelSelect) {
        yearLevelSelect.addEventListener('change', refreshFiltered);
      }

      render();
      refreshFiltered();

      // ---- Leave/Cancel confirmation: shared modal instead of native confirm() ----
      var enrollConfirmModal = document.getElementById('enrollConfirmModal');
      var enrollConfirmMessage = document.getElementById('enrollConfirmMessage');
      var enrollConfirmYesBtn = document.getElementById('enrollConfirmYesBtn');
      var pendingConfirmForm = null;

      if (enrollConfirmModal && enrollConfirmYesBtn) {
        document.querySelectorAll('.enroll-confirm-form').forEach(function (f) {
          f.addEventListener('submit', function (e) {
            e.preventDefault();
            pendingConfirmForm = f;
            enrollConfirmMessage.textContent = f.getAttribute('data-confirm-message') || 'Are you sure?';
            enrollConfirmModal.classList.add('open');
            document.body.style.overflow = 'hidden';
          });
        });

        enrollConfirmYesBtn.addEventListener('click', function () {
          enrollConfirmModal.classList.remove('open');
          document.body.style.overflow = '';
          if (pendingConfirmForm) { pendingConfirmForm.submit(); pendingConfirmForm = null; }
        });
      }
    })();
  </script>
<?php endif; ?>

<?php include __DIR__ . '/includes/drive_shell_bottom.php'; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>
