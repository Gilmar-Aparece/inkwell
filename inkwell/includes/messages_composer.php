<?php
/**
 * The message composer (attach button + menu + textarea + send button),
 * rendered once on initial page load. openThread() in messages.php's
 * inline <script> re-renders the same markup client-side when switching
 * conversations via AJAX — keep the two in sync if you edit this.
 * Expects $withId to be set by the including page.
 */
?>
<form class="msg-composer" id="msgComposerForm" data-with="<?php echo (int) $withId; ?>">
  <div class="msg-pending-atts" id="msgPendingAtts"></div>
  <div class="msg-composer-row">
    <div class="msg-composer-attach-wrap">
      <button type="button" class="msg-composer-attach" id="msgAttachBtn" aria-label="Attach">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
      </button>
      <div class="msg-composer-attach-menu" id="msgAttachMenu">
        <button type="button" data-pick="image">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>
          Photo or image
        </button>
        <button type="button" data-pick="file">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
          File or document
        </button>
      </div>
      <input type="file" id="msgFileImage" accept="image/png,image/jpeg,image/webp,image/gif" multiple hidden>
      <input type="file" id="msgFileGeneric" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv,.zip,.rar" multiple hidden>
    </div>
    <textarea name="body" id="msgComposerInput" placeholder="Write a message…"></textarea>
    <button type="submit" class="btn primary" aria-label="Send">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
    </button>
  </div>
</form>
