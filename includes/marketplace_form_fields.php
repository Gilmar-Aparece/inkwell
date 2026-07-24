<?php
/**
 * Shared <fieldset> markup for the create/edit listing form in sell.php.
 * Expects $categories (array) and $editing (listing row array, or null
 * when creating) to already be set by the including page.
 */
$v = fn($key, $default = '') => htmlspecialchars((string) ($editing[$key] ?? $default));
?>
<div class="mkt-form-row">
  <label>Title</label>
  <input type="text" name="title" value="<?php echo $v('title'); ?>" required maxlength="150" placeholder="e.g. Barangay Records Management System">
</div>

<div class="mkt-form-row">
  <label>Short tagline (optional)</label>
  <input type="text" name="tagline" value="<?php echo $v('tagline'); ?>" maxlength="200" placeholder="One line that sells it">
</div>

<div class="mkt-form-row two-col">
  <div>
    <label>Category</label>
    <select name="category_id">
      <option value="">— Choose —</option>
      <?php foreach ($categories as $cat): ?>
        <option value="<?php echo (int) $cat['id']; ?>" <?php echo (!empty($editing['category_id']) && (int) $editing['category_id'] === (int) $cat['id']) ? 'selected' : ''; ?>>
          <?php echo htmlspecialchars($cat['icon'] . ' ' . $cat['name']); ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>
  <div>
    <label>Price (₱, 0 = free)</label>
    <input type="number" name="price" value="<?php echo $v('price', '0'); ?>" min="0" step="0.01">
  </div>
</div>

<div class="mkt-form-row">
  <label>Description</label>
  <textarea name="description" rows="5" required placeholder="What does it do, who is it for, what's included in the ZIP?"><?php echo $v('description'); ?></textarea>
</div>

<div class="mkt-form-row">
  <label>Tech stack (optional)</label>
  <input type="text" name="tech_stack" value="<?php echo $v('tech_stack'); ?>" placeholder="e.g. PHP, MySQL, Bootstrap">
</div>

<div class="mkt-form-row">
  <label>Live preview URL (optional — leave blank to auto-generate one from your ZIP)</label>
  <input type="url" name="preview_url" value="<?php echo $v('preview_url'); ?>" placeholder="https://your-demo-link.example.com">
  <small class="admin-sub">If your ZIP has an <code>index.html</code> in it, Inkwell automatically extracts it and shows the <strong>actual live page</strong> right on your listing card — no screenshot needed, no external hosting. Fill this in only if you're hosting the demo somewhere else yourself.</small>
</div>

<div class="mkt-form-row two-col">
  <div>
    <label>Thumbnail image<?php echo $editing ? ' (leave blank to keep current)' : ''; ?></label>
    <div class="mkt-dropzone" data-dropzone data-mode="single-image" data-input="thumbnail">
      <input type="file" name="thumbnail" id="mktThumbnailInput" accept="image/png,image/jpeg,image/webp">
      <span class="mkt-dropzone-icon">🖼️</span>
      <span class="mkt-dropzone-label"><strong>Drag & drop</strong> or click to choose</span>
      <span class="mkt-dropzone-hint">PNG, JPG or WEBP, up to 3MB. Only used when there's no auto live preview.</span>
      <div class="mkt-dropzone-files" data-preview></div>
    </div>
  </div>
  <div>
    <label>Screenshots (up to 6, optional)</label>
    <div class="mkt-dropzone" data-dropzone data-mode="multi-image" data-input="screenshots" data-max="6">
      <input type="file" name="screenshots[]" id="mktScreenshotsInput" accept="image/png,image/jpeg,image/webp" multiple>
      <span class="mkt-dropzone-icon">🖼️</span>
      <span class="mkt-dropzone-label"><strong>Drag & drop</strong> or click to choose (up to 6)</span>
      <span class="mkt-dropzone-hint">PNG, JPG or WEBP, up to 3MB each.</span>
      <div class="mkt-dropzone-files" data-preview></div>
    </div>
  </div>
</div>

<div class="mkt-form-row">
  <label>System ZIP file<?php echo $editing ? ' (leave blank to keep current' . (!empty($editing['zip_original_name']) ? ': ' . htmlspecialchars($editing['zip_original_name']) : '') . ')' : ''; ?></label>
  <div class="mkt-dropzone" data-dropzone data-mode="single-file" data-input="zip">
    <input type="file" name="zip_file" id="mktZipInput" accept=".zip" <?php echo $editing ? '' : 'required'; ?>>
    <span class="mkt-dropzone-icon">📦</span>
    <span class="mkt-dropzone-label"><strong>Drag & drop your ZIP</strong> or click to choose</span>
    <span class="mkt-dropzone-hint">Max 40MB. Include an <code>index.html</code> at the root for an automatic live preview.</span>
    <div class="mkt-dropzone-files" data-preview></div>
  </div>
  <small class="admin-sub">This is what buyers download once they unlock the system.</small>
</div>

<div class="mkt-form-row">
  <label>Download delay (days)</label>
  <input type="number" name="download_delay_days" value="<?php echo $v('download_delay_days', '0'); ?>" min="0" max="365" step="1" style="max-width:140px;">
  <small class="admin-sub">Optional. After a buyer redeems their unlock code, they'll see the listing as owned right away, but the <strong>ZIP download itself</strong> stays locked for this many days. Set to 0 for no delay (download unlocks immediately, same as before).</small>
</div>

<div class="mkt-form-row two-col">
  <div>
    <label>Your GCash number</label>
    <input type="text" name="gcash_number" value="<?php echo $v('gcash_number'); ?>" placeholder="09XX-XXX-XXXX">
  </div>
  <div>
    <label>Your GCash account name</label>
    <input type="text" name="gcash_name" value="<?php echo $v('gcash_name'); ?>" placeholder="Juan Dela Cruz">
  </div>
</div>
<small class="admin-sub">Buyers pay you directly on GCash — required if your price is above ₱0.</small>

<div class="mkt-form-row">
  <label>Visibility</label>
  <select name="status">
    <option value="active" <?php echo $v('status', 'active') === 'active' ? 'selected' : ''; ?>>Active — visible in the marketplace</option>
    <option value="draft" <?php echo $v('status') === 'draft' ? 'selected' : ''; ?>>Draft — hidden, only you can see it</option>
    <?php if ($editing): ?>
      <option value="hidden" <?php echo $v('status') === 'hidden' ? 'selected' : ''; ?>>Hidden — pause without deleting</option>
    <?php endif; ?>
  </select>
</div>

<?php if (!defined('INKWELL_MKT_DROPZONE_JS_PRINTED')): define('INKWELL_MKT_DROPZONE_JS_PRINTED', true); ?>
<script>
(function () {
  function humanSize(bytes) {
    if (bytes >= 1024 * 1024) return (bytes / (1024 * 1024)).toFixed(1) + 'MB';
    if (bytes >= 1024) return Math.round(bytes / 1024) + 'KB';
    return bytes + 'B';
  }

  function renderPreview(zone) {
    var input = zone.querySelector('input[type="file"]');
    var preview = zone.querySelector('[data-preview]');
    var mode = zone.getAttribute('data-mode');
    preview.innerHTML = '';
    var files = input.files ? Array.prototype.slice.call(input.files) : [];
    zone.classList.toggle('is-filled', files.length > 0);

    files.forEach(function (file, idx) {
      var chip = document.createElement('span');
      chip.className = 'mkt-dropzone-file';

      if (mode !== 'single-file' && file.type && file.type.indexOf('image/') === 0) {
        var img = document.createElement('img');
        img.src = URL.createObjectURL(file);
        chip.appendChild(img);
      }

      var label = document.createElement('span');
      label.textContent = file.name + ' (' + humanSize(file.size) + ')';
      chip.appendChild(label);

      var remove = document.createElement('button');
      remove.type = 'button';
      remove.className = 'mkt-dropzone-remove';
      remove.setAttribute('aria-label', 'Remove file');
      remove.textContent = '✕';
      remove.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        var dt = new DataTransfer();
        files.forEach(function (f, i) { if (i !== idx) dt.items.add(f); });
        input.files = dt.files;
        renderPreview(zone);
      });
      chip.appendChild(remove);

      preview.appendChild(chip);
    });
  }

  function setFiles(zone, incoming) {
    var input = zone.querySelector('input[type="file"]');
    var mode = zone.getAttribute('data-mode');
    var max = parseInt(zone.getAttribute('data-max') || '1', 10);
    var dt = new DataTransfer();

    if (mode === 'multi-image') {
      var existing = input.files ? Array.prototype.slice.call(input.files) : [];
      existing.concat(Array.prototype.slice.call(incoming))
        .slice(0, max)
        .forEach(function (f) { dt.items.add(f); });
    } else {
      if (incoming.length) dt.items.add(incoming[0]);
    }
    input.files = dt.files;
    renderPreview(zone);
  }

  document.querySelectorAll('[data-dropzone]').forEach(function (zone) {
    var input = zone.querySelector('input[type="file"]');
    input.addEventListener('change', function () { renderPreview(zone); });

    ['dragenter', 'dragover'].forEach(function (evt) {
      zone.addEventListener(evt, function (e) {
        e.preventDefault();
        e.stopPropagation();
        zone.classList.add('is-dragover');
      });
    });
    ['dragleave', 'dragend', 'drop'].forEach(function (evt) {
      zone.addEventListener(evt, function (e) {
        e.preventDefault();
        e.stopPropagation();
        zone.classList.remove('is-dragover');
      });
    });
    zone.addEventListener('drop', function (e) {
      var files = (e.dataTransfer && e.dataTransfer.files) ? e.dataTransfer.files : [];
      if (files.length) setFiles(zone, files);
    });

    renderPreview(zone);
  });
})();
</script>
<?php endif; ?>
