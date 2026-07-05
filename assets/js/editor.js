// Inkwell — code editor engine.
// Wraps Monaco (the editor that powers VS Code) into a tabbed HTML/CSS/JS
// editor with a live preview iframe and a captured console panel.
// One instance is created per editor block on the page via initInkwellEditor().

window.InkwellEditor = (function () {
  const MONACO_CDN = 'https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.45.0/min/vs';
  let monacoLoading = null;

  function loadMonaco() {
    if (window.monaco) return Promise.resolve(window.monaco);
    if (monacoLoading) return monacoLoading;
    monacoLoading = new Promise((resolve, reject) => {
      const loaderScript = document.createElement('script');
      loaderScript.src = MONACO_CDN + '/loader.js';
      loaderScript.onload = () => {
        window.require.config({ paths: { vs: MONACO_CDN } });
        window.require(['vs/editor/editor.main'], () => resolve(window.monaco));
      };
      loaderScript.onerror = reject;
      document.head.appendChild(loaderScript);
    });
    return monacoLoading;
  }

  function buildPreviewDoc(html, css, js) {
    // Console capture: override console methods inside the iframe and
    // forward calls to the parent window via postMessage.
    const consoleBridge = `
      <script>
        (function () {
          const send = (type, args) => {
            try {
              parent.postMessage({ __inkwell: true, type, args: args.map(a => {
                try { return typeof a === 'object' ? JSON.stringify(a) : String(a); }
                catch (e) { return String(a); }
              }) }, '*');
            } catch (e) {}
          };
          ['log','warn','info'].forEach(m => {
            const orig = console[m];
            console[m] = function (...args) { send(m, args); orig.apply(console, args); };
          });
          window.addEventListener('error', function (e) {
            send('error', [e.message + ' (line ' + e.lineno + ')']);
          });
        })();
      <\/script>`;
    return `<!DOCTYPE html><html><head><style>${css}</style></head><body>${html}${consoleBridge}<script>${js}<\/script></body></html>`;
  }

  function create(config) {
    // config: { rootEl, initialHtml, initialCss, initialJs, showConsoleByDefault }
    const root = config.rootEl;
    const tabsEl = root.querySelector('[data-role=tabs]');
    const paneEl = root.querySelector('[data-role=editor-pane]');
    const runBtn = root.querySelector('[data-role=run]');
    const previewFrame = root.querySelector('[data-role=preview-frame]');
    const consolePane = root.querySelector('[data-role=console-pane]');
    const previewTabs = root.querySelectorAll('[data-role=preview-tab]');

    const state = {
      html: config.initialHtml || '',
      css: config.initialCss || '',
      js: config.initialJs || '',
      active: 'html',
      editors: {},
      models: {}
    };

    function renderTabs() {
      tabsEl.innerHTML = '';
      const labels = { html: 'index.html', css: 'style.css', js: 'script.js' };
      Object.keys(labels).forEach((key) => {
        const btn = document.createElement('button');
        btn.className = 'editor-tab' + (state.active === key ? ' active' : '');
        btn.textContent = labels[key];
        btn.type = 'button';
        btn.addEventListener('click', () => switchTab(key));
        tabsEl.appendChild(btn);
      });
      const spacer = document.createElement('div');
      spacer.className = 'spacer';
      tabsEl.appendChild(spacer);
      if (runBtn) tabsEl.appendChild(runBtn);
    }

    function switchTab(key) {
      state.active = key;
      renderTabs();
      Object.keys(state.editors).forEach((k) => {
        state.editors[k].getDomNode().parentElement.style.display = (k === key) ? 'block' : 'none';
      });
      if (state.editors[key]) state.editors[key].layout();
    }

    function runPreview() {
      Object.keys(state.editors).forEach((k) => { state[k] = state.editors[k].getValue(); });
      consolePane.innerHTML = '';
      previewFrame.srcdoc = buildPreviewDoc(state.html, state.css, state.js);
    }

    function attachConsoleListener() {
      window.addEventListener('message', (e) => {
        if (!e.data || !e.data.__inkwell) return;
        if (e.data.frame && e.data.frame !== previewFrame) return;
        const line = document.createElement('div');
        line.className = 'console-line' + (e.data.type === 'error' ? ' error' : '');
        line.textContent = e.data.args.join(' ');
        consolePane.appendChild(line);
      });
    }

    function switchPreviewTab(target) {
      previewTabs.forEach((t) => t.classList.toggle('active', t.dataset.target === target));
      previewFrame.style.display = target === 'preview' ? 'block' : 'none';
      consolePane.classList.toggle('visible', target === 'console');
    }

    loadMonaco().then((monaco) => {
      const langs = { html: 'html', css: 'css', js: 'javascript' };
      ['html', 'css', 'js'].forEach((key) => {
        const wrap = document.createElement('div');
        wrap.style.height = '100%';
        wrap.style.display = key === 'html' ? 'block' : 'none';
        paneEl.appendChild(wrap);
        const ed = monaco.editor.create(wrap, {
          value: state[key],
          language: langs[key],
          theme: document.documentElement.getAttribute('data-theme') === 'dark' ? 'vs-dark' : 'vs',
          minimap: { enabled: false },
          fontSize: 13,
          fontFamily: "'JetBrains Mono', monospace",
          automaticLayout: true,
          scrollBeyondLastLine: false,
          padding: { top: 12 }
        });
        state.editors[key] = ed;
      });
      renderTabs();
      switchTab('html');
      runPreview();

      window.addEventListener('inkwell:theme', (e) => {
        monaco.editor.setTheme(e.detail === 'dark' ? 'vs-dark' : 'vs');
      });
    });

    if (runBtn) runBtn.addEventListener('click', runPreview);
    previewTabs.forEach((t) => t.addEventListener('click', () => switchPreviewTab(t.dataset.target)));
    attachConsoleListener();
    switchPreviewTab('preview');

    return { runPreview, getState: () => state };
  }

  // Single read/write Monaco pane for languages that can't execute in a
  // browser (C, C++, Java, Python, C#, PHP...). Syntax highlighting only,
  // plus a copy-to-clipboard button — no preview, no console.
  function createStatic(config) {
    // config: { rootEl, code, language }
    const root = config.rootEl;
    const paneEl = root.querySelector('[data-role=editor-pane]');
    const copyBtn = root.querySelector('[data-role=copy]');
    let editor = null;

    loadMonaco().then((monaco) => {
      editor = monaco.editor.create(paneEl, {
        value: config.code || '',
        language: config.language || 'plaintext',
        theme: document.documentElement.getAttribute('data-theme') === 'dark' ? 'vs-dark' : 'vs',
        minimap: { enabled: false },
        fontSize: 13,
        fontFamily: "'JetBrains Mono', monospace",
        automaticLayout: true,
        scrollBeyondLastLine: false,
        padding: { top: 12 }
      });
      window.addEventListener('inkwell:theme', (e) => {
        monaco.editor.setTheme(e.detail === 'dark' ? 'vs-dark' : 'vs');
      });
    });

    if (copyBtn) {
      copyBtn.addEventListener('click', () => {
        if (!editor) return;
        navigator.clipboard.writeText(editor.getValue()).then(() => {
          const original = copyBtn.textContent;
          copyBtn.textContent = '✓ Copied';
          setTimeout(() => { copyBtn.textContent = original; }, 1400);
        });
      });
    }

    return { getEditor: () => editor };
  }

  return { create, createStatic };
})();
