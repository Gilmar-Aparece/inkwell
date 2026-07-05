# Inkwell — learn to code, with a live editor

A small PHP site in the spirit of W3Schools' "Try it Yourself": short lessons
on the left, a real VS Code-style editor (Monaco) on the right.

Covers **HTML, CSS, JavaScript, PHP, C, C++, Java, Python, and C#** — 27
lessons across 9 languages. ("Every language" isn't realistically possible
in one build, so this is a broad, representative set across the languages
people ask about most; adding another is a data-only change, see below.)

Two editor modes, chosen automatically per language:
- **Runnable** (HTML, CSS, JavaScript): full live preview in an iframe, plus
  a captured console — edit, press Run, see it instantly.
- **Reference** (PHP, C, C++, Java, Python, C#): these only run outside a
  browser, so the editor is a single syntax-highlighted Monaco pane with a
  Copy code button, and a note about running it locally (`gcc`, `javac`,
  `python3`, `dotnet run`, etc., depending on the language).

## What's inside

```
inkwell/
├── index.php            Home page + category grid
├── lesson.php           Lesson page (notes + editor), reads ?cat=&slug=
├── playground.php       Full-screen blank editor, autosaves to the browser
├── data/lessons.php     All lesson content + starter code lives here
├── includes/            header.php, sidebar.php, footer.php
└── assets/
    ├── css/style.css    The whole design system (light + dark theme)
    └── js/
        ├── app.js       Dark/light theme toggle
        └── editor.js    Monaco setup, tabs, preview iframe, console capture
```

## Running it locally

You need PHP installed (no database required — content lives in a PHP array
for now). From inside the `inkwell/` folder:

```bash
php -S localhost:8000
```

Then open `http://localhost:8000/index.php`.

## Deploying to InfinityFree (or any shared PHP host)

1. Zip the contents of the `inkwell/` folder (not the folder itself — the
   files should sit directly in `htdocs/`).
2. In the InfinityFree file manager (or via FTP), upload everything into
   `htdocs/`.
3. No database setup is needed for this version — it runs as soon as the
   files are uploaded, since lessons are stored in `data/lessons.php`.
4. Visit `yourdomain.com/index.php`.

The editor loads Monaco from a CDN (cdnjs), so the host just needs to serve
the PHP/HTML/CSS/JS — no extra server-side dependency to install.

## Adding a lesson

Open `data/lessons.php` and add an entry under the right category.

For a **runnable** category (HTML/CSS/JS — `'runnable' => true`):

```php
'my-slug' => [
  'title'   => 'My New Lesson',
  'summary' => 'One line describing it.',
  'body'    => '<p>Teaching text, HTML allowed.</p>',
  'html'    => '<h1>Starter HTML</h1>',
  'css'     => 'body { font-family: sans-serif; }',
  'js'      => '',
],
```

For a **reference** category (PHP/C/C++/Java/Python/C# — `'runnable' => false`):

```php
'my-slug' => [
  'title'   => 'My New Lesson',
  'summary' => 'One line describing it.',
  'body'    => '<p>Teaching text, HTML allowed.</p>',
  'code'    => "int main() {\n  return 0;\n}",
],
```

Either way it appears automatically in the sidebar, top nav, and prev/next
navigation — no other file needs to change.

## Adding a whole new language

Add a new top-level entry to `$INKWELL_LESSONS` in `data/lessons.php`:

```php
'ruby' => [
  'label'      => 'Ruby',
  'color'      => '#cc342d',
  'tagline'    => 'Optimized for developer happiness',
  'runnable'   => false,
  'monacoLang' => 'ruby',   // must match a Monaco language id
  'filename'   => 'main.rb',
  'lessons'    => [ /* same shape as above */ ],
],
```

It shows up in the homepage grid, sidebar, and top nav with no other changes.

## Notes on the reference-only languages

The live editor only executes HTML/CSS/JS in the browser (via an iframe) —
that's a fundamental browser limitation, not a missing feature. PHP, C,
C++, Java, Python, and C# all need a real compiler/interpreter, so their
lessons use a single read/write Monaco pane (syntax highlighting + a Copy
code button) instead of a live preview. If you want actual execution for
one of these — e.g. wiring Python to Pyodide (runs in-browser via
WebAssembly) or PHP to a `/run-php.php` sandbox endpoint — say the word and
it can be added.

## Next steps you might want

- Move `data/lessons.php` into a MySQL table once you want an admin UI to
  edit lessons without touching code (fits the same connection pattern you
  already use for AI-LDMS).
- Add user accounts + progress tracking (mark lessons complete).
- Add real in-browser execution for Python (Pyodide) or PHP (server
  sandbox) — see note above.

## Exams, certificates, and the admin panel

Each language has one certification exam, unlocked from the last lesson in
that category (and from its homepage card). Passing (≥70% by default, set
per-exam in `data/exams.php`) generates a certificate at
`certificate.php?id=...`, which is printable/savable as a PDF via the
browser's print dialog. Learners can retake an exam as many times as they
like.

Everything is stored as JSON files under `data-store/` — no database:
- `data-store/certificates.json` — every certificate ever issued
- `data-store/config.json` — the signer's name, title, and signature image
- `data-store/admin.json` — the admin password hash

`data-store/.htaccess` blocks direct web access to that folder. On
InfinityFree (or any host), just make sure `data-store/` and
`assets/uploads/` are writable after upload (they are by default).

**Admin panel:** `/admin/login.php` — default password is `ChangeMe123!`,
set in `includes/store.php` (`inkwell_admin_default_password()`). **Change
it immediately** from the dashboard's "Change password" section. From
`/admin/index.php` you can upload the signature image that appears on
certificates, edit the signer's name/title, and see every certificate
that's been issued.

Adding an exam for a new language: add a matching top-level key to
`data/exams.php` (same key as in `data/lessons.php`) with `title`,
`passScore`, and a `questions` array.

## About the source-view deterrent

`assets/js/protect.js` disables right-click and the common "view
source"/DevTools shortcuts (Ctrl+U, F12, Ctrl+Shift+I/J/C), showing a
message pointing people to Gilmar Aparece instead. Worth knowing: this is
a deterrent, not real protection — any browser has to download and can
therefore show the HTML/CSS/JS it renders, and DevTools can be reopened in
ways a page can't reliably detect (undocking, remote debugging, disabling
JS, etc.). It stops casual right-click-and-view-source, nothing more.
