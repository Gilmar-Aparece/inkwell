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
  <small class="admin-sub">If your ZIP has an <code>index.html</code> in it, Inkwell automatically hosts a click-to-preview demo from it — no external hosting needed. Fill this in only if you're hosting the demo somewhere else yourself.</small>
</div>

<div class="mkt-form-row two-col">
  <div>
    <label>Thumbnail image<?php echo $editing ? ' (leave blank to keep current)' : ''; ?></label>
    <input type="file" name="thumbnail" accept="image/png,image/jpeg,image/webp">
  </div>
  <div>
    <label>Screenshots (up to 6, optional)</label>
    <input type="file" name="screenshots[]" accept="image/png,image/jpeg,image/webp" multiple>
  </div>
</div>

<div class="mkt-form-row">
  <label>System ZIP file<?php echo $editing ? ' (leave blank to keep current' . (!empty($editing['zip_original_name']) ? ': ' . htmlspecialchars($editing['zip_original_name']) : '') . ')' : ''; ?></label>
  <input type="file" name="zip_file" accept=".zip" <?php echo $editing ? '' : 'required'; ?>>
  <small class="admin-sub">This is what buyers download once they unlock the system. Max 40MB.</small>
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
