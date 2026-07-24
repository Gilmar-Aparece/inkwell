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

  // Starter snippets shown when a language is first selected, keyed by
  // Wandbox's "language" family name (case-insensitive lookup).
  const STARTER_SNIPPETS = {
    'c': '#include <stdio.h>\n\nint main(void) {\n    printf("Hello, world!\\n");\n    return 0;\n}\n',
    'c++': '#include <iostream>\n\nint main() {\n    std::cout << "Hello, world!" << std::endl;\n    return 0;\n}\n',
    'c#': 'using System;\n\nclass Program {\n    static void Main() {\n        Console.WriteLine("Hello, world!");\n    }\n}\n',
    'java': 'public class Main {\n    public static void main(String[] args) {\n        System.out.println("Hello, world!");\n    }\n}\n',
    'python': 'print("Hello, world!")\n',
    'ruby': 'puts "Hello, world!"\n',
    'php': '<?php\necho "Hello, world!\\n";\n',
    'javascript': 'console.log("Hello, world!");\n',
    'typescript': 'const message: string = "Hello, world!";\nconsole.log(message);\n',
    'go': 'package main\n\nimport "fmt"\n\nfunc main() {\n    fmt.Println("Hello, world!")\n}\n',
    'rust': 'fn main() {\n    println!("Hello, world!");\n}\n',
    'swift': 'print("Hello, world!")\n',
    'kotlin': 'fun main() {\n    println("Hello, world!")\n}\n',
    'scala': 'object Main extends App {\n  println("Hello, world!")\n}\n',
    'perl': 'print "Hello, world!\\n";\n',
    'lua': 'print("Hello, world!")\n',
    'bash': 'echo "Hello, world!"\n',
    'r': 'cat("Hello, world!\\n")\n',
    'sql': 'SELECT \'Hello, world!\';\n',
    'pascal': 'program Hello;\nbegin\n  writeln(\'Hello, world!\');\nend.\n'
  };

  // Maps Wandbox language-family names to Monaco language ids for syntax
  // highlighting. Unknown families just fall back to plaintext.
  const MONACO_LANG_MAP = {
    'c': 'c', 'c++': 'cpp', 'c#': 'csharp', 'java': 'java', 'python': 'python',
    'ruby': 'ruby', 'php': 'php', 'javascript': 'javascript', 'typescript': 'typescript',
    'go': 'go', 'rust': 'rust', 'swift': 'swift', 'kotlin': 'kotlin', 'scala': 'scala',
    'perl': 'perl', 'lua': 'lua', 'bash': 'shell', 'r': 'r', 'sql': 'sql', 'pascal': 'pascal',
    'coffeescript': 'coffeescript', 'groovy': 'ini', 'haskell': 'plaintext'
  };

  function starterFor(language) {
    return STARTER_SNIPPETS[(language || '').toLowerCase()] || '// Write your ' + language + ' code here\n';
  }

  function monacoLangFor(language) {
    return MONACO_LANG_MAP[(language || '').toLowerCase()] || 'plaintext';
  }

  /**
   * "Any Language" runner: single Monaco editor + language/compiler
   * pickers, backed by includes/run_code.php (which relays to Wandbox
   * since real compilers can't run in a browser tab).
   */
  function createRunner(config) {
    // config: { rootEl, endpoint, onStateChange }
    const root = config.rootEl;
    const endpoint = config.endpoint || '/includes/run_code.php';
    const paneEl = root.querySelector('[data-role=code-editor-pane]');
    const langSelect = root.querySelector('[data-role=lang-select]');
    const compilerSelect = root.querySelector('[data-role=compiler-select]');
    const runBtn = root.querySelector('[data-role=code-run]');
    const outputPane = root.querySelector('[data-role=code-output-pane]');
    const stdinBox = root.querySelector('[data-role=code-stdin]');
    const previewTabs = root.querySelectorAll('[data-role=code-preview-tab]');

    let editor = null;
    let languages = {};
    let running = false;

    function setOutput(html) {
      outputPane.innerHTML = html;
    }

    function escapeHtml(s) {
      return String(s).replace(/[&<>]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;' }[c]));
    }

    function renderResult(result) {
      if (!result.ok) {
        setOutput('<div class="console-line error">' + escapeHtml(result.error || 'Something went wrong.') + '</div>');
        return;
      }

      // Some compilers only fill compiler_message (combined stdout+stderr)
      // rather than the split compiler_output/compiler_error fields —
      // fall back to it so notes/warnings from those aren't lost.
      const compilerText = result.compiler_error || result.compiler_output || result.compiler_message || '';
      const programText = result.program_output || result.program_message || '';

      // A non-zero exit status is what actually means "this failed" — a
      // compiler writing warnings to stderr on an otherwise-successful
      // build is normal and shouldn't be shown as a scary red error.
      const hasStatus = result.status !== null && result.status !== undefined && result.status !== '';
      const failed = hasStatus && Number(result.status) !== 0;

      const blocks = [];
      if (compilerText) {
        const isErrorLike = failed || !!result.compiler_error;
        blocks.push(
          '<div class="run-output-label">' + (isErrorLike ? 'Compiler error' : 'Compiler notes') + '</div>' +
          '<pre class="run-output-block' + (isErrorLike ? ' error' : '') + '">' + escapeHtml(compilerText) + '</pre>'
        );
      }
      if (result.program_error) {
        blocks.push('<div class="run-output-label">stderr</div><pre class="run-output-block error">' + escapeHtml(result.program_error) + '</pre>');
      }
      blocks.push(
        '<div class="run-output-label">Output' + (hasStatus ? ' <span class="run-exit">(exit ' + escapeHtml(result.status) + ')</span>' : '') + '</div>' +
        '<pre class="run-output-block">' + (programText.trim() === '' ? '<span class="run-empty">(no output)</span>' : escapeHtml(programText)) + '</pre>'
      );
      setOutput(blocks.join(''));
    }

    function populateCompilers(lang) {
      compilerSelect.innerHTML = '';
      const list = languages[lang] || [];
      list.forEach((c) => {
        const opt = document.createElement('option');
        opt.value = c.name;
        opt.textContent = c.label + (c.version ? ' (' + c.version + ')' : '');
        compilerSelect.appendChild(opt);
      });
    }

    function switchLanguage(lang, resetCode) {
      populateCompilers(lang);
      if (editor && window.monaco) {
        window.monaco.editor.setModelLanguage(editor.getModel(), monacoLangFor(lang));
        if (resetCode) editor.setValue(starterFor(lang));
      }
      if (config.onStateChange) config.onStateChange(getState());
    }

    function loadLanguages() {
      langSelect.innerHTML = '<option>Loading languages…</option>';
      fetch(endpoint + '?action=languages')
        .then((r) => r.json())
        .then((data) => {
          if (!data.ok) throw new Error(data.error || 'Could not load languages.');
          languages = data.languages || {};
          langSelect.innerHTML = '';
          Object.keys(languages).forEach((lang) => {
            const opt = document.createElement('option');
            opt.value = lang;
            opt.textContent = lang;
            langSelect.appendChild(opt);
          });
          const initialLang = config.initialLanguage && languages[config.initialLanguage] ? config.initialLanguage : Object.keys(languages)[0];
          if (initialLang) {
            langSelect.value = initialLang;
            populateCompilers(initialLang);
            if (config.initialCompiler && languages[initialLang].some((c) => c.name === config.initialCompiler)) {
              compilerSelect.value = config.initialCompiler;
            }
            if (editor && window.monaco) {
              window.monaco.editor.setModelLanguage(editor.getModel(), monacoLangFor(initialLang));
            }
          }
        })
        .catch((err) => {
          langSelect.innerHTML = '<option>Unavailable</option>';
          setOutput('<div class="console-line error">' + escapeHtml(err.message || 'Could not load the language list. Check your connection and reload.') + '</div>');
        });
    }

    function runCode() {
      if (running || !editor) return;
      const compiler = compilerSelect.value;
      const code = editor.getValue();
      if (!compiler) return;
      running = true;
      runBtn.disabled = true;
      runBtn.textContent = '⏳ Running…';
      setOutput('<div class="console-line">Running…</div>');
      switchPreviewTab('output');

      fetch(endpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'run', compiler, code, stdin: stdinBox ? stdinBox.value : '' })
      })
        .then((r) => r.json())
        .then(renderResult)
        .catch(() => setOutput('<div class="console-line error">Could not reach the run service. Try again.</div>'))
        .finally(() => {
          running = false;
          runBtn.disabled = false;
          runBtn.textContent = '▶ Run';
        });
    }

    function switchPreviewTab(target) {
      previewTabs.forEach((t) => t.classList.toggle('active', t.dataset.target === target));
      if (outputPane) outputPane.style.display = target === 'output' ? 'block' : 'none';
      if (stdinBox) stdinBox.style.display = target === 'stdin' ? 'block' : 'none';
    }

    function getState() {
      return {
        language: langSelect.value,
        compiler: compilerSelect.value,
        code: editor ? editor.getValue() : (config.initialCode || ''),
        stdin: stdinBox ? stdinBox.value : ''
      };
    }

    loadMonaco().then((monaco) => {
      editor = monaco.editor.create(paneEl, {
        value: config.initialCode || starterFor(config.initialLanguage || 'python'),
        language: monacoLangFor(config.initialLanguage || 'python'),
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
      loadLanguages();
    });

    langSelect.addEventListener('change', () => switchLanguage(langSelect.value, true));
    compilerSelect.addEventListener('change', () => { if (config.onStateChange) config.onStateChange(getState()); });
    if (runBtn) runBtn.addEventListener('click', runCode);
    previewTabs.forEach((t) => t.addEventListener('click', () => switchPreviewTab(t.dataset.target)));
    switchPreviewTab('output');

    return { runCode, getState };
  }

  return { create, createStatic, createRunner };
})();
