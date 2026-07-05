<?php
$pageTitle = 'Playground';
include __DIR__ . '/includes/header.php';
?>
<div class="playground-shell">
  <div class="playground-grid">
    <div class="editor-col" id="inkwellEditor">
      <div class="editor-tabs" data-role="tabs"></div>
      <button class="run-btn" data-role="run" type="button">▶ Run</button>
      <div class="playground-editors">
        <div class="editor-pane" data-role="editor-pane" style="height:100%;"></div>
      </div>
    </div>
    <div class="preview-wrap">
      <div class="preview-tabs">
        <button class="preview-tab active" data-role="preview-tab" data-target="preview" type="button">Preview</button>
        <button class="preview-tab" data-role="preview-tab" data-target="console" type="button">Console</button>
      </div>
      <iframe class="preview-frame" data-role="preview-frame" title="Live preview" sandbox="allow-scripts"></iframe>
      <div class="console-pane" data-role="console-pane"></div>
    </div>
  </div>
</div>

<script src="/assets/js/editor.js"></script>
<script>
  document.addEventListener('DOMContentLoaded', function () {
    const SAVE_KEY = 'inkwell_playground_v1';
    let saved = {};
    try { saved = JSON.parse(localStorage.getItem(SAVE_KEY) || '{}'); } catch (e) { saved = {}; }

    const defaults = {
      html: '<h1>Playground</h1>\n<p>Write HTML, CSS and JS, then press Run.</p>',
      css: 'body {\n  font-family: sans-serif;\n  padding: 24px;\n  color: #1c1b1a;\n}',
      js: 'console.log("Open the Console tab to see this.");'
    };

    // rootEl is the shared grid wrapping both the editor column and the
    // preview column, so editor.js's internal queries can find both sides.
    const instance = InkwellEditor.create({
      rootEl: document.querySelector('.playground-grid'),
      initialHtml: saved.html ?? defaults.html,
      initialCss: saved.css ?? defaults.css,
      initialJs: saved.js ?? defaults.js
    });

    setInterval(function () {
      const state = instance.getState();
      if (!state.editors.html) return;
      const toSave = {
        html: state.editors.html.getValue(),
        css: state.editors.css.getValue(),
        js: state.editors.js.getValue()
      };
      localStorage.setItem(SAVE_KEY, JSON.stringify(toSave));
    }, 2000);
  });
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
