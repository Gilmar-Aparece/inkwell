<?php
// Inside/dashboard page — suppress the marketing topbar (see includes/header.php).
$__hideTopbar = true;
require_once __DIR__ . '/includes/schools.php';

$pageTitle = 'Schools';
include __DIR__ . '/includes/header.php';

$allSchools = array_values(inkwell_list_schools_with_stats());
$activeSchools = array_filter($allSchools, function ($sch) {
  return (int) $sch['teacher_count'] > 0 || (int) $sch['student_count'] > 0 || (int) $sch['certificate_count'] > 0;
});
$otherSchools = array_udiff($allSchools, $activeSchools, function ($a, $b) { return $a['id'] <=> $b['id']; });

$driveActive = 'schools';
$driveCrumbs = [['label' => 'Home', 'href' => '/index.php'], ['label' => 'Schools']];
$driveTitle = 'Schools';
$driveSubtitle = 'Every school set up by an approved dean, ranked by how active their teachers and students are.';
include __DIR__ . '/includes/drive_shell_top.php';
?>

<section class="drive-schools" id="top-schools">
  <?php if (empty($allSchools)): ?>
    <div class="school-top-empty">No schools have been set up yet. Register as a Dean to create the first one — once approved, it'll show up here.</div>
  <?php else: ?>
    <div class="search-filter">
      <input type="search" class="search-filter-input" data-filter-target="#allSchoolsTable" placeholder="Search schools by name...">
    </div>
    <div class="admin-table-wrap">
      <table class="admin-table" id="allSchoolsTable">
        <thead><tr><th>#</th><th>School</th><th>Teachers</th><th>Students</th><th>Certs</th><th></th></tr></thead>
        <tbody>
          <?php $__rank = 0; foreach (array_merge(array_values($activeSchools), array_values($otherSchools)) as $sch): $__rank++; ?>
            <tr data-filter-row>
              <td class="school-row-rank">#<?php echo $__rank; ?></td>
              <td>
                <div style="display:flex; align-items:center; gap:10px;">
                  <?php if ($sch['logo']): ?>
                    <img class="school-row-logo" src="/assets/uploads/<?php echo htmlspecialchars($sch['logo']); ?>" alt="" loading="lazy">
                  <?php else: ?>
                    <span class="school-row-logo" aria-hidden="true">🏫</span>
                  <?php endif; ?>
                  <strong><?php echo htmlspecialchars($sch['name']); ?></strong>
                </div>
              </td>
              <td><?php echo (int) $sch['teacher_count']; ?></td>
              <td><?php echo (int) $sch['student_count']; ?></td>
              <td><?php echo (int) $sch['certificate_count']; ?></td>
              <td><a class="school-row-view" href="/school.php?id=<?php echo (int) $sch['id']; ?>">View ↗</a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>

<?php include __DIR__ . '/includes/drive_shell_bottom.php'; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>
