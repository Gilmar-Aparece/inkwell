// Inkwell — lightweight source deterrent.
// This is a deterrent, not real protection: any browser lets a user view
// the HTML/CSS/JS it renders, and DevTools can be reopened in ways this
// can't reliably detect (e.g. undocking, remote debugging). This only
// blocks the common casual paths: right-click, Ctrl+U, and the usual
// DevTools shortcuts.
(function () {
  const MESSAGE = 'Please contact Gilmar Aparece if you want to see the code.';
  let toastTimer = null;

  function showToast() {
    let el = document.getElementById('protectToast');
    if (!el) {
      el = document.createElement('div');
      el.id = 'protectToast';
      el.className = 'protect-toast';
      document.body.appendChild(el);
    }
    el.textContent = MESSAGE;
    el.classList.add('visible');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => el.classList.remove('visible'), 2600);
  }

  document.addEventListener('contextmenu', function (e) {
    e.preventDefault();
    showToast();
  });

  document.addEventListener('keydown', function (e) {
    const key = e.key ? e.key.toLowerCase() : '';
    const blockedCombo =
      key === 'f12' ||
      (e.ctrlKey && key === 'u') ||
      (e.ctrlKey && e.shiftKey && (key === 'i' || key === 'j' || key === 'c')) ||
      (e.metaKey && e.altKey && (key === 'i' || key === 'j' || key === 'c')); // macOS Cmd+Opt+I/J/C
    if (blockedCombo) {
      e.preventDefault();
      showToast();
    }
  });
})();
