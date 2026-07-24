<?php
/**
 * Shared "issue + design a certificate" form with a live preview.
 * Expects these variables set by the including page:
 *   $students           array of ['id' => ..., 'name' => ...]
 *   $certFormAction      string, form action URL (e.g. /teacher/certificates.php)
 *   $certDefaultSigner   ['name' => ..., 'title' => ...] the signer that
 *                        will be used if the "Signer" fields below are left
 *                        blank (the account/school/global default)
 *   $certSignerNote      short string explaining where that default comes from
 */
$__certDefaultSignerName = $certDefaultSigner['name'] ?? 'Inkwell';
$__certDefaultSignerTitle = $certDefaultSigner['title'] ?? 'Administrator';
?>
<style>
  .cert-editor-shell { display: grid; grid-template-columns: minmax(280px, 380px) 1fr; gap: 18px; align-items: start; }
  @media (max-width: 900px) { .cert-editor-shell { grid-template-columns: 1fr; } }
  .cert-editor-shell > * { min-width: 0; }
  .cert-editor-shell .admin-form { gap: 10px; }
  .cert-editor-shell fieldset { border: 1px solid var(--border-soft); border-radius: var(--radius-sm); padding: 12px; margin: 0; }
  .cert-editor-shell legend { font-size: 0.78rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.03em; color: var(--ink-dim); padding: 0 6px; }
  .cert-editor-shell .form-grid-2 { margin-bottom: 8px; }
  .cert-swatch-row { display: flex; gap: 8px; flex-wrap: wrap; }
  .cert-swatch-row label { display: flex; flex-direction: column; align-items: center; gap: 4px; font-size: 0.72rem; color: var(--ink-dim); cursor: pointer; }
  .cert-swatch-row input[type="radio"] { margin: 0; }
  .cert-preview-col { position: sticky; top: 16px; min-width: 0; }
  .cert-preview-frame { width: 100%; min-width: 0; overflow: hidden; contain: layout paint; border: 1px solid var(--border-soft); border-radius: var(--radius-lg); background: var(--surface-2); padding: 14px; box-sizing: border-box; }
  .cert-preview-scale { width: 880px; transform-origin: top left; }
  .cert-preview-label { font-size: 0.78rem; color: var(--ink-dim); margin: 0 0 8px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.03em; }
</style>

<div class="cert-editor-shell">
  <form method="post" action="<?php echo htmlspecialchars($certFormAction); ?>" class="admin-form" id="certEditorForm">
    <input type="hidden" name="action" value="issue_certificate">

    <fieldset>
      <legend>Recipient &amp; title</legend>
      <div class="form-grid-2">
        <div>
          <label for="student_id">Student</label>
          <select id="student_id" name="student_id" required>
            <option value="">Select a student…</option>
            <?php foreach ($students as $s): ?>
              <option value="<?php echo (int) $s['id']; ?>"><?php echo htmlspecialchars($s['name']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label for="label">Certificate title</label>
          <input type="text" id="label" name="label" maxlength="150" placeholder="e.g. Outstanding Project" required>
        </div>
      </div>
      <label for="message">Message (optional)</label>
      <input type="text" id="message" name="message" maxlength="255" placeholder="e.g. For exceptional creativity and effort.">
    </fieldset>

    <fieldset>
      <legend>Customize this certificate</legend>
      <div class="form-grid-2">
        <div>
          <label for="heading_text">Heading text</label>
          <input type="text" id="heading_text" name="heading_text" maxlength="150" placeholder="Inkwell Certifications">
        </div>
        <div>
          <label for="seal_label">Seal text (optional)</label>
          <input type="text" id="seal_label" name="seal_label" maxlength="60" placeholder="Defaults to the title above">
        </div>
      </div>

      <label>Template</label>
      <div class="cert-swatch-row" id="tplSwatches">
        <label><input type="radio" name="template" value="classic" checked>Classic</label>
        <label><input type="radio" name="template" value="modern">Modern</label>
        <label><input type="radio" name="template" value="minimal">Minimal</label>
      </div>

      <label style="margin-top:8px;">Font</label>
      <div class="cert-swatch-row" id="fontSwatches">
        <label><input type="radio" name="font_choice" value="default" checked>Default</label>
        <label><input type="radio" name="font_choice" value="serif">Elegant serif</label>
        <label><input type="radio" name="font_choice" value="sans">Modern sans</label>
      </div>

      <label style="margin-top:8px;">Background</label>
      <div class="cert-swatch-row" id="bgSwatches">
        <label><input type="radio" name="bg_style" value="solid" checked>Solid</label>
        <label><input type="radio" name="bg_style" value="dots">Dotted</label>
        <label><input type="radio" name="bg_style" value="gradient">Gradient</label>
      </div>

      <label for="accent_color" style="margin-top:8px;">Accent color</label>
      <input type="color" id="accent_color" name="accent_color" value="#5B7CFA" style="width:80px; height:38px; padding:2px; cursor:pointer;">
    </fieldset>

    <fieldset>
      <legend>Signer (optional override)</legend>
      <p class="admin-sub" style="margin:0 0 8px;"><?php echo htmlspecialchars($certSignerNote ?? "Leave blank to use your default signer."); ?></p>
      <div class="form-grid-2">
        <div>
          <label for="signer_name">Signer name</label>
          <input type="text" id="signer_name" name="signer_name" maxlength="100" placeholder="<?php echo htmlspecialchars($__certDefaultSignerName); ?>">
        </div>
        <div>
          <label for="signer_title">Signer title</label>
          <input type="text" id="signer_title" name="signer_title" maxlength="150" placeholder="<?php echo htmlspecialchars($__certDefaultSignerTitle); ?>">
        </div>
      </div>
    </fieldset>

    <button class="btn primary" type="submit" style="margin-top:4px;">Issue certificate</button>
  </form>

  <div class="cert-preview-col">
    <p class="cert-preview-label">Live preview</p>
    <div class="cert-preview-frame" id="certPreviewFrame">
      <div class="cert-preview-scale" id="certPreviewScale">
        <div class="cert-sheet cert-bg-solid" id="prevSheet">
          <div class="cert-border cert-tpl-classic" id="prevBorder" style="--seal:#5B7CFA;">
            <div class="cert-logo">
              <span class="cert-logo-mark" aria-hidden="true"><span class="nib-dot"></span></span>
              <span class="cert-logo-name">Inkwell</span>
            </div>
            <div class="cert-body">
              <div class="cert-title" id="prevHeading">Inkwell Certifications</div>
              <h1 class="cert-name" id="prevName">Student Name</h1>
              <p class="cert-line">is recognized for</p>
              <h2 class="cert-course" id="prevCourse">Certificate title</h2>
              <p class="cert-line cert-score" id="prevMessage" style="display:none;"></p>
              <p class="cert-line" id="prevIssuedByWrap">issued by <strong id="prevIssuedBy"><?php echo htmlspecialchars($__certDefaultSignerName); ?></strong></p>

              <div class="cert-seal-row">
                <div class="cert-seal">
                  <div class="cert-seal-ring">
                    <div class="cert-seal-inner">
                      <span class="cert-seal-check" aria-hidden="true">✓</span>
                      <span class="cert-seal-label">Inkwell Certified</span>
                      <span class="cert-seal-cat" id="prevSealCat">TITLE</span>
                    </div>
                  </div>
                  <div class="cert-seal-ribbon" aria-hidden="true"></div>
                </div>
              </div>

              <div class="cert-footer">
                <div class="cert-meta-block">
                  <div class="cert-meta-row"><span>Date Certified</span><strong><?php echo date('F j, Y'); ?></strong></div>
                  <div class="cert-meta-row"><span>Valid Through</span><strong><?php echo date('F j, Y', strtotime('+3 years')); ?></strong></div>
                  <div class="cert-meta-row"><span>Credential ID</span><strong>(assigned on issue)</strong></div>
                </div>
                <div class="cert-sign">
                  <div class="cert-sign-placeholder"></div>
                  <div class="cert-sign-line"></div>
                  <div class="cert-sign-name" id="prevSignName"><?php echo htmlspecialchars($__certDefaultSignerName); ?></div>
                  <div class="cert-sign-title" id="prevSignTitle"><?php echo htmlspecialchars($__certDefaultSignerTitle); ?></div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
(function () {
  const form = document.getElementById('certEditorForm');
  if (!form) return;
  const $ = (id) => document.getElementById(id);
  const studentSel = $('student_id'), labelInput = $('label'), messageInput = $('message');
  const headingInput = $('heading_text'), sealInput = $('seal_label'), accentInput = $('accent_color');
  const signerNameInput = $('signer_name'), signerTitleInput = $('signer_title');
  const defaultSignerName = <?php echo json_encode($__certDefaultSignerName); ?>;
  const defaultSignerTitle = <?php echo json_encode($__certDefaultSignerTitle); ?>;

  const prevName = $('prevName'), prevCourse = $('prevCourse'), prevMessage = $('prevMessage');
  const prevHeading = $('prevHeading'), prevSealCat = $('prevSealCat');
  const prevSignName = $('prevSignName'), prevSignTitle = $('prevSignTitle');
  const prevBorder = $('prevBorder'), prevSheet = $('prevSheet');

  function update() {
    prevName.textContent = (studentSel.selectedOptions[0] && studentSel.value) ? studentSel.selectedOptions[0].textContent : 'Student Name';
    prevCourse.textContent = labelInput.value.trim() || 'Certificate title';
    prevSealCat.textContent = (sealInput.value.trim() || labelInput.value.trim() || 'TITLE').toUpperCase();
    prevHeading.textContent = headingInput.value.trim() || 'Inkwell Certifications';
    if (messageInput.value.trim()) {
      prevMessage.textContent = messageInput.value.trim();
      prevMessage.style.display = '';
    } else {
      prevMessage.style.display = 'none';
    }
    prevSignName.textContent = signerNameInput.value.trim() || defaultSignerName;
    prevSignTitle.textContent = signerTitleInput.value.trim() || defaultSignerTitle;
    prevBorder.style.setProperty('--seal', accentInput.value || '#5B7CFA');

    const tpl = form.querySelector('input[name="template"]:checked').value;
    prevBorder.className = 'cert-border cert-tpl-' + tpl;

    const bg = form.querySelector('input[name="bg_style"]:checked').value;
    prevSheet.className = 'cert-sheet cert-bg-' + bg;

    const font = form.querySelector('input[name="font_choice"]:checked').value;
    const fontMap = { serif: "'Georgia','Iowan Old Style',serif", sans: "'Poppins','Inter',sans-serif" };
    if (fontMap[font]) prevBorder.style.setProperty('--cert-font', fontMap[font]);
    else prevBorder.style.removeProperty('--cert-font');
  }

  ['input', 'change'].forEach(function (evt) {
    form.addEventListener(evt, update);
  });
  update();

  function rescale() {
    const frame = $('certPreviewFrame'), scale = $('certPreviewScale');
    if (!frame || !scale) return;
    const ratio = frame.clientWidth > 0 ? frame.clientWidth / 880 : 0.4;
    scale.style.transform = 'scale(' + ratio + ')';
    frame.style.height = (scale.scrollHeight * ratio) + 'px';
  }
  rescale();
  window.addEventListener('resize', rescale);
  if (window.ResizeObserver) {
    new ResizeObserver(rescale).observe($('certPreviewFrame'));
  } else {
    setInterval(rescale, 800); // fallback for browsers without ResizeObserver
  }
  // A couple of delayed re-checks catch late webfont/layout shifts.
  setTimeout(rescale, 50);
  setTimeout(rescale, 300);
})();
</script>
