<!----<footer>
  Inkwell — 2026
  <br>Made by Gilmar Aparece. · <a href="/admin/login.php">Admin</a>
</footer>--->
<!-- Shared "Delete post?" confirmation — replaces the old browser confirm() alert. -->
<div class="modal-backdrop" id="postDeleteConfirmModal">
  <div class="modal">
    <div class="modal-head">
      <h2>Delete this post?</h2>
      <button type="button" data-modal-close aria-label="Close">✕</button>
    </div>
    <p class="admin-sub">This can't be undone — the post, its photos/video, likes and comments will be permanently removed.</p>
    <div class="modal-actions" style="display:flex; gap:10px; justify-content:flex-end; margin-top:18px;">
      <button type="button" class="btn" data-modal-close>Cancel</button>
      <button type="button" class="btn danger" id="postDeleteConfirmBtn">Delete post</button>
    </div>
  </div>
</div>
<!---<script src="/assets/js/protect.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/protect.js'); ?>"></script>--->
<script src="/assets/js/search-filter.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/search-filter.js'); ?>"></script>
<script src="/assets/js/app.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/app.js'); ?>"></script>
<script src="/assets/js/student-profile.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/student-profile.js'); ?>"></script>
<script src="/assets/js/teacher-profile.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/teacher-profile.js'); ?>"></script>
<script src="/assets/js/post-profile.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/post-profile.js'); ?>"></script>
<script src="/assets/js/post-lightbox.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/post-lightbox.js'); ?>"></script>
<script src="/assets/js/post-menu.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/post-menu.js'); ?>"></script>
<script src="/assets/js/mkt-thumb.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/mkt-thumb.js'); ?>"></script>
<script src="/assets/js/particles.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/particles.js'); ?>"></script>
</body>
</html>
