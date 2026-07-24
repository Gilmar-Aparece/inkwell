<?php
// Inside/dashboard page — suppress the marketing topbar (see includes/header.php).
$__hideTopbar = true;
$pageTitle = 'Playground';
include __DIR__ . '/includes/header.php';
$driveActive = 'playground';
$driveCrumbs = [['label' => 'Home', 'href' => '/index.php'], ['label' => 'Playground']];
$driveHideCta = true;
include __DIR__ . '/includes/drive_shell_top.php';
?>
<div class="playground-shell">
  <div class="playground-mode-tabs" data-role="playground-mode-tabs">
    <button class="mode-tab active" data-mode="web" type="button">🌐 Web (HTML/CSS/JS)</button>
    <button class="mode-tab" data-mode="code" type="button">⚙️ Any Language</button>
  </div>

  <div class="playground-grid" id="webGrid" data-role="web-grid">
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

  <div class="playground-grid" id="codeGrid" data-role="code-grid" style="display:none;">
    <div class="editor-col" id="inkwellRunner">
      <div class="editor-tabs">
        <select class="lang-select" data-role="lang-select"><option>Loading languages…</option></select>
        <select class="lang-select" data-role="compiler-select"></select>
        <div class="spacer"></div>
        <button class="run-btn" data-role="code-run" type="button">▶ Run</button>
      </div>
      <div class="playground-editors">
        <div class="editor-pane" data-role="code-editor-pane" style="height:100%;"></div>
      </div>
    </div>
    <div class="preview-wrap">
      <div class="preview-tabs">
        <button class="preview-tab active" data-role="code-preview-tab" data-target="output" type="button">Output</button>
        <button class="preview-tab" data-role="code-preview-tab" data-target="stdin" type="button">Stdin</button>
      </div>
      <div class="console-pane visible" data-role="code-output-pane" style="display:block;"></div>
      <textarea class="stdin-pane" data-role="code-stdin" placeholder="Optional input for the program (stdin)…" style="display:none;"></textarea>
    </div>
  </div>
</div>
<?php include __DIR__ . '/includes/drive_shell_bottom.php'; ?>

<script src="/assets/js/editor.js?v=<?php echo filemtime(__DIR__ . '/assets/js/editor.js'); ?>"></script>
<script>
  document.addEventListener('DOMContentLoaded', function () {
    /* ---------- Web (HTML/CSS/JS) mode — unchanged from before ---------- */
    const SAVE_KEY = 'inkwell_playground_v1';
    let saved = {};
    try { saved = JSON.parse(localStorage.getItem(SAVE_KEY) || '{}'); } catch (e) { saved = {}; }

    const defaults = {
      html: '<h1>Playground</h1>\n<p>Write HTML, CSS and JS, then press Run.</p>',
      css: 'body {\n  font-family: sans-serif;\n  padding: 24px;\n  color: #1c1b1a;\n}',
      js: 'console.log("Open the Console tab to see this.");'
    };

    const webInstance = InkwellEditor.create({
      rootEl: document.getElementById('webGrid'),
      initialHtml: saved.html ?? defaults.html,
      initialCss: saved.css ?? defaults.css,
      initialJs: saved.js ?? defaults.js
    });

    setInterval(function () {
      const state = webInstance.getState();
      if (!state.editors.html) return;
      const toSave = {
        html: state.editors.html.getValue(),
        css: state.editors.css.getValue(),
        js: state.editors.js.getValue()
      };
      localStorage.setItem(SAVE_KEY, JSON.stringify(toSave));
    }, 2000);

    /* ---------- Any Language mode — runs real code via includes/run_code.php ---------- */
    const CODE_SAVE_KEY = 'inkwell_playground_code_v1';
    let savedCode = {};
    try { savedCode = JSON.parse(localStorage.getItem(CODE_SAVE_KEY) || '{}'); } catch (e) { savedCode = {}; }

    const runnerInstance = InkwellEditor.createRunner({
      rootEl: document.getElementById('codeGrid'),
      initialLanguage: savedCode.language || 'Python',
      initialCompiler: savedCode.compiler || '',
      initialCode: savedCode.code || '',
      onStateChange: function (state) {
        localStorage.setItem(CODE_SAVE_KEY, JSON.stringify(state));
      }
    });

    setInterval(function () {
      localStorage.setItem(CODE_SAVE_KEY, JSON.stringify(runnerInstance.getState()));
    }, 3000);

    /* ---------- Mode switcher ---------- */
    const webGrid = document.getElementById('webGrid');
    const codeGrid = document.getElementById('codeGrid');
    document.querySelectorAll('[data-role=playground-mode-tabs] .mode-tab').forEach(function (btn) {
      btn.addEventListener('click', function () {
        document.querySelectorAll('[data-role=playground-mode-tabs] .mode-tab').forEach(function (b) { b.classList.remove('active'); });
        btn.classList.add('active');
        const mode = btn.dataset.mode;
        webGrid.style.display = mode === 'web' ? 'grid' : 'none';
        codeGrid.style.display = mode === 'code' ? 'grid' : 'none';
      });
    });
  });
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
