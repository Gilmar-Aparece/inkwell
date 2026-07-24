<?php
// Inside/dashboard page — suppress the marketing topbar (see includes/header.php).
$__hideTopbar = true;
require_once __DIR__ . '/data/lessons.php';
require_once __DIR__ . '/includes/store.php';
require_once __DIR__ . '/includes/exams_db.php';

$id = $_GET['id'] ?? '';
$cert = null;

if ($id) {
  $dbCert = inkwell_db_find_certificate($id);
  if ($dbCert) {
    $cert = [
      'id' => $dbCert['id'],
      'name' => $dbCert['student_name'],
      'category' => $dbCert['category_key'],
      'label' => $dbCert['label'],
      'score' => $dbCert['score'],
      'total' => $dbCert['total'],
      'percent' => $dbCert['percent'],
      'issued_at' => $dbCert['issued_at'],
      'teacher_id' => $dbCert['teacher_id'],
      'teacher_name' => $dbCert['teacher_name'],
      'source' => $dbCert['source'] ?? 'exam',
      'issued_by_name' => $dbCert['issued_by_name'] ?? null,
      'issued_by_role' => $dbCert['issued_by_role'] ?? null,
      'custom_message' => $dbCert['custom_message'] ?? null,
      'accent_color' => $dbCert['accent_color'] ?? null,
      'issuer_school_id' => $dbCert['issuer_school_id'] ?? null,
      'template' => $dbCert['template'] ?? null,
      'font_choice' => $dbCert['font_choice'] ?? null,
      'bg_style' => $dbCert['bg_style'] ?? null,
      'title_text' => $dbCert['title_text'] ?? null,
      'seal_label' => $dbCert['seal_label'] ?? null,
      'signer_name_override' => $dbCert['signer_name_override'] ?? null,
      'signer_title_override' => $dbCert['signer_title_override'] ?? null,
      'signer_signature_override' => $dbCert['signer_signature_override'] ?? null,
    ];
  } else {
    $cert = inkwell_find_certificate($id); // legacy JSON-stored certificate
  }
}

if (!$cert) {
  http_response_code(404);
  $pageTitle = 'Certificate not found';
  include __DIR__ . '/includes/header.php';
  echo '<main style="padding:60px 20px;"><h1>Certificate not found</h1><p>Check the link, or <a href="/index.php">go back to Inkwell</a>.</p></main>';
  include __DIR__ . '/includes/footer.php';
  exit;
}

$signers = inkwell_get_cert_signers($cert);
$schoolLogo = inkwell_get_cert_school_logo($cert);
$catInfo = !empty($cert['category']) ? inkwell_category($cert['category']) : null;
$isManual = ($cert['source'] ?? 'exam') === 'manual';
$sealColor = $cert['accent_color'] ?? ($catInfo['color'] ?? '#5B7CFA');
$issuedTs = strtotime($cert['issued_at']);
$validThrough = date('F j, Y', strtotime('+3 years', $issuedTs));

$tplName = in_array($cert['template'] ?? '', ['classic', 'modern', 'minimal'], true) ? $cert['template'] : 'classic';
$bgName = in_array($cert['bg_style'] ?? '', ['solid', 'dots', 'gradient'], true) ? $cert['bg_style'] : 'solid';
$fontVars = [
  'serif' => "'Georgia', 'Iowan Old Style', serif",
  'sans' => "'Poppins', 'Inter', sans-serif",
  'default' => null,
];
$fontChoiceKey = in_array($cert['font_choice'] ?? '', ['serif', 'sans'], true) ? $cert['font_choice'] : 'default';
$fontCss = $fontVars[$fontChoiceKey];
$titleText = !empty($cert['title_text']) ? $cert['title_text'] : 'Inkwell Certifications';
$sealLabelText = !empty($cert['seal_label']) ? $cert['seal_label'] : $cert['label'];
$pageTitle = 'Certificate — ' . $cert['label'];
include __DIR__ . '/includes/header.php';
$driveActive = '';
$driveCrumbs = [['label' => 'Home', 'href' => '/index.php'], ['label' => 'Certificate']];
$driveHideCta = true;
include __DIR__ . '/includes/drive_shell_top.php';
?>
  <div class="cert-toolbar no-print">
    <a class="btn" href="/index.php">← Back to Inkwell</a>
    <button class="btn primary" onclick="window.print()" type="button">🖨 Print / Save as PDF</button>
  </div>

  <div class="cert-sheet cert-bg-<?php echo $bgName; ?>">
    <div class="cert-border cert-tpl-<?php echo $tplName; ?>" style="--seal: <?php echo htmlspecialchars($sealColor); ?>;<?php echo $fontCss ? ' --cert-font: ' . htmlspecialchars($fontCss) . ';' : ''; ?>">

      <div class="cert-logo">
        <?php if ($schoolLogo): ?>
          <img class="cert-logo-img" src="/assets/uploads/<?php echo htmlspecialchars($schoolLogo); ?>" alt="School logo" loading="lazy">
        <?php else: ?>
          <span class="cert-logo-mark" aria-hidden="true"><span class="nib-dot"></span></span>
          <span class="cert-logo-name">Inkwell</span>
        <?php endif; ?>
      </div>

      <div class="cert-body">
        <div class="cert-title"><?php echo htmlspecialchars($titleText); ?></div>
        <h1 class="cert-name"><?php echo htmlspecialchars($cert['name']); ?></h1>
        <?php if ($isManual): ?>
          <p class="cert-line">is recognized for</p>
          <h2 class="cert-course"><?php echo htmlspecialchars($cert['label']); ?></h2>
          <?php if (!empty($cert['custom_message'])): ?>
            <p class="cert-line cert-score"><?php echo htmlspecialchars($cert['custom_message']); ?></p>
          <?php endif; ?>
          <?php if (!empty($cert['issued_by_name'])): ?>
            <p class="cert-line">issued by <strong><?php echo htmlspecialchars($cert['issued_by_name']); ?></strong><?php echo !empty($cert['issued_by_role']) ? ' (' . htmlspecialchars(ucfirst($cert['issued_by_role'])) . ')' : ''; ?></p>
          <?php endif; ?>
        <?php else: ?>
          <p class="cert-line">has successfully completed the Inkwell certification exam requirements and is recognized as a</p>
          <h2 class="cert-course"><?php echo htmlspecialchars($cert['label']); ?></h2>
          <p class="cert-line cert-score">scoring <strong><?php echo (int) $cert['percent']; ?>%</strong> (<?php echo (int) $cert['score']; ?>/<?php echo (int) $cert['total']; ?>) on the certification exam</p>
          <?php if (!empty($cert['teacher_name'])): ?>
            <p class="cert-line">taken under the instruction of <strong><?php echo htmlspecialchars($cert['teacher_name']); ?></strong></p>
          <?php endif; ?>
        <?php endif; ?>

        <div class="cert-seal-row">
          <div class="cert-seal">
            <div class="cert-seal-ring">
              <div class="cert-seal-inner">
                <span class="cert-seal-check" aria-hidden="true">✓</span>
                <span class="cert-seal-label">Inkwell Certified</span>
                <span class="cert-seal-cat"><?php echo htmlspecialchars(strtoupper($sealLabelText)); ?></span>
              </div>
            </div>
            <div class="cert-seal-ribbon" aria-hidden="true"></div>
          </div>
        </div>

        <div class="cert-footer">
          <div class="cert-meta-block">
            <div class="cert-meta-row"><span>Date Certified</span><strong><?php echo htmlspecialchars(date('F j, Y', $issuedTs)); ?></strong></div>
            <div class="cert-meta-row"><span>Valid Through</span><strong><?php echo htmlspecialchars($validThrough); ?></strong></div>
            <div class="cert-meta-row"><span>Credential ID</span><strong><?php echo htmlspecialchars($cert['id']); ?></strong></div>
          </div>
          <div class="cert-sign-group cert-sign-group-<?php echo count($signers); ?>">
            <?php foreach ($signers as $signer): ?>
              <div class="cert-sign">
                <?php if (!empty($signer['signature_file'])): ?>
                  <img class="cert-sign-img" src="/assets/uploads/<?php echo htmlspecialchars($signer['signature_file']); ?>" alt="Signature of <?php echo htmlspecialchars($signer['name']); ?>" loading="lazy">
                <?php else: ?>
                  <div class="cert-sign-placeholder"></div>
                <?php endif; ?>
                <div class="cert-sign-line"></div>
                <div class="cert-sign-name"><?php echo htmlspecialchars($signer['name']); ?></div>
                <div class="cert-sign-title"><?php echo htmlspecialchars($signer['title']); ?></div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <div class="cert-copyright-footer">&copy; <?php echo date('Y'); ?> Inkwell. All rights reserved.</div>
    </div>
  </div>
<?php include __DIR__ . '/includes/drive_shell_bottom.php'; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>
