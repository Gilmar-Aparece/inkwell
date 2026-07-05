<?php
require_once __DIR__ . '/data/lessons.php';
require_once __DIR__ . '/includes/store.php';

$id = $_GET['id'] ?? '';
$cert = $id ? inkwell_find_certificate($id) : null;

if (!$cert) {
  http_response_code(404);
  $pageTitle = 'Certificate not found';
  include __DIR__ . '/includes/header.php';
  echo '<main style="padding:60px 20px;"><h1>Certificate not found</h1><p>Check the link, or <a href="/index.php">go back to Inkwell</a>.</p></main>';
  include __DIR__ . '/includes/footer.php';
  exit;
}

$config = inkwell_get_config();
$catInfo = inkwell_category($cert['category'] ?? '');
$sealColor = $catInfo['color'] ?? '#5B7CFA';
$issuedTs = strtotime($cert['issued_at']);
$validThrough = date('F j, Y', strtotime('+3 years', $issuedTs));
$verifyUrl = (isset($_SERVER['HTTP_HOST']) ? '//' . $_SERVER['HTTP_HOST'] : '') . '/certificate.php?id=' . $cert['id'];
$pageTitle = 'Certificate — ' . $cert['label'];
include __DIR__ . '/includes/header.php';
?>
<main class="cert-main">
  <div class="cert-toolbar no-print">
    <a class="btn" href="/index.php">← Back to Inkwell</a>
    <button class="btn primary" onclick="window.print()" type="button">🖨 Print / Save as PDF</button>
  </div>

  <div class="cert-sheet">
    <div class="cert-border" style="--seal: <?php echo htmlspecialchars($sealColor); ?>;">

      <div class="cert-logo">
        <span class="cert-logo-mark" aria-hidden="true"><span class="nib-dot"></span></span>
        <span class="cert-logo-name">Inkwell</span>
      </div>

      <div class="cert-body">
        <div class="cert-title">Inkwell Certifications</div>
        <h1 class="cert-name"><?php echo htmlspecialchars($cert['name']); ?></h1>
        <p class="cert-line">has successfully completed the Inkwell certification exam requirements and is recognized as a</p>
        <h2 class="cert-course"><?php echo htmlspecialchars($cert['label']); ?></h2>
        <p class="cert-line cert-score">scoring <strong><?php echo (int) $cert['percent']; ?>%</strong> (<?php echo (int) $cert['score']; ?>/<?php echo (int) $cert['total']; ?>) on the certification exam</p>

        <div class="cert-seal-row">
          <div class="cert-seal">
            <div class="cert-seal-ring">
              <div class="cert-seal-inner">
                <span class="cert-seal-check" aria-hidden="true">✓</span>
                <span class="cert-seal-label">Inkwell Certified</span>
                <span class="cert-seal-cat"><?php echo htmlspecialchars(strtoupper($cert['label'])); ?></span>
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
          <div class="cert-sign">
            <?php if (!empty($config['signature_file'])): ?>
              <img class="cert-sign-img" src="/assets/uploads/<?php echo htmlspecialchars($config['signature_file']); ?>" alt="Signature of <?php echo htmlspecialchars($config['signer_name']); ?>">
            <?php else: ?>
              <div class="cert-sign-placeholder"></div>
            <?php endif; ?>
            <div class="cert-sign-line"></div>
            <div class="cert-sign-name"><?php echo htmlspecialchars($config['signer_name']); ?></div>
            <div class="cert-sign-title"><?php echo htmlspecialchars($config['signer_title']); ?></div>
          </div>
        </div>
      </div>

      <div class="cert-verify-footer">
        <div>Validate this certificate's authenticity at <strong><?php echo htmlspecialchars($_SERVER['HTTP_HOST'] ?? 'inkwell'); ?>/certificate.php</strong></div>
        <div>Certificate Verification No. <?php echo htmlspecialchars(strtoupper($cert['id'])); ?></div>
        <div class="cert-copyright">&copy; <?php echo date('Y'); ?> Inkwell. All rights reserved.</div>
      </div>
    </div>
  </div>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
